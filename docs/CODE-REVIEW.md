# Revisione del codice — Mavida Core

Revisione del codice presente nel plugin, aggiornata alla release 1.2.0 (tag `v1.2.0`).
Verifiche effettuate: lint sintattico PHP (`php -l`) su tutti i file, build di produzione
(`npm run build`) completata senza errori, lettura incrociata di tutti i file `includes/` e
del blocco. **Non** è stata eseguita una verifica end-to-end su un'installazione WordPress
reale (questo workspace contiene solo i sorgenti del tema/plugin, non un sito avviabile):
prima di andare in produzione va fatto un giro di test su staging, con WooCommerce e Blocksy
attivi (vedi la sezione "Verifica end-to-end" del piano di sviluppo).

Nessuna delle criticità elencate è bloccante per l'uso del plugin: sono segnalazioni da
valutare, in ordine di priorità decrescente.

## Aggiornamento 1.2.0

La release 1.2.0 ha sostituito la libreria `plugin-update-checker` con un updater scritto a
codice (`includes/updater.php`), aggiunto la guardia WooCommerce sul blocco (**risolve il punto
2 qui sotto**), introdotto la cache del blocco e riorganizzato la pagina opzioni a tab. Nuove
osservazioni emerse da questo giro di modifiche:

- **Rate limit GitHub non autenticato.** `includes/updater.php` chiama `api.github.com` senza
  token: il limite per IP è 60 richieste/ora. Mitigato dalla cache di 6 ore
  (`mavida_core_github_release`), che riduce le chiamate reali a poche al giorno anche con
  controlli manuali frequenti dalla tab Aggiornamenti; da tenere presente se in futuro più
  plugin Mavida sullo stesso server interrogano GitHub in modo non autenticato.
- **Il pulsante "Svuota cache" è globale, non per singola istanza del blocco.** Incrementando
  `mavida_core_cache_version` si invalidano tutte le combinazioni di colonne/esclusioni cache
  ate in qualunque pagina del sito, non solo quelle generate dal blocco su cui si clicca il
  pulsante. Comportamento corretto e semplice da implementare, ma non ovvio dalla sola
  etichetta del pulsante: andrebbe chiarito nel testo di aiuto se genera confusione in redazione.
- **Azione AJAX classica (`admin-ajax.php`) invece di REST per il controllo aggiornamenti.**
  Scelta intenzionale per replicare 1:1 il pattern già collaudato in `woo-dynamic-pricelist-pro`;
  il resto del plugin (categorie, invalidazione cache) usa invece la REST API. Le due cose
  convivono senza conflitti, ma è un'asimmetria voluta da tenere a mente in manutenzione futura.
- **Stringa nonce duplicata.** `'mavida_core_admin_nonce'` è scritta sia in
  `includes/settings-page.php` (dove il nonce viene creato) sia in `includes/updater.php` (dove
  viene verificato). Stesso tipo di duplicazione già segnalata al punto 6 per la classe CSS di
  default: rischio basso, ma da centralizzare in una costante se si aggiungeranno altre azioni
  AJAX in futuro.

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

## 2. ~~Il blocco si registra anche a WooCommerce disattivato~~ — Risolto in 1.2.0

**File:** `includes/block.php`

`mavida_core_register_blocks()` ora esce subito (`return;`) se `class_exists( 'WooCommerce' )` è
falso, prima di chiamare `register_block_type()`. Il blocco non compare più nell'inseritore
quando WooCommerce non è attivo.

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
| 2 | ~~Blocco registrato anche senza WooCommerce~~ | Risolto in 1.2.0 | — |
| 3 | Nessun feedback su errore REST in editor | Bassa | UX editor |
| 4 | Breakpoint mobile forza 2 colonne | Bassa | UX frontend |
| 5 | `Domain Path` senza cartella `languages/` | Molto bassa | Housekeeping |
| 6 | Default duplicato in 3 punti | Molto bassa | Manutenibilità |
| 7 | Ordine sottovoci menu dipende dalla posizione in array | Nota informativa | — |
| 8 | Rate limit GitHub non autenticato (mitigato da cache 6h) | Nota informativa | — |
| 9 | Pulsante "Svuota cache" ha effetto globale, non per istanza | Bassa | UX editor |
| 10 | Nonce AJAX duplicato in 2 file (stesso pattern del punto 6) | Molto bassa | Manutenibilità |

Nessuna criticità di sicurezza rilevata: output sempre escapato (`esc_html`, `esc_attr`,
`esc_url`, o funzioni core che già escapano), input sanitizzato (`sanitize_html_class`,
cast a `int`), capability verificate (`manage_options` per le opzioni, `edit_posts` per
l'endpoint REST), nonce gestiti dalla Settings API.
