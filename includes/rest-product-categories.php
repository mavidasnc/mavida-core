<?php
/**
 * Endpoint REST che alimenta la select delle categorie escluse nell'editor del blocco.
 * Restituisce solo i campi necessari (id, nome, conteggio prodotti), non l'intero oggetto WP_Term.
 *
 * @package Mavida_Core
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'mavida_core_register_rest_routes' ) ) {
	/**
	 * Registra la rotta GET mavida-core/v1/product-categories.
	 */
	function mavida_core_register_rest_routes() {
		register_rest_route(
			'mavida-core/v1',
			'/product-categories',
			array(
				'methods'             => 'GET',
				'callback'            => 'mavida_core_rest_get_product_categories',
				// Dato consumato solo dall'editor: richiede la capability minima per modificare contenuti,
				// non e' un endpoint pubblico.
				'permission_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);
	}
}
add_action( 'rest_api_init', 'mavida_core_register_rest_routes' );

if ( ! function_exists( 'mavida_core_rest_get_product_categories' ) ) {
	/**
	 * Callback della rotta REST: elenco delle categorie prodotto di primo livello.
	 *
	 * @return WP_REST_Response
	 */
	function mavida_core_rest_get_product_categories() {
		$terms = mavida_core_get_product_categories( array( 'hide_empty' => false ) );

		$data = array_map(
			function ( WP_Term $term ) {
				return array(
					'id'    => $term->term_id,
					'name'  => $term->name,
					'count' => $term->count,
				);
			},
			$terms
		);

		return rest_ensure_response( $data );
	}
}
