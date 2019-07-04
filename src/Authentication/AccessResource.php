<?php
	namespace Wadapi\Authentication;

	use Wadapi\Http\RequestHandler;
	use Wadapi\Http\ResponseHandler;
	use Wadapi\Persistence\SQLGateway;
	use Wadapi\Persistence\CryptKeeper;

	class AccessResource extends AccessController{
		public function execute(){
			$profileClass = $this->getTokenProfileClass();
			parent::execute();
		}

		protected function get(){
			$token = $this->getResourceObject("Wadapi\Authentication\APIToken","id",$this->viewFromArguments("access"),false);
			$payload = $this->assemblePayload($token);
			$eTag = md5($token->getETag());
			ResponseHandler::retrieved($payload,$token->getURI(),$token->getModified(),$eTag);
		}

		protected function put(){
			$token = $this->getResourceObject("Wadapi\Authentication\APIToken","id",$this->viewFromArguments("access"));

			$role = $this->getFromContent("role");
			$token->setRole($role);

			$sqlGateway = new SQLGateway();
			$sqlGateway->save($token);

			$payload = $this->assemblePayload($token);
			$eTag = md5($token->getETag());
			ResponseHandler::modified($payload,$token->getURI());
		}

		protected function delete(){
			$token = $this->getResourceObject("Wadapi\Authentication\APIToken","id",$this->viewFromArguments("access"));

			CryptKeeper::bury($token);
			ResponseHandler::deleted("Client access: /".RequestHandler::getRequestURI().", has been revoked.");
		}

		protected function isConsistent($modifiedDate,$eTag){
			$token = $this->getResourceObject("Wadapi\Authentication\APIToken","id",$this->viewFromArguments("access"));
			return $modifiedDate == $token->getModified() && $eTag == md5($token->getETag());
		}

		protected function assemblePayload($token){
			$payload = array(
				"self"=>$token->getURI(),
				"tokens"=>"{$token->getURI()}/tokens",
				"active-token"=>"{$token->getURI()}/tokens/active",
				"role"=>$token->getRole(),
				"enabled"=>!$token->isDisabled(),
				"profile"=>$token->getProfile()?$token->getProfile()->deliverPayload():""
			);

			return $payload;
		}
	}
?>
