<?php
  namespace Wadapi\Messaging;

  use Wadapi\System\Worker;
  use Wadapi\Reflection\Mirror;

  class SubscriptionManager extends Worker{
    public static function manage(){
      //Setup microservice subscriptions
      $subscriptions = json_decode(file_get_contents(MAPPINGS."/subscriptions.json"),true)["subscriptions"];

      foreach($subscriptions as $subscription){
        $entity = $subscription["entity"];
        if(!$entity || !is_string($entity)){
          continue;
        }

        $action = $subscription["action"];
        if(!$action || !is_string($action)){
          continue;
        }

        $controllerName = $subscription["controller"];
        if(!$controllerName || !Mirror::reflectClass($controllerName)->descendsFrom("Wadapi\Messaging\MessageController")){
          continue;
        }

        Messenger::subscribe($entity,$action,array(new $controllerName(),"subscribe"));
      }

      //Setup microservice services
      $services = json_decode(file_get_contents(MAPPINGS."/services.json"),true)["services"];

      foreach($services as $service){
        $name = $service["name"];
        if(!$name || !is_string($name)){
          continue;
        }

        $controllerName = $service["controller"];
        if(!$controllerName || !Mirror::reflectClass($controllerName)->descendsFrom("Wadapi\Messaging\ServiceController")){
          continue;
        }

        Messenger::subscribe("Services",$name,array(new $controllerName(),"subscribe"));
      }

      if(!$services && !$subscriptions){
        Messenger::subscribe("default","default",array(new NullController(),"subscribe"));
      }

      Messenger::listen();
    }
  }
?>
