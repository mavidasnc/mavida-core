<?php
/**
 * Carica CodeMirror (già incluso in WordPress core, lo stesso usato da Aspetto > Personalizza >
 * CSS aggiuntivo) nell'editor a blocchi, per l'evidenziazione sintattica nella modale
 * "Personalizza CSS" del blocco "Griglia categorie prodotto" (vedi src/product-category-grid/edit.js).
 *
 * @package Mavida_Core
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'mavida_core_enqueue_css_editor_assets' ) ) {
	/**
	 * Registra le impostazioni di CodeMirror per un editor di tipo CSS, se l'utente non ha
	 * disattivato l'evidenziazione sintattica dal proprio profilo (nel qual caso
	 * wp_enqueue_code_editor() restituisce false: la modale degrada a una textarea semplice,
	 * senza errori).
	 */
	function mavida_core_enqueue_css_editor_assets() {
		$settings = wp_enqueue_code_editor( array( 'type' => 'text/css' ) );

		if ( false === $settings ) {
			return;
		}

		wp_add_inline_script(
			'code-editor',
			'window.mavidaCoreCssEditorSettings = ' . wp_json_encode( $settings ) . ';'
		);
	}
}
add_action( 'enqueue_block_editor_assets', 'mavida_core_enqueue_css_editor_assets' );
