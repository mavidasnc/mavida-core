<?php
/**
 * Pagina opzioni "Mavida Core", organizzata a tab:
 * - "Generale": nome della classe CSS a cui agganciare l'iniezione dinamica delle
 *   categorie prodotto nel menu (vedi includes/menu-injection.php);
 * - "Aggiornamenti" (ultima tab): stato dell'auto-update da GitHub e pulsante per
 *   controllare manualmente la presenza di una nuova versione (vedi includes/updater.php).
 *
 * @package Mavida_Core
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'mavida_core_add_settings_page' ) ) {
	/**
	 * Aggiunge la voce di menu top-level "Mavida Core" in bacheca.
	 * Top-level (e non sottopagina) perche' il plugin e' pensato per ospitare
	 * altre impostazioni future oltre a quella del menu dinamico.
	 */
	function mavida_core_add_settings_page() {
		add_menu_page(
			__( 'Mavida Core', 'mavida-core' ),
			__( 'Mavida Core', 'mavida-core' ),
			'manage_options',
			'mavida-core',
			'mavida_core_render_settings_page',
			'dashicons-screenoptions'
		);
	}
}
add_action( 'admin_menu', 'mavida_core_add_settings_page' );

if ( ! function_exists( 'mavida_core_register_settings' ) ) {
	/**
	 * Registra l'opzione tramite la Settings API, con relativa sezione e campo.
	 */
	function mavida_core_register_settings() {
		register_setting(
			'mavida_core_settings',
			'mavida_core_options',
			array(
				'type'              => 'array',
				'sanitize_callback' => 'mavida_core_sanitize_options',
				'default'           => array( 'menu_css_class' => 'mavida-product-cats' ),
			)
		);

		add_settings_section(
			'mavida_core_menu_section',
			__( 'Menu dinamico categorie', 'mavida-core' ),
			function () {
				echo '<p>' . esc_html__( 'Assegna questa classe CSS a una voce di menu (Aspetto > Menu > opzioni schermata > classi CSS): le categorie prodotto verranno mostrate automaticamente come suoi sottomenu.', 'mavida-core' ) . '</p>';
			},
			'mavida-core'
		);

		add_settings_field(
			'menu_css_class',
			__( 'Classe CSS voce di menu', 'mavida-core' ),
			'mavida_core_render_menu_css_class_field',
			'mavida-core',
			'mavida_core_menu_section'
		);
	}
}
add_action( 'admin_init', 'mavida_core_register_settings' );

if ( ! function_exists( 'mavida_core_sanitize_options' ) ) {
	/**
	 * Sanitizza le opzioni salvate dal form.
	 *
	 * @param array $input Valori grezzi inviati dal form.
	 * @return array Valori sanitizzati.
	 */
	function mavida_core_sanitize_options( $input ) {
		$class = isset( $input['menu_css_class'] ) ? sanitize_html_class( $input['menu_css_class'] ) : '';

		// Se l'utente svuota il campo o inserisce un valore non valido come classe CSS,
		// si torna al default invece di lasciare l'iniezione del menu silenziosamente disattivata.
		if ( '' === $class ) {
			$class = 'mavida-product-cats';
		}

		return array( 'menu_css_class' => $class );
	}
}

if ( ! function_exists( 'mavida_core_render_menu_css_class_field' ) ) {
	/**
	 * Stampa il campo input della classe CSS.
	 */
	function mavida_core_render_menu_css_class_field() {
		$options = get_option( 'mavida_core_options', array() );
		$value   = isset( $options['menu_css_class'] ) ? $options['menu_css_class'] : 'mavida-product-cats';
		?>
		<input
			type="text"
			name="mavida_core_options[menu_css_class]"
			value="<?php echo esc_attr( $value ); ?>"
			class="regular-text"
		/>
		<?php
	}
}

if ( ! function_exists( 'mavida_core_get_settings_tabs' ) ) {
	/**
	 * Elenco delle tab della pagina opzioni, nell'ordine in cui vanno mostrate.
	 * "aggiornamenti" e' intenzionalmente l'ultima voce.
	 *
	 * @return array<string,string> Slug tab => etichetta.
	 */
	function mavida_core_get_settings_tabs() {
		return array(
			'generale'      => __( 'Generale', 'mavida-core' ),
			'aggiornamenti' => __( 'Aggiornamenti', 'mavida-core' ),
		);
	}
}

