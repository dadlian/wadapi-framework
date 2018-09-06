<?php
	namespace Wadapi\Persistence;

	class Grave extends PersistentClass{
		/** @WadapiString */
		protected $objectClass;

		/** @WadapiString */
		protected $objectId;

		/** @WadapiString */
		protected $timeOfDeath;
	}
?>
