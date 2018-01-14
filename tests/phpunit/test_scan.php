<?php
class TestScan extends WP_UnitTestCase {

	function test_scan_default_PHP_55() {
		$root_dir = realpath( dirname( __FILE__ ) . '/../../' );

		$wpephpc = new WPEPHPCompat( $root_dir );

		$wpephpc->clean_after_scan();

		$wpephpc->test_version = '5.5';

		$wpephpc->only_active = 'yes';

		$results = $wpephpc->start_test();

		$this->assertContains( 'PHP 5.5 compatible.', $results );
	}

	function test_scan_default_PHP_70() {
		$root_dir = realpath( dirname( __FILE__ ) . '/../../' );

		$wpephpc = new WPEPHPCompat( $root_dir );

		$wpephpc->clean_after_scan();

		$wpephpc->test_version = '7.0';

		$wpephpc->only_active = 'yes';

		$results = $wpephpc->start_test();

		$this->assertContains( 'PHP 7.0 compatible.', $results );
	}
}
