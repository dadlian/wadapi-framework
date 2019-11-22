<?php
	namespace Wadapi\System;

	use Wadapi\Persistence\QuarterMaster;
	use Wadapi\Messaging\Messenger;

	class Janitor extends Worker{
		protected static function cleanup($success=true){
			//Clear Object Cache
			QuarterMaster::decommission();

			//Close any open RabbitMQ connections
			Messenger::cleanup();

			//Cleanup all currently active controllers
			$registeredControllers = Registrar::getRegistered('Wadapi\System\Controller');
			foreach($registeredControllers as $controller){
				$controller->finish();
				Registrar::unregister($controller);
			}

			//Rollback and close all active connections
			$registeredConnections = Registrar::getRegistered('Wadapi\Persistence\DatabaseConnection');
			foreach($registeredConnections as $connection){
				if($success){
					$connection->commit();
				}else{
					$connection->rollback();
				}

				$connection->close();
				Registrar::unregister($connection);
			}

			//Report Profiling Data
			Detective::report();
		}
	}
?>
