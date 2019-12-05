<?php
	namespace Wadapi\Http;

	use Wadapi\Persistence\PersistentClass;
	use Wadapi\Persistence\SQLGateway;
	use Wadapi\Persistence\Searcher;
	use Wadapi\Persistence\Criterion;
	use Wadapi\Reflection\Mirror;
	use Wadapi\System\SettingsManager;
	use Wadapi\Utility\StringUtility;

	abstract class Resource extends PersistentClass{
		private $_buildErrors;

		abstract public static function getURITemplate();

		public function __construct(){
			$arguments = func_get_args();
			call_user_func_array(array('parent','__construct'), $arguments);

			$this->_buildErrors = [
				"required"=>array(),
				"invalid"=>array(),
				"conflicting"=>array()
			];
		}

		protected function build($data){
			foreach(Mirror::reflectClass($this)->getProperties(false) as $property){
				$propertyName = $property->getName();
      	$value = array_key_exists($propertyName,$data)?$data[$propertyName]:$property->getAnnotation()->getDefault();
				$setter = "set".StringUtility::capitalise($propertyName);

				//Load potential conflicting resource for uniqueness search
				$sqlGateway = new SQLGateway();
				$searcher = new Searcher();
				$searcher->addCriterion($propertyName,Criterion::EQUAL,$value);
				$conflictingResource = $sqlGateway->findUnique(get_class($this),$searcher);

	      if($property->getAnnotation()->isRequired() && (!array_key_exists($propertyName,$data) || (!$data[$propertyName] && !is_numeric($data[$propertyName])))){
					$this->_buildErrors["required"][] = $propertyName;
				}else if(!$property->isValidValue($value)){
					$this->_buildErrors["invalid"][] = $propertyName;
	      }else if($property->getAnnotation()->isUnique() && $conflictingResource && $conflictingResource->getId() !== $this->getId()){
					$this->_buildErrors["conflicting"][] = $propertyName;
				}else{
	        $this->$setter($value);
	      }
			}
		}

		protected function deliverPayload(){
			$payload = [
				"self"=>$this->getURI()
			];

			foreach(Mirror::reflectClass($this)->getProperties(false) as $property){
				if($property->getAnnotation()->isHidden()){
					continue;
				}

				$propertyName = $property->getName();
				$getter = "get".StringUtility::capitalise($property->getName());

				if($property->getAnnotation()->isObject()){
					$payload[$propertyName] = $this->$getter()?$this->$getter()->deliverPayload():[];
				}else if($property->getAnnotation()->isCollection()){
					$payload[$propertyName] = $this->_deliverCollectionPayload($property->getAnnotation()->getContainedType(),$this->$getter());
				}else{
					$payload[$propertyName] = $this->$getter();
				}
			}

			foreach($this->getCustomFields() as $customField => $value){
				$payload[$customField] = $value;
			}

			return $payload;
		}

		protected function hasBuildErrors(){
			return !empty($this->_buildErrors['required']) || !empty($this->_buildErrors['invalid']) || !empty($this->_buildErrors['conflicting']);
		}

		protected function getMissingArguments(){
			return $this->_buildErrors['required'];
		}

		protected function getInvalidArguments(){
			return $this->_buildErrors['invalid'];
		}

		protected function getConflictingArguments(){
			return $this->_buildErrors['conflicting'];
		}

		protected function getCustomFields(){
			return array();
		}

		protected function getETag(){
			$eTag = "";
			foreach(Mirror::reflectClass($this)->getProperties(false) as $property){
				if($property->getAnnotation()->isHidden()){
					continue;
				}

				$getter = "get".StringUtility::capitalise($property->getName());

				if($property->getAnnotation()->isObject()){
					$eTag .= $this->$getter()?$this->$getter()->getETag():"";
				}else if($property->getAnnotation()->isCollection()){
					$eTag .= $this->_getCollectionETag($property->getAnnotation()->getContainedType(),$this->$getter());
				}else{
					$eTag .= $this->$getter();
				}
			}

			return md5($this->getModified().$eTag);
		}

		private function _deliverCollectionPayload($containedType,$collection){
			$payload = [];

			foreach($collection as $key => $element){
				if($containedType->isCollection()){
					$eTag .= $this->_deliverCollectionPayload($containedType->getContainedType(),$element);
				}else if($containedType->isObject()){
					$payload[] = $element->deliverPayload();
				}else{
					$payload[] = $element;
				}
			}

			return $payload;
		}

		private function _getCollectionETag($containedType,$collection){
			$eTag = "";
			foreach($collection as $key => $element){
				if($containedType->isCollection()){
					$eTag .= $this->_getCollectionETag($containedType->getContainedType(),$element);
				}else if($containedType->isObject()){
					$eTag .= $element->getETag();
				}else{
					$eTag .= $element;
				}
			}

			return $eTag;
		}

		protected function getUri(){
			$sqlGateway = new SQLGateway();
			$searcher = new Searcher();
			$tokens = array();
			$uri = $this->getURITemplate();

			preg_match("/({\w+:\w+})/",$uri,$directives);
			foreach(array_slice($directives,1) as $directive){
				$directiveParts = preg_split("/:/",preg_replace("/[{}]/","",$directive));
				$searcher->addCriterion($directiveParts[1],Criterion::INCLUDES,$this);
				$parent = $sqlGateway->findUnique($directiveParts[0],$searcher);
				if($parent){
					$uri = preg_replace("/$directive/",$parent->getId(),$uri);
				}
			}

			preg_match("/({\w+})/",$uri,$tokens);
			foreach(array_slice($tokens,1) as $token){
				$getter = "get".StringUtility::capitalise(preg_replace("/[{}]/","",$token));
				$value = $this->$getter();
				$uri = preg_replace("/$token/",$value,$uri);
			}

			return $uri;
		}
	}
?>
