( function() {
	'use strict';
	var config = window.serfeAiDiscoveryFiles || {};

	function fallbackCopy( area ) {
		area.focus();
		area.select();
		return document.execCommand( 'copy' );
	}

	function copiedState( button, copied ) {
		var icon = button.querySelector( '.serfe-copy-icon' );
		var label = button.querySelector( '[data-serfe-copy-label]' );
		var status = button.querySelector( '[data-serfe-copy-status]' );

		button.classList.toggle( 'is-copied', copied );
		if ( icon ) {
			icon.textContent = copied ? '✓' : '⧉';
		}
		if ( label ) {
			label.textContent = copied ? config.copiedLabel : config.copyLabel;
		}
		if ( status ) {
			status.textContent = copied ? config.copiedStatus : '';
		}
	}

	function protectedState( button, updated ) {
		var icon = button.querySelector( '.serfe-protect-icon' );
		var label = button.querySelector( '[data-serfe-protect-label]' );

		button.classList.toggle( 'is-updated', updated );
		if ( icon ) {
			icon.textContent = updated ? '✓' : '+';
		}
		if ( label ) {
			label.textContent = updated ? config.protectedLabel : config.protectLabel;
		}
	}

	function templateState( button, message, busy ) {
		var label = button.querySelector( '[data-serfe-template-label]' );
		var status = button.querySelector( '[data-serfe-template-status]' );
		var form = button.closest( 'form' );
		var feedback = form ? form.querySelector( '[data-serfe-template-feedback]' ) : null;

		button.disabled = busy;
		if ( label ) {
			label.textContent = busy ? message : config.templateLabel;
		}
		if ( status ) {
			status.textContent = message;
		}
		if ( feedback ) {
			window.clearTimeout( feedback._serfeTemplateTimer );
			feedback.textContent = busy ? '' : message;
			feedback.hidden = busy;
			feedback.classList.toggle( 'is-error', ! busy && message === window.serfeAiDiscoveryFiles.failed );
			if ( ! busy && message !== window.serfeAiDiscoveryFiles.failed ) {
				feedback._serfeTemplateTimer = window.setTimeout( function() {
					feedback.hidden = true;
					feedback.textContent = '';
				}, 5000 );
			}
		}
	}

	document.addEventListener( 'click', function( event ) {
		var protect = event.target.closest( '[data-serfe-protect-section]' );
		if ( ! protect ) {
			return;
		}
		var input = document.getElementById( protect.getAttribute( 'data-serfe-section-input' ) );
		var area = document.getElementById( protect.getAttribute( 'data-serfe-content-signals' ) );
		var status = protect.querySelector( '[data-serfe-section-status]' );
		var path = input ? input.value.trim().replace( /\*$/, '' ) : '';
		if ( ! area || ! input || ! /^\/(?!\/)/.test( path ) || /[?#]/.test( path ) || '/' === path ) {
			if ( status ) {
				status.textContent = config.invalidPath;
			}
			return;
		}
		path = path.replace( /\/+$/, '' ) + '/';
		input.value = path;
		area.value = area.getAttribute( 'data-serfe-base' ).replace(
			'\n# ---------------------------\n# END SERFE BLOCK',
			'\n\n# Search-only access to this section\nUser-Agent: *\nContent-Signal: ' + path + ' ai-train=no, search=yes, ai-input=no\nAllow: ' + path + '\n\n# ---------------------------\n# END SERFE BLOCK'
		);
		area.classList.remove( 'is-updated' );
		window.requestAnimationFrame( function() {
			area.classList.add( 'is-updated' );
		} );
		protectedState( protect, true );
		window.setTimeout( function() {
			area.classList.remove( 'is-updated' );
			protectedState( protect, false );
		}, 500 );
		if ( status ) {
			status.textContent = config.protectedStatus;
		}
	} );

	document.addEventListener( 'click', function( event ) {
		var button = event.target.closest( '[data-serfe-copy-file]' );
		if ( ! button ) {
			return;
		}
		var area = document.getElementById( button.getAttribute( 'data-serfe-copy-file' ) );
		if ( ! area ) {
			return;
		}
		var copy = navigator.clipboard && window.isSecureContext
			? navigator.clipboard.writeText( area.value )
			: Promise.resolve( fallbackCopy( area ) );

		copy.then( function() {
			copiedState( button, true );
			window.setTimeout( function() {
				copiedState( button, false );
			}, 1800 );
		} ).catch( function() {
			var status = button.querySelector( '[data-serfe-copy-status]' );
			if ( status ) {
				status.textContent = config.copyFailed;
			}
			area.focus();
			area.select();
		} );
	} );

	document.addEventListener( 'click', function( event ) {
		var button = event.target.closest( '[data-serfe-insert-template]' );
		if ( ! button || ! window.serfeAiDiscoveryFiles || ! window.fetch ) {
			return;
		}
		event.preventDefault();
		var area = document.getElementById( button.getAttribute( 'data-serfe-template-target' ) );
		if ( ! area ) {
			return;
		}
		templateState( button, window.serfeAiDiscoveryFiles.generating, true );
		window.fetch( window.serfeAiDiscoveryFiles.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
			},
			body: new URLSearchParams( {
				action: 'serfe_ai_discovery_llms_template',
				nonce: window.serfeAiDiscoveryFiles.templateNonce
			} )
		} ).then( function( response ) {
			return response.json();
		} ).then( function( response ) {
			if ( ! response.success || ! response.data || 'string' !== typeof response.data.content ) {
				throw new Error( config.templateError );
			}
			area.value = response.data.content;
			area.classList.remove( 'is-updated' );
			window.requestAnimationFrame( function() {
				area.classList.add( 'is-updated' );
			} );
			window.setTimeout( function() {
				area.classList.remove( 'is-updated' );
			}, 500 );
			templateState( button, window.serfeAiDiscoveryFiles.inserted, false );
		} ).catch( function() {
			templateState( button, window.serfeAiDiscoveryFiles.failed, false );
		} );
	} );

	document.addEventListener( 'submit', function( event ) {
		var submitter = event.submitter;
		if ( submitter && submitter.matches( '[data-serfe-release-control]' ) && ! window.confirm( config.releaseConfirm ) ) {
			event.preventDefault();
		}
	} );
}() );
