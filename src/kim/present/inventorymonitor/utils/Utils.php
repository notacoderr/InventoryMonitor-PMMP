<?php
declare(strict_types=1);

namespace kim\present\inventorymonitor\utils;

class Utils{
	/**
	 * Code from pmmp/PocketMine-MP
	 *
	 * @author pmmp/PocketMine-MP
	 * @url https://github.com/pmmp/PocketMine-MP/blob/forms-api/src/pocketmine/utils/Utils.php#L626-L633
	 *
	 * @param array  $array
	 * @param string $class
	 *
	 * @return bool
	 */
	public static function validateObjectArray(array $array, string $class) : bool{
		foreach($array as $key => $item){
			if(!($item instanceof $class)){
				throw new \RuntimeException("\$item[$key] is not an instance of $class");
			}
		}
		return true;
	}
}