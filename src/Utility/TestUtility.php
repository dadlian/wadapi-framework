<?php
	namespace Wadapi\Utility;

	class TestUtility{
		public static $isTestRun = false;

		public static function start_testing(){
			self::$isTestRun = true;
		}

		public static function stop_testing(){
			self::$isTestRun = false;
		}

		public static function is_test_run(){
			return self::$isTestRun;
		}
	}
?>
