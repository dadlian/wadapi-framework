<?php
	namespace Wadapi\Authentication;

	use Wadapi\Http\Resource;
	use Wadapi\System\SettingsManager;

	class APIToken extends Resource{
		/** @WadapiString(required=true) */
		protected $role;

		/** @Integer */
		protected $expires;

		/** @WadapiString(max=32) */
		protected $accessKey;

		/** @WadapiString(max=32) */
		protected $accessSecret;

		/** @WadapiString(max=32) */
		protected $refreshSecret;

		/** @Boolean(default=false) */
		protected $disabled;

		/** @WadapiObject(class='Wadapi\Authentication\TokenProfile') */
		protected $profile;

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

		protected function getURI(){
			return ((array_key_exists('HTTPS',$_SERVER) && $_SERVER['HTTPS'])?"https://":"http://").SettingsManager::getSetting("install","url")."/access/{$this->getId()}";
		}

		protected function getURITemplate(){
			return ((array_key_exists('HTTPS',$_SERVER) && $_SERVER['HTTPS'])?"https://":"http://").SettingsManager::getSetting("install","url")."/access/{access_id}";
		}

		protected function getETag(){
			$eTag = $this->getRole();
			$eTag .= $this->getExpires();
			$eTag .= $this->getAccessKey();
			$eTag .= $this->getAccessSecret();
			$eTag .= $this->getRefreshSecret();
			return md5($this->getModified().$eTag);
		}
	}
?>
