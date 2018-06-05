<?php

declare(strict_types=1);

namespace kim\present\inventorymonitor\inventory\group;

use pocketmine\item\Item;
use pocketmine\Server;

class InvGroup extends SlotGroup{
	public const START = 0;
	public const END = 35;

	/**
	 * @param int  $index
	 * @param Item $item
	 */
	public function onUpdate(int $index, Item $item) : void{
		$player = Server::getInstance()->getPlayerExact($this->syncInventory->getPlayerName());
		if($player !== null){
			$player->getInventory()->setItem($index, $item, true);
		}
	}
}