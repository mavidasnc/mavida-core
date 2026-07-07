<?php
/**
 * Plugin Name:       Mavida Core
 * Plugin URI:        https://github.com/mavidasnc/mavida-core
 * Description:       Funzionalita' core Mavida per WooCommerce/Blocksy: blocco griglia categorie, menu dinamico, opzioni.
 * Version:           1.1.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
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
define( 'MAVIDA_CORE_VERSION', '1.1.0' );
define( 'MAVIDA_CORE_FILE', __FILE__ );
define( 'MAVIDA_CORE_PATH', plugin_dir_path( __FILE__ ) );
define( 'MAVIDA_CORE_URL', plugin_dir_url( __FILE__ ) );

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
				'menu_css_class' => 'mavida-product-cats',
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
foreach ( glob( MAVIDA_CORE_PATH . 'includes/*.php' ) as $mavida_core_include_file ) {
	require_once $mavida_core_include_file;
}
unset( $mavida_core_include_file );

/**
 * Auto-update da GitHub tramite la libreria plugin-update-checker (YahnisElsts), vendorizzata
 * in vendor/plugin-update-checker/. Il repository e' pubblico: nessuna autenticazione necessaria.
 * La libreria legge le Release/tag GitHub e confronta la versione con l'header "Version" qui sopra.
 */
require_once MAVIDA_CORE_PATH . 'vendor/plugin-update-checker/plugin-update-checker.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

PucFactory::buildUpdateChecker(
	'https://github.com/mavidasnc/mavida-core/',
	MAVIDA_CORE_FILE,
	'mavida-core'
);
