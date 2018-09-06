<?php
	namespace Wadapi\Reflection;

	use Dadlian\Addendum\Annotations\ReflectionAnnotatedClass;

	class WadapiReflectionClass extends ReflectionAnnotatedClass{
		//Array of all known ancestors
		private $ancestors = array();

		//Array of all known descendants
		private $descendants = array();

		//Array of direct class properties
		private $directProperties = array();

		//Array of inherited and direct class properties
		private $extendedProperties = array();

		//The detemined class hierarchy
		private $classHierarchy = array();

		//Returns this class' parent as a WadapiReflectionClass object
		public function getParentClass(){
			$grandparent = parent::getParentClass();

			if($grandparent){
				return Mirror::reflectClass($grandparent->getName());
			}else{
				return null;
			}
		}

		//Return true if this class is descended from the specified parent
		public function descendsFrom($ancestorName, $level = 0){
			if(!is_object($ancestorName) && !is_string($ancestorName)){
				return false;
			}

			if(is_object($ancestorName)){
				$ancestorName = get_class($ancestorName);
			}

			if(array_key_exists($ancestorName, $this->ancestors)){
				return $this->ancestors[$ancestorName];
			}else{
				$this->ancestors[$ancestorName] = true;
			}

			if($this->getName() != $ancestorName){
				$parentClass = $this->getParentClass();

				if($parentClass == null){
					$this->ancestors[$ancestorName] = false;
				}else{
					$this->ancestors[$ancestorName] = $parentClass->descendsFrom($ancestorName, $level+1);
				}
			}

			return $this->ancestors[$ancestorName];
		}

		//Returns a list of classes that have this class as an ancestor
		public function getDescendants(){
			if(!$this->descendants){
				foreach(get_declared_classes() as $declaredClassName){
					$declaredClass = Mirror::reflectClass($declaredClassName);
					if($declaredClass->descendsFrom($this->getName()) && $declaredClass->getName() != $this->getName()){
						$this->descendants[] = $declaredClass;
					}
				}
			}

			return $this->descendants;
		}

		//Return an array of all of this class' public and protected properties, including inherited properties unless specified otherwise
		public function getProperties($extendedProperties = true){
			if(!$this->directProperties){
				$parentProperties = array();
				$parentPropertyNames = array();

				if($this->getParentClass()){
					$parentProperties = $this->getParentClass()->getProperties();

					foreach($parentProperties as $parentProperty){
						$parentPropertyNames[] = $parentProperty->getName();
					}
				}

				$classProperties = parent::getProperties(ReflectionProperty::IS_PUBLIC | ReflectionProperty::IS_PROTECTED);
				foreach($classProperties as $property){
					if(!in_array($property->getName(), $parentPropertyNames)){
						$this->directProperties[] = new WadapiReflectionProperty($this->getName(), $property->getName());
					}
				}

				$this->extendedProperties = array_merge($parentProperties,$this->directProperties);
			}

			if($extendedProperties){
				return $this->extendedProperties;
			}else{
				return $this->directProperties;
			}
		}

		//Return an array of all the classes in this class' inheritance hierarchy
		public function getClassHierarchy(){
			if(!$this->classHierarchy){
				if($this->getParentClass()){
					$this->classHierarchy = $this->getParentClass()->getClassHierarchy();
				}

				$this->classHierarchy[] = $this;
			}

			return $this->classHierarchy;
		}
	}
?>
