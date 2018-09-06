<?php
	namespace Wadapi\Authentication;

	use Wadapi\Http\ResourceController;
	use Wadapi\Http\RequestHandler;
	use Wadapi\Http\ResponseHandler;
	use Wadapi\Persistence\CryptKeeper;

	class TokenResource extends ResourceController{
		public function delete(){
			$token = $this->getResourceObject("APIToken","id",$this->viewFromArguments("access"));

			if(in_array($token->getRole(),array("root","authenticator"))){
				ResponseHandler::conflict("The {$token->getRole()} access tokens cannot be invalidated.");
			}else{
				CryptKeeper::bury($token);
				ResponseHandler::deleted("Token: /".RequestHandler::getRequestURI().", has been invalidated.");
			}
		}

		protected function isInvalid(){
			return array();
		}

		protected function isConsistent($modified,$eTag){
			return true;
		}

		protected function assemblePayload($object){
			return "";
		}
	}
?>
