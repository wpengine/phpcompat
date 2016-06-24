=== PHP Compatibility Checker ===
Contributors: wpengine, octalmage, stevenkword, taylor4484
Tags: php 7, php 5.5, php, version, compatibility, wpe, wpengine, wp engine
Requires at least: 3.0.1
Tested up to: 4.5
Stable tag: 0.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Make sure your plugins and themes are compatible with newer PHP versions. 

== Description ==

The WP Engine PHP Compatibility Checker can be used by any WordPress website on any web host to check PHP version compatibility. 

This plugin will lint theme and plugin code inside your WordPress file system and provide you back a report of compatibility issues for you to fix. Compatiblity issues are categorized into errors and warnings and will list the file and line number of the offending code, as well as the info about why that line of code is incompatibile with the chosen version of PHP. The plugin will also suggest updates to themes and plugins, as there may be a new version that contains compatibile code. 

This plugin does not execute your theme and plugin code, as such this plugin cannot detect runtime compatibility issues. 

= Update to PHP 5.5 =
* As of June 2016, 60.2% of WordPress websites run a PHP version less PHP 5.5
* These versions of PHP have been deprecated and unsupported for over 9 months.

= Disclaimer =
While this plugin is written to detect as many problems as accurately as possible, 100% reliable detection is very difficult to ensure. It is best practice to run comprehensive tests before you migrate to a new PHP version. 

The plugin was created by WP Engine to help the WordPress community increase adoption of modern PHP versions. We welcome contributors to this plugin, and are excited to see how developers and other WordPress hosts use this plugin.

== Installation ==

1. Upload `phpcompat` to the `/wpengine-wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress

You will find the plugin options in the WP Admin ‘Tools => PHP Compatibility’ menu. Once you click ‘run’ it will take a few minutes to conduct the test. While the test is running, you cannot navigate away from the page. 


Maybe notes about usage? 

== Other Notes ==
PHP Compatibility Checker includes WP-CLI command support:

`wp phpcompat <version> [--scan=<scan>]`

`  
<version>
    PHP version to test.

[--scan=<scan>]
  Whether to scan only active plugins and themes or all of them.
  default: active
  options:
    - active
    - all
`
Example: `wp phpcompat 5.5 --scan=active`


== Frequently Asked Questions ==

1) Will this work outside of the WP Engine hosting account?

Yes, this plugin can be used any ANY WordPress website on ANY host. 


2) Can I use this to test non-WordPress PHP Projects? 
  
Yes! While you cannot use this WordPress plugin to test your non-WordPress projects, you can use the [Open Source PHPCompatibility Library](https://github.com/wimg/PHPCompatibility) that this plugin is built on.


5) I found a bug, or have a suggestion, can I contribute back? 

Yes! WP Engine has a public github repo where you can contribute back to this plugin. Please open an issue on the [Plugin Github](https://github.com/wpengine/phpcompat). We actively develop this plugin, and are always happy to receive pull requests. 

The plugin was created by WP Engine to help the WordPress community increase adoption of modern PHP versions. We welcome contributors to this plugin, and are excited to see how developers and other WordPress hosts use this plugin.

== Screenshots ==

1. Main screen: compatibility checker options
2. Compatibility results screen

== Changelog ==

= 0.1 =
- Initial version
- PHP 5.5, 5.4, and 5.3 Support
- Basic WP-CLI Commands

== Upgrade Notice ==

= 0.1 =
- Initial version
- PHP 5.5, 5.4, and 5.3 Support
- Basic WP-CLI Commands
