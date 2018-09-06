<?php
	namespace Wadapi\Utility;

	class ArrayUtility{
		/*
		 * Removes all cells containing 'false' values from an array.
		 */
		static function array_compress($array){
			if(!is_array($array)){
				return $array;
			}

			$tempArray = array();
			foreach($array as $key => $element){
				if($element){
					if(is_int($key)){
						$tempArray[] = $element;
					}else{
						$tempArray[$key] = $element;
					}
				}
			}

			return $tempArray;
		}

		/*
		 * Removes all matching elements from an array taking into account whether they are objects, arrays or base types
		 */
		 static function array_remove($array, $removeValue){
			if(!is_array($array)){
				return $array;
			}

		 	$tempArray = array();

			foreach($array as $element){
			 	if(is_object($element) && is_object($removeValue)){
			 		if(!$element->equals($removeValue)){
			 			$tempArray[] = $element;
			 		}
			 	}else{
			 		if(!($element === $removeValue)){
			 			$tempArray[] = $element;
			 		}
		 		}
		 	}

		 	return $tempArray;
		}

		/*
		 * Checks whether array $needle is a subset of array $haystack
		 */
		static function is_subset($needle, $haystack){
			if(!is_array($haystack)){
				return false;
			}

			 if(!is_array($needle)){
				$needle = array($needle);
			 }

			foreach($needle as $needleElement){
				$found = false;

				foreach($haystack as $haystackElement){
					if(is_object($haystackElement) && is_object($needleElement)){
						$found = $found || $needleElement->equals($haystackElement);
					}else if(!is_object($haystackElement) && !is_object($needleElement)){
						$found = $found || ($needleElement == $haystackElement);
					}
				}

				if(!$found){
					return false;
				}
			}

			return true;
		}

		/*
		 * Flattens an arbitrarily nested array
		 */
		static function array_flatten($array=null){
			if(!is_array($array)){
				return array($array);
			}

			$flattenedArray = array();
			$keys = array_keys($array);
			for($x = 0; $x < count($array); $x++){
				if(is_array($array[$keys[$x]])){
					$flattenedArray = array_merge($flattenedArray,self::array_flatten($array[$keys[$x]]));
				}else{
					$flattenedArray[] = $array[$keys[$x]];
				}
			}

			return $flattenedArray;
		}
	}
?>
