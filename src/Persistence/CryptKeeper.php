<?php
	namespace Wadapi\Persistence;

	use Wadapi\System\Worker;
	use Wadapi\Reflection\Mirror;
	use Wadapi\Http\ResponseHandler;
	use Wadapi\Utility\MessageUtility;
	use Wadapi\Logging\Logger;

	class CryptKeeper extends Worker{
		/*
		 * Remove an object from the database and mark it as removed
		 */
		public static function bury($object){
			if(!is_object($object) || !Mirror::reflectClass($object)->descendsFrom("Wadapi\Persistence\PersistentClass")){
				Logger::fatal_error(MessageUtility::DATA_MODIFY_ERROR,"Only PersistentClass objects may be buried.");
			}

			$sqlGateway = new SQLGateway();
			$sqlGateway->delete($object);

			$grave = new Grave(get_class($object),$object->getId(),strval(time()));
			$sqlGateway->save($grave);
		}

		/*
		 * See whether an object has previously existed and been deleted
		 */
		 public static function exhume($objectId,$resource){
			$sqlGateway = new SQLGateway();
			$searcher = new Searcher();
			$searcher->addCriterion("objectId",Criterion::EQUAL,$objectId);
			$searcher->addCriterion("objectClass",Criterion::EQUAL,$resource);
			$corpse = $sqlGateway->find("Grave",$searcher);

			if($corpse){
				ResponseHandler::gone("The requested resource no longer exists.");
			}

			return false;
		 }
	}
?>
