<?php
/**
 * Registers and sets up all needed PHP compat logic.
 *
 * @package WPEngine_PHPCompat
 */

namespace WPEngine_PHPCompat;

/**
 * Main PHP Compat class.
 */
class PHP_Compatibility_Checker {

	/**
	 * Contains singleton instance.
	 *
	 * @since 1.0.0
	 * @static
	 * @var WPEngine_PHPCompat|null
	 */
	private static $instance = null;

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
	 * Enqueue any needed scripts and styles.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hook_suffix The current admin page.
	 */
	public function enqueue_scripts( $hook_suffix = '' ) {
		// Only load on our scan admin page.
		if ( 'tools_page_wpe-php-compat' !== $hook_suffix ) {
			return;
		}

		$assets_file = dirname( dirname( __FILE__ ) ) . '/build/scan.asset.php';

		if ( ! file_exists( $assets_file ) ) {
			add_action(
				'admin_notices',
				function() {
					?>
					<div class="notice notice-error is-dismissible">
						<p><?php echo wp_kses_post( __( 'Looks like you are using a development version of <strong>PHP Compatibility Checker</strong>. Please run <code>make build</code> to build assets.', 'wpe-php-compat' ) ); ?></p>
					</div>
					<?php
				}
			);

			return;
		}

		// phpcs:ignore PEAR.Files.IncludingFile.UseIncludeOnce
		$ajax_js_assets = require_once $assets_file;

		$scan_css = '../build/scan.css';
		$ajax_js  = '../build/scan.js';

		wp_enqueue_style(
			'tide-checker',
			plugins_url( $scan_css, __FILE__ ),
			array(),
			$ajax_js_assets['version']
		);

		wp_register_script(
			'tide-checker',
			plugins_url( $ajax_js, __FILE__ ),
			$ajax_js_assets['dependencies'],
			$ajax_js_assets['version'],
			true
		);

		wp_localize_script(
			'tide-checker',
			'checkerList',
			array(
				'plugins' => $this->get_plugins_to_scan(),
				'themes'  => $this->get_themes_to_scan(),
			)
		);

		wp_enqueue_script( 'tide-checker' );
	}

	/**
	 * Setup our hooks.
	 */
	public function init() {
		$instance = self::instance();

		// Build our tools page.
		add_action( 'admin_menu', array( $instance, 'create_menu' ) );

		// Enqueue scripts and styles.
		add_action( 'admin_enqueue_scripts', array( $instance, 'enqueue_scripts' ) );
	}

	/**
	 * Get all plugins that should be scanned.
	 *
	 * @return array
	 */
	public function get_plugins_to_scan() {
		if ( ! function_exists( 'get_plugins' ) ) {
			// phpcs:ignore PEAR.Files.IncludingFile.UseIncludeOnce -- strict requirement
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugins = get_plugins();

		/**
		 * Filter which plugins should be excluded from scans.
		 *
		 * This will exclude based on the plugin name, not the plugin slug.
		 *
		 * @param string[] $excluded_plugins Plugins we want to exclude.
		 *
		 * @since 1.6.0
		 */
		$excluded_plugins = apply_filters( 'phpcompat_excluded_plugins', array( 'PHP Compatibility Checker', 'Hello Dolly' ) );

		// Exclude some plugins.
		$plugins = array_filter(
			$plugins,
			function ( $plugin_data ) use ( $excluded_plugins ) {
				return ! in_array( $plugin_data['Name'], $excluded_plugins, true );
			}
		);

		if ( empty( $plugins ) ) {
			return array();
		}

		$active_plugins = get_option( 'active_plugins' );

		// On multisite, get network active plugins.
		if ( is_multisite() ) {
		    $active_plugins = array_merge(
				$active_plugins,
				array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) )
			);
		}

		// Add "active" attribute.
		$plugins = array_map(
			function( $plugin_file, $plugin_data ) use ( $active_plugins ) {
				$plugin_data['plugin_file'] = $plugin_file;
				$plugin_data['active']      = in_array( $plugin_file, $active_plugins, true ) ? 'yes' : 'no';
				return $plugin_data;
			},
			array_keys( $plugins ),
			$plugins
		);

