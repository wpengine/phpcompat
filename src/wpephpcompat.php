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
    
    public $onlyActive = null;
    
    public $lock_name = 'wpephpcompat.lock';
    
    public $scan_status_name = 'wpephpcompat.status';
    
    public $base = null;
    
    function __construct($dir) 
    {
        $this->base = $dir;
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
    public function processFile($dir)
    {
        $this->values['files'] = $dir;
        //$this->values['ignored'] = $this->generateIgnoreList();
        $this->values['testVersion'] = $this->testVersion;
        $this->values['standard'] = "PHPCompatibility";
        $this->values['reportWidth'] = "9999";
        $this->values['extensions'] =  array("php");
        
         PHP_CodeSniffer::setConfigData('testVersion', $this->testVersion, true);
        
        ob_start();
        
        $this->cli->process($this->values);

        $report = ob_get_contents();

        ob_end_clean();
        
        return $this->cleanReport($report);
    }
    
    /**
     * Generate a list of directories to scan and populate the queue.
     */
    public function generateDirectoryList()
    {
        $plugin_base = dirname($this->base) . DIRECTORY_SEPARATOR;
                
        $all_plugins = get_plugins();
        
        foreach ($all_plugins as $k => $v) 
        {
            //Exclude our plugin.
            if ($v["Name"] === "WP Engine PHP Compatibility")
            {
                continue;
            }
            
            //Exclude active plugins if onlyActive = "yes".
            if ($this->onlyActive === "yes")
            {
                //Get array of active plugins.
                $active_plugins = get_option('active_plugins');
                
                if (!in_array($k, $active_plugins))
                {
                    continue;
                }
            }
            
            $plugin_path = $plugin_base . plugin_dir_path($k);
            
            $this->addDirectory($v["Name"], $plugin_path);
        }
        
        //Add themes.
        //TODO: Add logic to only get active theme.
        $all_themes = wp_get_themes();
        
        foreach ($all_themes as $k => $v) 
        {
            if ($this->onlyActive === "yes")
            {
                $current_theme = wp_get_theme();
                if ($all_themes[$k]->Name != $current_theme->Name)
                    continue;
            }

            $theme_path = $all_themes[$k]->theme_root . DIRECTORY_SEPARATOR . $k . DIRECTORY_SEPARATOR;
            
            $this->addDirectory($all_themes[$k]->Name, $theme_path);
        }
        
        update_option($this->scan_status_name, "1");
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
    
    public function cleanAfterScan()
    {

        delete_option("wpephpcompat.lock");
        delete_option("wpephpcompat.status");
        delete_option("wpephpcompat_scan_results");
        wp_clear_scheduled_hook("wpephpcompat_start_test_cron");
        
        $args = array('posts_per_page' => -1, 'post_type' => 'wpephpcompat_jobs');
            
        $directories = get_posts($args);
            
        foreach ($directories as $directory)
        {
            wp_delete_post($directory->ID);
        }
    }
    
    private function addDirectory($name, $path)
    {
        $dir = array(
            'post_title'    => $name,
            'post_content'  => $path,
            'post_status'   => 'publish',
            'post_author'   => 1, 
            'post_type'	    => 'wpephpcompat_jobs'
        );
        
        wp_insert_post( $dir );
    }
}
 
?>
