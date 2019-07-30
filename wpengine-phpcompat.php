<?php
/**
 * WPEngine_PHPCompat class
 *
 * @package WPEngine\PHPCompat
 * @since 1.0.0
 *
 * @wordpress-plugin
 * Plugin Name: PHP Compatibility Checker
 * Plugin URI:  https://wpengine.com
 * Description: Make sure your plugins and themes are compatible with newer PHP versions.
 * Author:      WP Engine
 * Version:     1.5.0
 * Author URI:  https://wpengine.com
 * Text Domain: php-compatibility-checker
 */

// Exit if this file is directly accessed.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPEPHPCOMPAT_CAPABILITY', 'manage_options' );
define( 'WPEPHPCOMPAT_ADMIN_PAGE_SLUG', 'php-compatibility-checker' );

require_once dirname( __FILE__ ) . '/load-files.php';

/**
 * This handles hooking into WordPress.
 *
 * @since 1.0.0
 */
class WPEngine_PHPCompat {

	/**
	 * Contains singleton instance.
	 *
	 * @since 1.0.0
	 * @static
	 * @var WPEngine_PHPCompat|null
	 */
	private static $instance = null;

	/**
	 * Settings page hook.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private $page;

	/**
	 * Returns an instance of this class.
	 *
	 * @since 1.0.0
	 * @static
	 *
	 * @return WPEngine_PHPCompat An instance of this class.
	 */
	public static function instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
			self::$instance->init();
		}

		return self::$instance;
	}

	/**
	 * Initializes hooks and setup environment variables.
	 *
	 * @since 0.1.0
	 * @static
	 */
	public static function init() {
		$instance = self::instance();

		// Load textdomain.
		add_action( 'init', array( $instance, 'load_textdomain' ) );

		// Build our tools page.
		add_action( 'admin_menu', array( $instance, 'create_menu' ) );

		// Load our JavaScript.
		add_action( 'admin_enqueue_scripts', array( $instance, 'admin_enqueue' ) );

		// The action to run the compatibility test.
		add_action( 'wp_ajax_wpephpcompat_start_test', array( $instance, 'start_test' ) );
		add_action( 'wp_ajax_wpephpcompat_check_status', array( $instance, 'check_status' ) );
		add_action( 'wpephpcompat_start_test_cron', array( $instance, 'start_test' ) );
		add_action( 'wp_ajax_wpephpcompat_clean_up', array( $instance, 'clean_up' ) );

		// Create custom post type.
		add_action( 'init', array( $instance, 'create_job_queue' ) );

		// Handle activation notice.
		register_activation_hook( __FILE__, array( $instance, 'set_activation_notice_flag' ) );
		add_action( 'admin_notices', array( $instance, 'maybe_show_activation_notice' ) );

		// Add plugin action link.
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $instance, 'filter_plugin_links' ) );
	}

	/**
	 * Returns an array of available PHP versions to test.
	 *
	 * @since 1.0.0
	 *
	 * @return array Associative array of available PHP versions.
	 */
	function get_phpversions() {

		$versions = array(
			'PHP 7.2' => '7.2',
			'PHP 7.1' => '7.1',
			'PHP 7.0' => '7.0',
		);

		if ( version_compare( phpversion(), '5.3', '>=' ) ) {
			$versions = array( 'PHP 7.3' => '7.3' ) + $versions;
		}

		$old_versions = array( '5.6', '5.5', '5.4', '5.3' );

		while ( ! empty( $old_versions ) ) {
			$oldest = array_pop( $old_versions );

			if ( version_compare( phpversion(), $oldest, '<' ) ) {
				array_push( $old_versions, $oldest );

				foreach ( $old_versions as $old_version ) {
					$old_version_label = "PHP {$old_version}";

					$versions[ $old_version_label ] = $old_version;
				}
				break;
			}
		}

		return apply_filters( 'phpcompat_phpversions', $versions );
	}

	/**
	 * Starts the test.
	 *
	 * @since 1.0.0
	 *
	 * @action wp_ajax_wpephpcompat_start_test
	 * @action wpephpcompat_start_test_cron
	 */
	public function start_test() {
		if ( current_user_can( WPEPHPCOMPAT_CAPABILITY ) || ( defined( 'DOING_CRON' ) && DOING_CRON ) ) {
			global $wpdb;

			$wpephpc = new WPEPHPCompat( dirname( __FILE__ ) );

			$test_data = array();
			foreach ( array( 'test_version', 'only_active' ) as $key ) {
				if ( isset( $_POST[ $key ] ) ) {
					$test_data[ $key ] = sanitize_text_field( $_POST[ $key ] );
				}
			}

			// New scan!
			if ( isset( $_POST['startScan'] ) ) {
				// Make sure we clean up after the last test.
				$wpephpc->clean_after_scan();

				// Fork so we can close the connection.
				$this->fork_scan( $test_data['test_version'], $test_data['only_active'] );
			} else {
				if ( isset( $test_data['test_version'] ) ) {
					$wpephpc->test_version = $test_data['test_version'];
				}

				if ( isset( $test_data['only_active'] ) ) {
					$wpephpc->only_active = $test_data['only_active'];
				}

				$wpephpc->start_test();
			}

			wp_die();
		}
	}

	/**
	 * Checks the progress or result of the tests.
	 *
	 * @todo Use heartbeat API.
	 * @since  1.0.0
	 *
	 * @action wp_ajax_wpephpcompat_check_status
	 */
	public function check_status() {
		if ( current_user_can( WPEPHPCOMPAT_CAPABILITY ) || ( defined( 'DOING_CRON' ) && DOING_CRON ) ) {
			$scan_status  = get_option( 'wpephpcompat.status' );
			$count_jobs   = wp_count_posts( 'wpephpcompat_jobs' );
			$total_jobs   = get_option( 'wpephpcompat.numdirs' );
			$test_version = get_option( 'wpephpcompat.test_version' );
			$only_active  = get_option( 'wpephpcompat.only_active' );

			$active_job = false;

			$jobs = get_posts(
				array(
					'posts_per_page' => -1,
					'post_type'      => 'wpephpcompat_jobs',
					'orderby'        => 'title',
					'order'          => 'ASC',
				)
			);

			if ( 0 < count( $jobs ) ) {
				$active_job = $jobs[0]->post_title;
			}

			$to_encode = array(
				'status'     => $scan_status,
				'count'      => $count_jobs->publish,
				'total'      => $total_jobs,
				'activeJob'  => $active_job,
				'version'    => $test_version,
				'onlyActive' => $only_active,
			);

			// If the scan is still running.
			if ( $scan_status ) {
				$to_encode['results']  = '0';
				$to_encode['progress'] = ( ( $total_jobs - $count_jobs->publish ) / $total_jobs ) * 100;
			} else {
				// Else return the results and clean up!
				$scan_results = get_option( 'wpephpcompat.scan_results' );
				// Not using esc_html since the results are shown in a textarea.
				$to_encode['results'] = $scan_results;

				$wpephpc = new WPEPHPCompat( dirname( __FILE__ ) );
				$wpephpc->clean_after_scan();
			}
			wp_send_json( $to_encode );
		}
	}

	/**
	 * Makes an Ajax call to start the scan in the background.
	 *
	 * @since 1.3.2
	 *
	 * @param string $test_version Version of PHP to test.
	 * @param string $only_active  Whether to scan only active plugins or all.
	 */
	public function fork_scan( $test_version, $only_active ) {
		$query = array(
			'action' => 'wpephpcompat_start_test',
		);

		// Keep track of these variables.
		$body = array(
			'test_version' => $test_version,
			'only_active'  => $only_active,
		);

		// Instantly return!
		$args = array(
			'timeout'   => 0.01,
			'blocking'  => false,
			'body'      => $body,
			'cookies'   => $_COOKIE,
			'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
		);

		// Build our URL.
		$url = add_query_arg( $query, admin_url( 'admin-ajax.php' ) );

		/**
		 * Modify the URL used to fork a request.
		 *
		 * When running in a Docker container the url used to access the site internally
		 * can be different from the external url. For example internally the port
		 * is 80, and externally it's 8081.
		 *
		 * @since 1.4.6
		 *
		 * @param string $url The url used to make the fork request.
		 */
		$url = apply_filters( 'phpcompat_fork_url', $url );
		// POST.
		wp_remote_post( esc_url_raw( $url ), $args );
	}

	/**
	 * Removes all database options from the database.
	 *
	 * @since 1.3.2
	 *
	 * @action wp_ajax_wpephpcompat_clean_up
	 */
	public function clean_up() {
		if ( current_user_can( WPEPHPCOMPAT_CAPABILITY ) || ( defined( 'DOING_CRON' ) && DOING_CRON ) ) {
			$wpephpc = new WPEPHPCompat( dirname( __FILE__ ) );
			$wpephpc->clean_after_scan();
			delete_option( 'wpephpcompat.scan_results' );
			wp_send_json( 'success' );
		}
	}

	/**
	 * Creates custom post type to store the directories we need to process.
	 *
	 * @since 1.0.0
	 */
	public function create_job_queue() {
		register_post_type(
			'wpephpcompat_jobs',
			array(
				'labels'      => array(
					'name'          => __( 'Jobs', 'php-compatibility-checker' ),
					'singular_name' => __( 'Job', 'php-compatibility-checker' ),
				),
				'public'      => false,
				'has_archive' => false,
			)
		);
	}

	/**
	 * Loads textdomain for WP < 4.6 translation support.
	 *
	 * @since 1.4.7
	 *
	 * @action admin_init
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'php-compatibility-checker' );
	}

	/**
	 * Enqueues our JavaScript and CSS.
	 *
	 * @since 1.0.0
	 *
	 * @action admin_enqueue_scripts
	 *
	 * @param string $hook Current page hook name.
	 */
	public function admin_enqueue( $hook ) {

		// Only enqueue these assets on the settings page.
		if ( $this->page !== $hook ) {
			return;
		}

		// Grab the plugin version.
		$plugin_data = get_plugin_data( __FILE__, false, false );
		if ( isset( $plugin_data['Version'] ) ) {
			$version = $plugin_data['Version'];
		}

		// Styles.
		wp_enqueue_style( 'wpephpcompat-style', plugins_url( '/src/css/style.css', __FILE__ ), array(), $version );

		// Scripts.
		wp_enqueue_script( 'wpephpcompat-handlebars', plugins_url( '/src/js/handlebars.js', __FILE__ ), array( 'jquery' ), $version );
		wp_enqueue_script( 'wpephpcompat-download', plugins_url( '/src/js/download.min.js', __FILE__ ), array(), $version );
		wp_enqueue_script( 'wpephpcompat', plugins_url( '/src/js/run.js', __FILE__ ), array( 'jquery', 'wpephpcompat-handlebars', 'wpephpcompat-download' ), $version );

		/**
		 * Strings for i18n.
		 *
		 * These translated strings can be access in jquery with window.wpephpcompat object.
		 */
		$strings = array(
			'name'       => __( 'Name', 'php-compatibility-checker' ),
			'compatible' => __( 'compatible', 'php-compatibility-checker' ),
			'are_not'    => __( 'plugins/themes may not be compatible', 'php-compatibility-checker' ),
			'is_not'     => __( 'Your WordPress site is possibly not PHP', 'php-compatibility-checker' ),
			'out_of'     => __( 'out of', 'php-compatibility-checker' ),
			'run'        => __( 'Scan site', 'php-compatibility-checker' ),
			'rerun'      => __( 'Scan site again', 'php-compatibility-checker' ),
			'your_wp'    => __( 'Your WordPress site is', 'php-compatibility-checker' ),
		);

		wp_localize_script( 'wpephpcompat', 'wpephpcompat', $strings );
	}

	/**
	 * Add the settings page to the wp-admin menu.
	 *
	 * @since 1.0.0
	 *
	 * @action admin_menu
	 */
	public function create_menu() {
		// Create Tools sub-menu.
		$this->page = add_submenu_page( 'tools.php', __( 'PHP Compatibility', 'php-compatibility-checker' ), __( 'PHP Compatibility', 'php-compatibility-checker' ), WPEPHPCOMPAT_CAPABILITY, WPEPHPCOMPAT_ADMIN_PAGE_SLUG, array( self::instance(), 'settings_page' ) );
	}

	/**
	 * Render method for the settings page.
	 *
	 * @since 1.0.0
	 */
	public function settings_page() {
		// Discover last options used.
		$test_version = get_option( 'wpephpcompat.test_version' );
		$only_active  = get_option( 'wpephpcompat.only_active' );

		// Determine if current site is a WP Engine customer.
		$is_wpe_customer = ! empty( $_SERVER['IS_WPE'] ) && $_SERVER['IS_WPE'];

		$phpversions = $this->get_phpversions();

		// Assigns defaults for the scan if none are found in the database.
		$test_version = ( ! empty( $test_version ) ) ? $test_version : '7.0';
		$only_active  = ( ! empty( $only_active ) ) ? $only_active : 'yes';

		// Content variables.
		$url_get_hosting          = esc_url( 'https://wpeng.in/5a0336/' );
		$url_wpe_agency_partners  = esc_url( 'https://wpeng.in/fa14e4/' );
		$url_wpe_customer_upgrade = esc_url( 'https://wpeng.in/407b79/' );
		$url_wpe_logo             = esc_url( 'https://wpeng.in/22f22b/' );
		$url_codeable_submit      = esc_url( 'https://codeable.io/wp-admin/admin-ajax.php?action=wp_engine_phpcompat' );

		$update_url = site_url( 'wp-admin/update-core.php', 'admin' );

		?>
		<div class="wrap wpe-pcc-wrap">
			<h1><?php _e( 'PHP Compatibility Checker', 'php-compatibility-checker' ); ?></h1>
			<div class="wpe-pcc-main">
				<p><?php _e( 'The PHP Compatibility Checker can be used on any WordPress website on any web host.', 'php-compatibility-checker' ); ?></p>
				<p><?php _e( 'This tool will lint your theme and plugin code on this site and provide you a report of compatibility issues. These issues are categorized into errors and warnings and will list the file and line number of the offending code, as well as the info about why that line of code is incompatible with the chosen version of PHP. This tool will also suggest updates to themes and plugins, as a new version may offer compatible code.', 'php-compatibility-checker' ); ?></p>
				<hr>
				<div class="wpe-pcc-scan-options">
					<h2><?php _e( 'Scan Options', 'php-compatibility-checker' ); ?></h2>
					<table class="form-table wpe-pcc-form-table">
						<tbody>
							<tr>
								<th scope="row"><label for="phptest_version"><?php _e( 'PHP Version', 'php-compatibility-checker' ); ?></label></th>
								<td>
									<fieldset>
										<?php
										foreach ( $phpversions as $name => $version ) {
											printf( '<label><input type="radio" name="phptest_version" value="%s" %s /> %s</label><br>', $version, checked( $test_version, $version, false ), $name );
										}
										?>
									</fieldset>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="active_plugins"><?php _e( 'Plugin / Theme Status', 'php-compatibility-checker' ); ?></label></th>
								<td>
									<fieldset>
										<label><input type="radio" name="active_plugins" value="yes" <?php checked( $only_active, 'yes', true ); ?> /> <?php _e( 'Only scan active plugins and themes', 'php-compatibility-checker' ); ?></label><br>
										<label><input type="radio" name="active_plugins" value="no" <?php checked( $only_active, 'no', true ); ?> /> <?php _e( 'Scan all plugins and themes', 'php-compatibility-checker' ); ?></label>
									</fieldset>
								</td>
							</tr>
							<tr>
								<th scope="row"></th>
									<td>
										<div class="wpe-pcc-run-scan">
											<input name="run" id="runButton" type="button" value="<?php _e( 'Scan site', 'php-compatibility-checker' ); ?>" class="button-secondary" />
											<div class="wpe-pcc-scan-information">
												<span style="display:none; visibility:visible;" class="spinner wpe-pcc-spinner"></span> <span id="wpe-progress-active"></span> <span style="display:none;" id="wpe-pcc-progress-count"></span>
											</div> <!-- /wpe-pcc-scan-information -->
										</div> <!-- /wpe-pcc-run-scan -->
									</td>
								</th>
							</tr>
						</tbody>
					</table>
				</div> <!-- /wpe-pcc-scan-options -->

				<div class="wpe-pcc-results" style="display:none;">
					<hr>
					<h2>
						<?php
						printf(
							/* translators: %s: PHP version number */
							__( 'Scan Results for PHP %s Compatibility', 'php-compatibility-checker' ),
							'<span class="wpe-pcc-test-version">' . $test_version . '</span>'
						);
						?>
					</h2>

					<div class="wpe-pcc-download-report" style="display:none;">
						<a id="downloadReport" class="button-primary" href="#"><span class="dashicons dashicons-download"></span> <?php _e( 'Download Report', 'php-compatibility-checker' ); ?></a>
						<a class="wpe-pcc-clear-results" name="run" id="cleanupButton"><?php _e( 'Clear results', 'php-compatibility-checker' ); ?></a>
						<label class="wpe-pcc-developer-mode">
							<input type="checkbox" id="developermode" name="developermode" value="yes" />
							<?php _e( 'View results as raw text', 'php-compatibility-checker' ); ?>
						</label>
						<hr>
					</div> <!-- /wpe-pcc-download-report -->

					<div id="wpe-pcc-standardMode"></div>

					<div style="display:none;" id="developerMode">
						<textarea readonly="readonly" id="testResults"></textarea>
					</div>

					<p class="wpe-pcc-attention">
						<?php
						printf(
							/* translators: %s: hosting URL */
							__( '<strong>Attention:</strong> Not all errors are show-stoppers. <a target="_blank" href="%s">Test this site on PHP 7</a> to see if it just works!', 'php-compatibility-checker' ),
							$url_get_hosting
						);
						?>
					</p>

				</div> <!-- /wpe-pcc-results -->

				<div class="wpe-pcc-footer">
					<hr>
					<strong><?php _e( 'Limitations &amp; Caveats', 'php-compatibility-checker' ); ?></strong>
					<ul class="wpe-pcc-bullets">
						<li><?php _e( 'This tool cannot detect unused code paths that might be used for backwards compatibility, potentially showing false positives. We maintain <a target="_blank" href="https://github.com/wpengine/phpcompat/wiki/Results">a whitelist of plugins</a> that can cause false positives.', 'php-compatibility-checker' ); ?></li>
						<li><?php _e( 'This tool does not execute your theme or plugin code, so it cannot detect runtime compatibility issues.', 'php-compatibility-checker' ); ?></li>
						<li><?php _e( 'PHP Warnings could cause compatibility issues with future PHP versions and/or spam your logs.', 'php-compatibility-checker' ); ?></li>
						<li>
							<?php
							printf(
								/* translators: %s: FAQ URL */
								__( 'The scan will get stuck if WP-Cron is not running correctly. Please <a target="_blank" href="%s">see the FAQ</a> for more information.', 'php-compatibility-checker' ),
								'https://wordpress.org/plugins/php-compatibility-checker/faq/'
							);
							?>
						</li>
					</ul>
					<p>
						<?php
						printf(
							/* translators: %s: GitHub Wiki URL */
							__( 'Report false positives <a target="_blank" href="%s">on our GitHub repo</a>.', 'php-compatibility-checker' ),
							'https://github.com/wpengine/phpcompat/wiki/Results'
						);
						?>
					</p>
				</div> <!-- /wpe-pcc-footer -->
			</div> <!-- /wpe-pcc-main -->

			<?php if ( function_exists( 'is_wpe' ) && is_wpe() ) : ?>
				<div class="wpe-pcc-aside">
					<a class="wpe-pcc-logo" href="<?php echo $url_wpe_logo; ?>" target="_blank"><svg width="182" height="34" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 268.3 51"><g fill="#40BAC8"><path d="M17.4 51h16.4V38.6l-4-4h-8.5l-3.9 4zM38.6 17.3l-3.9 3.9v8.6l3.9 3.9h12.5V17.3zM33.8 0H17.4v12.5l3.9 3.9h8.5l4-3.9zM51.1 51V38.6l-3.9-4H34.7V51zM4 0L.1 3.9v12.5h16.4V0zM34.7 0v12.5l3.9 3.9h12.5V0zM25.6 27.9c-1.3 0-2.3-1.1-2.3-2.3 0-1.3 1.1-2.3 2.3-2.3 1.3 0 2.3 1.1 2.3 2.3 0 1.2-1 2.3-2.3 2.3zM16.5 17.3H.1v16.4h12.4l4-3.9zM16.5 38.6l-4-4H.1V51h12.4l4-3.9z"/></g><g fill="#162A33"><path d="M86.2 38.6c-.3 0-.4-.1-.5-.4l-4.1-14.5h-.1l-4.1 14.5c-.1.3-.2.4-.5.4h-4.8c-.3 0-.4-.1-.5-.4l-7-25.2c0-.2 0-.4.3-.4h6.3c.3 0 .5.2.5.4L75 28.1h.1l4-15.1c.1-.3.2-.4.5-.4h3.9c.3 0 .4.1.5.4l4.2 15.1h.1L91.5 13c0-.2.2-.4.5-.4h6.3c.2 0 .3.2.3.4l-7 25.2c-.1.3-.2.4-.5.4h-4.9zM103.6 38.6c-.2 0-.4-.2-.4-.4V13c0-.2.2-.4.4-.4H114c6.3 0 9.6 3.6 9.6 8.6s-3.3 8.7-9.6 8.7h-3.8c-.2 0-.2.1-.2.2v8c0 .2-.2.4-.4.4h-6zm13.3-17.3c0-1.8-1.2-2.9-3.3-2.9h-3.4c-.2 0-.2.1-.2.2V24c0 .2.1.2.2.2h3.4c2.1 0 3.3-1.2 3.3-2.9zM132.5 32.2c-.5-1.4-.7-3.1-.7-6.5 0-3.3.3-5.1.7-6.5 1.3-4.1 4.5-6.2 8.6-6.2 4.2 0 7.3 2.1 8.6 6.2.5 1.4.7 3 .7 6.1 0 .3-.2.5-.6.5h-16.3c-.2 0-.3.2-.3.4 0 2.7.2 4.2.6 5.5 1.2 3.7 3.9 5.3 7.5 5.3 3.4 0 5.9-1.5 7.4-3.5.2-.3.5-.3.7-.1l.3.3c.3.2.3.5.1.7-1.7 2.4-4.6 4.1-8.4 4.1-4.5 0-7.5-2.1-8.9-6.3zm16.2-7.8c.2 0 .3-.1.3-.3 0-1.7-.2-3.1-.6-4.3-1.1-3.5-3.7-5.3-7.2-5.3s-6.1 1.7-7.2 5.3c-.4 1.2-.6 2.5-.6 4.3 0 .2.1.3.3.3h15zM173.6 38c-.3 0-.5-.2-.5-.5V22.9c0-5.8-2.4-8.3-7.1-8.3-4.1 0-7.5 2.8-7.5 7.6v15.4c0 .3-.2.5-.5.5h-.5c-.3 0-.5-.2-.5-.5V14.2c0-.3.2-.5.5-.5h.5c.3 0 .5.2.5.5v3.4h.1c1.2-2.8 4-4.5 7.5-4.5 5.5 0 8.6 3.1 8.6 9.4v15c0 .3-.2.5-.5.5h-.6zM182 44.3c-.2-.3-.2-.6.1-.7l.4-.3c.3-.2.5-.1.7.2 1.4 1.8 3.5 2.9 6.5 2.9 4.6 0 7.6-2.3 7.6-8.3V34h-.1c-1.2 2.7-3.3 4.6-7.6 4.6-4.1 0-6.9-2.2-8.1-5.8-.6-1.7-.8-3.9-.8-6.9 0-3 .3-5.2.8-6.9 1.2-3.6 4-5.8 8.1-5.8 4.3 0 6.4 1.9 7.6 4.6h.1v-3.5c0-.3.2-.5.5-.5h.5c.3 0 .5.2.5.5v23.9c0 6.7-3.6 9.7-9.2 9.7-3.5-.1-6.4-1.7-7.6-3.6zm14.6-12.1c.5-1.5.7-3.3.7-6.4 0-3-.2-4.9-.7-6.4-1.2-3.6-3.8-4.9-6.8-4.9-3.3 0-5.7 1.6-6.7 4.8-.5 1.5-.8 3.6-.8 6.4 0 2.8.3 4.9.8 6.4 1.1 3.2 3.4 4.8 6.7 4.8 3 .2 5.7-1.1 6.8-4.7zM207.2 6.1c-.3 0-.5-.2-.5-.5v-2c0-.3.2-.5.5-.5h1.2c.3 0 .5.2.5.5v2.1c0 .3-.2.5-.5.5h-1.2zm.4 31.9c-.3 0-.5-.2-.5-.5V14.2c0-.3.2-.5.5-.5h.5c.3 0 .5.2.5.5v23.3c0 .3-.2.5-.5.5h-.5zM233.5 38c-.3 0-.5-.2-.5-.5V22.9c0-5.8-2.4-8.3-7.1-8.3-4.1 0-7.5 2.8-7.5 7.6v15.4c0 .3-.2.5-.5.5h-.5c-.3 0-.5-.2-.5-.5V14.2c0-.3.2-.5.5-.5h.5c.3 0 .5.2.5.5v3.4h.1c1.2-2.8 4-4.5 7.5-4.5 5.5 0 8.6 3.1 8.6 9.4v15c0 .3-.2.5-.5.5h-.6zM241.4 32.2c-.5-1.4-.7-3.1-.7-6.5 0-3.3.3-5.1.7-6.5 1.3-4.1 4.5-6.2 8.6-6.2 4.2 0 7.3 2.1 8.6 6.2.5 1.4.7 3 .7 6.1 0 .3-.2.5-.6.5h-16.3c-.2 0-.3.2-.3.4 0 2.7.2 4.2.6 5.5 1.2 3.7 3.9 5.3 7.5 5.3 3.4 0 5.9-1.5 7.4-3.5.2-.3.5-.3.7-.1l.3.3c.3.2.3.5.1.7-1.7 2.4-4.6 4.1-8.4 4.1-4.5 0-7.6-2.1-8.9-6.3zm16.1-7.8c.2 0 .3-.1.3-.3 0-1.7-.2-3.1-.6-4.3-1.1-3.5-3.7-5.3-7.2-5.3s-6.1 1.7-7.2 5.3c-.4 1.2-.6 2.5-.6 4.3 0 .2.1.3.3.3h15z"/></g><g><path fill="#162A33" d="M262.3 16.1c0-1.7 1.3-3 3-3s3 1.3 3 3-1.3 3-3 3-3-1.3-3-3zm5.5 0c0-1.5-1.1-2.5-2.5-2.5-1.5 0-2.5 1.1-2.5 2.5 0 1.5 1.1 2.5 2.5 2.5s2.5-1 2.5-2.5zm-3.5 1.7c-.1 0-.1 0-.1-.1v-3.1c0-.1 0-.1.1-.1h1.2c.7 0 1.1.4 1.1 1 0 .4-.2.8-.7.9l.7 1.3c.1.1 0 .2-.1.2h-.3c-.1 0-.1-.1-.2-.1l-.7-1.3h-.7v1.2c0 .1-.1.1-.1.1h-.2zm1.8-2.4c0-.3-.2-.5-.6-.5h-.8v1h.8c.4 0 .6-.2.6-.5z"/></g></svg></a>

					<div class="wpe-pcc-get-hosting">
						<div class="wpe-pcc-aside-content">
							<?php if ( $is_wpe_customer ) : ?>
								<h2><?php _e( 'Make your site 2x faster by upgrading to PHP 7', 'php-compatibility-checker' ); ?></h2>
							<?php else : ?>
								<h2><?php _e( 'Make your site 2x faster with PHP 7 WordPress hosting', 'php-compatibility-checker' ); ?></h2>
							<?php endif; ?>

							<p><?php _e( 'Speed up your site and improve your conversion opportunities by upgrading to PHP 7 on the WP Engine platform.', 'php-compatibility-checker' ); ?></p>

							<?php if ( $is_wpe_customer ) : ?>
								<a target="_blank" class="wpe-pcc-button wpe-pcc-button-primary" href="<?php echo $url_wpe_customer_upgrade; ?>"><?php _e( 'Upgrade to PHP 7 for free', 'php-compatibility-checker' ); ?></a>
							<?php else : ?>
								<a target="_blank" class="wpe-pcc-button wpe-pcc-button-primary" href="<?php echo $url_get_hosting; ?>"><?php _e( 'Get PHP 7 Hosting', 'php-compatibility-checker' ); ?></a>
								<p><?php _e( 'Already a WP Engine customer?', 'php-compatibility-checker' ); ?> <a target="_blank" href="<?php echo $url_wpe_customer_upgrade; ?>"><?php _e( 'Click here to upgrade to PHP 7', 'php-compatibility-checker' ); ?></a></p>
							<?php endif; ?>

						</div> <!-- /wpe-pcc-aside-content -->
					</div> <!-- /wpe-pcc-get-hosting -->

					<div style="display:none;" class="wpe-pcc-information wpe-pcc-information-errors">
						<div class="wpe-pcc-aside-content">
							<h2><?php _e( 'Need help making this site PHP 7 compatible?', 'php-compatibility-checker' ); ?></h2>
							<div class="wpe-pcc-dev-helper">
								<p class="title"><strong><?php _e( 'Get help from WP Engine partners', 'php-compatibility-checker' ); ?></strong></p>
								<p><?php _e( 'Our agency partners can help make your site PHP 7 compatible.', 'php-compatibility-checker' ); ?></p>
								<a target="_blank" class="wpe-pcc-button" href="<?php echo $url_wpe_agency_partners; ?>"><?php _e( 'Find a WP Engine Partner', 'php-compatibility-checker' ); ?></a>
							</div> <!-- /wpe-pcc-dev-helper -->

							<div class="wpe-pcc-dev-helper">
								<p class="title"><strong><?php _e( 'Get PHP 7 ready with Codeable', 'php-compatibility-checker' ); ?></strong></p>
								<p><?php _e( 'Automatically submit this error report to Codeable to get a quick quote from their vetted WordPress developers.', 'php-compatibility-checker' ); ?></p>
								<form target="_blank" action="<?php echo add_query_arg( array( 'action' => 'wp_engine_phpcompat' ), $url_codeable_submit ); ?>" method="POST">
									<input type="hidden" name="data" value="<?php echo base64_encode( get_option( 'wpephpcompat.scan_results' ) ); ?>" />
									<input type="submit" class="wpe-pcc-button" value="<?php _e( 'Submit to Codeable', 'php-compatibility-checker' ); ?>" />
								</form>
							</div> <!-- /wpe-pcc-dev-helper -->
						</div> <!-- /wpe-pcc-aside-content -->
					</div> <!-- /wpe-pcc-information -->

				</div> <!-- /wpe-pcc-aside -->
			<?php endif; ?>

		</div> <!-- /wpe-pcc-wrap -->

		<script id="result-template" type="text/x-handlebars-template">
			<div class="wpe-pcc-alert wpe-pcc-alert-{{#if skipped}}skipped{{else if passed}}passed{{else}}error{{/if}}">
				<p>
					<span class="dashicons-before dashicons-{{#if errors}}no{{else if skipped}}editor-help{{else}}yes{{/if}}"></span>
					<strong>{{plugin_name}} </strong> -
					<span class="wpe-pcc-alert-status">
						{{#if skipped}}
							<span class="wpe-pcc-badge wpe-pcc-badge-skipped"><?php _e( 'Unknown', 'php-compatibility-checker' ); ?></span>
						{{else}}
							{{#if passed}}
								<span class="wpe-pcc-badge wpe-pcc-badge-passed"><?php _e( 'Compatible', 'php-compatibility-checker' ); ?></span>
							{{/if}}
							{{#if warnings}}
								<span class="wpe-pcc-badge wpe-pcc-badge-warnings"><?php _e( 'Warnings:', 'php-compatibility-checker' ); ?> <strong>{{warnings}}</strong></span>
							{{/if}}
							{{#if errors}}
								<span class="wpe-pcc-badge wpe-pcc-badge-errors"><?php _e( 'Errors:', 'php-compatibility-checker' ); ?> <strong>{{errors}}</strong></span>
							{{/if}}
						{{/if}}
						</span>
						{{#if updateAvailable}}
							(<a href="<?php echo esc_url( $update_url ); ?>"><?php _e( 'Update Available', 'php-compatibility-checker' ); ?></a>)
						{{/if}}
					<a class="wpe-pcc-alert-details" href="#"><?php _e( 'toggle details', 'php-compatibility-checker' ); ?></a>
					<textarea class="wpe-pcc-alert-logs hide">{{logs}}</textarea>
				</p>
			</div> <!-- /wpe-pcc-alert -->
		</script>
		<?php
	}

	/**
	 * Sets the activation notice flag so that it is shown in the admin.
	 *
	 * @since 1.4.4
	 */
	public function set_activation_notice_flag() {
		add_option( 'wpephpcompat.show_notice', true );
	}

	/**
	 * Shows the activation notice if the flag for it is set.
	 *
	 * @since 1.4.4
	 */
	public function maybe_show_activation_notice() {
		$option = get_option( 'wpephpcompat.show_notice' );

		if ( ! $option ) {
			return;
		}

		delete_option( 'wpephpcompat.show_notice' );

		if ( ! current_user_can( WPEPHPCOMPAT_CAPABILITY ) ) {
			return;
		}

		$url = add_query_arg( 'page', WPEPHPCOMPAT_ADMIN_PAGE_SLUG, admin_url( 'tools.php' ) );

		?>
		<div class="notice updated is-dismissible">
			<p>
				<?php
				printf(
					/* translators: %s: URL to admin page */
					__( 'You have just activated the <strong>PHP Compatibility Checker</strong>. <a href="%s">Start scanning your plugins and themes for compatibility with the latest PHP versions now!</a>', 'php-compatibility-checker' ),
					esc_url( $url )
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Adds a link to the admin page to the plugin action links.
	 *
	 * @since 1.4.4
	 *
	 * @param array $links Plugin action links.
	 * @return array Modified plugin action links.
	 */
	public function filter_plugin_links( $links ) {
		if ( current_user_can( WPEPHPCOMPAT_CAPABILITY ) ) {
			$url = add_query_arg( 'page', WPEPHPCOMPAT_ADMIN_PAGE_SLUG, admin_url( 'tools.php' ) );

			array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Start Scan', 'php-compatibility-checker' ) . '</a>' );
		}

		return $links;
	}
}

// Register the WPEngine_PHPCompat instance.
WPEngine_PHPCompat::init();
