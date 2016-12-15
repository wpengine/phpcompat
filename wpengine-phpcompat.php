<?php
/*
Plugin Name: PHP Compatibility Checker
Plugin URI: https://wpengine.com
Description: Make sure your plugins and themes are compatible with newer PHP versions.
Author: WP Engine
Version: 1.3.1
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
			'run'        => __( 'Run', 'php-compatibility-checker' ),
			'rerun'      => __( 'Re-run', 'php-compatibility-checker' ),
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

		// Assigns defaults for the scan if none are found in the database.
		$test_version = ( false !== $test_version ) ? $test_version : '7.0';
		$only_active = ( false !== $only_active ) ? $only_active : 'yes';
		?>
		<div class="wrap">
			<div style="float: left;">
				<h2><?php esc_attr_e( 'WP Engine PHP Compatibility Checker', 'php-compatibility-checker' ); ?></h2>
			</div>
			<div style="float: right; margin-top: 10px; text-align: right;">
				<input type="checkbox" id="developermode" name="developermode" value="yes" /><?php esc_attr_e( 'Developer mode', 'php-compatibility-checker' ); ?>
			</div>
			<div class="clear">
				<p><?php esc_attr_e( 'This plugin will lint theme and plugin code inside your WordPress file system and give you back a report of compatibility issues for you to fix. Compatibility issues are categorized into errors and warnings and will list the file and line number of the offending code, as well as the info about why that line of code is incompatible with the chosen version of PHP. The plugin will also suggest updates to themes and plugins, as a new version may offer compatible code.', 'php-compatibility-checker' ); ?></p>
								<p><b><?php esc_attr_e( 'This plugin does not execute your theme and plugin code, as such this plugin cannot detect runtime compatibility issues.', 'php-compatibility-checker' ); ?></b></p>


<p><?php printf( __( 'Please note that linting code is not perfect. This plugin cannot detect unused code paths that might be used for backwards compatibility, and thus might show false positives. You can <a href="%1$s">report false positives on our GitHub repo</a>.', 'php-compatibility-checker' ), 'https://github.com/wpengine/phpcompat/wiki/Results' ); ?></p>

<p><?php printf( __( 'This plugin relies on WP-Cron to scan files in the background. The scan will get stuck if the site&#39s WP-Cron is not running correctly. Please <a href="%1$s">see the FAQ</a> for more information.', 'php-compatibility-checker' ), 'https://wordpress.org/plugins/php-compatibility-checker/faq/' ); ?></p>
			</div>
			<hr>
			<h3 class="title clear"><?php esc_attr_e( 'Scan Options', 'php-compatibility-checker' ); ?></h3>
			<table class="form-table">
				<tbody>
					<tr>
						<th scope="row"><label for="phptest_version"><?php esc_attr_e( 'PHP Version', 'php-compatibility-checker' ); ?></label></th>
						<td>
							<?php foreach( $phpversions as $name => $version ) {
								printf( '<label><input type="radio" name="phptest_version" value="%s" %s /> %s</label><br>', $version, checked( $test_version, $version, false ), $name );
							}
							?>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="active_plugins"><?php esc_attr_e( 'Only Active', 'php-compatibility-checker' ); ?></label></th>
						<td><label><input type="radio" name="active_plugins" value="yes" <?php checked( $only_active, 'yes', true ); ?> /> <?php esc_attr_e( 'Only scan active plugins and themes', 'php-compatibility-checker' ); ?></label><br>
							<label><input type="radio" name="active_plugins" value="no" <?php checked( $only_active, 'no', true ); ?> /> <?php esc_attr_e( 'Scan all plugins and themes', 'php-compatibility-checker' ); ?></label>
						</td>
					</tr>
				</tbody>
			</table>
			<hr />
			<p>
				<div style="display: none;" id="wpe-progress">
					<label for=""><?php esc_attr_e( 'Progress', 'php-compatibility-checker' ); ?></label>
					<div id="progressbar"></div>
					<div id="wpe-progress-count"></div>
					<div id="wpe-progress-active"></div>
				</div>

				<!-- Area for pretty results. -->
				<div id="standardMode"></div>

				<!-- Area for developer results. -->
				<div style="display: none;" id="developerMode">
					<b><?php esc_attr_e( 'Test Results:', 'php-compatibility-checker' ); ?></b>
					<textarea readonly="readonly" id="testResults"></textarea>
				</div>

				<div id="footer" style="display: none;">
					<?php esc_attr_e( 'Note: PHP Warnings will not cause errors, but could cause compatibility issues with future PHP versions, and could spam your PHP logs.', 'php-compatibility-checker' ); ?><br>
					<a id="downloadReport" href="#"><?php esc_attr_e( 'Download Report', 'php-compatibility-checker' ); ?></a>
				</div>
			</p>
			<p>
				<input style="float: left;" name="run" id="runButton" type="button" value="<?php esc_attr_e( 'Run', 'php-compatibility-checker' ); ?>" class="button-primary" />
				<div style="display:none; visibility: visible; float: none;" class="spinner"></div>
			</p>
		</div>

		<!-- Results template -->
		<script id="result-template" type="text/x-handlebars-template">
			<div style="border-left-color: {{#if skipped}}#999999{{else if passed}}#038103{{else}}#e74c3c{{/if}};" class="wpe-results-card">
				<div class="inner-left">
					{{#if skipped}}<img src="<?php echo esc_url( plugins_url( '/src/images/question.png', __FILE__ ) ); ?>">{{else if passed}}<img src="<?php echo esc_url( plugins_url( '/src/images/check.png', __FILE__ ) ); ?>">{{else}}<img src="<?php echo esc_url( plugins_url( '/src/images/x.png', __FILE__ ) ); ?>">{{/if}}
				</div>
				<div class="inner-right">
					<h3 style="margin: 0px;">{{plugin_name}}</h3>
					{{#if skipped}}<?php esc_attr_e( 'Unknown', 'php-compatibility-checker' ); ?>{{else if passed}}PHP {{test_version}} <?php esc_attr_e( 'compatible', 'php-compatibility-checker' ); ?>.{{else}}<b><?php esc_attr_e( 'Possibly not', 'php-compatibility-checker' ); ?></b> PHP {{test_version}} <?php esc_attr_e( 'compatible', 'php-compatibility-checker' ); ?>.{{/if}}<br>
					{{update}}<br>
					<textarea style="display: none; white-space: pre;">{{logs}}</textarea><a class="view-details"><?php esc_attr_e( 'view details', 'php-compatibility-checker' ); ?></a>
				</div>
				<?php $update_url = site_url( 'wp-admin/update-core.php' , 'admin' ); ?>
				<div style="float:right;">{{#if updateAvailable}}<div class="badge wpe-update"><a href="<?php echo esc_url( $update_url ); ?>"><?php esc_attr_e( 'Update Available', 'php-compatibility-checker' ); ?></a></div>{{/if}}{{#if warnings}}<div class="badge warnings">{{warnings}} <?php esc_attr_e( 'Warnings', 'php-compatibility-checker' ); ?></div>{{/if}}{{#if errors}}<div class="badge errors">{{errors}} <?php esc_attr_e( 'Errors', 'php-compatibility-checker' ); ?></div>{{/if}}</div>
			</div>
		</script>
		<?php
	}
}
// Register the WPEngine_PHPCompat instance
WPEngine_PHPCompat::init();
