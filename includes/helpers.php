<?php
/**
 * Funzioni di utilita' condivise da blocco, endpoint REST e iniezione menu.
 * Nessuna classe: procedurale, coerente con lo stile del tema mcparts.
 *
 * @package Mavida_Core
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'mavida_core_get_css_class' ) ) {
	/**
	 * Restituisce la classe CSS configurata nella pagina opzioni, gia' sanitizzata.
	 * Usata dal filtro di iniezione del menu per trovare la voce padre a cui agganciare
	 * le categorie prodotto.
	 *
	 * @return string Classe CSS, oppure stringa vuota se non impostata.
	 */
	function mavida_core_get_css_class() {
		$options = get_option( 'mavida_core_options', array() );
		$class   = isset( $options['menu_css_class'] ) ? $options['menu_css_class'] : 'mavida-product-cats';

		return sanitize_html_class( $class );
	}
}

if ( ! function_exists( 'mavida_core_show_product_columns' ) ) {
	/**
	 * Indica se le colonne prodotto extra (Codice Marelli, Codice OE) vanno mostrate
	 * nell'elenco prodotti di Bacheca, in base all'opzione della tab "Opzioni".
	 *
	 * @return bool
	 */
	function mavida_core_show_product_columns() {
		$options = get_option( 'mavida_core_options', array() );

		return ! empty( $options['show_product_columns'] );
	}
}

if ( ! function_exists( 'mavida_core_get_product_categories' ) ) {
	/**
	 * Wrapper su get_terms() per le categorie prodotto WooCommerce.
	 * Di default recupera solo le categorie di primo livello (parent = 0).
	 *
	 * @param array $args Argomenti aggiuntivi/override per get_terms().
	 * @return WP_Term[] Elenco categorie, array vuoto se WooCommerce non e' attivo.
	 */
	function mavida_core_get_product_categories( array $args = array() ) {
		// Se la tassonomia prodotto non esiste (WooCommerce disattivo), non fallire: nessuna categoria.
		if ( ! taxonomy_exists( 'product_cat' ) ) {
			return array();
		}

		$defaults = array(
			'taxonomy'   => 'product_cat',
			'parent'     => 0,
			'hide_empty' => false,
		);

		$terms = get_terms( array_merge( $defaults, $args ) );

		return is_wp_error( $terms ) ? array() : $terms;
	}
}

if ( ! function_exists( 'mavida_core_get_category_image_html' ) ) {
	/**
	 * Restituisce il markup <img> dell'immagine di categoria, con la stessa logica
	 * usata da Blocksy per le card categoria in archivio:
	 * 1) term meta standard WooCommerce "thumbnail_id";
	 * 2) fallback storico Blocksy dentro "blocksy_taxonomy_meta_options" (chiavi image/icon_image);
	 * 3) immagine di default configurata nel pannello del blocco (se impostata);
	 * 4) placeholder WooCommerce.
	 *
	 * @param WP_Term $term                  Il termine categoria prodotto.
	 * @param string  $size                  Nome della dimensione immagine da usare.
	 * @param int     $default_attachment_id ID allegato da usare se la categoria non ha
	 *                                        un'immagine propria (0 = nessuna immagine di default).
	 * @return string Markup HTML gia' pronto per l'output (le funzioni WP usate escapano gia').
	 */
	function mavida_core_get_category_image_html( WP_Term $term, $size = 'woocommerce_thumbnail', $default_attachment_id = 0 ) {
		$attachment_id = (int) get_term_meta( $term->term_id, 'thumbnail_id', true );

		// Fallback: vecchie installazioni Blocksy possono avere l'immagine solo dentro le opzioni del termine.
		if ( ! $attachment_id ) {
			$blocksy_options = get_term_meta( $term->term_id, 'blocksy_taxonomy_meta_options', true );

			if ( is_array( $blocksy_options ) ) {
				$maybe_image = isset( $blocksy_options['image'] ) ? $blocksy_options['image'] : ( $blocksy_options['icon_image'] ?? null );

				if ( is_array( $maybe_image ) && ! empty( $maybe_image['attachment_id'] ) ) {
					$attachment_id = (int) $maybe_image['attachment_id'];
				}
			}
		}

		// Nessuna immagine propria: usa quella di default configurata nel blocco, se presente.
		if ( ! $attachment_id && $default_attachment_id > 0 ) {
			$attachment_id = $default_attachment_id;
		}

		if ( $attachment_id ) {
			$image_html = wp_get_attachment_image(
				$attachment_id,
				$size,
				false,
				array(
					'loading' => 'lazy',
					'alt'     => $term->name,
				)
			);

			if ( $image_html ) {
				return $image_html;
			}
		}

		// Nessuna immagine trovata: placeholder nativo WooCommerce, se disponibile.
		if ( function_exists( 'wc_placeholder_img' ) ) {
			return wc_placeholder_img( $size );
		}

		return '';
	}
}

if ( ! function_exists( 'mavida_core_get_post_image_html' ) ) {
	/**
	 * Restituisce il markup <img> dell'immagine di un post (blocco "Griglia post per tipo
	 * di contenuto"): immagine in evidenza propria, oppure l'immagine di default configurata
	 * nel pannello del blocco se il post non ne ha una.
	 *
	 * @param WP_Post $post                  Il post di cui recuperare l'immagine.
	 * @param string  $size                  Nome della dimensione immagine da usare.
	 * @param int     $default_attachment_id ID allegato da usare se il post non ha
	 *                                        un'immagine in evidenza (0 = nessuna immagine di default).
	 * @return string Markup HTML gia' pronto per l'output (le funzioni WP usate escapano gia'),
	 *                oppure stringa vuota se nessuna immagine e' disponibile.
	 */
	function mavida_core_get_post_image_html( WP_Post $post, $size = 'large', $default_attachment_id = 0 ) {
		$attachment_id = get_post_thumbnail_id( $post );

		// Nessuna immagine in evidenza propria: usa quella di default configurata nel
		// blocco, se presente.
		if ( ! $attachment_id && $default_attachment_id > 0 ) {
			$attachment_id = $default_attachment_id;
		}

		if ( $attachment_id ) {
			$image_html = wp_get_attachment_image(
				$attachment_id,
				$size,
				false,
				array(
					'loading' => 'lazy',
					'alt'     => get_the_title( $post ),
				)
			);

			if ( $image_html ) {
				return $image_html;
			}
		}

		// Nessuna immagine trovata: a differenza del blocco categorie prodotto non c'e' un
		// placeholder generico da usare (wc_placeholder_img() e' specifico WooCommerce),
		// quindi la card resta semplicemente senza immagine.
		return '';
	}
}

if ( ! function_exists( 'mavida_core_sanitize_css_color' ) ) {
	/**
	 * Ripulisce un valore colore proveniente da un attributo del blocco (color picker),
	 * prima di usarlo dentro un attributo "style" inline. Non valida che sia un colore CSS
	 * corretto, ma rimuove i caratteri (";", ":", ecc.) che permetterebbero di iniettare
	 * ulteriori dichiarazioni CSS, mantenendo i formati leciti (#hex, rgb()/rgba(),
	 * hsl()/hsla(), nomi colore, var(--...)).
	 *
	 * @param mixed  $value   Valore grezzo dell'attributo.
	 * @param string $default Valore di fallback se il risultato e' vuoto.
	 * @return string Colore ripulito, oppure il fallback.
	 */
	function mavida_core_sanitize_css_color( $value, $default = '' ) {
		$value = preg_replace( '/[^a-zA-Z0-9#(),.%\- ]/', '', (string) $value );

		return '' !== $value ? $value : $default;
	}
}
