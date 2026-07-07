# Revisione del codice — Mavida Core

Revisione del codice presente nel plugin, aggiornata alla release 1.3.0 (tag `v1.3.0`).
Verifiche effettuate: lint sintattico PHP (`php -l`) su tutti i file, build di produzione
(`npm run build`) completata senza errori, lettura incrociata di tutti i file `includes/` e
del blocco. **Non** è stata eseguita una verifica end-to-end su un'installazione WordPress
reale (questo workspace contiene solo i sorgenti del tema/plugin, non un sito avviabile, e il
dominio pubblico del sito non era raggiungibile da questo ambiente al momento della revisione):
prima di andare in produzione va fatto un giro di test su staging, con WooCommerce e Blocksy
attivi (vedi la sezione "Verifica end-to-end" del piano di sviluppo).

Nessuna delle criticità ancora aperte è bloccante per l'uso del plugin.

## Aggiornamento 1.3.0 — bug reale corretto

L'utente ha segnalato che, dopo aver aggiornato il plugin alla 1.2.0, WordPress continuava a
mostrare "È disponibile una nuova versione... versione 1.2.0" nonostante la versione installata
fosse già la 1.2.0. Causa individuata rileggendo `mavida-core.php`: il bump di versione della
release 1.2.0 aveva aggiornato l'header del plugin (`* Version: 1.2.0`, quello che WordPress
mostra nella lista Plugin) ma **non** la costante `MAVIDA_CORE_VERSION` usata internamente
dall'updater per il confronto (`version_compare()`), rimasta a `1.1.0`. L'updater confrontava
quindi correttamente la release GitHub (1.2.0) con una versione locale che credeva fosse ancora
1.1.0, e segnalava un aggiornamento disponibile che in realtà non c'era.

**Correzione:** la versione non è più duplicata a mano. `MAVIDA_CORE_VERSION` viene ora letta
direttamente dall'header del plugin con `get_file_data()` (unica fonte di verità), quindi non può
più disallinearsi. In aggiunta, il controllo manuale ("Controlla aggiornamenti" nella tab
Aggiornamenti) ora chiama `wp_update_plugins()` per ricostruire subito il transient di WordPress,
invece di limitarsi a cancellarlo: prima di questa modifica, anche dopo aver risolto la causa,
l'avviso poteva restare visibile fino al successivo controllo automatico di WordPress (fino a 12
ore).

Questo è esattamente il tipo di bug che le voci 6 e 10 qui sotto segnalavano in astratto (valori
duplicati in più punti che possono disallinearsi): la versione va aggiunta alla lista delle cose
da tenere d'occhio ad ogni release futura, se in altri punti del codice dovessero comparire nuovi
valori duplicati a mano.

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

## 1. ~~`glob()` non è protetto da un eventuale `false` di ritorno~~ — Risolto in 1.3.0

**File:** `mavida-core.php`

Il ciclo è ora `foreach ( (array) glob( ... ) as $file )`: un eventuale `false` (lettura cartella
fallita) diventa un array vuoto invece di generare un warning PHP.

## 2. ~~Il blocco si registra anche a WooCommerce disattivato~~ — Risolto in 1.2.0

**File:** `includes/block.php`

`mavida_core_register_blocks()` ora esce subito (`return;`) se `class_exists( 'WooCommerce' )` è
falso, prima di chiamare `register_block_type()`. Il blocco non compare più nell'inseritore
quando WooCommerce non è attivo.

## 3. ~~Nessun feedback in editor se la chiamata REST fallisce~~ — Risolto in 1.3.0

**File:** `src/product-category-grid/edit.js`

L'effect di caricamento categorie ora imposta un flag `isMounted` (cleanup al momento dello
smontaggio del componente) e, in caso di errore della `apiFetch`, mostra un `Notice` di errore
nel pannello al posto della select vuota e silenziosa.

## 4. ~~Il breakpoint mobile forza sempre 2 colonne~~ — Risolto in 1.3.0

**File:** `src/product-category-grid/style.scss`

