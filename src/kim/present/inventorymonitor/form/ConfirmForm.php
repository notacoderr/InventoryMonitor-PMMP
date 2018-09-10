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

namespace kim\present\inventorymonitor\form;

use kim\present\inventorymonitor\inventory\SyncInventory;
use kim\present\inventorymonitor\InventoryMonitor;
use pocketmine\{
	Player, Server
};
use pocketmine\network\mcpe\protocol\ModalFormRequestPacket;

class ConfirmForm extends ModalForm{
	private static $instances = [];

	/**
	 * @return ConfirmForm[]
	 */
	public static function getInstances() : array{
		return self::$instances;
	}

	/**
	 * @param ConfirmForm[] $instances
	 */
	public static function setInstances(array $instances) : void{
		self::$instances = $instances;
	}

	/**
	 * @param Player $player
	 *
	 * @return null|ConfirmForm
	 */
	public static function getInstance(Player $player) : ?ConfirmForm{
		return self::$instances[$player->getLowerCaseName()] ?? null;
	}

	/** @var InventoryMonitor */
	private $plugin;

	/** @var Player */
	private $player;

	/** @var SyncInventory */
	private $inventory;

	/** @var null|SelectPlayerForm */
	private $prevForm;

	/** @var bool */
	private $isOnline;

	/**
	 * ConfirmForm constructor.
	 *
	 * @param InventoryMonitor      $plugin
	 * @param Player                $player
	 * @param SyncInventory         $inventory
	 * @param null|SelectPlayerForm $prevForm = null
	 */
	public function __construct(InventoryMonitor $plugin, Player $player, SyncInventory $inventory, ?SelectPlayerForm $prevForm = null){
		$this->plugin = $plugin;
		$this->player = $player;
		$this->inventory = $inventory;
		$this->prevForm = $prevForm;
		$targetName = $inventory->getPlayerName();
		$this->isOnline = Server::getInstance()->getPlayerExact($targetName) instanceof Player;

		$lang = $plugin->getLanguage();
		parent::__construct($lang->translate("forms.confirm.title"), $lang->translate("forms.confirm.text." . ($this->isOnline ? "online" : "offline"), [$targetName]));
	}

	/**
	 * @param Player $player
	 *
	 * @return null|Form
	 */
	public function onSubmit(Player $player) : ?Form{
		if($this->choice){
			$this->player->addWindow($this->inventory);
		}elseif($this->prevForm !== null){
			$this->prevForm->sendForm();
		}
		unset(self::$instances[$this->player->getLowerCaseName()]);
		return null;
	}


	public function sendForm(){
		self::$instances[$this->player->getLowerCaseName()] = $this;
		$this->inventory->sendFakeChestBlock($this->player);

		$formPacket = new ModalFormRequestPacket();
		$formPacket->formId = (int) $this->plugin->getConfig()->getNested("settings.formId.confirm");
		$formPacket->formData = json_encode($this->jsonSerialize());
		$this->player->sendDataPacket($formPacket);
	}
}