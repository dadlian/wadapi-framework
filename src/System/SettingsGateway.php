<?php
	namespace Wadapi\System;

	class SettingsGateway{
		private $settings;

		public function __construct(){
			//Load User Settings
			$this->settings = json_decode(file_get_contents(PROJECT_PATH."/settings.json"),true);
			$environments = json_decode(file_get_contents(PROJECT_PATH."/environments.json"),true);
			$activeEnvironment = array_key_exists($this->settings['environment'],$environments)?
															$environments[$this->settings['environment']]:array_shift($environments);

			foreach($activeEnvironment as $setting => $values){
				$this->settings[$setting] = $values;
			}
		}

		public function find($name,$unique=false){
			$matchingSettings = array();

			foreach($this->settings as $setting => $values){
				if($name == $setting){
					$matchingSettings[] = new Settings($setting,$values);

					if($unique){
						break;
					}
				}
			}

			if($unique){
				return(sizeof($matchingSettings) > 0)?$matchingSettings[0]:null;
			}else{
				return $matchingSettings;
			}
		}

		public function findUnique($name){
			return $this->find($name,true);
		}
	}
?>
