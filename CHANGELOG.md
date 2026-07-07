# Changelog

Tutte le modifiche rilevanti a questo progetto sono documentate in questo file.

Il formato e' basato su [Keep a Changelog](https://keepachangelog.com/it/1.1.0/)
e questo progetto aderisce al [Versionamento Semantico](https://semver.org/lang/it/).

## [1.4.0] - 2026-07-07

### Added
- Tag HTML del nome categoria configurabile dal pannello del blocco (H1, H2, H3, H4, div, span),
  con colore e dimensione testo. Il tag e' validato server-side contro un elenco chiuso, per
  sicurezza (non e' possibile iniettare un tag arbitrario tramite l'attributo del blocco).
- Nuovo campo "Call to action" (es. "Visualizza tutti i prodotti") sotto la griglia: testo
  libero, URL, dimensione testo, colore testo, opzione "mostra come pulsante" con relativo
  colore di sfondo. Se il testo e' vuoto, la CTA non viene renderizzata.

### Changed
- La select delle categorie da escludere usa ora `FormTokenField`, lo stesso componente che
  WordPress usa nativamente per il pannello "Tag" nella barra laterale dell'editor (chip con
  ricerca), al posto della `<select multiple>` nativa.
- Estratta la sanitizzazione dei valori colore CSS (gia' usata per lo sfondo delle card) in un
  helper condiviso (`mavida_core_sanitize_css_color()`), ora riusato anche per i colori di nome
  categoria e CTA.

[1.4.0]: https://github.com/mavidasnc/mavida-core/releases/tag/v1.4.0

## [1.3.1] - 2026-07-07

### Added
- File di traduzione `languages/mavida-core.pot` (45 stringhe estratte da PHP, JS e block.json
  con WP-CLI `wp i18n make-pot`), e ripristinato l'header `Domain Path: /languages` che nella
  1.3.0 era stato rimosso in assenza di un vero file di traduzione.

### Fixed
- Aggiunto il commento `translators:` mancante per il placeholder `%s` nella stringa "È disponibile
  una nuova versione: %s".

[1.3.1]: https://github.com/mavidasnc/mavida-core/releases/tag/v1.3.1

## [1.3.0] - 2026-07-07

### Fixed
- Corretto il bug per cui WordPress continuava a segnalare un aggiornamento disponibile anche a
  plugin già aggiornato: la costante `MAVIDA_CORE_VERSION` era rimasta disallineata dall'header
  durante il bump a 1.2.0. La versione viene ora letta direttamente dall'header del plugin
  (`get_file_data()`), eliminando la duplicazione che aveva causato il problema.
- Il controllo manuale nella tab Aggiornamenti ora ricostruisce subito il transient di WordPress
  (`wp_update_plugins()`), invece di limitarsi a cancellarlo: l'avviso "aggiornamento disponibile"
  si aggiorna immediatamente invece di attendere il prossimo controllo automatico.
- `glob()` in `mavida-core.php` e' ora protetto contro un eventuale ritorno `false`.
- Il breakpoint mobile del blocco rispetta ora il numero di colonne scelto (non forza più sempre
  2 colonne quando l'editor ne ha impostata 1).
- Gestione dell'errore (e cleanup allo smontaggio) quando la richiesta REST delle categorie fallisce
  nell'editor del blocco.
- Rimosso `Domain Path` dall'header del plugin: puntava a una cartella `languages/` inesistente.
- Centralizzati in costanti il valore di default della classe CSS del menu e l'azione del nonce
  amministrativo, prima duplicati in più file.

### Added
- Il blocco "Griglia categorie prodotto" ha ora card con sfondo (colore configurabile dal
  pannello), angoli arrotondati (configurabili) e una piccola animazione al passaggio del mouse
  (sollevamento + ombra), ispirata alle card prodotto delle demo del gruppo Mavida.

[1.3.0]: https://github.com/mavidasnc/mavida-core/releases/tag/v1.3.0

## [1.2.0] - 2026-07-07

### Changed
- Auto-aggiornamento da GitHub riscritto interamente a codice, senza più dipendere dalla libreria
  esterna plugin-update-checker (rimossa la cartella `vendor/`).
- Il blocco "Griglia categorie prodotto" ora si registra solo se WooCommerce è attivo.
- Pagina opzioni "Mavida Core" riorganizzata a tab: "Generale" e, come ultima tab, "Aggiornamenti"
  (versione installata, ultima versione, repository, controllo manuale).

### Added
- Cache del markup frontend del blocco tramite transient, con durata configurabile (in minuti)
  e pulsante "Svuota cache" nel pannello del blocco.
- Endpoint REST `mavida-core/v1/purge-cache` per invalidare la cache del blocco.

[1.2.0]: https://github.com/mavidasnc/mavida-core/releases/tag/v1.2.0

## [1.1.0] - 2026-07-07

Prima release pubblica del plugin: si parte direttamente dalla 1.1.0, senza una 1.0.0 pregressa.

### Added
- Blocco Gutenberg dinamico "Griglia categorie prodotto": mostra nome e immagine (gestita da Blocksy)
  di ogni categoria prodotto WooCommerce, con numero di colonne ed elenco di categorie escludibili
  configurabili dall'editor.
- Iniezione dinamica delle categorie prodotto come sottovoci di una voce del menu nativo di
  WordPress, identificata tramite una classe CSS configurabile.
- Pagina opzioni "Mavida Core" per impostare la classe CSS usata dall'iniezione del menu.
- Endpoint REST `mavida-core/v1/product-categories` a supporto dell'editor del blocco.
- Auto-aggiornamento del plugin dal repository GitHub pubblico tramite plugin-update-checker.

[1.1.0]: https://github.com/mavidasnc/mavida-core/releases/tag/v1.1.0
