/**
 * External dependencies
 */
import jQuery from 'jquery';

/**
 * Internal dependencies
 */
import '../assets/stylesheets/banner.scss';

jQuery( document ).ready( ( $ ) => {
	$( '.woocommerce-services__connect-jetpack' ).one( 'click', function() {
		const btn = $( this );
		btn.addClass( 'disabled' );
	} );
} );
