<?php
	namespace Wadapi\Persistence;

	use Wadapi\System\WadapiClass;
	use Wadapi\Reflection\Mirror;
	
	abstract class Gateway extends WadapiClass{
		//Gateway Comparison Type Constants (for find method)
		const IS_EQUAL = "==";
		const NOT_EQUAL = "!=";
		const GREATER_THAN = ">";
		const LESS_THAN = "<";
		const GREATER_THAN_EQUAL = ">=";
		const LESS_THAN_EQUAL = "<=";

		/*
		 * Validates the parameters passed to the find method.
		 */
		protected function checkFindParameters($className, $propertyName, $propertyValue, &$comparisonType){
			//Check that the specified $className names an existing class. If not return an empty result set.
			if(!class_exists($className)){
				return false;
			}

			//Check that the specified $className is a PersistentClass
			$class = Mirror::reflectClass($className);
			if(!$class->descendsFrom("PersistentClass")){
				return false;
			}

			//Assign default value to comparisonType
			if(!$comparisonType){
				$comparisonType = self::IS_EQUAL;
			}

			//Check that the specified class has the given $propertyName. If not return an empty result set.
			if($propertyName && !$class->hasProperty($propertyName)){
				return false;
			}

			return true;
		}

		/*
		 * Validates the parameters passed to the save and delete methods.
		 */
		protected function checkUpdateParameters($updateObject){
			if(!$updateObject || !is_object($updateObject)){
				return false;
			}

			//Check that the specified $updateObject is a PersistentClass
			$updateObjectClass = Mirror::reflectClass($updateObject);
			if(!$updateObjectClass->descendsFrom("PersistentClass")){
				return false;
			}

			return true;
		}

		/*
		 * Returns the first instance that matches the given search criteria
		 */
		public function findUnique(){
			$arguments = func_get_args();
			$results = call_user_func_array(array($this,'find'), $arguments);

			if(empty($results)){
				return $results;
			}else{
				return array_shift($results);
			}
		}

		//public abstract function find();
		public abstract function save($className);
		public abstract function delete($className);
	}
?>