Sotto i 782px il CSS ora usa `repeat( min( var( --mv-columns, 4 ), 2 ), minmax( 0, 1fr ) )`: con
`columns = 1` scelto in editor, il mobile mostra 1 colonna invece di forzarne comunque 2.

## 5. ~~`Domain Path` punta a una cartella `languages/` inesistente~~ — Risolto in 1.3.0

**File:** `mavida-core.php`

La riga `Domain Path: /languages` è stata rimossa dall'header, non essendoci ancora una cartella
`languages/` reale con traduzioni estratte (l'estrazione richiede WP-CLI, non disponibile in
questo ambiente di sviluppo). Da ripristinare quando si predisporrà un vero `.pot`.
`load_plugin_textdomain()` resta comunque attivo e continuerà a funzionare non appena la cartella
sarà aggiunta, indipendentemente dall'header.

## 6. ~~Valore di default duplicato in tre punti~~ — Risolto in 1.3.0

**File:** `mavida-core.php`, `includes/settings-page.php`

Il valore `'mavida-product-cats'` è ora centralizzato nella costante
`MAVIDA_CORE_DEFAULT_MENU_CSS_CLASS`, usata in tutti e tre i punti che prima lo duplicavano.

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

## 9. ~~Pulsante "Svuota cache" ha effetto globale~~ — Chiarito in 1.3.0

**File:** `src/product-category-grid/edit.js`

Il comportamento non è cambiato (resta corretto e voluto: incrementa `mavida_core_cache_version`,
invalidando la cache di tutte le griglie del sito), ma ora sotto il pulsante compare un testo di
aiuto esplicito: "Svuota la cache di tutte le griglie categorie del sito, non solo di questo
blocco."

## 10. ~~Nonce AJAX duplicato in 2 file~~ — Risolto in 1.3.0

**File:** `mavida-core.php`, `includes/settings-page.php`, `includes/updater.php`

La stringa `'mavida_core_admin_nonce'` è ora centralizzata nella costante
`MAVIDA_CORE_ADMIN_NONCE_ACTION`.

---

## Riepilogo priorità

| # | Criticità | Stato | Tipo |
|---|-----------|-------|------|
| — | Costante versione disallineata (falso "aggiornamento disponibile") | **Risolto in 1.3.0** | Bug |
| 1 | `glob()` non protetto da `false` | Risolto in 1.3.0 | Robustezza |
| 2 | Blocco registrato anche senza WooCommerce | Risolto in 1.2.0 | UX editor |
| 3 | Nessun feedback su errore REST in editor | Risolto in 1.3.0 | UX editor |
| 4 | Breakpoint mobile forza 2 colonne | Risolto in 1.3.0 | UX frontend |
| 5 | `Domain Path` senza cartella `languages/` | Risolto in 1.3.0 | Housekeeping |
| 6 | Default duplicato in 3 punti | Risolto in 1.3.0 | Manutenibilità |
| 7 | Ordine sottovoci menu dipende dalla posizione in array | Nota informativa | — |
| 8 | Rate limit GitHub non autenticato (mitigato da cache 6h) | Nota informativa | — |
| 9 | Pulsante "Svuota cache" ha effetto globale, non per istanza | Chiarito in 1.3.0 (help text) | UX editor |
| 10 | Nonce AJAX duplicato in 2 file | Risolto in 1.3.0 | Manutenibilità |

Restano aperte solo le due note informative (7 e 8): nessuna richiede un intervento urgente.

Nessuna criticità di sicurezza rilevata: output sempre escapato (`esc_html`, `esc_attr`,
`esc_url`, o funzioni core che già escapano), input sanitizzato (`sanitize_html_class`,
cast a `int`, filtro su `cardBackgroundColor` per impedire iniezione di dichiarazioni CSS
aggiuntive), capability verificate (`manage_options` per le opzioni, `edit_posts` per l'endpoint
REST categorie, `manage_options` per l'endpoint REST di invalidazione cache), nonce gestiti dalla
Settings API e da `check_ajax_referer()`.
