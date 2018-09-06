<?php
	namespace Wadapi\Logging;

	use Wadapi\System\Worker;
	use Wadapi\Utility\TestUtility;

	class Logger extends Worker{
		protected static function warning($warnings, $debugs){
			//Wrap single element warnings and debugs in arrays
			if(!is_array($warnings)){
				$warnings = array($warnings);
			}

			if(!is_array($debugs)){
				$debugs = array($debugs);
			}

			foreach($warnings as $warning){
				Postman::postWarning($warning);
			}

			foreach($debugs as $debug){
				Postman::postDebug($debug);
			}
		}

		protected static function fatal_error($errors, $debugs){
			//Wrap single element errors and debugs in arrays
			if(!is_array($errors)){
				$errors = array($errors);
			}

			if(!is_array($debugs)){
				$debugs = array($debugs);
			}

			foreach($errors as $error){
				Postman::postError($error);
			}

			foreach($debugs as $debug){
				Postman::postDebug($debug);
			}

			if(!TestUtility::is_test_run()){
				$errorHandler = new APIError();
				$errorHandler->execute();
				exit;
			}else{
				foreach(Postman::deliverErrors() as $error){
					echo "{$error->getText()}\n";
				}

				foreach(Postman::deliverDebugs() as $debug){
					echo "{$debug->getText()}\n";
				}
			}
		}
	}
?>
