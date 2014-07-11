jQuery(document).ready(function($) {
	$( 'span.pullquote' ).each( function() {
		var $el = $(this);
		//$(this).before( $(this).clone().addClass( 'pulledquote' ) );
		$el.addClass( 'pulledquote' ).after( $el.html() );
		if ( $el.data( 'wrap' ) ) {
			$el.wrap( '<p>' );
			$el = $el.parent().addClass( $(this).attr( 'class' ) );
			$el.attr( 'style', $(this).attr( 'style' ) );
			$(this).attr( 'style', '' );
			$(this).attr( 'class', '' );
		}
		if ( $(this).data( 'back' ) ) {
			var $back = parseInt($(this).data( 'back' ));
			var $pel = $el.parents( 'p' );
			while( $back > 0 ) {
				$pel = $pel.prev( 'p' );
				--$back;
			}
			$pel.before( $el.clone() );
			$el.remove();
		} else if ( $(this).data( 'forward' ) ) {
			var $forward = parseInt($(this).data( 'forward' ));
			var $pel = $el.parents( 'p' );
			while( $forward > 0 ) {
				$pel = $pel.next( 'p' );
				--$forward;
			}
			$pel.before( $el.clone() );
			$el.remove();
		} else {
			$el.parents('p').css( 'position', 'relative' );
		}
	});
});
