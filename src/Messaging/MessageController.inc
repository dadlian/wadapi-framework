<?php
  namespace Wadapi\Messaging;

  use Wadapi\System\WadapiClass;
  use Wadapi\System\Registrar;

  abstract class MessageController extends WadapiClass{
    public function subscribe($message){
      $this->execute($message);

			//Commit any changes to the database made by this subscription
			$registeredConnections = Registrar::getRegistered('Wadapi\Persistence\DatabaseConnection');
			foreach($registeredConnections as $connection){
				$connection->commit();
      }
    }

		//Main execution loop of MessageController, called by SubscriptionManager in response to subscribed event being published
    public abstract function execute($message);
  }
?>
