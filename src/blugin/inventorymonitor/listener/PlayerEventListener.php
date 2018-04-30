<?php

declare(strict_types=1);

namespace blugin\inventorymonitor\listener;

use pocketmine\Server;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerPreLoginEvent;
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
     * @param PlayerPreLoginEvent $event
     */
    public function onPlayerPreLoginEvent(PlayerPreLoginEvent $event){
        $playerName = $event->getPlayer()->getLowerCaseName();
        $syncInventory = SyncInventory::$instances[$playerName] ?? null;
        if ($syncInventory !== null) {
            $namedTag = Server::getInstance()->getOfflinePlayerData($playerName);
            $syncInventory->saveToNBT($namedTag);
            Server::getInstance()->saveOfflinePlayerData($playerName, $namedTag);
        }
    }
}