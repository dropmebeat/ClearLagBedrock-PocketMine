<?php

declare(strict_types=1);

namespace icrafts\clearlagbedrock;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\entity\Entity;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\TaskHandler;
use pocketmine\utils\TextFormat;
use pocketmine\world\World;

final class ClearLagBedrock extends PluginBase{
	private int $countdown = 0;
	private ?TaskHandler $daemonTask = null;

	public function onEnable() : void{
		$this->saveDefaultConfig();
		$this->startDaemon();
		$this->getLogger()->info("ClearLagBedrock loaded successfully.");
	}

	public function onDisable() : void{
		$this->stopDaemon();
	}

	public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool{
		if(strtolower($command->getName()) !== "clearlag"){
			return false;
		}

		$sub = strtolower((string) ($args[0] ?? "help"));
		$perm = "clearlag.command." . $sub;
		if(!$sender->hasPermission($perm)){
			$this->sendPath($sender, "messages.no-permission");
			return true;
		}

		switch($sub){
			case "now":
				$this->sendPath($sender, "messages.instant-start");
				$removed = $this->runClear();
				$this->sendPath($sender, "messages.instant-done", ["%count%" => (string) $removed]);
				return true;

			case "reload":
				$this->sendPath($sender, "messages.reload-start");
				$this->reloadConfig();
				$this->startDaemon();
				$this->sendPath($sender, "messages.reload-done");
				return true;

			case "time":
				$this->sendPath($sender, "messages.time-left", ["%time_left%" => (string) $this->countdown]);
				return true;

			case "version":
				$this->sendPath($sender, "messages.version", ["%version%" => $this->getDescription()->getVersion()]);
				return true;

			case "help":
			default:
				$lines = $this->getConfig()->get("messages.help", []);
				if(is_array($lines)){
					foreach($lines as $line){
						$sender->sendMessage(TextFormat::colorize((string) $line));
					}
				}
				return true;
		}
	}

	private function startDaemon() : void{
		$this->stopDaemon();
		$this->countdown = max(1, (int) $this->getConfig()->get("clear-interval", 600));
		$this->daemonTask = $this->getScheduler()->scheduleRepeatingTask(new class($this) extends \pocketmine\scheduler\Task{
			private ClearLagBedrock $plugin;

			public function __construct(ClearLagBedrock $plugin){
				$this->plugin = $plugin;
			}

			public function onRun() : void{
				$this->plugin->daemonTick();
			}
		}, 20);
	}

	private function stopDaemon() : void{
		if($this->daemonTask !== null){
			$this->daemonTask->cancel();
			$this->daemonTask = null;
		}
	}

	public function daemonTick() : void{
		if($this->countdown <= 0){
			$removed = $this->runClear();
			$this->broadcastPath("messages.daemon-clear-success", [
				"%count%" => (string) $removed,
			]);
			$this->countdown = max(1, (int) $this->getConfig()->get("clear-interval", 600));
			return;
		}

		$warnings = $this->getConfig()->get("warnings", []);
		if(is_array($warnings)){
			foreach($warnings as $warning){
				if((int) $warning === $this->countdown){
					$this->broadcastPath("messages.daemon-warn-message", [
						"%time_left%" => (string) $this->countdown,
					]);
					break;
				}
			}
		}

		$this->countdown--;
	}

	public function runClear() : int{
		$removed = 0;
		foreach($this->getServer()->getWorldManager()->getWorlds() as $world){
			$removed += $this->clearWorld($world);
		}
		return $removed;
	}

	private function clearWorld(World $world) : int{
		$count = 0;
		$players = $world->getPlayers();
		$safeRadius = max(0, (int) $this->getConfig()->get("player-safe-radius", 24));
		$safeRadiusSquared = $safeRadius * $safeRadius;
		$minTicksLived = max(0, (int) round(((float) $this->getConfig()->get("minimum-lived-seconds", 5)) * 20.0));
		$clearNamed = (bool) $this->getConfig()->get("clear-named-entities", false);

		$excludedRaw = $this->getConfig()->get("excluded-entities", []);
		$excluded = [];
		if(is_array($excludedRaw)){
			foreach($excludedRaw as $e){
				$excluded[strtolower((string) $e)] = true;
			}
		}

		foreach($world->getEntities() as $entity){
			if($entity instanceof Player){
				continue;
			}
			if($entity->isClosed()){
				continue;
			}
			if(method_exists($entity, "getTicksLived") && $entity->getTicksLived() < $minTicksLived){
				continue;
			}

			$typeKey = $this->entityTypeKey($entity);
			if(isset($excluded[$typeKey])){
				continue;
			}

			if(!$clearNamed && method_exists($entity, "getNameTag")){
				$nameTag = trim((string) $entity->getNameTag());
				if($nameTag !== ""){
					continue;
				}
			}

			if($safeRadius > 0){
				$ep = $entity->getPosition();
				$closeToPlayer = false;
				foreach($players as $player){
					if($ep->distanceSquared($player->getPosition()) <= $safeRadiusSquared){
						$closeToPlayer = true;
						break;
					}
				}
				if($closeToPlayer){
					continue;
				}
			}

			$entity->flagForDespawn();
			$count++;
		}

		return $count;
	}

	private function entityTypeKey(Entity $entity) : string{
		$class = strtolower((new \ReflectionClass($entity))->getShortName());
		$class = str_replace(["entity", " "], ["", "_"], $class);
		$class = trim($class, "_");
		if($class === "item"){
			return "item";
		}
		return $class;
	}

	/**
	 * @param array<string, string> $replace
	 */
	private function sendPath(CommandSender $sender, string $path, array $replace = []) : void{
		$text = (string) $this->getConfig()->getNested($path, "");
		if($text === ""){
			return;
		}
		$sender->sendMessage(TextFormat::colorize($this->replaceVars($text, $replace)));
	}

	/**
	 * @param array<string, string> $replace
	 */
	private function broadcastPath(string $path, array $replace = []) : void{
		$text = (string) $this->getConfig()->getNested($path, "");
		if($text === ""){
			return;
		}
		$msg = TextFormat::colorize($this->replaceVars($text, $replace));
		foreach($this->getServer()->getOnlinePlayers() as $player){
			$player->sendMessage($msg);
		}
		$this->getLogger()->info(TextFormat::clean($msg));
	}

	/**
	 * @param array<string, string> $replace
	 */
	private function replaceVars(string $text, array $replace) : string{
		$prefix = (string) $this->getConfig()->getNested("messages.prefix", "");
		$text = str_replace("%prefix%", $prefix, $text);
		foreach($replace as $key => $value){
			$text = str_replace($key, $value, $text);
		}
		return $text;
	}
}
