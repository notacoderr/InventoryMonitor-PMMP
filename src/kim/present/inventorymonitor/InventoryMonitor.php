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
	/** @var InventoryMonitor */
	private static $instance;

	/** @var PluginLang */
	private $language;

	/** @var PluginCommand */
	private $command;

	/**
	 * @return InventoryMonitor
	 */
	public static function getInstance() : InventoryMonitor{
		return self::$instance;
	}

	/**
	 * Called when the plugin is loaded, before calling onEnable()
	 */
	public function onLoad() : void{
		self::$instance = $this;
	}

	/**
	 * Called when the plugin is enabled
	 */
	public function onEnable() : void{
		$dataFolder = $this->getDataFolder();
		if(!file_exists($dataFolder)){
			mkdir($dataFolder, 0777, true);
		}
		$this->saveDefaultConfig();
		$this->reloadConfig();
		$this->language = new PluginLang($this, PluginLang::FALLBACK_LANGUAGE);

		if($this->command !== null){
			$this->getServer()->getCommandMap()->unregister($this->command);
		}
		$this->command = new PluginCommand($this->language->translateString('commands.inventorymonitor'), $this);
		$this->command->setPermission('inventorymonitor.cmd');
		$this->command->setDescription($this->language->translateString('commands.inventorymonitor.description'));
		$this->command->setUsage($this->language->translateString('commands.inventorymonitor.usage'));
		/*
		 * TODO: Support aliases of main command
		 *
		 * if(is_array($aliases = $this->language->getArray('commands.inventorymonitor.aliases'))){
		 * 	$this->command->setAliases($aliases);
		 * }
		 */
		$this->getServer()->getCommandMap()->register('inventorymonitor', $this->command);

		$this->getServer()->getPluginManager()->registerEvents(new InventoryEventListener($this), $this);
		$this->getServer()->getPluginManager()->registerEvents(new PlayerEventListener($this), $this);
	}

	/**
	 * Called when the plugin is disabled
	 * Use this to free open things and finish actions
	 */
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
					$sender->sendMessage($this->language->translateString('commands.generic.player.notFound', [$args[0]]));
				}else{
					$confirmForm = new ConfirmForm($this, $sender, $syncInventory);
					$confirmForm->sendForm();
				}
			}else{
				$selectForm = new SelectPlayerForm($this, $sender);
				$selectForm->sendForm();
			}
		}else{
			$sender->sendMessage($this->language->translateString('commands.generic.onlyPlayer'));
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
}
