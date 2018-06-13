<?php

declare(strict_types=1);

namespace kim\present\inventorymonitor;

use kim\present\inventorymonitor\form\ConfirmForm;
use kim\present\inventorymonitor\form\SelectPlayerForm;
use kim\present\inventorymonitor\inventory\SyncInventory;
use kim\present\inventorymonitor\lang\PluginLang;
use kim\present\inventorymonitor\listener\{
	InventoryEventListener, PlayerEventListener
};
use pocketmine\command\{
	Command, CommandExecutor, CommandSender, PluginCommand
};
use pocketmine\Player;
use pocketmine\plugin\PluginBase;

class InventoryMonitor extends PluginBase implements CommandExecutor{
	/**
	 * @var InventoryMonitor
	 */
	private static $instance;

	/**
	 * @var PluginCommand
	 */
	private $command;

	/**
	 * @var PluginLang
	 */
	private $language;

	/**
	 * @return InventoryMonitor
	 */
	public static function getInstance() : InventoryMonitor{
		return self::$instance;
	}

	public function onLoad() : void{
		self::$instance = $this;
	}

	public function onEnable() : void{
		$dataFolder = $this->getDataFolder();
		if(!file_exists($dataFolder)){
			mkdir($dataFolder, 0777, true);
		}
		$this->saveDefaultConfig();
		$this->reloadConfig();
		$this->language = new PluginLang($this);

		if($this->command !== null){
			$this->getServer()->getCommandMap()->unregister($this->command);
		}
		$this->command = new PluginCommand($this->language->translate('commands.inventorymonitor'), $this);
		$this->command->setPermission('inventorymonitor.cmd');
		$this->command->setDescription($this->language->translate('commands.inventorymonitor.description'));
		$this->command->setUsage($this->language->translate('commands.inventorymonitor.usage'));
		if(is_array($aliases = $this->language->getArray('commands.inventorymonitor.aliases'))){
			$this->command->setAliases($aliases);
		}
		$this->getServer()->getCommandMap()->register('inventorymonitor', $this->command);

		$this->getServer()->getPluginManager()->registerEvents(new InventoryEventListener($this), $this);
		$this->getServer()->getPluginManager()->registerEvents(new PlayerEventListener($this), $this);
	}

	public function onDisable() : void{
		foreach(SyncInventory::getAll() as $playerName => $syncInventory){
			$syncInventory->delete();
		}
	}

	/**
	 * @param CommandSender $sender
	 * @param Command       $command
	 * @param string        $label
	 * @param string[]      $args
	 *
	 * @return bool
	 */
	public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool{
		if($sender instanceof Player){
			if(isset($args[0])){
				$syncInventory = SyncInventory::load(strtolower($args[0]));
				if($syncInventory === null){
					$sender->sendMessage($this->language->translate('commands.generic.player.notFound', [$args[0]]));
				}else{
					$confirmForm = new ConfirmForm($this, $sender, $syncInventory);
					$confirmForm->sendForm();
				}
			}else{
				$selectForm = new SelectPlayerForm($this, $sender);
				$selectForm->sendForm();
			}
		}else{
			$sender->sendMessage($this->language->translate('commands.generic.onlyPlayer'));
		}
		return true;
	}

	/**
	 * @param string $name = ''
	 *
	 * @return PluginCommand
	 */
	public function getCommand(string $name = '') : PluginCommand{
		return $this->command;
	}

	/**
	 * @return PluginLang
	 */
	public function getLanguage() : PluginLang{
		return $this->language;
	}

	/**
	 * @return string
	 */
	public function getSourceFolder() : string{
		$pharPath = \Phar::running();
		if(empty($pharPath)){
			return dirname(__FILE__, 5) . DIRECTORY_SEPARATOR;
		}else{
			return $pharPath . DIRECTORY_SEPARATOR;
		}
	}
}
