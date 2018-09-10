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
	private $plugin;

	/**
	 * PlayerEventListener constructor.
	 *
	 * @param InventoryMonitor $plugin
	 */
	public function __construct(InventoryMonitor $plugin){
		$this->plugin = $plugin;
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
			$config = $this->plugin->getConfig();
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