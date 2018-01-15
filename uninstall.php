<?php
/**
 * Uninstall script
 *
 * @package WPEngine\PHPCompat
 * @since 1.0.0
 */

// If uninstall.php is not called by WordPress, die.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	die;
}

require_once dirname( __FILE__ ) . '/load-files.php';

$wpephpc = new WPEPHPCompat( dirname( __FILE__ ) );
$wpephpc->clean_after_scan();
delete_option( 'wpephpcompat.scan_results' );
