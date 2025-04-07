<?php
	namespace Wadapi\Persistence;

	use Wadapi\System\Detective;
	use Wadapi\Utility\MessageUtility;
	use Wadapi\Logging\Logger;

	class SQLConnection extends DatabaseConnection{
		/*
		 * Database Driver
		 */
		/** @WadapiString(required=true, default='mysql') */
		protected $driver;

		/*
		 * Database Host
		 */
		/** @WadapiString(required=true, default='localhost') */
		protected $hostname;

		/*
		 * Database User
		 */
		/** @WadapiString(required=true, default='root') */
		protected $username;

		/*
		 * Database Password
		 */
		/** @WadapiString(required=true) */
		protected $password;

		/*
		 * Database Name
		 */
		/** @WadapiString(required=true) */
		protected $database;

		/*
		 * Database Port
		 */
		/** @WadapiString(required=false, default=3306) */
		protected $port;

		/*
		 * Database Connection
		 */
		private $connection;

		public function __construct(){
			call_user_func_array(array("\Wadapi\Persistence\DatabaseConnection", "__construct"), func_get_args());
			$this->connect();
		}

		/*
		 * Establishes a connection to an SQL database server. Closes an existing connection if any.
		 */
		public function connect(){
			if($this->connection){
				return;
			}

			$options = [
			  \PDO::MYSQL_ATTR_PORT => $this->getPort(),
			  \PDO::ATTR_EMULATE_PREPARES => false,
			  \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
		      \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
		    ];

			try{
				$dsn = "{$this->getDriver()}:";
				switch($this->getDriver()){
					case "sqlsrv":
						$options[\PDO::SQLSRV_ATTR_FETCHES_NUMERIC_TYPE] = true;
						$dsn.="server={$this->getHostname()};Database={$this->getDatabase()}";
						break;
					case "mysql":
					default:
						$options[\PDO::ATTR_AUTOCOMMIT] = 0;

						$dsn.="host={$this->getHostname()};dbname={$this->getDatabase()};charset=latin1";
						break;
				}

				$newConnection = new \PDO($dsn,$this->getUsername(), $this->getPassword(), $options);
			}catch(\PDOException $e){
				Logger::fatal_error(MessageUtility::DATABASE_CONNECT_ERROR, $e->getMessage().".");
				return;
			}

			$this->connection = $newConnection;
  		$this->connection->beginTransaction();
		}

		/*
		 * Returns a result set after executing the passed statement on the database connection.
		 * Any additional arguments are bound to the prepared statement
		 */
		public function execute(){
			if(!$this->connection){
				Logger::fatal_error(MessageUtility::DATABASE_EXECUTE_ERROR, "There was an attempt to execute a query using a closed connection.");
				return;
			}

			$arguments = func_get_args();
			$statement = $arguments[0];
			Detective::investigate($statement,false);

			$parameters = array_slice($arguments, 1, substr_count($statement, "?"));

			//Convert all $parameters into references
			foreach($parameters as $parameter){
				if(!is_null($parameter) && !is_scalar($parameter)){
					Logger::fatal_error(MessageUtility::DATABASE_EXECUTE_ERROR, "Query parameter of type ".gettype($parameter)." was passed to".
										" SQLConnection::execute(). Primitive type expected.");
					return;
				}
			}

			try{
				$preparedStatement = $this->connection->prepare($statement);

				for($i=1; $i <= sizeof($parameters); $i++){
					$parameter = $parameters[$i-1];

					if(is_null($parameter)){
						$dataType = \PDO::PARAM_NULL;
					}else if(is_bool($parameter)){
						$dataType = \PDO::PARAM_BOOL;
					}else if(is_int($parameter)){
						$dataType = \PDO::PARAM_INT;
					}else{
						$dataType = \PDO::PARAM_STR;
					}

					$preparedStatement->bindValue($i,$parameters[$i-1],$dataType);
				}

				$preparedStatement->execute();
			}catch(\PDOException $e){
				Logger::fatal_error(MessageUtility::DATABASE_EXECUTE_ERROR, $e->getMessage().". $statement");
				return;
			}

			Detective::closeCase($statement,false);
			while($preparedStatement->columnCount() === 0 && $preparedStatement->nextRowset()) {
		    // Advance rowset until we get to a rowset with data
			}

			if($preparedStatement->columnCount() > 0) {
				return $preparedStatement->fetchAll();
			}else{
				return [];
			}
		}

		/*
		 * Commits the current transaction
		 */
		public function commit(){
			if($this->connection){
				$this->connection->commit();
			}
		}

		/*
		 * Rollbacks the current transaction
		 */
		public function rollback(){
			if($this->connection){
				$this->connection->rollback();
			}
		}

		/*
		 * Closes a connection to a SQL database server.
		 */
		public function close(){
			$this->connection = null;
		}

		/*
		 * Checks if the connection is open.
		 */
		public function isClosed(){
			return is_null($this->connection);
		}

		/*
		 * Lists all tables in the database
		 */
		public function listTables(){
			if(!$this->connection){
				return [];
			}

			switch($this->getDriver()){
				case "sqlsrv":
					return $this->execute("SELECT * FROM sys.tables");
					break;
				case "mysql":
				default:
					return $this->execute("SHOW TABLES");
			}
		}

		/*
		 * Describes the given database table
		 */
		public function describe($tableName){
			if(!$this->connection){
				return [];
			}

			switch($this->getDriver()){
				case "sqlsrv":
					$result = $this->execute("SELECT column_name AS [Field],IS_NULLABLE AS [null?],DATA_TYPE + COALESCE('(' + CASE WHEN CHARACTER_MAXIMUM_LENGTH = -1 ".
																		"THEN 'Max' ELSE CAST(CHARACTER_MAXIMUM_LENGTH AS VARCHAR(5)) END + ')', '') AS [Type] ".
																		"FROM INFORMATION_SCHEMA.Columns WHERE  table_name = '$tableName'");

					for($i=0; $i < sizeof($result); $i++){
						$result[$i]["Key"] = "";
					}

					return $result;
					break;
				case "mysql":
				default:
					return $this->execute("DESC $tableName");
			}
		}
	}
?>
