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
            //FIXME: Queue actual plugins.
            $dir = array(
		 	    'post_title'    => "test",
 	            'post_content'  => "/vagrant/content/plugins/test",
 			    'post_status'   => 'publish',
 		 	    'post_author'   => 1, 
		 	    'post_type'	    => 'wpephpcompat_jobs'
			);
			error_log("Insert post:" . wp_insert_post( $dir ));
            
            update_option($scan_status_name, "1");
       }
       
       $args = array('posts_per_page' => -1, 'post_type' => 'wpephpcompat_jobs');
       $directories = get_posts($args);
       error_log("After getting posts.");
       
       //If there are no directories to scan, we're finished! 
       if (!$directories)
       {
           error_log("no posts");
           delete_option($lock_name);
           delete_option($scan_status_name);
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
       delete_option($scan_status_name);
       delete_option("wpephpcompat_scan_results");
       delete_option("wpephpcompat_scan_results");
       wp_clear_scheduled_hook("wpephpcompat_start_test_cron");
    
       delete_option($lock_name);
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
		<h2>WP Engine PHP Compatibility</h2>
		<p>
            <?php 
            
            ?>
            <b>Test Results:</b>
            <textarea disabled="disabled" style="width: 100%; height: 500px; background: #FFF; color: #000;" id="testResults"></textarea>
		</p>
        <p><input style="float: left;" name="run" id="runButton" type="button" value="Run" class="button-primary" /><div style="display:none; visibility: visible; float: none;" class="spinner"></div>
        </p>
        
	</div>
<?php 
}
