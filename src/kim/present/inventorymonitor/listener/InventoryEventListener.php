<?php

/*
 *
 *  ____                           _   _  ___
 * |  _ \ _ __ ___  ___  ___ _ __ | |_| |/ (_)_ __ ___
 * | |_) | '__/ _ \/ __|/ _ \ '_ \| __| ' /| | '_ ` _ \
 * |  __/| | |  __/\__ \  __/ | | | |_| . \| | | | | | |
 * |_|   |_|  \___||___/\___|_| |_|\__|_|\_\_|_| |_| |_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the MIT License. see <https://opensource.org/licenses/MIT>.
 *
 * @author  PresentKim (debe3721@gmail.com)
 * @link    https://github.com/PresentKim
 * @license https://opensource.org/licenses/MIT MIT License
 *
 *   (\ /)
 *  ( . .) ♥
 *  c(")(")
 */

declare(strict_types=1);

namespace kim\present\inventorymonitor\listener;

use kim\present\inventorymonitor\inventory\group\{ArmorGroup, CursorGroup, InvGroup};
use kim\present\inventorymonitor\inventory\SyncInventory;
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\event\Listener;
use pocketmine\inventory\ArmorInventory;
use pocketmine\inventory\PlayerCursorInventory;
use pocketmine\inventory\PlayerInventory;
use pocketmine\inventory\transaction\action\SlotChangeAction;

class InventoryEventListener implements Listener{

	/**
	 * @priority MONITOR
	 *
	 * @param InventoryTransactionEvent $event
	 */
	public function onInventoryTransactionEvent(InventoryTransactionEvent $event) : void{
		foreach($event->getTransaction()->getActions() as $key => $action){
			if($action instanceof SlotChangeAction){
				$inventory = $action->getInventory();
				if($inventory instanceof SyncInventory){
					if(!$inventory->isValidSlot($action->getSlot())){
						$event->setCancelled();
					}
				}elseif($inventory instanceof PlayerCursorInventory){
					$syncInventory = SyncInventory::get($inventory->getHolder()->getName());
					if($syncInventory !== null){
						$syncInventory->setItem(CursorGroup::START, $action->getTargetItem(), true, false);
					}
				}elseif($inventory instanceof ArmorInventory){
					$syncInventory = SyncInventory::get($inventory->getHolder()->getName());
					if($syncInventory !== null){
						$syncInventory->setItem($action->getSlot() + ArmorGroup::START, $action->getTargetItem());
					}
				}elseif($inventory instanceof PlayerInventory){
					$syncInventory = SyncInventory::get($inventory->getHolder()->getName());
					if($syncInventory !== null){
						$syncInventory->setItem($action->getSlot() + InvGroup::START, $action->getTargetItem());
					}
				}
			}
		}
	}
}
