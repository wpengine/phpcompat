<?php
/*
Plugin Name: PHP Compatibility Checker
Plugin URI: https://wpengine.com
Description: Make sure your plugins and themes are compatible with newer PHP versions.
Author: WP Engine
Version: 1.3.2
Author URI: https://wpengine.com
Text Domain: php-compatibility-checker
*/

// Exit if this file is directly accessed
if ( ! defined( 'ABSPATH' ) ) { exit; }

require_once( __DIR__ . '/vendor/autoload.php' );

// Add the phpcompat WP-CLI command.
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once( __DIR__ . '/src/wpcli.php' );
}

/**
 * This handles hooking into WordPress.
 */
class WPEngine_PHPCompat {

	/* Define and register singleton */
	private static $instance = false;

	/* Hook for the settings page  */
	private $page;

	/**
	 * Returns an instance of this class.
	 *
	 * @return self An instance of this class.
	 */
	public static function instance() {
		if ( ! self::$instance ) {
			self::$instance = new self;
			self::$instance->init();
		}
		return self::$instance;
	}

	/**
	 * Initialize hooks and setup environment variables.
	 *
	 * @since 0.1.0
	 */
	public static function init() {

		// Build our tools page.
		add_action( 'admin_menu', array( self::instance(), 'create_menu' ) );

		// Load our JavaScript.
		add_action( 'admin_enqueue_scripts', array( self::instance(), 'admin_enqueue' ) );

		// The action to run the compatibility test.
		add_action( 'wp_ajax_wpephpcompat_start_test', array( self::instance(), 'start_test' ) );
		add_action( 'wp_ajax_wpephpcompat_check_status', array( self::instance(), 'check_status' ) );
		add_action( 'wpephpcompat_start_test_cron', array( self::instance(), 'start_test' ) );
		add_action( 'wp_ajax_wpephpcompat_clean_up', array( self::instance(), 'clean_up' ) );

		// Create custom post type.
		add_action( 'init', array( self::instance(), 'create_job_queue' ) );
	}

	/**
	 * Return an array of available PHP versions to test.
	 */
	function get_phpversions() {

		return apply_filters( 'phpcompat_phpversions', array(
			'PHP 7.0' => '7.0',
			'PHP 5.6' => '5.6',
			'PHP 5.5' => '5.5',
			'PHP 5.4' => '5.4',
			'PHP 5.3' => '5.3',
		));
	}

	/**
	 * Start the test!
	 *
	 * @since  1.0.0
	 * @action wp_ajax_wpephpcompat_start_test
	 * @action wpephpcompat_start_test_cron
	 * @return null
	 */
	function start_test() {
		if ( current_user_can( 'manage_options' ) || ( defined( 'DOING_CRON' ) && DOING_CRON ) ) {
			global $wpdb;

			$wpephpc = new \WPEPHPCompat( __DIR__ );

			if ( isset( $_POST['startScan'] ) ) {
				$test_version = sanitize_text_field( $_POST['test_version'] );
				$only_active = sanitize_text_field( $_POST['only_active'] );

				$wpephpc->test_version = $test_version;
				$wpephpc->only_active = $only_active;
				$wpephpc->clean_after_scan();
			}

			$wpephpc->start_test();
			wp_die();
		}
	}

