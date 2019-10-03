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

namespace kim\present\inventorymonitor\inventory;

use kim\present\inventorymonitor\inventory\group\{ArmorGroup, CursorGroup, InvGroup, SlotGroup};
use kim\present\inventorymonitor\InventoryMonitor;
use pocketmine\item\ItemFactory;
use pocketmine\nbt\TreeRoot;
use pocketmine\network\mcpe\serializer\NetworkNbtSerializer;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\block\BlockLegacyIds;
use pocketmine\inventory\{BaseInventory, BlockInventory};
use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\{CompoundTag, ListTag};
use pocketmine\network\mcpe\protocol;
use pocketmine\network\mcpe\protocol\types\inventory\WindowTypes;
use pocketmine\scheduler\ClosureTask;
use pocketmine\block\tile\Spawnable;
use pocketmine\world\Position;

class SyncInventory extends BlockInventory{
	/** @var SyncInventory[] */
	protected static $instances = [];

	/**
	 * @return SyncInventory[]
	 */
	public static function getAll() : array{
		return self::$instances;
	}

	/**
	 * @param string $playerName
	 * @param bool   $includeOffline = true
	 *
	 * @return null|SyncInventory
	 */
	public static function load(string $playerName, bool $includeOffline = true) : ?SyncInventory{
		$syncInventory = SyncInventory::get($playerName);
		if($syncInventory instanceof SyncInventory){
			return $syncInventory;
		}

		$playerName = strtolower($playerName);
		/** @var Item[] $items */
		$items = [];
		$server = Server::getInstance();
		$player = $server->getPlayerExact($playerName);
		if($player instanceof Player){
			$inventory = $player->getInventory();
			/** @var Item[] $items */
			$items = $inventory->getContents(true);

			$armorInventory = $player->getArmorInventory();
			for($i = 0; $i < 4; ++$i){
				$item = $armorInventory->getItem($i);
				if(!$item->isNull()){
					$items[$i + 46] = $item;
				}
			}
		}elseif(!$includeOffline){
			return null;
		}

		if(!file_exists("{$server->getDataPath()}players/{$playerName}.dat")){
			return null;
		}

		$nbt = $server->getOfflinePlayerData($playerName);
		$inventoryTag = $nbt->getListTag("Inventory");
		if($inventoryTag === null){
			return null;
		}else{
			/** @var CompoundTag $itemTag */
			foreach($inventoryTag as $i => $itemTag){
				$slot = $itemTag->getByte("Slot");
				if($slot > 8 && $slot < 44){ // 9-44 is PlayerInventory slot
					$items[$slot - 9] = Item::nbtDeserialize($itemTag);
				}elseif($slot > 99 and $slot < 104){ // 100-103 is ArmorInventory slot
					$items[$slot + ArmorGroup::START - 100] = Item::nbtDeserialize($itemTag);
				}
			}
		}
		return new SyncInventory($playerName, $items);
	}

	/**
	 * @param string $playerName
	 *
	 * @return null|SyncInventory
	 */
	public static function get(string $playerName) : ?SyncInventory{
		return self::$instances[strtolower($playerName)] ?? null;
	}

	/** @var Vector3[] */
	protected $vectors = [];

	/** @var string */
	protected $playerName;

	/** @var SlotGroup[] */
	protected $groups = [];

	/**
	 * SyncInventory constructor.
	 *
	 * @param string $playerName
	 * @param Item[] $items
	 */
	public function __construct(string $playerName, array $items){
		parent::__construct(new Position(), 54);

		$this->groups[] = new InvGroup($this);
		$this->groups[] = new ArmorGroup($this);
		$this->groups[] = new CursorGroup($this);

		$borderItem = ItemFactory::get(-161); //barrier
		$borderItem->setCustomName("");
		for($i = 0; $i < 54; ++$i){
			if(!$this->isValidSlot($i)){
				$this->setItem($i, $borderItem);
			}
		}

		$this->playerName = strtolower($playerName);
		self::$instances[$this->playerName] = $this;

		foreach($items as $slot => $item){
			$this->setItem($slot, $item);
		}
	}

