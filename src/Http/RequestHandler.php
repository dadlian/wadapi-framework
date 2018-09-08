<?php
	namespace Wadapi\Http;

	use Wadapi\System\Worker;
	use Wadapi\System\SettingsManager;
	use Wadapi\Routing\Endpoint;
	use Wadapi\Routing\EndpointsGateway;
	use Wadapi\Routing\URLPattern;
	use Wadapi\Authentication\APIToken;
	use Wadapi\Persistence\SQLGateway;
	use Wadapi\Persistence\Searcher;
	use Wadapi\Persistence\Criterion;
	use Wadapi\Utility\ArrayUtility;
	use Wadapi\Utility\StringUtility;

	class RequestHandler extends Worker{
		private static $request;
		private static $requestURI;
		private static $endpoint;
		private static $uriArguments;
		private static $queryParameters;
		private static $acceptables;
		private static $authenticatedToken;
		private static $isUtility;

		public static function isUtility(){
			return self::$isUtility;
		}

		public static function getHost(){
			if(!self::$request){
				self::initialise();
			}

			return self::$request->getHost();
		}

		public static function getRequestURI(){
			if(!self::$requestURI){
				if(!self::$request){
					self::initialise();
				}

				//Extract the desired endpoint based on the project URL Root
				$regex = ((array_key_exists('HTTPS',$_SERVER) && $_SERVER['HTTPS'])?"https:":"http:")."\/\/(www\.)?".preg_replace("/\//","\/",SettingsManager::getSetting("install","url"));
				$fullPath = urldecode(((array_key_exists('HTTPS',$_SERVER) && $_SERVER['HTTPS'])?"https://":"http://").RequestHandler::getHost().self::$request->getEndpoint());
				$requestURI = implode("/",ArrayUtility::array_compress(preg_split("/\//", preg_replace("/$regex/","",$fullPath))));
				self::$requestURI = implode("/",ArrayUtility::array_compress(preg_split("/\//",$requestURI)));
			}

			return self::$requestURI;
		}

		public static function getEndpoint(){
			if(!self::$endpoint){
				if(!self::$request){
					self::initialise();
				}

				$endpointsGateway = new EndpointsGateway();
				self::$endpoint = self::$endpoint?self::$endpoint:$endpointsGateway->findUnique('path',self::getRequestURI());
			}

			return self::$endpoint;
		}

		public static function getURIArguments(){
			if(!self::$uriArguments){
				if(!self::$request){
					self::initialise();
				}

				//Extract arguments from endpoint
				$endpoint = new URLPattern(self::getRequestURI());
				$endpointArguments = $endpoint->extractArguments(self::getEndpoint()->getPath());

				$i = 0;
				$uriArguments = array();
				foreach(self::getEndpoint()->getParameters() as $parameter){
					$uriArguments[(string)$parameter] = urldecode($endpointArguments[$i]);
					$i++;
				}

				self::$uriArguments = $uriArguments;
			}

			return self::$uriArguments;
		}

		public static function getQueryParameters(){
			if(!self::$queryParameters){
				if(!self::$request){
					self::initialise();
				}

				$queryParameters = array();
				$queryParts = preg_split("/&/",$_SERVER['QUERY_STRING']);
				foreach($queryParts as $queryPart){
					$keyValue = preg_split("/=/",$queryPart);
					if(sizeof($keyValue) > 1){
						$queryParameters[$keyValue[0]] = urldecode($keyValue[1]);
					}
				}
				self::$queryParameters = $queryParameters;
			}

			return self::$queryParameters;
		}

		public static function getQueryParameter($parameter){
			if(!self::$queryParameters){
				self::getQueryParameters();
			}

			return array_key_exists($parameter,self::$queryParameters)?self::$queryParameters[$parameter]:"";
		}

		public static function getMethod(){
			if(!self::$request){
				self::initialise();
			}

			return self::$request->getMethod();
		}

		public static function getHeader($header){
			if(!self::$request){
				self::initialise();
			}

			return self::$request->viewFromHeaders($header);
		}

		public static function changeHeader($header,$newValue){
			if(!self::$request){
				self::initialise();
			}

			return self::$request->insertToHeaders($header,$newValue);
		}

		public static function getAcceptable($header){
			if(!self::$request){
				self::initialise();
			}

			return self::$acceptables[$header];
		}

		public static function getArgument($key){
			if(!self::$request){
				self::initialise();
			}

			return self::$request->viewFromArguments($key);
		}

		public static function getAuthorisation(){
			if(!self::$request){
				self::initialise();
			}

			$base64 = "(?:[A-Za-z0-9\+\/]{4})*(?:[A-Za-z0-9\+\/]{2}==|[A-Za-z0-9\+\/]{3}=)?";
			if(self::$request->viewFromHeaders("Authorization") && preg_match("/^Basic $base64$/",self::$request->viewFromHeaders("Authorization"))){
				$authorisation = array("key"=>"","secret"=>"");

				$authorisationParts = preg_split("/ /",self::$request->viewFromHeaders("Authorization"));
				$authorisationTokens = preg_split("/:/",base64_decode($authorisationParts[1]));

				if(sizeof($authorisationTokens) > 0){
					$authorisation["key"] = $authorisationTokens[0];
				}

				if(sizeof($authorisationTokens) > 1){
					$authorisation["secret"] = $authorisationTokens[1];
				}

				return $authorisation;
			}else{
				return false;
			}
		}

		public static function getAuthenticatedToken(){
			$authorisation = self::getAuthorisation();
			if(!$authorisation){
				return new APIToken();
			}

			if(!self::$authenticatedToken){
				$sqlGateway = new SQLGateway();
				$searcher = new Searcher();
				$searcher->addCriterion("accessKey",Criterion::EQUAL,md5($authorisation["key"]));
				self::$authenticatedToken = $sqlGateway->findUnique("Wadapi\Authentication\APIToken",$searcher);
			}

			return self::$authenticatedToken;
		}

		public static function checksConsistency(){
			return self::$request->viewFromHeaders("If-Unmodified-Since") || self::$request->viewFromHeaders("If-Match");
		}

		public static function getConsistencyTags(){
			$modifiedDate = date("U",strtotime(self::$request->viewFromHeaders("If-Unmodified-Since")));
			return array("ifUnmodifiedSince"=>$modifiedDate,"ifMatch"=>self::$request->viewFromHeaders("If-Match"));
		}

		public static function getSuggestedURI(){
			return self::$request->viewFromHeaders("Slug")?self::$request->viewFromHeaders("Slug"):"";
		}

		public static function getContent(){
			if(!self::$request){
				self::initialise();
			}

			$contentType = self::getHeader("Content-Type");
			if(in_array("application/json",preg_split("/;/",$contentType))){
				$content = json_decode(self::$request->getBody(),true);
				if(json_last_error() == JSON_ERROR_NONE){
					return $content?$content:array();
				}else{
					ResponseHandler::bad("Request arguments must be supplied using valid JSON.");
				}
			}else if(preg_match("/^image\//",$contentType)){

				$image = @imagecreatefromstring(self::$request->getBody());
				if($image){
					$tmpFile = PROJECT_PATH."/temp-image-validate-".time();
					fwrite(fopen($tmpFile,"w"),self::$request->getBody());
					$mimeType = finfo_file(finfo_open(FILEINFO_MIME_TYPE),$tmpFile);
					unlink($tmpFile);

					if($contentType == $mimeType){
						return self::$request->getBody();
					}else{
						ResponseHandler::bad("Request body contains invalid data for Content-Type: $contentType.");
					}
				}else{
					ResponseHandler::bad("Request body contains invalid data for Content-Type: $contentType.");
				}
			}else{
				return self::$request->getBody();
			}
		}

		private static function initialise(){
			self::$isUtility = false;

			//Handle Native Wadapi Calls. These override calls to the same endpoint in the userspace, if any.
			$requestURI = self::extractRequestURI();
			switch($requestURI){
				case "wadapi/setup":
					self::$endpoint = new Endpoint("Wadapi Setup",$requestURI,"Wadapi\System\WadapiSetup",array(),array(),array(),array(),array(),array());
					self::$isUtility = true;
					break;
				case "wadapi/rebuild":
					self::$endpoint = new Endpoint("Wadapi Database Rebuilder",$requestURI,"Wadapi\System\WadapiRebuildDatabase",array(),array(),array(),array(),array(),array());
					self::$isUtility = true;
					break;
				default:
					self::$endpoint = null;
			}

			//Parse query string for argument keys and values
			$arguments = array();
			if($_SERVER['QUERY_STRING']){
				foreach(preg_split("/\&/",urldecode($_SERVER['QUERY_STRING'])) as $queryPart){
					$argumentParts = preg_split("/=/",$queryPart);
					$arguments[$argumentParts[0]] = (sizeof($argumentParts)>1)?$argumentParts[1]:"";
				}
			}

			//Parse Accept Headers
			self::$acceptables = array();
			$unsupportedFormats = self::parseAcceptHeader("Accept","formats","Content-Type");
			$unsupportedCharsets = self::parseAcceptHeader("Accept-Charset","charsets","Content-Charset");
			$unsupportedLanguages = self::parseAcceptHeader("Accept-Language","languages","Content-Language");

			if($unsupportedFormats || $unsupportedCharsets || $unsupportedLanguages){
				$unsupported = array();

				if($unsupportedFormats){
					$unsupported[] = "formats: ".implode(", ",$unsupportedFormats);
				}

				if($unsupportedCharsets){
					$unsupported[] = "charsets: ".implode(", ",$unsupportedCharsets);
				}

				if($unsupportedLanguages){
					$unsupported[] = "languages: ".implode(", ",$unsupportedLanguages);
				}

				ResponseHandler::unacceptable("The requested end-point does not support ".implode(", ",$unsupported).".");
			}

			//Set request body to raw input value
			$headers = self::getAllHeaders();
			$body = file_get_contents("php://input");

			$contentLength = array_key_exists("Content-Length",$headers)?intval($headers['Content-Length']):0;
			ResponseHandler::changeContentType(self::$acceptables['Content-Type'][0]);
			ResponseHandler::changeContentCharset(self::$acceptables['Content-Charset'][0]);
			ResponseHandler::changeContentLanguage(self::$acceptables['Content-Language'][0]);
			self::$request = new Request($_SERVER['HTTP_HOST'],preg_replace("/\?".preg_replace("/\//","\/",preg_quote($_SERVER['QUERY_STRING']))."/","",$_SERVER['REQUEST_URI']),
								$_SERVER['REQUEST_METHOD'],$arguments,self::getAllHeaders(),
								self::$acceptables['Content-Type'][0],$contentLength,$body);
		}

		private static function parseAcceptHeader($header,$setting,$type){
			$endpointsGateway = new EndpointsGateway();
			$requestURI = self::extractRequestURI();

			//Overide Global Acceptables Setting with Endpoint Settings if they exist
			$endpoint = self::$endpoint?self::$endpoint:$endpointsGateway->findUnique('path',$requestURI);
			if(!$endpoint){
				ResponseHandler::missing("The requested endpoint /$requestURI does not exist on this server.");
			}

			$getter = "get".StringUtility::capitalise($setting);
			$endpointAcceptables = $endpoint->$getter();
			$supportedValues = array_values($endpointAcceptables?$endpointAcceptables:explode(",",SettingsManager::getSetting("api",$setting)));
			$defaultValue = $supportedValues?$supportedValues[0]:"*";

			if($setting == "languages"){
				$supportedValues = ["*"];
			}

			$headers = self::getAllHeaders();
			$acceptHeader = array_key_exists($header,$headers)?$headers[$header]:$defaultValue;
			if(!preg_match("/([\w\/\+\*(\*\/\*)]+(;q\=[0-9\.]+)?,?)+/",$acceptHeader)){
				ResponseHandler::bad("The $header Header was malformed.");
			}

			$acceptedValues = array();
			$unsupportedValues = array();
			foreach(explode(",",$acceptHeader) as $value){
				$valueParts = explode(";",$value);

				$priority = 1.0;
				if(sizeof($valueParts) > 1){
					$priorityParts = explode("=",$valueParts[1]);
					$priority = floatval($priorityParts[1]);
				}

				$requestedValue = strtolower($valueParts[0]);
				if(in_array("*",$supportedValues) || in_array($requestedValue,$supportedValues)){
					$acceptedValues[$requestedValue] = $priority;
				}else if(in_array($requestedValue,["*","*/*"]) && !in_array($defaultValue,$acceptedValues)){
					$acceptedValues[$defaultValue] = $priority;
				}else{
					$unsupportedValues[] = $requestedValue;
				}
			}

			arsort($acceptedValues);
			self::$acceptables[$type] = array_keys($acceptedValues);

			if(!$acceptedValues){
				return $unsupportedValues;
			}else{
				return array();
			}
		}

		private static function extractRequestURI(){
			//Load URL Root from settings
			$url = preg_replace("/\//","\/",SettingsManager::getSetting("install","url"));

			//Remove URL Root from Request URI
			$requestURI = preg_replace("/^$url/","",$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']);

			//Remove Query String from Request URI
			$requestURI = preg_replace("/\?".preg_replace("/\//","\/",preg_quote($_SERVER['QUERY_STRING']))."/","",$requestURI);

			//Remove leading slash from Request URI
			$requestURI = preg_replace("/^\//","",$requestURI);

			return $requestURI;
		}

		private static function getAllHeaders(){
			$headers = [];
			foreach ($_SERVER as $name => $value) {
				if (substr($name, 0, 5) == 'HTTP_') {
					$headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
				}
			}

			return $headers;
		}
	}
?>
