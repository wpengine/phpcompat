<?php
/**
 * Summary.
 */
require_once ( __DIR__ . '/../vendor/autoload.php' );

/**
 * Summary.
 *
 * Description.
 *
 * @since 1.0.0
 */
class WPEPHPCompat {
	/**
	 * The PHP_CodeSniffer_CLI object.
	 *
	 * @since 1.0.0
	 * @access public
	 * @var class
	 */
	public $cli = null;

	/**
	 * Default values for PHP_CodeSniffer scan.
	 *
	 * @since 1.0.0
	 * @access public
	 * @var array
	 */
	public $values = array();

	/**
	 * Version of PHP to test.
	 *
	 * @since 1.0.0
	 * @access public
	 * @var string
	 */
	public $test_version = null;

	/**
	 * Summary.
	 *
	 * @since 1.0.0
	 * @access public
	 * @var string
	 */
	public $only_active = null;

	/**
	 * Summary.
	 *
	 * @since 1.0.0
	 * @access public
	 * @var string
	 */
	public $lock_name = 'wpephpcompat.lock';

	/**
	 * Summary.
	 *
	 * @since 1.0.0
	 * @access public
	 * @var string
	 */
	public $scan_status_name = 'wpephpcompat.status';

	/**
	 * Summary.
	 *
	 * @since 1.0.0
	 * @access public
	 * @var string
	 */
	public $base = null;

	/**
	 * Summary.
	 *
	 * @param [type] $dir [description]
	 */
	function __construct( $dir ) {
		$this->base = $dir;
		$this->cli = new PHP_CodeSniffer_CLI();
	}

	/**
	 * Start the testing process.
	 *
	 * @since  1.0.0
	 * @todo Return the results instead of echoing.
	 * @return  null
	 */
	public function start_test() {

		$this->debugLog( 'startScan: ' . isset( $_POST['startScan'] ) );
		// Try to lock.
		$lock_result = add_option( 'wpephpcompat.lock', time(), '', 'no' );

		$this->debugLog( 'lock: ' . $lock_result );

		if ( ! $lock_result ) {
			$lock_result = get_option( 'wpephpcompat.lock' );

			// Bail if we were unable to create a lock, or if the existing lock is still valid.
			if ( ! $lock_result || ( $lock_result > ( time() - MINUTE_IN_SECONDS ) ) ) {
				$this->debugLog( 'Process already running (locked), returning.' );

				$timestamp = wp_next_scheduled( 'wpephpcompat_start_test_cron' );

				if ( $timestamp == false ) {
					wp_schedule_single_event( time() + ( MINUTE_IN_SECONDS ), 'wpephpcompat_start_test_cron' );
				}
				return;
			}
		}
		update_option( 'wpephpcompat.lock', time() );

		// Check to see if scan has already started.
		$scan_status = get_option( 'wpephpcompat.status' );
		$this->debugLog( 'scan status: ' . $scan_status );
		if ( ! $scan_status ) {
			$this->debugLog( 'Generating directory list.' );
			//Add plugins and themes.
			$this->generateDirectoryList();

			add_option( 'wpephpcompat.status', '1' );
			add_option( 'wpephpcompat.test_version', $this->test_version );
			add_option( 'wpephpcompat.only_active', $this->only_active );

			$count_jobs = wp_count_posts( 'wpephpcompat_jobs' );
			add_option( 'wpephpcompat.numdirs', $count_jobs->publish );
		} else {
			// Get scan settings from database.
			$this->test_version = get_option( 'wpephpcompat.test_version' );
			$this->only_active = get_option( 'wpephpcompat.only_active' );
		}

		$args = array(
			'posts_per_page' => -1,
			'post_type'      => 'wpephpcompat_jobs'
		);
		$directories = get_posts( $args );
		$this->debugLog( count( $directories ) . ' plugins left to process.' );

		// If there are no directories to scan, we're finished!
		if ( ! $directories ) {
			$this->debugLog( 'No more plugins to process.' );
			$this->cleanAfterScan();

			return;
		}

		wp_schedule_single_event( time() + ( MINUTE_IN_SECONDS ), 'wpephpcompat_start_test_cron' );

		$scan_results = get_option( 'wpephpcompat.scan_results' );

		foreach ( $directories as $directory ) {
			$this->debugLog( 'Processing: ' . $directory->post_title );

			$report = $this->processFile( $directory->post_content );

			if ( ! $report ) {
				$report = 'PHP ' . $this->test_version . ' compatible.';
			}

			$scan_results .= 'Name: ' . $directory->post_title . "\n\n" . $report . "\n";

			$update = get_post_meta( $directory->ID, 'update', true );

			if ( ! empty( $update ) ) {
				$version = get_post_meta( $directory->ID, 'version', true );
				$scan_results .= 'Update Available: ' . $update . '; Current Version: ' . $version . ";\n";
			}

			$scan_results .= "\n";

			update_option( 'wpephpcompat.scan_results', $scan_results );

			wp_delete_post( $directory->ID );
		}

		update_option( 'wpephpcompat.status', '0' );

		$this->debugLog( 'Scan finished.' );

		return;
	}