	/**
	 * @param int $index
	 *
	 * @return bool
	 */
	public function isValidSlot(int $index) : bool{
		return $this->getSlotGroup($index) instanceof SlotGroup;
	}

	/**
	 * @param int $index
	 *
	 * @return null|SlotGroup
	 */
	public function getSlotGroup(int $index) : ?SlotGroup{
		foreach($this->groups as $key => $group){
			if($group->validate($index)){
				return $group;
			}
		}
		return null;
	}

	/**
	 * @param int  $index
	 * @param Item $item
	 * @param bool $send
	 * @param bool $sync
	 *
	 * @return bool
	 */
	public function setItem(int $index, Item $item, bool $send = true, $sync = true) : void{
		if($sync){
			$slotGroup = $this->getSlotGroup($index);
			if($slotGroup instanceof SlotGroup){
				$slotGroup->setItem($index, $item);
			}
		}
		parent::setItem($index, $item, $send);
	}

	/**
	 * @param Player $who
	 */
	public function onOpen(Player $who) : void{
		BaseInventory::onOpen($who);

		$this->sendFakeChestBlock($who);
		$vec = $this->vectors[strtolower($who->getName())];

		$packets = [];
		$pk = new protocol\ContainerOpenPacket();
		$pk->type = WindowTypes::CONTAINER;
		$pk->entityUniqueId = -1;
		$pk->x = $vec->x;
		$pk->y = $vec->y;
		$pk->z = $vec->z;
		$pk->windowId = $who->getNetworkSession()->getInvManager()->getWindowId($this);
		$packets[] = $pk;

		$pk2 = new protocol\InventoryContentPacket();
		$pk2->items = $this->getContents(true);
		$pk2->windowId = $pk->windowId;
		$packets[] = $pk2;

		$plugin = InventoryMonitor::getInstance();
		$plugin->getScheduler()->scheduleDelayedTask(new ClosureTask(function(int $currentTick) use ($who, $packets) : void{
			foreach($packets as $key => $packet){
				$who->getNetworkSession()->sendDataPacket($packet);
			}
		}), (int) $plugin->getConfig()->getNested("settings.inventory-display-delay", 10));
	}

	/**
	 * @param Player $who
	 */
	public function onClose(Player $who) : void{
		BaseInventory::onClose($who);
		$this->restoreFakeChestBlock($who);
		if(empty($this->viewers)){
			$this->delete();
		}
	}

	/**
	 * @return string
	 */
	public function getName() : string{
		return "SyncInventory";
	}

	/**
	 * @return int
	 */
	public function getDefaultSize() : int{
		return 54;
	}

	/**
	 * @return int
	 */
	public function getNetworkType() : int{
		return WindowTypes::CONTAINER;
	}

	public function delete() : void{
		foreach($this->viewers as $key => $who){
			$this->onClose($who);
		}
		$this->save();
		unset(self::$instances[$this->playerName]);
	}

	public function save() : void{
		$server = Server::getInstance();
		$player = $server->getPlayerExact($this->playerName);
		if($player instanceof Player){
			$inventory = $player->getInventory();
			for($i = InvGroup::START; $i <= InvGroup::END; ++$i){
				$inventory->setItem($i, $this->getItem($i));
			}

			$armorInventory = $player->getArmorInventory();
			for($i = ArmorGroup::START; $i <= ArmorGroup::END; ++$i){
				$item = $this->getItem($i);
				if(!$item->isNull()){
					$armorInventory->setItem($i - 46, $this->getItem($i));
				}
			}
		}else{
			$namedTag = $server->getOfflinePlayerData($this->playerName);
			$inventoryTag = new ListTag([], NBT::TAG_Compound);
			for($i = InvGroup::START; $i <= InvGroup::END; ++$i){
				$item = $this->getItem($i);
				if(!$item->isNull()){
					$inventoryTag->push($item->nbtSerialize($i + 9));
				}
			}
			for($i = ArmorGroup::START; $i <= ArmorGroup::END; ++$i){
				$item = $this->getItem($i);
				if(!$item->isNull()){
					$inventoryTag->push($item->nbtSerialize($i - ArmorGroup::START + 100));
				}
			}
			$namedTag->setTag("Inventory", $inventoryTag);
			$server->saveOfflinePlayerData($this->playerName, $namedTag);
		}
	}

