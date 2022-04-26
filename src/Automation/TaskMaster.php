<?php
	namespace Wadapi\Automation;

  use Wadapi\System\Worker;
  use Wadapi\System\Janitor;

  class TaskMaster extends Worker{
    public static function cycle($period){
      $tasks = json_decode(file_get_contents(PROJECT_PATH."/tasks.json"),true)["tasks"];

      while(true){
        foreach($tasks as $task){
          $frequency = [
            "year" => 9999,
            "month" => 13,
            "day" => 32,
            "hour" => 24,
            "minute" => 60
          ];

          if(array_key_exists("minute", $task["every"])){
            $frequency["year"] = 1;
            $frequency["month"] = 1;
            $frequency["day"] = 1;
            $frequency["hour"] = 1;
            $frequency["minute"] = $task["every"]["minute"];
          }

          if(array_key_exists("hour", $task["every"])){
            $frequency["year"] = 1;
            $frequency["month"] = 1;
            $frequency["day"] = 1;
            $frequency["hour"] = $task["every"]["hour"];
          }

          if(array_key_exists("day", $task["every"])){
            $frequency["year"] = 1;
            $frequency["month"] = 1;
            $frequency["day"] = $task["every"]["day"];
          }

          if(array_key_exists("month", $task["every"])){
            $frequency["year"] = 1;
            $frequency["month"] = $task["every"]["month"];
          }

          if(array_key_exists("year", $task["every"])){
            $frequency["year"] = $task["every"]["year"];
          }

          $year = intval(date("Y"));
          $month = intval(date("m"))-1;
          $day = intval(date("d"))-1;
          $hour = intval(date("H"));
          $minute = intval(date("i"));

          if(
            $year % $frequency["year"] == 0 &&
            $month % $frequency["month"] == 0 &&
            $day % $frequency["day"] == 0 &&
            $hour % $frequency["hour"] == 0 &&
            $minute % $frequency["minute"] == 0
          ){
            $controller = new $task["controller"]();
            $controller->execute();
          }
        }

        Janitor::cleanup();
        sleep($period);
      }
    }
  }
?>
