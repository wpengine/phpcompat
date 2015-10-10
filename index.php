<?php
/*
    Plugin Name: WP Engine PHP Compatibility
    Plugin URI: http://wpengine.com
    Description: Make sure your plugins and themes are compatible with newer PHP versions. 
    Author: WP Engine
    Version: 0.0.1
    Author URI: http://wpengine.com
 */

require __DIR__ . '/vendor/autoload.php';

//Build our tools page.
add_action('admin_menu', 'wpephpcompat_create_menu');
//Load our JavaScript.
add_action( 'admin_enqueue_scripts', 'wpephpcompat_enqueue' );
//The action to run the compatibility test.
add_action('wp_ajax_wpephpcompat_start_test', 'wpephpcompat_start_test');
add_action('wpephpcompat_start_test_cron', 'wpephpcompat_start_test');
//Create custom post type.
add_action( 'init', 'wpephpcompat_create_job_queue' );

function wpephpcompat_start_test()
{
    global $wpdb;
    error_log("started");
    $lock_name = 'wpephpcompat.lock';
    $scan_status_name = 'wpephpcompat.status';
    
    // Try to lock.
    $lock_result = add_option($lock_name, time(), '', 'no' );
    
    error_log("lock: ". $lock_result);
    
    if (!$lock_result)
    {
       $lock_result = get_option($lock_name);

       // Bail if we were unable to create a lock, or if the existing lock is still valid.
       if ( ! $lock_result || ( $lock_result > ( time() - MINUTE_IN_SECONDS ) ) ) 
       {
           error_log("Locked, this would have returned.");
           return;
       }
    }
        update_option($lock_name, time());
       
       //Check to see if scan has already started.
       $scan_status = get_option($scan_status_name);
       error_log("scan status: " . $scan_status);
       if (!$scan_status)
       {
           //Add plugins.
           //TODO: Add logic to only get active plugins.
            $plugin_base = dirname(__DIR__) . DIRECTORY_SEPARATOR;
                    
            $all_plugins = get_plugins();
            
            foreach ($all_plugins as $k => $v) 
            {
                //Exclude our plugin.
                if ($v["Name"] === "WP Engine PHP Compatibility")
                {
                    continue;
                }
                
                $plugin_path = $plugin_base . plugin_dir_path($k);
                
                add_directory($v["Name"], $plugin_path);
            }
            
            //Add themes.
            //TODO: Add logic to only get active theme.
            $all_themes = wp_get_themes();
            
            foreach ($all_themes as $k => $v) 
            {

                $theme_path = $all_themes[$k]->theme_root . DIRECTORY_SEPARATOR . $k . DIRECTORY_SEPARATOR;
                
                add_directory($all_themes[$k]->Name, $theme_path);
            }
            
            update_option($scan_status_name, "1");
       }
       
       $args = array('posts_per_page' => -1, 'post_type' => 'wpephpcompat_jobs');
       $directories = get_posts($args);
       error_log("After getting posts.");
       
       //If there are no directories to scan, we're finished! 
       if (!$directories)
       {
           error_log("no posts");
           clean();
           return;
       }
       
       wp_schedule_single_event( time() + ( MINUTE_IN_SECONDS ), 'wpephpcompat_start_test_cron' );
       
       $scan_results = get_option("wpephpcompat_scan_results");
     
       foreach ($directories as $directory)
       {
           $wpephpc = new \WPEPHPCompat();
           $wpephpc->testVersion = "5.5";
           $report = $wpephpc->runTest($directory->post_content);
           $scan_results .= $report . "\n";
           update_option("wpephpcompat_scan_results", $scan_results);
           wp_delete_post($directory->ID);
       }
       
       echo $scan_results;
       
       //All scans finished, clean up!
       clean();
       wp_die();
}

function wpephpcompat_create_job_queue() 
{
	register_post_type( 'wpephpcompat_jobs',
		array(
			'labels' => array(
				'name' => __( 'Jobs' ),
				'singular_name' => __( 'Job' )
			),
		'public' => false,
		'has_archive' => false,
		)
	);
}

function wpephpcompat_enqueue()
{
    wp_enqueue_script( 'wpephpcompat', plugins_url( '/src/js/run.js', __FILE__ ), array('jquery') );

	wp_localize_script( 'wpephpcompat', 'ajax_object', array( 'ajax_url' => admin_url( 'admin-ajax.php' )) );
}

function wpephpcompat_create_menu()
{
    //Create Tools sub-menu.
    $wpeallowheartbeat_settings_page = add_submenu_page('tools.php', 'PHP Compatibility', 'PHP Compatibility', 'administrator', __FILE__, 'wpephpcompat_settings_page');
}

function wpephpcompat_settings_page()
{
        
    ?>
	<div class="wrap">
		<div style="float: left;"><h2>WP Engine PHP Compatibility</h2></div> <div style="float: right; margin-top: 10px;"> <input type="checkbox" id="developermode" name="developermode" value="yes">Developer mode</div>
        <br><br>
        <h3>Scan Settings</h3>
        <span style="font-weight: 900; font-size: 12px;">Scan only active plugins and themes?</span><br>
        <input type="radio" name="activeplugins" value="yes" checked>Yes
        <br>
        <input type="radio" name="activeplugins" value="no">No
        <br>
        <span style="font-weight: 900; font-size: 12px;">PHP Version?</span><br>
        <input type="radio" name="phptestversion" value="5.5" checked>PHP 5.5
        <br>
        <input type="radio" name="phptestversion" value="5.4">PHP 5.4
        <br>
        <input type="radio" name="phptestversion" value="5.3">PHP 5.3
    
		<p>            
            <div id="standardMode">
            
            </div>
            
            <div style="display: none;" id="developerMode">
                <b>Test Results:</b>
                <textarea disabled="disabled" style="width: 100%; height: 500px; background: #FFF; color: #000;" id="testResults"></textarea>
            </div>
            <div id="footer" style="display: none;">
            Note: Warnings are not currently an issue, but they will be in the future.<br>
            <a id="downloadReport" href="#">Download</a>
            </div>
		</p>
        <p><input style="float: left;" name="run" id="runButton" type="button" value="Run" class="button-primary" /><div style="display:none; visibility: visible; float: none;" class="spinner"></div>
        </p>
        
	</div>
<?php 
}

function add_directory($name, $path)
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

function clean()
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
<!-- Results template -->    
    <script id="result-template" type="text/x-handlebars-template">
        <div style="border-left-color: {{#if passed}}#038103{{else}}#e74c3c{{/if}};" class="results-card">
            <div class="inner-left">
                {{#if passed}}<img src="http://www.clker.com/cliparts/9/I/e/1/i/B/dark-green-check-mark-hi.png">{{else}}<img src="http://sweetclipart.com/multisite/sweetclipart/files/x_mark_red.png">{{/if}}
            </div>
            <div class="inner-right">
                <h3 style="margin: 0px;">{{plugin_name}}</h3>
                {{#if passed}}This plugin is PHP {{testVersion}} compatible.{{else}}This plugin is <b>not</b> PHP {{testVersion}} compatible.{{/if}}<br><br>
                <div class="addDetails"><textarea style="display: none;">{{logs}}</textarea><a class="view-details">view details</a></div>
            </div>
            <div style="float:right;"><div class="badge warnings">{{warnings}} Warnings</div><div class="badge errors">{{errors}} Errors</div></div>
        </div>
    </script>
<?php 
}
