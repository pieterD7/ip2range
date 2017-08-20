<?php

namespace App;

class IpDb{

	// \Workerman\MySQL\Connection 
	public $db = null;
	
	// String
	private $table = null;

	public function __construct(\Workerman\MySQL\Connection $db = null, $table){
		$this->db = $db;
		$this->table = $table;
	}
	
	public function closeConnection(){
		$this->db->closeConnection();
	}

	public function createMysqlTable(){
	
		// Create country / start / ip (same as ip2nation but adding to field)
		$sql = "CREATE TABLE if not exists " . $this->table . "(" .
			"ip         int(11)    unsigned     NOT NULL default '0'," . 
			"`to`       int(11)    unsigned     NOT NULL default '0'," . 
			"country    char(12)                NOT NULL default ''," . 
			"UNIQUE KEY ip (ip), 
			 UNIQUE KEY `to` (`to`), 
			 PRIMARY KEY (ip, `to`))";
			
		$this->db->query($sql);
	
	}
	
	public function hasToField(){
		$query = "show columns from "  . $this->table .
				" WHERE Field = 'to'";
		$r = $this->db->query($query);
		if(isset($r[0]['Field']) &&
			$r[0]['Field'] == 'to'){
			return true;	
		}
		return false;
	}
	
	public function hasIp2RangeTable($databaseName){
	
		$q = "SELECT TABLE_NAME as tables FROM INFORMATION_SCHEMA.TABLES " .
			"WHERE TABLE_SCHEMA='" . $databaseName . 
			"' AND TABLE_NAME = '" . $this->table  . "'";
				
		return isset($this->db->row($q)['tables']) &&
			$this->db->row($q)['tables'] == $this->table;
	}

	public function resolveV4($ip){
	
		// Filter netmasks
		if(strpos($ip, "255") === 0)
			return;
			
		$q = "SELECT 
			country
		FROM " .
			$this->table . "
		WHERE 
			ip <= INET_ATON('" . $ip . "')  
		ORDER BY 
			ip DESC 
		LIMIT 0,1;";
		
		return $this->db->row($q)['country'];
			
	/*	return $this->db
			->select(array("country"))
			->from($this->table)
			->where("ip <= INET_ATON(:ip) ")
			->bindValues(array("ip" => $ip))
			->orderByDESC(array("ip"))
			->limit(0,1)
			->row()['country']; */
	}
	
	public function insertSubRange(IpRange $iprange /* B */){
	
		if($iprange->getIpFrom() == 0 || $iprange->getIpTo() == 0){
			$this->error("Error null value", array($iprange->getIpFrom(), $iprange->getIpTo()));
			return;
		}

		// B overlaps A on Afrom = Bfrom AND Ato != Bto
		
		// if(Afrom = Bfrom AND Ato < Bto)
		// UPDATE Afrom = Bto ; INSERT B 	
		
		// Update A
		if($this->db->update($this->table)
			->cols(array("ip"))
			->where("ip = :ip1 AND `to` != :to")
			->bindValues(array(
				"ip1" => $iprange->getIpFrom(),
				"to" =>$iprange->getIpTo(),
				"ip" => $iprange->getIpTo()
				)
			)
			->query() == 1)
		{
			// Insert B
			$this->insertRange($iprange, false);
			
			return true;
		}
		
		// To do: in 1 query
		// ...

		// B in between both ends of A?
		
		// else(Afrom < Bfrom AND Ato > Bto)
		// UPDATE Ato = b.from ; INSERT B ; INSERT Cfrom = Bto Cto = Ato				
		$Arow = $this->db->select("country,ip,`to`")
		->from($this->table)		
		->where("ip < :ip AND `to` > :to")
		->bindValues(array(
			"ip" => $iprange->getIpFrom(),
			"to" => $iprange->getIpTo()
			)
		)
		->limit(0,1)		
		->row();
		
		if(isset($Arow["country"]) && $Arow['ip'] > 0 && $Arow['to'] > 0){
		
			// Update Ato
			$this->db->update($this->table)
			->cols(array("to"))
			->where("ip = :ip AND `to` = :toA")
			->bindValues(array(
				"to" => $iprange->getIpFrom(),
				"ip" => $Arow["ip"],
				"toA" => $Arow["to"]
				)
			)
			->query();	
			
						
			// Insert B
			$this->insertRange($iprange, false);
			
			// Insert C
			$c = new IpRange($Arow["country"], $iprange->getIpTo(), $Arow["to"]);
		
			$this->insertRange($c, false);
		
			return true;
		}
		
		// Full match on to and from 
		
		// if(Afrom = Bfrom AND Ato = Bto)
		// UPDATE Acc = Bcc
		if($this->db->update($this->table)
			->cols(array("country" ))
			->where("ip = :ip AND `to` = :to")
			->bindValues(
				array(	
					"country" => $iprange->getCC(),
					"ip" => $iprange->getIpFrom(),
					'to' => $iprange->getIpTo()
				)
			)
			->query() == 1){
				
			return true;
		}	
		
				
		// B overlaps A on Ato = Bto
		
		// if(Afrom > Bfrom AND Ato = Bto)
		// UPDATE Ato = Bfrom ; INSERT B;	
			
		// Update Ato 
		if($this->db->update($this->table)
			->cols(array("to"))
			->where("`to` = :to1 AND ip != :ip")
			->bindValues(array(
				"ip" => $iprange->getIpFrom(),
				"to" => $iprange->getIpFrom(),
				"to1" => $iprange->getIpTo()
				)
			)
			->query() == 1){	
						
			// Insert B
			$this->insertRange($iprange, false);
			
			return true;
		}
		
		// Range didn't exist. Insert it
		else 
			$this->insertRange($iprange, false);

		return false;
	}
	
	public function insertRange(IpRange $iprange, $optimize = false){

		if($optimize && !$this->optimizeTable($iprange))
			$this->insert($iprange);
			
		else if(!$optimize)
			$this->insert($iprange);
	}
	
	private function insert(IpRange $iprange){
	
		try{
		$this->db->insert($this->table)
			->cols(array(
				"country" => $iprange->getCC(),
				"ip" => $iprange->getIpFrom(),
				"to" => $iprange->getIpTo()
				)
			)
			->query();
		}
		catch(PDOException $e){
			return;
		}
	}
		
	private function optimizeTable(IpRange $iprange /* B */){
	
		// select A : B.from  = A.to		
		// update A set A.to = B.to 
		$cnt = $this->db->update($this->table)
			->cols(array('to'))
			->where("`to` = :toB AND country = :cc")
			->bindValues(array(
				"to" => $iprange->getIpTo(),
				"toB" => $iprange->getIpFrom(),
				"cc" => $iprange->getCC()))
			->query();
		
		if($cnt == 1)
			return true;
	
		// select A : B.to = A.from		
		// update A set A.from = B.from
		$cnt = $this->db->update($this->table)
			->cols(array('ip'))
			->where("ip = :ipB AND country = :cc")
			->bindValues(array(
				"ip" => $iprange->getIpFrom(),
				"ipB" => $iprange->getIpTo(),
				"cc" => $iprange->getCC()))
			->query();
		
		if($cnt == 1)
			return true;
					
		return false;
	}
}
?>
