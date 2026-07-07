<?php
/**
 * Auto-aggiornamento del plugin dalle Release GitHub, senza librerie esterne.
 *
 * Flusso:
 * 1) mavida_core_updater_get_remote_release() interroga /releases/latest dell'API GitHub,
 *    con caching in un transient (6 ore) per non superare il rate limit non autenticato.
 * 2) Se la versione remota e' maggiore di MAVIDA_CORE_VERSION, mavida_core_updater_inject_update()
 *    inserisce i dati di aggiornamento nel transient site "update_plugins" letto da WordPress
 *    (Bacheca > Aggiornamenti, badge sul menu Plugin, ecc.).
 * 3) mavida_core_updater_plugin_info() fornisce il popup "Visualizza dettagli" (filtro plugins_api).
 * 4) Lo zip sorgente generato automaticamente da GitHub si estrae in una cartella con nome
 *    "mavidasnc-mavida-core-<sha>": mavida_core_updater_fix_source_dir() la rinomina nella
 *    cartella reale del plugin prima dell'installazione, altrimenti l'update fallisce silenzioso.
 * 5) mavida_core_updater_handle_check_update() e' l'handler AJAX del pulsante "Controlla
 *    aggiornamenti" nella tab Aggiornamenti (vedi includes/settings-page.php).
 *
 * @package Mavida_Core
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'MAVIDA_CORE_GITHUB_OWNER' ) ) {
	define( 'MAVIDA_CORE_GITHUB_OWNER', 'mavidasnc' );
}
if ( ! defined( 'MAVIDA_CORE_GITHUB_REPO' ) ) {
	define( 'MAVIDA_CORE_GITHUB_REPO', 'mavida-core' );
}

if ( ! function_exists( 'mavida_core_updater_get_basename' ) ) {
	/**
	 * @return string Il basename del plugin, es. "mavida-core/mavida-core.php".
	 */
	function mavida_core_updater_get_basename() {
		return plugin_basename( MAVIDA_CORE_FILE );
	}
}

if ( ! function_exists( 'mavida_core_updater_get_slug' ) ) {
	/**
	 * @return string Lo slug (nome cartella) del plugin.
	 */
	function mavida_core_updater_get_slug() {
		return dirname( mavida_core_updater_get_basename() );
	}
}

if ( ! function_exists( 'mavida_core_updater_get_upgrade_url' ) ) {
	/**
	 * @return string URL con nonce per avviare l'aggiornamento da Bacheca > Aggiornamenti.
	 */
	function mavida_core_updater_get_upgrade_url() {
		$basename = mavida_core_updater_get_basename();

		return wp_nonce_url(
			self_admin_url( 'update.php?action=upgrade-plugin&plugin=' . rawurlencode( $basename ) ),
			'upgrade-plugin_' . $basename
		);
	}
}

if ( ! function_exists( 'mavida_core_updater_get_remote_release' ) ) {
	/**
	 * Recupera l'ultima release pubblicata su GitHub, con caching in transient.
	 *
	 * @param bool $force Se true, ignora il transient e interroga di nuovo l'API.
	 * @return array|false Dati della release, oppure false se non disponibile.
	 */
	function mavida_core_updater_get_remote_release( $force = false ) {
		$cache_key = 'mavida_core_github_release';

		if ( ! $force ) {
			$cached = get_transient( $cache_key );

			if ( false !== $cached ) {
				return $cached;
			}
		}

		$url = sprintf(
			'https://api.github.com/repos/%s/%s/releases/latest',
			MAVIDA_CORE_GITHUB_OWNER,
			MAVIDA_CORE_GITHUB_REPO
		);

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 15,
				'headers' => array(
					'Accept'     => 'application/vnd.github+json',
					'User-Agent' => 'MavidaCore-Updater',
				),
			)
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return false;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $data ) || empty( $data['tag_name'] ) || empty( $data['zipball_url'] ) ) {
			return false;
		}

		$release = array(
			// Il tag e' del tipo "v1.2.0": version_compare non deve vedere il prefisso "v".
			'version'      => ltrim( (string) $data['tag_name'], 'vV' ),
			'download_url' => (string) $data['zipball_url'],
			'changelog'    => isset( $data['body'] ) ? (string) $data['body'] : '',
			'html_url'     => isset( $data['html_url'] ) ? (string) $data['html_url'] : '',
			'published_at' => isset( $data['published_at'] ) ? (string) $data['published_at'] : '',
		);

		set_transient( $cache_key, $release, 6 * HOUR_IN_SECONDS );

		return $release;
	}
}

