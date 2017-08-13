<?php

namespace CliApp;

use CliApp\Logger;

class CliApp
{
    public  $logger;
    
    protected $options;
    protected $optionsAllowed;
    protected $optionsMutualyExclusive;
    
    private $optionsAllowedShort;
    private $optionsAllowedLong;

    public function __construct(Logger $logger = null){
    
        $this->logger = $logger;
        $this->logFile = null;
       
        // parse array of allowed options : separate arrays short from long
        if(is_array($this->optionsAllowed))
        {
            foreach($this->optionsAllowed as $el){
                foreach($el as $k => $v)
                    if($k == "short")
                        $this->optionsAllowedShort[$v] = $v;
                    else if($k == "long")
                         $this->optionsAllowedLong[$v] = $v;
            }
            $this->options = $this->getOptions();
        }

        // Open log file
        if(isset($this->options['l']))
            $this->logFile = $this->options['l'];
        
        $this->openLogFile($this->logFile);

        if($this->logFile)
            ini_set("error_log", $this->logFile);
        else
        	// Set to stderr for error info to get into httpd error log
            ini_set("error_log", 'php://stderr' );

        // Create and or parse iniFile 
        $this->iniFile = $this->parseAppIniFile();

        // Set log level or not.
        if(isset($this->options['d']) && !empty($this->options['d'])){
            if($this->options['d'] == "ERROR"){
                $this->logger->logLevel = \CliApp\LogLevel::ERROR;	
                error_reporting(E_ERROR);
            }
        }
        else{
            if($this->iniFile['log_level'] == 'ERROR'){
                $this->logger->logLevel = \CliApp\LogLevel::ERROR;	
                error_reporting(E_ERROR);
            }
        }        

        //  Emit startup line.
        $this->info( "App Started. " . 
            "User=" . get_current_user() . " " .
            "GID=" . getmygid() . " " .
            "UMASK=0" . decoct(umask()), $this->options);


        // Run
        if(isset($this->options['h']) || isset($this->options['-help'])){
            $this->displayHelpScreen();
        }
        else{
           $this->run($this->options);
        }

    }
    
    public function getLogger(){
        return $this->logger;
    }
    
    protected function displayHelpScreen(){
    
        global $argv;
    
        echo "       -l    Log file name. Defaults to stderr." . PHP_EOL;
        echo "       -i    Inifile to parse. Defaults to " . str_replace(".php", "", $argv[0]) . ".ini." . PHP_EOL;
        echo "       -d    Loglevel. Can be ERROR or DEBUG." . PHP_EOL;
        echo PHP_EOL;

    }
    
    public function emit($txt){

        $outFile =  isset($this->options['out']) ?
            $this->options['out'] :
            "php://stdout";

        file_put_contents($outFile, $txt, FILE_APPEND);
    }
    
    public function confirm($prompt, $reply){

        //$line = trim(readline($prompt));
        if($prompt){
            echo $prompt . " ";
        }
        $fp = fopen("php://stdin","r");
        $line = rtrim(fgets($fp, 1024));        
        
        return strtolower($line) == strtolower($reply);
    }
    
    public function parseAppIniFile(){
    
        global $argv;
        
        if(isset($this->options['i'])){
            $path = $this->options['i'];
        }
        else
             // Use the stem of the file name of the app for the ini file name
            $path = str_replace(".php", "", $argv[0] . ".ini");
        
        if(!is_file($path))
            $this->createINIFile($path);
        	
        $ini = parse_ini_file($path);
        if($ini)
            return $ini;
        else{
            $this->error("Unable to parse ini file.", array());
            return false;
        }
    }
    
    private function createINIFile($path){
    
        file_put_contents($path, 
            "[config]" . PHP_EOL .
            "log_level = ERROR" . PHP_EOL .
             PHP_EOL .
            "# Uncomment following line to enable warnings" . PHP_EOL .
            "#log_level = DEBUG" . PHP_EOL .
             PHP_EOL .
            "mysql_url = localhost" . PHP_EOL .
            "mysql_port = ''" . PHP_EOL .
            "mysql_user = pi" . PHP_EOL .
            "mysql_password = secret" . PHP_EOL .
            "mysql_table_V4 = ip2rangeV4" . PHP_EOL .
            "mysql_dtb = ip2range" . PHP_EOL .
            "mysql_testdtb = ip2nation" . PHP_EOL 
        );
    }

