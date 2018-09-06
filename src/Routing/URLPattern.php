<?php
	namespace Wadapi\Routing;

	use Wadapi\System\WadapiClass;
	use Wadapi\Utility\ArrayUtility;
	use Wadapi\Utility\MessageUtility;
	use Wadapi\Logging\Logger;

	class URLPattern extends WadapiClass{
		/*
		 * Symbol used to represent url wildcards. This symbol can match any path element
		 */
		const WILDCARD_TOKEN = '*';

		/*
		 * Symbol used to represent a url argument. This symbol identifies which URL elements are action arguments.
		 */
		const ARGUMENT_TOKEN = '@';

		/*
		 * Argument place holder for regex escaping
		 */
		const WILDCARD_PLACE_HOLDER = '603820759434780';

		/*
		 * Argument place holder for regex escaping
		 */
		const ARGUMENT_PLACE_HOLDER = '603820759434781';

		/*
		 * The string representing this URL
		 */
		/** @URL(required=true) */
		protected $urlString;

		/*
		 * An array of the URL elements
		 */
		private $urlElements;

		public function __construct($urlString){
			parent::__construct($urlString);

			$this->urlElements = preg_split('/\//', $this->getUrlString());
			if($this->getUrlString()){
				$this->urlElements = ArrayUtility::array_compress($this->urlElements);
			}
		}

		/*
		 * Returns true if this URL matches the $pattern. False otherwise.
		 */
		public function match($pattern){
			if(!is_string($pattern)){
				Logger::warning(MessageUtility::UNEXPECTED_ARGUMENT_WARNING, "URL expects presented pattern to be a string, ".gettype($pattern)." given.");
				return false;
			}

			if(!$pattern){
				$patternElements = array("");
			}else{
				$patternElements = ArrayUtility::array_compress(preg_split('/\//', $pattern));
			}

			if(sizeof($this->urlElements) != sizeof($patternElements)){
				return false;
			}

			for($i = 0; $i < sizeof($this->urlElements); $i++){
				if(!preg_match($this->convertToRegex($patternElements[$i]), $this->urlElements[$i])){
					return false;
				}
			}

			return true;
		}

		/*
		 * Returns an array of URL elements identified as arguments based on the specified argument positions in $patternURL
		 */
		public function extractArguments($pattern){
			$argumentArray = array();

			if(!$this->match($pattern)){
				return $argumentArray;
			}

			$patternElements = ArrayUtility::array_compress(preg_split('/\//', $pattern));

			for($i = 0; $i < sizeof($patternElements); $i++){
				preg_match($this->convertToRegex($patternElements[$i]), $this->urlElements[$i], $matches);
				for($j = 1; $j < sizeof($matches); $j++){
					$argumentArray[] = $matches[$j];
				}
			}

			return $argumentArray;
		}

		public function __toString(){
			return $this->getUrlString();
		}

		//Prepares a regular expression for URL matching
		private function convertToRegex($pattern){
			//Replace arguments and wildcards
			$pattern = str_replace(self::WILDCARD_TOKEN, self::WILDCARD_PLACE_HOLDER, $pattern);
			$pattern = preg_quote(str_replace(self::ARGUMENT_TOKEN, self::ARGUMENT_PLACE_HOLDER, $pattern));

			$regex = str_replace(self::WILDCARD_PLACE_HOLDER, ".+", str_replace(self::ARGUMENT_PLACE_HOLDER, "(.+)",$pattern));
			return "/^$regex$/";
		}
	}
?>
