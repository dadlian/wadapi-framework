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

			//Call user retrieval hook
			$resource = $this->retrieveResource($resource);

			$payload = $resource->deliverPayload();
			foreach($this->getCustomPayloadFields() as $customField => $customValue){
				$payload[$customField] = $customValue;
			}

			ResponseHandler::retrieved($payload,$resource->getURI(),$resource->getModified(),$resource->getETag());
		}

		protected function put(){
			$bodyArguments = RequestHandler::getContent()?RequestHandler::getContent():array();

			//Load Resource
			$resource = $this->_retrieveResource();

			//Check for Resource Consistency
			if(SettingsManager::getSetting("api", "consistency")){
				$this->_checkConsistency($resource);
			}

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
			if(SettingsManager::getSetting("api", "consistency")){
				$this->_checkConsistency($resource);
			}

			//Ensure DELETE method is supported
			$resource = $this->deleteResource($resource);
			if(!$resource){
				ResponseHandler::unsupported("/".RequestHandler::getRequestURI()." does not support the DELETE method.");
			}

			//Check if the resource has been removed and bury it if so
			$class = Mirror::reflectClass($resource);
			$present = DatabaseAdministrator::execute("SELECT COUNT(id) as present FROM {$class->getShortName()} WHERE id = '{$resource->getId()}'")[0]['present'];
			if(!$present){
				CryptKeeper::bury($resource);
			}

			//Return deleted resource message
			ResponseHandler::deleted(get_class($resource).": {$resource->getURI()}, has been deleted.");
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
