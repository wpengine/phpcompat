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

QUnit.test( 'Render test', function( assert ) {
	var fixture = $( '#qunit-fixture' );

	var response = `Name: Twenty Fifteen

PHP 5.5 compatible.

Name: Akismet

PHP 5.5 compatible.
Update Available: 3.1.11; Current Version: 3.1.7;
`;

	fixture.append( '<input id="runButton" class="button-primary-disabled" value="Run">' );
	fixture.append( '<div class="spinner">Loading...</div>' );
	fixture.append( '<textarea id="testResults"></textarea>' );
	fixture.append( '<div id="footer" style="display: none;"></div>' );
	fixture.append( '<div id="standardMode"></div>' );

	displayReport(response);

	var displayedResults = $('#testResults').text();

	assert.ok( response === displayedResults, 'Text results are correct' );
	assert.ok( ! $('.spinner').is(':visible'), 'Spinner is hidden' );
	assert.ok( 'Re-run' === $('#runButton').val(), 'Run button text is Re-run' );
	assert.ok( $('#footer').is(':visible'), 'Footer is visible' );
	assert.ok( ! $('#runButton').hasClass('button-primary-disabled'), "Run button isn't disabled" );
	assert.ok( $('.wpe-results-card').length == 2, 'There are 2 results.' );

});