<?php
/**
 * Dependency loader
 *
 * @package WPEngine\PHPCompat
 * @since 1.0.0
 */

// Exit if this file is directly accessed.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Loads the plugin files.
 */
function wpephpcompat_load_files() {
	require_once dirname( __FILE__ ) . '/src/wpephpcompat.php';

	if ( version_compare( phpversion(), '5.3', '<' ) ) {
		$autoload_file = dirname( __FILE__ ) . '/php52/vendor/autoload_52.php';
	} else {
		$autoload_file = dirname( __FILE__ ) . '/vendor/autoload.php';
	}

	require_once $autoload_file;

	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		require_once dirname( __FILE__ ) . '/src/wpcli.php';
	}
}

wpephpcompat_load_files();