		$plugin_info = get_site_transient( 'update_plugins' );

		// Extract real plugin slugs from the update_plugins transient.
		foreach ( $plugins as $key => $plugin_data ) {
			$plugin_file = $plugin_data['plugin_file'];

			if ( isset( $plugin_info->response[ $plugin_file ] ) ) {
				$plugins[ $key ]['slug'] = $plugin_info->response[ $plugin_file ]->slug;
			} elseif ( isset( $plugin_info->no_update[ $plugin_file ] ) ) {
				$plugins[ $key ]['slug'] = $plugin_info->no_update[ $plugin_file ]->slug;
			} else {
				$plugins[ $key ]['slug'] = dirname( $plugin_file );
			}
		}

		// Compact output.
		$plugins = array_map(
			function( $plugin ) {
				return array(
					'slug'    => sanitize_title( $plugin['slug'] ),
					'name'    => sanitize_text_field( $plugin['Name'] ),
					'version' => sanitize_text_field( $plugin['Version'] ),
					'active'  => $plugin['active'],
				);
			},
			$plugins
		);

		/**
		 * Filter which plugins should be scanned.
		 *
		 * @param array[] $plugins {
		 *     List of plugins.
		 *
		 *     @type string $slug Plugin slug.
		 *     @type string $name Plugin name.
		 *     @type string $version Plugin version.
		 *     @type string $active Whether the plugin is active (yes or no).
		 * }
		 *
		 * @since 1.6.0
		 */
		return apply_filters( 'phpcompat_plugins_to_scan', $plugins );
	}

	/**
	 * Get all themes that should be scanned.
	 *
	 * @return array
	 */
	public function get_themes_to_scan() {
		$themes_data = wp_prepare_themes_for_js();

		/**
		 * Filter which themes should be excluded from scans.
		 *
		 * This will exclude based on the theme name, not the theme slug.
		 *
		 * @param string[] $excluded_themes Themes we want to exclude.
		 *
		 * @since 1.6.0
		 */
		$excluded_themes = apply_filters( 'phpcompat_excluded_themes', array() );

		// Exclude some themes.
		$themes_data = array_filter(
			$themes_data,
			function ( $theme_data ) use ( $excluded_themes ) {
				return ! in_array( $theme_data['name'], $excluded_themes, true );
			}
		);

		$themes = array_map(
			function( $theme ) {
				return array(
					'slug'    => sanitize_title( $theme['id'] ),
					'name'    => sanitize_text_field( $theme['name'] ),
					'version' => sanitize_text_field( $theme['version'] ),
					'active'  => true === $theme['active'] ? 'yes' : 'no',
				);
			},
			$themes_data
		);

		/**
		 * Filter which themes should be scanned.
		 *
		 * @param array[] $themes {
		 *     List of themes.
		 *
		 *     @type string $slug Theme slug.
		 *     @type string $name Theme name.
		 *     @type string $version Theme version.
		 *     @type string $active Whether the theme is active (yes or no).
		 * }
		 *
		 * @since 1.6.0
		 */
		return apply_filters( 'phpcompat_themes_to_scan', $themes );
	}

	/**
	 * Add the scan page to the wp-admin menu.
	 *
	 * @since 1.0.0
	 *
	 * @action admin_menu
	 */
	public function create_menu() {
		// Create under Tools sub-menu.
		$this->page = add_submenu_page( 'tools.php', esc_html__( 'PHP Compatibility', 'wpe-php-compat' ), esc_html__( 'PHP Compatibility', 'wpe-php-compat' ), WPEPHPCOMPAT_CAPABILITY, WPEPHPCOMPAT_ADMIN_PAGE_SLUG, array( self::instance(), 'scan_page' ) );
	}

	/**
	 * Render method for the scan page.
	 *
	 * @since 1.0.0
	 */
	public function scan_page() {

		// Determine if current site is a WP Engine customer.
		$is_wpe_customer = function_exists( '\is_wpe' ) && \is_wpe();

		// Content variables.
		$url_get_hosting          = esc_url( 'https://wpeng.in/5a0336/' );
		$url_wpe_agency_partners  = esc_url( 'https://wpeng.in/fa14e4/' );
		$url_wpe_customer_upgrade = esc_url( 'https://wpeng.in/407b79/' );
		$url_wpe_logo             = esc_url( 'https://wpeng.in/22f22b/' );
		$url_codeable_submit      = esc_url( 'https://codeable.io/wp-admin/admin-ajax.php?action=wp_engine_phpcompat' );
		?>

		<div class="wrap wpe-pcc-wrap">
			<h1><?php esc_html_e( 'PHP Compatibility Checker', 'wpe-php-compat' ); ?></h1>
			<div class="wpe-pcc-main">
				<p><?php esc_html_e( 'The PHP Compatibility Checker can be used on any WordPress website on any web host.', 'wpe-php-compat' ); ?></p>
				<p><?php esc_html_e( 'This tool will lint your theme and plugin code on this site and provide you a report of compatibility issues. These issues are categorized into errors and warnings and will list the file and line number of the offending code, as well as the info about why that line of code is incompatible with the chosen version of PHP. This tool will also suggest updates to themes and plugins, as a new version may offer compatible code.', 'wpe-php-compat' ); ?></p>
				<hr>
				<div class="wpe-pcc-scan-options">
					<h2><?php esc_html_e( 'Scan Options', 'wpe-php-compat' ); ?></h2>
					<table class="form-table wpe-pcc-form-table">
						<tbody>
							<tr>
								<th scope="row"><label for="active_plugins"><?php esc_html_e( 'Plugin / Theme Status', 'wpe-php-compat' ); ?></label></th>
								<td>
									<fieldset>
										<label><input type="radio" name="active_plugins" value="yes" checked="checked" /> <?php esc_html_e( 'Only scan active plugins and theme', 'wpe-php-compat' ); ?></label><br>
										<label><input type="radio" name="active_plugins" value="no" /> <?php esc_html_e( 'Scan all plugins and themes', 'wpe-php-compat' ); ?></label>
									</fieldset>
								</td>
							</tr>
							<tr>
								<th scope="row"></th>
									<td>
										<div class="wpe-pcc-run-scan">
											<input name="run" id="runButton" type="button" value="<?php esc_html_e( 'Scan site', 'wpe-php-compat' ); ?>" class="button-secondary" />
											<div class="wpe-pcc-scan-information">
												<span style="display:none; visibility:visible;" class="spinner wpe-pcc-spinner"></span>
												<span class="wpe-progress-active" style="display:none;">
													<?php esc_html_e( 'Now scanning:', 'wpe-php-compat' ); ?>
													<span class="wpe-pcc-progress-count"></span>
												</span>
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
						<?php esc_html_e( 'Scan Results for PHP Compatibility', 'wpe-php-compat' ); ?>
					</h2>

					<div id="wpe-pcc-download-report" class="wpe-pcc-download-report" style="display:none;">
						<a id="downloadReport" class="button-primary" href="#"><span class="dashicons dashicons-download"></span> <?php esc_html_e( 'Download Report', 'wpe-php-compat' ); ?></a>
						<a class="wpe-pcc-clear-results" name="run" id="cleanupButton"><?php esc_html_e( 'Clear results', 'wpe-php-compat' ); ?></a>
						<label class="wpe-pcc-developer-mode">
							<input type="checkbox" id="developermode" name="developermode" value="yes" />
							<?php esc_html_e( 'View results as raw text', 'wpe-php-compat' ); ?>
						</label>
						<hr>
					</div> <!-- /wpe-pcc-download-report -->

					<div id="wpe_pcc_results"></div>

					<div style="display:none;" id="developerMode">
						<textarea readonly="readonly" id="testResults"></textarea>
					</div>

					<p class="wpe-pcc-attention">
						<?php
						printf(
							/* translators: %s: hosting URL */
							wp_kses_post( __( '<strong>Attention:</strong> Not all errors are show-stoppers. <a target="_blank" href="%s">Use LocalWP to test this site on PHP 8</a> to see if it just works!', 'wpe-php-compat' ) ),
							esc_url( 'https://localwp.com/' )
						);
						?>
					</p>

				</div> <!-- /wpe-pcc-results -->

				<div class="wpe-pcc-footer">
					<hr>
					<strong><?php esc_html_e( 'Limitations &amp; Caveats', 'wpe-php-compat' ); ?></strong>
					<ul class="wpe-pcc-bullets">
						<li><?php esc_html_e( 'This tool does not execute your theme or plugin code, so it cannot detect runtime compatibility issues.', 'wpe-php-compat' ); ?></li>
						<li><?php esc_html_e( 'PHP Warnings could cause compatibility issues with future PHP versions and/or spam your logs.', 'wpe-php-compat' ); ?></li>
						<li>
							<?php
							printf(
								/* translators: %s: GitHub Wiki URL */
								wp_kses_post( __( 'Report false positives <a target="_blank" href="%s">on our GitHub repo</a>.', 'wpe-php-compat' ) ),
								'https://github.com/wpengine/phpcompat/wiki/Results'
							);
							?>
						</li>
					</ul>
				</div> <!-- /wpe-pcc-footer -->
			</div> <!-- /wpe-pcc-main -->

			<?php if ( function_exists( '\is_wpe' ) && \is_wpe() ) : ?>
				<div class="wpe-pcc-aside">
					<a class="wpe-pcc-logo" href="<?php echo esc_url( $url_wpe_logo ); ?>" target="_blank"><svg width="182" height="34" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 268.3 51"><g fill="#40BAC8"><path d="M17.4 51h16.4V38.6l-4-4h-8.5l-3.9 4zM38.6 17.3l-3.9 3.9v8.6l3.9 3.9h12.5V17.3zM33.8 0H17.4v12.5l3.9 3.9h8.5l4-3.9zM51.1 51V38.6l-3.9-4H34.7V51zM4 0L.1 3.9v12.5h16.4V0zM34.7 0v12.5l3.9 3.9h12.5V0zM25.6 27.9c-1.3 0-2.3-1.1-2.3-2.3 0-1.3 1.1-2.3 2.3-2.3 1.3 0 2.3 1.1 2.3 2.3 0 1.2-1 2.3-2.3 2.3zM16.5 17.3H.1v16.4h12.4l4-3.9zM16.5 38.6l-4-4H.1V51h12.4l4-3.9z"/></g><g fill="#162A33"><path d="M86.2 38.6c-.3 0-.4-.1-.5-.4l-4.1-14.5h-.1l-4.1 14.5c-.1.3-.2.4-.5.4h-4.8c-.3 0-.4-.1-.5-.4l-7-25.2c0-.2 0-.4.3-.4h6.3c.3 0 .5.2.5.4L75 28.1h.1l4-15.1c.1-.3.2-.4.5-.4h3.9c.3 0 .4.1.5.4l4.2 15.1h.1L91.5 13c0-.2.2-.4.5-.4h6.3c.2 0 .3.2.3.4l-7 25.2c-.1.3-.2.4-.5.4h-4.9zM103.6 38.6c-.2 0-.4-.2-.4-.4V13c0-.2.2-.4.4-.4H114c6.3 0 9.6 3.6 9.6 8.6s-3.3 8.7-9.6 8.7h-3.8c-.2 0-.2.1-.2.2v8c0 .2-.2.4-.4.4h-6zm13.3-17.3c0-1.8-1.2-2.9-3.3-2.9h-3.4c-.2 0-.2.1-.2.2V24c0 .2.1.2.2.2h3.4c2.1 0 3.3-1.2 3.3-2.9zM132.5 32.2c-.5-1.4-.7-3.1-.7-6.5 0-3.3.3-5.1.7-6.5 1.3-4.1 4.5-6.2 8.6-6.2 4.2 0 7.3 2.1 8.6 6.2.5 1.4.7 3 .7 6.1 0 .3-.2.5-.6.5h-16.3c-.2 0-.3.2-.3.4 0 2.7.2 4.2.6 5.5 1.2 3.7 3.9 5.3 7.5 5.3 3.4 0 5.9-1.5 7.4-3.5.2-.3.5-.3.7-.1l.3.3c.3.2.3.5.1.7-1.7 2.4-4.6 4.1-8.4 4.1-4.5 0-7.5-2.1-8.9-6.3zm16.2-7.8c.2 0 .3-.1.3-.3 0-1.7-.2-3.1-.6-4.3-1.1-3.5-3.7-5.3-7.2-5.3s-6.1 1.7-7.2 5.3c-.4 1.2-.6 2.5-.6 4.3 0 .2.1.3.3.3h15zM173.6 38c-.3 0-.5-.2-.5-.5V22.9c0-5.8-2.4-8.3-7.1-8.3-4.1 0-7.5 2.8-7.5 7.6v15.4c0 .3-.2.5-.5.5h-.5c-.3 0-.5-.2-.5-.5V14.2c0-.3.2-.5.5-.5h.5c.3 0 .5.2.5.5v3.4h.1c1.2-2.8 4-4.5 7.5-4.5 5.5 0 8.6 3.1 8.6 9.4v15c0 .3-.2.5-.5.5h-.6zM182 44.3c-.2-.3-.2-.6.1-.7l.4-.3c.3-.2.5-.1.7.2 1.4 1.8 3.5 2.9 6.5 2.9 4.6 0 7.6-2.3 7.6-8.3V34h-.1c-1.2 2.7-3.3 4.6-7.6 4.6-4.1 0-6.9-2.2-8.1-5.8-.6-1.7-.8-3.9-.8-6.9 0-3 .3-5.2.8-6.9 1.2-3.6 4-5.8 8.1-5.8 4.3 0 6.4 1.9 7.6 4.6h.1v-3.5c0-.3.2-.5.5-.5h.5c.3 0 .5.2.5.5v23.9c0 6.7-3.6 9.7-9.2 9.7-3.5-.1-6.4-1.7-7.6-3.6zm14.6-12.1c.5-1.5.7-3.3.7-6.4 0-3-.2-4.9-.7-6.4-1.2-3.6-3.8-4.9-6.8-4.9-3.3 0-5.7 1.6-6.7 4.8-.5 1.5-.8 3.6-.8 6.4 0 2.8.3 4.9.8 6.4 1.1 3.2 3.4 4.8 6.7 4.8 3 .2 5.7-1.1 6.8-4.7zM207.2 6.1c-.3 0-.5-.2-.5-.5v-2c0-.3.2-.5.5-.5h1.2c.3 0 .5.2.5.5v2.1c0 .3-.2.5-.5.5h-1.2zm.4 31.9c-.3 0-.5-.2-.5-.5V14.2c0-.3.2-.5.5-.5h.5c.3 0 .5.2.5.5v23.3c0 .3-.2.5-.5.5h-.5zM233.5 38c-.3 0-.5-.2-.5-.5V22.9c0-5.8-2.4-8.3-7.1-8.3-4.1 0-7.5 2.8-7.5 7.6v15.4c0 .3-.2.5-.5.5h-.5c-.3 0-.5-.2-.5-.5V14.2c0-.3.2-.5.5-.5h.5c.3 0 .5.2.5.5v3.4h.1c1.2-2.8 4-4.5 7.5-4.5 5.5 0 8.6 3.1 8.6 9.4v15c0 .3-.2.5-.5.5h-.6zM241.4 32.2c-.5-1.4-.7-3.1-.7-6.5 0-3.3.3-5.1.7-6.5 1.3-4.1 4.5-6.2 8.6-6.2 4.2 0 7.3 2.1 8.6 6.2.5 1.4.7 3 .7 6.1 0 .3-.2.5-.6.5h-16.3c-.2 0-.3.2-.3.4 0 2.7.2 4.2.6 5.5 1.2 3.7 3.9 5.3 7.5 5.3 3.4 0 5.9-1.5 7.4-3.5.2-.3.5-.3.7-.1l.3.3c.3.2.3.5.1.7-1.7 2.4-4.6 4.1-8.4 4.1-4.5 0-7.6-2.1-8.9-6.3zm16.1-7.8c.2 0 .3-.1.3-.3 0-1.7-.2-3.1-.6-4.3-1.1-3.5-3.7-5.3-7.2-5.3s-6.1 1.7-7.2 5.3c-.4 1.2-.6 2.5-.6 4.3 0 .2.1.3.3.3h15z"/></g><g><path fill="#162A33" d="M262.3 16.1c0-1.7 1.3-3 3-3s3 1.3 3 3-1.3 3-3 3-3-1.3-3-3zm5.5 0c0-1.5-1.1-2.5-2.5-2.5-1.5 0-2.5 1.1-2.5 2.5 0 1.5 1.1 2.5 2.5 2.5s2.5-1 2.5-2.5zm-3.5 1.7c-.1 0-.1 0-.1-.1v-3.1c0-.1 0-.1.1-.1h1.2c.7 0 1.1.4 1.1 1 0 .4-.2.8-.7.9l.7 1.3c.1.1 0 .2-.1.2h-.3c-.1 0-.1-.1-.2-.1l-.7-1.3h-.7v1.2c0 .1-.1.1-.1.1h-.2zm1.8-2.4c0-.3-.2-.5-.6-.5h-.8v1h.8c.4 0 .6-.2.6-.5z"/></g></svg></a>

					<div class="wpe-pcc-get-hosting">
						<div class="wpe-pcc-aside-content">
							<?php if ( $is_wpe_customer ) : ?>
								<h2><?php esc_html_e( 'Make your site 2x faster by upgrading to PHP 7', 'wpe-php-compat' ); ?></h2>
							<?php else : ?>
								<h2><?php esc_html_e( 'Make your site 2x faster with PHP 7 WordPress hosting', 'wpe-php-compat' ); ?></h2>
							<?php endif; ?>

							<p><?php esc_html_e( 'Speed up your site and improve your conversion opportunities by upgrading to PHP 7 on the WP Engine platform.', 'wpe-php-compat' ); ?></p>

							<?php if ( $is_wpe_customer ) : ?>
								<a target="_blank" class="wpe-pcc-button wpe-pcc-button-primary" href="<?php echo esc_url( $url_wpe_customer_upgrade ); ?>"><?php esc_html_e( 'Upgrade to PHP 7 for free', 'wpe-php-compat' ); ?></a>
							<?php else : ?>
								<a target="_blank" class="wpe-pcc-button wpe-pcc-button-primary" href="<?php echo esc_url( $url_get_hosting ); ?>"><?php esc_html_e( 'Get PHP 7 Hosting', 'wpe-php-compat' ); ?></a>
								<p><?php esc_html_e( 'Already a WP Engine customer?', 'wpe-php-compat' ); ?> <a target="_blank" href="<?php echo esc_url( $url_wpe_customer_upgrade ); ?>"><?php esc_html_e( 'Click here to upgrade to PHP 7', 'wpe-php-compat' ); ?></a></p>
							<?php endif; ?>

						</div> <!-- /wpe-pcc-aside-content -->
					</div> <!-- /wpe-pcc-get-hosting -->

					<div style="display: none;" class="wpe-pcc-information wpe-pcc-information-errors">
						<div class="wpe-pcc-aside-content">
							<h2><?php esc_html_e( 'Need help making this site PHP 8 compatible?', 'wpe-php-compat' ); ?></h2>
							<div class="wpe-pcc-dev-helper">
								<p class="title"><strong><?php esc_html_e( 'Get help from WP Engine partners', 'wpe-php-compat' ); ?></strong></p>
								<p><?php esc_html_e( 'Our agency partners can help make your site PHP 8 compatible.', 'wpe-php-compat' ); ?></p>
								<a target="_blank" class="wpe-pcc-button" href="<?php echo esc_url( $url_wpe_agency_partners ); ?>"><?php esc_html_e( 'Find a WP Engine Partner', 'wpe-php-compat' ); ?></a>
							</div> <!-- /wpe-pcc-dev-helper -->

							<div class="wpe-pcc-dev-helper">
								<p class="title"><strong><?php esc_html_e( 'Get PHP 8 ready with Codeable', 'wpe-php-compat' ); ?></strong></p>
								<p><?php esc_html_e( 'Automatically submit this error report to Codeable to get a quick quote from their vetted WordPress developers.', 'wpe-php-compat' ); ?></p>
								<form target="_blank" action="<?php echo esc_url( add_query_arg( array( 'action' => 'wp_engine_phpcompat' ), $url_codeable_submit ) ); ?>" method="POST">
									<input type="hidden" id="wpe-pcc-codeable-data" name="data" value="" />
									<input type="submit" class="wpe-pcc-button" value="<?php esc_html_e( 'Submit to Codeable', 'wpe-php-compat' ); ?>" />
								</form>
							</div> <!-- /wpe-pcc-dev-helper -->
						</div> <!-- /wpe-pcc-aside-content -->
					</div> <!-- /wpe-pcc-information -->

				</div> <!-- /wpe-pcc-aside -->
			<?php endif; ?>

		</div> <!-- /wpe-pcc-wrap -->

		<script id="result-template" type="text/x-handlebars-template">
			<div id="{{type}}_{{slug}}" class="wpe-pcc-alert wpe-pcc-alert-{{status}}">
				<p>
					<strong>{{name}} {{version}}</strong>
				</p>

				{{#php.length}}
					<p class="wpe-pcc-results">
						{{#php}}
							{{#passed}}
							<span class="wpe-pcc-php-version-passed dashicons-before dashicons-yes">{{phpversion}}</span>
							{{/passed}}
							{{^passed}}
							<a href="#" data-php-version="{{phpversion}}" data-target="report_{{type}}_{{slug}}_{{phpversion}}" class="wpe-pcc-php-version-errors dashicons-before dashicons-no-alt">{{phpversion}}</a>
							{{/passed}}
						{{/php}}

						{{#has_errors}}
							<div><?php esc_html_e( 'Click on PHP Version to see errors and warnings', 'wpe-php-compat' ); ?></div>
						{{/has_errors}}
					</p>
				{{/php.length}}

				{{#custom_error}}
					{{#response.status}}
					<p><?php esc_html_e( 'Response status:', 'wpe-php-compat' ); ?> {{.}}</p>
					{{/response.status}}
					{{#response.message}}
					<p><?php esc_html_e( 'Message:', 'wpe-php-compat' ); ?> {{.}}</p>
					{{/response.message}}
					{{#response.errors}}
					<p>{{message}}</p>
					{{/response.errors}}
				{{/custom_error}}

				{{#reports.length}}
				<div id="wpe_pcc_reports">
					{{#reports}}
					<div id="report_{{type}}_{{slug}}_{{phpversion}}" data-php-version="{{phpversion}}" class="wpe-pcc-php-version-report" style="display:none">
						<h4>PHP {{phpversion}} <a href="#" class="wpe-pcc-php-version-report-close"><?php esc_html_e( 'close', 'wpe-php-compat' ); ?></a></h4>
						<textarea readonly="readonly">{{#messages}}
{{.}}
{{/messages}}</textarea>
					</div>
					{{/reports}}
				</div>
				{{/reports.length}}
			</div>
		</script>

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

		$url = add_query_arg( 'page', WPEPHPCOMPAT_ADMIN_PAGE_SLUG, admin_url( 'tools.php' ) );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Start Scan', 'wpe-php-compat' ) . '</a>' );

		return $links;
	}

}
