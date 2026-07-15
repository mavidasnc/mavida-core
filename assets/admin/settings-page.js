/**
 * Script della pagina opzioni "Mavida Core": gestisce il pulsante "Controlla
 * aggiornamenti" della tab Aggiornamenti, tramite una chiamata AJAX classica
 * (admin-ajax.php), coerente con l'handler PHP in includes/updater.php; e il
 * pulsante "Svuota cache" della tab Generale, tramite l'endpoint REST gia'
 * usato dal pannello del blocco (includes/block-cache.php).
 */
( function ( $ ) {
	'use strict';

	/**
	 * Escape minimale per inserire testo dinamico dentro markup HTML.
	 *
	 * @param {string} value Testo da escapare.
	 * @return {string} Testo sicuro per l'inserimento in HTML.
	 */
	function escHtml( value ) {
		return String( value )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' );
	}

	/**
	 * Costruisce il markup dell'avviso "aggiornamento disponibile", con link alla
	 * release e changelog (gia' in HTML sicuro, generato lato server con wp_kses_post).
	 *
	 * @param {Object} data Dati restituiti dall'handler AJAX in caso di successo.
	 * @return {string} Markup HTML dell'avviso.
	 */
	function buildUpdateNotice( data ) {
		var i18n = ( window.mavidaCoreAdmin && mavidaCoreAdmin.i18n ) ? mavidaCoreAdmin.i18n : {};
		var message = ( i18n.updateAvailable || '' ).replace( '%s', escHtml( data.latest ) );
		var link = data.html_url
			? ' <a href="' + data.html_url + '" target="_blank" rel="noopener noreferrer">' + escHtml( data.latest ) + '</a>'
			: '';

		return (
			'<div class="notice notice-warning inline"><p>' + message + link + '</p>' +
			( data.changelog ? '<div class="mavida-core-changelog">' + data.changelog + '</div>' : '' ) +
			'</div>'
		);
	}

	/**
	 * Collega il pulsante "Controlla aggiornamenti" alla chiamata AJAX.
	 */
	function initUpdatesTab() {
		var $btn = $( '#mavida-core-check-update' );

		if ( ! $btn.length ) {
			return;
		}

		var i18n = ( window.mavidaCoreAdmin && mavidaCoreAdmin.i18n ) ? mavidaCoreAdmin.i18n : {};
		var $status = $( '#mavida-core-update-status' );
		var $latest = $( '#mavida-core-latest-version' );
		var $result = $( '#mavida-core-update-result' );

		$btn.on( 'click', function () {
			$btn.prop( 'disabled', true );
			$status.text( i18n.checkingUpdate || '' );
			$result.empty();

			$.post( mavidaCoreAdmin.ajaxUrl, {
				action: 'mavida_core_check_update',
				nonce: mavidaCoreAdmin.nonce,
			} )
				.done( function ( response ) {
					if ( ! response || ! response.success || ! response.data ) {
						var errorMessage = ( response && response.data && response.data.message ) || i18n.errorCheck || '';
						$status.text( errorMessage );
						return;
					}

					var d = response.data;
					$status.text( '' );
					$latest.text( d.latest );

					if ( d.update_available ) {
						$result.html( buildUpdateNotice( d ) );
					} else {
						$result.html(
							'<div class="notice notice-success inline"><p>' + escHtml( i18n.upToDate || '' ) + '</p></div>'
						);
					}
				} )
				.fail( function () {
					$status.text( i18n.errorCheck || '' );
				} )
				.always( function () {
					$btn.prop( 'disabled', false );
				} );
		} );
	}

	/**
	 * Collega il pulsante "Svuota cache" della tab Generale all'endpoint REST
	 * mavida-core/v1/purge-cache (stesso endpoint del pulsante nel pannello del blocco).
	 */
	function initGridCachePurge() {
		var $btn = $( '#mavida-core-purge-grid-cache' );

		if ( ! $btn.length ) {
			return;
		}

		var i18n = ( window.mavidaCoreAdmin && mavidaCoreAdmin.i18n ) ? mavidaCoreAdmin.i18n : {};
		var $status = $( '#mavida-core-purge-grid-cache-status' );

		$btn.on( 'click', function () {
			$btn.prop( 'disabled', true );
			$status.text( '' );

			fetch( mavidaCoreAdmin.restUrl, {
				method: 'POST',
				headers: {
					'X-WP-Nonce': mavidaCoreAdmin.restNonce,
				},
			} )
				.then( function ( response ) {
					return response.ok ? response.json() : Promise.reject();
				} )
				.then( function () {
					$status.text( i18n.cachePurged || '' );
				} )
				.catch( function () {
					$status.text( i18n.cachePurgeError || '' );
				} )
				.finally( function () {
					$btn.prop( 'disabled', false );
				} );
		} );
	}

	$( function () {
		initUpdatesTab();
		initGridCachePurge();
	} );
} )( jQuery );
