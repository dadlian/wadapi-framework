<?php
  namespace Wadapi\System;
  use Composer\Script\Event;

  class ComposerScripts{
    public static function setup(Event $event){
      $environments = ["development","production"];
      foreach($environments as $environment){
        $response = "";
        while(!in_array(strtolower($response),["y","n","yes","no"])){
          $response = readline("Would you like to configure the $environment environment now [Y/n]? \n");
        }

        if(in_array(strtolower($response),["y","yes"])){
          self::configureEnvironment($environment, $event);
        }
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

    protected static function configureEnvironment($environment, Event $event){
      $environmentFile = $event->getComposer()->getConfig()->get("vendor-dir")."/../src/environments.json";
      $environments = json_decode(file_get_contents($environmentFile),true);

      if(!array_key_exists($environment,$environments)){
        $environments[$environment] = [
          "database"=>[
            "hostname"=>"",
            "database"=>"",
            "username"=>"",
            "password"=>"",
            "prefix"=>""
          ],
          "install"=>[
            "url"=>""
          ],
          "logging"=>[
              "level"=>""
          ]
        ];
      };

      //Configure environmental database settings
      $hostname = readline("Enter $environment database hostname [localhost]: ");
      $database = readline("Enter $environment database name []: ");
      $username = readline("Enter $environment database username [root]: ");
      $password = readline("Enter $environment database password []: ");
      $prefix = readline("Enter $environment database prefix [wadapi]: ");

      $environments[$environment]["database"] = [
        "hostname"=>$hostname?$hostname:"localhost",
        "database"=>$database,
        "username"=>$username?$username:"root",
        "password"=>$password,
        "prefix"=>$prefix?$prefix:"wadapi"
      ];

      //Configure environmental installation $settings
      $url = readline("Enter the base url of your $environment API [localhost]: ");

      $environments[$environment]["install"] = [
        "url"=>$url?$url:"localhost"
      ];

      //Configure environmental logging settings
      $logLevel = readline("Enter $environment logging level [debug]: ");

      $environments[$environment]["logging"] = [
        "level"=>$logLevel?$logLevel:"debug"
      ];

      file_put_contents($environmentFile,json_encode($environments,JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
      echo "Successfully Configured $environment environment\n";
    }

    private static function _writeSetting($event,$setting,$value){
      $settingsFile = $event->getComposer()->getConfig()->get("vendor-dir")."/../src/settings.json";
      $settings = json_decode(file_get_contents($settingsFile),true);
      $settings["api"][$setting] = $value;
      file_put_contents($settingsFile,json_encode($settings,JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
  }
?>
