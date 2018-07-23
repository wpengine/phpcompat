// Global variables.
var test_version, only_active, timer;

jQuery( document ).ready(function($) {

	// Check the status immediately to reflect if tests are running.
	checkStatus();

	$( '#developermode' ).change(function() {
		if ( $(this).is( ':checked' ) ) {
			$( '#developerMode' ).show();
			$( '#wpe-pcc-standardMode' ).hide();
		} else {
			$( '#developerMode' ).hide();
			$( '#wpe-pcc-standardMode' ).show();
		}
	});
	$( '#downloadReport' ).on( 'click', function() {
		download( $( '#testResults' ).val(), 'report.txt', 'text/plain' );
		return false;
	});
	$( document ).on( 'click', '.wpe-pcc-alert-details', function() {
		// Get the textarea with is on the same (dom) level.
		var textarea = $( this ).siblings( 'textarea' );
		textarea.toggleClass( 'hide' );
		return false;
	});
	$( '#runButton' ).on( 'click', function() {
		// Unselect button so it's not highlighted.
		$( '#runButton' ).blur();

		// Show the ajax spinner.
		$( '.wpe-pcc-spinner' ).show();
		// Empty the results textarea.
		resetDisplay();
		test_version = $( 'input[name=phptest_version]:checked' ).val();
		only_active = $( 'input[name=active_plugins]:checked' ).val();
		var data = {
			'action': 'wpephpcompat_start_test',
			'test_version': test_version,
			'only_active': only_active,
			'startScan': 1
		};
		$( '.wpe-pcc-test-version' ).text(test_version);
		// Start the test!
		jQuery.post( ajaxurl, data ).always(function() {
			// Start timer to check scan status.
			checkStatus();
		});
	});

	$( '#cleanupButton' ).on( 'click', function() {
		clearTimeout( timer );
		jQuery.get( ajaxurl,  { 'action': 'wpephpcompat_clean_up' }, function() {
			resetDisplay();
			checkStatus();
		});
	});

});

function startTimer() {
	// Requeue the checkStatus call.
	timer = setTimeout(function() {
		checkStatus();
	}, 5000);
}

/**
 * Check the scan status and display results if scan is done.
 */
function checkStatus() {
	var data = {
		'action': 'wpephpcompat_check_status'
	};

	var obj;
	jQuery.post( ajaxurl, data, function( obj ) {
		// TODO: Without jQuery migrate an empty response can throw a JSON parse error.
		// So we should do the parsing manually.
		if ( !obj ) {
			startTimer();
			return;
		}
		/*
		 * Status false: the test is not running and has not been run yet
		 * Status 1: the test is currently running
		 * Status 0: the test as completed but is not currently running
		 */
		if ( false === obj.results ) {
			jQuery( '#runButton' ).val( window.wpephpcompat.run );
		} else {
			jQuery( '#runButton' ).val( window.wpephpcompat.rerun );
		}

		if ( '1' === obj.status ) {
			jQuery( '.wpe-pcc-spinner' ).show();
		} else {
			jQuery( '.wpe-pcc-spinner' ).hide();
		}

		if ( '0' !== obj.results ) {
			if( false !== obj.results ) {
				test_version = obj.version;
				displayReport( obj.results );
			}
			jQuery( '#wpe-pcc-progress-count' ).hide();
		} else {
			// Display the current plugin count.
			if ( obj.total ) {
				jQuery( '#wpe-pcc-progress-count' ).show();
				jQuery( '#wpe-pcc-progress-count' ).text( '(' + ( obj.total - obj.count + 1 ) + ' of ' + obj.total + ')' );
			}

			// Display the object being scanned.
			jQuery( '#wpe-progress-active' ).html( '<strong>Now scanning:</strong> ' + obj.activeJob );

			startTimer();
		}
	}, 'json' ).fail(function ( xhr, status, error )
	{
		// Server responded correctly, but the response wasn't valid.
		if ( 200 === xhr.status ) {
			alert( "Error: " + error + "\nResponse: " + xhr.responseText );
		}
		else { // Server didn't respond correctly.
			alert( "Error: " + error + "\nStatus: " + xhr.status );
		}
	});
}
/**
 * Clear previous results.
 */
function resetDisplay() {
	jQuery( '#testResults' ).text('');
	jQuery( '#wpe-pcc-standardMode' ).html('');
	jQuery( '#wpe-pcc-progress-count' ).text('');
	jQuery( '#wpe-progress-active' ).text('');
	jQuery( '.wpe-pcc-download-report' ).hide();
	jQuery( '.wpe-pcc-results' ).hide();
	jQuery( '.wpe-pcc-information' ).hide();
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
		$('#wpe-pcc-standardMode').append( html );
	}

	// Display global compatibility status.
	if ( test_version == '7.0' &&  compatible ) {
		// php 7 ready, and user tested version 7
		jQuery( '.wpe-pcc-download-report' ).show();
		jQuery( '.wpe-pcc-results' ).show();

	} else if ( compatible ) {
		jQuery( '.wpe-pcc-download-report' ).show();
		jQuery( '.wpe-pcc-results' ).show();
		jQuery( '.wpe-pcc-information-errors' ).show();
	} else {
		// Display scan stats.
		jQuery( '.wpe-pcc-download-report' ).show();
		$( '#wpe-pcc-standardMode' ).prepend( '<p>' + failedCount + ' ' + window.wpephpcompat.out_of + ' ' + plugins.length + ' ' + window.wpephpcompat.are_not + '.</p>' );
		jQuery( '.wpe-pcc-information-errors' ).show();
		jQuery( '.wpe-pcc-results' ).show();
	}
}
