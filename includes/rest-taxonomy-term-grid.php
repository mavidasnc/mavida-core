<?php
/**
 * Endpoint REST che alimentano l'editor del blocco "Griglia Tassonomia": elenco delle
 * tassonomie selezionabili e, scelta una, elenco dei suoi termini (per popolare le select
 * "termini da includere/escludere").
 *
 * @package Mavida_Core
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'mavida_core_register_taxonomy_term_grid_rest_routes' ) ) {
	/**
	 * Registra le rotte GET mavida-core/v1/taxonomies e GET mavida-core/v1/taxonomy-terms.
	 */
	function mavida_core_register_taxonomy_term_grid_rest_routes() {
		register_rest_route(
			'mavida-core/v1',
			'/taxonomies',
			array(
				'methods'             => 'GET',
				'callback'            => 'mavida_core_rest_get_taxonomies',
				// Dato consumato solo dall'editor: richiede la capability minima per modificare
				// contenuti, non e' un endpoint pubblico.
				'permission_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);

		register_rest_route(
			'mavida-core/v1',
			'/taxonomy-terms',
			array(
				'methods'             => 'GET',
				'callback'            => 'mavida_core_rest_get_taxonomy_terms',
				'permission_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
				'args'                => array(
					'taxonomy' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
					),
				),
			)
		);
	}
}
add_action( 'rest_api_init', 'mavida_core_register_taxonomy_term_grid_rest_routes' );

if ( ! function_exists( 'mavida_core_rest_get_taxonomies' ) ) {
	/**
	 * Callback della rotta REST: elenco delle tassonomie pubbliche selezionabili nel blocco
	 * (incluse quelle native, category/post_tag, e quelle custom registrate via CPT UI).
	 *
	 * @return WP_REST_Response
	 */
	function mavida_core_rest_get_taxonomies() {
		$taxonomies = get_taxonomies( array( 'public' => true ), 'objects' );

		$data = array_values(
			array_map(
				function ( WP_Taxonomy $taxonomy ) {
					return array(
						'slug'  => $taxonomy->name,
						'label' => $taxonomy->labels->singular_name ? $taxonomy->labels->singular_name : $taxonomy->label,
					);
				},
				$taxonomies
			)
		);

		return rest_ensure_response( $data );
	}
}

if ( ! function_exists( 'mavida_core_rest_get_taxonomy_terms' ) ) {
	/**
	 * Callback della rotta REST: elenco dei termini di una tassonomia, usato per popolare le
	 * select "termini da includere/escludere" nel pannello del blocco.
	 *
	 * @param WP_REST_Request $request Richiesta REST, con il parametro "taxonomy".
	 * @return WP_REST_Response|WP_Error
	 */
	function mavida_core_rest_get_taxonomy_terms( WP_REST_Request $request ) {
		$taxonomy = $request->get_param( 'taxonomy' );

		// Rifiuta esplicitamente una tassonomia inesistente o non pubblica: non deve essere
		// possibile usare questo endpoint per elencare i termini di tassonomie private/interne.
		if ( ! taxonomy_exists( $taxonomy ) || ! is_taxonomy_viewable( $taxonomy ) ) {
			return new WP_Error(
				'mavida_core_invalid_taxonomy',
				__( 'Tassonomia non valida.', 'mavida-core' ),
				array( 'status' => 400 )
			);
		}

		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);

		if ( is_wp_error( $terms ) ) {
			return new WP_Error(
				'mavida_core_terms_query_failed',
				__( 'Impossibile recuperare i termini della tassonomia.', 'mavida-core' ),
				array( 'status' => 500 )
			);
		}

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
