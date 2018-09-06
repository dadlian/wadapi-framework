<?php
	namespace Wadapi\Persistence;

	class Magistrate extends Worker{
		public static function issueWarrant($action){
			$code = md5($action.time().rand());
			$sqlGateway = new SQLGateway();
			$sqlGateway->save(new Warrant($code,$action,false));

			return $code;
		}

		public static function executeWarrant($code){
			$sqlGateway = new SQLGateway();
			$searcher = new Searcher();
			$searcher->addCriterion("code",Criterion::EQUAL,$code);

			$warrant = $sqlGateway->findUnique("Warrant",$searcher);
			if($warrant->isExecuted()){
				return false;
			}else{
				$warrant->toggleExecuted();
				$sqlGateway->save($warrant);
				return true;
			}
		}
	}
?>
