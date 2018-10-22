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

namespace kim\present\inventorymonitor\task;

use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\Player;
use pocketmine\scheduler\Task;

class SendDataPacketTask extends Task{
	/** @var Player */
	private $player;

	/** @var DataPacket[] */
	private $packets;

	/**
	 * SendDataPacketTask constructor.
	 *
	 * @param Player       $player
	 * @param DataPacket[] $packets
	 */
	public function __construct(Player $player, DataPacket ...$packets){
		$this->player = $player;
		$this->packets = $packets;
	}

	/**
	 * @param int $currentTick
	 */
	public function onRun(int $currentTick) : void{
		foreach($this->packets as $key => $packet){
			$this->player->sendDataPacket($packet);
		}
	}
}
