<?php

declare(strict_types=1);

namespace blugin\inventorymonitor\inventory;

use pocketmine\Player;
use pocketmine\Server;
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
  CompoundTag, ListTag, IntTag, StringTag
};
use pocketmine\network\mcpe\protocol\{
  UpdateBlockPacket, BlockEntityDataPacket, ContainerOpenPacket, InventoryContentPacket
};
use pocketmine\network\mcpe\protocol\types\WindowTypes;
use pocketmine\tile\Spawnable;
use blugin\inventorymonitor\InventoryMonitor;
use blugin\inventorymonitor\task\SendDataPacketTask;

class SyncInventory extends CustomInventory{

    public const INV_START = 0;
    public const INV_END = 35;
    public const ARMOR_START = 46;
    public const ARMOR_END = 49;
    public const CURSOR = 52;

    /** @var NetworkLittleEndianNBTStream|null */
    private static $nbtWriter = null;

    /** @var self[] */
    public static $instances = [];

    /** CompoundTag */
    private $nbt;

    /** Vector3[] */
    private $vectors = [];

    /** @var string */
    private $playerName;

    /**
     * SyncInventory constructor.
     *
     * @param string      $playerName
     * @param CompoundTag $namedTag
     */
    public function __construct(string $playerName, ?CompoundTag $namedTag = null){
        parent::__construct(new Vector3(0, 0, 0), [], 54, null);

        $player = Server::getInstance()->getPlayerExact($playerName);
        if ($player instanceof Player) {
            $this->loadFromPlayer($player);
        } elseif ($namedTag !== null) {
            $this->loadFromNBT($namedTag);
        }
        $borderItem = Item::get(Block::SKULL_BLOCK);
        $borderItem->setCustomName('');
        for ($i = 0; $i < 54; ++$i) {
            if (!$this->isValidSlot($i)) {
                $this->setItem($i, clone $borderItem);
            }
        }

        $this->playerName = $playerName;
        $this->nbt = new CompoundTag('', [
          new StringTag('id', 'Chest'),
          new IntTag('x', 0),
          new IntTag('y', 0),
          new IntTag('z', 0),
          new StringTag('CustomName', InventoryMonitor::getInstance()->getLanguage()->translate('chest.name', [$player instanceof Player ? $player->getName() : $playerName])),
        ]);

        if (self::$nbtWriter === null) {
            self::$nbtWriter = new NetworkLittleEndianNBTStream();
        }
    }

    /** @param Player $who */
    public function onOpen(Player $who) : void{
        BaseInventory::onOpen($who);

        $this->vectors[$key = $who->getLowerCaseName()] = $who->subtract(0, 3, 0)->floor();
        if ($this->vectors[$key]->y < 0) {
            $this->vectors[$key]->y = 0;
        }
        $vec = $this->vectors[$key];

        for ($i = 0; $i < 2; $i++) {
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

            $pk = new BlockEntityDataPacket();
            $pk->x = $vec->x + $i;
            $pk->y = $vec->y;
            $pk->z = $vec->z;
            $pk->namedtag = self::$nbtWriter->write($this->nbt);
            $who->sendDataPacket($pk);
        }

        $pk = new ContainerOpenPacket();
        $pk->type = WindowTypes::CONTAINER;
        $pk->entityUniqueId = -1;
        $pk->x = $vec->x;
        $pk->y = $vec->y;
        $pk->z = $vec->z;
        $pk->windowId = $who->getWindowId($this);

        $pk2 = new InventoryContentPacket();
        $pk2->items = $this->getContents(true);
        $pk2->windowId = $pk->windowId;
        Server::getInstance()->getScheduler()->scheduleDelayedTask(new SendDataPacketTask($who, $pk, $pk2), 5);
    }

    /** @param Player $who */
    public function onClose(Player $who) : void{
        BaseInventory::onClose($who);
        $key = $who->getLowerCaseName();
        if (!isset($this->vectors[$key])) {
            return;
        }
        for ($i = 0; $i < 2; $i++) {
            $block = $who->getLevel()->getBlock($vec = $this->vectors[$key]->add($i, 0, 0));

            $pk = new UpdateBlockPacket();
            $pk->x = $vec->x;
            $pk->y = $vec->y;
            $pk->z = $vec->z;
            $pk->blockRuntimeId = BlockFactory::toStaticRuntimeId($block->getId(), $block->getDamage());
            $pk->flags = UpdateBlockPacket::FLAG_NONE;
            $who->sendDataPacket($pk);

            $tile = $who->getLevel()->getTile($vec);
            if ($tile instanceof Spawnable) {
                $who->sendDataPacket($tile->createSpawnPacket());
            }
        }
        unset($this->vectors[$key]);
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
        if ($sync && $this->playerName !== null) {
            $player = Server::getInstance()->getPlayerExact($this->playerName);
            if ($player !== null) {
                $inventory = $player->getInventory();
                if ($this->isInventorySlot($index)) {
                    $inventory->setItem($index, $item, true);
                } elseif ($this->isArmorSlot($index)) {
                    $player->getArmorInventory()->setItem($index - 46, $item, true);
                } elseif ($this->isCursorSlot($index)) {
                    $player->getCursorInventory()->setItem(0, $item, true);
                }
            }
        }
        return parent::setItem($index, $item, $send);
    }

