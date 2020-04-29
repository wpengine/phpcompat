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
	require_once dirname( __FILE__ ) . '/vendor/autoload.php';

	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		require_once dirname( __FILE__ ) . '/src/wpcli.php';
	}
}

wpephpcompat_load_files();