    /**
    * Parse commandline as options starting with spsce-.
    * Values can contain spaces but need to be quoted then by
    * double quotes. With php 5.3 this means these quotes need 
    * to be escaped on the comandline.
    */
    public function getOptions(){
    
        global $argv;

        // Make a copy; unset unsets the global variable as NOT stated in docs
        $av = $argv;

        // Unset prog name
        unset($av[0]);
        $a = array();

        // Make the argv array an array of options starting with space-
        $iav = " " . implode(" ", $av); 
        $av1 = explode(" -", $iav);

        foreach($av1 as $raw){
            $val = "";

            $raw = trim($raw);

            // Try to find a value for this option
            $v = $this->explodeQuotedBySpace($raw);

            //Value after a space?
            if(isset($v[1])){
                // Is allowed option?
                if(isset($this->optionsAllowedShort[$v[0] . ":"]) ||
                   isset($this->optionsAllowedShort[$v[0] . "::"]) ||
                   isset($this->optionsAllowedLong[$v[0] . ":"]) ||
                   isset($this->optionsAllowedLong[$v[0] . "::"]))
                {
                    $a[$v[0]] = $v[1];
                }
                // Option is not allowed
                else
                    $a['h'] = '';
            }
            // Option value required but no value given?
            else if(isset($this->optionsAllowedShort[$v[0] . ":"]) ||
                    isset($this->optionsAllowedLong[$v[0] . ":"]))
                    $a['h'] = '';
            else if(!empty($raw)){
                // Is allowed option?
                if(isset($this->optionsAllowedShort[$raw . "::"]) ||
                   isset($this->optionsAllowedShort[$raw]) ||
                   isset($this->optionsAllowedLong[$raw . "::"]) ||
                   isset($this->optionsAllowedLong[$raw]))
                   $a[$raw]= '';
                // Option is not allowed
                else
                   $a['h'] = '';
            }
        }
        
        // Parse options mutualy exclusive
        foreach($this->optionsMutualyExclusive as $opt){
            if(isset($a[$opt[0]]) && isset($a[$opt[1]]))
            {
                $this->logger->error("Options -" . $opt[0] . " and -" . $opt[1] . 
                " are mutualy exclusive.");
                $a['h'] = '';
            }	
        }
        return $a;
    }

    /** 
    * Explode string by space but skip spaces in between double quotes
    */
    public function explodeQuotedBySpace($in){
    
        // The result array
        $res = array();
        // InString?
        $q = -1;
        // The string
        $str = "";
        // The result of the normal explode
        $parts = explode(" ", $in);

        foreach($parts as $p){
            // StateChange?
            $s = false;

            // State change on "
            if(strpos($p, "\"") !== false)
            {
                $q = -1*$q;
                $s = true;
            }

            // If InString and StateChange start a new string
            if($q == 1 && $s)
                $str = $p;
            // If StateChange AND InString push to return array
            else if($s && $q == -1)
                $res[] = str_replace("\"", "", $str . " " . $p);
            // If InString append to result
            else if($q == 1)
                $str .= " " . $p;
            // Else just push to result
            else if($q == -1)
                $res[] = $p;
        }
        return $res;
    }
    
    public function filetime($time){
    
        return date("Y-m-d H:i:s (T)", $time);    
    }

    public function openLogFile($LOG_FILE){
    
        if(!empty($LOG_FILE))
        {
            $dirname = dirname($LOG_FILE);
            if(!is_dir($dirname))
            {
                mkdir($dirname);
            }

            $stream = fopen($LOG_FILE, "a");
            if($stream !== false)
                $this->logger->setStream($stream);
            else
            {
                $this->error("No permission to create or append to log file.", array());
                exit(1);
            }
        }
    }

    public function info($message, $context){
    
        if ($this->logger) {
            $this->logger->info($message, $context);
        }
    }

    public function error($message, $context){
    
        if ($this->logger) {
            $this->logger->error($message, $context);
        }
    }

    public function critical($message, $context){
    
        if ($this->logger) {
            $this->logger->critical($message, $context);
        }
    }

}
?>