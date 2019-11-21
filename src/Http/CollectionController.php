<?php
	namespace Wadapi\Http;

	use Wadapi\System\SettingsManager;

	abstract class CollectionController extends RestController{
		protected function get(){
			$maxPageLength = 25;
			$page = RequestHandler::getQueryParameter("page")?RequestHandler::getQueryParameter("page"):1;
			$records = RequestHandler::getQueryParameter("records")?RequestHandler::getQueryParameter("records"):$maxPageLength;

			//Verify validity of page and records values
			$invalidArguments = $this->getInvalidQueryParameters();

			if(!(string)(int)$page == $page || intval($page) < 1){
				$invalidArguments[] = "page";
			}

			if(!(string)(int)$records == $records || intval($records) < 1){
				$invalidArguments[] = "records";
			}

			if($invalidArguments){
				ResponseHandler::bad("The following query parameters have invalid values: ".implode(", ",$invalidArguments).".");
			}else{
				$page = intval($page);
				$records = intval($records);
			}

			$count = $this->countResources();

			//Verify page exists
			if($count && RequestHandler::getQueryParameter("page") && ($page-1)*$records >= $count){
				ResponseHandler::missing("The specified page does not exist for the given records per page.");
			}

			//Assemble Payload
			$payload = array();

			//Get additional query parameters
			$additionalQueryParameters = "";
			foreach(RequestHandler::getQueryParameters() as $parameter => $argument){
				if(!in_array($parameter,array("page","records"))){
					$additionalQueryParameters .= "&$parameter=".preg_replace("/\s/","%20",$argument);
				}
			}

			$baseUri = SettingsManager::getSetting("install","url");
			//Get page URIs
			$payload['self'] = "$baseUri/".RequestHandler::getRequestURI()."?page=$page".(($records==$maxPageLength)?"":"&records=$records").$additionalQueryParameters;
			if($page > 1){
				$payload['prev'] = "$baseUri/".RequestHandler::getRequestURI()."?page=".($page-1).(($records==$maxPageLength)?"":"&records=$records").$additionalQueryParameters;
			}

			if($page < ceil($count/$records)){
				$payload['next'] = "$baseUri/".RequestHandler::getRequestURI()."?page=".($page+1).(($records==$maxPageLength)?"":"&records=$records").$additionalQueryParameters;
			}

			//Get total record count
			$payload['total'] = $count;

			foreach($this->getCustomPayloadFields() as $customField => $customValue){
				$payload[$customField] = $customValue;
			}

			//Assemble Entries
			$entries = array();
			foreach($this->retrieveResources(($page-1)*$records,$records) as $entry){
				$entries[] = $entry->deliverPayload();
			}

			$payload['entries'] = $entries;

			ResponseHandler::retrieved($payload,$payload['self']);
		}

		protected function post(){
			$bodyArguments = RequestHandler::getContent()?RequestHandler::getContent():array();

			//Ensure POST is supported
			$resource = $this->createResource($bodyArguments);
			if(!$resource){
				ResponseHandler::unsupported("/".RequestHandler::getRequestURI()." does not support the POST method.");
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

			//Return created resource
      ResponseHandler::created($resource->deliverPayload(),$resource->getURI());
		}

		protected function getCustomPayloadFields(){
			return array();
		}

		abstract protected function getInvalidQueryParameters();
		abstract protected function countResources();
		abstract protected function retrieveResources($start,$count);
		abstract protected function createResource($data);
	}
?>