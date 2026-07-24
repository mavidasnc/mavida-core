<?php
/**
 * Invalidazione della cache dei blocchi griglia del plugin ("Griglia categorie prodotto",
 * "Griglia post per tipo di contenuto", "Griglia Tassonomia" — tutti leggono la stessa
 * option "mavida_core_cache_version" nella propria firma di cache), tramite un endpoint
 * REST richiamato dal pulsante "Svuota cache" nel pannello di ciascun blocco.
 *
 * @package Mavida_Core
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'mavida_core_register_cache_rest_routes' ) ) {
	/**
	 * Registra la rotta POST mavida-core/v1/purge-cache.
	 */
	function mavida_core_register_cache_rest_routes() {
		register_rest_route(
			'mavida-core/v1',
			'/purge-cache',
			array(
				'methods'             => 'POST',
				'callback'            => 'mavida_core_rest_purge_cache',
				// Azione con effetto globale su tutte le pagine del sito: richiede la
				// capability di amministrazione, non solo quella minima per modificare contenuti.
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			)
		);
	}
}
add_action( 'rest_api_init', 'mavida_core_register_cache_rest_routes' );

if ( ! function_exists( 'mavida_core_rest_purge_cache' ) ) {
	/**
	 * Incrementa la "versione" di cache: ogni chiave calcolata in precedenza da
	 * render.php diventa cosi' irraggiungibile, senza dover enumerare o cancellare
	 * i singoli transient (che nel frattempo scadono comunque in base al loro TTL).
	 *
	 * @return WP_REST_Response
	 */
	function mavida_core_rest_purge_cache() {
		$current_version = (int) get_option( 'mavida_core_cache_version', 1 );
		update_option( 'mavida_core_cache_version', $current_version + 1 );

		return rest_ensure_response( array( 'success' => true ) );
	}
}
