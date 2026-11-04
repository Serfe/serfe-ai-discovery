( function( $ ) {
	'use strict';

	$( function() {
		$( '.serfe-settings-tabs' ).each( function() {
			var tabList = $( this );
			var tabs = tabList.find( '.serfe-settings-tabs__tab' );
			var indicator = tabList.find( '.serfe-settings-tabs__indicator' );
			var activeTab = tabList.find( '.nav-tab-active' );

			if ( ! tabs.length || ! indicator.length || ! activeTab.length ) {
				return;
			}

			function moveIndicator( tab ) {
				var tabBounds = tab.getBoundingClientRect();
				var listBounds = tabList[ 0 ].getBoundingClientRect();
				var position = tabBounds.left - listBounds.left + tabList.scrollLeft();

				indicator.css( {
					transform: 'translateX(' + Math.round( position ) + 'px)',
					width: Math.round( tabBounds.width ) + 'px'
				} );
			}

			function restoreActiveIndicator() {
				moveIndicator( activeTab[ 0 ] );
			}

			moveIndicator( activeTab[ 0 ] );
			tabList.addClass( 'is-ready' );
			tabs.on( 'mouseenter focus', function() {
				moveIndicator( this );
			} );
			tabList.on( 'mouseleave', restoreActiveIndicator );
			tabs.on( 'blur', function() {
				window.setTimeout( function() {
					if ( ! tabList.find( ':focus' ).length ) {
						restoreActiveIndicator();
					}
				}, 0 );
			} );
			$( window ).on( 'resize', restoreActiveIndicator );
			if ( window.ResizeObserver ) {
				new window.ResizeObserver( restoreActiveIndicator ).observe( tabList[ 0 ] );
			}
		} );

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
