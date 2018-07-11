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
		//Save default resources
		$this->saveResource("lang/eng/lang.ini", false);
		$this->saveResource("lang/kor/lang.ini", false);
		$this->saveResource("lang/language.list", false);

		//Load config file
		$this->saveDefaultConfig();
		$this->reloadConfig();
		$config = $this->getConfig();

		//Load language file
		$this->language = new PluginLang($this, $config->getNested("settings.language"));
		$this->getLogger()->info($this->language->translateString("language.selected", [$this->language->getName(), $this->language->getLang()]));

		//Register main command
		$this->command = new PluginCommand($config->getNested("command.name"), $this);
		$this->command->setPermission("inventorymonitor.cmd");
		$this->command->setAliases($config->getNested("command.aliases"));
		$this->command->setUsage($this->language->translateString("commands.inventorymonitor.usage"));
		$this->command->setDescription($this->language->translateString("commands.inventorymonitor.description"));
		$this->getServer()->getCommandMap()->register($this->getName(), $this->command);

		//Register event listeners
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
					$sender->sendMessage($this->language->translateString("commands.generic.player.notFound", [$args[0]]));
				}else{
					$confirmForm = new ConfirmForm($this, $sender, $syncInventory);
					$confirmForm->sendForm();
				}
			}else{
				$selectForm = new SelectPlayerForm($this, $sender);
				$selectForm->sendForm();
			}
		}else{
			$sender->sendMessage($this->language->translateString("commands.generic.onlyPlayer"));
		}
		return true;
	}

	/**
	 * @Override for multilingual support of the config file
	 *
	 * @return bool
	 */
	public function saveDefaultConfig() : bool{
		$resource = $this->getResource("lang/{$this->getServer()->getLanguage()->getLang()}/config.yml");
		if($resource === null){
			$resource = $this->getResource("lang/" . PluginLang::FALLBACK_LANGUAGE . "/config.yml");
		}

		if(!file_exists($configFile = $this->getDataFolder() . "config.yml")){
			$ret = stream_copy_to_stream($resource, $fp = fopen($configFile, "wb")) > 0;
			fclose($fp);
			fclose($resource);
			return $ret;
		}
		return false;
	}

	/**
	 * @param string $name = ""
	 *
	 * @return PluginCommand
	 */
	public function getCommand(string $name = "") : PluginCommand{
		return $this->command;
	}

	/**
	 * @return PluginLang
	 */
	public function getLanguage() : PluginLang{
		return $this->language;
	}
}
