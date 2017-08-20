<?php
namespace App;

class App extends \CliApp\CliApp{

	// \App\IpDb
	protected $ipDb = null;

	public function __construct(\CliApp\Logger $logger = null){
	
		$this->optionsAllowed = array(
			array(
				"short" => "h",
				"long" => "-help"
			),
			array(
				"short" => "z::",
			),
			array(
				"short" => "ip:"
			),
			array(
				"short" => "b"
			),
			array(
				"short" => "c"
			),
			array(
				"short" => "d:",
			),
			array(
				"short" => "i:"
			),
			array(
				"short" => "l:"
			),
			array(
				"short" => "out:"
			)
		);
		
		$this->optionsMutualyExclusive = array(
			array("z", "b"),
		);
	
		parent::__construct($logger);
	
	}
	
	public function __destruct(){
	
		if($this->ipDb)
		{
			$this->ipDb->closeConnection();	
			$this->info("Mysql connection closed", array());
		}
	}

	protected function displayHelpScreen(){
	
		global $argv;

		echo "USAGE: " . $argv[0] . " [-h] | [-ip ipaddress] | [ -z [zipfile] | -b | -c] ";
		echo "[-l logfile] [-i inifile] [-d loglevel]" . PHP_EOL;
		echo "DESCR: Creates table(s) in the database with ip ranges or extends ip addresses ";
		echo "in piped input when no options are given." . PHP_EOL;
		echo PHP_EOL;
		echo "       -h    Generate inifile and or display this screen." . PHP_EOL;
		echo "       -ip   Resolve ip address to range info." . PHP_EOL; 
		echo "       -z    Create mysql tables from zip file from ip2nation.com. Optionally specify  " . PHP_EOL .
			 "             the url or filename for the ip2nation zip file. Cannot be used with -b." . PHP_EOL;	
		echo "       -b    Create ip ranges mysql table from ftp registries. Cannot be used with -z." . PHP_EOL;
		echo "       -c    Insert CIDRs from assets directory." . PHP_EOL;
				
		parent::displayHelpScreen();
	}

	public function run($options){

		$this->startUp();
		
		// First get data on all ip addresses

		// Build database from ip2nation zip file?
		if(isset($options['z'])) 
			$this->createTableFromZip($options);
		
		// Build database from ftp registries?
		if(isset($options['b']))		
			$this->createTableFromFtp();
					
		// Then we assume any CIDR is a subrange of 1 range	
		
		// Update CIDRs in location database?
		if(isset($options['c']))		
			$this->insertFromCidrs();
		
		// Resolve an IP address?
		if($this->ipDb){
			if(isset($options['ip']))
			{
				$a = new IpAddressV4($options['ip']);
				$this->emit( 
					$a->getNetworkClass() . " " .
					$this->ipDb->resolveV4($options['ip']) . PHP_EOL
				);
			}
		}
		
		// Input from pipe?		
		if(!(isset($options['b']) || isset($options['z']) || isset($options['ip']) || isset($options['c'])))
			$this->handlePipedInput();
		
	}
	
	private function startUp(){
	
		if(!$this->ipDb)
		{
		
			// Assume (empty) database exists
			// Will show PHP error with php logger 
			$this->ipDb = new \App\IpDb( new \Workerman\MySQL\Connection(
				$this->iniFile['mysql_url'], 
				$this->iniFile['mysql_port'], 
				$this->iniFile['mysql_user'], 
				$this->iniFile['mysql_password'], 
				$this->iniFile['mysql_dtb']
				),
				$this->iniFile["mysql_table_V4"]
			);
		
			$this->info("Mysql connection started", array());				
			
		}	
	}
	
	private function handlePipedInput(){
	
		$input = "";
		$praw = "";

		$fd = fopen("php://stdin", "r");
		ob_implicit_flush (true);

		while(is_resource($fd) && (false !== ($input = fgets($fd)) && !feof($fd))){

			$output = $input;
	
			preg_match_all("/\d{1,3}[.]\d{1,3}[.]\d{1,3}[.]\d{1,3}/", $input, $ipsV4);

			if(!empty($ipsV4[0])){
				$ipsV4 = array_unique($ipsV4[0]);
						
				foreach($ipsV4 as $ip){

					if($this->ipDb){
			
						$r = $this->ipDb->resolveV4($ip);
				
						if(!empty($r))
							$r = " (" . $r . ")";
						else
							$r = "";
								
						$output = str_replace($ip, $ip . $r, $output);
					}
				}
			}				

			$output = trim($output);
			if(!empty($output))
				$this->emit($output . PHP_EOL);
		
		}
		fclose($fd);
	}
	
