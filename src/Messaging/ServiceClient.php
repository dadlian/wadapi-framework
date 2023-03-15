<?php
  namespace Wadapi\Messaging;

  use PhpAmqpLib\Connection\AMQPStreamConnection;
  use PhpAmqpLib\Message\AMQPMessage;

	use Wadapi\System\SettingsManager;
  use Wadapi\System\WadapiClass;

  class ServiceClient extends WadapiClass{
    private $connection;
    private $channel;
    private $callback_queue;
    private $response;
    private $correlation_id;
    private $service;

    public function __construct($service = ""){
      $hostname = SettingsManager::getSetting("messaging","hostname");
      $port = SettingsManager::getSetting("messaging","port");
      $username = SettingsManager::getSetting("messaging","username");
      $password = SettingsManager::getSetting("messaging","password");

      $this->connection = new AMQPStreamConnection($hostname,$port,$username,$password);
      $this->channel = $this->connection->channel();
      list($this->callback_queue, ,) = $this->channel->queue_declare(
          "",
          false,
          false,
          true,
          false
      );

      $this->channel->basic_consume(
          $this->callback_queue,
          '',
          false,
          true,
          false,
          false,
          array(
            $this,
            'onResponse'
          )
      );

      $this->service = $service;
    }

    public function onResponse($response){
      if($response->get('correlation_id') == $this->correlation_id){
        $this->response = $response->body;
      }
    }

    public function call($arguments){
      $this->response = null;
      $this->correlation_id = uniqid();

      $request = new AMQPMessage(
        json_encode($arguments),
        array(
          "content_type" => "application/json",
          "correlation_id" => $this->correlation_id,
          "reply_to" => $this->callback_queue
        )
      );

      $this->channel->basic_publish($request, "Services", $this->service);
      while (!$this->response) {
          $this->channel->wait();
      }

      return json_decode($this->response, true);
    }

    public function close(){
      $this->channel->close();
      $this->connection->close();
    }
  }
?>
