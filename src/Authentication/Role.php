<?php
	namespace Wadapi\Authentication;

	use Wadapi\Persistence\PersistentClass;

	class Role extends PersistentClass{
		/** @Collection(type=@WadapiString) */
		protected $permissions;
	}
?>
