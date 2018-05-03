<?php

declare(strict_types=1);

namespace blugin\inventorymonitor\inventory\group;

use blugin\inventorymonitor\inventory\SyncInventory;
use pocketmine\item\Item;

abstract class SlotGroup{
    public const START = -1;
    public const END = -1;

   /** @var SyncInventory $syncInventory */
    protected $syncInventory;

    public function __construct(SyncInventory $syncInventory){
        $this->syncInventory = $syncInventory;
    }

    /**
     * @param int $slot
     *
     * @return bool
     */
    public function validate(int $slot) : bool{
        return $slot >= $this::START && $slot <= $this::END;
    }

    /**
     * @param int $slot
     * @param Item $item
     */
    public function setItem(int $slot, Item $item) : void{
        $this->onUpdate($slot - $this::START, $item);
    }

    /**
     * @param int $index
     * @param Item $item
     */
    public abstract function onUpdate(int $index, Item $item) : void;
}