	/**
	* Runs the actual PHPCompatibility test.
	* @since  1.0.0
	* @return string Scan results.
	*/
	public function processFile( $dir ) {
		$this->values['files']       = $dir;
		//$this->values['ignored'] = $this->generateIgnoreList();
		$this->values['testVersion'] = $this->test_version;
		$this->values['standard']    = 'PHPCompatibility';
		$this->values['reportWidth'] = '9999';
		$this->values['extensions']  = array( 'php' );

		PHP_CodeSniffer::setConfigData( 'testVersion', $this->test_version, true );

		ob_start();

		$this->cli->process( $this->values );

		$report = ob_get_clean();

		return $this->cleanReport( $report );
	}

	/**
	* Generate a list of directories to scan and populate the queue.
	* @since  1.0.0
	* @return  null
	*/
	public function generateDirectoryList() {
		if ( ! function_exists( 'get_plugins' ) ) {
			/**
			 * Summary.
			 */
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugin_base = dirname( $this->base ) . DIRECTORY_SEPARATOR;

		$all_plugins = get_plugins();

		$update_plugins = get_site_transient( 'update_plugins' );

		foreach ( $all_plugins as $k => $v ) {
			//Exclude our plugin.
			if ( $v['Name'] === 'WP Engine PHP Compatibility' ) {
				continue;
			}

			// Exclude active plugins if only_active = "yes".
			if ( $this->only_active === 'yes' ) {
				// Get array of active plugins.
				$active_plugins = get_option( 'active_plugins' );

				if ( ! in_array( $k, $active_plugins ) ) {
					continue;
				}
			}

			$plugin_path = $plugin_base . plugin_dir_path( $k );

			$id = $this->addDirectory( $v['Name'], $plugin_path );

			//Check for plugin updates.
			foreach ( $update_plugins->response as $uk => $uv ) {
				//If we have a match.
				if ( $uk === $k ) {
					$this->debugLog( 'An update exists for: ' . $v['Name'] );
					//Save the update version.
					update_post_meta( $id, 'update', $uv->new_version );
					//Save the current version.
					update_post_meta( $id, 'version', $v['Version'] );
				}
			}
		}

		//Add themes.
		$all_themes = wp_get_themes();

		foreach ( $all_themes as $k => $v ) {
			if ( $this->only_active === 'yes' ) {
				$current_theme = wp_get_theme();
				if ($all_themes[$k]->Name != $current_theme->Name)
				continue;
			}

			$theme_path = $all_themes[$k]->theme_root . DIRECTORY_SEPARATOR . $k . DIRECTORY_SEPARATOR;

			$this->addDirectory( $all_themes[$k]->Name, $theme_path );
		}
	}

	/**
	 * Cleans and formats the final report.
	 *
	 * @param  string $report The full report.
	 * @return string         The cleaned report.
	 */
	private function cleanReport( $report ) {
		//Remove unnecessary overview.
		$report = preg_replace ( '/Time:.+\n/si', '', $report );

		//Remove whitespace.
		$report = trim( $report );

		return $report;
	}

	/**
	 * Remove all database entries created by the scan.
	 *
	 * @since  1.0.0
	 * @return THING
	 */
	public function cleanAfterScan() {
		// Delete options created during the scan.
		delete_option( 'wpephpcompat.lock' );
		delete_option( 'wpephpcompat.status' );
		delete_option( 'wpephpcompat.scan_results' );
		delete_option( 'wpephpcompat.test_version' );
		delete_option( 'wpephpcompat.only_active' );
		delete_option( 'wpephpcompat.numdirs' );

		// Clear scheduled cron.
		wp_clear_scheduled_hook( 'wpephpcompat_start_test_cron' );

		//Make sure all directories are removed from the queue.
		$args = array(
			'posts_per_page' => -1,
			'post_type'      => 'wpephpcompat_jobs'
		);
		$directories = get_posts( $args );

		foreach ( $directories as $directory ) {
			wp_delete_post( $directory->ID );
		}
	}

	/**
	 * Add a path to the wpephpcompat_jobs custom post type.
	 *
	 * @param string $name Plugin or theme name.
	 * @param string $path Full path to the plugin or theme directory.
	 * @return THING
	 */
	private function addDirectory( $name, $path ) {
		$dir = array(
			'post_title'    => $name,
			'post_content'  => $path,
			'post_status'   => 'publish',
			'post_author'   => 1,
			'post_type'	    => 'wpephpcompat_jobs'
		);

		return wp_insert_post( $dir );
	}

	/**
	 * Log to the error log if WP_DEBUG is enabled.
	 *
	 * @since  1.0.0
	 * @param  string $message Message to log.
	 */
	private function debugLog( $message ){
		if ( WP_DEBUG === true ) {
			if ( is_array( $message ) || is_object( $message ) ) {
				error_log( print_r( $message , true ) );
			}
			else {
				error_log( 'WPE PHP Compatibility: ' . $message );
			}
		}
	}
}
