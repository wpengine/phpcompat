<?php
require_once( __DIR__ . '/../vendor/autoload.php' );

/**
 * PHPCompat WP-CLI command.
 *
 * Description.
 *
 * @since 1.0.0
 */
class PHPCompat_Command extends WP_CLI_Command {

	/**
	 * Test compatibility with different PHP versions.
	 *
	 * ## OPTIONS
	 *
	 * <version>
	 * : PHP version to test.
	 *
	 * [--scan=<scan>]
	 * : Whether to scan only active plugins and themes or all of them.
	 * ---
	 * default: active
	 * options:
	 *   - active
	 *   - all
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp phpcompat 5.5 --scan=active
	 *
	 */
	function __invoke( $args, $assoc_args ) {
		list( $test_version ) = $args;

		WP_CLI::line( 'Testing compatibility with PHP ' . $test_version . '.' );

		$root_dir = realpath( __DIR__ . '/../' );

		$wpephpc = new \WPEPHPCompat( $root_dir );

		$wpephpc->clean_after_scan();

		$wpephpc->test_version = $test_version;

		$wpephpc->only_active = 'yes';

		$results = $wpephpc->start_test();

		echo esc_html( $results );

		if ( preg_match( '/(\d*) ERRORS?/i', $results ) ) {
			WP_CLI::error( 'Your WordPress install is not compatible.' );
		}
		else {
			WP_CLI::success( 'Your WordPress install is compatible.' );
		}
	}
}

WP_CLI::add_command( 'phpcompat', 'PHPCompat_Command' );
