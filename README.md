# Mavida Core

Plugin WordPress con funzionalita' core per siti WooCommerce basati su [Blocksy](https://creativethemes.com/blocksy/).

## Funzionalita'

- **Blocco Gutenberg "Griglia categorie prodotto"** (`mavida-core/product-category-grid`): mostra le
  categorie prodotto WooCommerce in griglia (solo se WooCommerce e' attivo), con il nome in alto e
  l'immagine di categoria (gestita da Blocksy tramite il term meta `thumbnail_id`) sotto. Numero di
  colonne (desktop e mobile, quest'ultimo sotto i 782px), categorie da includere o da escludere (con
  lo stesso componente nativo di WordPress usato per i tag; se "da includere" e' compilato ha
  priorita' assoluta e mostra solo quelle, nell'ordine in cui sono state aggiunte), durata della
  cache, colore di sfondo, padding e arrotondamento degli angoli delle card configurabili dal
  pannello del blocco; le card hanno una piccola animazione al passaggio del mouse. Il tag HTML del
  nome categoria (H1-H4, div, span), colore e dimensione sono configurabili. Ogni card puo' mostrare
  una call to action opzionale (testo libero, dimensione, colori, stile pulsante); non ha un link
  proprio, eredita il click dell'intera card. Elenco e singole card sono personalizzabili via
  codice, vedi [Hook per sviluppatori](#hook-per-sviluppatori).
- **Colonne prodotto in Bacheca**: l'elenco prodotti (Prodotti) mostra due colonne aggiuntive,
  "Codice Marelli" e "Codice OE", lette dai relativi meta del prodotto.
- **Cache del blocco**: il markup del blocco viene salvato in transient per il numero di minuti
  impostato nel pannello (0 per disattivarla), con un pulsante "Svuota cache" che la invalida — sia
  nel pannello del blocco sia nella pagina opzioni (Bacheca > Mavida Core > Generale), con lo stesso
  effetto sito-wide.
- **Menu dinamico**: le categorie prodotto vengono aggiunte automaticamente come sottovoci di una voce
  del menu di navigazione, identificata da una classe CSS configurabile in Bacheca > Mavida Core.
- **Auto-aggiornamento**: il plugin controlla e propone gli aggiornamenti direttamente dal repository
  GitHub pubblico [`mavidasnc/mavida-core`](https://github.com/mavidasnc/mavida-core), tramite un
  updater scritto a codice (nessuna libreria esterna), gestibile anche manualmente dalla tab
  "Aggiornamenti" della pagina opzioni.

## Struttura

```
mavida-core/
├─ mavida-core.php     # File principale: header, costanti, loader
├─ includes/           # Un file per funzionalita', caricati automaticamente (updater incluso)
├─ assets/admin/       # Script della pagina opzioni (non passa dal build @wordpress/scripts)
├─ src/                # Sorgenti del blocco Gutenberg (@wordpress/scripts)
└─ build/              # Output compilato del blocco (committato)
```

## Sviluppo

Richiede Node.js e npm.

```bash
npm install
npm run start   # sviluppo con watch
npm run build   # build di produzione (aggiorna build/)
```

## Requisiti

- WordPress 6.5+
- PHP 8.1+
- WooCommerce (per le funzionalita' legate alle categorie prodotto)
- Tema Blocksy (per la gestione dell'immagine di categoria)

## Hook per sviluppatori

Il blocco "Griglia categorie prodotto" espone quattro filtri, applicati in questo ordine durante
il render: elenco categorie → (per ciascuna) contesto della card → HTML della card → HTML dopo
la griglia.

### 1. `mavida_core_product_category_grid_categories` — elenco categorie

Applicato subito dopo l'estrazione delle categorie da database, prima che vengano renderizzate.
Permette di aggiungere, rimuovere o riordinare le categorie mostrate via codice, indipendentemente
dalle select "categorie da includere/escludere" nell'editor.

```php
/**
 * @param WP_Term[] $categories  Elenco delle categorie estratte.
 * @param array     $attributes  Attributi del blocco (columns, cardBackgroundColor, ecc.).
 * @param WP_Block  $block       Istanza del blocco.
 */
add_filter( 'mavida_core_product_category_grid_categories', function ( $categories, $attributes, $block ) {
	// Esempio: rimuove la categoria "ricambi-usati" dalla griglia, ovunque sia usata sul sito.
	return array_filter(
		$categories,
		function ( $category ) {
			return 'ricambi-usati' !== $category->slug;
		}
	);
}, 10, 3 );
```

Per **aggiungere** una categoria, restituire un array che includa anche oggetti `WP_Term` validi
(es. ottenuti con `get_term()`), non array o `stdClass` generici: le funzioni interne del blocco
(link e immagine della card) si aspettano un `WP_Term` reale.

### 2. `mavida_core_product_category_grid_item_context` — contesto di una card

Applicato per ogni categoria, **prima** che il markup della card venga assemblato. Riceve un
array con i valori gia' pronti per l'output (url, nome, immagine, CTA): permette di sovrascrivere
uno di questi per una categoria specifica senza dover rifare query.

```php
/**
 * @param array   $context    'url', 'name', 'image_html', 'cta_html' (gia' pronti per l'output,
 *                             cioe' gia' escapati/sicuri: se li sovrascrivi, il tuo valore deve
 *                             esserlo altrettanto).
 * @param WP_Term $category   Il termine categoria corrente.
 * @param array   $attributes Attributi del blocco.
 */
add_filter( 'mavida_core_product_category_grid_item_context', function ( $context, $category, $attributes ) {
	// Esempio: imposta un'immagine per la categoria "accessori", che non ne ha una propria
	// (thumbnail_id vuoto): mavida_core_get_category_image_html() userebbe altrimenti il
	// placeholder WooCommerce.
	if ( 'accessori' === $category->slug ) {
		$context['image_html'] = wp_get_attachment_image(
			123, // ID dell'allegato da usare.
			'woocommerce_thumbnail',
			false,
			array( 'alt' => $category->name )
		);
	}

	return $context;
}, 10, 3 );
```

### 3. `mavida_core_product_category_grid_item_html` — HTML di una card

Applicato per ogni categoria, dopo l'assemblaggio del markup: riceve la card completa (l'intero
`<a class="mavida-cat-grid__item">...</a>`) e puo' sostituirla, avvolgerla o modificarla.

```php
/**
 * @param string  $card_html  Markup HTML della card.
 * @param WP_Term $category   Il termine categoria corrente.
 * @param array   $context    Il contesto (eventualmente gia' filtrato dal filtro precedente).
 * @param array   $attributes Attributi del blocco.
 */
add_filter( 'mavida_core_product_category_grid_item_html', function ( $card_html, $category, $context, $attributes ) {
	// Esempio: aggiunge una classe "is-featured" alla card di una categoria specifica,
	// per evidenziarla con CSS personalizzato nel tema.
	if ( 'ricambi-motore' === $category->slug ) {
		$card_html = str_replace( 'mavida-cat-grid__item"', 'mavida-cat-grid__item is-featured"', $card_html );
	}

	return $card_html;
}, 10, 4 );
```

### 4. `mavida_core_product_category_grid_after_items` — HTML dopo la griglia

Applicato una sola volta, dopo l'ultima card, ma ancora dentro il contenitore del blocco. Utile
per aggiungere una call to action globale sotto la griglia via codice.

```php
/**
 * @param string    $html       Vuoto di default.
 * @param WP_Term[] $categories Le categorie mostrate.
 * @param array     $attributes Attributi del blocco.
 */
add_filter( 'mavida_core_product_category_grid_after_items', function ( $html, $categories, $attributes ) {
	return $html . sprintf(
		'<a class="mavida-cat-grid__cta mavida-cat-grid__cta--button" href="%s">%s</a>',
		esc_url( get_permalink( wc_get_page_id( 'shop' ) ) ),
		esc_html__( 'Vedi tutti i prodotti', 'mio-tema' )
	);
}, 10, 3 );
```

### Note comuni ai 4 filtri

- I valori restituiti dai filtri 2, 3 e 4 vengono stampati **senza ulteriore escape automatico**:
  chi li sovrascrive e' responsabile della sicurezza dell'output (usare `esc_html()`, `esc_url()`,
  `esc_attr()` sui propri dati dinamici).
- **Interazione con la cache del blocco:** tutti i filtri vengono applicati solo in caso di
  cache-miss (se la cache e' attiva e gia' popolata, viene servito direttamente l'HTML in cache e
  nessuno dei filtri viene richiamato). Se la logica di un filtro dipende da condizioni che
  cambiano nel tempo (utente loggato, data, ecc.) e non solo dagli attributi del blocco, il
  risultato filtrato verra' comunque messo in cache fino alla scadenza (o allo svuotamento
  manuale) senza che la cache se ne accorga: in quel caso conviene impostare "Durata cache" a 0
  nel pannello del blocco, oppure invalidare la cache manualmente (pulsante "Svuota cache", nel
  pannello del blocco o nella pagina opzioni) quando la condizione cambia.

## Changelog

Vedi [CHANGELOG.md](CHANGELOG.md).

---

Sviluppato da MAVIDA.
