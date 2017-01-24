/* jshint esversion: 6 */
var helpers = {
	setUpReportTestFixtures: function(fixture, version)
	{
		fixture.append( '<input id="runButton" class="button-primary-disabled" value="Run">' );
		fixture.append( '<div class="spinner">Loading...</div>' );
		fixture.append( '<textarea id="testResults"></textarea>' );
		fixture.append( '<div id="footer" style="display: none;"></div>' );
		fixture.append( '<div id="standardMode"></div>' );
		fixture.append( '<input type="radio" name="phptest_version" value="' + version + '" checked="checked">' );
	},
	rgb2hex: function(rgb) {
		rgb = rgb.match(/^rgb\((\d+),\s*(\d+),\s*(\d+)\)$/);
		function hex(x) {
			return ("0" + parseInt(x).toString(16)).slice(-2);
		}
		return "#" + hex(rgb[1]) + hex(rgb[2]) + hex(rgb[3]);
	},
	passResults: 'Name: Twenty Fifteen' +
'' +
'PHP 5.5 compatible.' +
'' +
'Name: Akismet' +
'' +
'PHP 5.5 compatible.' +
'Update Available: 3.1.11; Current Version: 3.1.7;' +
'' +
'',
	skipResults : 'Name: Twenty Fifteen' +
'' +
'PHP 5.5 compatible.' +
'' +
'Name: Really Big Plugin' +
'' +
'The plugin/theme was skipped as it was too large to scan before the server killed the process.' +
'' +
'',
	failResults: 'Name: wp-bootstrap' +
'' +
'PHP 5.5 compatible.' +
'' +
'Name: Types - Complete Solution for Custom Fields and Types' +
'' +
'FILE: /nas/content/live/plugindb/wp-content/plugins/types/admin.php' +
'-----------------------------------------------------------------------------' +
'FOUND 0 ERRORS AND 1 WARNING AFFECTING 1 LINE' +
'-----------------------------------------------------------------------------' +
' 1 | WARNING | File has mixed line endings; this may cause incorrect results' +
'-----------------------------------------------------------------------------' +
'' +
'' +
'FILE: /nas/content/live/plugindb/wp-content/plugins/types/embedded/includes/fields/date/functions.php' +
'-----------------------------------------------------------------------------------------------------' +
'FOUND 0 ERRORS AND 1 WARNING AFFECTING 1 LINE' +
'-----------------------------------------------------------------------------------------------------' +
' 1 | WARNING | File has mixed line endings; this may cause incorrect results' +
'-----------------------------------------------------------------------------------------------------' +
'Update Available: 2.1; Current Version: 1.6.4;' +
'' +
'Name: Require Login' +
'' +
'PHP 5.5 compatible.' +
'' +
'Name: PHP Compatibility Checker' +
'' +
'PHP 5.5 compatible.' +
'' +
'Name: Custom Permalinks' +
'' +
'PHP 5.5 compatible.' +
'Update Available: 0.7.25; Current Version: 0.7.19;' +
'' +
'Name: CRED Frontend Editor' +
'' +
'FILE: /nas/content/live/plugindb/wp-content/plugins/cred-frontend-editor/third-party/zebra_form/includes/XSSClean.php' +
'---------------------------------------------------------------------------------------------------------------------' +
'FOUND 2 ERRORS AFFECTING 2 LINES' +
'---------------------------------------------------------------------------------------------------------------------' +
' 300 | ERROR | preg_replace() - /e modifier is deprecated in PHP 5.5' +
' 301 | ERROR | preg_replace() - /e modifier is deprecated in PHP 5.5' +
'---------------------------------------------------------------------------------------------------------------------' +
'' +
'Name: Crayon Syntax Highlighter' +
'' +
'FILE: /nas/content/live/plugindb/wp-content/plugins/crayon-syntax-highlighter/util/crayon_util.class.php' +
'--------------------------------------------------------------------------------------------------------' +
'FOUND 0 ERRORS AND 1 WARNING AFFECTING 1 LINE' +
'--------------------------------------------------------------------------------------------------------' +
' 652 | WARNING | The use of function split is discouraged from PHP version 5.3; use preg_split instead' +
'--------------------------------------------------------------------------------------------------------' +
'Update Available: 2.8.4; Current Version: 2.6.8;' +
'' +
''
};
/**
 * Add our current translation strings as a window var
 */
window.wpephpcompat = {"name":"Name","compatible":"compatible","are_not":"plugins\/themes are not compatible","is_not":"Your WordPress install is not PHP","out_of":"out of","run":"Run","rerun":"Re-run","your_wp":"Your WordPress install is"};
