# ClearLag

**ClearLag** is an essential performance-optimization plugin for **PocketMine-MP**. It is designed to reduce server lag by automatically clearing ground items and managing entities, ensuring a smooth experience for your players.

## Features

*   **Entity Clearing:** Automatically removes dropped items and specific entities to free up server resources.
*   **Timed Broadcasts:** Sends warning messages before a clearing task starts (e.g., "Items will be cleared in 60 seconds!").
*   **Performance Monitoring:** Includes tools to check server TPS (Ticks Per Second) and RAM usage.
*   **Highly Configurable:** Customize intervals, messages, and which items should be ignored (e.g., keep diamonds or rare loot).
*   **Manual Control:** Force a clear at any time with a simple command.

## Commands


| Command | Description | Permission |
|---------|-------------|------------|
| `/clearlag clear` | Manually clear ground items | `clearlag.admin` |
| `/clearlag tps` | View current server performance (TPS) | `clearlag.admin` |
| `/clearlag reload` | Reload the configuration file | `clearlag.admin` |

## Configuration

Example of the `config.yml` setup:

```yaml
# ClearLag Configuration
settings:
  # Time in seconds between clears
  interval: 300
  # List of worlds to clean
  worlds: ["world", "survival"]
  # Entities to protect (will not be removed)
  exempt_items: 
    - "minecraft:diamond"
    - "minecraft:totem_of_undying"

messages:
  warning: "§e[ClearLag] §fAll ground items will be removed in §c{seconds}§f seconds!"
  cleared: "§e[ClearLag] §fRemoved §a{count}§f ground items."
