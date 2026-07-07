# Revisione del codice — Mavida Core 1.1.0

Revisione del codice presente nel plugin al momento della release 1.1.0 (tag `v1.1.0`).
Verifiche effettuate: lint sintattico PHP (`php -l`) su tutti i file, build di produzione
(`npm run build`) completata senza errori, lettura incrociata di tutti i file `includes/` e
del blocco. **Non** è stata eseguita una verifica end-to-end su un'installazione WordPress
reale (questo workspace contiene solo i sorgenti del tema/plugin, non un sito avviabile):
prima di andare in produzione va fatto un giro di test su staging, con WooCommerce e Blocksy
attivi (vedi la sezione "Verifica end-to-end" del piano di sviluppo).

Nessuna delle criticità elencate è bloccante per l'uso del plugin: sono segnalazioni da
valutare, in ordine di priorità decrescente.

## 1. `glob()` non è protetto da un eventuale `false` di ritorno

**File:** `mavida-core.php:64`

```php
foreach ( glob( MAVIDA_CORE_PATH . 'includes/*.php' ) as $mavida_core_include_file ) {
```

`glob()` restituisce `false` (non un array vuoto) se la lettura della cartella fallisce, ad
esempio per una restrizione `open_basedir` o un problema di permessi sul filesystem. In quel
caso, in PHP 8 un `foreach` su `false` genera un warning ("foreach() argument must be of type
array|object, bool given") invece di limitarsi a non caricare nulla. Scenario concreto: hosting
con `open_basedir` che esclude temporaneamente la cartella del plugin durante un deploy
automatico → warning in log ad ogni richiesta finché il problema non si risolve.

**Suggerimento:** `foreach ( (array) glob( ... ) as $file )`, oppure un controllo esplicito
`if ( false === $files ) { return; }` prima del ciclo.

## 2. Il blocco si registra anche a WooCommerce disattivato

**File:** `includes/block.php:16-18`

```php
function mavida_core_register_blocks() {
	register_block_type( MAVIDA_CORE_PATH . 'build/product-category-grid' );
}
```

La registrazione non verifica che WooCommerce sia attivo. Il rendering lato server è già
protetto (`mavida_core_get_product_categories()` ritorna `[]` se `product_cat` non esiste, e
`render.php` fa `return;` su elenco vuoto), quindi non ci sono errori fatali. Ma il blocco resta
comunque visibile e inseribile dall'editor anche senza WooCommerce, per poi risultare
silenziosamente vuoto sia in anteprima sia in frontend: un redattore potrebbe non capire perché
"non succede nulla".

**Suggerimento:** condizionare la registrazione a `class_exists( 'WooCommerce' )`, oppure
mostrare un placeholder esplicito nell'editor ("WooCommerce non è attivo") quando l'elenco
categorie è vuoto.

## 3. Nessun feedback in editor se la chiamata REST fallisce

**File:** `src/product-category-grid/edit.js` (hook `useEffect` di caricamento categorie)

La `apiFetch` verso `mavida-core/v1/product-categories` non gestisce il caso di errore in modo
visibile: se la richiesta fallisce (permessi, plugin di sicurezza che blocca la REST API,
problema di rete) `isLoadingCategories` passa comunque a `false` e la select "categorie da
escludere" resta semplicemente vuota, senza alcun messaggio. Inoltre non c'è una funzione di
cleanup sull'effect: se il blocco viene rimosso mentre la richiesta è ancora in corso, l'update
di stato successivo alla risposta puo' generare un warning React di "set state su componente
smontato" (innocuo, ma da segnalare per pulizia).

**Suggerimento:** in caso di errore mostrare un `Notice` (`@wordpress/components`) nel pannello
laterale; per il cleanup, usare un flag `let isMounted = true` nella closure dell'effect (o un
`AbortController` passato ad `apiFetch`) e non aggiornare lo stato se il componente è smontato.

## 4. Il breakpoint mobile forza sempre 2 colonne

**File:** `src/product-category-grid/style.scss` (media query `max-width: 782px`)

Sotto i 782px il CSS impone `grid-template-columns: repeat(2, 1fr)` a prescindere dal valore
scelto nell'editor. Se un redattore imposta volutamente `columns = 1` (ad esempio per card più
grandi in evidenza), su mobile vedrebbe comunque 2 colonne, contraddicendo la scelta fatta.

**Suggerimento:** calcolare il minimo tra il valore scelto e 2 (es. passando anche un
`--mv-columns-mobile` calcolato in `render.php`), oppure documentare il comportamento come
intenzionale se il caso d'uso con 1 colonna non è previsto.

## 5. `Domain Path` punta a una cartella `languages/` inesistente

**File:** `mavida-core.php:14`

L'header dichiara `Domain Path: /languages`, ma la cartella non esiste ancora e non è stato
generato alcun file `.pot`/`.po`/`.mo`. Non è un errore bloccante (WordPress non trova nulla da
caricare e prosegue), ma è un'incompletezza da sistemare prima di distribuire traduzioni, o da
rimuovere dall'header finché non si predispone la cartella.

## 6. Valore di default duplicato in tre punti

**File:** `mavida-core.php:51`, `includes/settings-page.php` (default di `register_setting` e
fallback in `mavida_core_sanitize_options`)

La stringa `'mavida-product-cats'` (classe CSS di default) è scritta tre volte in punti diversi
del codice. Rischio basso oggi, ma se in futuro si decidesse di cambiare il default andrebbe
ricordato di aggiornarlo in tutti e tre i punti.

**Suggerimento:** centralizzare il valore in una costante, ad esempio
`MAVIDA_CORE_DEFAULT_MENU_CSS_CLASS`, definita accanto alle altre costanti in `mavida-core.php`.

## 7. Nota (non un difetto): ordinamento delle sottovoci menu iniettate

**File:** `includes/menu-injection.php`

Le sottovoci sintetiche vengono aggiunte in coda all'array `$items`. Il valore di `menu_order`
calcolato viene passato a `wp_setup_nav_menu_item()`, ma l'ordine con cui il walker del menu
renderizza gli elementi allo stesso livello dipende in pratica dalla posizione nell'array
restituito dal filtro, non da una nuova ricerca per `menu_order`. Nell'uso previsto (una voce di
menu "vuota", usata solo come contenitore per le categorie) questo non ha alcun effetto visibile.
Se in futuro quella stessa voce di menu avesse anche altre sottovoci reali aggiunte manualmente
da Bacheca, quelle comparirebbero prima delle categorie iniettate, indipendentemente dall'ordine
con cui sono state create. Da tenere a mente se il caso d'uso cambierà.

---

## Riepilogo priorità

| # | Criticità | Severità | Tipo |
|---|-----------|----------|------|
| 1 | `glob()` non protetto da `false` | Bassa | Robustezza |
| 2 | Blocco registrato anche senza WooCommerce | Bassa | UX editor |
| 3 | Nessun feedback su errore REST in editor | Bassa | UX editor |
| 4 | Breakpoint mobile forza 2 colonne | Bassa | UX frontend |
| 5 | `Domain Path` senza cartella `languages/` | Molto bassa | Housekeeping |
| 6 | Default duplicato in 3 punti | Molto bassa | Manutenibilità |
| 7 | Ordine sottovoci menu dipende dalla posizione in array | Nota informativa | — |

Nessuna criticità di sicurezza rilevata: output sempre escapato (`esc_html`, `esc_attr`,
`esc_url`, o funzioni core che già escapano), input sanitizzato (`sanitize_html_class`,
cast a `int`), capability verificate (`manage_options` per le opzioni, `edit_posts` per
l'endpoint REST), nonce gestiti dalla Settings API.
