<?php

declare(strict_types=1);

namespace kim\present\inventorymonitor\form;

use kim\present\inventorymonitor\inventory\SyncInventory;
use kim\present\inventorymonitor\InventoryMonitor;
use kim\present\inventorymonitor\utils\Utils;
use pocketmine\network\mcpe\protocol\ModalFormRequestPacket;
use pocketmine\Player;
use pocketmine\Server;

class SelectPlayerForm extends MenuForm{
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
	 * @var string[]
	 */
	private $playerNames;

	/**
	 * SelectPlayerForm constructor.
	 *
	 * @param InventoryMonitor $plugin
	 * @param Player           $player
	 */
	public function __construct(InventoryMonitor $plugin, Player $player){
		$this->plugin = $plugin;
		$this->player = $player;

		$lang = $plugin->getLanguage();
		parent::__construct($lang->translate("forms.select.title"), $lang->translate("forms.select.text"), []);
	}

	/**
	 * @return SelectPlayerForm[]
	 */
	public static function getInstances() : array{
		return self::$instances;
	}

	/**
	 * @param SelectPlayerForm[] $instances
	 */
	public static function setInstances(array $instances) : void{
		self::$instances = $instances;
	}

	/**
	 * @param Player $player
	 *
	 * @return null|SelectPlayerForm
	 */
	public static function getInstance(Player $player) : ?SelectPlayerForm{
		return self::$instances[$player->getLowerCaseName()] ?? null;
	}

	/**
	 * @param Player $player
	 *
	 * @return null|Form
	 */
	public function onSubmit(Player $player) : ?Form{
		if($this->selectedOption < count($this->playerNames)){
			$playerName = $this->playerNames[$this->selectedOption];
			$syncInventory = SyncInventory::load($playerName);
			if($syncInventory === null){
				$this->player->sendMessage($this->plugin->getLanguage()->translate('commands.generic.player.notFound', [$playerName]));
			}else{
				return new ConfirmForm($this->plugin, $this->player, $syncInventory, $this);
			}
		}
		unset(self::$instances[$this->player->getLowerCaseName()]);
		return null;
	}

	public function sendForm(){
		self::$instances[$this->player->getLowerCaseName()] = $this;
		$this->refreshList();

		$formPacket = new ModalFormRequestPacket();
		$formPacket->formId = (int) $this->plugin->getConfig()->getNested("formId.select");
		$formPacket->formData = json_encode($this->jsonSerialize());
		$this->player->dataPacket($formPacket);
	}

	public function refreshList(){
		$lang = $this->plugin->getLanguage();

		$this->playerNames = [];
		$this->options = [];

		foreach(Server::getInstance()->getOnlinePlayers() as $key => $player){
			$playerName = $player->getName();
			$this->playerNames[] = $playerName;
			$this->options[] = new MenuOption($lang->translate("forms.select.playerName.online", [$playerName]));
		}
		foreach(scandir(Server::getInstance()->getDataPath() . "players/") as $key => $fileName){
			if(substr($fileName, -4) == '.dat'){
				$playerName = substr($fileName, 0, -4);
				if(!Utils::in_arrayi($playerName, $this->playerNames)){
					$this->playerNames[] = $playerName;
					$this->options[] = new MenuOption($lang->translate("forms.select.playerName.offline", [$playerName]));
				}
			}
		}
	}

	/**
	 * @param Player $player
	 *
	 * @return null|Form
	 */
	public function onClose(Player $player) : ?Form{
		unset(self::$instances[$this->player->getLowerCaseName()]);

		return null;
	}
}