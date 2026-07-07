<?php
/**
 * Render lato server della griglia categorie prodotto.
 * Usato sia in frontend sia dall'anteprima ServerSideRender in editor.
 *
 * Variabili disponibili in questo scope, fornite da WordPress:
 *     $attributes (array): Attributi del blocco.
 *     $content    (string): Contenuto di default del blocco (non usato, blocco dinamico puro).
 *     $block      (WP_Block): Istanza del blocco.
 *
 * @package Mavida_Core
 */

defined( 'ABSPATH' ) || exit;

// Numero di colonne: stesso range 1-8 del RangeControl nell'editor.
$columns = isset( $attributes['columns'] ) ? (int) $attributes['columns'] : 4;
$columns = max( 1, min( 8, $columns ) );

// Categorie escluse scelte dall'utente nella select dell'editor.
$excluded_categories = isset( $attributes['excludedCategories'] )
	? array_map( 'intval', (array) $attributes['excludedCategories'] )
	: array();

$categories = mavida_core_get_product_categories( array( 'exclude' => $excluded_categories ) );

// Nessuna categoria da mostrare (o WooCommerce non attivo): nessun markup in pagina.
if ( empty( $categories ) ) {
	return;
}

// Numero di colonne passato come custom property CSS, consumata da style.scss.
$wrapper_attributes = get_block_wrapper_attributes(
	array(
		'class' => 'mavida-cat-grid',
		'style' => '--mv-columns:' . $columns . ';',
	)
);
?>
<div <?php echo $wrapper_attributes; ?>>
	<?php foreach ( $categories as $category ) : ?>
		<a class="mavida-cat-grid__item" href="<?php echo esc_url( get_term_link( $category ) ); ?>">
			<span class="mavida-cat-grid__name"><?php echo esc_html( $category->name ); ?></span>
			<?php echo mavida_core_get_category_image_html( $category ); ?>
		</a>
	<?php endforeach; ?>
</div>
