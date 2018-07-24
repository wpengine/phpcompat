<?php
// Allow the plugin to fork the request in the container.
add_filter( 'phpcompat_fork_url', function( $url ) {
	return str_replace( '8081', '80', $url );
});

// Allow the cron to work in the container.
add_filter( 'cron_request', function( $cron_request ) {
	$cron_request['url'] = str_replace( '8081', '80', $cron_request['url'] );
	return $cron_request;
});
