<?php

namespace presentkim\inventorymonitor\inventory;

use pocketmine\item\Item;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\block\{
  Block, BlockFactory
};
use pocketmine\inventory\{
  BaseInventory, CustomInventory
};
use pocketmine\math\Vector3;
use pocketmine\nbt\NetworkLittleEndianNBTStream;
use pocketmine\nbt\tag\{
  CompoundTag, IntTag, StringTag
};
use pocketmine\network\mcpe\protocol\{
  UpdateBlockPacket, BlockEntityDataPacket, ContainerOpenPacket, InventoryContentPacket
};
use pocketmine\network\mcpe\protocol\types\WindowTypes;
use pocketmine\tile\Spawnable;
use presentkim\inventorymonitor\task\SendDataPacketTask;
use presentkim\inventorymonitor\util\Translation;

class SyncInventory extends CustomInventory{

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
        $items = [];
        $player = Server::getInstance()->getPlayerExact($playerName);
        if ($player instanceof Player) {
            $inventory = $player->getInventory();
            for ($i = 0; $i < 36; ++$i) {
                $item = $inventory->getItem($i);
                if (!$item->isNull()) {
                    $items[$i] = $item;
                }
            }
            $armorInventory = $player->getArmorInventory();
            for ($i = 0; $i < 4; ++$i) {
                $item = $armorInventory->getItem($i);
                if (!$item->isNull()) {
                    $items[$i + 46] = $item;
                }
            }
        } elseif ($namedTag !== null) {
            $inventoryTag = $namedTag->getListTag("Inventory");
            if ($inventoryTag !== null) {
                /** @var CompoundTag $itemTag */
                foreach ($inventoryTag as $i => $itemTag) {
                    $slot = $itemTag->getByte("Slot");
                    if ($slot > 8 && $slot < 44) { // 9-44 is PlayerInventory slot
                        $items[$slot - 9] = Item::nbtDeserialize($itemTag);
                    } elseif ($slot > 99 and $slot < 104) { // 100-103 is ArmorInventory slot
                        // $items[$slot - 100 + 44] = Item::nbtDeserialize($itemTag);
                        $items[$slot - 54] = Item::nbtDeserialize($itemTag);
                    }
                }
            }
        }
        $borderItem = Item::get(Block::SKULL_BLOCK);
        $borderItem->setCustomName('');
        for ($i = 36; $i < 54; ++$i) {
            if (!$this->isValidSlot($i)) {
                $items[$i] = clone $borderItem;
            }
        }
        parent::__construct(new Vector3(0, 0, 0), $items, 54, null);

        $this->playerName = $playerName;
        $this->nbt = new CompoundTag('', [
          new StringTag('id', 'Chest'),
          new IntTag('x', 0),
          new IntTag('y', 0),
          new IntTag('z', 0),
          new StringTag('CustomName', Translation::translate('chest-name', $player instanceof Player ? $player->getName() : $playerName)),
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
                if ($this->isPlayerSlot($index)) {
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
    public function isValidSlot(int $index){
        return $this->isPlayerSlot($index) || $this->isArmorSlot($index) || $this->isCursorSlot($index);
    }

    /**
     * @param int $index
     *
     * @return bool
     */
    public function isPlayerSlot(int $index){
        //  0-36 is PlayerInventory slot
        return $index < 36;
    }

    /**
     * @param int $index
     *
     * @return bool
     */
    public function isArmorSlot(int $index){
        // 46-49 is ArmorInventory  slot
        return $index > 45 && $index < 50;
    }

    /**
     * @param int $index
     *
     * @return bool
     */
    public function isCursorSlot(int $index){
        // 52 is ArmorInventory  slot
        return $index === 52;
    }
}