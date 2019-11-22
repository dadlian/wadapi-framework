<?php
	namespace Wadapi\System;

	use Wadapi\Logging\Logger;

	abstract class Controller extends WadapiClass{
		/** @Collection(type=@WadapiString) */
		protected $arguments;

		public function __construct(){
			call_user_func_array(array('parent','__construct'),func_get_args());
			Registrar::register($this);

			//Remove unspecified arguments
			$filteredArguments = array();
			foreach($this->getArguments() as $key => $value){
				if($value !== "@"){
					$filteredArguments[$key] = $value;
				}
			}
			$this->setArguments($filteredArguments);
		}

		//Main execution loop of Controller, called by Dispatcher based on action.xml
		public abstract function execute();

		//Called at the beginning of the controller's execution, nothing happens unless method is redefined in child controller
		public function initialise(){
		}

		//Called at the end of the controller's execution, whether or not there are any errors during execution
		public function finish(){
		}
	}
?>
