<?php
	namespace Wadapi\Persistence;

	use Wadapi\System\WadapiClass;
	
	class Criterion extends WadapiClass{
		const EQUAL = "=";
		const NOT_EQUAL = "<>";
		const GREATER_THAN = ">";
		const LESS_THAN = "<";
		const GREATER_THAN_EQUAL = ">=";
		const LESS_THAN_EQUAL = "<=";
		const INCLUDES = "IN";
		const EXCLUDES = "NOT IN";
		const DESCENDING = "DESC";
		const ASCENDING = "ASC";
		const RANDOM = "RAND()";
		const IS = "IS";
		const IS_NOT = "IS NOT";
		const LIKE = "LIKE";
		const NOT_LIKE = "NOT LIKE";
		const REGEXP = "REGEXP";

		/** @WadapiString(required=true) */
		protected $field;

		/** @WadapiString(required=true) */
		protected $condition;

		/** @Collection(type=@WadapiString,required=true) */
		protected $values;

		/*
		 * Indicates whether the queried comparator is applicable to primitive values or not
		 */
		public static function isPrimitiveComparator($comparator){
			return in_array($comparator, array(self::EQUAL,self::NOT_EQUAL,self::IS,self::IS_NOT,self::LESS_THAN,
							self::GREATER_THAN,self::LESS_THAN_EQUAL,self::GREATER_THAN_EQUAL,
							self::LIKE,self::NOT_LIKE,self::REGEXP));
		}

		/*
		 * Indicates whether the queried comparator is applicable to object values or not
		 */
		public static function isObjectComparator($comparator){
			return in_array($comparator, array(self::EQUAL,self::NOT_EQUAL,self::IS,self::IS_NOT));
		}

		/*
		 * Indicates whether the queried comparator is applicable to list values or not
		 */
		public static function isListComparator($comparator){
			return in_array($comparator, array(self::INCLUDES,self::EXCLUDES));
		}
	}
?>
