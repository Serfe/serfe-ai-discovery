( function( $ ) {
	'use strict';

	$( function() {
		var toggle = $( '.serfe-markdown-toggle' );
		if ( ! toggle.length || ! window.serfeAiDiscoveryMarkdownToggle ) {
			return;
		}

		var config = window.serfeAiDiscoveryMarkdownToggle;
		toggle.on( 'click', function() {
			var button = $( this );
			var enabled = '1' !== button.attr( 'data-enabled' );
			var status = button.siblings( '.serfe-markdown-toggle__status' );
			var message = button.siblings( '.serfe-markdown-toggle__message' );

			button.prop( 'disabled', true );
			message.text( '' );
			$.post( config.ajaxUrl, {
				action: 'serfe_ai_discovery_toggle_markdown',
				nonce: config.nonce,
				enabled: enabled ? '1' : '0'
			} ).done( function( response ) {
				if ( ! response || ! response.success || ! response.data ) {
					message.text( config.error );
					return;
				}

				var active = !! response.data.enabled;
				button.toggleClass( 'is-active', active );
				button.attr( 'data-enabled', active ? '1' : '0' );
				button.attr( 'aria-checked', active ? 'true' : 'false' );
				button.attr( 'aria-label', active ? config.disabled : config.enabled );
				status.text( active ? config.enabled : config.disabled );
			} ).fail( function() {
				message.text( config.error );
			} ).always( function() {
				button.prop( 'disabled', false );
			} );
		} );
	} );
}( jQuery ) );
