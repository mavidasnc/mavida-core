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

// Aspetto delle card: colore di sfondo e arrotondamento angoli, entrambi configurabili
// dal pannello del blocco.
$card_background_color = mavida_core_sanitize_css_color( $attributes['cardBackgroundColor'] ?? '', '#ffffff' );
$card_border_radius    = isset( $attributes['cardBorderRadius'] ) ? max( 0, (int) $attributes['cardBorderRadius'] ) : 12;

// Tag HTML del nome categoria, colore e dimensione testo: tutti configurabili dal pannello.
// Il tag viene validato contro un elenco chiuso: e' l'unico modo sicuro di stamparlo
// direttamente nel markup (esc_attr non basterebbe a impedire un tag arbitrario).
$allowed_name_tags = array( 'h1', 'h2', 'h3', 'h4', 'div', 'span' );
$name_tag          = isset( $attributes['nameTagName'] ) && in_array( $attributes['nameTagName'], $allowed_name_tags, true )
	? $attributes['nameTagName']
	: 'span';
$name_color     = mavida_core_sanitize_css_color( $attributes['nameColor'] ?? '' );
$name_font_size = isset( $attributes['nameFontSize'] ) ? max( 0, (int) $attributes['nameFontSize'] ) : 16;
$name_style     = sprintf( 'font-size:%dpx;', $name_font_size ) . ( '' !== $name_color ? sprintf( 'color:%s;', $name_color ) : '' );

// Call to action opzionale sotto la griglia: testo libero, url, dimensione, colori e
// stile "pulsante". Se il testo e' vuoto, la CTA non viene renderizzata affatto.
$cta_text = isset( $attributes['ctaText'] ) ? trim( wp_strip_all_tags( (string) $attributes['ctaText'] ) ) : '';
$cta_url  = isset( $attributes['ctaUrl'] ) ? esc_url_raw( (string) $attributes['ctaUrl'] ) : '';

if ( '' !== $cta_text ) {
	$cta_is_button        = ! empty( $attributes['ctaIsButton'] );
	$cta_font_size        = isset( $attributes['ctaFontSize'] ) ? max( 0, (int) $attributes['ctaFontSize'] ) : 16;
	$cta_text_color       = mavida_core_sanitize_css_color( $attributes['ctaTextColor'] ?? '' );
	$cta_background_color = mavida_core_sanitize_css_color( $attributes['ctaBackgroundColor'] ?? '' );

	$cta_style = sprintf( 'font-size:%dpx;', $cta_font_size );
	if ( '' !== $cta_text_color ) {
		$cta_style .= sprintf( 'color:%s;', $cta_text_color );
	}
	if ( $cta_is_button && '' !== $cta_background_color ) {
		$cta_style .= sprintf( 'background-color:%s;', $cta_background_color );
	}

	$cta_classes = 'mavida-cat-grid__cta' . ( $cta_is_button ? ' mavida-cat-grid__cta--button' : '' );
} else {
	$cta_is_button        = false;
	$cta_font_size        = 0;
	$cta_text_color       = '';
	$cta_background_color = '';
	$cta_style             = '';
	$cta_classes           = '';
}

// Durata cache in minuti, configurabile dal pannello del blocco: 0 disabilita la cache.
$cache_minutes = isset( $attributes['cacheMinutes'] ) ? max( 0, (int) $attributes['cacheMinutes'] ) : 60;

$cache_key = null;

if ( $cache_minutes > 0 ) {
	// "mavida_core_cache_version" viene incrementata dal pulsante "Svuota cache" del blocco
	// (endpoint REST in includes/block-cache.php): cambiandola, ogni chiave calcolata in
	// precedenza diventa automaticamente irraggiungibile, senza dover enumerare o cancellare
	// i singoli transient esistenti (che nel frattempo scadono comunque da soli).
	$cache_version   = (int) get_option( 'mavida_core_cache_version', 1 );
	$cache_signature = array(
		$columns,
		$excluded_categories,
		$card_background_color,
		$card_border_radius,
		$name_tag,
		$name_color,
		$name_font_size,
		$cta_text,
		$cta_url,
		$cta_is_button,
		$cta_font_size,
		$cta_text_color,
		$cta_background_color,
	);
	$cache_key = 'mavida_core_grid_' . $cache_version . '_' . md5( wp_json_encode( $cache_signature ) );

	$cached_html = get_transient( $cache_key );

	if ( false !== $cached_html ) {
		echo $cached_html; // phpcs:ignore WordPress.Security.EscapeOutput -- gia' generato ed escapato qui sotto prima di essere messo in cache.
		return;
	}
}

$categories = mavida_core_get_product_categories( array( 'exclude' => $excluded_categories ) );

// Nessuna categoria da mostrare (o WooCommerce non attivo): nessun markup in pagina.
// Anche l'esito vuoto viene messo in cache, per non ripetere la query ad ogni richiesta.
if ( empty( $categories ) ) {
	if ( $cache_key ) {
		set_transient( $cache_key, '', $cache_minutes * MINUTE_IN_SECONDS );
	}
	return;
}

// Colonne, colore di sfondo e arrotondamento delle card passati come custom property CSS,
// consumate da style.scss.
$wrapper_attributes = get_block_wrapper_attributes(
	array(
		'class' => 'mavida-cat-grid',
		'style' => sprintf(
			'--mv-columns:%d;--mv-card-bg:%s;--mv-card-radius:%dpx;',
			$columns,
			$card_background_color,
			$card_border_radius
		),
	)
);

ob_start();
?>
<div <?php echo $wrapper_attributes; ?>>
	<div class="mavida-cat-grid__items">
		<?php foreach ( $categories as $category ) : ?>
			<a class="mavida-cat-grid__item" href="<?php echo esc_url( get_term_link( $category ) ); ?>">
				<?php
				printf(
					'<%1$s class="mavida-cat-grid__name" style="%2$s">%3$s</%1$s>',
					esc_attr( $name_tag ),
					esc_attr( $name_style ),
					esc_html( $category->name )
				);
				?>
				<?php echo mavida_core_get_category_image_html( $category ); ?>
			</a>
		<?php endforeach; ?>
	</div>
	<?php if ( '' !== $cta_text ) : ?>
		<?php if ( '' !== $cta_url ) : ?>
			<a class="<?php echo esc_attr( $cta_classes ); ?>" href="<?php echo esc_url( $cta_url ); ?>" style="<?php echo esc_attr( $cta_style ); ?>">
				<?php echo esc_html( $cta_text ); ?>
			</a>
		<?php else : ?>
			<span class="<?php echo esc_attr( $cta_classes ); ?>" style="<?php echo esc_attr( $cta_style ); ?>">
				<?php echo esc_html( $cta_text ); ?>
			</span>
		<?php endif; ?>
	<?php endif; ?>
</div>
<?php
$html = ob_get_clean();

if ( $cache_key ) {
	set_transient( $cache_key, $html, $cache_minutes * MINUTE_IN_SECONDS );
}

echo $html; // phpcs:ignore WordPress.Security.EscapeOutput -- markup gia' costruito con esc_html/esc_url/get_block_wrapper_attributes sopra.
