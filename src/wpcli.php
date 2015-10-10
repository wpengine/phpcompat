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
    function __invoke( $args, $assoc_args ) {
        list( $testVersion ) = $args;
		
		$root_dir = realpath(__DIR__ . "/../");
		
		$wpephpc = new \WPEPHPCompat($root_dir);
		
		$wpephpc->testVersion = $testVersion;
		
		$wpephpc->onlyActive = "yes";
		
		$wpephpc->startTest();
		
        // Print a success message
        WP_CLI::success( "Test finished!" );
    }
}

WP_CLI::add_command( 'phpcompat', 'PHPCompat_Command' );