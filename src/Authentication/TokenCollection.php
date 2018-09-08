<?php
	namespace Wadapi\Authentication;

	use Wadapi\Http\ResourceController;
	use Wadapi\Http\RequestHandler;
	use Wadapi\Http\ResponseHandler;
	use Wadapi\Persistence\SQLGateway;
	use Wadapi\Persistence\Searcher;
	use Wadapi\Persistence\Criterion;

	class TokenCollection extends ResourceController{
		protected function post(){
			$sqlGateway = new SQLGateway();
			$searcher = new Searcher();
			$searcher->addCriterion("id",Criterion::EQUAL,$this->viewFromArguments("access"));

			$token = $sqlGateway->findUnique("Wadapi\Authentication\APIToken",$searcher);
			$lifetime = $this->getFromContent("lifetime")?$this->getFromContent("lifetime"):0;
			$accessTokens = $token->refresh($lifetime);
			$sqlGateway->save($token);

			$payload = array(
				"self"=>"{$this->getBase()}/".RequestHandler::getRequestURI()."/active",
				"key"=>$accessTokens["key"],
				"secret"=>$accessTokens["secret"],
				"refresh"=>$accessTokens["refresh"],
				"lifetime"=>$lifetime
			);

			ResponseHandler::addExpiry($token->getExpires());
			ResponseHandler::created($payload,$payload["self"]);
		}

		protected function isInvalid(){
			$invalidArguments = array();
			if($lifetime = $this->getFromContent("lifetime")){
				if(!is_numeric($lifetime) || $lifetime < 0){
					$invalidArguments[] = "lifetime";
				}
			}

			return $invalidArguments;
		}

		protected function isConsistent($modified,$eTag){
			return true;
		}

		protected function assemblePayload($object){
			return "";
		}
	}
?>
