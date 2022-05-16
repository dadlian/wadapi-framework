<?php
  namespace Wadapi\Messaging;

  use PhpAmqpLib\Message\AMQPMessage;
  use Wadapi\System\WadapiClass;
  use Wadapi\System\Registrar;

  abstract class ServiceController extends WadapiClass{
    public function subscribe($request){
      //Process procedure call and get response
      $return = $this->respond(json_decode($request->body,true));

      //Create response message
      $response = new AMQPMessage(
        json_encode($return),
        array('correlation_id' => $request->get('correlation_id'))
      );

      //Send response
      $request->delivery_info['channel']->basic_publish(
          $response,
          '',
          $request->get('reply_to')
      );

      $request->ack();

			//Commit any changes to the database made by this subscription
			$registeredConnections = Registrar::getRegistered('Wadapi\Persistence\DatabaseConnection');
			foreach($registeredConnections as $connection){
				$connection->commit();
				$connection->close();
      }
    }

		//Main execution loop of MessageController, called by SubscriptionManager in response to subscribed event being published
    public abstract function respond($message);
  }
?>
