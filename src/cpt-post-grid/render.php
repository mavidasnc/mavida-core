<?php
/**
 * Render lato server della griglia post per tipo di contenuto.
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

// Tipo di contenuto scelto dall'editor: senza un CPT valido e pubblico non c'e' nulla da
// mostrare (evita anche di esporre CPT privati tramite un attributo manomesso).
$post_type = isset( $attributes['postType'] ) ? sanitize_key( $attributes['postType'] ) : '';

if ( '' === $post_type || ! post_type_exists( $post_type ) || ! is_post_type_viewable( $post_type ) ) {
	return;
}

// Numero di colonne: stesso range 1-8 del RangeControl nell'editor.
$columns = isset( $attributes['columns'] ) ? (int) $attributes['columns'] : 4;
$columns = max( 1, min( 8, $columns ) );

// Numero di colonne sotto i 782px (breakpoint tablet standard di WordPress), configurabile
// separatamente dal numero di colonne principale.
$mobile_columns = isset( $attributes['mobileColumns'] ) ? (int) $attributes['mobileColumns'] : 2;
$mobile_columns = max( 1, min( 8, $mobile_columns ) );

// Post esclusi, scelti dall'utente nella select dell'editor.
$excluded_posts = isset( $attributes['excludedPosts'] )
	? array_map( 'intval', (array) $attributes['excludedPosts'] )
	: array();

// Post da includere: se non vuoto, ha priorita' assoluta su "escludi" (vedi piu' sotto).
$included_posts = isset( $attributes['includedPosts'] )
	? array_map( 'intval', (array) $attributes['includedPosts'] )
	: array();

// Aspetto delle card: colore di sfondo, arrotondamento angoli e padding, tutti configurabili
// dal pannello del blocco.
$card_background_color = mavida_core_sanitize_css_color( $attributes['cardBackgroundColor'] ?? '', '#ffffff' );
$card_border_color     = mavida_core_sanitize_css_color( $attributes['cardBorderColor'] ?? '', 'rgba(0,0,0,0.08)' );
$card_border_radius    = isset( $attributes['cardBorderRadius'] ) ? max( 0, (int) $attributes['cardBorderRadius'] ) : 12;
$card_padding          = isset( $attributes['cardPadding'] ) ? max( 0, (int) $attributes['cardPadding'] ) : 16;

// Immagine di default per i post che non hanno un'immagine in evidenza propria (scelta dalla
// media library nel pannello del blocco).
$default_image_id = isset( $attributes['defaultImageId'] ) ? (int) $attributes['defaultImageId'] : 0;

// CSS personalizzato per-istanza: cssInstanceId identifica in modo univoco questo blocco (dà
// un ancoraggio stabile al CSS scritto dall'utente), customCss e' il testo scritto nella modale
// "Personalizza CSS" dell'editor. Nessuno dei due viene stampato se customCss e' vuoto.
$css_instance_id = isset( $attributes['cssInstanceId'] ) ? sanitize_html_class( (string) $attributes['cssInstanceId'] ) : '';
$custom_css      = isset( $attributes['customCss'] ) ? (string) $attributes['customCss'] : '';

// Tag HTML del nome post, colore e dimensione testo: tutti configurabili dal pannello.
// Il tag viene validato contro un elenco chiuso: e' l'unico modo sicuro di stamparlo
// direttamente nel markup (esc_attr non basterebbe a impedire un tag arbitrario).
$allowed_name_tags = array( 'h1', 'h2', 'h3', 'h4', 'div', 'span' );
$name_tag          = isset( $attributes['nameTagName'] ) && in_array( $attributes['nameTagName'], $allowed_name_tags, true )
	? $attributes['nameTagName']
	: 'span';
$name_color     = mavida_core_sanitize_css_color( $attributes['nameColor'] ?? '' );
$name_font_size = isset( $attributes['nameFontSize'] ) ? max( 0, (int) $attributes['nameFontSize'] ) : 16;
$name_style     = sprintf( 'font-size:%dpx;', $name_font_size ) . ( '' !== $name_color ? sprintf( 'color:%s;', $name_color ) : '' );

// Call to action opzionale dentro ogni card: testo libero, dimensione, colori e stile
// "pulsante". Non ha un link proprio: essendo dentro la card, eredita il click dell'intera
// card (il link del post) invece di puntare a un URL esterno indicabile a parte.
// Se il testo e' vuoto, la CTA non viene renderizzata affatto.
$cta_text = isset( $attributes['ctaText'] ) ? trim( wp_strip_all_tags( (string) $attributes['ctaText'] ) ) : '';

if ( '' !== $cta_text ) {
	$cta_is_button         = ! empty( $attributes['ctaIsButton'] );
	$cta_font_size         = isset( $attributes['ctaFontSize'] ) ? max( 0, (int) $attributes['ctaFontSize'] ) : 16;
	$cta_text_color        = mavida_core_sanitize_css_color( $attributes['ctaTextColor'] ?? '' );
	$cta_background_color = mavida_core_sanitize_css_color( $attributes['ctaBackgroundColor'] ?? '' );

	$cta_style = sprintf( 'font-size:%dpx;', $cta_font_size );
	if ( '' !== $cta_text_color ) {
		$cta_style .= sprintf( 'color:%s;', $cta_text_color );
	}
	if ( $cta_is_button && '' !== $cta_background_color ) {
		$cta_style .= sprintf( 'background-color:%s;', $cta_background_color );
	}

	$cta_classes = 'mavida-cpt-grid__cta' . ( $cta_is_button ? ' mavida-cpt-grid__cta--button' : '' );
} else {
	$cta_is_button         = false;
	$cta_font_size         = 0;
	$cta_text_color        = '';
	$cta_background_color  = '';
	$cta_style             = '';
	$cta_classes           = '';
}

// Durata cache in minuti, configurabile dal pannello del blocco: 0 disabilita la cache.
$cache_minutes = isset( $attributes['cacheMinutes'] ) ? max( 0, (int) $attributes['cacheMinutes'] ) : 60;

$cache_key = null;

if ( $cache_minutes > 0 ) {
	// "mavida_core_cache_version" e' la stessa option incrementata dal pulsante "Svuota cache"
	// (endpoint REST in includes/block-cache.php) condiviso con il blocco "Griglia categorie
	// prodotto": cambiandola, ogni chiave calcolata in precedenza da uno dei due blocchi
	// diventa automaticamente irraggiungibile, senza dover enumerare o cancellare i singoli
	// transient esistenti (che nel frattempo scadono comunque da soli).
	$cache_version   = (int) get_option( 'mavida_core_cache_version', 1 );
	$cache_signature = array(
		$post_type,
		$columns,
		$mobile_columns,
		$excluded_posts,
		$included_posts,
		$card_background_color,
		$card_border_radius,
		$card_padding,
		$name_tag,
		$name_color,
		$name_font_size,
		$cta_text,
		$cta_is_button,
		$cta_font_size,
		$cta_text_color,
		$cta_background_color,
		$default_image_id,
		$css_instance_id,
		$custom_css,
	);
	$cache_key = 'mavida_core_cpt_grid_' . $cache_version . '_' . md5( wp_json_encode( $cache_signature ) );

	$cached_html = get_transient( $cache_key );

	if ( false !== $cached_html ) {
		echo $cached_html; // phpcs:ignore WordPress.Security.EscapeOutput -- gia' generato ed escapato qui sotto prima di essere messo in cache.
		return;
	}
}

if ( ! empty( $included_posts ) ) {
	// "post da includere" ha priorita' assoluta su "escludi" (ignorato in questo caso).
	// get_posts(['post__in' => $ids]) non garantirebbe l'ordine di $ids: si recupera un
	// post alla volta, nell'ordine esatto scelto in editor (FormTokenField preserva
	// l'ordine di inserimento dei token).
	$posts = array();
	foreach ( $included_posts as $included_post_id ) {
		$included_post = get_post( $included_post_id );
		if (
			$included_post instanceof WP_Post
			&& $post_type === $included_post->post_type
			&& 'publish' === $included_post->post_status
		) {
			$posts[] = $included_post;
		}
	}
	unset( $included_post_id, $included_post );
} else {
	$posts = get_posts(
		array(
			'post_type'      => $post_type,
			'post_status'    => 'publish',
			'numberposts'    => -1,
			'post__not_in'   => $excluded_posts,
			'orderby'        => 'title',
			'order'          => 'ASC',
		)
	);
}

/**
 * Filtra l'elenco dei post mostrati dalla griglia, subito dopo l'estrazione da database.
 * Permette di aggiungere, rimuovere o riordinare i post via codice, indipendentemente dalle
 * select "post da includere/escludere" nell'editor. Vedi il README del plugin per un
 * esempio d'uso analogo (blocco "Griglia categorie prodotto").
 *
 * @param WP_Post[] $posts      Elenco dei post estratti.
 * @param array     $attributes Attributi del blocco.
 * @param WP_Block  $block      Istanza del blocco.
 */
