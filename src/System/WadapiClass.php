<?php
	namespace Wadapi\System;

	use Wadapi\Reflection\Mirror;
	use Wadapi\Utility\StringUtility;
	use Wadapi\Utility\MessageUtility;
	use Wadapi\Logging\Logger;

	abstract class WadapiClass{
		/*
		 * Generic constructor which takes all the object's properties as arguments and throws an error if any are missing
		 */
		public function __construct(){
			//Get the variable number of arguments passed to this method, along with its count.
			$errors = array();
			$debugs = array();
			$argumentCount = sizeof(func_get_args());
			$argumentList = func_get_args();

			//Determine if all required properties are passed to constructor. Create blank values for those not specified.
			$class = Mirror::reflectClass($this);
			$classProperties = $class->getProperties();

			if($argumentCount < sizeof($classProperties)){
				for($i = $argumentCount; $i < sizeof($classProperties); $i++){
					$annotation = $classProperties[$i]->getAnnotation();

					//Set type specific default values
					$nextArgument = $this->getDefault($annotation);

					//Update defaults under certain conditions
					if($annotation->getDefault()){
						$nextArgument = $annotation->getDefault();
					}else if($annotation->getValues()){
						$values = $annotation->getValues();
						$nextArgument = $values[0];
					}else if($annotation->getMin()){
						if($annotation->isNumeric()){
							$nextArgument = $annotation->getMin();
						}else if($annotation->isString()){
							$nextArgument = str_repeat(" ",$annotation->getMin());
						}else if($annotation->isCollection()){
							$nextArgument = array_fill(0,$annotation->getMin(),$this->getDefault($annotation->getContainedType()));
						}
					}else if($annotation->isRequired() && $annotation->isObject()){
						$objectClass = $annotation->getObjectClass();
						$nextArgument = new $objectClass();
					}

					$argumentList[] = $nextArgument;
				}
			}

			//Assign the nth constructor argument to the nth class property, starting with inherited properties.
			for($i = 0; $i < sizeof($classProperties); $i++){
				$propertyName = $classProperties[$i]->getName();
				$methodName = "set".StringUtility::capitalise($propertyName);
				$this->$methodName($argumentList[$i]);
			}
		}

		/*
		 * Method is invoked when an undefined method is called on this class
		 */
		public function __call($method, $arguments){
			$class = Mirror::reflectClass($this);

			//Initialise an array of minimum arguments if none are specified
			$arguments = array_pad($arguments,2,null);

			//Handle single item accessors
			if(preg_match("/^(get|set)([A-Z]\w*)/", $method, $matches)){
				//Create a dummy argument if none was provided
				$arguments = array_pad($arguments,1,null);

				//Initialise parameters for method call
				$accessor = "{$matches[1]}X";
				$property = $matches[2];

				//Pre-process accessor parameters
				$errors = array();
				$debugs = array();

				if(!$class->hasProperty($property) && !$class->hasProperty(StringUtility::decapitalise($property))){
					if($class->hasMethod($method)){
						return call_user_func_array(array($this,$method), $arguments);
					}else{
						$errors[] = MessageUtility::DATA_ACCESS_ERROR;
						$debugs[] = "Call to undefined method {$class->getName()}::{$matches[1]}$property().";
					}
				}

				if($class->hasProperty(StringUtility::decapitalise($property))){
					$property = StringUtility::decapitalise($property);
				}

				//Call auto-generated method if its parameters are error-free
				if(!empty($errors)){
					Logger::fatal_error($errors, $debugs);
					return;
				}else{
					return $this->$accessor($property, $arguments[0], $method);
				}
			//Handle boolean accessor methods
			}else if(preg_match("/^(is|toggle)([A-Z]\w*)/", $method, $matches)){
				if($class->hasMethod($method)){
					return call_user_func_array(array($this,$method), $arguments);
				}

				$action = $matches[1];
				$property = $matches[2];

				$annotation = null;
				if($class->hasProperty(StringUtility::decapitalise($property))){
					$annotation = Mirror::reflectProperty($this, StringUtility::decapitalise($property))->getAnnotation();
				}

				if($annotation && !$annotation->isBoolean()){
					if($action == 'is'){
						$actionName = "assert";
					}else if($action == "toggle"){
						$actionName = "toggle";
					}

					Logger::fatal_error(MessageUtility::DATA_ACCESS_ERROR,"Cannot $actionName ".StringUtility::decapitalise($property).", it is a ".$annotation->getType().".");
					return;
				}

				if($action == 'is'){
					$getter = "get$property";
					return $this->$getter();
				}else if($action == "toggle"){
					$getter = "get$property";
					$setter = "set$property";

					$this->$setter(!$this->$getter());
					return;
				}
			//Handle list accessor methods
			}else if(preg_match("/^(([a-z]+)(To|From))([A-Z]\w*)/", $method, $matches)){
				//Create dummy arguments if none were provided
				$arguments = array_pad($arguments,2,null);

				//Initialise parameters for method call
				$accessor = "{$matches[1]}X";
				$action = $matches[2];
				$direction = StringUtility::decapitalise($matches[3]);
				$property = $matches[4];

				//Pre-process accessor parameters
				$errors = array();
				$debugs = array();

				if(!$class->hasProperty($property) && !$class->hasProperty(StringUtility::decapitalise($property))){
					if($class->hasMethod($method)){
						return call_user_func_array(array($this,$method), $arguments);
					}else{
						$errors[] = MessageUtility::DATA_ACCESS_ERROR;
						$debugs[] = "Call to undefined method {$class->getName()}::$method().";
					}
				}

				if($class->hasProperty(StringUtility::decapitalise($property))){
					$property = StringUtility::decapitalise($property);
					$propertyObject = Mirror::reflectProperty($this, $property);

					if(!$propertyObject->getAnnotation()->isCollection()){
						$errors[] = MessageUtility::DATA_MODIFY_ERROR;
						$debugs[] = "Can only $action elements $direction Collections. ".
								"Property '$property' is a {$propertyObject->getAnnotation()->getType()}.";
					}
				}

				//Call auto-generated method if its parameters are error-free
				if(!empty($errors)){
					Logger::fatal_error($errors, $debugs);
					return;
				}else{
					//Ensure only auto-generated list accessors are called
					if($class->hasMethod($accessor)){
						return $this->$accessor($method, $property, $arguments[0], $arguments[1]);
					}
				}
			}

			//If no auto-generated methods were called, see if method has been defined and let call through
			if($class->hasMethod($method)){
				return call_user_func_array(array($this,$method), $arguments);
			}else{
				Logger::fatal_error(MessageUtility::DATA_ACCESS_ERROR, "Call to undefined method {$class->getName()}::$method().");
			}
		}

		/*
		 * Generic getter method for any class property
		 */
		protected function getX($propertyName){
			//Prevent returning null for unitilalised arrays
			$annotation = Mirror::reflectProperty($this,$propertyName)->getAnnotation();
			return ($annotation->isCollection() && is_null($this->$propertyName))?array():$this->$propertyName;
		}

		/*
		 * Generic setter method for any class property
		 */
		protected function setX($propertyName, $newValue, $calledMethod){
			//Initialise reflection objects
			$class = get_class($this);
			$property = Mirror::reflectProperty(get_class($this), $propertyName);

			$errors = array();
			$debugs = array();


			//See whether property can be null or not. If null, assign a default value, when it exists
			if($property->getAnnotation()->isRequired() && is_null($newValue)){
				if($property->getAnnotation()->getDefault()){
					$newValue = $property->getAnnotation()->getDefault();
				}else if($property->getAnnotation()->isCollection()){
					$newValue = array();
				}else if($property->getAnnotation()->isObject()){
					$newValue = null;
				}else{
					$errors[] = MessageUtility::DATA_MODIFY_ERROR;
					$debugs[] = "Null value given for required property '$propertyName' of $class.";
				}
			}

			//Check whether specified value is of the correct type for this property
			if(!$property->isValidType($newValue) && !is_null($newValue)){
				$expectedType = $property->getAnnotation()->getType();
				if($property->getAnnotation()->isCollection()){
					$expectedElement = $property->getAnnotation()->getContainedType()->getType();
					if($expectedElement == "WadapiObject"){
						$expectedElement = $property->getAnnotation()->getContainedType()->getObjectClass();
					}
					$expectedType = "$expectedElement array";
				}else if($property->getAnnotation()->isObject()){
					$expectedType = "Object of class {$property->getAnnotation()->getObjectClass()}";
				}

				$errors[] = MessageUtility::DATA_MODIFY_ERROR;
				$debugs[] = "Invalid argument '$newValue' supplied for $class::$calledMethod(). $expectedType expected.";
			}

			//Check whether the specified value is a valid enumerated value for the property, if applicable.
			if(!$property->isEnumeratedValue($newValue)){
				$errors[] = MessageUtility::DATA_MODIFY_ERROR;
				$debugs[] = "Value given for property '$propertyName' of $class must be one of (".
						implode(", ", $property->getAnnotation()->getValues()).").";
			}

			//Check whether the specified value falls within the specified range, if applicable.
			if(!$property->inRange($newValue)){
				$errors[] = MessageUtility::DATA_MODIFY_ERROR;
				$debugs[] = "Out of range value '$newValue' given for property '$propertyName' of $class. Value must be in the range ".
						"[{$property->getAnnotation()->getMin()},{$property->getAnnotation()->getMax()}].";
			}

			if(!empty($errors)){
				Logger::fatal_error($errors, $debugs);
			}else{
				$this->$propertyName = $newValue;
			}
		}

		/*
		 * Generic add method for appending a list of new elements to the end of a collection
		 */
		 protected function appendToX($methodName, $propertyName, $insertValues){
			//Initiliase reflection objects
			$class = get_class($this);
			$property = Mirror::reflectProperty($class, $propertyName);

			$errors = array();
			$debugs = array();

			if(!is_array($insertValues)){
				$insertValues = array($insertValues);
			}

		 	if(!$property->isValidType($insertValues)){
				$errors[] = MessageUtility::DATA_MODIFY_ERROR;
				$debugs[] = "Invalid argument supplied for $class::$methodName(). ".
						"{$property->getAnnotation()->getContainedType()->getType()} expected.";
		 	}

		 	if(!empty($errors)){
				Logger::fatal_error($errors, $debugs);
		 	}else{
				$getter = "get".StringUtility::capitalise($propertyName);
				$tempArray = $this->$getter();

				foreach($insertValues as $insertValue){
					 $tempArray[] = $insertValue;
				}

				$setter = "set".StringUtility::capitalise($propertyName);
				$this->$setter($tempArray);
			}
		 }

		 /*
		  * Generic drop method for deleting a group of elements from a list based on their values
		  */
		 protected function dropFromX($methodName, $propertyName, $deleteValues){
			if(!is_array($deleteValues) || sizeof($deleteValues) == 1){
				$deleteValues = array($deleteValues);
			}

			foreach($deleteValues as $deleteValue){
				$tempArray = array();
				$getter = "get".StringUtility::capitalise($propertyName);

				$elements = $this->$getter();
				foreach($elements as $key => $element){
					$match = false;
					$property = Mirror::reflectProperty(get_class($this), $propertyName);
					$listType = $property->getAnnotation()->getContainedType();

					if($listType->isCollection()){
						if(is_array($deleteValue)){
							$match = $this->compareCollections($deleteValue, $element);
						}
					}else if($listType->isObject()){
						if(is_object($deleteValue)){
							$match = $deleteValue->equals($element);
						}
					}else{
						$match = ($deleteValue === $element);
					}

					if(!$match){
						$tempArray[$key] = $element;
					}
				}

				$setter = "set".StringUtility::capitalise($propertyName);
				$this->$setter($tempArray);
			}
		 }

		/*
		 * Generic method takes a key and value and inserts it into the given collection. If the
		 * key is already in the Collection, its value will be replaced.
		 */
		protected function insertToX($methodName, $propertyName, $insertKey, $insertValue){
			//Initiliase reflection objects
			$class = get_class($this);
			$property = Mirror::reflectProperty($this, $propertyName);

			$errors = array();
			$debugs = array();

		 	if(!$property->isValidType(array($insertValue))){
				$errors[] = MessageUtility::DATA_MODIFY_ERROR;
				$debugs[] = "Invalid argument supplied for $class::$methodName(). ".
						"{$property->getAnnotation()->getContainedType()->getType()} expected.";
		 	}

		 	//Verify key is valid
		 	if(!is_int($insertKey) && !is_string($insertKey)){
				$errors[] = MessageUtility::DATA_MODIFY_ERROR;
				$debugs[] = "Invalid key supplied for method $class::$methodName(). Integer or String expected.";
		 	}

		 	if(!empty($errors)){
				Logger::fatal_error($errors, $debugs);
		 	}else{
				$getter = "get".StringUtility::capitalise($propertyName);
				$tempArray = $this->$getter();
				$tempArray[$insertKey] = $insertValue;

				$setter = "set".StringUtility::capitalise($propertyName);
				$this->$setter($tempArray);
		 	}
		}

		/*
		 * Returns the value of a collection's element stored at the given key, if one exists.
		 * Null otherwise. The 'remove' parameter determines whether the returned element is
		 * removed from the collection or not.
		 */
		protected function viewFromX($methodName, $propertyName, $viewKey, $remove=false){
			//Initiliase reflection objects
			$class = get_class($this);

			$errors = array();
			$debugs = array();

		 	//Verify key is valid
		 	if(!is_int($viewKey) && !is_string($viewKey)){
				$errors[] = MessageUtility::DATA_ACCESS_ERROR;
				$debugs[] = "Invalid key supplied for method $class::$methodName(). Integer or String expected.";
		 	}

		 	if(!empty($errors)){
				Logger::fatal_error($errors, $debugs);
		 	}else{
				$returnValue = null;
				$getter = "get".StringUtility::capitalise($propertyName);
				if($this->$getter() && array_key_exists($viewKey,$this->$propertyName)){
					$collection = $this->$getter();
					$returnValue = $collection[$viewKey];

					if($remove){
						unset($collection[$viewKey]);

						$setter = "set".StringUtility::capitalise($propertyName);
						$this->$setter($collection);
					}
				}

				return $returnValue;
		 	}
		}

		/*
		 * Simply calls viewFromX with remove set to true.
		 */
		protected function takeFromX($methodName, $propertyName, $viewKey){
			return $this->viewFromX($methodName, $propertyName, $viewKey, true);
		}

		/*
		 * Returns true if all object properties of this and otherObject are the same
		 */
		public function equals($otherObject){
			if(get_class($this) !== get_class($otherObject)){
				return false;
			}

			$class = Mirror::reflectClass($this);
			$classProperties = $class->getProperties();
			$equal = true;

			for($i = 0; $i < sizeof($classProperties); $i++){
				$propertyName = $classProperties[$i]->getName();
				$propertyName = strtoupper(substr($propertyName,0,1)) . substr($propertyName,1);
				$getter = "get".StringUtility::capitalise($propertyName);

				if($classProperties[$i]->getAnnotation()->isCollection()){
					$equal = ($equal && $this->compareCollections($this->$getter(),$otherObject->$getter()));
				}else if($classProperties[$i]->getAnnotation()->isObject()){
					if(!is_null($this->$getter())){
						$equal = ($equal && $this->$getter()->equals($otherObject->$getter()));
					}else{
						$equal = ($equal && is_null($otherObject->$getter()));
					}
				}else{
					$equal = ($equal && ($this->$getter() === $otherObject->$getter()));
				}
			}

		  	return $equal;
		  }

		  /*
		   * Default top level behaviour that allows WadapiClasses to be treated as strings
		   */
		  public function __toString(){
			return "";
		  }

		  /*
		   * Method returns the default value for each field annotation type
		   */
		  private function getDefault($annotation){
				if($annotation->isNumeric()){
					return 0;
				}else if($annotation->isBoolean()){
					return false;
				}else if($annotation->isString()){
					return "";
				}else if($annotation->isObject()){
					return null;
				}else if($annotation->isCollection()){
					return null;
				}
		  }

		  //Compares collection properties recursively
		  private function compareCollections($a, $b){
			if(is_null($a) && is_null($b)){
				return true;
			}else if(is_null($a) || is_null($b)){
				return false;
			}

			if(sizeof($a) != sizeof($b)){
				return false;
			}

			$aValues = array_values($a);
			$bValues = array_values($b);

			$equals = true;
			for($i=0; $i<sizeof($aValues); $i++){
				if(is_null($aValues[$i])){
					$equals = $equals && is_null($bValues[$i]);
				}else if(is_object($aValues[$i])){
					$equals = $equals && $aValues[$i]->equals($bValues[$i]);
				}else if(is_array($aValues[$i])){
					$equals = $equals && $this->compareCollections($aValues[$i], $bValues[$i]);
				}else{
					$equals = $equals && ($aValues[$i] === $bValues[$i]);
				}
			}

			return $equals;
		  }
	}
?>
