<?php

/**
 * Test the primary plugin file
 *
 * @package WPEngine_PHPCompat\PHP_Compatibility_Checker
 */

namespace WPEngine_PHPCompat;

use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Filters;

/**
 * Test the main plugin file
 */
class ExcludePluginTest extends TestCase {

	protected function setUp(): void
	{
		Monkey\setUp();
		parent::setUp();
	}

	protected function tearDown(): void {

		Monkey\tearDown();
		parent::tearDown();

	}

	public function test_exclude_filter() {
		( new PHP_Compatibility_Checker() )->exclude_plugin( array( 'Name' => 'Plugin' ) );
		$this->assertTrue( Filters\applied('phpcompat_excluded_plugins') === 1 );
	}

	/** @dataProvider data_provider_for_test_exclude_plugin */
	public function test_exclude_plugin( $plugin_data, $expected )
	{
		$result = ( new PHP_Compatibility_Checker() )->exclude_plugin( $plugin_data );

		$this->assertSame( $expected, $result );
	}

	public function data_provider_for_test_exclude_plugin()
	{
		return array(
			'Shoud not exclude regular plugin' => array(
				'plugin_data' => array( 'Name' => 'Random plugin name' ),
				'expected' => false,
			),
			'Shoud exclude Hello Dolly' => array(
				'plugin_data' => array( 'Name' => 'Hello Dolly' ),
				'expected' => true,
			),
			'Shoud exclude self plugin' => array(
				'plugin_data' => array( 'Name' => 'PHP Compatibility Checker' ),
				'expected' => true,
			),
		);
	}
}
