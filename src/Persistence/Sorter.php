<?php
	namespace Wadapi\Persistence;

	use Wadapi\System\WadapiClass;
	use Wadapi\Utility\MessageUtility;
	use Wadapi\Logging\Logger;

	class Sorter extends WadapiClass{
		/** @Collection(type=@WadapiObject(class='Wadapi\Persistence\Criterion')) */
		protected $criteria;

		/*
		 * Adds a Criterion object to the criteria collection, converting non-string values
		 * to strings where applicable.
		 */
		public function addCriterion($field, $order){
			if(!is_string($field)){
				Logger::warning(MessageUtility::UNEXPECTED_ARGUMENT_WARNING, "Sorter criterion should have string fields, ".gettype($field)." given.");
				return;
			}

			if(!is_string($order)){
				Logger::warning(MessageUtility::UNEXPECTED_ARGUMENT_WARNING, "Sorter criterion should have string order, ".gettype($order)." given.");
				return;
			}

			$this->appendToCriteria(new Criterion($field, $order, array()));
		}

		/*
		 * Removes all criteria from the criteria collection
		 */
		public function clearCriteria(){
			$this->setCriteria(array());
		}
	}
?>
