<?php
/**
 * Registrazione dei blocchi Gutenberg del plugin: "Griglia categorie prodotto",
 * "Griglia post per tipo di contenuto" e "Griglia Tassonomia".
 *
 * @package Mavida_Core
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'mavida_core_register_blocks' ) ) {
	/**
	 * Registra i blocchi dinamici leggendo i metadati dalle rispettive cartelle build/.
	 * Il rendering e' delegato al render.php referenziato in ciascun block.json (attributo
	 * "render"), quindi non serve passare qui un render_callback.
	 */
	function mavida_core_register_blocks() {
		// Il blocco "Griglia categorie prodotto" mostra categorie prodotto WooCommerce:
		// senza WooCommerce non avrebbe nulla da mostrare. Evita di registrarlo per non
		// lasciarlo comparire, vuoto e confuso, nell'inserimento blocchi.
		if ( class_exists( 'WooCommerce' ) ) {
			register_block_type( MAVIDA_CORE_PATH . 'build/product-category-grid' );
		}

		// Il blocco "Griglia post per tipo di contenuto" lavora sui CPT nativi di WordPress:
		// nessuna dipendenza da WooCommerce, sempre disponibile.
		register_block_type( MAVIDA_CORE_PATH . 'build/cpt-post-grid' );

		// Il blocco "Griglia Tassonomia" lavora su qualunque tassonomia pubblica del sito:
		// nessuna dipendenza da WooCommerce, sempre disponibile.
		register_block_type( MAVIDA_CORE_PATH . 'build/taxonomy-term-grid' );
	}
}
add_action( 'init', 'mavida_core_register_blocks' );
