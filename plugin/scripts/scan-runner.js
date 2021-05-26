(function($) {
	function build_rest_route( plugin_list ) {
		var base_route = 'somesite.com/' + plugin_list;
		return base_route;
	}

	function render_result_item( attributes ) {
		$('#wpe_php_compat_results').append('<article class="'+ attributes.slug + '">');
		$('#wpe_php_compat_results .' + attributes.slug ).append('<li class="' + attributes.slug + '"><b>Status:</b> ' + attributes.status + '</li>');
 		$('#wpe_php_compat_results').append('</article>');
	}
	$( document ).ready(function() {
		// maybe we'll need to do some priming.
	});
	
	$(document).on( 'click', '#runButton', function( event ) {
		event.preventDefault();
		console.log( checkerList );
		// placeholder.
		var plugin_list = 'akismet';
		});

		$(document).on( 'click', '#finalRunButton', function( event ) {
			event.preventDefault();
			console.log('hey, listen!');
			// placeholder.
			var plugin_list = 'akismet';
			var endpoint = build_rest_route( plugin_list );
			$.ajax({
				url: endpoint,
				beforeSend: function (xhr) {
					// maybe in future state send header.
					// xhr.setRequestHeader( 'X-WP-Nonce', ajaxpagination.rest_nonce);
				},
				type: 'GET',
				success: function( html, status, request ) {
					// a way to check for rest pages left..maybe we'll need some pagination. $total_pages = parseInt(request.getResponseHeader('X-WP-TotalPages'), 10);
	
					// an element removal if needed: $('#wpe_dynamic_rest_block').find( 'article' ).remove();
					$.each( html, function(){
						render_result_item( this );
					});
					// a value we can track as we progress through batches: value = 'something';
				},
				error: function () {
					//@todo maybe alert if the endpoints arent available?
				}
			});
	
	});
})(jQuery);
