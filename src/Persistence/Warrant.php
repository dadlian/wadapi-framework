<?php
	namespace Wadapi\Persistence;

	class Warrant extends PersistentClass{
		/** @WadapiString */
		protected $code;

		/** @WadapiString */
		protected $action;

		/** @Boolean(default=false) */
		protected $executed;
	}
?>
