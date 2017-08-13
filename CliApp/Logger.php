<?php

namespace CliApp;

use Psr\Log\LoggerInterface;
use CliApp\LogLevel;
use CliApp\TimeStamp;

class Logger implements LoggerInterface
{
    /** @var resource The stream resource being written to */
    protected $stream;

    public $logLevel;

    public function __construct($logLevel)
    {
        $this->logLevel = $logLevel;

        // handle basic use-case
        $this->stream = STDERR;
    }

    public function setStream($stream)
    {
        $this->stream = $stream;
    }
    
    public function emergency($message, array $context = []){}
    
    public function alert($message, array $context = []){}
    
    public function critical($message, array $context = []){
        if($this->logLevel >= LogLevel::CRITICAL)
             $this->log("CRITICAL", $message, $context);
    }

	public function warning($message, array $context = []){}

    public function error($message, array $context = [])
    {
        if($this->logLevel >= LogLevel::ERROR)
             $this->log("ERROR", $message, $context);
    }

	public function notice($message, array $context = []){}
	
    public function info($message, array $context = [])
    {
        if($this->logLevel >= LogLevel::INFO)
             $this->log("INFO", $message, $context);
    }	
	
	public function debug($message, array $context = []){}
	
    public function log($level, $message, array $context = [])
    {        
        $ts = new \CliApp\TimeStamp();

        fwrite($this->stream, $ts->makeTimeStamp() . " " . $level . " " . $message . " " . 
        	urldecode(http_build_query($context, null, " ")) . PHP_EOL);
    }

}

?>