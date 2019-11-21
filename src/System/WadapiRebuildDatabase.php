<?php
	namespace Wadapi\System;

	use Wadapi\Persistence\Curator;
	use Wadapi\Http\ResponseHandler;

	class WadapiRebuildDatabase extends UtilityController{
		protected function post(){
			//Rebuild Database
			Curator::rebuildDatabase();

			$payload = array(
				"message"=>"Database Rebuilt Successfully"
			);

			ResponseHandler::created($payload,"/wadapi/rebuild");
		}
	}
?>
