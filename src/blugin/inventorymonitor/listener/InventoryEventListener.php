<?php

declare(strict_types=1);

namespace blugin\inventorymonitor\listener;

use pocketmine\Player;
use pocketmine\event\Listener;
use pocketmine\event\entity\{
  EntityArmorChangeEvent, EntityInventoryChangeEvent
};
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\inventory\transaction\action\SlotChangeAction;
use pocketmine\inventory\PlayerCursorInventory;
use blugin\inventorymonitor\InventoryMonitor;
use blugin\inventorymonitor\inventory\SyncInventory;
use blugin\inventorymonitor\inventory\group\{
    InvGroup, ArmorGroup, CursorGroup
};

class InventoryEventListener implements Listener{

    /** @var InventoryMonitor */
    private $owner = null;

    public function __construct(InventoryMonitor $owner){
        $this->owner = $owner;
    }

    /**
     * @priority MONITOR
     *
     * @param EntityInventoryChangeEvent $event
     */
    public function onEntityInventoryChangeEvent(EntityInventoryChangeEvent $event) : void{
        if (!$event->isCancelled()) {
            $player = $event->getEntity();
            if ($player instanceof Player) {
                $syncInventory = SyncInventory::get($player->getName());
                if ($syncInventory !== null) {
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
                    $syncInventory = SyncInventory::get($player->getName());
                    if ($syncInventory !== null) {
                        $syncInventory->setItem(CursorGroup::START, $action->getTargetItem(), true, false);
                    }
                }
            }
        }
    }
}