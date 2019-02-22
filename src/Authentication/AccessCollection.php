<?php
	namespace Wadapi\Authentication;

	use Wadapi\Http\ResponseHandler;
	use Wadapi\Persistence\SQLGateway;
	use Wadapi\Persistence\Searcher;
	use Wadapi\Persistence\Criterion;

	class AccessCollection extends AccessController{
		public function post(){
			$invalidatedToken = $this->getFromContent("invalidated-token");
			$sqlGateway = new SQLGateway();

			if($invalidatedToken){
				$searcher = new Searcher();

				preg_match($this->getRegexBase()."\/access\/([0-9]{14})$/",$invalidatedToken,$matches);
				$searcher->addCriterion("id",Criterion::EQUAL,$matches[1]);
				$profile = $sqlGateway->findUnique("Wadapi\Authentication\APIToken",$searcher)->getProfile();
			}else{
				$profileClass = $this->getTokenProfileClass();
				$reflectedProfileClass = new \ReflectionClass($profileClass);
				$profile = ($profileClass && !$reflectedProfileClass->isAbstract())?new $profileClass():null;
			}

			//Create Access Token
			$token = new APIToken($this->getFromContent("role"));
			$accessTokens = $token->refresh(1);
			$token->setProfile($profile);

			if($profile && $invalidArguments = $profile->initialise($this->getRequestArguments(),$token)){
				ResponseHandler::bad("The following values are missing or invalid: ".implode(", ",$invalidArguments).".");
			}

			$sqlGateway->save($token);

			$lifetime = $token->getExpires()?($token->getExpires()-$token->getModified()):0;
			$payload = array(
				"self"=>$token->getURI(),
				"tokens"=>"{$token->getURI()}/tokens",
				"active-token"=>array(
					"self"=>"{$token->getURI()}/tokens/active",
					"key"=>$accessTokens["key"],
					"secret"=>$accessTokens["secret"],
					"refresh"=>$accessTokens["refresh"],
					"lifetime"=>$lifetime
				),
				"role"=>$this->getFromContent("role"),
				"profile"=>$profile?$profile->deliverPayload():""
			);

			ResponseHandler::created($payload,$token->getURI());
		}

		protected function isConsistent($modifiedDate,$eTag){
			return true;
		}

		protected function assemblePayload($object){
			return true;
		}
	}
?>
