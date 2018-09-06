<?php
	namespace Wadapi\Authentication;

  use Wadapi\Http\Resource;

  abstract class TokenProfile extends Resource{
    protected function initialise($arguments,$token){
      $invalidArguments = $this->getInvalidArguments($arguments,$token);
      if(!$invalidArguments){
        $this->build($arguments,$token);
      }

      return $invalidArguments;
    }

    abstract public function getInvalidArguments($arguments,$token);
    abstract public function build($arguments,$token);
    abstract public function deliverPayload();
  }
?>
