<?php 
use PHPUnit\Framework\TestCase as TestCase;

class TestGenerateIgnoredList extends TestCase {
	
	private $wpephpc;
	
	public function setUp()
	{
		$root_dir = realpath( dirname( __FILE__ ) . '/../../' );

		$this->wpephpc = new WPEPHPCompat( $root_dir );
		
		$this->wpephpc->whitelist = array(
			'*/jetpack/*' => '7.0',
			'*/fakeplugin/*' => '5.3',
			'*/reallyfake/*' => '5.5',
			'*/oldplugin/*' => '5.2',
		);
	}

	function test_generate_ignored_list_default() {
		$this->wpephpc->test_version = '7.0';
		
		$ignored = $this->wpephpc->generate_ignored_list();
		
		$this->assertContains( '*/tests/*', $ignored );
		$this->assertContains( '*/tmp/*', $ignored );
	}
	
	function test_generate_ignored_list_version_70() {
		$this->wpephpc->test_version = '7.0';
		
		$ignored = $this->wpephpc->generate_ignored_list();
		
		$this->assertContains( '*/jetpack/*', $ignored );
		$this->assertNotContains( '*/fakeplugin/*', $ignored );
		$this->assertNotContains( '*/reallyfake/*', $ignored );
		$this->assertNotContains( '*/oldplugin/*', $ignored );

	}
	
	function test_generate_ignored_list_version_71() {
		$this->wpephpc->test_version = '7.1';
		
		$ignored = $this->wpephpc->generate_ignored_list();
		
		$this->assertNotContains( '*/jetpack/*', $ignored );
		$this->assertNotContains( '*/fakeplugin/*', $ignored );
		$this->assertNotContains( '*/reallyfake/*', $ignored );
		$this->assertNotContains( '*/oldplugin/*', $ignored );
	}
	
	function test_generate_ignored_list_version_53() {
		$this->wpephpc->test_version = '5.3';
		
		$ignored = $this->wpephpc->generate_ignored_list();
		
		$this->assertContains( '*/jetpack/*', $ignored );
		$this->assertContains( '*/fakeplugin/*', $ignored );
		$this->assertContains( '*/reallyfake/*', $ignored );
		$this->assertNotContains( '*/oldplugin/*', $ignored );
	}
	
	function test_generate_ignored_list_version_55() {
		$this->wpephpc->test_version = '5.5';
		
		$ignored = $this->wpephpc->generate_ignored_list();
		
		$this->assertContains( '*/jetpack/*', $ignored );
		$this->assertContains( '*/reallyfake/*', $ignored );
		$this->assertNotContains( '*/fakeplugin/*', $ignored );
		$this->assertNotContains( '*/oldplugin/*', $ignored );
	}

	function test_filter_ignored_list() {
		tests_add_filter( 'phpcompat_whitelist', array( $this, '_filter_whitelist' ) );

		$ignored = $this->wpephpc->generate_ignored_list();
		
		$this->assertContains( '*/filterplugin/*', $ignored );
	}

	function _filter_whitelist( $whitelist ) {
		return array_merge( $whitelist, array( '*/filterplugin/*') );
	}
}
