<?php
	namespace Wadapi\Persistence;

	use Wadapi\Reflection\Mirror;
	use Wadapi\Routing\URLPattern;
	use Wadapi\Utility\ArrayUtility;
	use Wadapi\Utility\StringUtility;
	use Wadapi\Utility\MessageUtility;
	use Wadapi\Logging\Logger;

	class XMLGateway extends Gateway{
		//Pointer to root node of the xml document to which this gateway provides access
		private $xmlRoot;

		/** @File */
		protected $datasource;

		/*
		 * XMLGateway constructor initialises xmlRoot
		 */
		public function __construct($initialDatasource){
			parent::__construct(preg_replace("/".preg_replace("/\//","\/",PROJECT_PATH)."/","",$initialDatasource));
			$this->setXmlRoot($path.$this->getDatasource());
		}

		/*
		 * Find an object of the specified type given the supplied property name, value and comparison method
		 */
		public function find(){
			$arguments = func_get_args();
			$className = $arguments[0];
			$propertyName = (sizeof($arguments)>1)?$arguments[1]:"";
			$propertyValue = (sizeof($arguments)>2)?$arguments[2]:"None";
			$comparisonType = (sizeof($arguments)>3)?$arguments[3]:self::IS_EQUAL;

			$findResults = array();

			if(!$this->checkFindParameters($className, $propertyName, $propertyValue, $comparisonType)){
				return $findResults;
			}

			$className = strtolower($className);
			$listName = "{$className}list";
			$objectListNode = $this->xmlRoot->$listName;

			if(!$objectListNode){
				return $findResults;
			}

			//If searching for an object by ID, look for it in the gateway cache.
			if($propertyName == 'id' && $comparisonType == self::IS_EQUAL){
				if($cachedObject = QuarterMaster::release($propertyValue)){
					$findResults[] = $cachedObject;
					return $findResults;
				}
			}

			if($propertyName && $propertyValue !== "None"){
				$property = Mirror::reflectProperty($className, $propertyName);
				foreach($objectListNode->$className as $child){
					$comparisonResult = false;

					if(!$child){
						continue;
					}

					if($property->getAnnotation()->isObject()){
						$childObject = $this->loadObject($classname,$child);
						$comparisonResult = $this->compare($childObject, $propertyValue, $comparisonType);
					}else if($property->getAnnotation()->isCollection()){
						$childList = $this->loadList($child, $property->getCollectionType()->getType());
						$comparisonResult = $this->compare($childList, $propertyValue, $comparisonType);
					}else if($property->getAnnotation()->isUrl()){
						$url = new URLPattern($propertyValue);
						$comparisonResult = $url->match((string)$child->$propertyName);
					}else{
						$comparisonResult = eval("return \$child->\$propertyName $comparisonType \$propertyValue;");
					}

					if($comparisonResult == true){
						$findResults[] = $this->loadObject($className,$child);
					}
				}
			}else{
				foreach($objectListNode->children() as $child){
					$findResults[] = $this->loadObject($className,$child);
				}
			}

			return $findResults;
		}

		/*
		 * Saves the given object to the datasource. If it already exists, the entry is updated. If objects of this type are not yet
		 * stored, an appropriate object list is created.
		 */
		public function save($saveObjects){
			if(!is_array($saveObjects)){
				$saveObjects = array($saveObjects);
			}

			$xmlRoot = $this->xmlRoot;

			//Load a list of all classes currently stored in the datasource
			$storedClassListNodes = $xmlRoot->children();
			$storedClassLists = array();
			foreach($storedClassListNodes as $classListNode){
				$storedClassLists[] = substr($classListNode->getName(), 0, strlen($classListNode->getName()) - 4);
			}

			foreach($saveObjects as $object){
				if(!is_object($object) || !$this->checkUpdateParameters($object)){
					Logger::fatal_error(MessageUtility::DATA_MODIFY_ERROR, "Only objects of PersistentClass can be saved via a Gateway");
					return;
				}

				$objectClass = strtolower(get_class($object));
				if(!in_array($objectClass, $storedClassLists)){
					$xmlRoot->addChild("{$objectClass}list");
				}

				//Try to find $object in class list
				$classList = $this->findNode("{$objectClass}list", $xmlRoot);
				$objectFound = false;
				foreach($classList as $classListObject){
					$idNode = $this->findNode("id", $classListObject);
					if((string)$idNode == $object->getId()){
						$objectFound = true;
						break;
					}
				}

				//Delete old saved object if it already exists
				if($objectFound){
					$this->delete($object);
				}

				$this->insertObject($object, $classList);
			}

			$this->xmlRoot = $xmlRoot;
			$dom = new DomDocument('1.0');
			$dom->preserveWhiteSpace = false;
			$dom->formatOutput = true;
			$dom->loadXML($this->xmlRoot->asXML());
			$dom->save(PROJECT_PATH."/".$this->getDatasource());

			return true;
		}

		/*
		 * Removes the node corresponding to $loadObject from the data source, if it exists
		 */
		public function delete($deleteObjects){
			if(!is_array($deleteObjects)){
				$deleteObjects = array($deleteObjects);
			}

			foreach($deleteObjects as $object){
				if(!$this->checkUpdateParameters($object)){
					if(is_object($object)){
						$type = get_class($object);
					}else{
						$type = gettype($object);
					}

					Logger::warning(MessageUtility::DATA_MODIFY_ERROR, "XMLGateway can only save and delete persistent classes. $type is not a PersistentClass.");
					return;
				}

				$objectClass = strtolower(get_class($object));
				$objectClassList = $this->findNode("{$objectClass}list", $this->xmlRoot);

				//Search for index of class list in file and object in class list to facilitate delete
				$classLists = $this->xmlRoot->children();
				foreach($classLists as $classList){
					if($classList->getName() == "{$objectClass}list"){
						$objectCounter = 0;
						foreach($classList->children() as $objectNode){
							$idNode = $this->findNode('id', $objectNode);
							if((string)$idNode == $object->getId()){
								$classObjects = $classLists->children();
								$dom = dom_import_simplexml($objectNode);
								$dom->parentNode->removeChild($dom);
								break;
							}
							$objectCounter++;
						}

						break;
					}
				}
			}

			$this->xmlRoot->saveXml(PROJECT_PATH."/".$this->getDatasource());
			return true;
		}

		/*
		 * Updates XML datasource as well as $xmlRoot
		 */
		private function setXmlRoot($datasource){
			@$this->xmlRoot = simplexml_load_file($datasource);

			if(!is_object($this->xmlRoot)){
				Logger::fatal_error(MessageUtility::DATA_ACCESS_ERROR, "An XMLGateway cannot be created from non well-formed xml datasource $datasource.");
				return;
			}
		}

		/*
		 * Search the XML tree for the first node with the specified $nodeName, starting at the $root node
		 */
		private function findNode($nodeName, &$root){
			if($root->getName() == $nodeName){
				return $root;
			}else if($root->count() == 0){
				return null;
			}

			foreach($root->children() as $child){
				$childResult = $this->findNode($nodeName, $child);
				if($childResult != null){
					return $childResult;
				}
			}

			return null;
		}

		/*
		 * Loads an object from an XML node using reflection to determine the object structure
		 */
		private function loadObject($className, $node){
			if(!$node || $this->isLeaf($node)){
				return null;
			}

			$constructorArray = array();
			$objectID = (string)$node->id;
			if($objectID){
				if($cachedObject = QuarterMaster::release($objectID)){
					return $cachedObject;
				}
			}

			$class = Mirror::reflectClass($className);

			foreach($class->getProperties(false) as $property){
				$propertyName = strtolower($property->getName());
				$propertyValue = $node->$propertyName;

				if($property->getAnnotation()->isObject()){
					$subObjectID = $propertyValue->id;
					$subObjectClass = $propertyValue->type;

					if($subObjectID){
						$constructorArray[] = $subObjectClass::bindInstance($subObjectID, $this);
					}else{
						$constructorArray[] = $this->loadObject($subObjectClass,$propertyValue);
					}
				}else if($property->getAnnotation()->isCollection()){
					$listType = $property->getAnnotation()->getContainedType();
					$constructorArray[] = $this->loadList($propertyValue, $listType);
				}else if($property->getAnnotation()->isInteger()){
					$constructorArray[] = (int)$propertyValue;
				}else if($property->getAnnotation()->isFloat() || $property->getAnnotation()->isMonetary()){
					$constructorArray[] = (float)$propertyValue;
				}else{
					$constructorArray[] = (string)$propertyValue;
				}
			}

			$objectInstance = $class->newInstanceArgs($constructorArray);
			QuarterMaster::store($objectInstance);
			return $objectInstance;
		}

		/*
		 * Loads a list from an XML Node using the $listType to determine how it should be loaded.
		 */
		private function loadList($node, $listType){
			$childList = array();

			if(!$node){
				return $childList;
			}

			foreach($node->children() as $listElement){
				if($listType->isCollection()){
					$listElementChildren = $listElement->children();
					$nextElement = $this->loadList($listElementChildren[0]);
				}else if($listType->isObject() && class_exists($listType->getObjectClass())){
					$class = Mirror::reflectClass($listType->getObjectClass());
					if($class->descendsFrom('Wadapi\Persistence\PersistentClass')){
						$listElementChildren = $listElement->children();
						$nextElement = $this->loadObject($listType->getObjectClass(),$listElement);
					}else{
						$nextElement = (string)$listElement;
					}
				}else{
					$nextElement = (string)$listElement;
				}

				if(preg_match("/{$node->getName()}[0-9]+/", $listElement->getName())){
					$childList[] = $nextElement;
				}else{
					$childList[$listElement->getName()] = $nextElement;
				}
			}

			return $childList;
		}

		/*
		 * Inserts an object into the given parent node, with the given name is specified.
		 */
		private function insertObject($object, &$root, $nodeName = ""){
			$objectClass = $object?get_class($object):"";

			if(!$nodeName){
				$nodeName = $objectClass;
			}

			$root->addChild(strtolower($nodeName));
			$objectNode = $this->lastInserted($root);

			if($nodeName != $objectClass){
				$objectNode->addAttribute("type", strtolower($objectClass));
			}

			//if $object is null leave inserted node empty
			if(!$object){
				return;
			}

			$class = Mirror::reflectClass($object);
			foreach($class->getProperties() as $property){
				$propertyName = $property->getName();
				$getterMethod = "get".StringUtility::capitalise($propertyName);
				$propertyValue = $object->$getterMethod();

				if(!$property->getAnnotation()->isObject() && !$property->getAnnotation()->isCollection()){
					$objectNode->addChild($propertyName, $propertyValue);
				}

				if($property->getAnnotation()->isObject()){
					$this->insertObject($propertyValue, $objectNode, $propertyName);
				}else if($property->getAnnotation()->isCollection()){
					$this->insertList($propertyValue, $objectNode, $propertyName);
				}
			}
		}

		/*
		 * Inserts the provided list into the given parent node with the specified name
		 */
		private function insertList($list, &$root, $nodeName){
			if(!is_array($list) || sizeof($list) == 0){
				return;
			}

			//Add list node to root
			$root->addChild($nodeName);
			$listNode = $this->lastInserted($root);

			foreach($list as $key => $element){
				if(is_int($key)){
					$elementName = "$nodeName$key";
				}else{
					$elementName = str_replace(" ","-",strval($key));
				}

				//Add element node to list
				if(!is_object($element) && !is_array($element)){
					if(is_bool($element)){
						if(!$element){
							$element = 0;
						}

						$listNode->addChild($elementName, $element);
					}else{
						$listNode->addChild($elementName, $element);
					}
				}else{
					$listNode->addChild($elementName);
				}

				$elementNode = $this->lastInserted($listNode);

				if(is_object($element)){
					$this->insertObject($element, $elementNode, get_class($element));
				}else if(is_array($element)){
					$this->insertList($element, $elementNode, "{$elementName}list");
				}
			}
		}

		/*
		 * Returns the most recently object child node of root
		 */
		private function lastInserted(&$root){
			$rootChildren = $root->children();
			$lastChildIndex = sizeof($rootChildren) - 1;
			return $rootChildren[$lastChildIndex];
		}

		/*
		 * Compares both argument objects according to the provided comparison type
		 */
		 private function compare($a, $b, $comparisonType){
		 	if(is_object($a)){
		 		//Compare objects together using comparator methods
				if(is_object($b)){
					if(get_class($a) != get_class($b)){
						return false;
					}else{
						return eval("return '{$a->getId()}' $comparisonType '{$b->getId()}';");
					}
				//Check if object 'compares' to at least one object in list
		 		}else if(is_array($b)){
		 			return $this->compareElementToList($a, $b, $comparisonType);
		 		//An object cannot be compared to a base type
		 		}else{
		 			return false;
		 		}
		 	}else if(is_array($a)){
				if(sizeof($a) == 0){
					return false;
				}
		 		//Check if object $b is in array
		 		if(is_object($b)){
					return $this->compareElementToList($b, $a, $comparisonType);
				//Comparison of two lists is invalid
		 		}else if(is_array($b)){
		 			//If comparing for equality return true if array $b is a subset of $a
		 			if($comparisonType == Gateway::IS_EQUAL && ArrayUtility::is_subset($b, $a)){
						return true;
					//If comparing for inequality return true if array $b is not a subset of $a
					}else if($comparisonType == Gateway::NOT_EQUAL && !ArrayUtility::is_subset($b, $a)){
						return true;
					//Any other comparison type is not applicable for lists and should return false
		 			}else{
		 				return false;
		 			}
		 		//Check if base type $b is in array
		 		}else{
		 			return $this->compareElementToList($b, $a, $comparisonType);
		 		}
		 	}else{
		 		//Comparison of base type to object is invalid
		 		if(is_object($b)){
		 			return false;
		 		//Check if base type is in array $b
		 		}else if(is_array($b)){
		 			return $this->compareElementToList($a, $b, $comparisonType);
				//Compare base types $a and $b using the comparison operator
		 		}else{
		 			return eval("return '$a' $comparisonType '$b';");
		 		}
		 	}
		 }

		/*
		 * Checks whether the given element is in the given list
		 */
		private function compareElementToList($element, $list, $comparisonType){
			if(!is_array($list) || sizeof($list) == 0){
				return false;
			}

			$comparisonResult = false;
			foreach($list as $listElement){
				if(is_object($element)){
					$elementCompare = $this->compare($element, $listElement, $comparisonType);
				}else{
					$elementCompare = eval("return '$element' $comparisonType '$listElement';");
				}

				if($comparisonType == self::IS_EQUAL){
					$comparisonResult = $comparisonResult || $elementCompare;
				}else{
					$comparisonResult = $comparisonResult && $elementCompare;
				}
			}

			return $comparisonResult;
		}

		/*
		 * Determines whether the specified node is a leaf node or not
		 */
		private function isLeaf($node){
			return (string)$node == "" && $node->count() == 0;
		}
	}
?>
