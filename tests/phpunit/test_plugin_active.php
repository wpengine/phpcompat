<?php

use PHPUnit\Framework\TestCase;

class TestPluginActive extends TestCase {

	function test_plugin_activated() {
		$this->assertTrue( class_exists('WPEngine_PHPCompat') );
	}
}

