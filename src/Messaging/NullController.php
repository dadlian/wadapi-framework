<?php
  namespace Wadapi\Messaging;

  /* A controller which forces the SubscriptionManager to listen even when no connections are specified */
  class NullController extends MessageController{
    public function execute($message){
    }
  }
?>
