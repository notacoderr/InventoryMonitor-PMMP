<?php

declare(strict_types=1);

namespace kim\present\inventorymonitor\listener;

use kim\present\inventorymonitor\form\ConfirmForm;
use kim\present\inventorymonitor\form\SelectPlayerForm;
use kim\present\inventorymonitor\inventory\SyncInventory;
use kim\present\inventorymonitor\InventoryMonitor;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerPreLoginEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\network\mcpe\protocol\ModalFormResponsePacket;

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

	/**
	 * @priority HIGHEST
	 *
	 * @param DataPacketReceiveEvent $event
	 */
	public function onDataPacketReceiveEvent(DataPacketReceiveEvent $event) : void{
		$pk = $event->getPacket();
		if($pk instanceof ModalFormResponsePacket){
			$config = $this->owner->getConfig();
			$player = $event->getPlayer();
			if($pk->formId === (int) $config->getNested("formId.select")){
				$form = SelectPlayerForm::getInstance($player);
			}elseif($pk->formId === (int) $config->getNested("formId.confirm")){
				$form = ConfirmForm::getInstance($player);
			}else{
				return;
			}
			if($form !== null){
				/** @var SelectPlayerForm $newForm */
				$newForm = $form->handleResponse($player, json_decode($pk->formData));
				if($newForm !== null){
					$newForm->sendForm();
				}
			}
			$event->setCancelled();
		}
	}
}