$posts = apply_filters( 'mavida_core_cpt_post_grid_posts', $posts, $attributes, $block );

// Nessun post da mostrare: nessun markup in pagina. Anche l'esito vuoto viene messo in
// cache, per non ripetere la query ad ogni richiesta.
if ( empty( $posts ) ) {
	if ( $cache_key ) {
		set_transient( $cache_key, '', $cache_minutes * MINUTE_IN_SECONDS );
	}
	return;
}

// Colonne (desktop e mobile), colore di sfondo, arrotondamento e padding delle card,
// passati come custom property CSS, consumate da style.scss.
$wrapper_extra_attributes = array(
	'class' => 'mavida-cpt-grid',
	'style' => sprintf(
		'--mv-columns:%d;--mv-columns-mobile:%d;--mv-card-bg:%s;--mv-card-border:%s;--mv-card-radius:%dpx;--mv-card-padding:%dpx;',
		$columns,
		$mobile_columns,
		$card_background_color,
		$card_border_color,
		$card_border_radius,
		$card_padding
	),
);

// L'id univoco serve solo da ancoraggio al CSS personalizzato: lo si aggiunge solo se serve
// davvero, per non sporcare il markup con un id vuoto quando non c'e' CSS personalizzato.
if ( '' !== $css_instance_id && '' !== $custom_css ) {
	$wrapper_extra_attributes['id'] = 'mavida-cpt-grid-' . $css_instance_id;
}

