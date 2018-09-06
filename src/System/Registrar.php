<?php
	namespace Wadapi\System;

	use Wadapi\Reflection\Mirror;
	use Wadapi\Utility\ArrayUtility;

	class Registrar extends Worker{
		//Associative array of arrays storing references to registered objects
		private static $register = array();

		//Adds a reference to the argument object to the system register
		protected static function register(&$object){
			if(!is_object($object)){
				return;
			}

			$objectClassname = get_class($object);
			if(!in_array($objectClassname, array_keys(self::$register))){
				self::$register[$objectClassname] = array();
			}

			self::$register[$objectClassname][] = $object;
		}

		//Removes a reference to the argument object to the system register
		protected static function unregister(&$object){
			if(!is_object($object)){
				return;
			}

			$objectClassname = get_class($object);
			if(in_array($objectClassname, array_keys(self::$register))){
				for($i = 0; $i < sizeof(self::$register[$objectClassname]); $i++){
					if(self::$register[$objectClassname][$i] === $object){
						self::$register[$objectClassname][$i] = null;
						self::$register[$objectClassname] = ArrayUtility::array_compress(self::$register[$objectClassname]);

						if(empty(self::$register[$objectClassname])){
							unset(self::$register[$objectClassname]);
						}

						return;
					}
				}
			}
		}

		//Returns an array of all objects of type $class that are registered
		protected static function getRegistered($className){
			$registeredObjects = array();

			foreach(self::getRegisteredClasses() as $registeredClassName){
 				$registeredClass = Mirror::reflectClass($registeredClassName);
				if($className == $registeredClassName){
					$registeredObjects = array_merge($registeredObjects, self::$register[$className]);
				}else if($registeredClass->descendsFrom($className)){
					$registeredObjects = array_merge($registeredObjects, self::$register[$registeredClassName]);
				}
			}

			return $registeredObjects;
		}

		//Returns an array of all objects of type $class that are registered
		protected static function getRegisteredClasses(){
			return array_keys(self::$register);
		}

		protected static function clearRegister(){
			self::$register = array();
		}
	}
?>
