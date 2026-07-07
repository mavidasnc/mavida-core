<?php
/**
 * Iniezione dinamica delle categorie prodotto come sottovoci di una voce
 * del menu nativo di WordPress, identificata tramite una classe CSS configurabile
 * dalla pagina opzioni (vedi includes/settings-page.php).
 *
 * @package Mavida_Core
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'mavida_core_inject_category_menu_items' ) ) {
	/**
	 * Filtra gli item di un menu di navigazione aggiungendo, come figli delle voci
	 * che hanno la classe CSS configurata, le categorie prodotto di primo livello.
	 *
	 * @param array  $items Voci di menu gia' recuperate.
	 * @param object $menu  Oggetto termine del menu (non usato).
	 * @param array  $args  Argomenti di wp_nav_menu (non usato).
	 * @return array Voci di menu, con le sottovoci categoria eventualmente aggiunte.
	 */
	function mavida_core_inject_category_menu_items( $items, $menu, $args ) {
		// Questo filtro viene eseguito anche nell'editor dei menu (Aspetto > Menu):
		// se agisse anche li' finirebbe per "sporcare" l'interfaccia di gestione.
		// Va applicato solo al rendering front-end.
		if ( is_admin() ) {
			return $items;
		}

		$target_class = mavida_core_get_css_class();

		if ( '' === $target_class ) {
			return $items;
		}

		// Trova le voci di menu (possono essere piu' di una) che hanno la classe CSS target.
		$parent_items = array();

		foreach ( $items as $item ) {
			if ( in_array( $target_class, (array) $item->classes, true ) ) {
				$parent_items[] = $item;
			}
		}

		if ( empty( $parent_items ) ) {
			return $items;
		}

		$categories = mavida_core_get_product_categories();

		if ( empty( $categories ) ) {
			return $items;
		}

		$queried_object = get_queried_object();

		foreach ( $parent_items as $parent_item ) {
			$menu_order = $parent_item->menu_order;

			foreach ( $categories as $category ) {
				$term_link = get_term_link( $category );

				// get_term_link() puo' restituire un WP_Error (es. tassonomia non pubblica): salta il termine.
				if ( is_wp_error( $term_link ) ) {
					continue;
				}

				++$menu_order;

				// ID sintetico negativo e deterministico: non collide mai con un vero ID di post/menu item.
				$synthetic_id = - ( 1000000 + $category->term_id );

				$classes = array( 'menu-item', 'menu-item-mavida-cat' );

				// Evidenzia la sottovoce quando si sta visualizzando proprio quella categoria.
				if ( $queried_object instanceof WP_Term && 'product_cat' === $queried_object->taxonomy && $queried_object->term_id === $category->term_id ) {
					$classes[] = 'current-menu-item';
				}

				$menu_item = (object) array(
					'ID'               => $synthetic_id,
					'db_id'            => $synthetic_id,
					'menu_item_parent' => $parent_item->ID,
					'object_id'        => $category->term_id,
					'object'           => 'product_cat',
					'type'             => 'taxonomy',
					'type_label'       => __( 'Categoria prodotto', 'mavida-core' ),
					'title'            => $category->name,
					'url'              => $term_link,
					'target'           => '',
					'attr_title'       => '',
					'description'      => '',
					'classes'          => $classes,
					'xfn'              => '',
					'menu_order'       => $menu_order,
				);

				$items[] = wp_setup_nav_menu_item( $menu_item );
			}
		}

		return $items;
	}
}
add_filter( 'wp_get_nav_menu_items', 'mavida_core_inject_category_menu_items', 20, 3 );
