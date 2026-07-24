=== Mavida Core ===
Contributors: mavida
Tags: woocommerce, gutenberg, block, categories, menu, cpt
Requires at least: 6.5
Tested up to: 6.9
Requires PHP: 8.1
Stable tag: 1.11.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Funzionalita' core Mavida per WooCommerce/Blocksy: blocco griglia categorie, menu dinamico, opzioni.

== Description ==

Mavida Core aggiunge al sito:

* Un blocco Gutenberg dinamico che mostra una griglia delle categorie prodotto WooCommerce (nome e immagine
  di categoria gestita da Blocksy), con numero di colonne e categorie escluse configurabili.
* Un blocco Gutenberg dinamico analogo che mostra una griglia dei post di un tipo di contenuto (CPT) a
  scelta (nome e immagine in evidenza del post), con le stesse opzioni di aspetto, CTA, cache e CSS
  personalizzato del blocco categorie.
* Un blocco Gutenberg dinamico analogo che mostra una griglia dei termini di una tassonomia a scelta
  (qualunque tassonomia pubblica del sito), con le stesse opzioni di aspetto, CTA, cache e CSS
  personalizzato degli altri due blocchi.
* Un sistema che inietta automaticamente l'elenco delle categorie prodotto come sottovoci di una voce
  del menu di navigazione, identificata tramite una classe CSS impostabile in una pagina di opzioni dedicata.
* Aggiornamenti automatici del plugin direttamente dal repository GitHub pubblico.

== Installation ==

1. Carica la cartella `mavida-core` in `wp-content/plugins/`.
2. Attiva il plugin dal menu Plugin di WordPress.
3. Vai in "Mavida Core" nel menu di amministrazione per impostare la classe CSS del menu dinamico.

== Changelog ==

= 1.11.0 =
* Nuovo blocco Gutenberg "Griglia Tassonomia": analogo alla griglia categorie prodotto, ma con la tassonomia selezionabile (tutte le tassonomie pubbliche del sito) invece di product_cat fissa. Card = termine, con nome, permalink d'archivio e immagine (stessa logica di fallback della griglia categorie, senza placeholder WooCommerce).
* Nuovo link "Controlla aggiornamenti" nella riga del plugin in Bacheca > Plugin: forza un controllo immediato della release GitHub e ricarica la pagina con una notice di conferma.

= 1.10.1 =
* Fix: il colore del bordo card (e in generale ogni modifica al colore di sfondo/bordo) non veniva mai riflesso in frontend perche' la cache del blocco calcolava la chiave senza considerare il colore del bordo, servendo sempre l'HTML della prima combinazione generata.

= 1.10.0 =
* Nuovo blocco Gutenberg "Griglia post per tipo di contenuto": analogo alla griglia categorie prodotto, ma su un CPT a scelta invece che su product_cat (card = post, con titolo, immagine in evidenza, post da includere/escludere, aspetto card, CTA, cache e CSS personalizzato).

= 1.9.0 =
* Nuova tab "Opzioni" nella pagina impostazioni (Bacheca > Mavida Core), con la spunta "Visualizza colonne extra su prodotti" (disattivata di default) per mostrare o nascondere le colonne "Codice Marelli" e "Codice OE" nell'elenco prodotti di Bacheca.

= 1.8.0 =
* CSS personalizzato per singola istanza del blocco: pulsante "Personalizza CSS" apre una modale con evidenziazione sintattica (CodeMirror), precompilata col CSS di default, con pulsanti "Ripristina default" e "Salva CSS".
* Immagine di default (dalla media library) per le categorie senza immagine propria.
* I colori della CTA sono ora dentro l'accordion "Call to action", non più in un pannello separato.

= 1.7.0 =
* Nuova opzione "categorie da includere" (priorità assoluta su "escludi", ordine di visualizzazione preservato).
* Tre nuovi filtri per personalizzare la griglia via codice: mavida_core_product_category_grid_item_context (contesto di una card), mavida_core_product_category_grid_item_html (HTML di una card), mavida_core_product_category_grid_after_items (HTML dopo la griglia). Documentati nel README con esempi.
* Pulsante "Svuota cache" anche nella pagina opzioni (Bacheca > Mavida Core > Generale), oltre a quello nel pannello del blocco.

= 1.6.0 =
* Numero di colonne mobile configurabile separatamente da quello desktop (default 2).
* Padding delle card configurabile dal pannello del blocco (default 16px = 1rem).
* Nuovo filtro mavida_core_product_category_grid_categories per aggiungere/rimuovere/riordinare le categorie via codice. Documentato nel README con esempio.

= 1.5.0 =
* Requisito minimo PHP alzato a 8.1 (verificato: nessuna sintassi 8.x gia' in uso).
* La call to action del blocco ora e' dentro ogni card, senza campo URL: eredita il link della card.
* Due nuove colonne, "Codice Marelli" e "Codice OE", nell'elenco prodotti di Bacheca.
* Link diretto "Impostazioni" nella riga del plugin in Bacheca > Plugin.

= 1.4.0 =
* Tag HTML del nome categoria configurabile (H1-H4, div, span), con colore e dimensione testo.
* Nuovo campo Call to Action (testo libero, url, dimensione, colori, stile pulsante) sotto la griglia.
* La select delle categorie da escludere usa ora FormTokenField, lo stesso componente nativo di WordPress per la selezione dei tag.

= 1.3.1 =
* Aggiunto il file di traduzione languages/mavida-core.pot (45 stringhe estratte) e ripristinato l'header Domain Path.

= 1.3.0 =
* Corretto un bug per cui il plugin segnalava un aggiornamento disponibile anche quando era già alla versione più recente (costante di versione disallineata dall'header).
* Il controllo manuale degli aggiornamenti ora ricostruisce subito lo stato, senza attendere il prossimo controllo automatico di WordPress.
* Il blocco "Griglia categorie prodotto" ha ora card con sfondo (colore configurabile), angoli arrotondati (configurabili) e una piccola animazione al passaggio del mouse.
* Varie correzioni minori di robustezza e manutenibilità individuate in fase di revisione del codice.

= 1.2.0 =
* Auto-aggiornamento da GitHub riscritto senza librerie esterne (rimossa la dipendenza plugin-update-checker).
* Il blocco "Griglia categorie prodotto" si registra solo se WooCommerce è attivo.
* Cache del markup frontend del blocco (durata configurabile, pulsante "Svuota cache" nel pannello del blocco).
* Pagina opzioni "Mavida Core" riorganizzata a tab, con una nuova tab "Aggiornamenti".

= 1.1.0 =
* Blocco Gutenberg "Griglia categorie prodotto" (colonne + esclusioni).
* Iniezione delle categorie prodotto come sottovoci di menu tramite classe CSS configurabile.
* Pagina opzioni "Mavida Core".
* Endpoint REST mavida-core/v1/product-categories.
* Auto-aggiornamento da GitHub.
