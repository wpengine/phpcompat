<?php
/**
 * Plugin Name: PHP Compatibility Checker
 * Plugin URI: https://wpengine.com
 * Description: The WP Engine PHP Compatibility Checker can be used by any WordPress website on any web host to check PHP version compatibility.
 * Version: 0.0.1
 * Text Domain: wpe-php-compat
 * Domain Path: /languages
 * Author: WP Engine
 * Author URI: https://wpengine.com/
 * License: GPLv2
 *
 * @package WPEngine\PHP_Compatibility_Checker
 */

define( 'WPENGINE_PHP_COMPATIBILITY_VERSION', '0.0.1' );

/**
 * Load plugin functionality.
 *
 * @since 1.0.0
 */
function wpe_phpcompat_loader() {

	// Load the text domain.
	load_plugin_textdomain( 'wpe-php-compat', false, dirname( dirname( __FILE__ ) ) . '/languages' );

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

	$prefix   = 'WPEngine\\PHP_Compatibility_Checker\\';
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

spl_autoload_register( 'wpe_phpcompat_autoloader' );

add_action( 'plugins_loaded', 'wpe_phpcompat_loader' );
