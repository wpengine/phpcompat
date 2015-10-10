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
    
    public function startTest()
    {
        // Try to lock.
        $lock_result = add_option($this->lock_name, time(), '', 'no' );
        
        error_log("lock: ". $lock_result);
        
        if (!$lock_result)
        {
           $lock_result = get_option($this->lock_name);

           // Bail if we were unable to create a lock, or if the existing lock is still valid.
           if ( ! $lock_result || ( $lock_result > ( time() - MINUTE_IN_SECONDS ) ) ) 
           {
               error_log("Locked, this would have returned.");
               return;
           }
        }
            update_option($this->lock_name, time());
           
           //Check to see if scan has already started.
           $scan_status = get_option($this->scan_status_name);
           error_log("scan status: " . $scan_status);
           if (!$scan_status)
           {
               //Add plugins.
               //TODO: Add logic to only get active plugins.
               $this->generateDirectoryList();
                
           }
           
           $args = array('posts_per_page' => -1, 'post_type' => 'wpephpcompat_jobs');
           $directories = get_posts($args);
           error_log("After getting posts.");
           
           //If there are no directories to scan, we're finished! 
           if (!$directories)
           {
               error_log("no posts");
               
               $this->cleanAfterScan();
               
               return;
           }
           
           wp_schedule_single_event( time() + ( MINUTE_IN_SECONDS ), 'wpephpcompat_start_test_cron' );
           
           $scan_results = get_option("wpephpcompat_scan_results");
         
           foreach ($directories as $directory)
           {
               $report = $this->processFile($directory->post_content);
               $scan_results .= "Name: " . $directory->post_title . "\n" . $report . "\n";
               update_option("wpephpcompat_scan_results", $scan_results);
               //update_post_meta($directory->ID, "results", )
               wp_delete_post($directory->ID);
           }
           
           echo $scan_results;
           
           //All scans finished, clean up!
           $this->cleanAfterScan();
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
