# InventoryMonitor [![license](https://img.shields.io/github/license/PMMPPlugin/InventoryMonitor.svg?label=License)](LICENSE)
[![icon](assets/icon/192x192.png?raw=true)]()  
[![release](https://img.shields.io/github/release/PMMPPlugin/InventoryMonitor.svg?label=Release) ![download](https://img.shields.io/github/downloads/PMMPPlugin/InventoryMonitor/total.svg?label=Download)](https://github.com/PMMPPlugin/InventoryMonitor/releases/latest)
  
<br/><br/>
  
A plugin monitoring player's inventory for PocketMine-MP  
- [x] See player's inventory with real-time sync
- [x] Modify inventory in sync inventory
- [x] with armor inventory
- [x] with cursor inventory
  
## Command
Main command : `/inventorymonitor <view | lang | reload>`

| subcommand | arguments              | description                 |
| ---------- | ---------------------- | --------------------------- |
| View       | \[player name\]        | Open player's inventory     |
| Lang       | \<language prefix\>    | Load default lang file      |
| Reload     |                        | Reload all data             |




## Permission
| permission                  | default  | description          |
| --------------------------- | -------- | -------------------- |
| inventorymonitor.cmd        | OP       | main command         |
|                             |          |                      |
| inventorymonitor.cmd.view   | OP       | view subcommand      |
| inventorymonitor.cmd.lang   | OP       | lang subcommand      |
| inventorymonitor.cmd.reload | OP       | reload subcommand    |
  
<br/><br/>
  
## Required API
- PocketMine-MP : higher than [Build #937](https://jenkins.pmmp.io/job/PocketMine-MP/937)
  
<br/><br/>
  
## Form
![form](assets/screenshot/form.jpg?raw=true)
  
<br/><br/>
  
## Demo
![demo](assets/screenshot/demo.gif?raw=true)