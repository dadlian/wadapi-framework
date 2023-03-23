<?php
	namespace Wadapi\Persistence;

	use Wadapi\System\WadapiClass;
	use Wadapi\System\Registrar;

	abstract class DatabaseConnection extends WadapiClass{
		//DatabaseConnection constructor which registers itself in the system registrar before creation
		public function __construct(){
			Registrar::register($this);
			call_user_func_array(array("Wadapi\System\WadapiClass", "__construct"), func_get_args());
		}

		public abstract function connect();
		public abstract function close();
		public abstract function execute();
		public abstract function commit();
		public abstract function rollback();
	}
?>
