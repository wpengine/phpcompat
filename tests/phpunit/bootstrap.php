<?php

// Disable xdebug backtrace.
if ( function_exists( 'xdebug_disable' ) ) {
	xdebug_disable();
}

if ( false !== getenv( 'WP_PLUGIN_DIR' ) ) {
	define( 'WP_PLUGIN_DIR', getenv( 'WP_PLUGIN_DIR' ) );
}

$GLOBALS['wp_tests_options'] = array(
	'active_plugins' => array( basename( dirname( dirname( dirname( __FILE__ ) ) ) ) . '/wpengine-phpcompat.php' ),
);

if ( false !== getenv( 'WP_DEVELOP_DIR' ) ) {
	$test_root = getenv( 'WP_DEVELOP_DIR' ) . '/tests/phpunit';
} elseif ( file_exists( '/tmp/wordpress-tests-lib/includes/bootstrap.php' ) ) {
	$test_root = '/tmp/wordpress-tests-lib';
} else {
	$test_root = '../../../../../../../tests/phpunit';
}

if ( file_exists( $test_root . '/includes/functions.php' ) ) {
	require_once $test_root . '/includes/functions.php';
	function _manually_load_plugin() {
		require dirname( __FILE__ ) . '/../../wpengine-phpcompat.php';
	}

	tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );
}

// Core started including their own phpunit6-compat, so we should use that if we can.
if ( ! file_exists( '/tmp/wordpress-tests-lib/includes/phpunit6/compat.php' ) ) {
	// WordPress versions before 4.8 will be incompatible with newer PHPUnit versions.
	if ( version_compare( getenv( 'WP_VERSION' ), '4.8', '<' ) && class_exists( 'PHPUnit\Runner\Version' ) ) {
		require_once dirname( __FILE__ ) . '/phpunit6-compat.php';
	}
}

require $test_root . '/includes/bootstrap.php';
