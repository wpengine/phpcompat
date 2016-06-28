<?php
class TestScan extends WP_UnitTestCase {

	function test_scan_default_PHP_55() {
		$expected_results = "Name: Twenty Sixteen

PHP 5.5 compatible.

";
		$root_dir = realpath( __DIR__ . '/../../' );

		$wpephpc = new \WPEPHPCompat( $root_dir );

		$wpephpc->clean_after_scan();

		$wpephpc->test_version = '5.5';

		$wpephpc->only_active = 'yes';

		$results = $wpephpc->start_test();

		$this->assertEquals( $expected_results, $results );
	}

	function test_scan_default_PHP_70() {
		$expected_results = "Name: Twenty Sixteen

PHP 7.0 compatible.

";
		$root_dir = realpath( __DIR__ . '/../../' );

		$wpephpc = new \WPEPHPCompat( $root_dir );

		$wpephpc->clean_after_scan();

		$wpephpc->test_version = '7.0';

		$wpephpc->only_active = 'yes';

		$results = $wpephpc->start_test();

		$this->assertEquals( $expected_results, $results );
	}
}
