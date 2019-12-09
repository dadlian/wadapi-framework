<?php
	namespace Wadapi\Http;

	use Wadapi\System\Worker;
	use Wadapi\System\Janitor;
	use Wadapi\System\SettingsManager;

	class ResponseHandler extends Worker{
		private static $response;

		protected static function changeContentType($contentType){
			if(!self::$response){
				self::initialise();
			}

			self::$response->setContentType($contentType);
		}

		protected static function changeContentLanguage($contentLanguage){
			if(!self::$response){
				self::initialise();
			}

			self::$response->setContentLanguage($contentLanguage);
		}

		protected static function changeContentCharset($contentCharset){
			if(!self::$response){
				self::initialise();
			}

			self::$response->setContentCharset($contentCharset);
		}

		protected static function addExpiry($expiry){
			self::$response->setExpiryDate($expiry);
		}

		private static function initialise(){
			self::$response = new Response();
		}

		/*------------------------------- SUCCESS FUNCTIONs -------------------------------*/
		protected static function created($payload,$location,$lastModified="",$eTag=""){
			self::$response->setContentURI($location,$location);

			if($lastModified){
				self::$response->setState($lastModified,$eTag);
			}
			
			self::sendSuccess(201,$payload);
		}

		protected static function retrieved($payload,$location,$lastModified="",$eTag=""){
			self::$response->setContentURI($location);

			if($lastModified){
				self::$response->setState($lastModified,$eTag);
			}

			self::sendSuccess(200,$payload);
		}

		protected static function modified($payload,$location,$lastModified="",$eTag=""){
			self::$response->setContentURI($location);

			if($lastModified){
				self::$response->setState($lastModified,$eTag);
			}

			self::sendSuccess(200,$payload);
		}

		protected static function deleted($message){
			self::sendSuccess(200,array("message"=>$message));
		}

		private static function sendSuccess($status, $payload){
			if(!self::$response){
				self::initialise();
			}

			Janitor::cleanup(true);
			self::$response->setStatusCode($status);
			self::send($payload);
		}

		/*------------------------------- ERROR FUNCTIONs -------------------------------*/
		protected static function bad($messages){
			self::sendError(400,$messages);
		}

		protected static function unauthorised($messages){
			self::$response->insertToHeaders("WWW-Authenticate","Basic");
			self::sendError(401,$messages);
		}

		protected static function forbidden($messages){
			self::sendError(403,$messages);
		}

		protected static function missing($messages){
			self::sendError(404,$messages);
		}

		protected static function unsupported($messages){
			self::sendError(405,$messages);
		}

		protected static function unacceptable($messages){
			//Set Default Content Metadata
			$supportedFormats = explode(",",SettingsManager::getSetting("api","formats"));
			$defaultFormat = $supportedFormats?$supportedFormats[0]:"";
			ResponseHandler::changeContentType($defaultFormat);

			$supportedCharsets = explode(",",SettingsManager::getSetting("api","charsets"));
			$defaultCharset = $supportedCharsets?$supportedCharsets[0]:"";
			ResponseHandler::changeContentCharset($defaultCharset);

			$supportedLanguages = explode(",",SettingsManager::getSetting("api","languages"));
			$defaultLanguage = $supportedLanguages?$supportedLanguages[0]:"";
			ResponseHandler::changeContentLanguage($defaultLanguage);

			self::sendError(406,$messages);
		}

		protected static function conflict($messages){
			self::sendError(409,$messages);
		}

		protected static function gone($messages){
			self::sendError(410,$messages);
		}

		protected static function precondition($messages){
			self::sendError(412,$messages);
		}

		protected static function error($messages){
			self::sendError(500,$messages);
		}

		private static function sendError($status, $messages){
			if(!self::$response){
				self::initialise();
			}

			self::changeContentType("application/json");
			self::$response->setStatusCode($status);
			self::send(array("message"=>$messages));
			exit;
		}


		private static function send($payload){
			if(!self::$response){
				self::initialise();
			}

			if(self::$response->getContentType() == "application/json"){
				json_encode($payload,JSON_UNESCAPED_SLASHES);
				if(json_last_error()){
					echo json_last_error_msg();
					ResponseHandler::error(array("The server was unable to encode the requested data."));
				}
				self::$response->setBody(json_encode($payload,JSON_UNESCAPED_SLASHES));
			}else{
				self::$response->setBody($payload);
			}

			self::$response->send();
		}
	}
?>