	/**
	 * Check the progress or result of the tests.
	 *
	 * @todo Use heartbeat API.
	 * @since  1.0.0
	 * @action wp_ajax_wpephpcompat_check_status
	 * @return null
	 */
	function check_status() {
		if ( current_user_can( 'manage_options' ) || ( defined( 'DOING_CRON' ) && DOING_CRON ) ) {
			$scan_status = get_option( 'wpephpcompat.status' );
			$count_jobs = wp_count_posts( 'wpephpcompat_jobs' );
			$total_jobs = get_option( 'wpephpcompat.numdirs' );
			$test_version = get_option( 'wpephpcompat.test_version' );
			$only_active = get_option( 'wpephpcompat.only_active' );

			$active_job = false;
			$jobs = get_posts( array(
				'posts_per_page' => -1,
				'post_type'      => 'wpephpcompat_jobs',
				'orderby'        => 'title',
				'order'          => 'ASC',
			) );

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
				$to_encode['results'] = '0';
				$to_encode['progress'] = ( ( $total_jobs - $count_jobs->publish ) / $total_jobs) * 100;
			} else {
				// Else return the results and clean up!
				$scan_results = get_option( 'wpephpcompat.scan_results' );
				// Not using esc_html since the results are shown in a textarea.
				$to_encode['results'] = $scan_results;

				$wpephpc = new \WPEPHPCompat( __DIR__ );
				$wpephpc->clean_after_scan();
			}
			wp_send_json( $to_encode );
		}
	}

	/**
	 * Remove all database options from the database.
	 *
	 * @since 1.3.2
	 * @action wp_ajax_wpephpcompat_clean_up
	 */
	function clean_up() {
		if ( current_user_can( 'manage_options' ) || ( defined( 'DOING_CRON' ) && DOING_CRON ) ) {
			$wpephpc = new \WPEPHPCompat( __DIR__ );
			$wpephpc->clean_after_scan();
			delete_option( 'wpephpcompat.scan_results' );
			wp_send_json( 'success' );
		}
	}

	/**
	 * Create custom post type to store the directories we need to process.
	 *
	 * @since 1.0.0
	 * @return  null
	 */
	function create_job_queue() {
		register_post_type( 'wpephpcompat_jobs',
			array(
				'labels' => array(
					'name' => __( 'Jobs' ),
					'singular_name' => __( 'Job' ),
				),
			'public' => false,
			'has_archive' => false,
			)
		);
	}

	/**
	 * Enqueue our JavaScript and CSS.
	 *
	 * @since 1.0.0
	 * @action admin_enqueue_scripts
	 * @return  null
	 */
	function admin_enqueue( $hook ) {

		// Only enqueue these assets on the settings page.
		if ( $this->page !== $hook ) {
			return;
		}

		// Styles
		wp_enqueue_style( 'wpephpcompat-style', plugins_url( '/src/css/style.css', __FILE__ ) );

		// Scripts
		wp_enqueue_script( 'wpephpcompat-handlebars', plugins_url( '/src/js/handlebars.js', __FILE__ ), array( 'jquery' ) );
		wp_enqueue_script( 'wpephpcompat-download', plugins_url( '/src/js/download.min.js', __FILE__ ) );
		wp_enqueue_script( 'wpephpcompat', plugins_url( '/src/js/run.js', __FILE__ ), array( 'jquery', 'wpephpcompat-handlebars', 'wpephpcompat-download' ) );

		// Progress Bar
		wp_enqueue_script( 'jquery-ui-progressbar' );
		wp_enqueue_style( 'jquery-style', '//ajax.googleapis.com/ajax/libs/jqueryui/1.8.2/themes/smoothness/jquery-ui.css' );

		/**
		 * i18n strings
		 *
		 * These translated strings can be access in jquery with window.wpephpcompat object.
		 */
		$strings = array(
			'name'       => __( 'Name', 'php-compatibility-checker' ),
			'compatible' => __( 'compatible', 'php-compatibility-checker' ),
			'are_not'    => __( 'plugins/themes are possibly not compatible', 'php-compatibility-checker' ),
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
	 * @action admin_menu
	 * @return null
	 */
	function create_menu() {
		// Create Tools sub-menu.
		$this->page = add_submenu_page( 'tools.php', __( 'PHP Compatibility', 'php-compatibility-checker' ), __( 'PHP Compatibility', 'php-compatibility-checker' ), 'manage_options', 'php-compatibility-checker', array( self::instance(), 'settings_page' ) );
	}

	/**
	 * Render method for the settings page.
	 *
	 * @since 1.0.0
	 * @return null
	 */
	function settings_page() {
		// Discovers last options used.
		$test_version = get_option( 'wpephpcompat.test_version' );
		$only_active = get_option( 'wpephpcompat.only_active' );

		$phpversions = $this->get_phpversions();

		// Assigns defaults for the scan if none are found in the database.
		$test_version = ( false !== $test_version ) ? $test_version : '7.0';
		$only_active = ( false !== $only_active ) ? $only_active : 'yes';
		?>
		<div class="wrap wpe-pcc-wrap">
			<h1><?php _e( 'PHP Compatibility Checker' ) ?></h1>
			<div class="wpe-pcc-main">
				<p><?php printf( __(' Developed by <a href="%1$s">WP Engine</a>', 'php-compatibility-checker'), 'https://wpengine.com/' ); ?></p>
				<p><?php _e(' Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.', 'php-compatibility-checker' ); ?></p>
				<hr>
				<div class="wpe-pcc-scan-options">
					<h2><?php printf( 'Scan Options' ); ?></h2>
					<table class="form-table wpe-pcc-form-table">
						<tbody>
							<tr>
								<th scope="row"><label for="phptest_version"><?php _e( '1. What version of PHP do you want to test?', 'php-compatibility-checker' ); ?></label></th>
								<td>
									<?php
									foreach ( $phpversions as $name => $version ) {
										printf( '<label><input type="radio" name="phptest_version" value="%s" %s /> %s</label><br>', $version, checked( $test_version, $version, false ), $name );
									} ?>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="active_plugins"><?php _e( '2. What plugins/themes do you want to scan?', 'php-compatibility-checker' ); ?></label></th>
								<td><label><input type="radio" name="active_plugins" value="yes" <?php checked( $only_active, 'yes', true ); ?> /> <?php _e( 'Only scan active plugins and themes', 'php-compatibility-checker' ); ?></label><br>
									<label><input type="radio" name="active_plugins" value="no" <?php checked( $only_active, 'no', true ); ?> /> <?php _e( 'Scan all plugins and themes', 'php-compatibility-checker' ); ?></label>
								</td>
							</tr>
						</tbody>
					</table>

					<div class="wpe-pcc-run-scan">
						<input name="run" id="runButton" type="button" value="<?php _e( 'Scan', 'php-compatibility-checker' ); ?>" class="button-primary" />
						<span style="display:none; visibility:visible;" class="spinner wpe-pcc-spinner"></span>
					</div> <!-- /wpe-pcc-run-scan -->

				</div> <!-- /wpe-pcc-scan-options -->
				<hr>
				<div class="wpe-pcc-results">
					<h2><?php _e( 'Scan Results', 'php-compatibility-checker' ); ?></h2>

					<!-- Progress bar -->
					<div style="display: none;" id="wpe-progress">
						<p><?php printf( '<strong>Scan progress</strong> - <span id="wpe-progress-count"></span> <span id="wpe-progress-active"></span>', 'php-compatibility-checker' ); ?></p>
						<div id="progressbar"></div>
					</div>

					<a class="wpe-pcc-clear-results" name="run" id="cleanupButton"><?php _e( 'Clear results', 'php-compatibility-checker' ); ?></a>

					<!-- Area for pretty results. -->
					<div id="wpe-pcc-standardMode"></div>

					<!-- Area for developer results. -->
					<div style="display: none;" id="developerMode">
						<b><?php _e( 'Test Results:', 'php-compatibility-checker' ); ?></b>
						<textarea readonly="readonly" id="testResults"></textarea>
					</div>

					<p><?php printf( __( '<strong>Note:</strong> This plugin relies on WP-Cron to scan files in the background. The scan will get stuck if the site&#39s WP-Cron is not running correctly. Please <a href="%1$s">see the FAQ</a> for more information.', 'php-compatibility-checker' ), 'https://wordpress.org/plugins/php-compatibility-checker/faq/' ); ?></p>
					<p><?php printf( __( 'Report false positives <a href="%1$s">on our GitHub repo</a>.', 'php-compatibility-checker' ), 'https://github.com/wpengine/phpcompat/wiki/Results' ); ?></p>
				</div> <!-- /wpe-pcc-results -->
			</div> <!-- /wpe-pcc-main -->
			<div class="wpe-pcc-aside">
				<p class="wpe-pcc-logo"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 268.3 51"><g fill="#40BAC8"><path d="M17.4 51h16.4V38.6l-4-4h-8.5l-3.9 4zM38.6 17.3l-3.9 3.9v8.6l3.9 3.9h12.5V17.3zM33.8 0H17.4v12.5l3.9 3.9h8.5l4-3.9zM51.1 51V38.6l-3.9-4H34.7V51zM4 0L.1 3.9v12.5h16.4V0zM34.7 0v12.5l3.9 3.9h12.5V0zM25.6 27.9c-1.3 0-2.3-1.1-2.3-2.3 0-1.3 1.1-2.3 2.3-2.3 1.3 0 2.3 1.1 2.3 2.3 0 1.2-1 2.3-2.3 2.3zM16.5 17.3H.1v16.4h12.4l4-3.9zM16.5 38.6l-4-4H.1V51h12.4l4-3.9z"/></g><g fill="#162A33"><path d="M86.2 38.6c-.3 0-.4-.1-.5-.4l-4.1-14.5h-.1l-4.1 14.5c-.1.3-.2.4-.5.4h-4.8c-.3 0-.4-.1-.5-.4l-7-25.2c0-.2 0-.4.3-.4h6.3c.3 0 .5.2.5.4L75 28.1h.1l4-15.1c.1-.3.2-.4.5-.4h3.9c.3 0 .4.1.5.4l4.2 15.1h.1L91.5 13c0-.2.2-.4.5-.4h6.3c.2 0 .3.2.3.4l-7 25.2c-.1.3-.2.4-.5.4h-4.9zM103.6 38.6c-.2 0-.4-.2-.4-.4V13c0-.2.2-.4.4-.4H114c6.3 0 9.6 3.6 9.6 8.6s-3.3 8.7-9.6 8.7h-3.8c-.2 0-.2.1-.2.2v8c0 .2-.2.4-.4.4h-6zm13.3-17.3c0-1.8-1.2-2.9-3.3-2.9h-3.4c-.2 0-.2.1-.2.2V24c0 .2.1.2.2.2h3.4c2.1 0 3.3-1.2 3.3-2.9zM132.5 32.2c-.5-1.4-.7-3.1-.7-6.5 0-3.3.3-5.1.7-6.5 1.3-4.1 4.5-6.2 8.6-6.2 4.2 0 7.3 2.1 8.6 6.2.5 1.4.7 3 .7 6.1 0 .3-.2.5-.6.5h-16.3c-.2 0-.3.2-.3.4 0 2.7.2 4.2.6 5.5 1.2 3.7 3.9 5.3 7.5 5.3 3.4 0 5.9-1.5 7.4-3.5.2-.3.5-.3.7-.1l.3.3c.3.2.3.5.1.7-1.7 2.4-4.6 4.1-8.4 4.1-4.5 0-7.5-2.1-8.9-6.3zm16.2-7.8c.2 0 .3-.1.3-.3 0-1.7-.2-3.1-.6-4.3-1.1-3.5-3.7-5.3-7.2-5.3s-6.1 1.7-7.2 5.3c-.4 1.2-.6 2.5-.6 4.3 0 .2.1.3.3.3h15zM173.6 38c-.3 0-.5-.2-.5-.5V22.9c0-5.8-2.4-8.3-7.1-8.3-4.1 0-7.5 2.8-7.5 7.6v15.4c0 .3-.2.5-.5.5h-.5c-.3 0-.5-.2-.5-.5V14.2c0-.3.2-.5.5-.5h.5c.3 0 .5.2.5.5v3.4h.1c1.2-2.8 4-4.5 7.5-4.5 5.5 0 8.6 3.1 8.6 9.4v15c0 .3-.2.5-.5.5h-.6zM182 44.3c-.2-.3-.2-.6.1-.7l.4-.3c.3-.2.5-.1.7.2 1.4 1.8 3.5 2.9 6.5 2.9 4.6 0 7.6-2.3 7.6-8.3V34h-.1c-1.2 2.7-3.3 4.6-7.6 4.6-4.1 0-6.9-2.2-8.1-5.8-.6-1.7-.8-3.9-.8-6.9 0-3 .3-5.2.8-6.9 1.2-3.6 4-5.8 8.1-5.8 4.3 0 6.4 1.9 7.6 4.6h.1v-3.5c0-.3.2-.5.5-.5h.5c.3 0 .5.2.5.5v23.9c0 6.7-3.6 9.7-9.2 9.7-3.5-.1-6.4-1.7-7.6-3.6zm14.6-12.1c.5-1.5.7-3.3.7-6.4 0-3-.2-4.9-.7-6.4-1.2-3.6-3.8-4.9-6.8-4.9-3.3 0-5.7 1.6-6.7 4.8-.5 1.5-.8 3.6-.8 6.4 0 2.8.3 4.9.8 6.4 1.1 3.2 3.4 4.8 6.7 4.8 3 .2 5.7-1.1 6.8-4.7zM207.2 6.1c-.3 0-.5-.2-.5-.5v-2c0-.3.2-.5.5-.5h1.2c.3 0 .5.2.5.5v2.1c0 .3-.2.5-.5.5h-1.2zm.4 31.9c-.3 0-.5-.2-.5-.5V14.2c0-.3.2-.5.5-.5h.5c.3 0 .5.2.5.5v23.3c0 .3-.2.5-.5.5h-.5zM233.5 38c-.3 0-.5-.2-.5-.5V22.9c0-5.8-2.4-8.3-7.1-8.3-4.1 0-7.5 2.8-7.5 7.6v15.4c0 .3-.2.5-.5.5h-.5c-.3 0-.5-.2-.5-.5V14.2c0-.3.2-.5.5-.5h.5c.3 0 .5.2.5.5v3.4h.1c1.2-2.8 4-4.5 7.5-4.5 5.5 0 8.6 3.1 8.6 9.4v15c0 .3-.2.5-.5.5h-.6zM241.4 32.2c-.5-1.4-.7-3.1-.7-6.5 0-3.3.3-5.1.7-6.5 1.3-4.1 4.5-6.2 8.6-6.2 4.2 0 7.3 2.1 8.6 6.2.5 1.4.7 3 .7 6.1 0 .3-.2.5-.6.5h-16.3c-.2 0-.3.2-.3.4 0 2.7.2 4.2.6 5.5 1.2 3.7 3.9 5.3 7.5 5.3 3.4 0 5.9-1.5 7.4-3.5.2-.3.5-.3.7-.1l.3.3c.3.2.3.5.1.7-1.7 2.4-4.6 4.1-8.4 4.1-4.5 0-7.6-2.1-8.9-6.3zm16.1-7.8c.2 0 .3-.1.3-.3 0-1.7-.2-3.1-.6-4.3-1.1-3.5-3.7-5.3-7.2-5.3s-6.1 1.7-7.2 5.3c-.4 1.2-.6 2.5-.6 4.3 0 .2.1.3.3.3h15z"/></g><g><path fill="#162A33" d="M262.3 16.1c0-1.7 1.3-3 3-3s3 1.3 3 3-1.3 3-3 3-3-1.3-3-3zm5.5 0c0-1.5-1.1-2.5-2.5-2.5-1.5 0-2.5 1.1-2.5 2.5 0 1.5 1.1 2.5 2.5 2.5s2.5-1 2.5-2.5zm-3.5 1.7c-.1 0-.1 0-.1-.1v-3.1c0-.1 0-.1.1-.1h1.2c.7 0 1.1.4 1.1 1 0 .4-.2.8-.7.9l.7 1.3c.1.1 0 .2-.1.2h-.3c-.1 0-.1-.1-.2-.1l-.7-1.3h-.7v1.2c0 .1-.1.1-.1.1h-.2zm1.8-2.4c0-.3-.2-.5-.6-.5h-.8v1h.8c.4 0 .6-.2.6-.5z"/></g></svg></p>
				<div class="wpe-pcc-aside-content">
					<h2><?php _e( 'Launch this site in a PHP7 hosting environment and double your site speed!', 'php-compatibility-checker' ); ?></h2>
					<p><?php _e( 'Easily test your site on a PHP7 server or launch your new PHP7 site now!', 'php-compatibility-checker' ); ?></p>
					<a class="wpe-pcc-button" href="https://wpengine.com/plans/"><?php _e( 'Get PHP7 Hosting!', 'php-compatibility-checker' ); ?></a>

					<hr>

					<h2><?php _e( 'Need help making this site PHP7 compatible?', 'php-compatibility-checker' ); ?></h2>
					<p class="wpe-pcc-attention"><?php _e( '<strong>Attention:</strong> Not all errors are show-stoppers. <a href="https://wpengine.com/plans/">Test this site in PHP7</a> to see if just works.', 'php-compatibility-checker' ); ?></p>
					<div class="wpe-pcc-dev-helper">
						<p class="title"><strong><?php _e( 'Get help from WP Engine partners', 'php-compatibility-checker' ); ?></strong></p>
						<img src="http://placehold.it/233x105" />
						<p><?php _e( 'We partner with the brightest agency minds that are dedicated to delivering enterprise grade solutions in WordPress.', 'php-compatibility-checker' ); ?></p>
					</div> <!-- /wpe-pcc-dev-helper -->

					<div class="wpe-pcc-dev-helper">
						<p class="title"><strong><?php _e( 'Get a fast quote from Codeable', 'php-compatibility-checker' ); ?></strong></p>
						<img src="http://placehold.it/233x105" />
						<p><?php _e( 'Here is some text about this thing right here that could be a few lines long.', 'php-compatibility-checker' ); ?></p>
					</div> <!-- /wpe-pcc-dev-helper -->

				</div> <!-- /wpe-pcc-aside-content -->
				<input type="checkbox" id="developermode" name="developermode" value="yes" /><?php _e( 'Developer mode', 'php-compatibility-checker' ); ?>
			</div> <!-- /wpe-pcc-aside -->
		</div> <!-- /wpe-pcc-wrap -->
		<!-- // end new markup -->

		<!-- Results template -->
		<script id="result-template" type="text/x-handlebars-template">
			<div class="wpe-pcc-alert wpe-pcc-alert-{{#if skipped}}skipped{{else if passed}}passed{{else}}error{{/if}}">
				<p>
					<!-- Appropriate icon, based on status -->
					<span class="dashicons-before dashicons-{{#if skipped}}editor-help{{else if passed}}yes{{else}}no{{/if}}"></span>
					<!-- Name of plugin/theme being tested -->
					<strong>{{plugin_name}} </strong> -
					<!-- Update??? Don't know what this does -->
					<span class="wpe-pcc-update">{{update}}</span>
					<!-- Results status -->
					<span class="wpe-pcc-alert-status">
						{{#if passed}}
							<?php _e( 'Compatible', 'php-compatibility-checker' ); ?>
						{{/if}}
						{{#if skipped}}
							<?php _e( 'Unknown', 'php-compatibility-checker' ); ?>
						{{/if}}
						{{#if warnings}}
							{{warnings}} <?php _e( 'warning(s)', 'php-compatibility-checker' ); ?>
						{{/if}}
						{{#if errors}}
							{{errors}} <?php _e( 'error(s)', 'php-compatibility-checker' ); ?>
						{{/if}}
					</span>
					<!-- Check if plugin/theme has an update available -->
					<?php $update_url = site_url( 'wp-admin/update-core.php' , 'admin' ); ?>
					{{#if updateAvailable}}
						(<a href="<?php echo esc_url( $update_url ); ?>"><?php _e( 'Update Available', 'php-compatibility-checker' ); ?></a>)
					{{/if}}
					<!-- View details link -->
					<a class="wpe-pcc-alert-details" href="#"><?php _e( 'toggle details', 'php-compatibility-checker' ); ?></a>
					<textarea class="wpe-pcc-alert-logs hide">{{logs}}</textarea>
				</p>
			</div> <!-- /wpe-pcc-alert -->
		</script>
		<?php
	}
}
// Register the WPEngine_PHPCompat instance
WPEngine_PHPCompat::init();
