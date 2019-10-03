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

namespace kim\present\inventorymonitor;

use kim\present\inventorymonitor\inventory\SyncInventory;
use kim\present\inventorymonitor\lang\PluginLang;
use kim\present\inventorymonitor\listener\{InventoryEventListener, PlayerEventListener};
use kim\present\inventorymonitor\task\CheckUpdateAsyncTask;
use pocketmine\command\{Command, CommandSender, PluginCommand};
use pocketmine\permission\{Permission, PermissionManager, PermissionParser};
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;

class InventoryMonitor extends PluginBase{
	/** @var InventoryMonitor */
	private static $instance;

	/**
	 * @return InventoryMonitor
	 */
	public static function getInstance() : InventoryMonitor{
		return self::$instance;
	}

	/** @var PluginLang */
	private $language;

	/** @var PluginCommand */
	private $command;

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
		$config = $this->getConfig();

		//Check latest version
		if($config->getNested("settings.update-check", false)){
			$this->getServer()->getAsyncPool()->submitTask(new CheckUpdateAsyncTask());
		}

		//Load language file
		$this->language = new PluginLang($this, $config->getNested("settings.language"));
		$this->getLogger()->info($this->language->translate("language.selected", [$this->language->getName(), $this->language->getLang()]));

		//Register main command
		$this->command = new PluginCommand($config->getNested("command.name"), $this);
		$this->command->setPermission("inventorymonitor.cmd");
		$this->command->setAliases($config->getNested("command.aliases"));
		$this->command->setUsage($this->language->translate("commands.inventorymonitor.usage"));
		$this->command->setDescription($this->language->translate("commands.inventorymonitor.description"));
		$this->getServer()->getCommandMap()->register($this->getName(), $this->command);

		//Load permission's default value from config
		$permissions = PermissionManager::getInstance()->getPermissions();
		$defaultValue = $config->getNested("permission.main");
		if($defaultValue !== null){
			$permissions["inventorymonitor.cmd"]->setDefault(PermissionParser::defaultFromString($config->getNested("permission.main")));
		}

		//Register event listeners
		$this->getServer()->getPluginManager()->registerEvents(new InventoryEventListener(), $this);
		$this->getServer()->getPluginManager()->registerEvents(new PlayerEventListener(), $this);
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
		if(!$sender instanceof Player){
			$sender->sendMessage($this->language->translate("commands.generic.onlyPlayer"));
			return true;
		}

		if(!isset($args[0])){
			return false;
		}

		$syncInventory = SyncInventory::load($args[0]);
		if($syncInventory !== null){
			$sender->setCurrentWindow($syncInventory);
			return true;
		}

		$player = $this->getServer()->getPlayer($args[0]);
		if($player instanceof Player){
			$sender->setCurrentWindow(SyncInventory::load($player->getName()));
			return true;
		}

		$sender->sendMessage($this->language->translate("commands.generic.player.notFound", [$args[0]]));
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

		if(file_exists($configFile = $this->getDataFolder() . "config.yml")){
			return false;
		}

		$ret = stream_copy_to_stream($resource, $fp = fopen($configFile, "wb")) > 0;
		fclose($fp);
		fclose($resource);
		return $ret;
	}

	/**
	 * @return PluginLang
	 */
	public function getLanguage() : PluginLang{
		return $this->language;
	}
}
