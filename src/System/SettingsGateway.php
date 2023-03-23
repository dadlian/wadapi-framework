<?php
	namespace Wadapi\System;

	class SettingsGateway{
		private $settings;

		public function __construct(){
			//Load User Settings
			$this->settings = json_decode(file_get_contents(CONFIG."/settings.json"),true);

			foreach($this->settings as $setting =>$values){
				$setting = preg_replace("/\-/","",$setting);
				if(is_array($values)){
					foreach($values as $name => $value){
						$name = preg_replace("/\-/","",$name);
						$processedSettings[$setting][$name] = json_encode($value);
					}
				}else{
					$processedSettings[$setting] = json_encode($values);
				}
			}

			$this->settings = $processedSettings;
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
