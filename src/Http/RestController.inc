<?php
	namespace Wadapi\Http;

	use Wadapi\System\Controller;
	use Wadapi\System\SettingsManager;
	use Wadapi\Reflection\Mirror;

	abstract class RestController extends Controller{
		public function execute(){
			$method = strtolower(RequestHandler::getMethod());

			if(!Mirror::reflectClass($this)->hasMethod($method)){
				ResponseHandler::unsupported("/".RequestHandler::getRequestURI()." does not support the ".RequestHandler::getMethod()." method.");
			}else{
				$this->$method();
			}
		}

		protected function options(){
			$payload = array("options"=>array());
			foreach(Mirror::reflectClass($this)->getMethods() as $method){
				$methodName = $method->getName();
				if(in_array($methodName,array("get","post","put","delete","options"))){
					$payload["options"][] = strtoupper($methodName);
				}
			}

			$uri = ((array_key_exists('HTTPS',$_SERVER) && $_SERVER['HTTPS'])?"https://":"http://").SettingsManager::getSetting("install","url")."/".RequestHandler::getRequestURI();
			ResponseHandler::retrieved($payload,$uri);

		}
  }
?>
