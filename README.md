# <img src="https://rawgit.com/PresentKim/SVG-files/master/plugin-icons/inventorymonitor.svg" height="50" width="50"> InventoryMonitor  
__A plugin for [PMMP](https://pmmp.io) :: Monitoring player inventory!__  
  
[![license](https://img.shields.io/github/license/organization/InventoryMonitor-PMMP.svg?label=License)](LICENSE)
[![release](https://img.shields.io/github/release/organization/InventoryMonitor-PMMP.svg?label=Release)](../../releases/latest)
[![download](https://img.shields.io/github/downloads/organization/InventoryMonitor-PMMP/total.svg?label=Download)](../../releases/latest)
[![Build status](https://ci.appveyor.com/api/projects/status/4yidkhii2i71aipq/branch/master?svg=true)](https://ci.appveyor.com/project/PresentKim/inventorymonitor-pmmp/branch/master)
 
## What is this?   
Inventory monitor is plugin that monitoring player inventory
  
You can see player inventory and modify too.  
Inventory monitor will be sync with player inventory.  
  
  
## Features  
- [x] See player's inventory with real-time sync  
- [x] Modify inventory in sync inventory  
	- [x] with armor inventory  
	- [x] with cursor inventory  
- [x] Support configurable things  
- [x] Check that the plugin is not latest version  
  - [x] If not latest version, show latest release download url  
  
  
## Configurable things  
- [x] Configure the language for messages  
  - [x] in `{SELECTED LANG}/lang.ini` file  
  - [x] Select language in `config.yml` file  
- [x] Configure the command (include subcommands)  
  - [x] in `config.yml` file  
- [x] Configure the permission of command  
  - [x] in `config.yml` file  
- [x] Configure the whether the update is check (default "false")
  - [x] in `config.yml` file  
  
The configuration files is created when the plugin is enabled.  
The configuration files is loaded  when the plugin is enabled.  
  
  
## Command  
Main command : `/inventorymonitor [player name]`  
  
  
## Permission  
| permission                  | default | description  |  
| --------------------------- | ------- | ------------ |  
| inventorymonitor.cmd        | OP      | main command |  
