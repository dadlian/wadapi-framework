<?php
	namespace Wadapi\Persistence;

	use Wadapi\Reflection\Mirror;
	use Wadapi\System\Detective;
	use Wadapi\Utility\ArrayUtility;
	use Wadapi\Utility\StringUtility;
	use Wadapi\Utility\MessageUtility;
	use Wadapi\Logging\Logger;

	class SQLGateway extends Gateway{
		//A map of loaded class object members for delayed loading
		private $objectMembers;

		//A map of queued insert queries and their arguments for delayed execution
		private $insertQueries;

		public function count($className, $searcher=null){
			return $this->find($className,$searcher,null,0,0,true,true);
		}

		public function find($className, $searcher=null, $sorter=null, $records=0, $start=0,$lazyLoad=true,$countResults=false){
			$objects = array();

			//Verify that $className is a string
			if(!is_string($className)){
				Logger::fatal_error(MessageUtility::UNEXPECTED_ARGUMENT_WARNING, "SQLGateway find expects a string search class argument. ".
										gettype($className)." given.");
				return;
			}

			//Verify that the argument $className is valid and exists
			if(!class_exists($className)){
				Logger::warning(MessageUtility::UNEXPECTED_ARGUMENT_WARNING, "SQLGateway can only search for PersistentClass objects. ". $className.
									" is not Persistent.");
				return $countResults?0:$objects;
			}

			//Verify that the argument $className is a PersistentClass
			$reflectedClass = Mirror::reflectClass($className);
			if(!$reflectedClass->descendsFrom('Wadapi\Persistence\PersistentClass')){
				Logger::warning(MessageUtility::UNEXPECTED_ARGUMENT_WARNING, "SQLGateway can only search for PersistentClass objects. ". $className.
									" is not Persistent.");
				return $countResults?0:$objects;
			}

			//Verify that the reflect class is not abstract
			if($reflectedClass->isAbstract()){
				Logger::warning(MessageUtility::UNEXPECTED_ARGUMENT_WARNING, "SQLGateway cannot search for abstract PersistentClasses. $className is abstract.");
				return $countResults?0:$objects;
			}

			//Initialise an empty searcher if none was specified
			if(is_null($searcher)){
				$searcher = new Searcher(array());
			}

			//Test that searcher is actually a searcher object
			if(!is_object($searcher) || get_class($searcher) != "Wadapi\Persistence\Searcher"){
				if(is_object($searcher)){
					$type = get_class($searcher);
				}else{
					$type = gettype($searcher);
				}

				Logger::fatal_error(MessageUtility::UNEXPECTED_ARGUMENT_WARNING,"SQLGateway find expects a Searcher class object argument as a searcher, $type given.");
				return $countResults?0:$objects;
			}

			//Initialise an empty sorter if none was specified
			if(is_null($sorter)){
				$sorter = new Sorter(array());
			}

			//Test that sorter is actually a sorter object
			if(!is_object($sorter) || get_class($sorter) != "Wadapi\Persistence\Sorter"){
				if(is_object($sorter)){
					$type = get_class($sorter);
				}else{
					$type = gettype($sorter);
				}

				Logger::fatal_error(MessageUtility::UNEXPECTED_ARGUMENT_WARNING,"SQLGateway find expects a Sorter class object argument as a sorter, $type given.");
				return $countResults?0:$objects;
			}

			if(!is_int($records)){
				Logger::fatal_error(MessageUtility::UNEXPECTED_ARGUMENT_WARNING,"SQLGateway find expects integer limit values, ".gettype($records)." given.");
				return $countResults?0:$objects;
			}else if($records < 0){
				Logger::fatal_error(MessageUtility::UNEXPECTED_ARGUMENT_WARNING,"SQLGateway find limit values must be greater than or equal to 0.");
				return $countResults?0:$objects;
			}

			if(!is_int($start)){
				Logger::fatal_error(MessageUtility::UNEXPECTED_ARGUMENT_WARNING,"SQLGateway find expects integer start values, ".gettype($start)." given.");
				return $countResults?0:$objects;
			}else if($start < 0){
				Logger::fatal_error(MessageUtility::UNEXPECTED_ARGUMENT_WARNING,"SQLGateway find start values must be greater than or equal to 0.");
				return $countResults?0:$objects;
			}

			$class = Mirror::reflectClass($className);
			$tableName = $class->getShortName();

			//Construct a query and search for all objects matching it
			$where = array();
			$whereArguments = array();

			$criteria = array();
			if($searcher->getCriteria()){
				$criteria = $searcher->getCriteria();
			}

			foreach($criteria as $criterion){
				$condition = $criterion->getCondition();

				//We search the object table using its own arguments
				if($class->hasProperty($criterion->getField())){
					$property = Mirror::reflectProperty($className, $criterion->getField());
					$annotation = $property->getAnnotation();
					$values = $criterion->getValues();
					$field = $criterion->getField();

					//Optimisation: If finding an object by ID load it from the cache if it exists and return it immediately
					$cachedObjects = array();
					foreach($values as $value){
						if($field == 'id' && $condition == Criterion::EQUAL && QuarterMaster::release($value) &&
							get_class(QuarterMaster::release($value)) == $className && sizeof($criteria) == 1){
							$cachedObjects[$value] = QuarterMaster::release($value);
						}
					}

					if(sizeof($cachedObjects) == sizeof($values)){
						if($records){
							return $countResults?sizeof($cachedObjects):array_slice($cachedObjects,$start*$records,$records);
						}else{
							return $countResults?sizeof($cachedObjects):$cachedObjects;
						}
					}

					//Find Collection Root Field Type
					if($annotation->isCollection()){
						$listTables = array();
						while($annotation->isCollection()){
							if(!$listTables){
								$listTables[] = "$tableName".StringUtility::capitalise($property->getName());
							}else{
								$listTables[] = $listTables[sizeof($listTables)-1]."List";
							}

							$annotation = $annotation->getContainedType();
						}

						$field = "value";
					}

					//Convert conditions if necessary
					if($annotation->isString() && $condition == Criterion::EQUAL){
						$condition = Criterion::LIKE;
					}else if($annotation->isString() && $condition == Criterion::NOT_EQUAL){
						$condition = Criterion::NOT_LIKE;
					}

					//Convert parameters as necessary based on property type
					for($i=0; $i < sizeof($values); $i++){
						if($annotation->isFloat() || $annotation->isMonetary()){
							$digitParts = preg_split("/\./",strval($values[$i]));
							$decimals = strlen($digitParts[sizeof($digitParts)-1]);
							$field = "ROUND($field,$decimals)";
						}
					}

					//Create where condition based on property type
					if($property->getAnnotation()->isCollection()){
						if(!Criterion::isListComparator($condition)){
							Logger::warning(MessageUtility::DATA_ACCESS_ERROR, "SQLGateway cannot find $className objects using non-applicable comparator ".
									"'{$condition}'.");
							continue;
						}

						$listCondition = "";
						for($i=sizeof($listTables)-1; $i >= 0; $i--){
							$parentColumn = "parentKey";
							if($i == 0){
								$parentColumn = StringUtility::decapitalise($className);
							}

							if(sizeof($values) > 1){
								$equality = Criterion::INCLUDES;
								$placeholder = "(".implode(",",array_fill(0,sizeof($values),"?")). ")";
							}else{
								$placeholder = "?";

								if(!is_null($values[0])){
									$equality = Criterion::EQUAL;
								}else{
									$equality = Criterion::IS;
								}
							}

							if(!$listCondition){
								$listCondition = "SELECT $parentColumn FROM {$listTables[$i]} WHERE $field $equality $placeholder";
								$whereArguments = array_merge($whereArguments,$values);
							}else{
								$listCondition = "SELECT $parentColumn FROM {$listTables[$i]} WHERE id IN ($listCondition)";
							}
						}

						$where[] = "id {$condition} ($listCondition)";
					}else{
						if(($annotation->isObject() && !Criterion::isObjectComparator($condition))
							|| !Criterion::isPrimitiveComparator($condition)){
							Logger::warning(MessageUtility::DATA_ACCESS_ERROR, "SQLGateway cannot find $className objects using non-applicable comparator ".
									"'{$condition}'.");
							continue;
						}

						if(sizeof($values) > 1){
							$condition = Criterion::INCLUDES;
							$placeholder = "(".implode(",",array_fill(0,sizeof($values),"?")). ")";
						}else{
							$placeholder = "?";
						}

						$where[] = "$field {$condition} $placeholder";
						$whereArguments = array_merge($whereArguments,$values);
					}
				//If the search is for inclusion in another object we generate a simple query
				}else if(class_exists($criterion->getField())){
					$value = $criterion->viewFromValues(0);
					$table = $criterion->getField();

					$ownerClass = Mirror::reflectClass($criterion->getField());

					if(!$ownerClass->hasProperty($value)){
						Logger::warning(MessageUtility::DATA_ACCESS_ERROR, "SQLGateway cannot find $className objects using non-existant owner field ".
										"'{$value}'.");
						continue;
					}else if((string)((int)$condition) !== $condition && !Criterion::isListComparator($condition)){
						Logger::warning(MessageUtility::DATA_ACCESS_ERROR, "SQLGateway cannot find $className objects using non-applicable comparator ".
								"'{$condition}'.");
						continue;
					}else if(!$ownerClass->descendsFrom('Wadapi\Persistence\PersistentClass')){
						Logger::warning(MessageUtility::DATA_ACCESS_ERROR, "SQLGateway cannot find $className objects using non-persistent owner ".
								"'{$criterion->getField()}'.");
						continue;
					}

					//If the condition is an ID number we are looking for inclusion in a particular object
					if((string)((int)$condition) == $condition){
						$where[] = "id IN (SELECT value FROM $table".StringUtility::capitalise($value)." WHERE ".strtolower($table)." = '$condition')";
					//else we look for inclusion in ANY object
					}else{
						$property = Mirror::reflectProperty($criterion->getField(), $value);
						$annotation = $property->getAnnotation();

						if($annotation->isCollection()){
							$value = "value";
							$table = "";

							while($annotation->isCollection()){
								if(!$table){
									$table = $criterion->getField().StringUtility::capitalise($criterion->viewFromValues(0));
								}else{
									$table .= "List";
								}

								$annotation = $annotation->getContainedType();
							}
						}

						if(!$annotation->isObject()){
							continue;
						}

						$where[] = "id {$condition} (SELECT $value FROM $table)";
					}
				}else{
					Logger::warning(MessageUtility::DATA_ACCESS_ERROR, "SQLGateway cannot find $className objects fron non-existant field ".
									"'{$criterion->getField()}'.");
				}
			}

			//Create result order based on Sorter
			$sorts = array();
			$criteria = array();
			if($sorter->getCriteria()){
				$criteria = $sorter->getCriteria();
			}

			foreach($criteria as $criterion){
				$condition = $criterion->getCondition();

				if($condition != Criterion::RANDOM && !$class->hasProperty($criterion->getField())){
					Logger::warning(MessageUtility::DATA_ACCESS_ERROR, "SQLGateway cannot sort $className results using non-existant field ".
									"'{$criterion->getField()}'.");
					continue;
				}else if(!in_array($condition, array(Criterion::ASCENDING, Criterion::DESCENDING, Criterion::RANDOM))){
					Logger::warning(MessageUtility::DATA_ACCESS_ERROR, "SQLGateway cannot sort $className results according to invalid order ".
									"'{$condition}'.");
					continue;
				}

				if($condition == Criterion::RANDOM){
					$sorts[] = "$condition";
				}else{
					$sortProperty = Mirror::reflectProperty($className, $criterion->getField());
					if($sortProperty->getAnnotation()->isCollection()){
						Logger::warning(MessageUtility::DATA_ACCESS_ERROR, "SQLGateway cannot sort $className results using collection field ".
										"'{$criterion->getField()}'.");
						continue;
					}

					$sorts[] = "a.{$criterion->getField()} {$condition}";
				}
			}

			$order = "";
			if($sorts){
				$order = "ORDER BY ".implode(",",$sorts);
			}

			//Set result set limits according to parameters
			$limit = "";
			if($records){
				if(\Wadapi\Persistence\DatabaseAdministrator::isSQLServer()){
					$order = $order?$order:"ORDER BY a.id";
					$limit = "OFFSET ".($start+1)." ROWS FETCH NEXT $records ROWS ONLY";
				}else{
					$limit = "LIMIT $start,$records";
				}
			}

			//We want to search and select fields from the full class hierarchy
			$persistentClass = Mirror::reflectClass('Wadapi\Persistence\PersistentClass');
			$classHierarchy = array_diff($reflectedClass->getClassHierarchy(),$persistentClass->getClassHierarchy());

			$index = 97;
			$label = chr($index);
			$previousLabel = $label;
			$fromTable = "$tableName AS $label";
			foreach(array_reverse($classHierarchy) as $class){
				if($className != $class->getName()){
					$index++;
					$label = chr($index);
					$fromTable .= " JOIN {$class->getShortName()} AS $label ON $previousLabel.id = $label.id";
					$previousLabel = $label;
				}
			}

			$projection = ($countResults)?"COUNT(a.id) AS results":"*";
			$query = "SELECT $projection FROM $fromTable ".($where?preg_replace("/\s(id|created|modified)\s/"," a.$1 ","WHERE ".implode(" AND ",$where)):"")." $order $limit";

			//Replace placeholders with hard coded nulls, if necessary
			if(in_array(null,$whereArguments)){
				$positions = array();
				$pos = -1;
				while (($pos = strpos($query,"?", $pos+1)) !== false) {
					$positions[] = $pos;
				}
				$filteredWhereArguments = array();
				for($i=sizeof($whereArguments)-1; $i>=0; $i--){
					$whereArgument = $whereArguments[$i];
					if(is_null($whereArgument)){
						$query = substr_replace($query,"null",$positions[$i],1);
					}else{
						$filteredWhereArguments[] = $whereArgument;
					}
				}

				$whereArguments = array_reverse($filteredWhereArguments);
			}

			$results = call_user_func_array(array('Wadapi\Persistence\DatabaseAdministrator','execute'),array_merge(array($query),$whereArguments));
			$data = array();

			//If this is a count query return the count and don't load objects into memory
			if($countResults){
				return $results[0]["results"];
			}

			foreach($results as $result){
				$data[$result['id']] = $result;
			}

			$this->objectMembers = array();

			$modifiedStamps = array();
			foreach($data as $id => $objectData){
				//Ensure in-memory objects are not reloaded
				if(!QuarterMaster::isCached($id)){
					$objects[$id] = $this->loadObject($reflectedClass, $objectData);
					$modifiedStamps[$id] = $objects[$id]->getModified();
				}
			}

			//Load Child Objects and Collections
			if(!$lazyLoad && $objects){
				$subGateway = new SQLGateway();
				$searcher = new Searcher();
				foreach($reflectedClass->getProperties() as $property){
					$setter = "set".StringUtility::capitalise($property->getName());
					if($property->getAnnotation()->isObject()){
						$containedClass = $property->getAnnotation()->getObjectClass();
						$reflectedClass = Mirror::reflectClass($containedClass);

						if(!$reflectedClass->descendsFrom("Wadapi\Persistence\PersistentClass")){
							continue;
						}

						$ids = array();
						if(array_key_exists($property->getName(), $this->objectMembers)){
							$ids = array_keys($this->objectMembers[$property->getName()]);
						}

						$searcher->clearCriteria();
						$searcher->addCriterion('id',Criterion::EQUAL,$ids);

						foreach($subGateway->find($containedClass, $searcher,null,0,0,false) as $memberObject){
							foreach($this->objectMembers[$property->getName()][$memberObject->getId()] as $objectId){
								$objects[$objectId]->$setter($memberObject);
							}
						}
					}else if($property->getAnnotation()->isCollection()){
						$collections = $this->loadCollections($className.StringUtility::capitalise($property->getName()),
											$property->getAnnotation()->getContainedType(),
											array_keys($objects),strtolower($className));

						foreach($collections as $ownerID => $collection){
							$objects[$ownerID]->$setter($collection);

							//Take a snapshot of the object after collection loading
							Photographer::takeSnapshot($objects[$ownerID]);

							//Mark object as clean after collection loading
							$objects[$ownerID]->markClean();
						}
					}
				}
			}

			//Restore Object Modified Dates
			foreach($objects as $id => $objectData){
				$objects[$id]->setModified($modifiedStamps[$id]);
			}

			//Merge Cached and Newly Loaded Objects
			$mergedObjects = Array();
			foreach($data as $id => $objectData){
				$mergedObjects[$id] = QuarterMaster::isCached($id)?QuarterMaster::release($id):$objects[$id];
			}

			return $mergedObjects;
		}

		public function findUnique(){
			$arguments = func_get_args();

			//Update records parameter to 1 and call findUnique
			$arguments[0] = array_key_exists(0,$arguments)?$arguments[0]:"";
			$arguments[1] = array_key_exists(1,$arguments)?$arguments[1]:null;
			$arguments[2] = array_key_exists(2,$arguments)?$arguments[2]:null;
			$arguments[3] = 1;

			$results = call_user_func_array(array($this,'find'), $arguments);
			return $results?array_shift($results):null;
		}

		public function save($saveObjects){
			if(!is_array($saveObjects)){
				$saveObjects = array($saveObjects);
			}

			foreach($saveObjects as $saveObject){
				if(!is_object($saveObject) || !$this->checkUpdateParameters($saveObject)){
					Logger::fatal_error(MessageUtility::DATA_MODIFY_ERROR, "Only objects of PersistentClass can be saved via a Gateway");
					return;
				}

				//Save object data to table hierarchy
				$this->writeObject($saveObject);
			}
		}

		public function delete($deleteObjects,$owner = "", $field = ""){
			if(!is_array($deleteObjects)){
				$deleteObjects = array($deleteObjects);
			}

			$oldObjectMap = array();
			$oldPropertyMap = array();

			foreach($deleteObjects as $deleteObject){
				if(!is_object($deleteObject) || !$this->checkUpdateParameters($deleteObject)){
					Logger::fatal_error(MessageUtility::DATA_MODIFY_ERROR, "A SQLGateway can only delete PersistentClass Objects.");
					return;
				}

				$objectClass = Mirror::reflectClass($deleteObject);

				//Delete List Property Tables
				foreach($objectClass->getProperties(false) as $property){
					if($property->getAnnotation()->isCollection()){
						$listTable = $objectClass->getName().StringUtility::capitalise($property->getName());

						if(!array_key_exists($listTable,$oldPropertyMap)){
							$oldPropertyMap[$listTable] = array("field"=>strtolower($objectClass->getName()),"ids"=>array());
						}

						$oldPropertyMap[$listTable]["ids"][] = $deleteObject->getId();
					}
				}

				//Delete Object Class Tables
				$classHierarchy = array_diff($objectClass->getClassHierarchy(),
								Mirror::reflectClass("Wadapi\Persistence\PersistentClass")->getClassHierarchy());

				$classHierarchy = $classHierarchy?array_reverse($classHierarchy):[$objectClass];
				foreach($classHierarchy as $class){
					if(!array_key_exists($class->getName(),$oldObjectMap)){
						$oldObjectMap[$class->getShortName()] = array();
					}

					$oldObjectMap[$class->getShortName()][] = $deleteObject->getId();
				}
			}

			//Delete owner rows (if applicable)
			if($owner && $field){
				$ownerTable = $owner.StringUtility::capitalise($field);
				foreach($oldObjectMap as $class => $objects){
					DatabaseAdministrator::execute("DELETE FROM $ownerTable WHERE value IN (".implode(",",$objects).")");
				}
			}

			//Delete old object properties
			foreach($oldPropertyMap as $class => $properties){
				DatabaseAdministrator::execute("DELETE FROM $class WHERE {$properties['field']} IN (".implode(",",$properties['ids']).")");
			}

			//Delete old objects
			foreach($oldObjectMap as $class => $objects){
				DatabaseAdministrator::execute("DELETE FROM $class WHERE id IN (".implode(",",$objects).")");
			}
		}

		/*
		 * Performs the SQL Query to either insert or update an object to the database
		 */
		 public function writeObject($saveObject){
			//No need to save clean objects to the database, unless they have not yet been persisted
			if($saveObject->isPersisted() && !$saveObject->isDirty()){
				return;
			}

			$saveObjectClass = Mirror::reflectClass($saveObject);
			$classHierarchy = $saveObjectClass->getClassHierarchy();

			//Remove parents of PersistentClass from hierarchy
			$persistentClass = Mirror::reflectClass("Wadapi\Persistence\PersistentClass");
			$classHierarchy = array_diff($classHierarchy, $persistentClass->getClassHierarchy());

			//Write each class component of the saveObject's hierarchy to the corresponding database table
			foreach($classHierarchy as $class){
				$propertyList = $class->getProperties(false);
				$propertyValueMap = $this->buildPropertyValueMap($propertyList, $saveObject);

				if($propertyValueMap){
					$saveValues = array();
					$classProperties = implode(",",array_keys($propertyValueMap));

					//Setup Insert Part of Query
					$insertParameters = implode(",",array_fill(0,sizeof($propertyValueMap),"?"));
					$insertValues = array_merge(
								array($saveObject->getId(),$saveObject->getCreated(),$saveObject->getModified()),
								array_values($propertyValueMap)
							);

					//Setup Update Part of Query
					$updateParameters = array();
					$updateValues = array($saveObject->getModified());
					foreach($propertyValueMap as $property => $value){
						$updateParameters[] = "$property=?";
						$updateValues[] = $value;
					}

					//Build Save Query
					if($saveObject->isPersisted()){
						$saveQuery = "UPDATE {$class->getShortName()} SET modified=?,".implode(",",$updateParameters)." WHERE id = ?";
						$saveValues = array_merge($updateValues,array($saveObject->getId()));
					}else{
						$saveQuery = "INSERT INTO {$class->getShortName()}(id,created,modified,$classProperties) VALUES(?,?,?,$insertParameters)";
						$saveValues = array_merge($insertValues,$updateValues);
					}

					//Execute the save query and pass in the extracted parameters
					call_user_func_array(array("Wadapi\Persistence\DatabaseAdministrator","execute"), array_merge(array($saveQuery), $saveValues));
				//Write classes without properties only when they are not already persisted
				}else if(!$saveObject->isPersisted()){
					DatabaseAdministrator::execute("INSERT INTO {$class->getShortName()}(id,created,modified) VALUES(?,?,?)",
										$saveObject->getId(),$saveObject->getCreated(),$saveObject->getModified());
				//Update modification time for classes without properties that have already been persisted
				}else if($saveObject->isDirty()){
					DatabaseAdministrator::execute("UPDATE {$class->getShortName()} SET modified = ? WHERE id = ?",$saveObject->getModified(),$saveObject->getId());
				}

				//Save the object's collection properties
				foreach($propertyList as $property){
					if($property->getAnnotation()->isCollection() && $saveObject->isDirty($property->getName())){
						$getterName = "get".StringUtility::capitalise($property->getName());
						$this->writeList($saveObject, $property, $saveObject->$getterName());
					}
				}
			}

			//Having been written to the database this object is now clean and should be marked as persisted
			$saveObject->markClean();
			$saveObject->markPersisted();
		}

		/*
		 * Given an object and a specified list property, saves the value of the property to the database
		 */
		private function writeList($saveObject, $saveProperty, $propertyValue, $listDepth = 0, $parentKey = 0){
			//No need to save clean collections to the database
			if(!$saveObject->isDirty($saveProperty->getName())){
				return;
			}

			if(!$propertyValue){
				$propertyValue = array();
			}

			$listAnnotation = $saveProperty->getAnnotation()->getContainedType();
			$saveObjectClass = get_class($saveObject);
			$propertyName = $saveProperty->getName();
			$tableName = $saveObjectClass.StringUtility::capitalise($propertyName);
			$parentColumn = strtolower($saveObjectClass);

			//Load properties based on list depth
			$snapshot = Photographer::getSnapshot($saveObject,$saveProperty->getName());
			for($i = 0; $i < $listDepth; $i++){
				$tableName .= "List";
				$listAnnotation = $listAnnotation->getContainedType();
				$snapshot = is_null($snapshot)?$snapshot:(array_key_exists($parentKey,$snapshot)?$snapshot[$parentKey]:null);
			}

			//Remove unused keys from the persisted list (will not apply to newly created objects)
			$storedElementDeleteQuery = "DELETE FROM $tableName WHERE $parentColumn = '{$saveObject->getId()}'";
			if($listDepth > 0){
				$storedElementDeleteQuery .= " AND parentKey = '$parentKey'";
			}

			if(!is_null($snapshot)){
				$staleKeys = array_diff(array_keys($snapshot),array_keys($propertyValue));
				foreach($staleKeys as $index => $staleKey){
					$staleKeys[$index] = strval($staleKey);

					//Remove stale nested entries if necessary
					if($listAnnotation->isCollection()){
						$this->writeList($saveObject, $saveProperty, array(), $listDepth+1, $staleKey);
					}
				}

				if($staleKeys){
					$storedElementDeleteQuery .= " AND name IN (".implode(",",array_fill(0,sizeof($staleKeys),"?")).")";
					call_user_func_array(array("Wadapi\Persistence\DatabaseAdministrator","execute"),array_merge(array($storedElementDeleteQuery),$staleKeys));
				}
			}else{
				DatabaseAdministrator::execute($storedElementDeleteQuery);
			}

			//Add and update elements to persisted list
			$fields = array(strtolower(get_class($saveObject)),'name');
			if(!$listAnnotation->isCollection()){
				$fields[] = 'value';
			}

			if($listDepth > 0){
				$fields[] = 'parentKey';
			}

			foreach($propertyValue as $key => $element){
				//Write any dirty object list elemenets to the database
				if($listAnnotation->isObject() && $element->isDirty()){
					$this->writeObject($element);
				}

				//Do not write list elements that have not changed
				if($snapshot && array_key_exists($key,$snapshot) && $snapshot[$key] == $element){
					continue;
				}

				$placeHolders = array();
				$values = [$saveObject->getId(),strval($key)];

				$placeHolderLength = ($listDepth == 0)?3:4;
				if($listAnnotation->isObject()){
					$values[] = $element->getId();
				}else if($listAnnotation->isCollection()){
					$placeHolderLength--;
					$this->writeList($saveObject, $saveProperty, $element, $listDepth+1, $key);
				}else{
					$values[] = $element;
				}

				$placeHolders[] = "(".implode(",",array_fill(0,$placeHolderLength,"?")).")";

				if($listDepth > 0){
					$values[] = $parentKey;
				}

				if($placeHolders){
					//Check if key entry already exists
					$countWhere = [];
					for($i=0; $i < sizeof($fields); $i++){
						if($fields[$i] !== 'value'){
							$countWhere[] = "{$fields[$i]} = '{$values[$i]}'";
						}
					}

					$present = DatabaseAdministrator::execute("SELECT COUNT(name) AS present FROM $tableName WHERE ".implode(" AND ",$countWhere))[0]["present"];

					if(!$present){
						$writeListQuery = "INSERT INTO $tableName(".implode(",",$fields).") VALUES ".implode(",",$placeHolders);
						call_user_func_array(array("Wadapi\Persistence\DatabaseAdministrator","execute"),array_merge(array($writeListQuery),$values));
					}else{
						$writeListQuery = "UPDATE $tableName SET name = ?";

						if(!$listAnnotation->isCollection()){
							$writeListQuery .= ", value = ?";
						}

						$writeListQuery .= " WHERE {$fields[0]} = ? AND name = ?";
						if($listDepth > 0){
							$writeListQuery .= " AND parentKey = ?";
						}

						$arguments = [$values[1]];
						if(!$listAnnotation->isCollection()){
							$arguments[] = $values[sizeof($fields)-2];
						}

						$arguments[] = $values[0];
						$arguments[] = $values[1];
						if($listDepth > 0){
							$arguments[] = $values[sizeof($fields)-1];
						}

						call_user_func_array(array("Wadapi\Persistence\DatabaseAdministrator","execute"),array_merge(array($writeListQuery),$arguments));
					}
				}
			}
		}

		/*
		 * Returns a map of object properties to their values in the object
		 */
		private function buildPropertyValueMap($propertyList, $object){
			$propertyValueMap = array();
			foreach($propertyList as $property){
				$propertyName = $property->getName();
				$getterName = "get".StringUtility::capitalise($propertyName);

				//Skip Array Members and Unchanged Values
				$isArrayMember = $property->getAnnotation()->isCollection();
				$isUnchanged = !$object->isDirty($propertyName);
				if($isArrayMember || $isUnchanged){
					continue;
				}

				//Skip Unspecified Required Values
				$propertyValue = $object->$getterName();
				$requiredNotSpecified = is_null($propertyValue) && $property->getAnnotation()->isRequired();
				if($requiredNotSpecified){
					continue;
				}

				if(is_null($propertyValue)){
					$propertyValueMap[$propertyName] = null;
				}else{
					if($property->getAnnotation()->isObject()){
						$this->writeObject($propertyValue);

						//Only rewrite object reference if it has changed
						if(!Photographer::compareToSnapshot($object,$propertyName)){
							$propertyValueMap[$propertyName] = $propertyValue->getId();
						}
					}else if($property->getAnnotation()->isString()){
						$propertyValueMap[$propertyName] = (string)$propertyValue;
					}else{
						$propertyValueMap[$propertyName] = $propertyValue;
					}
				}
			}

			return $propertyValueMap;
		}

		/*
		 * Given raw data construct PersistentClass object
		 */
		 private function loadObject($class, $data){
			Detective::investigate($class->getName(),false);

			if(array_key_exists('id', $data) && QuarterMaster::release($data['id']) &&
				get_class(QuarterMaster::release($data['id'])) == $class->getName()){
					Detective::closeCase($class->getName(),false);
					return QuarterMaster::release($data['id']);
			}

			$arguments = array();
			foreach(array_diff($class->getProperties(), Mirror::reflectClass('Wadapi\Persistence\PersistentClass')->getProperties()) as $property){
				$field = $property->getName();

				if(!array_key_exists($field, $data)){
					$data[$field] = null;
				}

				if($property->getAnnotation()->isCollection()){
					$arguments[] = null;
				}else if($property->getAnnotation()->isObject()){
					//Make note of contained object for delayed loading
					if(!array_key_exists($property->getName(), $this->objectMembers)){
						$this->objectMembers[$property->getName()] = array();
					}

					if($data[$field]){
						if(!array_key_exists($data[$field],$this->objectMembers[$property->getName()])){
							$this->objectMembers[$property->getName()][$data[$field]] = array();
						}

						$this->objectMembers[$property->getName()][$data[$field]][] = $data['id'];

						//Set object stub for lazy loading
						$containedClass = $property->getAnnotation()->getObjectClass();
						$arguments[] = call_user_func_array(array($containedClass,"bindInstance"),array($data[$field],new SQLGateway()));
					}else{
						$arguments[] = null;
					}
				}else if($property->getAnnotation()->isBoolean()){
					$arguments[] = (bool)$data[$field];
				}else if($property->getAnnotation()->isString()){
					$arguments[] = strval($data[$field]);
				}else{
					$arguments[] = $data[$field];
				}
			}

			$object = $class->newInstanceArgs($arguments);
			$object->setId($data['id']);
			$object->setCreated($data['created']);
			$object->setModified($data['modified']);

			//Take a snapshot of the object as it was loaded
			Photographer::takeSnapshot($object);

			//Loaded Objects always start as Clean
			$object->markClean();

			//Loaded Objects are marked as persisted by default
			$object->markPersisted();

			//Cache object before returning it
			QuarterMaster::store($object);

			Detective::closeCase($class->getName(),false);
			return $object;
		 }

		 /*
		  * Delayed loading of object collections to ensure a single query to each table
		  */
		 private function loadCollections($listTable, $listAnnotation, $parentIds, $parentColumn = "parentKey", $rootColumn="", $rootId=0){
			$fields = "$parentColumn,name";
			if(!$listAnnotation->isCollection()){
				$fields .= ",value";
			}

			$findQuery = "SELECT $fields FROM $listTable WHERE $parentColumn IN ('".implode("','",$parentIds)."')";
			if($rootColumn){
				$findQuery .= " AND $rootColumn = $rootId";
			}

			$results = DatabaseAdministrator::execute($findQuery);

			if($listAnnotation->isObject()){
				$subGateway = new SQLGateway();
				$values = array();

				foreach($results as $result){
					$values[] = $result['value'];
				}

				$searcher = new Searcher();
				$searcher->addCriterion('id',Criterion::EQUAL,$values);
				$objects = $subGateway->find($listAnnotation->getObjectClass(), $searcher);
			}else if($listAnnotation->isCollection()){
				$keys = array();

				foreach($results as $result){
					$keys[] = $result['name'];
				}

				$rootColumn = $rootColumn?$rootColumn:$parentColumn;
				$rootId = $rootId?$rootId:$parentIds[0];
				$nestedCollections = $this->loadCollections($listTable."List", $listAnnotation->getContainedType(), $keys, "parentKey", $rootColumn, $rootId);
			}

			$collections = array();
			foreach($parentIds as $parentId){
				$collections[$parentId] = array();
			}

			foreach($results as $result){
				if($listAnnotation->isObject()){
					if($result['value']){
						$collections[$result[$parentColumn]][$result['name']] = $objects[$result['value']];
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
					$value = $result['value'];
					if($listAnnotation->isBoolean()){
						$value = boolval($value);
					}

					$collections[$result[$parentColumn]][$result['name']] = $value;
				}
			}

			return $collections;
		 }
	}
?>
