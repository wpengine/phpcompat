<?php

// If uninstall.php is not called by WordPress, die.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	die;
}

require_once( __DIR__ . '/src/wpephpcompat.php' );

$wpephpc = new \WPEPHPCompat( __DIR__ );
$wpephpc->clean_after_scan();
delete_option( 'wpephpcompat.scan_results' );
