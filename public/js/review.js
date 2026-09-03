/**
 * LeadMagnet review widget.
 *
 * 1–5 star rating. High ratings (>= threshold) redirect to the public
 * business review URL; low ratings reveal a private "what went wrong" form.
 */
( function () {
	'use strict';

	function init( root ) {
		var restUrl   = root.getAttribute( 'data-rest' );
		var token     = root.getAttribute( 'data-token' );
		var threshold = parseInt( root.getAttribute( 'data-threshold' ), 10 ) || 4;

		var stars   = Array.prototype.slice.call( root.querySelectorAll( '.lmf93-star' ) );
		var lowBox  = root.querySelector( '.lmf93-review-low' );
		var thanks  = root.querySelector( '.lmf93-review-thanks' );
		var errBox  = root.querySelector( '.lmf93-review-error-msg' );
		var sendBtn = root.querySelector( '.lmf93-review-send' );
		var reasonEl  = root.querySelector( '.lmf93-review-reason' );
		var commentEl = root.querySelector( '.lmf93-review-comment' );

		var chosen = 0;
		var busy   = false;

		if ( ! stars.length ) {
			return;
		}

		function paint( upto ) {
			stars.forEach( function ( s, i ) {
				if ( i < upto ) {
					s.classList.add( 'is-on' );
				} else {
					s.classList.remove( 'is-on' );
				}
			} );
		}

		function showError( msg ) {
			if ( ! errBox ) {
				return;
			}
			errBox.textContent = msg;
			errBox.hidden = false;
		}

		function post( payload, done ) {
			var xhr = new XMLHttpRequest();
			xhr.open( 'POST', restUrl, true );
			xhr.setRequestHeader( 'Content-Type', 'application/json' );
			xhr.onreadystatechange = function () {
				if ( xhr.readyState !== 4 ) {
					return;
				}
				var res = null;
				try {
					res = JSON.parse( xhr.responseText );
				} catch ( e ) {
					res = null;
				}
				done( xhr.status >= 200 && xhr.status < 300, res );
			};
			xhr.send( JSON.stringify( payload ) );
		}

		// Hover preview.
		stars.forEach( function ( star, idx ) {
			star.addEventListener( 'mouseenter', function () {
				if ( ! chosen ) {
					paint( idx + 1 );
				}
			} );
			star.addEventListener( 'mouseleave', function () {
				paint( chosen );
			} );
			star.addEventListener( 'click', function () {
				if ( busy ) {
					return;
				}
				chosen = idx + 1;
				paint( chosen );
				stars.forEach( function ( s, i ) {
					s.setAttribute( 'aria-checked', i < chosen ? 'true' : 'false' );
				} );

				// Submit the rating immediately.
				busy = true;
				post( { lmf93_token: token, rating: chosen }, function ( ok, res ) {
					busy = false;
					if ( ! ok || ! res ) {
						showError( 'Sending your feedback failed. Please try again in a moment.' );
						return;
					}
					if ( res.success === false ) {
						showError( res.message || 'This review link is not valid.' );
						return;
					}
					if ( res.redirect ) {
						// High rating: send them to the public review page.
						window.location.href = res.redirect;
						return;
					}
					if ( res.low && lowBox ) {
						// Low rating: reveal the private reason form.
						lowBox.hidden = false;
					} else if ( thanks ) {
						thanks.hidden = false;
					}
				} );
			} );
		} );

		// Low-rating reason submission (updates the same lead's latest feedback
		// intent by recording a second row with reason + comment).
		if ( sendBtn ) {
			sendBtn.addEventListener( 'click', function () {
				if ( busy ) {
					return;
				}
				busy = true;
				var payload = {
					lmf93_token: token,
					rating: chosen || 1,
					reason: reasonEl ? reasonEl.value : '',
					comment: commentEl ? commentEl.value : ''
				};
				post( payload, function ( ok ) {
					busy = false;
					if ( ! ok ) {
						showError( 'Sending your feedback failed. Please try again in a moment.' );
						return;
					}
					if ( lowBox ) {
						lowBox.hidden = true;
					}
					if ( thanks ) {
						thanks.hidden = false;
					}
				} );
			} );
		}
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		var roots = document.querySelectorAll( '.lmf93-review' );
		Array.prototype.forEach.call( roots, init );
	} );
} )();
