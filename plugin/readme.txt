=== PHP Compatibility Checker ===
Contributors:      wpengine, octalmage, stevenkword, Taylor4484, pross, jcross, rfmeier, cadic, dkotter, ankit-k-gupta, jeffpaul
Tags:              php 7, php 8, php, version, compat, compatibility, checker, wp engine, wpe, wpengine
Requires at least: 5.6
Tested up to:      6.2
Stable tag:        1.6.2
License:           GPLv2 or later
License URI:       http://www.gnu.org/licenses/gpl-2.0.html

Make sure your plugins and themes are compatible with newer PHP versions.

== Description ==

The WP Engine PHP Compatibility Checker can be used by any WordPress website on any web host to check PHP version compatibility.

This plugin will lint theme and plugin code installed on your WordPress site and give you back a report of compatibility issues as reported by [Tide](https://wptide.org) for you to fix. Compatibility issues are categorized into errors and warnings and will list the file and line number of the offending code, as well as the info about why that line of code is incompatible with the chosen version of PHP. The plugin will also suggest updates to themes and plugins, as a new version may offer compatible code.

**This plugin does not execute your theme and plugin code, as such this plugin cannot detect runtime compatibility issues.**

**Please note that linting code is not perfect. This plugin cannot detect unused code-paths that might be used for backwards compatibility, and thus might show false positives. We maintain a [whitelist of plugins](https://github.com/wpengine/phpcompat/wiki/Results) that can cause false positives. We are continuously working to ensure the checker provides the most accurate results possible.**

**This plugin relies on [Tide](https://wptide.org) that constantly scans updated versions of plugins and themes in the background. Your scan results should be near real-time, but if not that just means Tide has not yet scanned your specific plugin or theme version. Please be patient as this may take up to 10 minutes for results to be returned from Tide. Please see the [FAQ](https://wordpress.org/plugins/php-compatibility-checker/faq/) for more information.**

= Update to PHP 7.4 =

* Use this plugin to check your site for compatibility up to PHP 7.4!
* As of [July 2022](https://wordpress.org/about/stats/), 8.52% of WordPress websites run a PHP version older than PHP 7.0.
* These versions of PHP have been [deprecated and unsupported](https://secure.php.net/supported-versions.php) for over 2 years.
* Only 7.1% of WordPress websites run PHP 8, the current main version of PHP.

= Disclaimer =
*While this plugin is written to detect as many problems as accurately as possible, 100% reliable detection is very difficult to ensure. It is best practice to run comprehensive tests before you migrate to a new PHP version.*

The plugin was created by WP Engine to help the WordPress community increase adoption of modern PHP versions. We [welcome contributors](https://github.com/wpengine/phpcompat) to this plugin, and are excited to see how developers and other WordPress hosts use this plugin.

To disclose security issues for this plugin please email WordPress@wpengine.com.

== Installation ==

*Note: Go to 'Plugins' > 'Add New' in the WordPress admin and search for "PHP Compatibility Checker" and install it from there.*

To manually install:
1. Upload `phpcompat` to the `/wpengine-wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress

You will find the plugin options in the WP Admin `Tools => PHP Compatibility` menu. Once you click `run` it will take a few minutes to conduct the test. Feel free to navigate away from the page and check back later.

== Frequently Asked Questions ==

= 1. Will this work outside of the WP Engine hosting account? =

    Yes, this plugin can be used any ANY WordPress website on ANY host.

= 2. Are there WP-CLI commands available? =

    As of the 1.6.0 release this plugin no longer includes the `phpcompat` WP-CLI command. If you still require use of that command, then please run version 1.5.2 or older of this plugin as those versions extend WP-CLI and provide commands.

= 3. A plugin I created is listed as not compatible, what should I do? =

    We maintain a [whitelist of plugins](https://github.com/wpengine/phpcompat/wiki/Results) that cause false positives. If your plugin shows up as incompatible but you think that is wrong, please open a [GitHub issue](https://github.com/wpengine/phpcompat/issues/new) on the project, or email wordpress@wpengine.com with info about your plugin and why you know it is compatible (you have automated tests, the failure is on backwards compatibility code paths, etc).

= 4. Can I use this to test non-WordPress PHP Projects? =

    Yes! While you cannot use this WordPress plugin to test your non-WordPress projects, you can use the [Open Source PHPCompatibility Library](https://github.com/wimg/PHPCompatibility) that this plugin is built on.

= 5. Why was my plugin/theme skipped? =

    If your plugin or theme is not available on WordPress.org, then [Tide](https://wptide.org) will not be able to scan or return results of that plugin or theme.
    
    If your plugin or theme is available on WordPress.org, but Tide is not immediately returning results than it likely means Tide has not yet audited that plugin or theme and within a few minutes results should be available once Tide completes its audit.

= 6. The scan is stuck, what can I do? =

    As of version 1.6.0 of this plugin, there should no longer be issues of the scan getting stuck as it no longer runs on your WordPress host server.  If you are seeing significantly slow or unresponsive results from a plugin or theme that is available on WordPress.org, then please [open an issue](https://github.com/wptide/wptide.org/issues/new/choose) with those details for the Tide team to investigate why that specific plugin or theme version is not appearing in the Tide results.

= 7. I found a bug, or have a suggestion, can I contribute back? =

    Yes! WP Engine has a public GitHub repo where you can contribute back to this plugin. Please open an issue on the [Plugin GitHub](https://github.com/wpengine/phpcompat). We actively develop this plugin, and are always happy to receive pull requests.

    The plugin was created by WP Engine to help the WordPress community increase adoption of modern PHP versions. We welcome contributors to this plugin, and are excited to see how developers and other WordPress hosts use this plugin.

    To disclose security issues for this plugin please email WordPress@wpengine.com.

== Screenshots ==

1. Main screen: compatibility checker options
2. Compatibility results screen

== Changelog ==
= 1.6.2 =
- Update packages.

= 1.6.1 =
- Fix issue on update where old files were included.

= 1.6.0 =
- Changed from running PHP Compatibility scans on your WordPress server to using scan data from [Tide](https://wptide.org).
- Removed `phpcompat` WP-CLI command.
- Update dependencies.

= 1.5.2 =
- Removed PHP 5.2 checks
- Fixed PHP 8 issue where plugin cannot cannot be uninstalled.

= 1.5.1 =
- Added Smart Plugin Manager to whitelisted plugins.

= 1.5.0 =
- Added support for PHP 7.3 compatibility checks.

= 1.4.8 =
- Update dependencies.

= 1.4.7 =
- Better translation support.

= 1.4.6 =
- Switched to new PHPCompatibilityWP library to help prevent false positives.

= 1.4.5 =
- Use plugin version number to enqueue scripts and styles.

= 1.4.4 =
- PHP 5.2 Support & PHP 7.1 and 7.2 Lints.
- Updated call to action sidebar depending on platform.

= 1.4.3 =
- Fixed Composer issue.

= 1.4.1 =
- Updated PHP_CodeSniffer to fix a security advisory.
- Whitelisted a number of plugins.

= 1.4.0 =
- Updated UX for viewing PHP errors to be more intuitive and require less scrolling.
- Added links for non-technical users who need assistance from developers to fix PHP errors or to test their site in PHP 7 enabled hosting environments.

= 1.3.2 =
- Added a "Clean up" button and uninstall.php.
- Added phpcompat_phpversions filter.

= 1.3.1 =
- Whitelisted a number of plugins.

= 1.3.0 =
- Updated the PHPCompatibility library to latest version. Should fix many false positives.
- Changed language and added help text to Admin UI.

= 1.2.4 =
- Fixed Composer issue.

= 1.2.3 =
- Updated the PHPCompatibility library to latest version.
- Whitelisted TablePress.

= 1.2.2 =
- Whitelisted UpdraftPlus and Max Mega Menu.

= 1.2.1 =
- Updated the PHPCompatibility library to latest version.

= 1.2.0 =
- Updated the PHPCompatibility library to latest version.
- Added support for PHP 5.6

= 1.1.2 =
- Fixed issue with WordPress notices breaking the plugin header.
- Changed the way we send and parse JSON.
- You can now restart an in progress scan.
- Updated download.js to v4.2 for better Safari compatibility.

= 1.1.1 =
- Fixed bug with active job display.
- Updated progress bar calculation.

= 1.1.0 =
- Test results now persist page reloads.
- Failed tests will show an overview of the results.
- The scan timeout is now configurable using a filter. See the FAQ for more details.

= 1.0.3 =
- Fixed a bug in the WP-CLI command.
- Added a handful of PHP 7 compatible plugins to the whitelist.

= 1.0.2 =
- Added additional role protections.
- Changed the UI colors to better understand output at a glance.
- Exclude checking node_modules and tmp directories.
- Added support for child theme's parent theme.

= 1.0.1 =
- Updated compatibility library with a few bugfixes.
- Added skip logic to prevent checker from hanging.

= 1.0.0 =
- Major update to add PHP 7 checking support.
- Improved the UX of the progress bar.
- Fixed bug with the way the plugin menu was registered.

= 0.1.0 =
- Initial version.
- PHP 5.5, 5.4, and 5.3 Support.
- Basic WP-CLI Commands.

== Upgrade Notice ==

= 1.6.0 =
- WordPress minimum increased from 4.8 to 5.6.
- PHP Compatibility scans now run via [Tide](https://wptide.org) and no longer run on your host server!
- The WP-CLI `phpcompat` command has been removed as this plugin no longer runs on your host server and relies upon Tide.

= 1.4.8 =
- Update dependencies.
