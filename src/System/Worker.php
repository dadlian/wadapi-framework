<?php
	namespace Wadapi\System;

	use Wadapi\Utility\MessageUtility;
	use Wadapi\Logging\Logger;

	class Worker{
		private static $onVacation = array();

		public static function takeVacation(){
			self::$onVacation[get_called_class()] = true;
		}

		public static function backToWork(){
			self::$onVacation[get_called_class()] = false;
		}

		public static function onVacation(){
			if(in_array(get_called_class(), array_keys(self::$onVacation))){
				return self::$onVacation[get_called_class()];
			}

			return false;
		}

		//Only call a method if the worker is not on vacation
		public static function __callStatic($method, $arguments){
			if(!self::onVacation()){
				if(method_exists(get_called_class(), $method)){
					$references = array();
					foreach($arguments as $key => $value){
						$references[$key] = &$arguments[$key];
					}
					return call_user_func_array(get_called_class()."::$method", $references);
				}else{
					Logger::fatal_error(MessageUtility::DATA_ACCESS_ERROR, "Call to undefined method ".get_called_class()."::$method().");
				}
			}else{
				Logger::warning(MessageUtility::RESOURCE_UNAVAILABLE_ERROR, "Call to ".get_called_class()."::$method() failed. ".get_called_class()." is on vacation.");
			}
		}
	}
?>
