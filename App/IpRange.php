<?php

namespace App;

class IpRange{

	private $cc = '';
	private $ipFrom = 0;
	private $ipTo = 0;

	public function __construct($cc, $ipFrom, $ipTo){
	
		$this->cc = $cc;
		
		$this->ipFrom = $ipFrom;
		
		$this->ipTo = $ipTo;
		
	}
	
	public function getCC(){
		return $this->cc;
	}
	
	public function getIpFrom(){
		return $this->ipFrom;
	}
	
	public function getIpTo(){
		return $this->ipTo;
	}

}

?>