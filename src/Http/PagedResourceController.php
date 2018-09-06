<?php
	namespace Wadapi\Http;
	
	abstract class PagedResourceController extends ResourceController{
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

			$count = $this->getRecordCount();

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

			//Get page URIs
			$payload['self'] = "{$this->getBase()}/".RequestHandler::getRequestURI()."?page=$page".(($records==$maxPageLength)?"":"&records=$records").$additionalQueryParameters;
			if($page > 1){
				$payload['prev'] = "{$this->getBase()}/".RequestHandler::getRequestURI()."?page=".($page-1).(($records==$maxPageLength)?"":"&records=$records").$additionalQueryParameters;
			}
			if($page < ceil($count/$records)){
				$payload['next'] = "{$this->getBase()}/".RequestHandler::getRequestURI()."?page=".($page+1).(($records==$maxPageLength)?"":"&records=$records").$additionalQueryParameters;
			}

			//Get total record count
			$payload['total'] = $count;

			foreach($this->getCustomPayloadFields() as $customField => $customValue){
				$payload[$customField] = $customValue;
			}

			//Assemble Entries
			$payload['entries'] = $this->buildPageEntries(($page-1)*$records,$records);

			ResponseHandler::retrieved($payload,$payload['self']);
		}

		protected function getCustomPayloadFields(){
			return array();
		}

		abstract protected function getInvalidQueryParameters();
		abstract protected function getRecordCount();
		abstract protected function buildPageEntries($start,$count);
	}
?>
