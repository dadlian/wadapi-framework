<?php
	namespace Wadapi\Persistence;

	use Wadapi\System\Worker;
	use Wadapi\Reflection\Mirror;
	use Wadapi\Utility\MessageUtility;
	use Wadapi\Logging\Logger;

	/*
	 * The QuarterMaster handles the storage and retrievel of objects from the system's
	 * cache. This prevents multiple copies of the same object from circulating in
	 * memory, and may decrease load times in some instances.
	 */
	class QuarterMaster extends Worker{
		/*
		 * Object cache stores loaded object in memory to avoid in memory changes being
		 * overwritten. There is a single global cache for the current session
		 */
		private static $cache = array();

		/*
		 * Caches the argument object in the global cache, provided it is a descendant
		 * of PersistentClass
		 */
		public static function store($object){
			if(!is_object($object)){
				Logger::warning(MessageUtility::CACHE_LOAD_WARNING, "A primitive value of type ".gettype($object)." cannot be cached.");
				return false;
			}

			//Check that the specified $object is an object of PersistentClass
			$objectClass = Mirror::reflectClass($object);
			if(!$objectClass->descendsFrom("PersistentClass")){
				Logger::warning(MessageUtility::CACHE_LOAD_WARNING, "Object of class ".get_class($object)." is not a descendant ".
								 "of PersistentClass and cannot be stored in cache.");
				return false;
			}

			self::$cache[$object->getId()] = $object;
		}

		/*
		 * Returns the instance of the specified class with the specified id, if it is currently cached. Null otherwise.
		 */
		protected static function release($id){
			if(!is_string($id) && !is_int($id)){
				Logger::warning(MessageUtility::UNEXPECTED_ARGUMENT_WARNING, "QuarterMaster release expects string argument, ".gettype($id)." given.");
				return;
			}

			if(array_key_exists($id, self::$cache)){
				return self::$cache[$id];
			}else{
				return null;
			}
		}

		/*
		 * Returns whether or not an object with the given ID is cached.
		 */
		protected static function isCached($id){
			if(!is_string($id) && !is_int($id)){
				Logger::warning(MessageUtility::UNEXPECTED_ARGUMENT_WARNING, "QuarterMaster release expects string argument, ".gettype($id)." given.");
				return;
			}

			return array_key_exists($id, self::$cache);
		}

		/*
		 * Clears either the entire object cache if no arguments are specified, or the object associated with the given ID, if it exists
		 */
		protected static function decommission($id = ""){
			if(!is_string($id) && !is_int($id)){
				Logger::warning(MessageUtility::UNEXPECTED_ARGUMENT_WARNING, "QuarterMaster decommission expects string argument, ".gettype($id)." given.");
				return;
			}

			if(!$id){
				self::$cache = array();
			}else if(array_key_exists($id, self::$cache)){
				unset(self::$cache[$id]);
			}
		}
	}
?>
