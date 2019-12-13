<?php
	namespace Wadapi\Http;

	use Wadapi\System\Controller;
	use Wadapi\System\SettingsManager;
	use Wadapi\Reflection\Mirror;
	use Wadapi\Persistence\SQLGateway;
	use Wadapi\Persistence\Searcher;
	use Wadapi\Persistence\Criterion;
	use Wadapi\Persistence\CryptKeeper;

	abstract class RestController extends Controller{
		public function execute(){
			$method = strtolower(RequestHandler::getMethod());

			if(!Mirror::reflectClass($this)->hasMethod($method)){
				ResponseHandler::unsupported("/".RequestHandler::getRequestURI()." does not support the ".RequestHandler::getMethod()." method.");
			}else{
				$this->$method();
			}
		}

		protected function options(){
			$payload = array("options"=>array());
			foreach(Mirror::reflectClass($this)->getMethods() as $method){
				$methodName = $method->getName();
				if(in_array($methodName,array("get","post","put","delete","options"))){
					$payload["options"][] = strtoupper($methodName);
				}
			}

			$uri = ((array_key_exists('HTTPS',$_SERVER) && $_SERVER['HTTPS'])?"https://":"http://").SettingsManager::getSetting("install","url")."/".RequestHandler::getRequestURI();
			ResponseHandler::retrieved($payload,$uri);

		}

		protected function _retrieveResource($descendant=false){
			//Find resource class that matches URL pattern (if any)
			$resourceClass = Mirror::reflectClass("Wadapi\Http\Resource");
			$requestUri = "/".RequestHandler::getRequestURI();
			$targetClass = "";
			$matchScore = 0;

			foreach($resourceClass->getDescendants() as $resourceDescendant){
				$uriTemplate = call_user_func_array(array($resourceDescendant->getName(),'getURITemplate'),[]);
				$uriPattern = preg_replace("/([\/\\\])/","\\\\$1",preg_replace("/{[\w:]+}/",".*",$uriTemplate));

				if($uriTemplate && preg_match("/^$uriPattern".($descendant?"":"$")."/",$requestUri) && strlen($uriPattern) > $matchScore){
					$matchScore = strlen($uriPattern);
					$targetClass = $resourceDescendant->getName();
				}
			}

			if(!$descendant && !$targetClass){
				ResponseHandler::missing("There is presently no resource with the given URI.");
			}

			if($targetClass){
				//Load resource into memory
				$sqlGateway = new SQLGateway();
				$searcher = new Searcher();

				$tokens = array();
				$templateParts = preg_split("/\//",$targetClass::getURITemplate());
				$uriParts = preg_split("/\//",$requestUri);
				$resourceIdentifier = "";

				for($i=0; $i < sizeof($templateParts); $i++){
					if(preg_match("/{\w+}/",$templateParts[$i])){
						$resourceIdentifier = $uriParts[$i];
						$searcher->addCriterion(preg_replace("/[{}]/","",$templateParts[$i]),Criterion::EQUAL,$resourceIdentifier);
					}
				}

				$resource = $sqlGateway->findUnique($targetClass,$searcher, null, 0, 0, false);
				if(!$resource && CryptKeeper::exhume($resourceIdentifier)){
					ResponseHandler::gone("The requested resource no longer exists.");
				}else if(!$resource){
					ResponseHandler::missing("There is presently no resource with the given URI.");
				}
			}

			return $resource;
		}

		protected function getCustomPayloadFields(){
			return array();
		}
  }
?>
