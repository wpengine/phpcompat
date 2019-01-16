<?php
/**
 * WPEPHPCompat class
 *
 * @package WPEngine\PHPCompat
 * @since 1.0.0
 */

// Exit if this file is directly accessed.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once dirname( dirname( __FILE__ ) ) . '/load-files.php';

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
	 * @var object
	 */
	public $cli = null;

	/**
	 * Default values for PHP_CodeSniffer scan.
	 *
	 * @since 1.0.0
	 * @var array
	 */
	public $values = array();

	/**
	 * Version of PHP to test.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public $test_version = null;

	/**
	 * Scan only active plugins or all?
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public $only_active = null;

	/**
	 * The base directory for the plugin.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public $base = null;

	/**
	 *  Array of "directory name" => "latest PHP version it's compatible with".
	 *
	 *  @todo Using the directory name is brittle, we shouldn't use it.
	 *  @since 1.0.3
	 *  @var array
	 */
	public $whitelist = array(
		'*/jetpack/*'                                     => '7.0', // https://github.com/wpengine/phpcompat/wiki/Results#jetpack
		'*/wordfence/*'                                   => '7.0', // https://github.com/wpengine/phpcompat/wiki/Results#wordfence-security
		'*/woocommerce/*'                                 => '7.0', // https://github.com/wpengine/phpcompat/wiki/Results#woocommerce
		'*/wp-migrate-db/*'                               => '7.0', // https://github.com/wpengine/phpcompat/wiki/Results#wp-migrate-db
		'*/easy-digital-downloads/*'                      => '7.0', // https://github.com/wpengine/phpcompat/wiki/Results#easy-digital-downloads
		'*/updraftplus/*'                                 => '7.0',
		'*/megamenu/*'                                    => '7.0',
		'*/tablepress/*'                                  => '7.0',
		'*/myMail/*'                                      => '7.0',
		'*/wp-spamshield/*'                               => '7.0',
		'*/vendor/stripe/stripe-php/lib/StripeObject.php' => '7.0', // https://github.com/wpengine/phpcompat/issues/89
		'*/gravityforms/*'                                => '7.0', // https://github.com/wpengine/phpcompat/issues/85
		'*/download-monitor/*'                            => '7.0', // https://github.com/wpengine/phpcompat/issues/84
		'*/query-monitor/*'                               => '7.0', // https://wordpress.org/support/topic/false-positive-showing-query-monitor-as-not-php-7-compatible/
		'*/bbpress/*'                                     => '7.0', // https://wordpress.org/support/topic/false-positive-showing-bbpress-as-not-php-7-compatible/
		'*/comet-cache/*'                                 => '7.0', // https://wordpress.org/support/topic/false-positive-comet-cache/
		'*/comment-mail/*'                                => '7.0', // https://wordpress.org/support/topic/false-positive-comment-mail/
		'*/social-networks-auto-poster-facebook-twitter-g/*' => '7.0', // https://wordpress.org/plugins/social-networks-auto-poster-facebook-twitter-g/
		'*/mailpoet/*'                                    => '7.0', // https://wordpress.org/support/topic/false-positive-mailpoet-3-not-compatible-with-php7/
		'*/give/*'                                        => '7.0', // https://github.com/wpengine/phpcompat/issues/148
		'*/woocommerce-pdf-invoices-packing-slips/*'      => '7.0', // https://github.com/wpengine/phpcompat/issues/160
		'*/iwp-client/*'                                  => '7.0', // https://wordpress.org/support/topic/iwp-client-and-php-7-compatibility/
		'*/health-check/*'                                => '7.2', // https://github.com/wpengine/phpcompat/issues/179
		'*/genesis/*'                                     => '7.2', // https://github.com/wpengine/phpcompat/issues/127
		'*/wpmudev-updates/*'                             => '7.3', // https://github.com/wpengine/phpcompat/issues/178
	);

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param string $dir Base plugin directory.
	 */
	public function __construct( $dir ) {
		$this->base = $dir;
		$this->cli  = new PHP_CodeSniffer_CLI();
	}

	/**
	 * Starts the testing process.
	 *
	 * @since 1.0.0
	 */
	public function start_test() {
		$this->debug_log( 'startScan: ' . isset( $_POST['startScan'] ) );

		/**
		* Filters the scan timeout.
		*
		* Lets you change the timeout of the scan. The value is how long the scan
		* runs before dying and picking back up on a cron. You can set $timeout to
		* 0 to disable the timeout and the cron.
		*
		* @since 1.0.4
		*
		* @param int $timeout The timeout in seconds.
		*/
		$timeout = apply_filters( 'wpephpcompat_scan_timeout', MINUTE_IN_SECONDS );
		$this->debug_log( 'timeout: ' . $timeout );

		// No reason to lock if there's no timeout.
		if ( 0 !== $timeout ) {
			// Try to lock.
			$lock_result = add_option( 'wpephpcompat.lock', time(), '', 'no' );

			$this->debug_log( 'lock: ' . $lock_result );

			if ( ! $lock_result ) {
				$lock_result = get_option( 'wpephpcompat.lock' );

				// Bail if we were unable to create a lock, or if the existing lock is still valid.
				if ( ! $lock_result || ( $lock_result > ( time() - $timeout ) ) ) {
					$this->debug_log( 'Process already running (locked), returning.' );

					$timestamp = wp_next_scheduled( 'wpephpcompat_start_test_cron' );

					if ( false === (bool) $timestamp ) {
						wp_schedule_single_event( time() + $timeout, 'wpephpcompat_start_test_cron' );
					}
					return;
				}
			}
			update_option( 'wpephpcompat.lock', time(), false );
		}

		// Check to see if scan has already started.
		$scan_status = get_option( 'wpephpcompat.status' );
		$this->debug_log( 'scan status: ' . $scan_status );
		if ( ! $scan_status ) {

			// Clear the previous results.
			delete_option( 'wpephpcompat.scan_results' );

			update_option( 'wpephpcompat.status', '1', false );
			update_option( 'wpephpcompat.test_version', $this->test_version, false );
			update_option( 'wpephpcompat.only_active', $this->only_active, false );

			$this->debug_log( 'Generating directory list.' );

			// Add plugins and themes.
			$this->generate_directory_list();

			$count_jobs = wp_count_posts( 'wpephpcompat_jobs' );
			update_option( 'wpephpcompat.numdirs', $count_jobs->publish, false );
		} else {
			// Get scan settings from database.
			$this->test_version = get_option( 'wpephpcompat.test_version' );
			$this->only_active  = get_option( 'wpephpcompat.only_active' );
		}

		$args = array(
			'posts_per_page' => -1,
			'post_type'      => 'wpephpcompat_jobs',
			'orderby'        => 'title',
			'order'          => 'ASC',
		);

		$directories = get_posts( $args );
		$this->debug_log( count( $directories ) . ' plugins left to process.' );

		// If there are no directories to scan, we're finished!
		if ( ! $directories ) {
			$this->debug_log( 'No more plugins to process.' );
			update_option( 'wpephpcompat.status', '0', false );

			return;
		}
		if ( 0 !== $timeout ) {
			wp_schedule_single_event( time() + $timeout, 'wpephpcompat_start_test_cron' );
		}

		if ( ! $this->is_command_line() ) {
			/**
			 * Kill cron after a configurable timeout.
			 * Subtract 5 from the timeout if we can to avoid race conditions.
			 */
			set_time_limit( ( $timeout > 5 ? $timeout - 5 : $timeout ) );
		}

		$scan_results = get_option( 'wpephpcompat.scan_results' );

		foreach ( $directories as $directory ) {
			$this->debug_log( 'Processing: ' . $directory->post_title );

			// Add the plugin/theme name to the results.
			$scan_results .= __( 'Name', 'php-compatibility-checker' ) . ': ' . $directory->post_title . "\n\n";

			// Keep track of the number of times we've attempted to scan the plugin.
			$count = (int) get_post_meta( $directory->ID, 'count', true );
			if ( ! $count ) {
				$count = 1;
			}

			$this->debug_log( 'Attempted scan count: ' . $count );

			if ( $count > 2 ) { // If we've already tried twice, skip it.
				$scan_results .= __( 'The plugin/theme was skipped as it was too large to scan before the server killed the process.', 'php-compatibility-checker' ) . "\n\n";
				update_option( 'wpephpcompat.scan_results', $scan_results, false );
				wp_delete_post( $directory->ID );
				$count = 0;
				$this->debug_log( 'Skipped: ' . $directory->post_title );
				continue;
			}

			// Increment and save the count.
			$count++;
			update_post_meta( $directory->ID, 'count', $count );

			// Start the scan.
			$report = $this->process_file( $directory->post_content );

			if ( ! $report ) {
				$report = 'PHP ' . $this->test_version . __( ' compatible.', 'php-compatibility-checker' );
			}

			$scan_results .= $report . "\n";

			$update = get_post_meta( $directory->ID, 'update', true );

			if ( ! empty( $update ) ) {
				$version = get_post_meta( $directory->ID, 'version', true );

				$scan_results .= 'Update Available: ' . $update . '; Current Version: ' . $version . ";\n";
			}

			$scan_results .= "\n";

			update_option( 'wpephpcompat.scan_results', $scan_results, false );

			wp_delete_post( $directory->ID );
		}

		update_option( 'wpephpcompat.status', '0', false );

		$this->debug_log( 'Scan finished.' );

		return $scan_results;
	}

	/**
	 * Runs the actual PHPCompatibility test.
	 *
	 * @since 1.0.0
	 *
	 * @param string $dir Directory to scan.
	 * @return string Scan results.
	 */
	public function process_file( $dir ) {
		$this->values['files']       = $dir;
		$this->values['testVersion'] = $this->test_version;
		$this->values['standard']    = 'PHPCompatibility';
		$this->values['reportWidth'] = '9999';
		$this->values['extensions']  = array( 'php' );

		// Whitelist.
		$this->values['ignored'] = $this->generate_ignored_list();

		if ( version_compare( phpversion(), '5.3', '>=' ) && class_exists( 'PHPCompatibility\PHPCSHelper' ) ) {
			call_user_func( array( 'PHPCompatibility\PHPCSHelper', 'setConfigData' ), 'testVersion', $this->test_version, true );
		} else {
			PHP_CodeSniffer::setConfigData( 'testVersion', $this->test_version, true );
		}

		ob_start();

		$this->cli->process( $this->values );

		$report = ob_get_clean();

		return $this->clean_report( $report );
	}

	/**
	 * Generates a list of ignored files and directories.
	 *
	 * @since 1.0.3
	 *
	 * @return array An array containing files and directories that should be ignored.
	 */
	public function generate_ignored_list() {
		// Default ignored list.
		$ignored = array(
			'*/tests/*', // No reason to scan tests.
			'*/test/*', // Another common test directory.
			'*/node_modules/*', // Commonly used for development but not in production.
			'*/tmp/*', // Temporary files.
		);

		foreach ( $this->whitelist as $plugin => $version ) {
			// Check to see if the plugin is compatible with the tested version.
			if ( version_compare( $this->test_version, $version, '<=' ) ) {
				array_push( $ignored, $plugin );
			}
		}

		return apply_filters( 'phpcompat_whitelist', $ignored );
	}

	/**
	 * Generates a list of directories to scan and populate the queue.
	 *
	 * @since  1.0.0
	 */
	public function generate_directory_list() {
		if ( ! function_exists( 'get_plugins' ) ) {

			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugin_base = dirname( $this->base ) . DIRECTORY_SEPARATOR;

		$all_plugins = get_plugins();

		$update_plugins = get_site_transient( 'update_plugins' );

		foreach ( $all_plugins as $k => $v ) {
			// Exclude our plugin.
			if ( 'PHP Compatibility Checker' === $v['Name'] ) {
				continue;
			}

			// Exclude active plugins if only_active = "yes".
			if ( 'yes' === $this->only_active ) {
				// Get array of active plugins.
				$active_plugins = get_option( 'active_plugins' );

				if ( ! in_array( $k, $active_plugins, true ) ) {
					continue;
				}
			}

			$plugin_file = plugin_dir_path( $k );

			// Plugin in root directory (like Hello Dolly).
			if ( './' === $plugin_file ) {
				$plugin_path = $plugin_base . $k;
			} else {
				$plugin_path = $plugin_base . $plugin_file;
			}

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
			if ( 'yes' === $this->only_active ) {
				$current_theme = wp_get_theme();
				if ( $all_themes[ $k ]->Name !== $current_theme->Name ) {
					continue;
				}
			}

			$theme_path = $all_themes[ $k ]->theme_root . DIRECTORY_SEPARATOR . $k . DIRECTORY_SEPARATOR;

			$this->add_directory( $all_themes[ $k ]->Name, $theme_path );
		}

		// Add parent theme if the current theme is a child theme.
		if ( 'yes' === $this->only_active && is_child_theme() ) {
			$parent_theme_path = get_template_directory();
			$theme_data        = wp_get_theme();
			$parent_theme_name = $theme_data->parent()->Name;

			$this->add_directory( $parent_theme_name, $parent_theme_path );
		}
	}

	/**
	 * Cleans and formats the final report.
	 *
	 * @param  string $report The full report.
	 * @return string         The cleaned report.
	 */
	public function clean_report( $report ) {
		// Remove unnecessary overview.
		$report = preg_replace( '/Time:.+\n/si', '', $report );

		// Remove whitespace.
		$report = trim( $report );

		return $report;
	}

	/**
	 * Removes all database entries created by the scan.
	 *
	 * @since 1.0.0
	 */
	public function clean_after_scan() {
		// Delete options created during the scan.
		delete_option( 'wpephpcompat.lock' );
		delete_option( 'wpephpcompat.status' );
		delete_option( 'wpephpcompat.numdirs' );

		// Clear scheduled cron.
		wp_clear_scheduled_hook( 'wpephpcompat_start_test_cron' );

		// Make sure all directories are removed from the queue.
		$args = array(
			'posts_per_page' => -1,
			'post_type'      => 'wpephpcompat_jobs',
		);

		$directories = get_posts( $args );

		foreach ( $directories as $directory ) {
			wp_delete_post( $directory->ID );
		}
	}

	/**
	 * Adds a path to the wpephpcompat_jobs custom post type.
	 *
	 * @param string $name Plugin or theme name.
	 * @param string $path Full path to the plugin or theme directory.
	 * @return null
	 */
	private function add_directory( $name, $path ) {
		$dir = array(
			'post_title'   => $name,
			'post_content' => $path,
			'post_status'  => 'publish',
			'post_author'  => 1,
			'post_type'    => 'wpephpcompat_jobs',
		);

		return wp_insert_post( $dir );
	}

	/**
	 * Logs to the error log if WP_DEBUG is enabled.
	 *
	 * @since 1.0.0
	 *
	 * @param string $message Message to log.
	 */
	private function debug_log( $message ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG === true && ! $this->is_command_line() ) {
			if ( is_array( $message ) || is_object( $message ) ) {
				error_log( print_r( $message, true ) );
			} else {
				error_log( 'WPE PHP Compatibility: ' . $message );
			}
		}
	}

	/**
	 * Are we running on the command line?
	 *
	 * @since  1.0.0
	 * @return boolean Returns true if the request came from the command line.
	 */
	private function is_command_line() {
		return defined( 'WP_CLI' ) || defined( 'PHPUNIT_TEST' ) || php_sapi_name() === 'cli';
	}
}
