<?php

declare(strict_types=1);

namespace blugin\inventorymonitor\inventory\group;

use pocketmine\Server;
use pocketmine\item\Item;

class CursorGroup extends SlotGroup{

    public const START = 52;
    public const END = 52;

    /**
     * @param int $index
     * @param Item $item
     */
    public function onUpdate(int $index, Item $item): void{
        $player = Server::getInstance()->getPlayerExact($this->syncInventory->getPlayerName());
        if ($player !== null) {
            $player->getCursorInventory()->setItem(0, $item, true);
        }
    }
}