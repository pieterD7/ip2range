<?php

namespace App;

class Cidr{

	private $mask = null;
	
	// long
	private $base = "";
	
	public function __construct($base, $mask){
	
		$ip = new IpAddressV4($base);
		$this->base = $ip->getIpAddressLong();
		$this->mask = $mask;
	}

	public function getIpFrom(){
	
		return $this->base;
	}
	
	public function getIpTo(){
	
		return ($this->base) + pow(2, (32 - (int)$this->mask)) - 1;
	}
}

?>