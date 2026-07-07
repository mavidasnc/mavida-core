<?php
/**
 * Aggiunge due colonne nell'elenco prodotti di Bacheca (edit.php?post_type=product):
 * "Codice Marelli" e "Codice OE", lette dai meta prodotto omonimi (campi custom
 * "codice_marelli" e "codice_oe", distinti da SKU e GTIN nativi di WooCommerce).
 *
 * @package Mavida_Core
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'mavida_core_add_product_columns' ) ) {
	/**
	 * Inserisce le due colonne subito dopo la colonna SKU (se presente), altrimenti in coda.
	 *
	 * @param array $columns Colonne esistenti dell'elenco prodotti.
	 * @return array
	 */
	function mavida_core_add_product_columns( $columns ) {
		$new_columns = array();

		foreach ( $columns as $key => $label ) {
			$new_columns[ $key ] = $label;

			if ( 'sku' === $key ) {
				$new_columns['codice_marelli'] = __( 'Codice Marelli', 'mavida-core' );
				$new_columns['codice_oe']      = __( 'Codice OE', 'mavida-core' );
			}
		}

		// Colonna SKU non presente (es. rimossa da un altro plugin): aggiungi comunque in coda.
		if ( ! isset( $new_columns['codice_marelli'] ) ) {
			$new_columns['codice_marelli'] = __( 'Codice Marelli', 'mavida-core' );
			$new_columns['codice_oe']      = __( 'Codice OE', 'mavida-core' );
		}

		return $new_columns;
	}
}
add_filter( 'manage_edit-product_columns', 'mavida_core_add_product_columns' );

if ( ! function_exists( 'mavida_core_render_product_column' ) ) {
	/**
	 * Stampa il valore del meta corrispondente per le due colonne aggiunte.
	 *
	 * @param string $column  Slug della colonna corrente.
	 * @param int    $post_id ID del prodotto della riga corrente.
	 */
	function mavida_core_render_product_column( $column, $post_id ) {
		if ( ! in_array( $column, array( 'codice_marelli', 'codice_oe' ), true ) ) {
			return;
		}

		echo esc_html( get_post_meta( $post_id, $column, true ) );
	}
}
add_action( 'manage_product_posts_custom_column', 'mavida_core_render_product_column', 10, 2 );
