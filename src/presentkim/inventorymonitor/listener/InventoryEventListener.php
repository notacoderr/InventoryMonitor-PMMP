<?php

namespace presentkim\inventorymonitor\listener;

use pocketmine\Player;
use pocketmine\event\Listener;
use pocketmine\event\entity\EntityInventoryChangeEvent;
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\inventory\transaction\action\SlotChangeAction;
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
                    $syncInventory->setItem($event->getSlot(), $event->getNewItem(), true, false);
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
            if ($action instanceof SlotChangeAction && $action->getInventory() instanceof SyncInventory) {
                if ($action->getSlot() >= 36) {// 36 = PlayerInventory::getDefaultSize();
                    $event->setCancelled();
                }
            }
        }
    }
}