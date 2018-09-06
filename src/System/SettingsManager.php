<?php
	namespace Wadapi\System;

	use Wadapi\Utility\StringUtility;

	class SettingsManager extends Worker{
		protected static $settings;
		private static $settingsGateway;

		public static function getSetting($category,$name){
			$category = StringUtility::decapitalise($category);
			$name = StringUtility::capitalise(StringUtility::camelise($name));

			if(!self::$settings){
				self::$settings = array();
			}

			if(!self::$settingsGateway){
				self::$settingsGateway = new SettingsGateway();
			}

			if(!array_key_exists($category,self::$settings)){
				self::$settings[$category] = array();
			}

			if(!array_key_exists($name,self::$settings[$category])){
				self::$settings[$category][$name] = "";
			}

			if(!self::$settings[$category][$name]){
				$settingCategory = self::$settingsGateway->findUnique($category);
				if($settingCategory){
					$getter = "get$name";
					self::$settings[$category][$name] = $settingCategory->$getter();
				}
			}

			return self::$settings[$category][$name];
		}

		public static function changeSource($source){
			self::$settingsGateway = new SettingsGateway($source);
		}
	}
?>
