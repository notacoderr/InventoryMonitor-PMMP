<?php

namespace presentkim\inventorymonitor\listener;

use pocketmine\Player;
use pocketmine\event\Listener;
use pocketmine\event\entity\{
  EntityArmorChangeEvent, EntityInventoryChangeEvent
};
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\inventory\transaction\action\SlotChangeAction;
use pocketmine\inventory\PlayerCursorInventory;
use presentkim\inventorymonitor\InventoryMonitor as Plugin;
use presentkim\inventorymonitor\inventory\SyncInventory;

class InventoryEventListener implements Listener{

    /** @var Plugin */
    private $owner = null;

    public function __construct(){
        $this->owner = Plugin::getInstance();
    }

    /**
     * @priority MONITOR
     *
     * @param EntityInventoryChangeEvent $event
     */
    public function onEntityInventoryChangeEvent(EntityInventoryChangeEvent $event){
        if (!$event->isCancelled()) {
            $player = $event->getEntity();
            if ($player instanceof Player) {
                $syncInventory = SyncInventory::$instances[$player->getLowerCaseName()] ?? null;
                if ($syncInventory !== null) {
                    $syncInventory->setItem($event->getSlot() + ($event instanceof EntityArmorChangeEvent ? 46 : 0), $event->getNewItem(), true, false);
                }
            }
        }
    }

    /**
     * @priority MONITOR
     *
     * @param InventoryTransactionEvent $event
     */
    public function onInventoryTransactionEvent(InventoryTransactionEvent $event){
        $transaction = $event->getTransaction();
        foreach ($transaction->getActions() as $key => $action) {
            if ($action instanceof SlotChangeAction) {
                $inventory = $action->getInventory();
                if ($inventory instanceof SyncInventory) {
                    if (!$inventory->isValidSlot($action->getSlot())) {
                        $event->setCancelled();
                    }
                } elseif ($inventory instanceof PlayerCursorInventory) {
                    $player = $inventory->getHolder();
                    $syncInventory = SyncInventory::$instances[$player->getLowerCaseName()] ?? null;
                    if ($syncInventory !== null) {
                        $syncInventory->setItem(52, $action->getTargetItem(), true, false);
                    }
                }
            }
        }
    }
}