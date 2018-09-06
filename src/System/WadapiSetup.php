<?php
	namespace Wadapi\System;

	use Wadapi\Persistence\SQLGateway;
	use Wadapi\Persistence\Searcher;
	use Wadapi\Persistence\Criterion;
	use Wadapi\Persistence\Curator;
	use Wadapi\Authentication\APIToken;
	use Wadapi\Http\ResponseHandler;

	class WadapiSetup extends UtilityController{
		protected function isInvalid(){
			$invalidArguments = array();
			return $invalidArguments;
		}

		protected function getInvalidQueryParameters(){
			$invalidQueryParameters = array();
			return $invalidQueryParameters;
		}

		protected function isConsistent($modifiedDate,$eTag){
			return true;
		}

		protected function assemblePayload($tokens){
			$payload = array(
				"root"=>[
          "key"=>$tokens['root-key'],
          "secret"=>$tokens['root-secret']
        ],
        "authenticator"=>[
          "key"=>$tokens['authenticator-key'],
          "secret"=>$tokens['authenticator-secret']
        ]
			);

			return $payload;
		}

		protected function post(){
      $sqlGateway = new SQLGateway();
      $searcher = new Searcher();

			//Rebuild Database
			Curator::rebuildDatabase();

			//Initialise Root API Token if necessary
			$rootKey = md5(md5(time()*rand()*rand()));
			$rootSecret = md5(md5(time()*rand()*rand()));
			$searcher->addCriterion("role",Criterion::EQUAL,"root");
			if(!$sqlGateway->findUnique("APIToken",$searcher)){
				$sqlGateway->save(new APIToken("root",0,md5($rootKey),md5($rootSecret),md5($rootKey.$rootSecret)));
			}

			//Initialise authenticator API Token if necessary
			$authenticatorKey = md5(md5(time()*rand()*rand()));
			$authenticatorSecret = md5(md5(time()*rand()*rand()));
      $searcher->clearCriteria();
			$searcher->addCriterion("role",Criterion::EQUAL,"authenticator");
			if(!$sqlGateway->findUnique("APIToken",$searcher)){
				$sqlGateway->save(new APIToken("authenticator",0,md5($authenticatorKey),md5($authenticatorSecret),md5($authenticatorKey.$authenticatorSecret)));
			}

      $tokens = [
        "root-key"=>$rootKey,
        "root-secret"=>$rootSecret,
        "authenticator-key"=>$authenticatorKey,
        "authenticator-secret"=>$authenticatorSecret
      ];

			ResponseHandler::created($this->assemblePayload($tokens),$this->getBase()."/wadapi/setup");
		}
	}
?>
