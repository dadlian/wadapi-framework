<?php
	namespace Wadapi\Messaging;

  use PhpAmqpLib\Connection\AMQPStreamConnection;
  use PhpAmqpLib\Message\AMQPMessage;

	use Wadapi\System\Worker;
	use Wadapi\System\SettingsManager;

	class Messenger extends Worker{
		/*
		 * The active rabbitmq connection
		 */
		private static $_activeConnection;

		/*
		 * The active rabbitmq channel
		 */
		private static $_activeChannel;

		/*
		 * The active rabbitmq channel
		 */
		private static $_activeQueue;

		/*
		 * Array of callback functions tied to specific subscriptions
		 */
		private static $_callbacks;

    public static function publish($topic,$message){
      if(!is_string($topic)){
        return false;
      }

      $exchange = SettingsManager::getSetting("messaging","exchange");

      $channel = self::getChannel();
      $channel->exchange_declare($exchange,"topic",false,false,false);

      $message = new AMQPMessage(json_encode($message),array("content_type"=>"application/json"));
      $channel->basic_publish($message,$exchange,$topic);

      return true;
    }

    public static function subscribe($exchange,$topic,$callback){
      if(!is_string($exchange) || !is_string($topic)){
        return false;
      }

			$channel = self::getChannel();
			$channel->exchange_declare($exchange,"topic",false,false,false);
			$channel->queue_bind(self::$_activeQueue,$exchange,$topic);
			self::$_callbacks["$exchange:$topic"] = $callback;

			$channel->basic_consume(self::$_activeQueue,'',false,true,false,false,function($message){
				$subscribedExchange = $message->delivery_info['exchange'];
				$subscribedTopic = $message->delivery_info['routing_key'];
				$callback = self::$_callbacks["$subscribedExchange:$subscribedTopic"];
				call_user_func_array($callback,array(json_decode($message->body,true)));
			});
    }

    public static function listen(){
			$channel = self::getChannel();
			while($channel->is_consuming()){
				$channel->wait();
			}
    }

    public static function cleanup(){
      if(self::$_activeChannel){
        self::$_activeChannel->close();
        self::$_activeChannel = null;
      }

      if(self::$_activeConnection){
        self::$_activeConnection->close();
        self::$_activeConnection = null;
      }

			self::$_callbacks = null;
    }

		/*
		 * CHecks whether a RabbitMQ connection exists, and initialises one if not
		 */
		private static function getChannel(){
			if(!self::$_activeChannel){
				self::tryConnection(1);
				list(self::$_activeQueue, ,) = self::$_activeChannel->queue_declare("",false,false,true,false);

				self::$_callbacks = array();
			}

			return self::$_activeChannel;
		}

		private static tryConnection($attempt){
			if(!self::$_activeChannel){
				$hostname = SettingsManager::getSetting("messaging","hostname");
				$port = SettingsManager::getSetting("messaging","port");
				$username = SettingsManager::getSetting("messaging","username");
				$password = SettingsManager::getSetting("messaging","password");

				try{
					self::$_activeConnection = new AMQPStreamConnection($hostname,$port,$username,$password);
					self::$_activeChannel = self::$_activeConnection->channel();
				}catch(Exception $e){
					sleep(5*$attempt);
					self::tryConnection($attempt+1);
				}
			}
		}
  }
?>
