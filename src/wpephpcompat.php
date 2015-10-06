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
    public function runTest($dir)
    {
        $this->values['files'] = $dir;
        //$this->values['ignored'] = $this->generateIgnoreList();
        $this->values['testVersion'] = $this->testVersion;
        $this->values['standard'] = "PHPCompatibility";
        $this->values['reportWidth'] = "9999";
        $this->values['extensions'] =  array("php");
        
        ob_start();
        
        $this->cli->process($this->values);

        $report = ob_get_contents();

        ob_end_clean();
        
        return $this->cleanReport($report);
    }
    
    /**
     * Generate a list of files to scan.
     * @return array Array of files to scan.
     */
    private function generateFileList()
    {
        //FIXME: Need to replace WP_CONTENT_DIR with a list of directories to scan.
        return array(WP_CONTENT_DIR);
    }
    
    /**
     * Generate a list of files to ignore.
     * @return array Array of files to exclude from the scan.
     */
    private function generateIgnoreList()
    {
        //Get this plugins relative directory.
        $pluginDir = dirname(plugin_basename(__DIR__));
        return array($pluginDir);
    }
    
    /**
     * Cleans and formats the final report.
     * @param  string $report The full report.
     * @return string         The cleaned report.
     */
    private function cleanReport($report)
    {
        //Remove unnecessary overview.
        $report = preg_replace ('/Time:.+\n/si', '', $report);
        
        //Remove whitespace.
        $report = trim($report);
        
        return $report;
    }
}
 
?>
