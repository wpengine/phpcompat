<?php
/**
 * Plugin Name:       PHP Compatibility Checker
 * Plugin URI:        https://wpengine.com
 * Description:       The WP Engine PHP Compatibility Checker can be used by any WordPress website on any web host to check PHP version compatibility.
 * Version:           1.6.2
 * Requires at least: 5.6
 * Requires PHP:      5.6
 * Author:            WP Engine
 * Author URI:        https://wpengine.com/
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wpe-php-compat
 * Domain Path:       /languages
 *
 * @package WPEngine_PHPCompat\PHP_Compatibility_Checker
 */

namespace WPEngine_PHPCompat;

define( 'WPEPHPCOMPAT_ADMIN_PAGE_SLUG', 'wpe-php-compat' );
define( 'WPEPHPCOMPAT_CAPABILITY', 'manage_options' );

use WPEngine_PHPCompat\PHP_Compatibility_Checker;

/**
 * Load plugin functionality.
 *
 * @since 1.0.0
 */
function wpe_phpcompat_loader() {
	$register_phpcompat = new PHP_Compatibility_Checker();
	$register_phpcompat->init();

	// Load the text domain.
	load_plugin_textdomain( 'wpe-php-compat', false, dirname( dirname( __FILE__ ) ) . '/languages' );

	// Add plugin action link.
	add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $register_phpcompat, 'filter_plugin_links' ) );
}

/**
 * Builds the class file name for the plugin
 *
 * @since 1.0.0
 *
 * @param string $class The name of the class to get.
 * @return string
 */
function wpe_phpcompat_get_class_file( $class ) {

	$prefix   = 'WPEngine_PHPCompat\\';
	$base_dir = __DIR__ . '/lib/';

	$len = strlen( $prefix );

	if ( 0 !== strncmp( $prefix, $class, $len ) ) {
		return '';
	}

	$relative_class = substr( $class, $len );
	$file           = $base_dir . str_replace( '\\', '/', 'class-' . strtolower( str_replace( '_', '-', $relative_class ) ) ) . '.php';

	$relative_class_parts = explode( '\\', $relative_class );

	if ( 1 < count( $relative_class_parts ) ) {
		$class_file = $relative_class_parts[0] . '/class-' . strtolower( str_replace( '_', '-', $relative_class_parts[1] ) );
		$file       = $base_dir . str_replace( '\\', '/', $class_file ) . '.php';
	}

	return $file;
}

/**
 * Auto-loading functionality for the plugin features
 *
 * @since 1.0.0
 *
 * @param object $class The class to load.
 */
function wpe_phpcompat_autoloader( $class ) {

	$file = wpe_phpcompat_get_class_file( $class );

	if ( ! empty( $file ) && file_exists( $file ) ) {
		include $file;
	}
}

spl_autoload_register( __NAMESPACE__ . '\wpe_phpcompat_autoloader' );

add_action( 'plugins_loaded', __NAMESPACE__ . '\wpe_phpcompat_loader' );

/**
 * Remove old options and custom post type
 *
 * @return void
 */
function maybe_migrate_to_wptide() {
	$is_wptide = get_option( 'wpephpcompat_is_wptide', false );

	if ( $is_wptide ) {
		// No need to clean legacy options.
		return;
	}

	delete_option( 'wpephpcompat.test_version' );
	delete_option( 'wpephpcompat.only_active' );
	delete_option( 'wpephpcompat.scan_results' );
	delete_option( 'wpephpcompat.lock' );
	delete_option( 'wpephpcompat.status' );
	delete_option( 'wpephpcompat.numdirs' );
	delete_option( 'wpephpcompat.show_notice' );

	wp_clear_scheduled_hook( 'wpephpcompat_start_test_cron' );

	$paged = 1;

	do {
		$jobs = get_posts(
			array(
				'posts_per_page' => 100,
				'paged'          => $paged,
				'post_type'      => 'wpephpcompat_jobs',
				'fields'         => 'ids',
			)
		);

		foreach ( $jobs as $job ) {
			wp_delete_post( $job );
		}

		$found_jobs = count( $jobs );
		$paged ++;
	} while ( $found_jobs );

	update_option( 'wpephpcompat_is_wptide', 1 );
}

/**
 * Activate plugin
 *
 * @return void
 */
function activate() {
	maybe_migrate_to_wptide();
}

/**
 * Uninstall plugin
 *
 * @return void
 */
function uninstall() {
	maybe_migrate_to_wptide();
	delete_option( 'wpephpcompat_is_wptide' );
}

/**
 * Perform operations when the plugin is upgraded
 *
 * @param WP_Upgrader $upgrader WordPress upgrader instance.
 * @param array       $hook_extra Options.
 * @return void
 */
function upgrade( $upgrader, $hook_extra ) {
	$current_plugin_path_name = plugin_basename( __FILE__ );

	if ( 'update' === $hook_extra['action'] && 'plugin' === $hook_extra['type'] ) {
		foreach ( $hook_extra['plugins'] as $plugin ) {
			if ( $plugin === $current_plugin_path_name ) {
				maybe_migrate_to_wptide();
			}
		}
	}
}

register_activation_hook( __FILE__, __NAMESPACE__ . '\activate' );
register_uninstall_hook( __FILE__, __NAMESPACE__ . '\uninstall' );
add_action( 'upgrader_process_complete', __NAMESPACE__ . '\upgrade', 10, 2 );
