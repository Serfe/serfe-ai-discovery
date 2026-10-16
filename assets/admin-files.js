( function() {
	'use strict';

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
			label.textContent = copied ? 'Copied' : 'Copy to clipboard';
		}
		if ( status ) {
			status.textContent = copied ? 'Content copied to clipboard.' : '';
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
			label.textContent = updated ? 'Protection added' : 'Protect section from AI-Train';
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
				status.textContent = 'Enter a section path such as /blog/.';
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
			status.textContent = 'Section protection added to the copyable block.';
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
				status.textContent = 'Copy failed. Select the content and copy it manually.';
			}
			area.focus();
			area.select();
		} );
	} );

	document.addEventListener( 'submit', function( event ) {
		var submitter = event.submitter;
		if ( submitter && submitter.matches( '[data-serfe-release-control]' ) && ! window.confirm( 'Release Serfe control? The current file will not change, but Serfe metadata and backups will be removed.' ) ) {
			event.preventDefault();
		}
	} );
}() );
