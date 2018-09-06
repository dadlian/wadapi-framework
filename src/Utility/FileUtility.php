<?php
	namespace Wadapi\Utility;

	use Wadapi\Logging\Logger;

	class FileUtility{
		private static $includedClasses = array();

		public static function require_all($directory){
			if(!file_exists($directory)){
				return;
			}else if(is_dir($directory)){
				self::require_directory($directory);
			}else{
				self::require_file($directory);
			}
		}

		public static function backup_file($filepath){
			if(!file_exists($filepath)){
				Logger::warning(MessageUtility::RESOURCE_UNAVAILABLE_ERROR,"There was an attempt to backup a non-existant file: $filepath");
				return;
			}

			if(file_exists("$filepath.bk")){
				Logger::warning("","There was an attempt to backup a file for which a backup already exists: $filepath");
				return;
			}

			copy($filepath, "$filepath.bk");
		}

		public static function restore_file($filepath){
			if(!file_exists($filepath)){
				Logger::warning(MessageUtility::RESOURCE_UNAVAILABLE_ERROR,"There was an attempt to restore a non-existant file: $filepath");
				return;
			}

			if(!file_exists("$filepath.bk")){
				Logger::warning("","There was an attempt to restore a file that has no backup: $filepath");
				return;
			}

			copy("$filepath.bk", $filepath);
			unlink("$filepath.bk");
		}

		public static function require_directory($directoryString){
			$fileListing = self::index_directory($directoryString);
			foreach($fileListing as $class){
				self::require_class($class,$fileListing);
			}
		}

		private static function index_directory($directoryString){
			$directoryIndex = array();

			$directory = opendir($directoryString);
			while(($filename = readdir($directory)) !== FALSE) {
				$nextItem = $directoryString."/".$filename;

				if(is_file($nextItem) && (substr($nextItem, strlen($nextItem) - 3) == "inc" || substr($nextItem, strlen($nextItem) - 3) == "php")){
					$fileContents = file_get_contents($nextItem);
					preg_match("/class\s+(\w+)(?:\s+extends\s+(\w+))?/", $fileContents, $matches);

					$className = "";
					if(sizeof($matches) > 1){
						$className = $matches[1];
					}

					$parent = "";
					if(sizeof($matches) > 2){
						$parent = $matches[2];
					}

					$directoryIndex[$className] = array("filename"=>$nextItem,"parent"=>$parent);
				}else if (is_dir($nextItem) && substr($nextItem, strlen($nextItem) - 1) !== "." && substr($nextItem, strlen($nextItem) - 2) !== ".."){
					$directoryIndex = array_merge($directoryIndex,self::index_directory($nextItem));
				}
			}
			closedir($directory);

			return $directoryIndex;
		}

		private static function require_class($class,$fileListing){
			$filename = $class['filename'];
			$parent = $class['parent'];

			if(!file_exists($filename)){
				Logger::fatal_error(MessageUtility::RESOURCE_UNAVAILABLE_ERROR,"require_all() could not open file: $filename");
				return;
			}

			if(array_key_exists($parent,$fileListing) && !class_exists($parent)){
				self::require_class($fileListing[$parent],$fileListing);
			}

			require_once($filename);
		}
	}
?>
