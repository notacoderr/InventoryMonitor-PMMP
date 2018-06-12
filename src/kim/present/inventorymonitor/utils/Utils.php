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

	/**
	 * @param string $str
	 * @param array  $strs
	 *
	 * @return bool
	 */
	public static function in_arrayi(string $str, array $strs) : bool{
		foreach($strs as $key => $value){
			if(strcasecmp($str, $value) === 0){
				return true;
			}
		}
		return false;
	}
}