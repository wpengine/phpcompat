<?php
class TestPluginActive extends WP_UnitTestCase {

	function test_plugin_activated() {
		$this->assertTrue( class_exists('WPEngine_PHPCompat') );
	}
}

