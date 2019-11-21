<?php
	namespace Wadapi\Routing;

	use Wadapi\System\Worker;
	use Wadapi\Persistence\SQLGateway;
	use Wadapi\Persistence\Searcher;
	use Wadapi\Http\RequestHandler;
	use Wadapi\Http\ResponseHandler;
	use Wadapi\Reflection\Mirror;
	use Wadapi\System\SettingsManager;

	class Dispatcher extends Worker{
		protected static function dispatchRequest(){
			$sqlGateway = new SQLGateway();
			$searcher = new Searcher();

			//If there is no controller mapped to this endpoint
			if(!RequestHandler::getEndpoint() || !class_exists(RequestHandler::getEndpoint()->getController()) ||
					!Mirror::reflectClass(RequestHandler::getEndpoint()->getController())->descendsFrom("Wadapi\Http\RestController")){
				ResponseHandler::missing("The requested endpoint /".RequestHandler::getRequestURI()." does not exist on this server.");
			}

			//Answer OPTIONS requests without requiring authorisation
			if(strtoupper(RequestHandler::getMethod()) == "OPTIONS"){
				$controllerClass = RequestHandler::getEndpoint()->getController();
				$controller = new $controllerClass(RequestHandler::getURIArguments());
				$controller->options();
				return;
			}

			$isAuthorised = false;
			switch(SettingsManager::getSetting("api","auth")){
				case "basic":
					//Ensure request is authenticated
					if(!RequestHandler::getAuthorisation()){
						ResponseHandler::unauthorised("Please use Basic Authentication to authorise this request.");
					}

					//Ensure request authentication is valid
					$authorisation = RequestHandler::getAuthorisation();
					$accessSecret = $authorisation["secret"];

					$token = RequestHandler::getAuthenticatedToken();
					$method = strtolower(RequestHandler::getMethod());

					$isValidKey = $token;
					$isFresh = $isValidKey && (!$token->getExpires() || $token->getExpires() > time());
					$isValidSecret = $isValidKey && $isFresh && $token->getAccessSecret() == md5($accessSecret);
					$isValidRefresh = $isValidKey && $token->getRefreshSecret() == md5($accessSecret);
					$isRefreshRequest = preg_match("/^access\/[0-9]+\/tokens$/",RequestHandler::getRequestURI());
					$isValidRole = $isValidKey && ($token->getRole() == "root" || RequestHandler::getEndpoint()->viewFromRoles($token->getRole()));
					$isEnabled = $isValidKey && !$token->isDisabled();
					$isRoot = $isValidRole && $token->getRole() == "root";
					$activeRole = $isValidRole?RequestHandler::getEndpoint()->viewFromRoles($token->getRole()):null;

					$hasPermission = $isRoot || ($isValidRole && ((in_array($method,array("get")) && in_array("read",$activeRole->getPermissions())) ||
									in_array($method,array("put","post","delete")) && in_array("write",$activeRole->getPermissions())));

					$isAuthorised = ($isValidRole && $hasPermission && $isValidSecret && $isEnabled) || ($isRefreshRequest && $isValidRefresh);
					break;
				case "none":
				default:
					$isAuthorised = true;
					break;
			}

			if(RequestHandler::isUtility() || $isAuthorised){
				$controllerClass = RequestHandler::getEndpoint()->getController();
				$controller = new $controllerClass(RequestHandler::getURIArguments());
				$controller->execute();
			}else{
				ResponseHandler::forbidden("The provided tokens do not have permission to perform this action.");
			}
		}
	}
?>
