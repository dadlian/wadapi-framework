<?php
	namespace Wadapi\System;

	class Settings extends WadapiClass{
		const INSTALL = "install";
		const DATABASE = "database";
		const MESSAGING = "messaging";
		const LOGGING = "logging";
		const API = "api";

		/** @WadapiString */
		protected $name;

		/** @Collection(type=@WadapiString) */
		protected $values;

		/*
		 * Redefined getter allows direct indexing into values array
		 */
		public function __call($method, $arguments){
			//Handle single item accessors
			if(preg_match("/^get([A-Z0-9]\w*)/", $method, $matches) &&
				$this->values && array_key_exists(strtolower($matches[1]), $this->values)){
					return $this->values[strtolower($matches[1])];
			}else{
				return parent::__call($method,$arguments);
			}
		}
	}
?>
