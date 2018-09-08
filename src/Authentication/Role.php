<?php
	namespace Wadapi\Authentication;

	use Wadapi\System\WadapiClass;

	class Role extends WadapiClass{
		/** @Collection(type=@WadapiString) */
		protected $permissions;
	}
?>
