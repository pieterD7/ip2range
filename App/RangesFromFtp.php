<?php

namespace App;

class RangesFromFtp{

	private $registry1 = array(
		"ftp://ftp.ripe.net/ripe/stats/delegated-ripencc-latest"
		,"ftp://ftp.lacnic.net/pub/stats/lacnic/delegated-lacnic-latest"
		,"ftp://ftp.apnic.net/pub/stats/apnic/delegated-apnic-latest"
		,"ftp://ftp.arin.net/pub/stats/arin/delegated-arin-extended-latest"
		,"ftp://ftp.afrinic.net/pub/stats/afrinic/delegated-afrinic-latest"
		);
		
	public function __construct(\App\IpDb $ipDb = null, \CliApp\Logger $logger = null){
		
		$this->logger = $logger;
		$this->ipDb = $ipDb;
		
		// Get data from url or tmp/
		foreach($this->registry1 as $c => $registry)
		{	
			$path = sys_get_temp_dir() . "/delegated-xxxx-latest-" . $c;
	
			if(!is_file($path))	{

				$this->logger->info("Getting url " . $registry, array());

				$x = file_get_contents($registry);
				file_put_contents($path, $x);
			}
			else{
	
				$this->logger->info("Getting file " . $path, array());
	
				$x = file_get_contents($path);
			}
				
			$lines = explode("\n", $x);
			
			$fl = 0;
			foreach($lines as $k => $v){
				if(strpos($v, "#") === false){
					$fl = $k;
					break;
				}
			}
	
			$this->parseFirstLine($lines[$fl]);
	
			foreach($lines as $line){
				$r = $this->parseLine($line);
				if($r !== false)
					$this->ipDb->insertRange($r, true);
//				else
//					$this->logger->error("Line could not be parsed.", array("line" => $line));
			}
		}
	}
	
	private function parseFirstLine($line){
		
		$a = explode("|", $line);
		
		if(isset($a[6]) && $a[0] > 1){
			$date = $a[5];
			$timeZ = $a[6];
			
			list($version, $registry, $serial, $records, $startDate, $endDate, $tz) = $a;
			
			$this->logger->info(
				"Records: " . $records .
				", registry: " . $registry .
				", date from: " . $startDate . 
				", date to: " . $endDate . 
				" " . $tz, array());
		}
		else{	
			$this->logger->error("First line '" . $line . "' could not be parsed", array($a));
		}
	}
	
	private function parseLine($line){
		
		$a = explode("|", $line);
		
		if(isset($a[4])){
		
			list($a, $cc, $type, $ip, $value) = $a;
		
			if($type == 'ipv4' && $ip !== '*'){
			
				// From
				$ipa = new IpAddressV4($ip);
	
				// To
				$ipb = new IpAddressV4($ipa->plus($value));
		
				return new IpRange($cc, $ipa->getIpAddressLong(), $ipb->getIpAddressLong());
			}
		}
		return false;
	}

}

?>