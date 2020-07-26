<?php
	namespace Wadapi\Authentication;

	use Wadapi\Http\RestController;
	use Wadapi\Http\RequestHandler;
	use Wadapi\Http\ResponseHandler;
	use Wadapi\Persistence\SQLGateway;
	use Wadapi\Persistence\Searcher;
	use Wadapi\Persistence\Criterion;

	class TokenCollection extends RestController{
		protected function post(){
			$data = RequestHandler::getContent();
			$lifetime = array_key_exists("lifetime",$data)?$data["lifetime"]:0;
			if(!is_numeric($lifetime) || $lifetime < 0){
				ResponseHandler::bad("Lifetime must be a valid number greater than 0.");
			}

			$sqlGateway = new SQLGateway();
			$searcher = new Searcher();
			$searcher->addCriterion("id",Criterion::EQUAL,$this->viewFromArguments("access"));
			$token = $sqlGateway->findUnique("Wadapi\Authentication\APIToken",$searcher);

			$accessTokens = $token->refresh($lifetime);
			$accessTokens["lifetime"] = $lifetime;
			$sqlGateway->save($token);

			ResponseHandler::created($accessTokens,$token->getURI());
		}
	}
?>
