<?php
	namespace Wadapi\System;

	use Wadapi\Persistence\RequestHandler;
	use Wadapi\Utility\MessageUtility;
	use Wadapi\Logging\Logger;

	class Detective extends Worker{
		private static $investigations = array();
		private static $customInvestigations = array();
		private static $closedCases = array();
		private static $closedCustomCases = array();

		protected static function investigate($entity,$custom=true){
			if($custom){
				if(!array_key_exists($entity,self::$customInvestigations)){
					self::$customInvestigations[$entity] = array();
				}

				self::$customInvestigations[$entity][] = microtime(true);
			}else{
				if(!array_key_exists($entity,self::$investigations)){
					self::$investigations[$entity] = array();
				}

				self::$investigations[$entity][] = microtime(true);
			}
		}

		protected static function closeCase($entity,$custom=true){
			if(!array_key_exists($entity,self::$investigations) && !array_key_exists($entity,self::$customInvestigations)){
				return;
			}

			if($custom){
				if(!array_key_exists($entity,self::$closedCustomCases)){
					self::$closedCustomCases[$entity] = Array();
				}

				self::$closedCustomCases[$entity][] = round(microtime(true)-array_pop(self::$customInvestigations[$entity]),5);
			}else{
				if(!array_key_exists($entity,self::$closedCases)){
					self::$closedCases[$entity] = Array();
				}

				self::$closedCases[$entity][] = round(microtime(true)-array_pop(self::$investigations[$entity]),5);
			}
		}

		protected static function report(){
			//Don't collect profiling information if the profiler is off
			if(SettingsManager::getSetting("profiler","profiling") !== "on"){
				return;
			}

			$api = SettingsManager::getSetting("profiler","api");

			$queryString = "";
			if(RequestHandler::getQueryParameters()){
				$queryString = array();
				foreach(RequestHandler::getQueryParameters() as $key=>$value){
					$queryString[] = "$key=".urlencode($value);
				}
				$queryString = "?".implode("&",$queryString);
			}
			$endpoint = RequestHandler::getMethod()." /".RequestHandler::getEndpoint()->getPath().$queryString;

			$connection = new mysqli("localhost","wadapi_main","2#wGgy6)*3%","wadapi_main");
			if($connection->connect_error){
				Logger::fatal_error(MessageUtility::DATABASE_CONNECT_ERROR, $connection->connect_error.".");
				return;
			}
			$connection->autocommit(FALSE);


			//Write Investigation Statistics
			$timestamp = strval(time());
			$objectValues = Array();
			$callValues = Array();
			$queryValues = Array();
			$id = number_format(microtime(true)*10000,0,'','');
			foreach(self::$closedCases as $entity => $cases){
				$id++;

				if(class_exists($entity)){
					$class = $entity;
					$instances = count($cases);
					$loadTime = round(array_sum($cases),5);

					$objectValues[] = "($id,$timestamp,$timestamp,$api,'$endpoint','$class',$instances,$loadTime,1)";
				}else if(sizeof(preg_split("/\s/",$entity)) == 2 && filter_var(preg_split("/\s/",$entity)[1], FILTER_VALIDATE_URL)){
					$externalCall = $entity;
					$calls = count($cases);
					$roundtrip = round(array_sum($cases),5);

					$callValues[] = "($id,$timestamp,$timestamp,$api,'$endpoint','$externalCall',$calls,$roundtrip,1)";
				}else{
					$query = $entity;
					$executions = count($cases);
					$runtime = round(array_sum($cases),5);

					$queryValues[] = "($id,$timestamp,$timestamp,$api,'$endpoint','".$connection->escape_string($query)."',$executions,$runtime,1)";
				}
			}

			if($objectValues){
				$connection->query("INSERT INTO profiler_ObjectStatistic VALUES ".join(",",$objectValues)." ON DUPLICATE KEY UPDATE ".
							"modified=VALUES(modified),instances=instances+VALUES(instances),loadTime=loadTime+VALUES(loadTime),requests=requests+1");
			}

			if($callValues){
				$connection->query("INSERT INTO profiler_CallStatistic VALUES ".join(",",$callValues)." ON DUPLICATE KEY UPDATE ".
							"modified=VALUES(modified),calls=calls+VALUES(calls),roundtrip=roundtrip+VALUES(roundtrip),requests=requests+1");
			}

			if($queryValues){
				$connection->query("INSERT INTO profiler_QueryStatistic VALUES ".join(",",$queryValues)." ON DUPLICATE KEY UPDATE ".
							"modified=VALUES(modified),executions=executions+VALUES(executions),runtime=runtime+VALUES(runtime),requests=requests+1");
			}

			//Write Custom Investigation Statistics
			$customValues = Array();
			foreach(self::$closedCustomCases as $entity => $cases){
				$id++;

				$key = $entity;
				$runs = count($cases);
				$duration = round(array_sum($cases),5);

				$customValues[] = "($id,$timestamp,$timestamp,$api,'$endpoint','$key',$runs,$duration,1)";
			}

			if($customValues){
				$connection->query("INSERT INTO profiler_CustomStatistic VALUES ".join(",",$customValues)." ON DUPLICATE KEY UPDATE ".
							"modified=VALUES(modified),runs=runs+VALUES(runs),duration=duration+VALUES(duration),requests=requests+1");
			}


			//Write Endpoint Statistics
			$id++;
			$date = date("Y-m-d");
			$runtime = round(microtime(true)-$GLOBALS["scriptStartTime"],5);

			$connection->query("INSERT INTO profiler_EndpointStatistic VALUES($id,$timestamp,$timestamp,$api,'$endpoint','$date',1,$runtime) ".
						"ON DUPLICATE KEY UPDATE modified='$timestamp',requests=requests+1,runtime=runtime+$runtime");

			if($connection->affected_rows == 1){
				$connection->query("INSERT INTO profiler_Resource VALUES($id,$timestamp,$timestamp)");
			}

			$connection->commit();
			$connection->close();
		}
	}
?>
