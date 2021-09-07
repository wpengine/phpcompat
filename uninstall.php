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

delete_option( 'wpephpcompat.lock' );
delete_option( 'wpephpcompat.status' );
delete_option( 'wpephpcompat.numdirs' );

// Clear scheduled cron.
wp_clear_scheduled_hook( 'wpephpcompat_start_test_cron' );

// Make sure all directories are removed from the queue.
$args = array(
	'posts_per_page' => -1,
	'post_type'      => 'wpephpcompat_jobs',
);

$directories = get_posts( $args );

foreach ( $directories as $directory ) {
	wp_delete_post( $directory->ID );
}

delete_option( 'wpephpcompat.scan_results' );
