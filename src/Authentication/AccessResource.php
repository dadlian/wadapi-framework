<?php
	namespace Wadapi\Authentication;

	use Wadapi\Http\ResourceController;
	use Wadapi\Persistence\SQLGateway;

	class AccessResource extends ResourceController{
		protected function retrieveResource($token){
			return $token;
		}

		protected function modifyResource($token, $data){
			$token->build($data);
			if(!$token->hasBuildErrors()){
				$sqlGateway = new SQLGateway();
				$sqlGateway->save($token);
			}

			return $token;
		}

		protected function deleteResource($token){
			$sqlGateway = new SQLGateway();
			$sqlGateway->delete($token);
			$sqlGateway->delete($token->getProfile());

			return $token;
		}
	}
?>
