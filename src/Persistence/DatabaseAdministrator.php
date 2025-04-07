<?php
	namespace Wadapi\Persistence;

	use Wadapi\System\Worker;
	use Wadapi\System\SettingsManager;

	class DatabaseAdministrator extends Worker{
		/*
		 * The active database connection
		 */
		private static $activeConnection;

		/*
		 * System table prefix loaded once from configuration
		 */
		private static $tablePrefix = null;

		/*
		 * System index hits loaded once from configuration
		 */
		private static $indexHits = null;

		/*
 		 * Stores the tables known to exist so they don't have to be looked up more than once
 		 */
 		private static $knownTables = array();


		/*
		 * Sends the provided statement to the connected server
		 */
		public static function execute(){
			$arguments = func_get_args();
			$statement = $arguments[0];

			if(!self::checkConnection()){
				return;
			}

			$expectedParameterCount = substr_count($statement, "?");
			$statementParameters = array_slice($arguments, 1, $expectedParameterCount);
			$statementParameterCount = sizeof($statementParameters);

			for($i = $statementParameterCount; $i < $expectedParameterCount; $i++){
				$statementParameters[] = "?";
			}

			$statement = self::addPrefixes($statement);
			$sqlStatementString = new SQLString($statement);

			//Execute parameterised statement
			$executeParameters = array_merge(array($statement), $statementParameters);
			$result = call_user_func_array(array(self::$activeConnection, "execute"), $executeParameters);

			if($sqlStatementString->getType() == SQLString::CREATE_STRING){
				self::$knownTables = array_merge(array_keys($sqlStatementString->getTables()),self::$knownTables);
			}else if($sqlStatementString->getType() == SQLString::DROP_STRING){
				self::$knownTables = array_diff(self::$knownTables,array_keys($sqlStatementString->getTables()));
			}else if($sqlStatementString->getType() == SQLString::RENAME_STRING){
				$tables = array_keys($sqlStatementString->getTables());
				$from = $tables[0]; $to = $tables[1];
				self::$knownTables = array_merge(array_diff(self::$knownTables, array($from)), array($to));
			}

			return $result;
		}

		/*
		 * Returns true if a table with the given name already exists. False otherwise.
		 */
		public static function tableExists($tableName){
			$tablePrefix = self::getTablePrefix();

			if(!self::$knownTables){
				if(!self::checkConnection()){
					return;
				}

				$results = self::$activeConnection->listTables();
				foreach($results as $result){
					self::$knownTables[] = array_shift($result);
				}
			}

			$prefix = self::getTablePrefix();
			return in_array("$prefix$tableName", self::$knownTables);
		}

		/*
		 * Returns the columns of the given table if it exists
		 */
		public static function describe($tableName){
			if(self::checkConnection()){
				return self::$activeConnection->describe(self::getTablePrefix()."$tableName");
			}
		}

		/*
		 * Says whether a table allows cascading keys or not
		 */
		public static function isSQLServer(){
			if(self::checkConnection()){
				return self::$activeConnection->getDriver() == "sqlsrv";
			}
		}

		/*
		 * Returns the ID of the last database insert
		 */
		public static function getLastInsertId(){
			$insertIdResultSet = self::execute("SELECT LAST_INSERT_ID() as insert_id");
			return $insertIdResultSet[0]['insert_id'];
		}

		/*
		 * Commits active transaction to DB
		 */
		public static function commit(){
			if(self::$activeConnection){
				self::$activeConnection->commit();
			}
		}

		/*
		 * Rollsback active transaction to DB
		 */
		public static function rollback(){
			if(self::$activeConnection){
				self::$activeConnection->rollback();
			}
		}

		//Builds a connection using the configured connection parameters
		protected static function connect(){
			self::buildConnection(
				SettingsManager::getSetting('database','driver'),
				SettingsManager::getSetting('database','hostname'),
				SettingsManager::getSetting('database','username'),
				SettingsManager::getSetting('database','password'),
				SettingsManager::getSetting('database','database'),
				SettingsManager::getSetting('database','port')
			);
		}

		//Builds the connection using the passed connection parameters
		protected static function buildConnection($driver, $hostname, $username, $password, $database, $port=3306, $tablePrefix=null){
			if(self::$activeConnection){
				self::$activeConnection->close();
			}

			$databaseConnection = new SQLConnection($driver, $hostname, $username, $password, $database, $port);
			$databaseConnection->connect();
			self::$activeConnection = $databaseConnection;
			self::$knownTables = array();
			self::$tablePrefix = $tablePrefix?$tablePrefix."_":$tablePrefix;
		}

		/*
		 * Tells the administrator to use the system wide DB connection if none has been specified
		 */
		private static function checkConnection(){
			if(!self::$activeConnection || self::$activeConnection->isClosed()){
				self::connect();
			}

			return self::$activeConnection;
		}

		/*
		 * Adds the system specifed table prefix, before all table names in statement
		 */
		private static function addPrefixes($statement){
			$sqlStatementString = new SQLString($statement);
			$statementTables = $sqlStatementString->getTables();

			//Add table prefix only if one exists
			$tablePrefix = self::getTablePrefix();
			if($tablePrefix){
				foreach($statementTables as $table => $alias){
					$statement = preg_replace("/([,\s])$table([,\s;\)\(]|$)/", "$1$tablePrefix$table$2", $statement);
				}
			}

			return $statement;
		}

		/*
		 * Return the table prefix to inset into queries
		 */
		private static function getTablePrefix(){
			if(is_null(self::$tablePrefix)){
				if(SettingsManager::getSetting('database','prefix')){
					self::$tablePrefix = SettingsManager::getSetting('database','prefix')."_";
				}else{
					self::$tablePrefix = "";
				}
			}

			return self::$tablePrefix;
		}
	}
?>
