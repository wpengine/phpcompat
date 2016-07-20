/* jshint esversion: 6 */

// No document.ready event.
$.holdReady( true );

QUnit.module( 'findAll' );

QUnit.test( 'no matches', function( assert ) {
	var count = findAll( /(\d*) ERRORS?/g, '' );

	assert.ok( 0 === count, 'Found: ' + count );
});

QUnit.test( 'one match', function( assert ) {
	var count = findAll( /(\d*) ERRORS?/g, '1 ERRORS' );
	assert.ok( 1 === count, 'Found: ' + count );
});

QUnit.test( 'five matches', function( assert ) {
	var count = findAll( /(\d*) ERRORS?/g, '5 ERRORS' );
	assert.ok( 5 === count, 'Found: ' + count );
});

QUnit.module( 'resetDisplay' );

QUnit.test( 'reset elements', function( assert ) {
	var fixture = $( '#qunit-fixture' );

	fixture.append( '<div id="standardMode">Hello this is text.</div>' );
	fixture.append( '<textarea id="testResults">This is some more text!</textarea>' );
	fixture.append( '<div id="wpe-progress-count"></div>' );
	fixture.append( '<div id="progressbar"></div>' );

	// Set the progress bar to a known state.
	jQuery( '#progressbar' ).progressbar({ value: 5 });

	resetDisplay();

	assert.ok( '' === $( '#testResults' ).text(), 'testResults is empty' );
	assert.ok( '' === $( '#standardMode' ).html(), 'standardMode is empty' );
	assert.ok( '' === $( '#wpe-progress-count' ).text(), 'wpe-progress-count is empty' );
	assert.ok( 0 === $( '#progressbar' ).progressbar( 'value' ), 'progressbar is set to 0' );
});

QUnit.module( 'displayReport' );

QUnit.test( 'Render test pass', function( assert ) {
	var fixture = $( '#qunit-fixture' );

	helpers.setUpReportTestFixtures(fixture, '5.5');

	test_version = $( 'input[name=phptest_version]:checked' ).val();

	displayReport(helpers.passResults);

	var displayedResults = $('#testResults').text();

	assert.ok( helpers.passResults === displayedResults, 'Text results are correct' );
	assert.ok( $('#footer').is(':visible'), 'Footer is visible' );
	assert.ok( $('.wpe-results-card').length == 2, 'There are 2 results.' );
	assert.ok( $('#standardMode').text().indexOf( 'Your WordPress install is PHP 5.5 compatible.' ) !== -1, 'Test did pass.' );
	assert.ok( '#038103' === helpers.rgb2hex( $( ".wpe-results-card" ).eq( 0 ).css( 'border-left-color' ) ), 'First plugin marked as passed.' );
	assert.ok( $( '#standardMode' ).text().indexOf( '0 out of 2' ) === -1, 'No scan stats are shown.' );
});

QUnit.test( 'Render test fail', function( assert ) {
	var fixture = $( '#qunit-fixture' );

	helpers.setUpReportTestFixtures(fixture, '5.5');

	test_version = $( 'input[name=phptest_version]:checked' ).val();

	displayReport(helpers.failResults);

	var displayedResults = $('#testResults').text();

	assert.ok( helpers.failResults === displayedResults, 'Text results are correct' );
	assert.ok( $('#footer').is(':visible'), 'Footer is visible' );
	assert.ok( $('.wpe-results-card').length == 7, 'There are 7 results.' );
	assert.ok( $('#standardMode').text().indexOf( 'Your WordPress install is not PHP 5.5 compatible.' ) !== -1, 'Test did not pass.' );
	assert.ok( $( '#standardMode' ).text().indexOf( '1 out of 7' ) !== -1, 'Scan stats are correct' );
});

QUnit.test( 'Render test skip', function( assert ) {
	var fixture = $( '#qunit-fixture' );

	helpers.setUpReportTestFixtures( fixture, '5.5' );

	test_version = $( 'input[name=phptest_version]:checked' ).val();

	displayReport(helpers.skipResults);

	var displayedResults = $( '#testResults' ).text();

	assert.ok( '#038103' === helpers.rgb2hex( $( ".wpe-results-card" ).eq( 0 ).css( 'border-left-color' ) ), 'First plugin marked as passed.' );
	assert.ok( '#999999' === helpers.rgb2hex( $( ".wpe-results-card" ).eq( 1 ).css( 'border-left-color' ) ), 'Second plugin marked as skipped.' );
});

