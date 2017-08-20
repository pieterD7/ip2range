<?php

require "vendor/autoload.php";

spl_autoload_register(function($className){	
	$parts = explode("\\", $className);
	require $parts[0] . DIRECTORY_SEPARATOR . $parts[1] . ".php";
});

//$a = new \Tests\Tests(new \App\App(new \CliApp\Logger(\CliApp\LogLevel::DEBUG)));
//$a->runTests();
if(empty($a))	
	$a = new \App\App(new \CliApp\Logger(\CliApp\LogLevel::DEBUG));
?>
