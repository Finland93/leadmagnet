/**
 * LeadMagnet — front-end form handler.
 * Vanilla JS, no jQuery dependency. Progressive: works without CAPTCHA.
 */
( function () {
	'use strict';

	/**
	 * Read a query-string parameter.
	 * @param {string} name Param name.
	 * @return {string}
	 */
	function qp( name ) {
		var m = new RegExp( '[?&]' + name + '=([^&]+)' ).exec( window.location.search );
		return m ? decodeURIComponent( m[ 1 ].replace( /\+/g, ' ' ) ) : '';
	}

	/**
	 * Populate hidden tracking fields.
	 * @param {HTMLFormElement} form Form.
	 */
	function fillTracking( form ) {
		var landing = form.querySelector( '[data-lmf93-landing]' );
		if ( landing ) {
			landing.value = window.location.pathname + window.location.search;
		}
		var utms = form.querySelectorAll( '[data-lmf93-utm]' );
		utms.forEach( function ( el ) {
			el.value = qp( el.getAttribute( 'data-lmf93-utm' ) );
		} );
	}

	/**
	 * Serialize form to a plain object supporting fields[...] and consents[...].
	 * @param {HTMLFormElement} form Form.
	 * @return {Object}
	 */
	function serialize( form ) {
		var data = { fields: {}, consents: {} };
		var els = form.elements;
		for ( var i = 0; i < els.length; i++ ) {
			var el = els[ i ];
			if ( ! el.name ) {
				continue;
			}
			var name = el.name;

			// Skip unchecked checkboxes/radios.
			if ( ( el.type === 'checkbox' || el.type === 'radio' ) && ! el.checked ) {
				continue;
			}

			// fields[key] or fields[key][]
			var fm = name.match( /^fields\[([^\]]+)\](\[\])?$/ );
			if ( fm ) {
				var key = fm[ 1 ];
				if ( fm[ 2 ] ) {
					data.fields[ key ] = data.fields[ key ] || [];
					data.fields[ key ].push( el.value );
				} else {
					data.fields[ key ] = el.value;
				}
				continue;
			}

			// consents[purpose]
			var cm = name.match( /^consents\[([^\]]+)\]$/ );
			if ( cm ) {
				data.consents[ cm[ 1 ] ] = el.checked ? 1 : el.value;
				continue;
			}

			// Everything else (hidden tracking, nonce, honeypot, ts, captcha).
			data[ name ] = el.value;
		}
		return data;
	}

	/**
	 * Show a message in the form's message area.
	 * @param {HTMLElement} box  Message container.
	 * @param {string}      text Message.
	 * @param {string}      type success | error.
	 */
	function showMessage( box, text, type ) {
		if ( ! box ) {
			return;
		}
		box.textContent = text;
		box.className = 'lmf93-messages lmf93-' + type;
	}

	/**
	 * Attach handlers to a single form.
	 * @param {HTMLFormElement} form Form.
	 */
	/**
	 * Optional postal-code -> city auto-fill (country-agnostic).
	 *
	 * Only runs when the site owner has enabled auto-fill and provided a
	 * dataset ( { "CODE": "City", ... } ). When disabled, the city field is a
	 * normal editable input, so the form works in every country out of the box.
	 * @param {HTMLFormElement} form Form.
	 */
	var lmf93Postcodes = null;
	var lmf93PostcodesLoading = false;
	function initPostcodeLookup( form ) {
		var cfg  = window.LMF93 || {};
		if ( ! cfg.autocity ) { return; }

		var pc   = form.querySelector( '[data-lmf93-postcode]' );
		var city = form.querySelector( '[data-lmf93-city]' );
		if ( ! pc || ! city ) { return; }

		var url        = cfg.postcodeUrl || '';
		var numeric    = ( cfg.postalInputMode === 'numeric' );
		var maxLen     = parseInt( cfg.postalMaxLength, 10 ) || 12;
		var unknownTxt = ( cfg.i18n && cfg.i18n.unknownPostcode ) ? cfg.i18n.unknownPostcode : 'Unknown postal code';

		if ( ! url ) { return; }

		function loadData( cb ) {
			if ( lmf93Postcodes ) { cb(); return; }
			if ( lmf93PostcodesLoading ) { return; }
			lmf93PostcodesLoading = true;
			var xhr = new XMLHttpRequest();
			xhr.open( 'GET', url, true );
			xhr.onreadystatechange = function () {
				if ( 4 === xhr.readyState ) {
					lmf93PostcodesLoading = false;
					if ( 200 === xhr.status ) {
						try { lmf93Postcodes = JSON.parse( xhr.responseText ); cb(); } catch ( e ) {}
					}
				}
			};
			xhr.send();
		}

		/**
		 * Normalize the entered code for dataset lookup. In numeric mode we keep
		 * digits only; otherwise we keep the alphanumeric value uppercased and
		 * space-collapsed, which matches typical dataset keys.
		 * @param {string} raw Raw input.
		 * @return {string}
		 */
		function normalize( raw ) {
			raw = ( raw || '' ).toString();
			if ( numeric ) {
				return raw.replace( /\D/g, '' ).slice( 0, maxLen );
			}
			return raw.toUpperCase().replace( /\s+/g, ' ' ).trim().slice( 0, maxLen );
		}

		function lookup() {
			var code = normalize( pc.value );
			if ( code.length < 2 ) { city.value = ''; return; }
			loadData( function () {
				if ( ! lmf93Postcodes ) { return; }
				// Try the normalized key, then a space-free variant (e.g. "SW1A1AA").
				var name = lmf93Postcodes[ code ];
				if ( ! name ) { name = lmf93Postcodes[ code.replace( /\s+/g, '' ) ]; }
				if ( name ) {
					city.value = name;
					city.style.color = '';
				} else {
					city.value = unknownTxt;
					city.style.color = '#b3402f';
				}
				city.dispatchEvent( new Event( 'input', { bubbles: true } ) );
			} );
		}

		pc.addEventListener( 'focus', function () { loadData( function () {} ); } );
		pc.addEventListener( 'input', lookup );
		pc.addEventListener( 'blur', lookup );
		if ( pc.value ) { lookup(); }
	}

	function initForm( form ) {
		fillTracking( form );
		initPostcodeLookup( form );

		var endpoint = form.getAttribute( 'data-endpoint' );
		var msgBox = form.querySelector( '.lmf93-messages' );
		var submitBtn = form.querySelector( '.lmf93-submit' );

		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();

			// reCAPTCHA v3: fetch a fresh token just before submit.
			var v3 = form.querySelector( '[data-lmf93-recaptcha-v3]' );
			if ( v3 && window.grecaptcha && window.LMF93 && window.LMF93.captchaSiteKey ) {
				window.grecaptcha.ready( function () {
					window.grecaptcha
						.execute( window.LMF93.captchaSiteKey, { action: 'lead' } )
						.then( function ( token ) {
							v3.value = token;
							doSubmit();
						} );
				} );
				return;
			}
			doSubmit();

			function doSubmit() {
				if ( submitBtn ) {
					submitBtn.disabled = true;
					submitBtn.classList.add( 'lmf93-loading' );
				}
				showMessage( msgBox, '', '' );

				var payload = serialize( form );

				fetch( endpoint, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': payload.lmf93_nonce || ''
					},
					body: JSON.stringify( payload )
				} )
					.then( function ( res ) {
						return res.json().then( function ( body ) {
							return { ok: res.ok, body: body };
						} );
					} )
					.then( function ( result ) {
						if ( result.ok && result.body && result.body.success ) {
							form.reset();
							showMessage(
								msgBox,
								result.body.message || 'Thank you!',
								'success'
							);
							form.classList.add( 'lmf93-submitted' );
							// Fire a JS event sites can hook (e.g. GA conversion).
							document.dispatchEvent(
								new CustomEvent( 'lmf93:submitted', {
									detail: { reference: result.body.reference }
								} )
							);
						} else {
							var errMsg =
								( result.body && result.body.message ) ||
								'Something went wrong. Please try again.';
							showMessage( msgBox, errMsg, 'error' );
							resetCaptcha();
						}
					} )
					.catch( function () {
						showMessage(
							msgBox,
							'Network error. Please try again.',
							'error'
						);
						resetCaptcha();
					} )
					.finally( function () {
						if ( submitBtn ) {
							submitBtn.disabled = false;
							submitBtn.classList.remove( 'lmf93-loading' );
						}
					} );
			}

			function resetCaptcha() {
				try {
					if ( window.turnstile ) {
						window.turnstile.reset();
					}
					if ( window.grecaptcha && window.grecaptcha.reset ) {
						window.grecaptcha.reset();
					}
				} catch ( err ) {}
			}
		} );
	}

	// CAPTCHA callbacks (set token into hidden input).
	window.lmf93TurnstileCb = function ( token ) {
		document.querySelectorAll( 'input[name="lmf93_captcha"]' ).forEach( function ( el ) {
			el.value = token;
		} );
	};
	window.lmf93RecaptchaCb = function ( token ) {
		document.querySelectorAll( 'input[name="lmf93_captcha"]' ).forEach( function ( el ) {
			el.value = token;
		} );
	};

	document.addEventListener( 'DOMContentLoaded', function () {
		document.querySelectorAll( '.lmf93-form' ).forEach( initForm );
	} );
} )();
