<?php
	namespace Wadapi\System;

  use Wadapi\Http\ResourceController;
  use Wadapi\Http\ResponseHandler;

  abstract class UtilityController extends ResourceController{
    public function execute(){
			//Confirm that the user has utilities access
			$password = $this->getFromContent("utility-key");
			if($password !== SettingsManager::getSetting("api","utilitykey")){
				ResponseHandler::forbidden("The provided tokens do not have permission to perform this action.");
			}

      parent::execute();
    }
  }
?>