	private function createTableFromZip($options){
	
		//if(! $this->confirm("Drop table " . $this->iniFile['mysql_dtb'] . ".ip2nation if exists on " . 
		//	$this->iniFile["mysql_url"] . "? Y/n", "y"))
		//	return;
	
		$this->info("Creating " . $this->iniFile['mysql_dtb'] . ".ip2nation table from zip file.", array());
	
		$url = "http://ip2nation.com/ip2nation.zip";
		if(!empty($options['z'])) 
			$url = $options['z'];

		$this->info("ip2nation zip file : " . $url, array());

		$this->updateDtb($url);	
	}
	
	private function createTableFromFtp(){

		//if(! $this->confirm("Drop table " . $this->iniFile['mysql_dtb'] . "." .
		//	$this->iniFile['mysql_table_V4'] . " if exists on " . $this->iniFile["mysql_url"] . 
		//	"? Y/n", "y"))
		//	return;
	
		$this->info("Creating " . $this->iniFile['mysql_dtb'] . "." . 
			$this->iniFile['mysql_table_V4']  . " table from ftp registries.", array());
	
		$this->ipDb->db->query("DROP TABLE IF EXISTS " . $this->iniFile['mysql_table_V4']  . ";");
		
		// Create table
		$this->ipDb->createMysqlTable();
	
		$r = new RangesFromFtp($this->ipDb, $this->logger);
	}
	
	private function insertFromCidrs(){
	
		// Check for ip2range table inside this database
		$canInsertTo = true;
		if(!$this->ipDb->hasIp2RangeTable($this->iniFile["mysql_dtb"]))
		{
			$this->logger->error("No table found to insert to.", array());
			$canInsertTo = false;
		}
	
		$this->info("Reading CIDRs.", array());		

		// Get the data from directory
		$ip2bots = new CidrsFromAssets();
		if($canInsertTo){
			foreach($ip2bots->getCidrs() as $bot => $ar){
		
				$this->info("Inserting CIDRs for " . $bot, array());
		
				foreach($ar as $a){
				
					list($ip, $mask) = explode("/", $a);
										
					$cidr = new Cidr($ip, $mask);					
								
					// Check if there is a dev table
					if(!$this->ipDb->hasToField()){
						$this->logger->error("Missing 'to' field in " . 
							$this->iniFile['mysql_table_V4']  . " table.", array() );
						break;
					}
									
					$this->ipDb->insertSubRange(
						new IpRange(
							$bot,
							$cidr->getIpFrom(),							
							$cidr->getIpTo() + 1
						)
					);
				}
			}			
		}	
	}

	private function updateDtb($url){

		// open tmpfile
		$path =  sys_get_temp_dir() . "/ip2nation";

		$this->deleteTempFiles("/ip2nation");

		if(!mkdir($path)){
			$this->error("Error creating tempfile.", array());
			return false;
		}
		else{
			// Get file mtime
			if(strpos($url, "http") === 0)
				$this->info("Remote file mtime : " . $this->getRemoteFileMTime($url), array());	
			else
				$this->info("File mtime : " . $this->filetime( filemtime($url)), array());			

			// get zip file
			$zipContents = file_get_contents($url);

			if(!$zipContents){
				$this->error("Could not read from " . $url, array());
				return false;
			}

			// make it a file on the local fs
			$wrtn = file_put_contents($path . "/ip2nation.zip", $zipContents);
			if(!$wrtn){
				$this->error("Could not write to " . $path . ".", array());
				return false;
			}
			else
				$this->info("Written " . $wrtn . " bytes to " . 
					$path . "/ip2nation.zip.", array());

			// unzip
			$zip = new \VIPSoft\Unzip\Unzip();
			$zip->extract($path . "/ip2nation.zip", $path);

			if(is_file($path . "/ip2nation.sql")){
				$this->info("Sql file found in archive. $path/ip2nation.sql " . 
					filesize($path . "/ip2nation.sql") . " bytes", array());
				
				$cmd = "mysql" .
						" -u " . $this->iniFile['mysql_user'] . 
						" -p" . $this->iniFile['mysql_password'] .
						" " . $this->iniFile['mysql_dtb'] . 
						" < " . $path . "/ip2nation.sql";						
				`$cmd`;
				
				$this->deleteTempFiles("/ip2nation");
				
				return true;
			}
			else{
				$this->error("No sql file found in archive.", array());

				$this->deleteTempFiles("/ip2nation");

				return false;
			}

		}   
		
	}
    
	private function getRemoteFileMTime($url){

		if(function_exists('curl_version')){
			$curl = curl_init($url);
			curl_setopt($curl, CURLOPT_NOBODY, true);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($curl, CURLOPT_FILETIME, true);

			$result = curl_exec($curl);

			return $this->filetime( curl_getinfo($curl, CURLINFO_FILETIME));     	
		} 
		return "(curl is not enabled.)";  	
	}

	private function deleteTempFiles($path){
	
		// delete folder and contents if exists
		$path = sys_get_temp_dir() . $path;
		if(is_dir($path)){
			foreach(glob($path . "/*") as $file)
				unlink($file);
			rmdir($path);
		}	
	}
}
?>
