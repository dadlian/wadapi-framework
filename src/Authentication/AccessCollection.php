<?php
	namespace Wadapi\Authentication;

	use Wadapi\Http\CollectionController;
	use Wadapi\Http\ResponseHandler;
	use Wadapi\Persistence\SQLGateway;
	use Wadapi\Persistence\Searcher;
	use Wadapi\Persistence\Criterion;

	class AccessCollection extends CollectionController{
		private $createdToken;

		protected function getInvalidQueryParameters($parameters){
			return [];
		}

		protected function countResources($parameters,$owner){
			return 0;
		}

		protected function retrieveResources($start,$count,$parameters,$owner){
			return null;
		}

		protected function createResource($data,$owner){
			$sqlGateway = new SQLGateway();
			$searcher = new Searcher();

			$invalidatedToken = array_key_exists("invalidated-token",$data)?$data["invalidated-token"]:"";
			if($invalidatedToken){
				preg_match($this->getRegexBase()."\/access\/([0-9]{14})$/",$invalidatedToken,$matches);

				$searcher->addCriterion("id",Criterion::EQUAL,$matches?$matches[1]:"");
				if(!$matches || !$sqlGateway->findUnique("Wadapi\Authentication\APIToken",$searcher)){
					ResponseHandler::bad("The invalidated token is malformed or non-existant.");
				}
			}

			if($invalidatedToken){
				preg_match($this->getRegexBase()."\/access\/([0-9]{14})$/",$invalidatedToken,$matches);
				$searcher->clearCriteria();
				$searcher->addCriterion("id",Criterion::EQUAL,$matches[1]);
				$profile = $sqlGateway->findUnique("Wadapi\Authentication\APIToken",$searcher)->getProfile();
			}else{
				$profileClass = APIToken::getTokenProfileClass();
				$reflectedProfileClass = new \ReflectionClass($profileClass);
				$profile = ($profileClass && !$reflectedProfileClass->isAbstract())?new $profileClass():null;
			}

			//Create Access Token
			$token = new APIToken();
			$token->build($data);

			if(!$token->hasBuildErrors()){
				$profile->build($data);

				if(!$profile->hasBuildErrors()){
					$token->setProfile($profile);
					$sqlGateway->save($token);

					$this->createdToken = $token;
				}else{
					$missingArguments = $profile->getMissingArguments();
					if($missingArguments){
						ResponseHandler::bad("The following arguments are required, but have not been supplied: ".implode(", ",$missingArguments).".");
					}

					//Check for any missing arguments
					$invalidArguments = $profile->getInvalidArguments();
					if($invalidArguments){
						ResponseHandler::bad("The following arguments have invalid values: ".implode(", ",$invalidArguments).".");
					}
				}
			}
			return $token;
		}

		protected function getCustomPayloadFields(){
			$customPayloadFields = [];

			if($this->createdToken){
				$accessTokens = $this->createdToken->refresh(1);
				$customPayloadFields["active-token"] = $accessTokens;
				$customPayloadFields["active-token"]["self"] = "{$this->createdToken->getURI()}/tokens/active";
				$customPayloadFields["active-token"]["lifetime"] = $this->createdToken->getExpires()?($this->createdToken->getExpires()-$this->createdToken->getModified()):0;
			}

			return $customPayloadFields;
		}
	}
?>
