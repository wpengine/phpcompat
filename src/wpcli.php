<?php
/**
 * PHPCompat WP-CLI command.
 */

require __DIR__ . '/../vendor/autoload.php';

class PHPCompat_Command extends WP_CLI_Command {

	/**
	 * Test compatibility with different PHP versions.
	 *
	 * ## OPTIONS
	 *
	 * <version>
	 * : PHP version to test.
	 *
	 * ## EXAMPLES
	 *
	 *     wp phpcompat 5.5
	 *
	 * @synopsis <version>
	 */
	function __invoke( $args, $assoc_args )
	{
		list( $test_version ) = $args;

		WP_CLI::line("Testing compatibility with PHP " . $test_version . ".");

		$root_dir = realpath(__DIR__ . "/../");

		$wpephpc = new \WPEPHPCompat($root_dir);

		$wpephpc->cleanAfterScan();

		$wpephpc->test_version = $test_version;

		$wpephpc->only_active = "yes";

		$results = $wpephpc->startTest();

		echo $results;

		if (preg_match("/(\d*) ERRORS?/i", $results))
		{
			WP_CLI::error( "Your WordPress install is not compatible." );
		}
		else
		{
			WP_CLI::success( "Your WordPress install is compatible." );
		}
	}
}

WP_CLI::add_command( 'phpcompat', 'PHPCompat_Command' );
