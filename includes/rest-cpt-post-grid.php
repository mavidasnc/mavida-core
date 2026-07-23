<?php
/**
 * Endpoint REST che alimentano l'editor del blocco "Griglia post per tipo di contenuto":
 * elenco dei tipi di contenuto selezionabili e, scelto uno, elenco dei suoi post
 * (per popolare le select "post da includere/escludere").
 *
 * @package Mavida_Core
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'mavida_core_register_cpt_post_grid_rest_routes' ) ) {
	/**
	 * Registra le rotte GET mavida-core/v1/post-types e GET mavida-core/v1/posts.
	 */
	function mavida_core_register_cpt_post_grid_rest_routes() {
		register_rest_route(
			'mavida-core/v1',
			'/post-types',
			array(
				'methods'             => 'GET',
				'callback'            => 'mavida_core_rest_get_post_types',
				// Dato consumato solo dall'editor: richiede la capability minima per modificare
				// contenuti, non e' un endpoint pubblico.
				'permission_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);

		register_rest_route(
			'mavida-core/v1',
			'/posts',
			array(
				'methods'             => 'GET',
				'callback'            => 'mavida_core_rest_get_cpt_posts',
				'permission_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
				'args'                => array(
					'post_type' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
					),
				),
			)
		);
	}
}
add_action( 'rest_api_init', 'mavida_core_register_cpt_post_grid_rest_routes' );

if ( ! function_exists( 'mavida_core_rest_get_post_types' ) ) {
	/**
	 * Callback della rotta REST: elenco dei tipi di contenuto pubblici selezionabili nel blocco.
	 * Esclude gli allegati, che non sono un tipo di contenuto sensato per una griglia di post.
	 *
	 * @return WP_REST_Response
	 */
	function mavida_core_rest_get_post_types() {
		$post_types = get_post_types( array( 'public' => true ), 'objects' );

		unset( $post_types['attachment'] );

		$data = array_values(
			array_map(
				function ( WP_Post_Type $post_type ) {
					return array(
						'slug'  => $post_type->name,
						'label' => $post_type->labels->singular_name,
					);
				},
				$post_types
			)
		);

		return rest_ensure_response( $data );
	}
}

if ( ! function_exists( 'mavida_core_rest_get_cpt_posts' ) ) {
	/**
	 * Callback della rotta REST: elenco dei post pubblicati di un tipo di contenuto, usato
	 * per popolare le select "post da includere/escludere" nel pannello del blocco.
	 *
	 * @param WP_REST_Request $request Richiesta REST, con il parametro "post_type".
	 * @return WP_REST_Response|WP_Error
	 */
	function mavida_core_rest_get_cpt_posts( WP_REST_Request $request ) {
		$post_type = $request->get_param( 'post_type' );

		// Rifiuta esplicitamente un tipo di contenuto inesistente o non pubblico: non deve
		// essere possibile usare questo endpoint per elencare i post di CPT privati/interni.
		if ( ! post_type_exists( $post_type ) || ! is_post_type_viewable( $post_type ) ) {
			return new WP_Error(
				'mavida_core_invalid_post_type',
				__( 'Tipo di contenuto non valido.', 'mavida-core' ),
				array( 'status' => 400 )
			);
		}

		$posts = get_posts(
			array(
				'post_type'   => $post_type,
				'post_status' => 'publish',
				'numberposts' => -1,
				'orderby'     => 'title',
				'order'       => 'ASC',
			)
		);

		$data = array_map(
			function ( WP_Post $post ) {
				return array(
					'id'    => $post->ID,
					'title' => get_the_title( $post ),
				);
			},
			$posts
		);

		return rest_ensure_response( $data );
	}
}
