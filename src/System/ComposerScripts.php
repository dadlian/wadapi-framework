<?php
  namespace Wadapi\System;
  use Composer\Script\Event;

  class ComposerScripts{
    public static function setup(Event $event){
      $response = "";
      while(!in_array(strtolower($response),["y","n","yes","no"])){
        $response = readline("Would you like to configure the environment now [Y/n]? \n");
      }

      if(in_array(strtolower($response),["y","yes"])){
        self::configureEnvironment($event);
      }

      //Configure Authentication Method
      $authType = "";
      while(!in_array(strtolower($authType),["none","basic"])){
        $authType = readline("Please select the authentication type for your API [none/basic]? \n");
      }
      self::_writeSetting($event,"auth",$authType);

      //Generate Utlity Key
      $utilityKey = md5(microtime(true) * rand() * rand());
      self::_writeSetting($event,"utilitykey",$utilityKey);
      echo "Successfully Generated Utility Key: [$utilityKey]\n";
    }

    protected static function configureEnvironment(Event $event){
      $settingsFile = $event->getComposer()->getConfig()->get("vendor-dir")."/../conf/settings.json";
      $settings = json_decode(file_get_contents($settingsFile),true);

      $settings["database"] = [
        "hostname"=>"",
        "database"=>"",
        "username"=>"",
        "password"=>"",
        "prefix"=>""
      ];

      $settings["install"] = [
        "url"=>""
      ];

      $settings["logging"] = [
        "level"=>""
      ];

      //Configure environmental database settings
      $hostname = readline("Enter database hostname [localhost]: ");
      $database = readline("Enter database name []: ");
      $username = readline("Enter database username [root]: ");
      $password = readline("Enter database password []: ");
      $prefix = readline("Enter database prefix [wadapi]: ");

      $settings["database"] = [
        "hostname"=>$hostname?$hostname:"localhost",
        "database"=>$database,
        "username"=>$username?$username:"root",
        "password"=>$password,
        "prefix"=>$prefix?$prefix:"wadapi"
      ];

      //Configure environmental logging settings
      $logLevel = readline("Enter logging level [debug]: ");

      $settings["logging"] = [
        "level"=>$logLevel?$logLevel:"debug"
      ];

      file_put_contents($settingsFile,json_encode($settings,JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
      echo "Successfully Configured environment\n";
    }

    private static function _writeSetting($event,$setting,$value){
      $settingsFile = $event->getComposer()->getConfig()->get("vendor-dir")."/../conf/settings.json";
      $settings = json_decode(file_get_contents($settingsFile),true);
      $settings["api"][$setting] = $value;
      file_put_contents($settingsFile,json_encode($settings,JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
  }
?>
