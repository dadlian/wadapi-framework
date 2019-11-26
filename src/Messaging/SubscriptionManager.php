<?php
  namespace Wadapi\Messaging;

  use Wadapi\System\Worker;
  use Wadapi\Reflection\Mirror;

  class SubscriptionManager extends Worker{
    public static function manage(){
      $subscriptions = json_decode(file_get_contents(PROJECT_PATH."/subscriptions.json"),true)["subscriptions"];

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

      if(!$subscriptions){
        Messenger::subscribe("default","default",array(new NullController(),"subscribe"));
      }

      Messenger::listen();
    }
  }
?>
