<?php
	namespace Wadapi\Routing;

	use Wadapi\System\WadapiClass;

	class Endpoint extends WadapiClass{
		/*
		 * The unique name that identifies this endpoint
		 */
		/** @WadapiString(required=true) */
		protected $name;

		/*
		 * The URL that invokes this particular endpoint
		 */
		/** @WadapiString */
		protected $path;

		/*
		 * The name that identifies this endpoint and the controller that handles it
		 */
		/** @WadapiString(required=true) */
		protected $controller;

		/*
		 * A list of user types that have permission to execute this endpoint
		 */
		/** @Collection(type=@WadapiObject(class='Wadapi\Authentication\Role')) */
		protected $roles;

		/*
		 * A list of the expected parameters to this endpoint
		 */
		/** @Collection(type=@WadapiString) */
		protected $parameters;

		/*
		 * A list of MIME types which this endpoint can respond with (overides the global settings)
		 */
		/** @Collection(type=@WadapiString) */
		protected $formats;

		/*
		 * A list of character sets in which this endpoint can respond (overides the global settings)
		 */
		/** @Collection(type=@WadapiString) */
		protected $charsets;

		/*
		 * A list of languages in which this endpoint can respond (overides the global settings)
		 */
		/** @Collection(type=@WadapiString) */
		protected $languages;

		public function getAllowedRoles(){
			$allowedRoles = array();
			foreach($this->getRoles() as $role){
				$allowedRoles[] = $role->getTitle();
			}
			return $allowedRoles;
		}

		public function __toString(){
			return $this->getPath();
		}
	}
?>
