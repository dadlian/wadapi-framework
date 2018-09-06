<?php
	namespace Wadapi\Persistence;

	use Wadapi\System\WadapiClass;
	use Wadapi\Utility\ArrayUtility;

	class SQLString extends WadapiClass{
		const SELECT_STRING = "SELECT";
		const INSERT_STRING = "INSERT";
		const UPDATE_STRING = "UPDATE";
		const DELETE_STRING = "DELETE";
		const CREATE_STRING = "CREATE";
		const RENAME_STRING = "RENAME";
		const DROP_STRING = "DROP";

		/*
		 * The actual SQL query string
		 */
		/** @WadapiString(required=true) */
		protected $sqlString;

		/*
		 * Returns the SQL statement type
		 */
		public function getType(){
			$tokens = preg_split("/\s+/", $this->getSqlString());
			return $tokens[0];
		}

		/*
		 * Returns an array of all the table names contained within the SQL String
		 */
		public function getTables(){
			$tableCommands = "(?:\s+INTO|\s+TO|^UPDATE|\s+FROM|\s+TABLE(?: IF NOT EXISTS)?|\s+REFERENCES|^DESC|\s+INDEX ON)\s+";
			$tableReference = "\w+(?:\s+AS\s+\w+)?";
			$tableDelimeters = "(?:\s*,\s*|\s+(?:(?:LEFT|RIGHT|INNER|OUTER)\s+)?JOIN\s+)";
			$tableCondition = "\s+ON\s+[\w\.]+\s+\=\s+[\w\.]+";
			preg_match_all("/$tableCommands($tableReference(?:$tableDelimeters$tableReference(?:$tableCondition)?)*)/i", $this->getSqlString(), $tables);

			//Return an empty array if the query is invalid
			if(empty($tables)){
				return array();
			}

			$tableToAliasMap = array();
			foreach($tables[1] as $tableCluster){
				$tableStrings = preg_split("/$tableDelimeters/", $tableCluster);

				//Remove table aliases from table names
				foreach($tableStrings as $tableString){
					$tableParts = preg_split("/\s+/", $tableString);

					$tableName = $tableParts[0];
					$tableAlias = "";
					if(sizeof($tableParts) > 2){
						$tableAlias = $tableParts[2];
					}

					$tableToAliasMap[$tableParts[0]] = $tableAlias;
				}
			}

			return $tableToAliasMap;
		}

		/*
		 * Returns an array of all the referenced column names contained within the SQL String. It
		 * does not include projected columns of SELECT queries
		 */
		public function getColumns(){
			$columns = array();
			$foundTokens = array();

			$columnSyntax = "((?:\w+\.)?\w+)";
			$columnComparison = "\s*(?:=|!=|>=|<=|<>|>|<|LIKE|NOT|\s+IN)\s*";
			$conjunctions = "(?:AND|OR)";

			if($this->getType() == self::SELECT_STRING || $this->getType() == self::DELETE_STRING){
				$selectColumnFinder = "$columnSyntax$columnComparison\S+\s*$conjunctions*\s*";
				preg_match_all("/$selectColumnFinder/", $this->getSqlString(), $columnTokens);
				$foundTokens = $columnTokens[1];

				$selectColumnFinder = "$columnComparison\s+(\w+\.\w+)";
				preg_match_all("/$selectColumnFinder/", $this->getSqlString(), $columnTokens);
				$foundTokens = array_merge($foundTokens, $columnTokens[1]);

				$selectColumnFinder = "(\w+)\s+(?:ASC|DESC)\s*,?\s*";
				preg_match_all("/$selectColumnFinder/", $this->getSqlString(), $columnTokens);
				$foundTokens = array_merge($foundTokens, $columnTokens[1]);
			}else if($this->getType() == self::INSERT_STRING){
				$insertColumnFinder = "\(((?:\w+\s*,?\s*)+)\)\s+VALUES";

				if(preg_match("/$insertColumnFinder/", $this->getSqlString(), $insertMatches)){
					$foundTokens = preg_split("/,/", $insertMatches[1]);
					ArrayUtility::array_compress($foundTokens);
				}
			}else if($this->getType() == self::UPDATE_STRING){
				$updateColumnFinder = "$columnSyntax$columnComparison\S+\s*$conjunctions*\s*";
				preg_match_all("/$updateColumnFinder/", $this->getSqlString(), $columnTokens);
				$foundTokens = $columnTokens[1];

				$updateColumnFinder = "$columnComparison\s+(\w+\.\w+)";
				preg_match_all("/$updateColumnFinder/", $this->getSqlString(), $columnTokens);
				$foundTokens = array_merge($foundTokens, $columnTokens[1]);

				$updateColumnFinder = "(\w+)=";
				preg_match_all("/$updateColumnFinder/", $this->getSqlString(), $columnTokens);
				$foundTokens = array_merge($foundTokens, $columnTokens[1]);
			}else if($this->getType() == self::CREATE_STRING){
				$dataTypes = "(?:BIT|TINYINT|SMALLINT|MEDIUMINT|INT|INTEGER|BIGINT|REAL|DOUBLE|FLOAT|DECIMAL|NUMERIC|DATE|TIME";
				$dataTypes .= "|TIMESTAMP|DATETIME|YEAR|CHAR|VARCHAR|BINARY|VARBINARY|TINYBLOB|BLOB|MEDIUMBLOB|LONGBLOB|TINYTEXT";
				$dataTypes .= "|TEXT|MEDIUMTEXT|LONGTEXT|ENUM|SET|BOOL)";
				$createColumnFinder = "(\w+)\s+$dataTypes";
				preg_match_all("/$createColumnFinder/", $this->getSqlString(), $columnTokens);
				$foundTokens = $columnTokens[1];
			}

			foreach($foundTokens as $token){
				$tokenPieces = array();
				$tokenPieces = preg_split("/\./", $token);

				$table = "";
				if(sizeof($tokenPieces) > 1){
					$table = $tokenPieces[0];
				}

				if(!in_array($token, array_keys($columns)) || $table){
					$columns[$token] = $table;
				}
			}

			return $columns;
		}
	}
?>
