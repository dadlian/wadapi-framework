<?php
	namespace Wadapi\Reflection;

	class WadapiReflectionAnnotation{
		const COLLECTION = "Wadapi\Reflection\Collection";
		const OBJECT = "Wadapi\Reflection\WadapiObject";
		const STRING = "Wadapi\Reflection\WadapiString";
		const BOOLEAN = "Wadapi\Reflection\Boolean";
		const INTEGER = "Wadapi\Reflection\Integer";
		const FLOAT = "Wadapi\Reflection\WadapiFloat";
		const MONETARY = "Wadapi\Reflection\Monetary";
		const URL = "Wadapi\Reflection\URL";
		const EMAIL = "Wadapi\Reflection\Email";
		const PHONE = "Wadapi\Reflection\Phone";
		const PASSWORD = "Wadapi\Reflection\Password";
		const FILE = "Wadapi\Reflection\File";
		const IMAGE = "Wadapi\Reflection\Image";
		const TEXT = "Wadapi\Reflection\Text";

		//The annotation's type
		private $type;

		//Stores whether the annotation is required or not
		private $required;

		//Stores whether the annotated property's value needs to be unique or not
		private $unique;

		//Stores whether the annotated property's value should be externally accessible
		private $hidden;

		//Annotation's default value (if applicable)
		private $default;

		//Annotation's class (if applicable)
		private $objectClass;

		//The annotation type to be contained in a collection
		private $containedType;

		//The annotation values array
		private $values;

		//The annotation max value
		private $max;

		//The annotation min value
		private $min;

		//The annotation pattern to match (for strings)
		private $pattern;

		//The annotation height (for images)
		private $height;

		//The annotation width (for images)
		private $width;

		//Contains the project path for easy reference
		private static $path;

		public function __construct($annotation){
			$annotationClass = null;
			if($annotation){
				$annotationClass = Mirror::reflectClass($annotation);
			}else{
				echo "A class property is annotated with a non-existant class or is not well-formed. ".
									"Please check your class annotations and try again.\n";
				return;
			}

			$this->type = $annotationClass;

			//Initialise annotation object class property
			$this->objectClass = null;
			if($this->isObject()){
				if($annotation->class && class_exists($annotation->class)){
					$this->objectClass = $annotation->class;
				}else{
					echo "The specified class '{$annotation->class}' for an Object annotation is not defined or does not exist. ".
										"Please ensure defined classes are used in annotations.\n";
					return;
				}
			}

			//Initialise annotation collection type property
			$this->containedType = null;
			if($this->isCollection()){
				if($annotation->type){
					$this->containedType = new WadapiReflectionAnnotation($annotation->type);
				}else{
					echo "The specified type for a Collection annotation is invalid or does not exist. ".
										"Please ensure valid annotations are used as collection types.\n";
					return;
				}
			}

			//Initialise annotation required property
			$this->required = false;
			if($annotation->required){
				if(is_bool($annotation->required)){
					$this->required = $annotation->required;
				}else{
					echo "An annotation's 'required' value is not a boolean, as required.\n";
					return;
				}
			}

			//Initialise annotation unique property
			$this->unique = false;
			if($annotation->unique){
				if(is_bool($annotation->unique)){
					$this->unique = $annotation->unique;
				}else{
					echo "An annotation's 'unique' value is not a boolean, as required.\n";
					return;
				}
			}

			//Initialise annotation hidden property
			$this->hidden = false;
			if($annotation->hidden){
				if(is_bool($annotation->hidden)){
					$this->hidden = $annotation->hidden;
				}else{
					echo "An annotation's 'hidden' value is not a boolean, as required.\n";
					return;
				}
			}

			//Initialise Annotation default property
			$this->default = null;
			if($this->isDefaulted() && ($annotation->default || is_bool($annotation->default))){
				if($this->isString() && !is_string($annotation->default) ||
				   $this->isUrl() && !filter_var((preg_match("/^https?:\/\//",$annotation->default)?"":"//").$annotation->default,
									FILTER_VALIDATE_URL) ||
				   $this->isEmail() && !filter_var($annotation->default,FILTER_VALIDATE_EMAIL) ||
				   $this->isPhone() && !preg_match("/\+?[0-9\s\-\)\(]+/",$annotation->default) ||
				   $this->isFile() && !file_exists(self::$path."/".$annotation->default) ||
				   $this->isInteger() && !is_int($annotation->default) ||
				   $this->isBoolean() && !is_bool($annotation->default) ||
				   ($this->isFloat() || $this->isMonetary()) && !is_numeric($annotation->default)){
					echo "An annotation's default value must match its declared type. ".
									"Default value is ".gettype($annotation->default).
									" but declared type is {$this->type->getName()}.\n";
					return;
				}

				$this->default = $annotation->default;
			}

			//Initialise annotation value array property
			$this->values = array();
			if($this->isValued() && $annotation->values){
				if(!is_array($annotation->values)){
					echo "An annotation's enumerated values must be given as an array, ".gettype($annotation->values)." given.\n";
					return;
				}

				foreach($annotation->values as $value){
					if($this->isString() && !is_string($value) ||
					   $this->isUrl() && !filter_var((preg_match("/^https?:\/\//",$value)?"":"//").$value,FILTER_VALIDATE_URL) ||
					   $this->isEmail() && !filter_var($value,FILTER_VALIDATE_EMAIL) ||
					   $this->isPhone() && !preg_match("/\+?[0-9\s\-\)\(]+/",$value) ||
					   $this->isFile() && !file_exists(self::$path."/".$value) ||
					   $this->isInteger() && !is_int($value) ||
					   $this->isBoolean() && !is_bool($value) ||
					   ($this->isFloat() || $this->isMonetary()) && !is_numeric($value)){
						echo "One or more of a {$this->type->getName()} annotation's enumerated values does not match its ".
								"declared type, ".gettype($value)." value given.\n";
						return;
					}
				}

				$this->values = $annotation->values;
			}

			//Initialise annotation min and max properties
			$this-> max = null;
			if($this->isRanged() && ($annotation->max || is_numeric($annotation->max))){
				if(!is_numeric($annotation->max)){
					echo "An annotation's max value must be numeric, ".gettype($annotation->max)." given.\n";
					return;
				}

				$this->max = $annotation->max;
			}

			$this-> min = null;
			if($this->isRanged() && ($annotation->min || is_numeric($annotation->min))){
				if(!is_numeric($annotation->min)){
					echo "An annotation's min value must be numeric, ".gettype($annotation->min)." given.\n";
					return;
				}

				$this->min = $annotation->min;
			}

			//Initialise annotation pattern property
			$this->pattern = null;
			if($this->isString() && $annotation->pattern){
				if(!is_string($annotation->pattern)){
					echo "An annotation's pattern value must be a string, ".gettype($annotation->pattern)." given.\n";
					return;
				}

				$this->pattern = $annotation->pattern;
			}

			//Initialise annotation width and height properties
			$this->height = null;
			if($this->isImage() && $annotation->height){
				if(!is_numeric($annotation->height)){
					echo "An image annotation's height must be numeric, ".gettype($annotation->height)." given.\n";
					return;
				}

				$this->height = $annotation->height;
			}

			$this->width = null;
			if($this->isImage() && $annotation->width){
				if(!is_numeric($annotation->width)){
					echo "An image annotation's width must be numeric, ".gettype($annotation->width)." given.\n";
					return;
				}

				$this->width = $annotation->width;
			}

			if(!self::$path){
				self::$path = PROJECT_PATH;
			}
		}

		public function getType(){
			return $this->type->getName();
		}

		public function isRequired(){
			return $this->required;
		}

		public function isUnique(){
			return $this->unique;
		}

		public function isHidden(){
			return $this->hidden;
		}

		public function getDefault(){
			if($this->default){
				return $this->default;
			}else if($this->isCollection()){
				return [];
			}else if($this->isObject()){
				return null;
			}else if($this->isBoolean()){
				return false;
			}else if($this->isNumeric()){
				return 0;
			}else{
				return "";
			}
		}

		public function getObjectClass(){
			return $this->objectClass;
		}

		public function getContainedType(){
			return $this->containedType;
		}

		public function getValues(){
			return $this->values;
		}

		public function getMax(){
			return $this->max;
		}

		public function getMin(){
			return $this->min;
		}

		public function getPattern(){
			if($this->pattern){
				return $this->pattern;
			}else{
				return ".*";
			}
		}

		public function getHeight(){
			return $this->height;
		}

		public function getWidth(){
			return $this->width;
		}

		public function isCollection(){
			return $this->getType() == self::COLLECTION;
		}

		public function isObject(){
			return $this->getType() == self::OBJECT;
		}

		public function isBoolean(){
			return $this->getType() == self::BOOLEAN;
		}

		public function isInteger(){
			return $this->getType() == self::INTEGER;
		}

		public function isFloat(){
			return $this->getType() == self::FLOAT;
		}

		public function isMonetary(){
			return $this->getType() == self::MONETARY;
		}

		public function isUrl(){
			return $this->getType() == self::URL;
		}

		public function isEmail(){
			return $this->getType() == self::EMAIL;
		}

		public function isPhone(){
			return $this->getType() == self::PHONE;
		}

		public function isPassword(){
			return $this->getType() == self::PASSWORD;
		}

		public function isFile(){
			return $this->type->descendsFrom(self::FILE);
		}

		public function isImage(){
			return $this->getType() == self::IMAGE;
		}

		public function isText(){
			return $this->getType() == self::TEXT;
		}

		public function isNumeric(){
			return ($this->isInteger() || $this->isFloat() || $this->isMonetary());
		}

		public function isString(){
			return $this->type->descendsFrom(self::STRING);
		}

		public function isDefaulted(){
			return $this->type->descendsFrom('Wadapi\Reflection\DefaultedAnnotation');
		}

		public function isValued(){
			return $this->type->descendsFrom('Wadapi\Reflection\ValuedAnnotation');
		}

		public function isRanged(){
			return $this->type->descendsFrom('Wadapi\Reflection\RangedAnnotation');
		}
	}
?>
