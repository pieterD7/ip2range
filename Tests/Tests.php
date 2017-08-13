<?php

namespace Tests;

use Webmozart\Assert\Assert;

class Tests{

	private $ip = "1.0.1.1";
	
	public function __construct(App $app = null){
		$this->app = $app;
	}

	public function runTests(){
		
		// Make connection to testdb
		$testdb = new \Workerman\MySQL\Connection(
				"localhost", null, 
				$this->app->iniFile['mysql_user'], 
				$this->app->iniFile['mysql_password'], 
				$this->app->iniFile['mysql_testdtb']
			);
		$db = new \Workerman\MySQL\Connection(
				"localhost", null, 
				$this->app->iniFile['mysql_user'], 
				$this->app->iniFile['mysql_password'], 
				$this->app->iniFile['mysql_dtb']
			);
		
		$this->ipresolver1 = new IpResolver($db);
		$this->ipresolver2 = new IpResolver($testdb);
	
		$this->resolveSame($this->ip);
		
		$this->app->info("Test passed.", array());
	
	}
	
	private function resolveSame($ip){
	
		$r1 = $this->ipresolver1->resolveV4($ip);
		$r2 = $this->ipresolver2->resolveV4($ip);		
		
		try{
			Assert::same($r1, $r2, "Lookup is different for %s");
		}
		catch(\InvalidArgumentException $e){
		
			$this->app->error(
				"Test failed. " . $e->getMessage(), 
				array(
					"ip" => $ip,
					"prod" => "" . $r1,
					"test" => "" . $r2
				)
			);
		}	
	}
}

?>