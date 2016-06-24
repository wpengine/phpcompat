<?php
// Exit if this file is directly accessed
if ( ! defined( 'ABSPATH' ) ) exit;

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
	 * @var object
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
	public $base = null;

	/**
	 * @param string $dir Base plugin directory.
	 */
	function __construct( $dir ) {
		$this->base = $dir;
		$this->cli = new PHP_CodeSniffer_CLI();
	}

	/**
	 * Start the testing process.
	 *
	 * @since  1.0.0
	 * @return  null
	 */
	public function start_test() {

		$this->debug_log( 'startScan: ' . isset( $_POST['startScan'] ) );
		// Try to lock.
		$lock_result = add_option( 'wpephpcompat.lock', time(), '', 'no' );

		$this->debug_log( 'lock: ' . $lock_result );

		if ( ! $lock_result ) {
			$lock_result = get_option( 'wpephpcompat.lock' );

			// Bail if we were unable to create a lock, or if the existing lock is still valid.
			if ( ! $lock_result || ( $lock_result > ( time() - MINUTE_IN_SECONDS ) ) ) {
				$this->debug_log( 'Process already running (locked), returning.' );

				$timestamp = wp_next_scheduled( 'wpephpcompat_start_test_cron' );

				if ( $timestamp == false ) {
					wp_schedule_single_event( time() + ( MINUTE_IN_SECONDS ), 'wpephpcompat_start_test_cron' );
				}
				return;
			}
		}
		update_option( 'wpephpcompat.lock', time(), false );

		// Check to see if scan has already started.
		$scan_status = get_option( 'wpephpcompat.status' );
		$this->debug_log( 'scan status: ' . $scan_status );
		if ( ! $scan_status ) {

			update_option( 'wpephpcompat.status', '1', false );
			update_option( 'wpephpcompat.test_version', $this->test_version, false );
			update_option( 'wpephpcompat.only_active', $this->only_active, false );

			$this->debug_log( 'Generating directory list.' );
			//Add plugins and themes.
			$this->generate_directory_list();

			$count_jobs = wp_count_posts( 'wpephpcompat_jobs' );
			update_option( 'wpephpcompat.numdirs', $count_jobs->publish, false );
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
		$this->debug_log( count( $directories ) . ' plugins left to process.' );

		// If there are no directories to scan, we're finished!
		if ( ! $directories ) {
			$this->debug_log( 'No more plugins to process.' );
			update_option( 'wpephpcompat.status', '0', false );

			return;
		}

		wp_schedule_single_event( time() + ( MINUTE_IN_SECONDS ), 'wpephpcompat_start_test_cron' );

		$scan_results = get_option( 'wpephpcompat.scan_results' );

		foreach ( $directories as $directory ) {
			$this->debug_log( 'Processing: ' . $directory->post_title );

			$report = $this->process_file( $directory->post_content );

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

			update_option( 'wpephpcompat.scan_results', $scan_results , false );

			wp_delete_post( $directory->ID );
		}

		update_option( 'wpephpcompat.status', '0', false );

		$this->debug_log( 'Scan finished.' );

		return $scan_results;
	}

	/**
	* Runs the actual PHPCompatibility test.
	*
	* @since  1.0.0
	* @return string Scan results.
	*/
	public function process_file( $dir ) {
		$this->values['files']       = $dir;
		//$this->values['ignored'] = $this->generateIgnoreList();
		$this->values['testVersion'] = $this->test_version;
		$this->values['standard']    = 'PHPCompatibility';
		$this->values['reportWidth'] = '9999';
		$this->values['extensions']  = array( 'php' );
		$this->values['ignored'] = array( '*/tests/*' );

		PHP_CodeSniffer::setConfigData( 'testVersion', $this->test_version, true );

		ob_start();

		$this->cli->process( $this->values );

		$report = ob_get_clean();

		return $this->clean_report( $report );
	}

	/**
	* Generate a list of directories to scan and populate the queue.
	*
	* @since  1.0.0
	* @return null
	*/
	public function generate_directory_list() {
		if ( ! function_exists( 'get_plugins' ) ) {
			/**
			 * Summary.
			 */
			require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
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

			$id = $this->add_directory( $v['Name'], $plugin_path );

			if ( is_object( $update_plugins ) && is_array( $update_plugins->response ) ) {
				// Check for plugin updates.
				foreach ( $update_plugins->response as $uk => $uv ) {
					// If we have a match.
					if ( $uk === $k ) {
						$this->debug_log( 'An update exists for: ' . $v['Name'] );
						// Save the update version.
						update_post_meta( $id, 'update', $uv->new_version );
						// Save the current version.
						update_post_meta( $id, 'version', $v['Version'] );
					}
				}
			}
		}

		// Add themes.
		$all_themes = wp_get_themes();

		foreach ( $all_themes as $k => $v ) {
			if ( $this->only_active === 'yes' ) {
				$current_theme = wp_get_theme();
				if ($all_themes[$k]->Name != $current_theme->Name)
				continue;
			}

			$theme_path = $all_themes[$k]->theme_root . DIRECTORY_SEPARATOR . $k . DIRECTORY_SEPARATOR;

			$this->add_directory( $all_themes[$k]->Name, $theme_path );
		}
	}

	/**
	 * Cleans and formats the final report.
	 *
	 * @param  string $report The full report.
	 * @return string         The cleaned report.
	 */
	private function clean_report( $report ) {
		// Remove unnecessary overview.
		$report = preg_replace ( '/Time:.+\n/si', '', $report );

		// Remove whitespace.
		$report = trim( $report );

		return $report;
	}

	/**
	 * Remove all database entries created by the scan.
	 *
	 * @since  1.0.0
	 * @return null
	 */
	public function clean_after_scan() {
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
	 * @return null
	 */
	private function add_directory( $name, $path ) {
		$dir = array(
			'post_title'    => $name,
			'post_content'  => $path,
			'post_status'   => 'publish',
			'post_author'   => 1,
			'post_type'     => 'wpephpcompat_jobs'
		);

		return wp_insert_post( $dir );
	}

	/**
	 * Log to the error log if WP_DEBUG is enabled.
	 *
	 * @since  1.0.0
	 * @param  string $message Message to log.
	 * @return null
	 */
	private function debug_log( $message ){
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG === true ) {
			if ( is_array( $message ) || is_object( $message ) ) {
				error_log( print_r( $message , true ) );
			}
			else {
				error_log( 'WPE PHP Compatibility: ' . $message );
			}
		}
	}
}
