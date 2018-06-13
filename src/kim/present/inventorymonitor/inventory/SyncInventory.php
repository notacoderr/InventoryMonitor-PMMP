<?php

declare(strict_types=1);

namespace kim\present\inventorymonitor\inventory;

use kim\present\inventorymonitor\inventory\group\{
	ArmorGroup, CursorGroup, InvGroup, SlotGroup
};
use kim\present\inventorymonitor\InventoryMonitor;
use pocketmine\block\{
	Block, BlockFactory
};
use pocketmine\inventory\{
	BaseInventory, CustomInventory
};
use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\nbt\{
	NBT, NetworkLittleEndianNBTStream
};
use pocketmine\nbt\tag\{
	CompoundTag, ListTag, StringTag
};
use pocketmine\network\mcpe\protocol\{
	BlockEntityDataPacket, ContainerOpenPacket, InventoryContentPacket, UpdateBlockPacket
};
use pocketmine\network\mcpe\protocol\types\WindowTypes;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\tile\Spawnable;

class SyncInventory extends CustomInventory{
	/**
	 * @var SyncInventory[]
	 */
	protected static $instances = [];

	/**
	 * @var CompoundTag
	 */
	protected $nbt;

	/**
	 * @var Vector3[]
	 */
	protected $vectors = [];

	/**
	 * @var string
	 */
	protected $playerName;

	/**
	 * @var SlotGroup[]
	 */
	protected $groups = [];

	/**
	 * SyncInventory constructor.
	 *
	 * @param string $playerName
	 * @param Item[] $items
	 */
	public function __construct(string $playerName, array $items){
		parent::__construct(new Vector3(0, 0, 0), $items, 54, null);

		$this->groups[] = new InvGroup($this);
		$this->groups[] = new ArmorGroup($this);
		$this->groups[] = new CursorGroup($this);

		$borderItem = Item::get(Block::SKULL_BLOCK);
		$borderItem->setCustomName('');
		for($i = 0; $i < 54; ++$i){
			if(!$this->isValidSlot($i)){
				$this->setItem($i, clone $borderItem);
			}
		}

		$this->playerName = strtolower($playerName);
		$this->nbt = new CompoundTag('', [
			new StringTag('id', 'Chest'),
		]);
		self::$instances[$this->playerName] = $this;
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
	public function setItem(int $index, Item $item, bool $send = true, $sync = true) : bool{
		if($sync){
			$slotGroup = $this->getSlotGroup($index);
			if($slotGroup instanceof SlotGroup){
				$slotGroup->setItem($index, $item);
			}
		}
		return parent::setItem($index, $item, $send);
	}

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
		}elseif($includeOffline){
			if(file_exists("{$server->getDataPath()}players/{$playerName}.dat")){
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
			}else{
				return null;
			}
		}else{
			return null;
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

	/**
	 * @param Player $who
	 */
	public function onOpen(Player $who) : void{
		BaseInventory::onOpen($who);

		$vec = $this->vectors[$key = $who->getLowerCaseName()];

		$pk = new ContainerOpenPacket();
		$pk->type = WindowTypes::CONTAINER;
		$pk->entityUniqueId = -1;
		$pk->x = $vec->x;
		$pk->y = $vec->y;
		$pk->z = $vec->z;
		$pk->windowId = $who->getWindowId($this);
		$who->dataPacket($pk);

		$pk2 = new InventoryContentPacket();
		$pk2->items = $this->getContents(true);
		$pk2->windowId = $pk->windowId;
		$who->dataPacket($pk2);
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

	public function delete() : void{
		foreach($this->viewers as $key => $who){
			$this->close($who);
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
			$inventoryTag = new ListTag("Inventory", [], NBT::TAG_Compound);
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
			$namedTag->setTag($inventoryTag);
			$server->saveOfflinePlayerData($this->playerName, $namedTag);
		}
	}
	/**
	 * @param Player $who
	 */
	public function sendFakeChestBlock(Player $who) : void{
		$this->vectors[$key = $who->getLowerCaseName()] = $who->subtract(0, 3, 0)->floor();
		if($this->vectors[$key]->y < 0){
			$this->vectors[$key]->y = 0;
		}
		$vec = $this->vectors[$key];

		for($i = 0; $i < 2; $i++){
			$pk = new UpdateBlockPacket();
			$pk->x = $vec->x + $i;
			$pk->y = $vec->y;
			$pk->z = $vec->z;
			$pk->blockRuntimeId = BlockFactory::toStaticRuntimeId(Block::CHEST);
			$pk->flags = UpdateBlockPacket::FLAG_NONE;
			$who->sendDataPacket($pk);


			$this->nbt->setInt('x', $vec->x + $i);
			$this->nbt->setInt('y', $vec->y);
			$this->nbt->setInt('z', $vec->z);
			$this->nbt->setInt('pairx', $vec->x + (1 - $i));
			$this->nbt->setInt('pairz', $vec->z);
			$player = Server::getInstance()->getPlayerExact($this->playerName);
			$this->nbt->setString('CustomName', InventoryMonitor::getInstance()->getLanguage()->translate('chest.name', [$player instanceof Player ? $player->getName() : $this->playerName]));

			$pk = new BlockEntityDataPacket();
			$pk->x = $vec->x + $i;
			$pk->y = $vec->y;
			$pk->z = $vec->z;
			$pk->namedtag = (new NetworkLittleEndianNBTStream())->write($this->nbt);
			$who->sendDataPacket($pk);
		}
	}

	/**
	 * @param Player $who
	 */
	public function restoreFakeChestBlock(Player $who) : void{
		if(!isset($this->vectors[$key = $who->getLowerCaseName()])){
			return;
		}
		for($i = 0; $i < 2; $i++){
			$block = $who->getLevel()->getBlock($vec = $this->vectors[$key]->add($i, 0, 0));

			$pk = new UpdateBlockPacket();
			$pk->x = $vec->x;
			$pk->y = $vec->y;
			$pk->z = $vec->z;
			$pk->blockRuntimeId = BlockFactory::toStaticRuntimeId($block->getId(), $block->getDamage());
			$pk->flags = UpdateBlockPacket::FLAG_NONE;
			$who->sendDataPacket($pk);

			$tile = $who->getLevel()->getTile($vec);
			if($tile instanceof Spawnable){
				$who->sendDataPacket($tile->createSpawnPacket());
			}
		}
		unset($this->vectors[$key]);
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
