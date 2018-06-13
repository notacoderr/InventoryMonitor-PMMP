<?php

declare(strict_types=1);

namespace kim\present\inventorymonitor\form;

use kim\present\inventorymonitor\inventory\SyncInventory;
use kim\present\inventorymonitor\InventoryMonitor;
use pocketmine\network\mcpe\protocol\ModalFormRequestPacket;
use pocketmine\Player;
use pocketmine\Server;

class ConfirmForm extends ModalForm{
	private static $instances = [];

	/**
	 * @var InventoryMonitor
	 */
	private $plugin;

	/**
	 * @var Player
	 */
	private $player;

	/**
	 * @var SyncInventory
	 */
	private $inventory;

	/**
	 * @var null|SelectPlayerForm
	 */
	private $prevForm;

	/**
	 * @var bool
	 */
	private $isOnline;

	/**
	 * ConfirmForm constructor.
	 *
	 * @param InventoryMonitor $plugin
	 * @param Player           $player
	 * @param SyncInventory    $inventory
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
		$formPacket->formId = (int) $this->plugin->getConfig()->getNested("formId.confirm");
		$formPacket->formData = json_encode($this->jsonSerialize());
		$this->player->dataPacket($formPacket);
	}
}