	/**
	 * @param Player $who
	 */
	public function sendFakeChestBlock(Player $who) : void{
		$this->vectors[$key = strtolower($who->getName())] = $who->getPosition()->subtract(0, 3, 0)->floor();
		if($this->vectors[$key]->y < 0){
			$this->vectors[$key]->y = 0;
		}
		$vec = $this->vectors[$key];

		for($i = 0; $i < 2; $i++){
			$pk = new protocol\UpdateBlockPacket();
			$pk->x = $vec->x + $i;
			$pk->y = $vec->y;
			$pk->z = $vec->z;
			$pk->blockRuntimeId = protocol\types\RuntimeBlockMapping::toStaticRuntimeId(BlockLegacyIds::CHEST);
			$pk->flags = 0;
			$who->getNetworkSession()->sendDataPacket($pk);


			$player = Server::getInstance()->getPlayerExact($this->playerName);
			/**
			$nbt = new CompoundTag("", [
				new StringTag("id", "Chest"),
				new IntTag("x", $vec->x + $i),
				new IntTag("y", $vec->y),
				new IntTag("z", $vec->z),
				new IntTag("pairx", $vec->x + (1 - $i)),
				new IntTag("pairz", $vec->z),
				new StringTag("CustomName", InventoryMonitor::getInstance()->getLanguage()->translate("chest.name", [$player instanceof Player ? $player->getName() : $this->playerName])),
			]);
			 */

			$nbt = CompoundTag::create()
					->setString("id", "Chest")
					->setInt("x", $vec->x + $i)
					->setInt("y", $vec->y)
					->setInt("z", $vec->z)
					->setInt("pairx", $vec->x + (1 - $i))
					->setInt("pairz", $vec->z)
					->setString("CustomName", InventoryMonitor::getInstance()->getLanguage()->translate("chest.name", [$player instanceof Player ? $player->getName() : $this->playerName]));

			$pk = new protocol\BlockActorDataPacket();
			$pk->x = $vec->x + $i;
			$pk->y = $vec->y;
			$pk->z = $vec->z;
			$pk->namedtag = (new NetworkNbtSerializer())->write(new TreeRoot($nbt));
			$who->getNetworkSession()->sendDataPacket($pk);
		}
	}

	/**
	 * @param Player $who
	 */
	public function restoreFakeChestBlock(Player $who) : void{
		if(!isset($this->vectors[$key = strtolower($who->getName())])){
			return;
		}

		for($i = 0; $i < 2; $i++){
			$block = $who->getWorld()->getBlock($vec = $this->vectors[$key]->add($i, 0, 0));

			$pk = new protocol\UpdateBlockPacket();
			$pk->x = $vec->x;
			$pk->y = $vec->y;
			$pk->z = $vec->z;
			$pk->blockRuntimeId = protocol\types\RuntimeBlockMapping::toStaticRuntimeId($block->getId(), $block->getMeta());
			$pk->flags = 0;
			$who->getNetworkSession()->sendDataPacket($pk);

			$tile = $who->getWorld()->getTile($vec);
			if($tile instanceof Spawnable){
				$who->getNetworkSession()->sendDataPacket(protocol\BlockActorDataPacket::create($tile->getPos()->x, $tile->getPos()->y, $tile->getPos()->z, (new NetworkNbtSerializer())->write(new TreeRoot($tile->getSpawnCompound()))));
			}
		}
		unset($this->vectors[$key]);
	}

	/**
	 * @return string
	 */
	public function getPlayerName() : string{
		return $this->playerName;
	}

	/**
	 *
	 * @return SlotGroup[]
	 */
	public function getGroups() : array{
		return $this->groups;
	}

	/**
	 *
	 * @param SlotGroup[] $groups
	 */
	public function setGroups(array $groups) : void{
		$this->groups = $groups;
	}
}
