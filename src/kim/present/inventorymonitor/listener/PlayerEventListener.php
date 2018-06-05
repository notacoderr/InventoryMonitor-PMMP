<?php

declare(strict_types=1);

namespace kim\present\inventorymonitor\listener;

use kim\present\inventorymonitor\inventory\SyncInventory;
use kim\present\inventorymonitor\InventoryMonitor;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerPreLoginEvent;

class PlayerEventListener implements Listener{
	/**
	 * @var InventoryMonitor
	 */
	private $owner;

	/**
	 * PlayerEventListener constructor.
	 *
	 * @param InventoryMonitor $owner
	 */
	public function __construct(InventoryMonitor $owner){
		$this->owner = $owner;
	}

	/**
	 * @priority LOWEST
	 *
	 * @param PlayerPreLoginEvent $event
	 */
	public function onPlayerPreLoginEvent(PlayerPreLoginEvent $event){
		$playerName = $event->getPlayer()->getLowerCaseName();
		$syncInventory = SyncInventory::get($playerName);
		if($syncInventory !== null){
			$syncInventory->save();
		}
	}
}