if ( ! function_exists( 'mavida_core_updater_force_check' ) ) {
	/**
	 * Forza un controllo immediato, ignorando ogni cache, e ricostruisce subito il
	 * transient site "update_plugins" invece di limitarsi a cancellarlo. Senza questo
	 * passaggio l'avviso "aggiornamento disponibile" nella lista Plugin poteva restare
	 * visibile con dati non aggiornati fino al successivo controllo automatico di
	 * WordPress (fino a 12 ore), anche dopo aver premuto "Controlla aggiornamenti".
	 *
	 * @return array|false
	 */
	function mavida_core_updater_force_check() {
		delete_transient( 'mavida_core_github_release' );
		delete_site_transient( 'update_plugins' );

		$release = mavida_core_updater_get_remote_release( true );

		if ( ! function_exists( 'wp_update_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/update.php';
		}
		wp_update_plugins();

		return $release;
	}
}

if ( ! function_exists( 'mavida_core_updater_inject_update' ) ) {
	/**
	 * Inserisce l'aggiornamento nel transient site "update_plugins", se disponibile.
	 *
	 * @param mixed $transient Il transient passato dal filtro.
	 * @return mixed
	 */
	function mavida_core_updater_inject_update( $transient ) {
		if ( ! is_object( $transient ) ) {
			return $transient;
		}

		$release = mavida_core_updater_get_remote_release();

		if ( false === $release || ! version_compare( $release['version'], MAVIDA_CORE_VERSION, '>' ) ) {
			return $transient;
		}

		$basename = mavida_core_updater_get_basename();

		$update               = new stdClass();
		$update->slug         = mavida_core_updater_get_slug();
		$update->plugin       = $basename;
		$update->new_version  = $release['version'];
		$update->package      = $release['download_url'];
		$update->url          = $release['html_url'];
		$update->tested       = '';
		$update->requires_php = MAVIDA_CORE_REQUIRES_PHP;

		$transient->response[ $basename ] = $update;

		return $transient;
	}
}
add_filter( 'pre_set_site_transient_update_plugins', 'mavida_core_updater_inject_update' );

if ( ! function_exists( 'mavida_core_updater_plugin_info' ) ) {
	/**
	 * Fornisce i dati per il popup "Visualizza dettagli" della versione disponibile.
	 *
	 * @param mixed  $result Valore di default del filtro.
	 * @param string $action Azione richiesta da WordPress.
	 * @param object $args   Argomenti della richiesta (contiene lo slug).
	 * @return mixed
	 */
	function mavida_core_updater_plugin_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || mavida_core_updater_get_slug() !== $args->slug ) {
			return $result;
		}

		$release = mavida_core_updater_get_remote_release();

		if ( false === $release ) {
			return $result;
		}

		$info                = new stdClass();
		$info->name          = 'Mavida Core';
		$info->slug          = mavida_core_updater_get_slug();
		$info->version       = $release['version'];
		$info->author        = '<a href="https://mavida.com">MAVIDA</a>';
		$info->homepage      = $release['html_url'];
		$info->download_link = $release['download_url'];
		$info->sections      = array(
			'changelog' => wpautop( wp_kses_post( $release['changelog'] ) ),
		);

		return $info;
	}
}
add_filter( 'plugins_api', 'mavida_core_updater_plugin_info', 20, 3 );

