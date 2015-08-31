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
add_action('wp_ajax_wpephpcompat_run_test', 'wpephpcompat_run_test');

function wpephpcompat_run_test()
{

    //TODO: Allow setting testVersion from the UI.
    $wpephpc = new \WPEPHPCompat();
    $wpephpc->testVersion = "5.5";
    $report = $wpephpc->runTest();

    echo $report;
    
    wp_die();
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
            <b>Test Results:</b>
            <textarea style="width: 100%; height: 500px;" id="testResults"></textarea>
		</p>
        <p><input style="float: left;" name="run" id="runButton" type="button" value="Run" class="button" /><div style="display:none; visibility: visible; float: none;" class="spinner"></div>
        </p>
        
	</div>
<?php 
}
