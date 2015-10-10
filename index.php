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

//Add the phpcompat WP-CLI command.
if ( defined('WP_CLI') && WP_CLI ) {
    include __DIR__ . '/src/wpcli.php';
}

function wpephpcompat_start_test()
{
    global $wpdb;

    $wpephpc = new \WPEPHPCompat(__DIR__);
    
    //$wpephpc->cleanAfterScan();
    //die();
    
    $testVersion = $_POST['testVersion'];
    $onlyActive = $_POST['onlyActive'];

    
    $wpephpc->testVersion = $testVersion;
    
    $wpephpc->onlyActive = $onlyActive;
    
    error_log("started");

    
    $wpephpc->startTest();
    
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
