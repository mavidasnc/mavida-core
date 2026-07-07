<?php
/**
 * Registrazione del blocco Gutenberg "Griglia categorie prodotto".
 *
 * @package Mavida_Core
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'mavida_core_register_blocks' ) ) {
	/**
	 * Registra il blocco dinamico leggendo i metadati da build/product-category-grid/block.json.
	 * Il rendering e' delegato al render.php referenziato nel block.json (attributo "render"),
	 * quindi non serve passare qui un render_callback.
	 */
	function mavida_core_register_blocks() {
		register_block_type( MAVIDA_CORE_PATH . 'build/product-category-grid' );
	}
}
add_action( 'init', 'mavida_core_register_blocks' );
