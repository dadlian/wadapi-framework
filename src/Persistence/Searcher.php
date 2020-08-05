<?php
	namespace Wadapi\Persistence;

	use Wadapi\System\WadapiClass;
	use Wadapi\Reflection\Mirror;
	use Wadapi\Utility\MessageUtility;
	use Wadapi\Logging\Logger;

	class Searcher extends WadapiClass{
		/** @Collection(type=@WadapiObject(class='Wadapi\Persistence\Criterion')) */
		protected $criteria;

		/*
		 * Adds a Criterion object to the criteria collection, converting non-string values
		 * to strings where applicable.
		 */
		public function addCriterion($field, $condition, $value){
			if(!is_string($field)){
				Logger::warning(MessageUtility::UNEXPECTED_ARGUMENT_WARNING, "Searcher criterion should have string fields, ".gettype($field)." given.");
				return;
			}

			if(!is_string($condition)){
				Logger::warning(MessageUtility::UNEXPECTED_ARGUMENT_WARNING, "Searcher criterion should have string conditions, ".gettype($condition)." given.");
				return;
			}

			if(is_array($value)){
				$newValue = array();
				foreach($value as $element){
					$newValue[] = $this->validate($element);
				}
				
				$value = $newValue;
			}else if(!is_string($value)){
				$value = $this->validate($value);

				if(is_null($value)){
					if($condition == Criterion::EQUAL){
						$condition = Criterion::IS;
					}else if($condition == Criterion::NOT_EQUAL){
						$condition = Criterion::IS_NOT;
					}
				}
			}

			if(!is_array($value)){
				$value = array($value);
			}

			$this->appendToCriteria(new Criterion($field, $condition, $value));
		}

		/*
		 * Removes all criteria from the criteria collection
		 */
		public function clearCriteria(){
			$this->setCriteria(array());
		}

		//Validate that the given argument is string compatible
		private function validate($value){
			$class = null;
			if(is_object($value)){
				$class = Mirror::reflectClass($value);
			}

			if($class && $class->descendsFrom('Wadapi\Persistence\PersistentClass')){
				$value = $value->getId();
			}else if($class || is_array($value)){
				$type = gettype($value);
				if($class){
					$type = "non-persistent $type";
				}

				Logger::warning(MessageUtility::UNEXPECTED_ARGUMENT_WARNING, "Searcher values must be string compatible, $type given.");
				return;
			}else if(!is_null($value)){
				$value = strval($value);
			}

			return $value;
		}
	}
?>
