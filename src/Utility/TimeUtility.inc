<?php
	namespace Wadapi\Utility;

	class TimeUtility{
		static protected $times = array();

		public static function start($item){
			$startTime = microtime(true);
			self::$times[$item] = $startTime;
		}

		public static function end($item){
			$endTime = microtime(true);

			if(array_key_exists($item,self::$times)){
				self::$times[$item] = $endTime - self::$times[$item];
			}else{
				self::$times[$item] = 0;
			}
		}

		public static function printTimes(){
			foreach(self::$times as $item => $time){
				echo "$item: $time seconds<BR>\n";
			}
		}
	}
?>