$wrapper_attributes = get_block_wrapper_attributes( $wrapper_extra_attributes );

ob_start();
?>
<div <?php echo $wrapper_attributes; ?>>
	<div class="mavida-cpt-grid__items">
		<?php
		foreach ( $posts as $post ) {
			$permalink = get_permalink( $post );

			// Contesto pre-calcolato: valori gia' pronti per l'output (escapati dove serve),
			// cosi' un filtro puo' sovrascrivere una singola card (es. l'immagine di un post
			// senza immagine in evidenza) senza dover rifare query.
			$context = array(
				'url'        => $permalink ? esc_url( $permalink ) : '',
				'name'       => esc_html( get_the_title( $post ) ),
				'image_html' => mavida_core_get_post_image_html( $post, 'large', $default_image_id ),
				'cta_html'   => '' !== $cta_text
					? sprintf(
						'<span class="%1$s" style="%2$s">%3$s</span>',
						esc_attr( $cta_classes ),
						esc_attr( $cta_style ),
						esc_html( $cta_text )
					)
					: '',
			);

			/**
			 * Filtra il contesto di una singola card, PRIMA che il markup venga assemblato.
			 * Utile per sovrascrivere immagine/nome/CTA di un post specifico senza toccare il
			 * database. Vedi il README del plugin per un esempio d'uso analogo.
			 *
			 * @param array   $context    url, name, image_html, cta_html (gia' pronti per l'output).
			 * @param WP_Post $post       Il post corrente.
			 * @param array   $attributes Attributi del blocco.
			 */
			$context = apply_filters( 'mavida_core_cpt_post_grid_item_context', $context, $post, $attributes );

			$card_html = sprintf(
				'<a class="mavida-cpt-grid__item" href="%1$s"><%2$s class="mavida-cpt-grid__name" style="%3$s">%4$s</%2$s>%5$s%6$s</a>',
				$context['url'],
				esc_attr( $name_tag ),
				esc_attr( $name_style ),
				$context['name'],
				$context['image_html'],
				$context['cta_html']
			);

			/**
			 * Filtra l'HTML completo di una singola card, dopo l'assemblaggio. Permette di
			 * sostituire, avvolgere o accodare markup all'intera card. Vedi il README del
			 * plugin per un esempio d'uso analogo.
			 *
			 * @param string  $card_html  Markup HTML della card.
			 * @param WP_Post $post       Il post corrente.
			 * @param array   $context    Il contesto (eventualmente gia' filtrato).
			 * @param array   $attributes Attributi del blocco.
			 */
			echo apply_filters( 'mavida_core_cpt_post_grid_item_html', $card_html, $post, $context, $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput -- responsabilita' di chi usa il filtro, vedi README
		}
		unset( $post, $permalink, $context, $card_html );
		?>
	</div>
	<?php
	/**
	 * Filtra l'HTML stampato subito dopo la griglia di card, dentro il wrapper del blocco.
	 * Vedi il README del plugin per un esempio d'uso analogo (es. una CTA globale sotto la griglia).
	 *
	 * @param string    $html       Vuoto di default.
	 * @param WP_Post[] $posts      I post mostrati.
	 * @param array     $attributes Attributi del blocco.
	 */
	echo apply_filters( 'mavida_core_cpt_post_grid_after_items', '', $posts, $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput -- responsabilita' di chi usa il filtro, vedi README

	// CSS personalizzato per questa istanza (scritto nella modale "Personalizza CSS" in editor).
	// wp_strip_all_tags() e' l'unica sanitizzazione applicata: come il CSS aggiuntivo nativo di
	// WordPress (Aspetto > Personalizza), non e' un sanitizzatore CSS completo ma impedisce la
	// via di attacco piu' seria (chiusura anticipata di </style> e injection di tag arbitrari).
	// Stampato solo se l'id di ancoraggio e' presente: senza, il CSS non avrebbe nulla a cui
	// applicarsi in modo sicuro.
	if ( '' !== $custom_css && '' !== $css_instance_id ) {
		printf( '<style>%s</style>', wp_strip_all_tags( $custom_css ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- vedi commento sopra
	}
	?>
</div>
<?php
$html = ob_get_clean();

if ( $cache_key ) {
	set_transient( $cache_key, $html, $cache_minutes * MINUTE_IN_SECONDS );
}

echo $html; // phpcs:ignore WordPress.Security.EscapeOutput -- markup gia' costruito con esc_html/esc_url/get_block_wrapper_attributes sopra.