QUnit.module( 'checkStatus' );

QUnit.test( 'Test checkStatus progress', function( assert ) {
	// This will be an async test since it involves callbacks.
	var done = assert.async();
	var fixture = $( '#qunit-fixture' );
	fixture.append( '<div id="wpe-progress-count"></div>' );
	fixture.append( '<div id="progressbar"></div>' );

	// Define our mock URL.
	ajaxurl = '/checkStatus/progress/';

	// Mock the ajax call.
	$.mockjax({
		url: ajaxurl,
		responseTime: 0,
		responseText: '{"status":"1","count":"1","total":"17","progress":94.1176470588,"results":"0"}',
		data: function (response) { // Check the data posted to our mock.
			assert.ok( 'wpephpcompat_check_status' === response.action, 'Correct action called.' );
			return true;
		},
		onAfterComplete: function() { // Check the results of checkStatus();
			assert.ok( $( '#wpe-progress-count' ).text() === '17/17', 'Progress count is correct.' );
			assert.ok( $( '#progressbar' ).progressbar( 'value' ) === 94.1176470588 , 'Progress bar is correct.' );

			// Clear the next queued checkStatus call.
			clearTimeout( timer );
			// End the test.
			done();
		}
	});

	// Actually run the function.
	checkStatus();
});

QUnit.test( 'Test checkStatus done', function( assert ) {
	var done = assert.async();
	var fixture = $( '#qunit-fixture' );
	fixture.append( '<div id="wpe-progress"><div id="wpe-progress-count"></div></div>' );

	ajaxurl = '/checkStatus/done/';

	$.mockjax({
		url: ajaxurl,
		responseTime: 0,
		responseText: '{"status":"1","count":"17","total":"17","progress":100,"results":"done"}',
		data: function (response) {
			assert.ok( 'wpephpcompat_check_status' === response.action, 'Correct action called.' );
			return true;
		},
		onAfterComplete: function() {
			assert.ok( ! $('#wpe-progress').is(':visible'), 'Progress div is hidden.' );
			clearTimeout( timer );
			done();
		}
	});

	checkStatus();
});

QUnit.test( 'Test checkStatus fail JSON.parse', function( assert ) {
	var done = assert.async();
	var fixture = $( '#qunit-fixture' );
	fixture.append( '<div id="wpe-progress"><div id="wpe-progress-count"></div></div>' );

	ajaxurl = '/checkStatus/fail/json/';
	var callCount = 0;
	var alertText;
	// Stub alert and keep track of the call count.
	var oldAlert = window.alert;
	window.alert = function (text) { callCount++; alertText = text; };
	$.mockjax({
		url: ajaxurl,
		responseTime: 0,
		responseText: '"status":"1","count":"17","total":"17","progress":100,"results":"done"}', // Broken JSON.
		data: function (response) {
			assert.ok( 'wpephpcompat_check_status' === response.action, 'Correct action called.' );
			return true;
		},
		onAfterComplete: function() {
			assert.ok(  $('#wpe-progress').is(':visible'), 'Progress div is visible.' );
			// Ensure that an alert was popped with the JSON SyntaxError.
			assert.ok( -1 !== alertText.toString().search( 'SyntaxError' ), 'Got JSON parse error in alert.' );
			assert.ok( 1 === callCount, 'Alert was called.' );
			clearTimeout( timer );
			window.alert = oldAlert;
			done();
		}
	});

	checkStatus();
});

QUnit.module( 'fixJSONResponse' );

QUnit.test( 'Test fixJSONResponse with errors.', function( assert ) {
	assert.expect( 1 );
	var json = '{"status":"1","count":"17","total":"17","progress":100,"results":"done"}';
	var response = "Error: This is an error\n\n\n\n\r\r\tNotice: This is a notice\n\n\r" + json;
	
	try {
		obj = JSON.parse( fixJSONResponse( response ) );
		assert.strictEqual( typeof obj, 'object', 'Response was parsed.');
	} catch(e) {
		
	}
});

QUnit.test( 'Test fixJSONResponse with no errors.', function( assert ) {
	assert.expect( 2 );
	var json = '{"status":"1","count":"17","total":"17","progress":100,"results":"done"}';
	
	try {
		response = fixJSONResponse( json );
		obj = JSON.parse( fixJSONResponse( response ) );
		assert.strictEqual( typeof obj, 'object', 'Response was parsed.');
		assert.strictEqual( json, response, 'Response is equal to json.');
	} catch(e) {
		
	}
});
