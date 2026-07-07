# Changelog

Tutte le modifiche rilevanti a questo progetto sono documentate in questo file.

Il formato e' basato su [Keep a Changelog](https://keepachangelog.com/it/1.1.0/)
e questo progetto aderisce al [Versionamento Semantico](https://semver.org/lang/it/).

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
