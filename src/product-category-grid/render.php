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
// dal pannello del blocco. Il colore viene ripulito da caratteri che permetterebbero di
// iniettare ulteriori dichiarazioni CSS (es. ";", ":"), mantenendo i formati leciti
// (#hex, rgb()/rgba(), hsl()/hsla(), nomi colore, var(--...)).
$card_background_color = isset( $attributes['cardBackgroundColor'] ) ? (string) $attributes['cardBackgroundColor'] : '#ffffff';
$card_background_color = preg_replace( '/[^a-zA-Z0-9#(),.%\- ]/', '', $card_background_color );
if ( '' === $card_background_color ) {
	$card_background_color = '#ffffff';
}

$card_border_radius = isset( $attributes['cardBorderRadius'] ) ? max( 0, (int) $attributes['cardBorderRadius'] ) : 12;

// Durata cache in minuti, configurabile dal pannello del blocco: 0 disabilita la cache.
$cache_minutes = isset( $attributes['cacheMinutes'] ) ? max( 0, (int) $attributes['cacheMinutes'] ) : 60;

$cache_key = null;

if ( $cache_minutes > 0 ) {
	// "mavida_core_cache_version" viene incrementata dal pulsante "Svuota cache" del blocco
	// (endpoint REST in includes/block-cache.php): cambiandola, ogni chiave calcolata in
	// precedenza diventa automaticamente irraggiungibile, senza dover enumerare o cancellare
	// i singoli transient esistenti (che nel frattempo scadono comunque da soli).
	$cache_version = (int) get_option( 'mavida_core_cache_version', 1 );
	$cache_key     = 'mavida_core_grid_' . $cache_version . '_' . md5( wp_json_encode( array( $columns, $excluded_categories, $card_background_color, $card_border_radius ) ) );

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
	<?php foreach ( $categories as $category ) : ?>
		<a class="mavida-cat-grid__item" href="<?php echo esc_url( get_term_link( $category ) ); ?>">
			<span class="mavida-cat-grid__name"><?php echo esc_html( $category->name ); ?></span>
			<?php echo mavida_core_get_category_image_html( $category ); ?>
		</a>
	<?php endforeach; ?>
</div>
<?php
$html = ob_get_clean();

if ( $cache_key ) {
	set_transient( $cache_key, $html, $cache_minutes * MINUTE_IN_SECONDS );
}

echo $html; // phpcs:ignore WordPress.Security.EscapeOutput -- markup gia' costruito con esc_html/esc_url/get_block_wrapper_attributes sopra.
