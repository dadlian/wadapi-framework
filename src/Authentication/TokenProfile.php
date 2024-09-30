<?php
	namespace Wadapi\Authentication;

  use Wadapi\Http\Resource;

  abstract class TokenProfile extends Resource{
    public function initialise($arguments,$token){
      $invalidArguments = $this->getInvalidArguments($arguments,$token);
      if(!$invalidArguments){
        $this->build($arguments,$token);
      }

      return $invalidArguments;
    }
  }
?>
