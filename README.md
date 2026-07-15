# Mavida Core

Plugin WordPress con funzionalita' core per siti WooCommerce basati su [Blocksy](https://creativethemes.com/blocksy/).

## Funzionalita'

- **Blocco Gutenberg "Griglia categorie prodotto"** (`mavida-core/product-category-grid`): mostra le
  categorie prodotto WooCommerce in griglia (solo se WooCommerce e' attivo), con il nome in alto e
  l'immagine di categoria (gestita da Blocksy tramite il term meta `thumbnail_id`) sotto. Numero di
  colonne (desktop e mobile, quest'ultimo sotto i 782px), categorie da escludere (con lo stesso
  componente nativo di WordPress usato per i tag), durata della cache, colore di sfondo, padding e
  arrotondamento degli angoli delle card configurabili dal pannello del blocco; le card hanno una
  piccola animazione al passaggio del mouse. Il tag HTML del nome categoria (H1-H4, div, span),
  colore e dimensione sono configurabili. Ogni card puo' mostrare una call to action opzionale
  (testo libero, dimensione, colori, stile pulsante); non ha un link proprio, eredita il click
  dell'intera card. L'elenco delle categorie mostrate e' filtrabile via codice, vedi
  [Hook per sviluppatori](#hook-per-sviluppatori).
- **Colonne prodotto in Bacheca**: l'elenco prodotti (Prodotti) mostra due colonne aggiuntive,
  "Codice Marelli" e "Codice OE", lette dai relativi meta del prodotto.
- **Cache del blocco**: il markup del blocco viene salvato in transient per il numero di minuti
  impostato nel pannello (0 per disattivarla), con un pulsante "Svuota cache" che la invalida.
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

Il blocco "Griglia categorie prodotto" espone il filtro `mavida_core_product_category_grid_categories`,
applicato subito dopo l'estrazione delle categorie da database (`get_terms()`), prima che vengano
renderizzate. Permette di aggiungere, rimuovere o riordinare le categorie mostrate via codice,
indipendentemente dalla select "categorie da escludere" nell'editor.

```php
/**
 * @param WP_Term[] $categories  Elenco delle categorie estratte (di primo livello, gia' al netto
 *                                delle esclusioni scelte in editor).
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

**Interazione con la cache del blocco:** il filtro viene applicato dopo un'eventuale cache-hit (se
la cache e' attiva e gia' popolata, il filtro non viene richiamato: viene servito direttamente
l'HTML gia' in cache). Se la logica del filtro dipende da condizioni che cambiano nel tempo (utente
loggato, data, ecc.) e non solo dagli attributi del blocco, il risultato filtrato verra' comunque
messo in cache fino alla scadenza (o allo svuotamento manuale) senza che la cache se ne accorga: in
quel caso conviene impostare "Durata cache" a 0 nel pannello del blocco, oppure invalidare la cache
manualmente (pulsante "Svuota cache") quando la condizione cambia.

## Changelog

Vedi [CHANGELOG.md](CHANGELOG.md).

---

Sviluppato da MAVIDA.
