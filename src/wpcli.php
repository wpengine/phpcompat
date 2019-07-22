<?php
/**
 * PHPCompat_Command class
 *
 * @package WPEngine\PHPCompat
 * @since 1.0.0
 */

require_once dirname( dirname( __FILE__ ) ) . '/load-files.php';

/**
 * PHPCompat WP-CLI command.
 *
 * Test compatibility with different PHP versions.
 *
 * @since 1.0.0
 */
class PHPCompat_Command extends WP_CLI_Command {

	/**
	 * Test compatibility with different PHP versions.
	 *
	 * ## EXAMPLES
	 *
	 *     wp phpcompat 5.5 --scan=active
	 */
	function __invoke( $args, $assoc_args ) {

		// Get the PHP test version.
		$test_version = $args[0];

		WP_CLI::log( 'Testing compatibility with PHP ' . $test_version . '.' );
		// Add empty line.
		WP_CLI::log( '' );

		$root_dir = realpath( dirname( dirname( __FILE__ ) ) . '/' );

		$wpephpc = new WPEPHPCompat( $root_dir );

		$wpephpc->clean_after_scan();

		$wpephpc->test_version = $test_version;

		// Set scan type if 'scan' was passed in.
		if ( isset( $assoc_args['scan'] ) && 'active' === $assoc_args['scan'] ) {
			$wpephpc->only_active = 'yes';
		}

		$results = $wpephpc->start_test();

		WP_CLI::log( $results );

		$wpephpc->clean_after_scan();
		delete_option( 'wpephpcompat.scan_results' );

		if ( preg_match( '/(\d*) ERRORS?/i', $results ) ) {
			WP_CLI::error( 'Your WordPress install is not compatible.' );
		} else {
			WP_CLI::success( 'Your WordPress install is compatible.' );
		}
	}
}

/**
 *  Using this for now since there are issues with the PHPDoc syntax.
 *  TODO: Use PHPDoc syntax.
 */
WP_CLI::add_command(
	'phpcompat',
	'PHPCompat_Command',
	array(
		'shortdesc' => 'Test compatibility with different PHP versions.',
		'synopsis'  => array(
			array(
				'type'     => 'positional',
				'name'     => 'version',
				'optional' => false,
				'multiple' => false,
			),
			array(
				'type'     => 'assoc',
				'name'     => 'scan',
				'optional' => true,
				'default'  => 'active',
				'options'  => array( 'active', 'all' ),
			),
		),
	)
);
