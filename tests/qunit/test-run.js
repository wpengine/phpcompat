/* jshint esversion: 6 */
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

	resetDisplay();

	assert.ok( '' === $( '#testResults' ).text(), 'testResults is empty' );
	assert.ok( '' === $( '#standardMode' ).html(), 'standardMode is empty' );
});

QUnit.module( 'displayReport' );

QUnit.test( 'Render test pass', function( assert ) {
	var fixture = $( '#qunit-fixture' );

	helpers.setUpReportTestFixtures(fixture, '5.5');

	test_version = $( 'input[name=phptest_version]:checked' ).val();

	displayReport(helpers.passResults);

	var displayedResults = $('#testResults').text();

	assert.ok( helpers.passResults === displayedResults, 'Text results are correct' );
	assert.ok( ! $('.spinner').is(':visible'), 'Spinner is hidden' );
	assert.ok( 'Re-run' === $('#runButton').val(), 'Run button text is Re-run' );
	assert.ok( $('#footer').is(':visible'), 'Footer is visible' );
	assert.ok( ! $('#runButton').hasClass('button-primary-disabled'), "Run button isn't disabled" );
	assert.ok( $('.wpe-results-card').length == 2, 'There are 2 results.' );
	assert.ok( $('#standardMode').text().includes( 'Your WordPress install is PHP 5.5 compatible.' ), 'Test did pass.' );
});

QUnit.test( 'Render test fail', function( assert ) {
	var fixture = $( '#qunit-fixture' );

	helpers.setUpReportTestFixtures(fixture, '5.5');

	test_version = $( 'input[name=phptest_version]:checked' ).val();

	displayReport(helpers.failResults);

	var displayedResults = $('#testResults').text();

	assert.ok( helpers.failResults === displayedResults, 'Text results are correct' );
	assert.ok( ! $('.spinner').is(':visible'), 'Spinner is hidden' );
	assert.ok( 'Re-run' === $('#runButton').val(), 'Run button text is Re-run' );
	assert.ok( $('#footer').is(':visible'), 'Footer is visible' );
	assert.ok( ! $('#runButton').hasClass('button-primary-disabled'), "Run button isn't disabled" );
	assert.ok( $('.wpe-results-card').length == 7, 'There are 7 results.' );
	assert.ok( $('#standardMode').text().includes( 'Your WordPress install is not PHP 5.5 compatible.' ), 'Test did not pass.' );
});
