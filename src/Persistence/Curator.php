<?php
	namespace Wadapi\Persistence;

	include_once 'Grave.php';
	include_once 'Warrant.php';
	include_once dirname(__FILE__)."/../Http/Resource.php";
	include_once dirname(__FILE__)."/../Authentication/APIToken.php";
	include_once dirname(__FILE__)."/../Authentication/TokenProfile.php";

	use Wadapi\System\Worker;
	use Wadapi\System\SettingsManager;
	use Wadapi\Reflection\Mirror;
	use Wadapi\Utility\StringUtility;

	class Curator extends Worker{
		//Keep track of classes processed this session so we don't revisit them
		private static $classesSeen = array();

		//An array of all list properties seen for table creation
		private static $listProperties = array();

		/*
		 * Rebuilds the database based on the current PersistentClass definitions in the system
		 */
		protected static function rebuildDatabase(){
			$persistentClasses = array();

			foreach(get_declared_classes() as $declaredClass){
				$class = Mirror::reflectClass($declaredClass);
				if($class->descendsFrom("Wadapi\Persistence\PersistentClass") && $declaredClass !== "Wadapi\Persistence\PersistentClass"){
					//Manage table hierarchy for object class
					self::manageTableForClass($class);

					while(sizeof(self::$listProperties) > 0){
						$listProperty = array_shift(self::$listProperties);
						self::manageTableForList($listProperty);
					}

					//Re-initialise seen class hierarchy for this object
					self::$classesSeen = array();
					self::$listProperties = array();
				}
			}
		}

		/*
		 * Ensures that a table representation of an object already exists, and decides how to proceed.
		 */
		private static function manageTableForClass($class){
			if(!in_array($class->getName(), self::$classesSeen)){
				self::$classesSeen[] = $class->getName();
				if(DatabaseAdministrator::tableExists($class->getShortName())){
					//Add any new class fields, or change field types for existing table
					self::alterTableForClass($class);
				}else{
					self::createTableForClass($class);
				}
			}
		}

		/*
		 * Checks whether a table representation of the list already exists, and decides how to proceed based on the result.
		 */
		private static function manageTableForList($property){
			$class = $property->getDeclaringClass();

			if(!in_array($class->getName().StringUtility::capitalise($property->getName()), self::$classesSeen)){
				self::$classesSeen[] = $class->getName().StringUtility::capitalise($property->getName());
				if(DatabaseAdministrator::tableExists($class->getShortName().StringUtility::capitalise($property->getName()))){
					self::alterTableForList($property);
				}else{
					self::createTableForList($property);
				}
			}
		}

		/*
		 * Creates a database table corresponding to a certain PersistentClass if it does not already exist
		 */
		private static function createTableForClass($class){
			//Every class will need to include an ID field to associate with its parent
			$classProperties = array(Mirror::reflectProperty($class->getName(), "id"),Mirror::reflectProperty($class->getName(), "created"),Mirror::reflectProperty($class->getName(), "modified"));
			$classProperties = array_merge($classProperties, $class->getProperties(false));

			//Check that necessary parent table exist. Create them if not
			$classHierarchy = $class->getParentClass()->getClassHierarchy();
			$persistentClass = Mirror::reflectClass("Wadapi\Persistence\PersistentClass");
			$classHierarchy = array_diff($classHierarchy, $persistentClass->getClassHierarchy());

			foreach($classHierarchy as $nextClass){
				self::manageTableForClass($nextClass);
			}

			$createStatement = "CREATE TABLE {$class->getShortName()}(";
			$idType = "";

			foreach($classProperties as $property){
				if($property->getAnnotation()->isCollection()){
					continue;
				}

				$columnType = "";
				$propertyName = $property->getName();
				$columnType = self::getColumnType($property->getAnnotation());

				//Create object field table
				if($property->getAnnotation()->isObject()){
					self::manageTableForClass(Mirror::reflectClass($property->getAnnotation()->getObjectClass()));
				}

				if($property->getName() == "id"){
					$idType = $columnType;
					$columnType .= " PRIMARY KEY";
				}

				$createStatement .= "{$property->getName()} $columnType,";
			}

			//If class is not hierarchy root, add foreign key to parent object
			if($class->getParentClass()->getName() != "Wadapi\Persistence\PersistentClass"){
				$parentClassName = $class->getParentClass()->getShortName();
				$constraintName = "fk_".SettingsManager::getSetting("database","prefix")."_".substr(strtolower($class->getShortName()), 0, 25)."_id";
				$createStatement .= "CONSTRAINT $constraintName FOREIGN KEY (id) REFERENCES $parentClassName (id)";
				if(!DatabaseAdministrator::isSQLServer()){
					$createStatement .= " ON DELETE CASCADE ON UPDATE CASCADE";
				}

				$createStatement .= ",";
			}

			//Add foreign keys for object references
			foreach($classProperties as $property){
				if($property->getAnnotation()->isObject()){
					$propertyName = $property->getName();
					preg_match("/(?:\\\\)?(\w+)$/",$property->getAnnotation()->getObjectClass(),$objectClassMatch);
					$objectTable = $objectClassMatch[1];
					$constraintName = "fk_".SettingsManager::getSetting("database","prefix")."_".substr(strtolower($class->getShortName()),0,25).substr("_$propertyName",0,20);
					$createStatement .= "CONSTRAINT $constraintName FOREIGN KEY ($propertyName) REFERENCES $objectTable (id)";

					if(!DatabaseAdministrator::isSQLServer()){
						$createStatement .= " ON DELETE SET NULL ON UPDATE CASCADE";
					}

					$createStatement .= ",";
				}
			}

			//Remove trailing comma from $createStatement
			$createStatement = substr($createStatement, 0, strlen($createStatement) - 1);
			$createStatement .= ")ENGINE=InnoDB DEFAULT CHARSET=latin1";
			DatabaseAdministrator::execute($createStatement);

			//Mark list property for later table creation
			foreach($classProperties as $property){
				if($property->getAnnotation()->isCollection()){
					self::$listProperties[] = $property;
				}
			}
		}

		/*
		 * Creates a database table corresponding to stored object lists when a class has a one-to-many or many-to-many relationship
		 */
		private static function createTableForList($property){
			$propertyName = $property->getName();
			$className = $property->getDeclaringClass()->getShortName();
			$annotation = $property->getAnnotation();
			$listAnnotations = array();

			//Get a listing of all the nested types of the Collection property
			while($annotation->isCollection()){
				$annotation = $annotation->getContainedType();
				$listAnnotations[] = $annotation;
			}

			//Traverse type list to create tables for each nested list
			for($i = 0; $i < sizeof($listAnnotations); $i++){
				$nextAnnotation = $listAnnotations[$i];
				$tableName = $className.StringUtility::capitalise($propertyName).str_repeat("List", $i);
				if(DatabaseAdministrator::tableExists($tableName)){
					continue;
				}

				$createStatement = "CREATE TABLE $tableName(".strtolower($className)." VARCHAR(20) NOT NULL,name VARCHAR(128)";

				if($i > 0){
					$createStatement .= ",parentKey VARCHAR(128) NOT NULL";
				}

				if($nextAnnotation->isObject()){
					$objectClass = Mirror::reflectClass($nextAnnotation->getObjectClass());
					self::manageTableForClass($objectClass);

					$constraintName = "fk_".SettingsManager::getSetting("database","prefix")."_".substr(strtolower($tableName),0,25)."_value";
					$createStatement .= ",value VARCHAR(20),CONSTRAINT $constraintName ".
								"FOREIGN KEY (value) REFERENCES {$objectClass->getShortName()} (id) ";

					if(!DatabaseAdministrator::isSQLServer()){
						$createStatement .= "ON UPDATE CASCADE ON DELETE CASCADE";
					}
				}else if(!$nextAnnotation->isCollection()){
					$columnType = self::getColumnType($nextAnnotation);
					$createStatement .= ",value $columnType";
				}

				//Specify Primary Key Constraint
				$keyFields = array();
				$keyFields[] = strtolower($className);
				if($i > 0){
					$keyFields[] = "parentKey";
				}
				$keyFields[] = "name";
				$createStatement .= ",PRIMARY KEY(".implode(",",$keyFields).")";

				//Specify owning object column constraint
				$tableAbbr = substr(strtolower($tableName), 0, 15).substr(strtolower($tableName), strlen($tableName)-10,strlen($tableName));
				$constraintName = "fk_".SettingsManager::getSetting("database","prefix")."_".$tableAbbr."_".substr(strtolower($className),0,20);
				$createStatement .= ",CONSTRAINT $constraintName FOREIGN KEY (".strtolower($className).") REFERENCES $className (id)";
				if(!DatabaseAdministrator::isSQLServer()){
						$createStatement .= " ON UPDATE CASCADE ON DELETE CASCADE";
				}
				$createStatement .= ")ENGINE=InnoDB DEFAULT CHARSET=latin1";

				DatabaseAdministrator::execute($createStatement);
			}
		}

		/*
		 * Change a database table to reflect new class definitions.
		 */
		private static function alterTableForClass($class){
			//Get table description
			$tableName = $class->getShortName();
			$tableDescription = DatabaseAdministrator::describe($tableName);
			$classProperties = $class->getProperties(false);
			$tableFieldTypeMap = array();
			$tableForeignKeyMap = array();

			//Check that necessary parent tables exist. Create them if not
			$classHierarchy = $class->getParentClass()->getClassHierarchy();
			$persistentClass = Mirror::reflectClass("Wadapi\Persistence\PersistentClass");
			$classHierarchy = array_diff($classHierarchy, $persistentClass->getClassHierarchy());

			foreach($classHierarchy as $nextClass){
				self::manageTableForClass($nextClass);
			}

			foreach($tableDescription as $column){
				$tableFieldTypeMap[$column['Field']] = $column['Type'];
				$tableForeignKeyMap[$column['Field']] = $column['Key'];
			}

			foreach($classProperties as $property){
				$changed = true;
				$propertyName = $property->getName();

				if(in_array($property->getName(), array_keys($tableFieldTypeMap))){
					$oldColumnType = $tableFieldTypeMap[$property->getName()];
					$oldColumnKey = $tableForeignKeyMap[$property->getName()];
				}else{
					$oldColumnType = "";
					$oldColumnKey = "";
				}

				$newColumnType = self::getColumnType($property->getAnnotation());

				//Create object field table
				if($property->getAnnotation()->isObject()){
					self::manageTableForClass(Mirror::reflectClass($property->getAnnotation()->getObjectClass()));
				}

				//Add any missing columns to table
				if(!$oldColumnType && !$property->getAnnotation()->isCollection()){
					$oldColumnType = $newColumnType;
					DatabaseAdministrator::execute("ALTER TABLE $tableName ADD $propertyName $newColumnType");

				//Alter table if a field type has changed
				}else if(($property->getAnnotation()->isObject() && (!preg_match("/^varchar/", $oldColumnType))) ||
					($property->getAnnotation()->isInteger() && (!preg_match("/^int/", $oldColumnType))) ||
					(($property->getAnnotation()->isFloat() || $property->getAnnotation()->isMonetary())
						&& $oldColumnType != "float") ||
					($property->getAnnotation()->isBoolean() && (!preg_match("/^bit/", $oldColumnType))) ||
					($property->getAnnotation()->isText() && !preg_match("/^(text|varchar)/", $oldColumnType)) ||
					($property->getAnnotation()->isString() && !$property->getAnnotation()->isText() && !preg_match("/^varchar/", $oldColumnType))){
					//Set all values in changed column to NULL to ensure compatability with new type
					DatabaseAdministrator::execute("UPDATE $tableName SET $propertyName=NULL");

					//If column was previously object and no longer is, drop foreign key
					if(!$property->getAnnotation()->isObject()){
						$columnKey = "fk_".SettingsManager::getSetting("database","prefix")."_".substr(strtolower($tableName),0,25).substr("_$propertyName",0,20);
						DatabaseAdministrator::execute("ALTER TABLE $tableName DROP FOREIGN KEY $columnKey");
						DatabaseAdministrator::execute("ALTER TABLE $tableName DROP KEY $columnKey");
					}

					DatabaseAdministrator::execute("ALTER TABLE $tableName CHANGE $propertyName $propertyName $newColumnType");

				//Otherwise table has not changed
				}else{
					$changed = false;
				}

				//If column has changed to object add foreign key
				if($changed && $property->getAnnotation()->isObject()){
					$columnKey = "fk_".SettingsManager::getSetting("database","prefix")."_".substr(strtolower($tableName),0,25).substr("_$propertyName",0,20);
					$objectTable = $property->getAnnotation()->getObjectClass();

					$alterStatement = "ALTER TABLE $tableName ADD CONSTRAINT $columnKey FOREIGN KEY ($propertyName) REFERENCES $objectTable (id)";
					if(!DatabaseAdministrator::isSQLServer()){
						$alterStatement .= " ON DELETE SET NULL ON UPDATE CASCADE";
					}

					DatabaseAdministrator::execute($alterStatement);
				}
			}

			//Mark list property for later table creation
			foreach($classProperties as $property){
				if($property->getAnnotation()->isCollection()){
					self::$listProperties[] = $property;
				}
			}
		}

		/*
		 * Change a list database table to reflect new class definitions.
		 */
		private static function alterTableForList($property){
			$propertyName = $property->getName();
			$className = $property->getDeclaringClass()->getName();
			$annotation = $property->getAnnotation();
			$listAnnotations = array();

			//Get a listing of all the nested types of the Collection property
			while($annotation->isCollection()){
				$annotation = $annotation->getContainedType();
				$listAnnotations[] = $annotation;
			}

			//Traverse type list in order to alter tables for each nested list
			for($i = 0; $i < sizeof($listAnnotations); $i++){
				$nextAnnotation = $listAnnotations[$i];
				$tableName = $className.StringUtility::capitalise($propertyName).str_repeat("List", $i);
				$columnName = $propertyName;

				if(!DatabaseAdministrator::tableExists($tableName)){
					self::createTableForList($property);
				}else{
					$tableDescription = DatabaseAdministrator::describe("$tableName");
					$dataIndex = sizeof($tableDescription) - 1;

					$currentColumns = array();
					foreach($tableDescription as $row){
						$currentColumns[] = $row['Field'];
					}

					$storedPropertyName = $tableDescription[$dataIndex]['Field'];
					$storedAnnotationType = $tableDescription[$dataIndex]['Type'];
					$storedKey = $tableDescription[$dataIndex]['Key'];

					$newColumnType = self::getColumnType($nextAnnotation);
					if($nextAnnotation->isObject()){
						self::manageTableForClass(Mirror::reflectClass($nextAnnotation->getObjectClass()));
					}

					$typeChange = false;
					if(($nextAnnotation->isObject() && (!preg_match("/^varchar/", $storedAnnotationType)))||
					   ($nextAnnotation->isInteger() && (!preg_match("/^int/", $storedAnnotationType)))||
					   (($nextAnnotation->isFloat() || $nextAnnotation->isMonetary()) && $storedAnnotationType != "float") ||
					   ($nextAnnotation->isBoolean() && !preg_match("/^bit/", $storedAnnotationType)) ||
					   ($nextAnnotation->isText() && !preg_match("/^text/", $storedAnnotationType)) ||
					   ($nextAnnotation->isString() && !$property->getAnnotation()->isText() && !preg_match("/^varchar/", $storedAnnotationType))){
						$typeChange = true;
					}

					//If property recently changed from collection add element column
					if(!$nextAnnotation->isCollection() && !in_array("value",$currentColumns)){
						DatabaseAdministrator::execute("ALTER TABLE $tableName ADD COLUMN value $newColumnType DEFAULT NULL");
					//If list type has become collection, drop element column
					}else if($nextAnnotation->isCollection() && in_array("value",$currentColumns)){
						DatabaseAdministrator::execute("ALTER TABLE $tableName DROP COLUMN value");
					}


					$tableAbbr = substr(strtolower($tableName),0,15).substr(strtolower($tableName),strlen($tableName)-10,strlen($tableName));
					if($typeChange){
						//Set all values of old column to NULL to ensure compatability with new type
						DatabaseAdministrator::execute("UPDATE $tableName SET value=NULL");

						//If there is a key on the changed column drop it
						if($storedKey == "MUL"){
							$columnKey = "fk_".SettingsManager::getSetting("database","prefix")."_".$tableAbbr."_value";
							DatabaseAdministrator::execute("ALTER TABLE $tableName DROP FOREIGN KEY $columnKey");
							DatabaseAdministrator::execute("ALTER TABLE $tableName DROP KEY $columnKey");
						}

						if(in_array("value",$currentColumns)){
							DatabaseAdministrator::execute("ALTER TABLE $tableName CHANGE value value $newColumnType");
						}

						//If new column is object or collection add Foreign Key to class table
						if($nextAnnotation->isObject()){
							$columnKey = "fk_".SettingsManager::getSetting("database","prefix")."_{$tableAbbr}_value";
							if($nextAnnotation->isObject()){;
								self::manageTableForClass(Mirror::reflectClass($nextAnnotation->getObjectClass()));
								$foreignKeyTable = $nextAnnotation->getObjectClass();
							}

							$alterStatement = "ALTER TABLE $tableName ADD CONSTRAINT $columnKey FOREIGN KEY(value) REFERENCES $foreignKeyTable(id)";
							if(!DatabaseAdministrator::isSQLServer()){
								$alterStatement .= " ON DELETE CASCADE ON UPDATE CASCADE";
							}

							DatabaseAdministrator::execute($alterStatement);
						}
					}
				}
			}
		}

		/*
		 * Determine the MySQL column type for each possible WadapiClass property type
		 */
		private static function getColumnType($annotation){
			if($annotation->isInteger()){
				$columnType = "INT";
			}else if($annotation->isFloat() || $annotation->isMonetary()){
				$columnType = "FLOAT";
			}else if($annotation->isBoolean()){
				$columnType = "BIT";
			}else if($annotation->isObject()){
				$columnType = "VARCHAR(20)";
			}else if($annotation->isText()){
				$columnType = DatabaseAdministrator::isSQLServer()?"VARCHAR(MAX)":"TEXT";
			}else if($annotation->isString()){
				$limit = 256;
				if($annotation->getMax()){
					$limit = $annotation->getMax();
				}

				$columnType = "VARCHAR($limit)";
			}else if($annotation->isCollection()){
				$columnType = "";
			}

			return $columnType;
		}
	}
?>
