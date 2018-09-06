<?php
	namespace Wadapi\Persistence;

	use Wadapi\System\WadapiClass;
	use Wadapi\Reflection\Mirror;
	use Wadapi\Utility\StringUtility;
	use Wadapi\Utility\MessageUtility;
	use Wadapi\Logging\Logger;

	abstract class PersistentClass extends WadapiClass{
		/*
		 * Keeps track of IDs dispensed to new objects to avoid duplicates
		 */
		private static $dispensedIDs = array();

		/*
		 * Records whether or not object members have been written to
		 */
		private $dirtyBits = array();

		/*
		 * Records whether or not primitive object members have been set
		 */
		private $loadedBits = array();

		/*
		 * Indicates whether this object has already been written to the database
		 */
		/** @Boolean(required=true, default=false) */
		private $persisted;

		/*
		 * A unique integer identifying this object from other instances of the same class.
		 */
		/** @WadapiString(required=true, min=0, max=20)*/
		protected $id;

		/*
		 * A unix timestamp representing when this element was created.
		 */
		/** @WadapiString */
		protected $created;

		/*
		 * A unix timestamp representing when this element last had one of its values changed.
		 */
		/** @WadapiString */
		protected $modified;

		/*
		 * An instance of the Gateway type used to load this object
		 */
		/** @WadapiObject(class='Gateway') */
		protected $gateway;

		/*
		 * Boolean flag indicating whether or not this object's data has been completely loaded.
		 */
		/** @Boolean(required=true, default=true) */
		protected $loaded;

		/*
		 * Constructer returns unbound instance of the object. An ID is automatically generated and will be
		 * used to refer to the object going forward.
		 */
		public function __construct(){
			$arguments = func_get_args();

			//Add default gateway and loaded values to constructor arguments
			$arguments = array_merge(array(self::generateID(), strval(time()), strval(time()), null, true), $arguments);
			call_user_func_array(array('parent','__construct'), $arguments);

			//Initialise Dirty Bits
			foreach(Mirror::reflectClass(get_class($this))->getProperties(false) as $property){
				if(!in_array($property->getName(),array('id','created','modified'))){
					$this->dirtyBits[$property->getName()] = true;

					if(!$property->getAnnotation()->isObject() && !$property->getAnnotation()->isCollection()){
						$this->loadedBits[$property->getName()] = true;
					}
				}
			}

			$this->loadedBits["created"] = true;
			$this->loadedBits["modified"] = true;

			QuarterMaster::store($this);
		}

		/*
		 * Pseudo-constructor that returns a bound version of the object with all its fields null. This
		 * class will be loaded when one of its fields is accessed.
		 */
		public static function bindInstance($id, $gateway){
			$currentClass = get_called_class();
			$reflectedClass = Mirror::reflectClass($currentClass);
			if($reflectedClass->isAbstract() && $reflectedClass->getDescendants()){
				$currentClass = $reflectedClass->getDescendants()[0]->getName();
			}else{
				return null;
			}

			$instance = new $currentClass();

			$instance->setId($id);
			$instance->setGateway($gateway);
			$instance->setLoaded(false);

			foreach($instance->loadedBits as $field => $loadedBit){
				$instance->loadedBits[$field] = false;
			}

			$instance->loadedBits["created"] = false;
			$instance->loadedBits["modified"] = false;

			//A bound instance always starts clean
			$instance->markClean();

			//Mark any empty child objects as clean
			foreach(Mirror::reflectClass($instance)->getProperties(false) as $property){
				$field = $property->getName();
				if($property->getAnnotation()->isObject() && $instance->$field){
					$instance->$field->markClean();
				}
			}

			//A bound instance is by definition persisted
			$instance->markPersisted();

			//Take a snapshot of the object stub (it will be retaken when the object is loaded)
			Photographer::takeSnapshot($instance);

			return $instance;
		}

		  /*
		   * Default persistent level behaviour that returns a PersistentCLass ID as its string representation
		   */
		  public function __toString(){
			return $this->getId();
		  }

		/*
		 * Overridden getX method to account for lazy loading
		 */
		 protected function getX($searchProperty){
			$annotation = Mirror::reflectProperty($this,$searchProperty)->getAnnotation();

			if(!in_array($searchProperty,array('id','gateway','loaded')) && !$annotation->isCollection() && !$this->isLoaded($searchProperty)){
				$this->loadData();
			}

			if($annotation->isCollection() && is_null($this->$searchProperty)){
				if($this->isPersisted()){
					$this->$searchProperty = $this->loadCollections(
						$annotation->getContainedType(), //ListAnnotation
						get_class($this).StringUtility::capitalise($searchProperty), //ListTable
						array($this->getId()), //Current Object ID
						strtolower(get_class($this)) //ParentTable
					)[$this->getId()];

					//Take a snapshot of the recently loaded collection
					Photographer::takeSnapshot($this,$searchProperty);
				}else{
					$this->$searchProperty = array();
				}

				$this->loadedBits[$searchProperty] = true;
				$this->dirtyBits[$searchProperty] = false;
			}

		 	return parent::getX($searchProperty);
		 }

		/*
		 * Overridden setX method to update last modified time
		 */
		 protected function setX($propertyName, $newValue, $calledMethod){
			parent::setX($propertyName, $newValue, $calledMethod);
			//Set Loaded Bit to True
			if(array_key_exists($propertyName,$this->loadedBits)){
				$this->loadedBits[$propertyName] = true;
			}

			//If the modified time is being set, change the value and move one
			if($propertyName === "modified"){
				return;
			}

			//See whether we should mark this field as dirty based on its original value
			if(!Photographer::compareToSnapshot($this,$propertyName)){
				//Don't change the modified stamp if a collection changes as the object table will not be updated
				$annotation = Mirror::reflectProperty($this,$propertyName)->getAnnotation();
				if(!$annotation->isCollection()){
					$this->modified = strval(time());
				}

				if(array_key_exists($propertyName,$this->dirtyBits)){
					$this->dirtyBits[$propertyName] = true;
				}
			}else{
				$this->dirtyBits[$propertyName] = false;
			}

			return;

		 }

		 /*
		  * Returns an object's creation time object based on its ID
		  */
		 protected function getCreationTime(){
			$createTime = $this->getCreated();
			return date('Y-m-d G:i:s',$createTime);
		 }

		 /*
		  * Returns true if the specified field has been modified, false otherwise, If no field is specified, the function
		  * returns true if aby of the object's fields have been modified, and false otherwise
		  */
		  protected function isDirty($field=""){
			$class = Mirror::reflectClass($this);
			if($field && !$class->hasProperty($field)){
				Logger::fatal_error(MessageUtility::DATA_ACCESS_ERROR,"Can't determine dirty status on non-existant field '$field'");
			}

			if($field){
				$property = Mirror::reflectProperty($this,$field)->getAnnotation();
				if($property->isObject()){
					return $this->dirtyBits[$field] || (is_null($this->$field)?false:$this->$field->isDirty());
				}else if($property->isCollection()){
					$leaves = $this->$field;
					if(is_null($leaves)){
						return false;
					}

					$containedType = $property->getContainedType();
					while($containedType->isCollection()){
						$newLeaves = array();
						foreach($leaves as $leaf){
							foreach($leaf as $element){
								$newLeaves[] = $element;
							}
						}

						$containedType = $containedType->getContainedType();
						$leaves = $newLeaves;
					}

					if($containedType->isObject()){
						foreach($leaves as $leaf){
							if($leaf && $leaf->isDirty()){
								return true;
							}
						}
					}

					return $this->dirtyBits[$field];
				}else if(array_key_exists($field,$this->dirtyBits)){
					return $this->dirtyBits[$field];
				}else{
					return true;
				}
			}else{
				foreach($this->dirtyBits as $field => $dirtyBit){
					if($this->isDirty($field)){
						return true;
					}
				}

				return false;
			}
		  }

		 /*
		  * Marks the object as clean either because it was freshly loaded, or recently written to the database
		  */
		  protected function markClean(){
			foreach($this->dirtyBits as $field => $dirtyBit){
				$this->dirtyBits[$field] = false;
			}
		  }

		 /*
		  * Returns true if the specified field has been loaded from the database, false otherwise, If no field is specified, the function
		  * returns true if all of the object's fields have been loaded, and false otherwise
		  */
		  protected function isLoaded($field=""){
			$class = Mirror::reflectClass($this);
			if($field && !$class->hasProperty($field)){
				Logger::fatal_error(MessageUtility::DATA_ACCESS_ERROR,"Can't determine loaded status on non-existant field '$field'");
			}

			if($field){
				$property = Mirror::reflectProperty($this,$field)->getAnnotation();
				if($property->isObject()){
					return is_null($this->$field)?false:$this->$field->isLoaded();
				}else if($property->isCollection()){
					return !is_null($this->$field);
				}else if(array_key_exists($field,$this->loadedBits)){
					return $this->loadedBits[$field];
				}else{
					return false;
				}
			}else{
				foreach(Mirror::reflectClass($this)->getProperties(false) as $property){
					if(!$this->isLoaded($property->getName())){
						return false;
					}
				}

				return true;
			}
		  }

		  /*
		   * Indicates whether or not this class has been persisted yet
		   */
		  protected function isPersisted(){
			return $this->persisted;
		  }

		  /*
		   * Marks the object as persisted
		   */
		  protected function markPersisted(){
			return $this->persisted = true;
		  }

		 /*
		  * Generates a unique ID number for the current object based on the current Unix timestamp
		  */
		  private static function generateID(){
			$id = number_format(microtime(true)*10000,0,'','');

			do{
				$id++;
			}while(in_array($id, self::$dispensedIDs));

			self::$dispensedIDs[] = $id;
			return strval($id);
		  }

		/*
		 * If an instance of the object has been bound but not loaded.
		 */
		 private function loadData(){
			if($this->loaded || !$this->getGateway()){
				return;
			}

			//Load object data
			$searcher = new Searcher();
			$searcher->addCriterion("id",Criterion::EQUAL,$this->getId());

			$className = get_class($this);
			$object = $this->gateway->findUnique($className,$searcher);

			if(!$object){
				return;
			}

			//Find list all class properties including those of parents
			$class = Mirror::reflectClass($className);
			$persistentClass = Mirror::reflectClass("PersistentClass");
			$propertyList = array_diff($class->getProperties(),$persistentClass->getProperties());
			$presetData = array();

			foreach($propertyList as $property){
				$propertyName = $property->getName();

				//Store user set data so it can be restored after object load
				if(array_key_exists($propertyName,$this->loadedBits) && $this->loadedBits[$propertyName]){
					$presetData[$propertyName] = $this->$propertyName;
				}

				$this->$propertyName = $object->$propertyName;

				if(!$property->getAnnotation()->isCollection()){
					$this->loadedBits[$propertyName] = true;
				}
			}

			//Set created and modified time flags
			$this->created = $object->created;
			$this->modified = $object->modified;

			//Mark all fields as clean
			foreach($this->dirtyBits as $field => $dirtyBit){
				//Values defiend by the user before loading should be kept as dirty
				if(array_key_exists($field,$this->loadedBits) && $this->loadedBits[$field]){
					continue;
				}

				$this->dirtyBits[$field] = false;
			}

			$this->loaded = true;

			//Replace the stub snapshot with a snapshot of the full class
			Photographer::takeSnapshot($this);

			//Restore data that was preset by the user
			foreach($presetData as $propertyName => $propertyValue){
				$setter = "set".StringUtility::capitalise($propertyName);
				$this->$setter($propertyValue);
			}
		}

		private function loadCollections($listAnnotation, $listTable, $parentIds, $parentColumn = "parentKey"){
			$fields = "$parentColumn,name";
			if(!$listAnnotation->isCollection()){
				$fields .= ",value";
			}

			$results = array();
			$results = DatabaseAdministrator::execute("SELECT $fields FROM $listTable WHERE ".strtolower(get_class($this))." = {$this->getId()} AND ".
									"$parentColumn IN ('".implode("','",$parentIds)."')");

			if($listAnnotation->isCollection()){
				$keys = array();

				foreach($results as $result){
					$keys[] = $result['name'];
				}

				if($keys){
					$nestedCollections = $this->loadCollections($listAnnotation->getContainedType(), $listTable."List", $keys);
				}
			}

			$collections = array();
			foreach($parentIds as $parentId){
				$collections[$parentId] = array();
			}


			foreach($results as $result){
				if($listAnnotation->isObject()){
					if($result['value']){
						//Set object stub for lazy loading
						$containedClass = $listAnnotation->getObjectClass();
						$collections[$result[$parentColumn]][$result['name']] = call_user_func_array(array($containedClass,"bindInstance"),array($result['value'],new SQLGateway()));
					}else{
						$collections[$result[$parentColumn]][$result['name']] = null;
					}
				}else if($listAnnotation->isCollection()){
					if(array_key_exists($result['name'], $nestedCollections)){
						$collections[$result[$parentColumn]][$result['name']] = $nestedCollections[$result['name']];
					}else{
						$collections[$result[$parentColumn]][$result['name']] = array();
					}
				}else{
					$collections[$result[$parentColumn]][$result['name']] = $listAnnotation->isBoolean()?boolval($result['value']):$result['value'];
				}
			}

			return $collections;
		 }
	}
?>
