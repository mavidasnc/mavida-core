<?php
/**
 * Link "Controlla aggiornamenti" nella riga del plugin in Bacheca > Plugin, accanto a
 * "Impostazioni | Disattiva". Riusa l'infrastruttura dell'updater custom da GitHub Releases
 * (vedi includes/updater.php), senza duplicarne la logica: forza un controllo immediato
 * (mavida_core_updater_force_check(), che pulisce e ricostruisce subito il transient
 * "update_plugins", cosi' un eventuale aggiornamento disponibile compare senza attendere il
 * prossimo controllo automatico di WordPress) e poi ricarica la pagina Plugin riportando lo
 * scroll sulla riga del plugin.
 *
 * @package Mavida_Core
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'MAVIDA_CORE_CHECK_UPDATES_NONCE_ACTION' ) ) {
	define( 'MAVIDA_CORE_CHECK_UPDATES_NONCE_ACTION', 'mavida_core_check_updates' );
}

if ( ! function_exists( 'mavida_core_get_plugin_row_anchor' ) ) {
	/**
	 * @return string Id HTML usato sia per l'ancora del link sia per il fragment di redirect,
	 *                 cosi' il click riporta lo scroll esattamente sulla riga di questo plugin.
	 */
	function mavida_core_get_plugin_row_anchor() {
		return 'mavida-core-check-updates';
	}
}

if ( ! function_exists( 'mavida_core_add_check_updates_link' ) ) {
	/**
	 * Aggiunge il link "Controlla aggiornamenti" in coda ai link azione della riga del plugin
	 * (dopo "Impostazioni | Disattiva", aggiunti da mavida_core_add_settings_link() e dal core).
	 *
	 * @param array $links Link gia' presenti nella riga del plugin.
	 * @return array
	 */
	function mavida_core_add_check_updates_link( $links ) {
		$url = wp_nonce_url(
			admin_url( 'plugins.php?mavida_core_check_updates=1' ),
			MAVIDA_CORE_CHECK_UPDATES_NONCE_ACTION
		);

		$links[] = sprintf(
			'<a href="%1$s#%2$s" id="%2$s">%3$s</a>',
			esc_url( $url ),
			esc_attr( mavida_core_get_plugin_row_anchor() ),
			esc_html__( 'Controlla aggiornamenti', 'mavida-core' )
		);

		return $links;
	}
}
add_filter( 'plugin_action_links_' . plugin_basename( MAVIDA_CORE_FILE ), 'mavida_core_add_check_updates_link' );

if ( ! function_exists( 'mavida_core_handle_check_updates_request' ) ) {
	/**
	 * Gestisce il click sul link "Controlla aggiornamenti": forza il controllo, poi
	 * ridirige di nuovo a Bacheca > Plugin con un parametro per la notice di conferma.
	 * Agganciato a "load-plugins.php" (non "admin_init") cosi' gira solo sulla schermata
	 * dell'elenco plugin, dove il link viene mostrato.
	 */
	function mavida_core_handle_check_updates_request() {
		if ( empty( $_GET['mavida_core_check_updates'] ) ) {
			return;
		}

		check_admin_referer( MAVIDA_CORE_CHECK_UPDATES_NONCE_ACTION );

		if ( ! current_user_can( 'update_plugins' ) ) {
			wp_die( esc_html__( 'Permessi insufficienti per controllare gli aggiornamenti.', 'mavida-core' ) );
		}

		mavida_core_updater_force_check();

		wp_safe_redirect(
			add_query_arg( 'mavida_core_checked', '1', admin_url( 'plugins.php' ) ) . '#' . mavida_core_get_plugin_row_anchor()
		);
		exit;
	}
}
add_action( 'load-plugins.php', 'mavida_core_handle_check_updates_request' );

if ( ! function_exists( 'mavida_core_render_check_updates_notice' ) ) {
	/**
	 * Mostra la notice di conferma dopo il redirect di mavida_core_handle_check_updates_request(),
	 * con indicazione se una nuova versione risulta disponibile.
	 */
	function mavida_core_render_check_updates_notice() {
		if ( empty( $_GET['mavida_core_checked'] ) ) {
			return;
		}

		// Guardia sulla schermata: senza, un parametro "mavida_core_checked" aggiunto a mano
		// nell'URL di una qualsiasi pagina admin farebbe comparire la notice li'.
		$screen = get_current_screen();
		if ( ! $screen || 'plugins' !== $screen->id ) {
			return;
		}

		$release           = mavida_core_updater_get_remote_release();
		$update_available  = false !== $release && version_compare( $release['version'], MAVIDA_CORE_VERSION, '>' );
		$message           = $update_available
			/* translators: %s: numero della versione disponibile, es. "1.11.0". */
			? sprintf( __( 'Controllo aggiornamenti completato: è disponibile la versione %s.', 'mavida-core' ), $release['version'] )
			: __( 'Controllo aggiornamenti completato: il plugin è già aggiornato all\'ultima versione.', 'mavida-core' );
		?>
		<div class="notice notice-success is-dismissible">
			<p><?php echo esc_html( $message ); ?></p>
		</div>
		<?php
	}
}
add_action( 'admin_notices', 'mavida_core_render_check_updates_notice' );
