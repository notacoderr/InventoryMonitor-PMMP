<?php

declare(strict_types=1);

namespace kim\present\inventorymonitor\listener;

use kim\present\inventorymonitor\inventory\group\{
	ArmorGroup, CursorGroup, InvGroup
};
use kim\present\inventorymonitor\inventory\SyncInventory;
use kim\present\inventorymonitor\InventoryMonitor;
use pocketmine\event\entity\{
	EntityArmorChangeEvent, EntityInventoryChangeEvent
};
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\event\Listener;
use pocketmine\inventory\PlayerCursorInventory;
use pocketmine\inventory\transaction\action\SlotChangeAction;
use pocketmine\Player;

class InventoryEventListener implements Listener{
	/**
	 * @var InventoryMonitor
	 */
	private $owner;

	/**
	 * InventoryEventListener constructor.
	 *
	 * @param InventoryMonitor $owner
	 */
	public function __construct(InventoryMonitor $owner){
		$this->owner = $owner;
	}

	/**
	 * @priority MONITOR
	 *
	 * @param EntityInventoryChangeEvent $event
	 */
	public function onEntityInventoryChangeEvent(EntityInventoryChangeEvent $event) : void{
		if(!$event->isCancelled()){
			$player = $event->getEntity();
			if($player instanceof Player){
				$syncInventory = SyncInventory::get($player->getName());
				if($syncInventory !== null){
					$slot = $event->getSlot() + ($event instanceof EntityArmorChangeEvent ? ArmorGroup::START : InvGroup::START);
					$syncInventory->setItem($slot, $event->getNewItem(), true, false);
				}
			}
		}
	}

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
				}
			}
		}
	}
}
