// Global variables.
var test_version, only_active, timer;

jQuery( document ).ready(function($) {

	// Check the status immediately to reflect if tests are running.
	checkStatus();

	$( '#developermode' ).change(function() {
		if ( $(this).is( ':checked' ) ) {
			$( '#developerMode' ).show();
			$( '#standardMode' ).hide();
		} else {
			$( '#developerMode' ).hide();
			$( '#standardMode' ).show();
		}
	});
	$( '#downloadReport' ).on( 'click', function() {
		download( $( '#testResults' ).val(), 'report.txt', 'text/plain' );
	});
	$( document ).on( 'click', '.view-details', function() {
		// Get the textarea with is on the same (dom) level.
		var textarea = $( this ).siblings( 'textarea' );
		if ( 'none' === textarea.css( 'display' ) ) {
			textarea.css( 'display' , '' );
		} else {
			textarea.css( 'display', 'none' );
		}
	});
	$( '#runButton' ).on( 'click', function() {
		// Unselect button so it's not highlighted.
		$( '#runButton' ).blur();
		// If run button is disabled, don't run test.
		if ( $( '#runButton' ).hasClass( 'button-primary-disabled' ) ) {
			alert( 'Scan is already running!' );
			return;
		}

		// Disable run button.
		$( '#runButton' ).addClass( 'button-primary-disabled' );
		// Show the ajax spinner.
		$( '.spinner' ).show();
		// Empty the results textarea.
		resetDisplay();
		$( '#footer' ).hide();
		test_version = $( 'input[name=phptest_version]:checked' ).val();
		only_active = $( 'input[name=active_plugins]:checked' ).val();
		var data = {
			'action': 'wpephpcompat_start_test',
			'test_version': test_version,
			'only_active': only_active,
			'startScan': 1
		};
		// Init and show the Progress Bar
		jQuery( '#wpe-progress' ).show();

		// Start the test!
		jQuery.post( ajaxurl, data ).always(function() {
			// Start timer to check scan status.
			checkStatus();
		});
	});
});
/**
 * Check the scan status and display results if scan is done.
 */
function checkStatus() {
	var data = {
		'action': 'wpephpcompat_check_status'
	};

	var obj;
	jQuery.post( ajaxurl, data, function( response ) {
		try {
			obj = JSON.parse( response );
		} catch(e) {
			// If response wasn't JSON something is wrong.
			alert( "Error: " + e + "\nResponse: " + response );
			return;
		}

		/*
		 * Status false: the test is not running and has not been run yet
		 * Status 1: the test is currently running
		 * Status 0: the test as completed but is not currently running
		 */
		if ( false === obj.results ) {
			jQuery( '#runButton' ).val( 'Run' );
		} else {
			jQuery( '#runButton' ).val( 'Re-run' );
		}

		if ( '1' === obj.status ) {
			jQuery( '#runButton' ).addClass( 'button-primary-disabled' );
			jQuery( '.spinner' ).show();
		} else {
			jQuery( '#runButton' ).removeClass( 'button-primary-disabled' );
			jQuery( '.spinner' ).hide();
		}

		if ( '0' !== obj.results ) {
			if( false !== obj.results ) {
				test_version = obj.version;
				displayReport( obj.results );
			}
			jQuery( '#wpe-progress' ).hide();
		} else {
			jQuery( '#progressbar' ).progressbar({
				value: obj.progress
			});
			jQuery( '#wpe-progress' ).show();

			// Display the current plugin count.
			jQuery( '#wpe-progress-count' ).text( ( obj.total - obj.count + 1 ) + '/' + obj.total );

			// Display the object being scanned.
			jQuery( '#wpe-progress-active' ).text( obj.activeJob );

			// Requeue the checkStatus call.
			timer = setTimeout(function() {
				checkStatus();
			}, 5000);
		}
	});
}
/**
 * Clear previous results.
 */
function resetDisplay() {
	jQuery( '#progressbar' ).progressbar({
		value: 0
	});
	jQuery( '#testResults' ).text('');
	jQuery( '#standardMode' ).html('');
	jQuery( '#wpe-progress-count' ).text('');
	jQuery( '#wpe-progress-active' ).text('');
}
/**
 * Loop through a string and count the total matches.
 * @param  {RegExp} regex Regex to execute.
 * @param  {string} log   String to loop through.
 * @return {int}          The total number of matches.
 */
function findAll( regex, log ) {
	var m;
	var count = 0;
	while ( ( m = regex.exec( log ) ) !== null ) {
		if ( m.index === regex.lastIndex ) {
			regex.lastIndex++;
		}
		if ( parseInt( m[1] ) > 0 ) {
			count += parseInt( m[1] );
		}
	}
	return count;
}
/**
 * Display the pretty report.
 * @param  {string} response Full test results.
 */
function displayReport( response ) {
	// Clean up before displaying results.
	resetDisplay();
	var $ = jQuery;
	var compatible = 1;

	// Keep track of the number of failed plugins/themes.
	var failedCount = 0;
	var errorsRegex = /(\d*) ERRORS?/g;
	var warningRegex = /(\d*) WARNINGS?/g;
	var updateVersionRegex = /e: (.*?);/g;
	var currentVersionRegex = /n: (.*?);/g;

	// Grab and compile our template.
	var source = $( '#result-template' ).html();
	var template = Handlebars.compile( source );

	$( '#testResults' ).text( response );
	$( '#footer' ).show();

	// Separate plugins/themes.
	var plugins = response.replace( /^\s+|\s+$/g, '' ).split( window.wpephpcompat.name + ':' );

	// Remove the first item, it's empty.
	plugins.shift();

	// Loop through them.
	for ( var x in plugins ) {
		var updateVersion;
		var updateAvailable = 0;
		var passed = 1;
		var skipped = 0;
		// Extract plugin/theme name.
		var name = plugins[x].substring( 0, plugins[x].indexOf( '\n' ) );
		// Extract results.
		var log = plugins[x].substring( plugins[x].indexOf('\n'), plugins[x].length );
		// Find number of errors and warnings.
		var errors = findAll( errorsRegex, log );
		var warnings = findAll( warningRegex, log );
		// Check to see if there are any plugin/theme updates.
		if ( updateVersionRegex.exec( log ) ) {
			updateAvailable = 1;
		}
		// Update plugin and global compatibility flags.
		if ( parseInt( errors ) > 0 ) {
			compatible = 0;
			passed = 0;
			failedCount++;
		}
		// Trim whitespace and newlines from report.
		log = log.replace( /^\s+|\s+$/g, '' );

		if ( log.search('skipped') !== -1 ) {
			skipped = 1;
		}
		// Use handlebars to build our template.
		var context = {
			plugin_name: name,
			warnings: warnings,
			errors: errors,
			logs: log,
			passed: passed,
			skipped: skipped,
			test_version: test_version,
			updateAvailable: updateAvailable
		};
		var html = template( context );
		$('#standardMode').append( html );
	}

	// Display global compatibility status.
	if ( compatible ) {
		$( '#standardMode' ).prepend( '<h3>Your WordPress install is PHP ' + test_version + ' compatible.</h3>' );
	} else {
		// Display scan stats.
		$( '#standardMode' ).prepend( '<p>' + failedCount + ' out of ' + plugins.length + ' plugins/themes are not compatible.</p>' );

		$( '#standardMode' ).prepend( '<h3>Your WordPress install is not PHP ' + test_version + ' compatible.</h3>' );
	}
}
