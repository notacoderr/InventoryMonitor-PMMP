<?php

declare(strict_types=1);

namespace blugin\inventorymonitor\listener;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use blugin\inventorymonitor\InventoryMonitor as Plugin;
use blugin\inventorymonitor\inventory\SyncInventory;

class PlayerEventListener implements Listener{

    /** @var Plugin */
    private $owner = null;

    public function __construct(){
        $this->owner = Plugin::getInstance();
    }

    /**
     * @priority LOWEST
     *
     * @param PlayerJoinEvent $event
     */
    public function onPlayerJoinEvent(PlayerJoinEvent $event){
        $player = $event->getPlayer();
        $syncInventory = SyncInventory::$instances[$player->getLowerCaseName()] ?? null;
        if ($syncInventory !== null) {
            $syncInventory->saveToPlayer($player);
        }
    }
}