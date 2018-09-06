<?php
	namespace Wadapi\Http;

	use Wadapi\System\WadapiClass;
	use Wadapi\System\Detective;
	use Wadapi\Utility\TestUtility;
	use Wadapi\Logging\Logger;

	class Request extends WadapiClass{
		/** @URL(required=true) */
		protected $host;

		/** @WadapiString(default="") */
		protected $endpoint;

		/** @WadapiString(values={'GET','HEAD','POST','PUT','DELETE','OPTIONS','TRACE','CONNECT'}, default='GET') */
		protected $method;

		/** @Collection(type=@WadapiString) */
		protected $arguments;

		/** @Collection(type=@WadapiString) */
		protected $headers;

		/** @WadapiString(default='text/plain') */
		protected $contentType;

		/** @Integer */
		protected $contentLength;

		/** @WadapiString */
		protected $body;

		/** @WadapiString(default='tcp') */
		protected $transport;

		/** @Integer(default=80) */
		protected $port;

		protected function addPreferedContentType($mimeType,$priority=1.0){
			$this->addPreference("Accept",$mimeType,$priority);
		}

		protected function addPreferedCharset($mimeType,$priority=1.0){
			$this->addPreference("Accept-Charset",$mimeType,$priority);
		}

		protected function addPreferedEncoding($mimeType,$priority=1.0){
			$this->addPreference("Accept-Encoding",$mimeType,$priority);
		}

		protected function addPreferedLanguage($mimeType,$priority=1.0){
			$this->addPreference("Accept-Language",$mimeType,$priority);
		}

		protected function authorise($key,$secret){
			$this->insertToHeaders("Authorization","Basic ".base64_encode("$key:$secret"));
		}

		protected function assureCurrency($date,$eTag){
			$httpDate = gmdate("D, d M Y H:i:s",strtotime($date))." GMT";
			$this->insertToHeaders("If-Modified-Since",$httpDate);
			$this->insertToHeaders("If-None-Match",$eTag);
		}

		protected function assureConsistency($date=null,$eTag=null){
			if(!$date || !$eTag){
				$response = $this->get();
				$date = $response->viewFromHeaders("Last-Modified");
				$eTag = $response->viewFromHeaders("ETag");
			}

			$httpDate = gmdate("D, d M Y H:i:s",strtotime($date))." GMT";
			$this->insertToHeaders("If-Unmodified-Since",$httpDate);
			$this->insertToHeaders("If-Match",$eTag);
		}

		protected function suggestURI($suggestion){
			$this->insertToHeaders("Slug",$suggestion);
		}

		protected function getFullURI(){
			$protocol = ($this->getTransport()=="ssl")?"https":"http";

			$queryString = "";
			if($this->getArguments()){
				$queryString = array();
				foreach($this->getArguments() as $key=>$value){
					$queryString[] = "$key=".urlencode($value);
				}
				$queryString = "?".implode("&",$queryString);
			}

			return "$protocol://{$this->getHost()}{$this->getEndpoint()}$queryString";
		}

		protected function get(){
			$this->setMethod("GET");
			return $this->send();
		}

		protected function post(){
			$this->setMethod("POST");
			return $this->send();
		}

		protected function put(){
			$this->setMethod("PUT");
			return $this->send();
		}

		protected function delete(){
			$this->setMethod("DELETE");
			return $this->send();
		}

		protected function send(){
			//Establish a Connection to the Remote Host
			$destination = "{$this->getTransport()}://{$this->getHost()}:{$this->getPort()}";
			$remoteConnection = stream_socket_client($destination,$errorNumber,$errorString);

			if(!$remoteConnection){
				Logger::fatal_error("There was a problem establishing a remote connection.","$errorNumber: $errorString");
			}

			Detective::investigate("{$this->getMethod()} {$this->getFullURI()}",false);

			//Build Request
			$request = "";

			//Build the URL query string
			$queryString = "";
			if($this->getArguments()){
				$queryString = array();
				foreach($this->getArguments() as $key=>$value){
					$queryString[] = "$key=".urlencode($value);
				}
				$queryString = "?".implode("&",$queryString);
			}

			//Build Request Line
			$singleSlashEndpoint = preg_replace("/\/+/","/","/{$this->getEndpoint()}");
			$request .= "{$this->getMethod()} $singleSlashEndpoint$queryString HTTP/1.1\r\n";

			//Build Headers
			$request .= "Host: {$this->getHost()}\r\n";
			$request .= "Date: ".gmdate("D, d M Y H:i:s")." GMT\r\n";
			foreach($this->getHeaders() as $header => $value){
				$request .= "$header: $value\r\n";
			}

			//Build Request Body
			if(ctype_print($this->body)){
				$this->setContentLength(strlen($this->body));
			}

			if($this->getBody()){
				$request .= "Content-Type: {$this->getContentType()}\r\n";
				$request .= "Content-Length: ".$this->getContentLength()."\r\n";
			}

			$request .= "Connection: close\r\n\r\n";
			$request .= $this->getBody();

			if(TestUtility::is_test_run()){
				echo "\n\n============================================================================================================================\n";
				echo $request;
				echo "\n============================================================================================================================\n\n";
			}

			$response = new Response();
			if($remoteConnection){
				//Send Request to server
				fwrite($remoteConnection,$request);

				//Parse Response
				$responseString = "";
				while(!feof($remoteConnection)){
					$responseString .= fgets($remoteConnection, 1024);
				}

				if(TestUtility::is_test_run()){
					echo "\n\n============================================================================================================================\n";
					echo $responseString;
					echo "\n============================================================================================================================\n\n";
				}

				preg_match("/^(.*)\r\n((?:.*\r\n)*)\r\n((?:.*\n?)*)/",$responseString,$matches);
				if($matches && preg_match("/^HTTP\/1\.[0-1] [0-9]{3} [A-Za-z\s]+$/",$matches[1])){
					$statusParts = preg_split("/\s/",$matches[1]);
					$response->setStatusCode(intval($statusParts[1]));
					$response->setReason(implode(" ",array_slice($statusParts,2,sizeof($statusParts))));

					foreach(preg_split("/\r\n/",trim($matches[2])) as $header){
						$headerParts = preg_split("/:\s/",$header);
						if(sizeof($headerParts) > 1){
							$response->insertToHeaders($headerParts[0],trim($headerParts[1]));
						}
					}
					$response->setBody($matches[3]);
				}else{
					Logger::warning("An HTTP Response was invalid.","Invalid Response: $responseString");
				}

				//Close connection
				fclose($remoteConnection);
			}

			Detective::closeCase("{$this->getMethod()} {$this->getFullURI()}",false);
			return $response;
		}

		private function addPreference($header,$preference,$priority){
			$contentTypes = $this->viewFromHeaders($header);
			if(!$contentTypes){
				$contentTypes = array();
			}else{
				$contentTypes = explode(",",$contentTypes);
			}

			if(!$priority){
				$priority = 1.0;
			}

			$priority = min(max($priority,0.0),1.0);
			$contentTypes[] = sprintf("$preference;q=%.1f",$priority);
			$this->insertToHeaders($header,implode(",",$contentTypes));
		}
	}
?>
