<?php

require __DIR__ . '/../vendor/autoload.php';
 
class WPEPHPCompat
{    
    /**
     * The PHP_CodeSniffer_CLI object.  
     * @var class
     */
    public $cli = null;
    
    /**
     * Default values for PHP_CodeSniffer scan. 
     * @var array
     */
    public $values = array();
    
    /**
     * Version of PHP to test.
     * @var string
     */
    public $testVersion = null;
    
    function __construct() 
    {
        $this->cli = new PHP_CodeSniffer_CLI();
    }
    
    /**
     * Runs the actual PHPCompatibility test.
     * @return string Scan results.
     */
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
        
    /**
     * Generate a list of files to scan.
     * @return array Array of files to scan.
     */
    private function generateFileList()
    {
        //FIXME: Need to replace WP_CONTENT_DIR with a list of directories to scan.
        return array(WP_CONTENT_DIR);
    }
    
        return $report;
    }
}
 
?>
