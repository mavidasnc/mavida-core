# Mavida Core

Plugin WordPress con funzionalita' core per siti WooCommerce basati su [Blocksy](https://creativethemes.com/blocksy/).

## Funzionalita'

- **Blocco Gutenberg "Griglia categorie prodotto"** (`mavida-core/product-category-grid`): mostra le
  categorie prodotto WooCommerce in griglia (solo se WooCommerce e' attivo), con il nome in alto e
  l'immagine di categoria (gestita da Blocksy tramite il term meta `thumbnail_id`) sotto. Numero di
  colonne, elenco di categorie da escludere, durata della cache, colore di sfondo delle card e
  arrotondamento degli angoli configurabili dal pannello del blocco; le card hanno una piccola
  animazione al passaggio del mouse (sollevamento + ombra).
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
- PHP 7.4+
- WooCommerce (per le funzionalita' legate alle categorie prodotto)
- Tema Blocksy (per la gestione dell'immagine di categoria)

## Changelog

Vedi [CHANGELOG.md](CHANGELOG.md).

---

Sviluppato da MAVIDA.
