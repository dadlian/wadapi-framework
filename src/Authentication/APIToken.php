<?php
	namespace Wadapi\Authentication;

	use Wadapi\Http\Resource;
	use Wadapi\System\SettingsManager;
	use Wadapi\Reflection\Mirror;

	class APIToken extends Resource{
		/** @WadapiString(required=true) */
		protected $role;

		/** @Integer(hidden=true) */
		protected $expires;

		/** @WadapiString(max=32, hidden=true) */
		protected $accessKey;

		/** @WadapiString(max=32, hidden=true) */
		protected $accessSecret;

		/** @WadapiString(max=32, hidden=true) */
		protected $refreshSecret;

		/** @Boolean(default=false, hidden=true) */
		protected $disabled;

		/** @WadapiObject(class='Wadapi\Authentication\TokenProfile') */
		protected $profile;

		protected function getCustomFields(){
			$customFields = [];
			$customFields["tokens"] = "{$this->getURI()}/tokens";
			$customFields["enabled"] = !$this->isDisabled();
			return $customFields;
		}

		protected function refresh($lifetime=0){
			$accessKey = md5((time()/rand()) * rand());
			$accessSecret = md5((time()/rand()) * rand());
			$refreshSecret = md5((time()/rand()) * rand());

			$this->setAccessKey(md5($accessKey));
			$this->setAccessSecret(md5($accessSecret));
			$this->setRefreshSecret(md5($refreshSecret));
			$this->setExpires($lifetime?$this->getModified()+$lifetime:$lifetime);

			return array("key"=>$accessKey,"secret"=>$accessSecret,"refresh"=>$refreshSecret);
		}

		public static function getURITemplate(){
			return "/access/{id}";
		}

		public static function getTokenProfileClass(){
			foreach(get_declared_classes() as $declaredClass){
				$reflectedClass = Mirror::reflectClass($declaredClass);
				if($reflectedClass->descendsFrom("Wadapi\Authentication\TokenProfile")){
					$profileClass = $declaredClass;
				}
			}

			return $profileClass;
		}
	}
?>
