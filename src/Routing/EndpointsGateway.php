<?php
	namespace Wadapi\Routing;

	use Wadapi\System\SettingsManager;
	use Wadapi\Authentication\Role;

	class EndpointsGateway{
		private $endpoints;

		public function __construct(){
			//Load User Endpoints
			$userEndpoints = json_decode(file_get_contents(MAPPINGS."/endpoints.json"),true);
			$this->endpoints = array_key_exists("endpoints",$userEndpoints)?$userEndpoints['endpoints']:array();

			//Add System Endpoints
			$authenticatorWrite = ["write"];
			$authenticatorReadWrite = ["read","write"];

			$this->endpoints[] = [
	      "name"=>"Access Collection",
	      "path"=>"access",
	      "controller"=>"Wadapi\Authentication\AccessCollection",
	      "roles"=>["authenticator"=>$authenticatorWrite],
	      "parameters"=>[]
			];

			$this->endpoints[] = [
	      "name"=>"Access Resource",
	      "path"=>"access/@",
	      "controller"=>"Wadapi\Authentication\AccessResource",
	      "roles"=>["authenticator"=>$authenticatorReadWrite],
	      "parameters"=>["access"]
			];

			$this->endpoints[] = [
	      "name"=>"Token Collection",
	      "path"=>"access/@/tokens",
	      "controller"=>"Wadapi\Authentication\TokenCollection",
	      "roles"=>["authenticator"=>$authenticatorWrite],
	      "parameters"=>["access"]
			];

			$this->endpoints[] = [
	      "name"=>"Token Resource",
	      "path"=>"access/@/tokens/active",
	      "controller"=>"Wadapi\Authentication\TokenResource",
	      "roles"=>["authenticator"=>$authenticatorWrite],
	      "parameters"=>["access"]
			];
		}

		public function find($property,$value,$unique=false){
			$matchingEndpoints = array();

			foreach($this->endpoints as $endpoint){
				if($property == "path"){
					$url = new URLPattern($value);
					$isMatch = $url->match($endpoint[$property]);
				}else{
					$isMatch = array_key_exists($property,$endpoint) && $endpoint[$property] == $value;
				}

				if($isMatch){
					$roles = array();
					foreach($endpoint['roles'] as $role => $permissions){
						$roles[$role] = new Role($permissions);
					}

					$matchingEndpoints[] = new Endpoint(
						$endpoint['name'],
						$endpoint['path'],
						$endpoint['controller'],
						$roles,
						$endpoint['parameters']
					);

					if($unique){
						break;
					}
				}
			}

			if($unique){
				return(sizeof($matchingEndpoints) > 0)?$matchingEndpoints[0]:null;
			}else{
				return $matchingEndpoints;
			}
		}

		public function findUnique($property,$value){
			return $this->find($property,$value,true);
		}
	}
?>
