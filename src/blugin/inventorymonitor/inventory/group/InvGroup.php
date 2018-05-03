<?php

declare(strict_types=1);

namespace blugin\inventorymonitor\inventory\group;

use pocketmine\Server;
use pocketmine\item\Item;

class InvGroup extends SlotGroup{

    public const START = 0;
    public const END = 35;

    /**
     * @param int $index
     * @param Item $item
     */
    public function onUpdate(int $index, Item $item): void{
        $player = Server::getInstance()->getPlayerExact($this->syncInventory->getPlayerName());
        if ($player !== null) {
            $player->getInventory()->setItem($index, $item, true);
        }
    }
}