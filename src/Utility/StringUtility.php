<?php
	namespace Wadapi\Utility;

	use Wadapi\Logging\Logger;

	class StringUtility{
		public static function capitalise($string){
			return strtoupper(substr($string, 0, 1)) . substr($string, 1);
		}

		public static function decapitalise($string){
			return strtolower(substr($string, 0, 1)) . substr($string, 1);
		}

		public static function isCapitalised($string){
			return $string == self::capitalise($string);
		}

		public static function camelise($string, $capitalise=false){
			if(!is_string($string)){
				Logger::warning(MessageUtility::UNEXPECTED_ARGUMENT_WARNING, "camelise() can only convert strings, ".gettype($string)." given.");
				return;
			}

			$camelisedString = "";
			foreach(preg_split("/\s+/",$string) as $stringPart){
				$camelisedString .= self::capitalise($stringPart);
			}

			if(!$capitalise){
				$camelisedString = self::decapitalise($camelisedString);
			}

			return $camelisedString;
		}

		public static function decamelise($string, $capitalise=false){
			if(!is_string($string)){
				Logger::warning(MessageUtility::UNEXPECTED_ARGUMENT_WARNING, "decamelise() can only convert strings, ".gettype($string)." given.");
				return;
			}

			$decamelisedString = preg_replace("/([A-Z])/"," $1",$string);

			if($capitalise){
				$decamelisedString = self::capitalise($decamelisedString);
			}else{
				$decamelisedString = strtolower($decamelisedString);
			}

			return $decamelisedString;
		}
	}
?>
