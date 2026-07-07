# Mavida Core

Plugin WordPress con funzionalita' core per siti WooCommerce basati su [Blocksy](https://creativethemes.com/blocksy/).

## Funzionalita'

- **Blocco Gutenberg "Griglia categorie prodotto"** (`mavida-core/product-category-grid`): mostra le
  categorie prodotto WooCommerce in griglia, con il nome in alto e l'immagine di categoria (gestita da
  Blocksy tramite il term meta `thumbnail_id`) sotto. Numero di colonne ed elenco di categorie da
  escludere configurabili dal pannello del blocco.
- **Menu dinamico**: le categorie prodotto vengono aggiunte automaticamente come sottovoci di una voce
  del menu di navigazione, identificata da una classe CSS configurabile in Bacheca > Mavida Core.
- **Auto-aggiornamento**: il plugin controlla e propone gli aggiornamenti direttamente dal repository
  GitHub pubblico [`mavidasnc/mavida-core`](https://github.com/mavidasnc/mavida-core), tramite la
  libreria [plugin-update-checker](https://github.com/YahnisElsts/plugin-update-checker).

## Struttura

```
mavida-core/
├─ mavida-core.php     # File principale: header, costanti, loader, init auto-update
├─ includes/           # Un file per funzionalita', caricati automaticamente
├─ src/                # Sorgenti del blocco Gutenberg (@wordpress/scripts)
├─ build/              # Output compilato del blocco (committato)
└─ vendor/             # Libreria plugin-update-checker vendorizzata (committata)
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
- PHP 7.4+
- WooCommerce (per le funzionalita' legate alle categorie prodotto)
- Tema Blocksy (per la gestione dell'immagine di categoria)

## Changelog

Vedi [CHANGELOG.md](CHANGELOG.md).

---

Sviluppato da MAVIDA.
