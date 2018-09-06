<?php
	namespace Wadapi\Http;

	use Wadapi\System\WadapiClass;

	class Response extends WadapiClass{
		/** @Integer */
		protected $statusCode;

		/** @WadapiString */
		protected $reason;

		/** @Collection(type=@WadapiString) */
		protected $headers;

		/** @WadapiString */
		protected $contentType;

		/** @WadapiString */
		protected $contentCharset;

		/** @WadapiString */
		protected $contentLanguage;

		/** @WadapiString */
		protected $body;

		protected function setContentURI($contentLocation,$includeLocation=true){
			$this->insertToHeaders("Content-Location",$contentLocation);
			if($includeLocation){
				$this->insertToHeaders("Location",$contentLocation);
			}
		}

		protected function setState($modified,$eTag){
			$httpDate = gmdate("D, d M Y H:i:s",$modified)." GMT";
			$this->insertToHeaders("Last-Modified",$httpDate);
			$this->insertToHeaders("ETag",$eTag);

		}

		protected function setExpiryDate($date){
			$httpDate = gmdate("D, d M Y H:i:s",$date)." GMT";
			$this->insertToHeaders("Expires",$httpDate);
		}

		protected function send(){
			//Build Response
			$response = "";

			//Configure Request Line
			http_response_code($this->getStatusCode());

			//Build Headers
			header("Date: ".gmdate("D, d M Y H:i:s")." GMT");
			$headers = $this->getHeaders()?$this->getHeaders():array();
			foreach($headers as $header => $value){
				header("$header: $value");
			}

			//Build Request Body
			if($this->getBody()){
				header("Content-Type: {$this->getContentType()}".($this->getContentCharset()?";charset={$this->getContentCharset()}":""));
				header("Content-Language: {$this->getContentLanguage()}");
				header("Content-Length: ".strlen($this->getBody()));
			}

			echo $this->getBody();
		}
	}
?>