if ( ! function_exists( 'mavida_core_updater_fix_source_dir' ) ) {
	/**
	 * Rinomina la cartella estratta dallo zip sorgente di GitHub (che ha un nome tipo
	 * "mavidasnc-mavida-core-<sha>") nello slug reale del plugin, prima dell'installazione.
	 * Senza questo passaggio l'aggiornamento non sovrascriverebbe la cartella corretta.
	 *
	 * @param string $source        Percorso della cartella estratta.
	 * @param string $remote_source Percorso della cartella temporanea di download.
	 * @param object $upgrader      Istanza dell'upgrader in corso.
	 * @param array  $hook_extra    Contesto dell'operazione (contiene "plugin" per gli update).
	 * @return string|WP_Error
	 */
	function mavida_core_updater_fix_source_dir( $source, $remote_source, $upgrader, $hook_extra ) {
		global $wp_filesystem;

		if ( ! $wp_filesystem ) {
			return $source;
		}

		if ( ! empty( $hook_extra['plugin'] ) ) {
			// Aggiornamento di un plugin: interveniamo solo se e' il nostro.
			if ( mavida_core_updater_get_basename() !== $hook_extra['plugin'] ) {
				return $source;
			}
			$desired_slug = dirname( mavida_core_updater_get_basename() );
		} else {
			// Installazione manuale di uno zip: nessun basename nel contesto, verifichiamo il file principale.
			$main_file = basename( MAVIDA_CORE_FILE );
			if ( ! $wp_filesystem->exists( trailingslashit( $source ) . $main_file ) ) {
				return $source;
			}
			$desired_slug = 'mavida-core';
		}

		$desired_path = trailingslashit( $remote_source ) . $desired_slug;

		if ( untrailingslashit( $source ) === untrailingslashit( $desired_path ) ) {
			return $source;
		}

		if ( $wp_filesystem->move( $source, $desired_path, true ) ) {
			return trailingslashit( $desired_path );
		}

		return new WP_Error(
			'mavida_core_rename_failed',
			__( 'Impossibile rinominare la cartella del pacchetto di aggiornamento.', 'mavida-core' )
		);
	}
}
add_filter( 'upgrader_source_selection', 'mavida_core_updater_fix_source_dir', 10, 4 );

if ( ! function_exists( 'mavida_core_updater_clear_cache_after_update' ) ) {
	/**
	 * Svuota la cache della release subito dopo un aggiornamento riuscito del plugin.
	 *
	 * @param object $upgrader Istanza dell'upgrader.
	 * @param array  $data     Dettagli dell'operazione completata.
	 */
	function mavida_core_updater_clear_cache_after_update( $upgrader, $data ) {
		if ( isset( $data['action'], $data['type'] ) && 'update' === $data['action'] && 'plugin' === $data['type'] ) {
			delete_transient( 'mavida_core_github_release' );
		}
	}
}
add_action( 'upgrader_process_complete', 'mavida_core_updater_clear_cache_after_update', 10, 2 );

if ( ! function_exists( 'mavida_core_updater_handle_check_update' ) ) {
	/**
	 * Handler AJAX del pulsante "Controlla aggiornamenti" nella tab Aggiornamenti.
	 */
	function mavida_core_updater_handle_check_update() {
		check_ajax_referer( MAVIDA_CORE_ADMIN_NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permessi insufficienti.', 'mavida-core' ) ), 403 );
		}

		$release = mavida_core_updater_force_check();

		if ( false === $release ) {
			wp_send_json_error( array( 'message' => __( 'Impossibile contattare GitHub. Riprova più tardi.', 'mavida-core' ) ) );
		}

		$update_available = version_compare( $release['version'], MAVIDA_CORE_VERSION, '>' );

		wp_send_json_success(
			array(
				'current'          => MAVIDA_CORE_VERSION,
				'latest'           => $release['version'],
				'update_available' => $update_available,
				'changelog'        => wpautop( wp_kses_post( $release['changelog'] ) ),
				'html_url'         => $release['html_url'],
				'published_at'     => $release['published_at'],
				'upgrade_url'      => mavida_core_updater_get_upgrade_url(),
			)
		);
	}
}
add_action( 'wp_ajax_mavida_core_check_update', 'mavida_core_updater_handle_check_update' );
