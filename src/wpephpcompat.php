<?php

require __DIR__ . '/../vendor/autoload.php';
 
class WPEPHPCompat
{    
    public $cli = null;
    
    public $values = array();
    
    public $testVersion = null;
    
    function __construct() 
    {
        $this->cli = new PHP_CodeSniffer_CLI();
    }
    
    public function runTest()
    {
        //FIXME: Need to replace WP_CONTENT_DIR, and exclude this plugin from the search. 
        $this->values['files'] = array(WP_CONTENT_DIR);
        $this->values['testVersion'] = $this->testVersion;
        $this->values['standard'] = "PHPCompatibility";
        $this->values['reportWidth'] = "9999";
        
        ob_start();
        
        $this->cli->process($this->values);

        $report = ob_get_contents();

        ob_end_clean();
        
        return $report;
    }
}
 
?>
