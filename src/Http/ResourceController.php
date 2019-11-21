<?php
	namespace Wadapi\Http;

	use Wadapi\System\SettingsManager;
	use Wadapi\Reflection\Mirror;
	use Wadapi\Persistence\DatabaseAdministrator;
	use Wadapi\Persistence\SQLGateway;
	use Wadapi\Persistence\Searcher;
	use Wadapi\Persistence\Sorter;
	use Wadapi\Persistence\Criterion;
	use Wadapi\Persistence\CryptKeeper;

	abstract class ResourceController extends RestController{
		protected function get(){
			$resource = $this->_retrieveResource();
			ResponseHandler::retrieved($resource->deliverPayload(),$resource->getURI(),$resource->getModified(),$resource->getETag());
		}

		protected function put(){
			$bodyArguments = RequestHandler::getContent()?RequestHandler::getContent():array();

			//Load Resource
			$resource = $this->_retrieveResource();

			//Check for Resource Consistency
			$this->_checkConsistency($resource);

			//Ensure PUT is supported
			$resource = $this->modifyResource($resource, $bodyArguments);
			if(!$resource){
				ResponseHandler::unsupported("/".RequestHandler::getRequestURI()." does not support the PUT method.");
			}

			//Check for any missing arguments
			$missingArguments = $resource->getMissingArguments();
			if($missingArguments){
				ResponseHandler::bad("The following arguments are required, but have not been supplied: ".implode(", ",$missingArguments).".");
			}

			//Check for any missing arguments
			$invalidArguments = $resource->getInvalidArguments();
			if($invalidArguments){
				ResponseHandler::bad("The following arguments have invalid values: ".implode(", ",$invalidArguments).".");
			}

			//Check for any conflicting arguments
			$conflictingArguments = $resource->getConflictingArguments();
			if($conflictingArguments){
				ResponseHandler::conflict("The following arguments conflict with those of another ".strtolower(get_class($resource)).": ".implode(", ",$conflictingArguments).".");
			}

			//Return modified resource
			ResponseHandler::modified($resource->deliverPayload(),$resource->getURI(),$resource->getModified(),$resource->getETag());
		}

		protected function delete(){
			//Load Resource
			$resource = $this->_retrieveResource();

			//Check for Resource Consistency
			$this->_checkConsistency($resource);

			//Ensure DELETE method is supported
			$resource = $this->deleteResource($resource);
			if(!$resource){
				ResponseHandler::unsupported("/".RequestHandler::getRequestURI()." does not support the DELETE method.");
			}

			//Check if the resource has been removed and bury it if so
			$present = DatabaseAdministrator::execute("SELECT COUNT(id) as present FROM ".get_class($resource)." WHERE id = '{$resource->getId()}'")[0]['present'];
			if(!$present){
				CryptKeeper::bury($resource);
			}

			//Return deleted resource message
			ResponseHandler::deleted(get_class($resource).": {$resource->getURI()}, has been deleted.");
		}

		private function _retrieveResource(){
			//Find resource class that matches URL pattern (if any)
			$resourceClass = Mirror::reflectClass("Wadapi\Http\Resource");
			$requestUri = "/".RequestHandler::getRequestURI();
			$targetClass = "";

			foreach($resourceClass->getDescendants() as $resourceDescendant){
				$uriTemplate = call_user_func_array(array($resourceDescendant->getName(),'getURITemplate'),[]);
				$uriPattern = preg_replace("/([\/\\\])/","\\\\$1",preg_replace("/{\w+}/",".*",$uriTemplate));

				if(preg_match("/^$uriPattern$/",$requestUri)){
					$targetClass = $resourceDescendant->getName();
					break;
				}
			}

			if(!$targetClass){
				ResponseHandler::missing("There is presently no resource with the given URI.");
			}

			//Load resource into memory
			$sqlGateway = new SQLGateway();
			$searcher = new Searcher();

			$tokens = array();
			$templateParts = preg_split("/\//",$uriTemplate);
			$uriParts = preg_split("/\//",$requestUri);
			$resourceIdentifier = "";

			for($i=0; $i < sizeof($templateParts); $i++){
				if(preg_match("/{\w+}/",$templateParts[$i])){
					$resourceIdentifier = $uriParts[$i];
					$searcher->addCriterion(preg_replace("/[{}]/","",$templateParts[$i]),Criterion::EQUAL,$resourceIdentifier);
				}
			}

			$resource = $sqlGateway->findUnique($targetClass,$searcher);

			if(!$resource && CryptKeeper::exhume($resourceIdentifier)){
				ResponseHandler::gone("The requested resource no longer exists.");
			}else if(!$resource){
				ResponseHandler::missing("There is presently no resource with the given URI.");
			}else{
				//Call user retrieval hook
				$this->retrieveResource($resource);

				return $resource;
			}
		}

		private function _checkConsistency($resource){
			if(RequestHandler::checksConsistency()){
				$consistencyTags = RequestHandler::getConsistencyTags();
				if($resource->getModified() !== $consistencyTags["ifUnmodifiedSince"] || $resource->getETag() !== $consistencyTags["ifMatch"]){
					ResponseHandler::precondition("The wrong Modification Date and ETag values were given for this resource.");
				}
			}else{
				ResponseHandler::forbidden("If-Unmodified-Since and If-Match Headers must be specified to modify this resource.");
			}
		}

		abstract protected function retrieveResource($uri);
		abstract protected function modifyResource($resource, $data);
		abstract protected function deleteResource($resource);
	}
?>
