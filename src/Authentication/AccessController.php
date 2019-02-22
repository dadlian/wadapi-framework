<?php
	namespace Wadapi\Authentication;

	use Wadapi\Http\ResourceController;
	use Wadapi\System\SettingsManager;
	use Wadapi\Persistence\SQLGateway;
	use Wadapi\Persistence\Searcher;
	use Wadapi\Persistence\Criterion;
	use Wadapi\Reflection\Mirror;

	abstract class AccessController extends ResourceController{
		protected function isInvalid(){
			$invalidArguments = array();

			$validRoles = preg_split("/,/",SettingsManager::getSetting("api","roles"));

			$role = $this->getFromContent("role");
			if(!is_string($role) || !in_array($role,$validRoles)){
				$invalidArguments[] = "role";
			}

			$invalidatedToken = $this->getFromContent("invalidated-token");
			if($invalidatedToken){
				preg_match($this->getRegexBase()."\/access\/([0-9]{14})$/",$invalidatedToken,$matches);

				$sqlGateway = new SQLGateway();
				$searcher = new Searcher();
				$searcher->addCriterion("id",Criterion::EQUAL,$matches?$matches[1]:"");
				if(!$matches || !$sqlGateway->findUnique("Wadapi\Authentication\APIToken",$searcher)){
				  $invalidArguments[] = "invalidated-token";
				}
			}

			return $invalidArguments;
		}

		protected function getTokenProfileClass(){
			foreach(get_declared_classes() as $declaredClass){
				$reflectedClass = Mirror::reflectClass($declaredClass);
				if($reflectedClass->descendsFrom("Wadapi\Authentication\TokenProfile")){
					$profileClass = $declaredClass;
				}
			}

			return $profileClass;
		}
	}
?>
