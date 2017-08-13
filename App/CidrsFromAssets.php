<?php
namespace App;

define("CDIR_GLOBPATH", "assets/ipranges/cidr");

class CidrsFromAssets{

	public $botName = "";

	// Associative array with botname as index
	// $cidrs['Google'][0]
	private $cidrs = null;

	public function __construct(){
	
		$this->loadCidrs();
	}
	
	public function getCidrs(){
	
		return $this->cidrs;
	}

	private function loadCidrs(){
	
		foreach( glob( CDIR_GLOBPATH . "/cidr-*") as $cf){
				
			list($a, $botName) = explode(DIRECTORY_SEPARATOR . "cidr-", str_replace(".txt", "", $cf));
		
			if(!isset($botName))
				throw new Exception("CIDR file name error.");
			else{
				$lines = explode("\n", file_get_contents($cf));
				
				foreach($lines as $cidr){
					if(
						// Skip blank lines
						!empty($cidr) &&
					
						// Skip comments
						strpos(trim($cidr), "#") !== 0
					){
						$this->cidrs[$botName][] = $cidr;
					}
				}
			}
		}	
	}
}

?>