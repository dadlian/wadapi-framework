<?php
	namespace Wadapi\Http;

	use Wadapi\System\SettingsManager;

	abstract class CollectionController extends RestController{
		protected function get(){
			$maxPageLength = 25;
			$page = RequestHandler::getQueryParameter("page")?RequestHandler::getQueryParameter("page"):1;
			$records = RequestHandler::getQueryParameter("records")?RequestHandler::getQueryParameter("records"):$maxPageLength;
			$owner = $this->_retrieveResource(true);

			//Verify validity of page and records values
			$invalidArguments = $this->getInvalidQueryParameters(RequestHandler::getQueryParameters());

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

			$count = $this->countResources(RequestHandler::getQueryParameters(),$owner);

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

			//Ensure GET is supported
			$collection = $this->retrieveResources(($page-1)*$records,$records,RequestHandler::getQueryParameters(),$owner);
			if(is_null($collection)){
				ResponseHandler::unsupported("/".RequestHandler::getRequestURI()." does not support the GET method.");
			}

			//Assemble Entries
			$entries = array();
			foreach($collection as $resource){
				$entries[] = $resource->deliverPayload();
			}

			$payload['entries'] = $entries;

			ResponseHandler::retrieved($payload,$payload['self']);
		}

		protected function post(){
			$bodyArguments = RequestHandler::getContent()?RequestHandler::getContent():array();

			//Ensure POST is supported
			$owner = $this->_retrieveResource(true);
			$resource = $this->createResource($bodyArguments, $owner);
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

			$payload = $resource->deliverPayload();
			foreach($this->getCustomPayloadFields() as $customField => $customValue){
				$payload[$customField] = $customValue;
			}

			//Return created resource
      ResponseHandler::created($payload,$resource->getURI());
		}

		abstract protected function getInvalidQueryParameters($parameters);
		abstract protected function countResources($parameters, $owner);
		abstract protected function retrieveResources($start,$count,$parameters, $owner);
		abstract protected function createResource($data, $owner);
	}
?>
