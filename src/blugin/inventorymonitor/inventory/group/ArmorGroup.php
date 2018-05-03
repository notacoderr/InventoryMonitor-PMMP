<?php

declare(strict_types=1);

namespace blugin\inventorymonitor\inventory\group;

use pocketmine\Server;
use pocketmine\item\Item;

class ArmorGroup extends SlotGroup{

    public const START = 46;
    public const END = 49;

    /**
     * @param int $index
     * @param Item $item
     */
    public function onUpdate(int $index, Item $item): void{
        $player = Server::getInstance()->getPlayerExact($this->syncInventory->getPlayerName());
        if ($player !== null) {
            $player->getArmorInventory()->setItem($index, $item, true);
        }
    }
}