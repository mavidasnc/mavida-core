<?php
/**
 * Plugin Name:       Mavida Core
 * Plugin URI:        https://github.com/mavidasnc/mavida-core
 * Description:       Funzionalita' core Mavida per WooCommerce/Blocksy: blocco griglia categorie, menu dinamico, opzioni.
 * Version:           1.5.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Author:            MAVIDA
 * Author URI:        https://mavida.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       mavida-core
 * Domain Path:       /languages
 * Update URI:        https://github.com/mavidasnc/mavida-core
 *
 * @package Mavida_Core
 */

// Blocca l'accesso diretto al file.
defined( 'ABSPATH' ) || exit;

// Costanti di base del plugin, usate da tutti i file inclusi.
// Versione e requisito PHP vengono lette direttamente dall'header qui sopra (unica fonte
// di verita'): duplicarle in una stringa a parte aveva gia' causato un disallineamento
// che faceva segnalare un aggiornamento disponibile anche a plugin gia' aggiornato.
$mavida_core_plugin_data = get_file_data( __FILE__, array( 'Version' => 'Version', 'RequiresPHP' => 'Requires PHP' ) );
define( 'MAVIDA_CORE_VERSION', $mavida_core_plugin_data['Version'] );
define( 'MAVIDA_CORE_REQUIRES_PHP', $mavida_core_plugin_data['RequiresPHP'] );
define( 'MAVIDA_CORE_FILE', __FILE__ );
define( 'MAVIDA_CORE_PATH', plugin_dir_path( __FILE__ ) );
define( 'MAVIDA_CORE_URL', plugin_dir_url( __FILE__ ) );
unset( $mavida_core_plugin_data );

// Valore di default della classe CSS del menu dinamico: unica fonte di verita',
// usata sia all'attivazione sia nella pagina opzioni (register_setting e sanitizzazione).
define( 'MAVIDA_CORE_DEFAULT_MENU_CSS_CLASS', 'mavida-product-cats' );

// Azione del nonce usato dalle chiamate AJAX della pagina opzioni (tab Aggiornamenti):
// creato in includes/settings-page.php, verificato in includes/updater.php.
define( 'MAVIDA_CORE_ADMIN_NONCE_ACTION', 'mavida_core_admin_nonce' );

/**
 * Carica il text domain per le traduzioni.
 * Da WordPress 6.7 il caricamento e' gestito automaticamente in modo "just in time",
 * ma il caricamento esplicito resta una buona pratica per compatibilita' con versioni precedenti.
 */
add_action(
	'init',
	function () {
		load_plugin_textdomain( 'mavida-core', false, dirname( plugin_basename( MAVIDA_CORE_FILE ) ) . '/languages' );
	}
);

/**
 * Imposta le opzioni di default all'attivazione del plugin,
 * cosi' la pagina impostazioni e il filtro del menu hanno subito un valore valido.
 */
register_activation_hook(
	MAVIDA_CORE_FILE,
	function () {
		add_option(
			'mavida_core_options',
			array(
				'menu_css_class' => MAVIDA_CORE_DEFAULT_MENU_CSS_CLASS,
			)
		);
	}
);

/**
 * Carica automaticamente ogni file di funzionalita' in includes/.
 * Stesso pattern di caricamento a scansione di cartella gia' usato nel tema mcparts
 * (functions.php -> modules/*.php): un file per responsabilita', nessuna classe.
 * L'ordine alfabetico garantisce che helpers.php (utility condivise) sia incluso
 * prima dei file che ne dipendono (block.php, menu-injection.php, ecc.).
 */
// glob() puo' restituire false (invece di un array vuoto) se la lettura della cartella
// fallisce, es. per una restrizione open_basedir: il cast previene un warning PHP in quel caso.
foreach ( (array) glob( MAVIDA_CORE_PATH . 'includes/*.php' ) as $mavida_core_include_file ) {
	require_once $mavida_core_include_file;
}
unset( $mavida_core_include_file );