    /** @return string */
    public function getName() : string{
        return "SyncInventory";
    }

    /** @return int */
    public function getDefaultSize() : int{
        return 54;
    }

    /** @return int */
    public function getNetworkType() : int{
        return WindowTypes::CONTAINER;
    }

    /** @return string */
    public function getPlayerName() : string{
        return $this->playerName;
    }

    /**
     * @param int $index
     *
     * @return bool
     */
    public function isValidSlot(int $index) : bool{
        return $this->isInventorySlot($index) || $this->isArmorSlot($index) || $this->isCursorSlot($index);
    }

    /**
     * @param int $index
     *
     * @return bool
     */
    public function isInventorySlot(int $index) : bool{
        return $index >= self::INV_START && $index <= self::INV_END;
    }

    /**
     * @param int $index
     *
     * @return bool
     */
    public function isArmorSlot(int $index) : bool{
        return $index >= self::ARMOR_START && $index <= self::ARMOR_END;
    }

    /**
     * @param int $index
     *
     * @return bool
     */
    public function isCursorSlot(int $index) : bool{
        return $index === self::CURSOR;
    }

    public function delete() : void{
        foreach ($this->getViewers() as $key => $who) {
            $this->close($who);
        }

        $player = Server::getInstance()->getPlayerExact($this->playerName);
        if ($player !== null) {
            $this->saveToPlayer($player);
        } else {
            $namedTag = $this->getServer()->getOfflinePlayerData($playerName);
            $this->saveToNBT($namedTag);
            $this->getServer()->saveOfflinePlayerData($playerName, $namedTag);
        }
    }

    /**
     * @param CompoundTag $namedTag
     */
    public function loadFromNBT(CompoundTag $namedTag) : void{
        $inventoryTag = $namedTag->getListTag("Inventory");
        if ($inventoryTag !== null) {
            /** @var CompoundTag $itemTag */
            foreach ($inventoryTag as $i => $itemTag) {
                $slot = $itemTag->getByte("Slot");
                if ($slot > 8 && $slot < 44) { // 9-44 is PlayerInventory slot
                    $this->setItem($slot - 9, Item::nbtDeserialize($itemTag));
                } elseif ($slot > 99 and $slot < 104) { // 100-103 is ArmorInventory slot
                    $this->setItem($slot + self::ARMOR_START - 100, Item::nbtDeserialize($itemTag));
                }
            }
        }
    }

    /**
     * @param CompoundTag $namedTag
     */
    public function saveToNBT(CompoundTag $namedTag) : void{
        $inventoryTag = new ListTag("Inventory", [], NBT::TAG_Compound);
        for ($i = self::INV_START; $i <= self::INV_END; ++$i) {
            $item = $this->getItem($i);
            if (!$item->isNull()) {
                $inventoryTag->push($item->nbtSerialize($i + 9));
            }
        }
        for ($i = self::ARMOR_START; $i <= self::ARMOR_END; ++$i) {
            $item = $this->getItem($i);
            if (!$item->isNull()) {
                $inventoryTag->push($item->nbtSerialize($i - self::ARMOR_START + 100));
            }
        }
        $namedTag->setTag($inventoryTag);
    }

    /**
     * @param Player $player
     */
    public function loadFromPlayer(Player $player) : void{
        $inventory = $player->getInventory();
        for ($i = 0; $i < 36; ++$i) {
            $item = $inventory->getItem($i);
            if (!$item->isNull()) {
                $this->setItem($i, $item);
            }
        }

        $armorInventory = $player->getArmorInventory();
        for ($i = 0; $i < 4; ++$i) {
            $item = $armorInventory->getItem($i);
            if (!$item->isNull()) {
                $this->setItem($i + 46, $item);
            }
        }
    }

    /**
     * @param Player $player
     */
    public function saveToPlayer(Player $player) : void{
        $inventory = $player->getInventory();
        for ($i = self::INV_START; $i <= self::INV_END; ++$i) {
            $inventory->setItem($i, $this->getItem($i));
        }

        $armorInventory = $player->getArmorInventory();
        for ($i = self::ARMOR_START; $i <= self::ARMOR_END; ++$i) {
            $item = $this->getItem($i);
            if (!$item->isNull()) {
                $armorInventory->setItem($i - 46, $this->getItem($i));
            }
        }
    }
}