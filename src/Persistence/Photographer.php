<?php
	namespace Wadapi\Persistence;

	use Wadapi\System\Worker;
	use Wadapi\Reflection\Mirror;
	use Wadapi\Utility\StringUtility;
	use Wadapi\Utility\MessageUtility;
	use Wadapi\Logging\Logger;

	class Photographer extends Worker{
		//An array of all the object snapshots taken by the photographer
		private static $gallery = array();

		/*
		 * Takes a snapshot of the object at the time it was passed to the method.
		 * The snapshot can be used in the future to see if the object has changed.
		 * If a $field is specified only this field will be added/replaced to the snapshot
		 */
		protected static function takeSnapshot($object,$field = ""){
			//Verify that the argument $object is a PersistentClass
			$class = Mirror::reflectClass($object);
			if(!$class->descendsFrom('PersistentClass')){
				Logger::warning(MessageUtility::UNEXPECTED_ARGUMENT_WARNING, "Photographer can only take snapshots of PersistentClass objects.");
				return;
			}

			$snapshot = array();
			foreach($class->getProperties(false) as $property){
				$propertyName = $property->getName();
				$getter = "get".StringUtility::capitalise($propertyName);

				if($field && $field != $propertyName && array_key_exists($object->getId(),self::$gallery)){
					$snapshot[$propertyName] = self::$gallery[$object->getId()][$propertyName];
				}else{
					//Taking a snapshot of an object should not load it into memory if it is not already
					if(!$object->isLoaded($propertyName)){
						$snapshot[$propertyName] = null;
					}else if($property->getAnnotation()->isObject()){
						$snapshot[$propertyName] = $object->$getter()->getId();
					}else if($property->getAnnotation()->isCollection()){
						if($object->isLoaded($propertyName)){
							$containedType = $property->getAnnotation()->getContainedType();
							$snapshot[$propertyName] = self::takeCollectionSnapshot($containedType,$object->$getter());
						}else{
							$snapshot[$propertyName] = null;
						}
					}else{
						$snapshot[$propertyName] = $object->$getter();
					}
				}
			}

			self::$gallery[$object->getId()] = $snapshot;
		}

		/*
		 * Returns the snapshot taken for the specified $object. If a $field is specified, only the snapshot fragment for that field will be returned.
		 */
		 protected static function getSnapshot($object,$field=""){
			//Verify that the argument $object is a PersistentClass
			$class = Mirror::reflectClass($object);
			if(!$class->descendsFrom('PersistentClass')){
				Logger::warning(MessageUtility::UNEXPECTED_ARGUMENT_WARNING, "Photographer only stores snapshots of PersistentClass objects.");
				return;
			}

			if(!array_key_exists($object->getId(),self::$gallery)){
				return null;
			}

			$snapshot = self::$gallery[$object->getId()];
			if($field){
				if(!array_key_exists($field,$snapshot)){
					return null;
				}else{
					return $snapshot[$field];
				}
			}else{
				return $snapshot;
			}
		 }

		/*
		 * Compares the passed $object to this object's snapshot if it exists. If a $field is specified, only this value will be compared.
		 * Otherwise all of the object's fields will be compared to the snapshot. Returns true if the object matches its snapshot.
		 * False otherwise. If a snapshot does not exist, the function returns false.
		 */
		protected static function compareToSnapshot($object,$field=""){
			//Verify that the argument $className is a PersistentClass
			$class = Mirror::reflectClass($object);
			if(!$class->descendsFrom('PersistentClass')){
				Logger::warning(MessageUtility::UNEXPECTED_ARGUMENT_WARNING, "Photographer can only take snapshots of PersistentClass objects.");
				return;
			}

			//Return false if there is no snapshot of this object
			if(!array_key_exists($object->getId(),self::$gallery)){
				return false;
			}

			if($field){
				//Return false if object's snapshot does not include this field
				if(!array_key_exists($field,self::$gallery[$object->getId()])){
					return false;
				}

				//Comparing a snapshot should not force lazy-loading on an object
				if(!$object->isLoaded($field)){
					return false;
				}

				$property = Mirror::reflectProperty($object,$field);
				$getter = "get".StringUtility::capitalise($property->getName());
				if($property->getAnnotation()->isObject()){
					return $object->$getter()->getId() === self::$gallery[$object->getId()][$field];
				}else if($property->getAnnotation()->isCollection()){
					$containedType = $property->getAnnotation()->getContainedType();
					return self::compareCollectionToSnapshot($containedType,$object->$getter(),self::$gallery[$object->getId()][$field]);
				}else if($property->getAnnotation()->isFloat() || $property->getAnnotation()->isMonetary()){
					return abs($object->$getter() - self::$gallery[$object->getId()][$field]) < 0.00000001;
				}else{
					return $object->$getter() === self::$gallery[$object->getId()][$field];
				}
			}else{
				foreach($class->getProperties(false) as $property){
					if(!self::compareToSnapshot($object,$property->getName())){
						return false;
					}
				}

				return true;
			}
		}

		//Helper method that takes array snapshots
		private static function takeCollectionSnapshot($listType,$collection){
			$listSnapshot = array();
			foreach($collection as $key => $element){
				if($listType->isCollection()){
					$listSnapshot[$key] = self::takeCollectionSnapshot($listType->getContainedType(),$element);
				}else if($listType->isObject()){
					$listSnapshot[$key] = $element->getId();
				}else{
					$listSnapshot[$key] = $element;
				}
			}

			return $listSnapshot;
		}

		//Helper method to compare collections
		private static function compareCollectionToSnapshot($listType,$collection,$snapshot){
			if(is_null($snapshot)){
				return false;
			}

			if(sizeof($collection) !== sizeof($snapshot)){
				return false;
			}

			foreach($collection as $key => $element){
				if($listType->isCollection()){
					$subsnapshot = array_key_exists($key,$snapshot)?$snapshot[$key]:null;
					if(!self::compareCollectionToSnapshot($listType->getContainedType(),$element,$subsnapshot)){
						return false;
					}
				}else if($listType->isObject()){
					if($element->getId() !== $snapshot[$key]){
						return false;
					}
				}else{
					if($element !== $snapshot[$key]){
						return false;
					}
				}
			}

			return true;
		}
	}
?>
