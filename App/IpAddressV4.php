<?php
namespace App;

class IpAddressV4{

	// in long form: ip2long("10.10.10.10");
	private $ip = null;
	
	public function __construct($ip){
	
		if(strpos($ip, ".") !== false)
			$this->ip = sprintf("%u", ip2long($ip));
		else	
			$this->ip = $ip;
	}
	
	public function getIpAddressLong(){

		return $this->ip;
	}
	
	public function getIpAddress(){
	
		return long2ip($this->ip);
	}
		
	public function getNetworkClass(){
	
		$parts = explode(".", long2ip($this->ip));
		$octet0 = $parts[0];
		
		if($octet0 < 128)
			return "A";
		else if($octet0 < 192)
			return "B";
		else if($octet0 < 224)
			return "C";
		else if($octet0 < 240)
			return "D";
		else
			return "E";
	}
	
	public function plus($int){
	
		return long2ip($this->ip + $int);
	}
}

?>