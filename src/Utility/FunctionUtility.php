<?php
	namespace Wadapi\Utility;

	class FunctionUtility{
		public static function get_caller($level = 2){
			$stackTrace = debug_backtrace();
			if(in_array('file', array_keys($stackTrace[$level]))){
				$pathParts = preg_split('/\//', $stackTrace[$level]['file']);
				$fileParts = preg_split('/\./',$pathParts[sizeof($pathParts)-1]);
				return $fileParts[0];
			}else{
				return "";
			}
		}
	}
?>