if ( ! function_exists( 'mavida_core_enqueue_admin_assets' ) ) {
	/**
	 * Carica lo script della pagina opzioni (pulsante "Controlla aggiornamenti"),
	 * solo sulla pagina del plugin.
	 *
	 * @param string $hook Hook suffix della schermata admin corrente.
	 */
	function mavida_core_enqueue_admin_assets( $hook ) {
		if ( 'toplevel_page_mavida-core' !== $hook ) {
			return;
		}

		$rel_path = 'assets/admin/settings-page.js';
		$src      = MAVIDA_CORE_URL . $rel_path;
		$path     = MAVIDA_CORE_PATH . $rel_path;
		$version  = file_exists( $path ) ? filemtime( $path ) : MAVIDA_CORE_VERSION;

		wp_enqueue_script( 'mavida-core-admin', $src, array( 'jquery' ), $version, true );

		wp_localize_script(
			'mavida-core-admin',
			'mavidaCoreAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'mavida_core_admin_nonce' ),
				'i18n'    => array(
					'checkingUpdate'  => __( 'Controllo aggiornamenti in corso…', 'mavida-core' ),
					'upToDate'        => __( 'Il plugin è già aggiornato all\'ultima versione.', 'mavida-core' ),
					'updateAvailable' => __( 'È disponibile una nuova versione: %s', 'mavida-core' ),
					'errorCheck'      => __( 'Impossibile controllare gli aggiornamenti. Riprova più tardi.', 'mavida-core' ),
				),
			)
		);
	}
}
add_action( 'admin_enqueue_scripts', 'mavida_core_enqueue_admin_assets' );

if ( ! function_exists( 'mavida_core_render_settings_page' ) ) {
	/**
	 * Renderizza la pagina delle opzioni: tab bar + contenuto della tab selezionata.
	 */
	function mavida_core_render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$tabs = mavida_core_get_settings_tabs();

		$current_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'generale';
		if ( ! array_key_exists( $current_tab, $tabs ) ) {
			$current_tab = 'generale';
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Mavida Core', 'mavida-core' ); ?></h1>

			<nav class="nav-tab-wrapper">
				<?php foreach ( $tabs as $tab_slug => $tab_label ) : ?>
					<a
						href="<?php echo esc_url( add_query_arg( array( 'page' => 'mavida-core', 'tab' => $tab_slug ), admin_url( 'admin.php' ) ) ); ?>"
						class="nav-tab <?php echo $current_tab === $tab_slug ? 'nav-tab-active' : ''; ?>"
					>
						<?php echo esc_html( $tab_label ); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<div class="mavida-core-tab-content" style="margin-top: 20px;">
				<?php if ( 'aggiornamenti' === $current_tab ) : ?>
					<?php mavida_core_render_updates_tab(); ?>
				<?php else : ?>
					<form action="options.php" method="post">
						<?php
						settings_fields( 'mavida_core_settings' );
						do_settings_sections( 'mavida-core' );
						submit_button();
						?>
					</form>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}
}

if ( ! function_exists( 'mavida_core_render_updates_tab' ) ) {
	/**
	 * Renderizza il contenuto della tab "Aggiornamenti": versione installata, ultima
	 * versione (popolata via AJAX), link al repository e pulsante di controllo manuale.
	 */
	function mavida_core_render_updates_tab() {
		?>
		<p class="description">
			<?php esc_html_e( 'Questo plugin si aggiorna direttamente dalle release GitHub. Usa il pulsante qui sotto per controllare una nuova versione su richiesta; WordPress controlla comunque automaticamente in background.', 'mavida-core' ); ?>
		</p>

		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row"><?php esc_html_e( 'Versione installata', 'mavida-core' ); ?></th>
					<td><code><?php echo esc_html( MAVIDA_CORE_VERSION ); ?></code></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Ultima versione', 'mavida-core' ); ?></th>
					<td><code id="mavida-core-latest-version">&#8211;</code></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Repository', 'mavida-core' ); ?></th>
					<td>
						<a href="https://github.com/mavidasnc/mavida-core" target="_blank" rel="noopener noreferrer">mavidasnc/mavida-core</a>
					</td>
				</tr>
			</tbody>
		</table>

		<p style="margin-top: 10px;">
			<button type="button" class="button button-primary" id="mavida-core-check-update">
				<?php esc_html_e( 'Controlla aggiornamenti', 'mavida-core' ); ?>
			</button>
			<span id="mavida-core-update-status" style="margin-left: 10px;"></span>
		</p>

		<div id="mavida-core-update-result"></div>
		<?php
	}
}
