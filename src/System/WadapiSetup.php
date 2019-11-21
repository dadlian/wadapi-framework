<?php
	namespace Wadapi\System;

	use Wadapi\Persistence\DatabaseAdministrator;
	use Wadapi\Persistence\SQLGateway;
	use Wadapi\Persistence\Searcher;
	use Wadapi\Persistence\Criterion;
	use Wadapi\Persistence\Curator;
	use Wadapi\Authentication\APIToken;
	use Wadapi\Http\ResponseHandler;

	class WadapiSetup extends UtilityController{
		protected function post(){
			if(DatabaseAdministrator::tableExists("APIToken")){
				ResponseHandler::conflict("This Wadapi Instance has already been successfully configured.");
			}

      $sqlGateway = new SQLGateway();
      $searcher = new Searcher();

			//Rebuild Database
			Curator::rebuildDatabase();

			//Initialise Root API Token if necessary
			$rootKey = md5(md5(time()*rand()*rand()));
			$rootSecret = md5(md5(time()*rand()*rand()));
			$searcher->addCriterion("role",Criterion::EQUAL,"root");
			if(!$sqlGateway->findUnique("Wadapi\Authentication\APIToken",$searcher)){
				$sqlGateway->save(new APIToken("root",0,md5($rootKey),md5($rootSecret),md5($rootKey.$rootSecret)));
			}

			//Initialise authenticator API Token if necessary
			$authenticatorKey = md5(md5(time()*rand()*rand()));
			$authenticatorSecret = md5(md5(time()*rand()*rand()));
      $searcher->clearCriteria();
			$searcher->addCriterion("role",Criterion::EQUAL,"authenticator");
			if(!$sqlGateway->findUnique("Wadapi\Authentication\APIToken",$searcher)){
				$sqlGateway->save(new APIToken("authenticator",0,md5($authenticatorKey),md5($authenticatorSecret),md5($authenticatorKey.$authenticatorSecret)));
			}

			$payload = array(
				"root"=>[
          "key"=>$rootKey,
          "secret"=>$rootSecret
        ],
        "authenticator"=>[
          "key"=>$authenticatorKey,
          "secret"=>$authenticatorSecret
        ]
			);

			ResponseHandler::created($payload,"/wadapi/setup");
		}
	}
?>
