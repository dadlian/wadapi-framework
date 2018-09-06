<?php
	namespace Wadapi\Http;

	use Wadapi\System\Controller;
	use Wadapi\System\SettingsManager;
	use Wadapi\Reflection\Mirror;
	use Wadapi\Persistence\SQLGateway;
	use Wadapi\Persistence\Searcher;
	use Wadapi\Persistence\Sorter;
	use Wadapi\Persistence\CryptKeeper;

	abstract class ResourceController extends Controller{
		public function execute(){
			$method = strtolower(RequestHandler::getMethod());

			if(!Mirror::reflectClass($this)->hasMethod($method)){
				ResponseHandler::unsupported("/".RequestHandler::getRequestURI()." does not support the ".RequestHandler::getMethod()." method.");
			}else{
				if(in_array($method,array("post","put"))){
					//Check for missing and invalid arguments
					$this->ensureRequirements();
					$this->validateArguments();
				}

				if(in_array($method,array("put","delete")) && $this->mustPrevalidate()){
					if(RequestHandler::checksConsistency()){
						//Check for consistency preconditions
						$consistencyTags = RequestHandler::getConsistencyTags();
						if(!$this->isConsistent($consistencyTags["ifUnmodifiedSince"],$consistencyTags["ifMatch"])){
							ResponseHandler::precondition("The wrong Modification Date and ETag values were given for this resource.");
						}
					}else{
						ResponseHandler::forbidden("If-Unmodified-Since and If-Match Headers must be specified to modify this resource.");
					}
				}

				$this->$method();
			}
		}

		protected function getBase(){
			return ((array_key_exists('HTTPS',$_SERVER) && $_SERVER['HTTPS'])?"https://":"http://").SettingsManager::getSetting("install","url");
		}

		protected function getRegexBase(){
			return "/^https?:\/\/".preg_replace("/\//","\/",preg_quote(SettingsManager::getSetting("install","url")));
		}

		protected function getFromContent($argument,$default=""){
			$arguments = $this->getRequestArguments();
			return array_key_exists($argument,$arguments)?$arguments[$argument]:$default;
		}

		protected function getRequestArguments(){
			return is_array(RequestHandler::getContent())?RequestHandler::getContent():array(RequestHandler::getContent());
		}

		private function ensureRequirements(){
			$missingArguments = array();
			foreach(RequestHandler::getEndpoint()->getRequirements() as $field){
				if(!array_key_exists($field,is_array(RequestHandler::getContent())?RequestHandler::getContent():array(RequestHandler::getContent()))){
					$missingArguments[] = $field;
				}
			}

			if($missingArguments){
				ResponseHandler::bad("The following arguments are required, but have not been supplied: ".implode(", ",$missingArguments).".");
			}
		}

		protected function validateArguments(){
			if($invalidArguments = $this->isInvalid()){
				ResponseHandler::bad("The following arguments have invalid values: ".implode(", ",$invalidArguments).".");
			}
		}

		protected function getResourceObject($resourceClass,$resourceKeyField,$resourceKeyValue,$lazyLoad=true){
			$sqlGateway = new SQLGateway();
			$searcher = new Searcher();
			$searcher->addCriterion($resourceKeyField,Criterion::EQUAL,$resourceKeyValue);
			$resource = $sqlGateway->findUnique($resourceClass,$searcher,new Sorter(),0,0,$lazyLoad);

			if(!$resource && !CryptKeeper::exhume($resourceKeyValue,$resourceClass)){
				ResponseHandler::missing("There is presently no resource with the given URI.");
			}

			return $resource;
		}

		protected function mustPrevalidate(){
			return true;
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

		protected abstract function isInvalid();
		protected abstract function isConsistent($modified,$eTag);
		protected abstract function assemblePayload($object);
	}
?>
