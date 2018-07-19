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
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author  PresentKim (debe3721@gmail.com)
 * @link    https://github.com/PresentKim
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0.0
 *
 *   (\ /)
 *  ( . .) ♥
 *  c(")(")
 */

declare(strict_types=1);

namespace kim\present\inventorymonitor\listener;

use kim\present\inventorymonitor\form\{
	ConfirmForm, SelectPlayerForm
};
use kim\present\inventorymonitor\inventory\SyncInventory;
use kim\present\inventorymonitor\InventoryMonitor;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerPreLoginEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\network\mcpe\protocol\ModalFormResponsePacket;

class PlayerEventListener implements Listener{
	/** @var InventoryMonitor */
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
			if($pk->formId === (int) $config->getNested("settings.formId.select")){
				$form = SelectPlayerForm::getInstance($player);
			}elseif($pk->formId === (int) $config->getNested("settings.formId.confirm")){
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