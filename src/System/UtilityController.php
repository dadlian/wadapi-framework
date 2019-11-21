<?php
	namespace Wadapi\System;

  use Wadapi\Http\RestController;
  use Wadapi\Http\RequestHandler;
  use Wadapi\Http\ResponseHandler;

  abstract class UtilityController extends RestController{
    public function execute(){
			//Confirm that the user has utilities $accessSecret
			$requestBody = RequestHandler::getContent();
			$requestBody = is_array($requestBody)?$requestBody:[$requestBody];

			$password = array_key_exists("utilitykey",$requestBody)?$requestBody["utilitykey"]:"";
			if($password !== SettingsManager::getSetting("api","utilitykey")){
				ResponseHandler::forbidden("The provided tokens do not have permission to perform this action.");
			}

      parent::execute();
    }
  }
?>
