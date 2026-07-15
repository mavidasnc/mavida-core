<?php
/**
 * Pagina opzioni "Mavida Core", organizzata a tab:
 * - "Generale": nome della classe CSS a cui agganciare l'iniezione dinamica delle
 *   categorie prodotto nel menu (vedi includes/menu-injection.php);
 * - "Opzioni": spunta per mostrare/nascondere le colonne prodotto extra in Bacheca
 *   (vedi includes/product-admin-columns.php);
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

if ( ! function_exists( 'mavida_core_add_settings_link' ) ) {
	/**
	 * Aggiunge un link diretto alla pagina opzioni nella riga del plugin, in Bacheca > Plugin
	 * (visibile solo a plugin attivo: se non lo fosse, questo file non verrebbe caricato).
	 *
	 * @param array $links Link gia' presenti nella riga del plugin.
	 * @return array
	 */
	function mavida_core_add_settings_link( $links ) {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'admin.php?page=mavida-core' ) ),
			esc_html__( 'Impostazioni', 'mavida-core' )
		);

		array_unshift( $links, $settings_link );

		return $links;
	}
}
add_filter( 'plugin_action_links_' . plugin_basename( MAVIDA_CORE_FILE ), 'mavida_core_add_settings_link' );

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
				'default'           => array(
					'menu_css_class'       => MAVIDA_CORE_DEFAULT_MENU_CSS_CLASS,
					'show_product_columns' => MAVIDA_CORE_DEFAULT_SHOW_PRODUCT_COLUMNS,
				),
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

		add_settings_section(
			'mavida_core_product_columns_section',
			__( 'Colonne prodotto extra', 'mavida-core' ),
			function () {
				echo '<p>' . esc_html__( 'Mostra le colonne "Codice Marelli" e "Codice OE" nell\'elenco prodotti di Bacheca.', 'mavida-core' ) . '</p>';
			},
			'mavida-core-opzioni'
		);

		add_settings_field(
			'show_product_columns',
			__( 'Colonne extra prodotti', 'mavida-core' ),
			'mavida_core_render_show_product_columns_field',
			'mavida-core-opzioni',
			'mavida_core_product_columns_section'
		);
	}
}
add_action( 'admin_init', 'mavida_core_register_settings' );

if ( ! function_exists( 'mavida_core_sanitize_options' ) ) {
	/**
	 * Sanitizza le opzioni salvate dal form.
	 *
	 * Le tab "Generale" e "Opzioni" condividono lo stesso array di opzioni ma inviano
	 * form separati, ognuno con solo i propri campi: senza sapere quale tab ha inviato
	 * la richiesta, il campo mancante nell'altro form verrebbe scambiato per un valore
	 * svuotato/deselezionato, sovrascrivendo silenziosamente quanto salvato dall'altra tab.
	 * Il campo nascosto "mavida_core_active_tab" (vedi mavida_core_render_settings_page)
	 * distingue i due casi.
	 *
	 * @param array $input Valori grezzi inviati dal form.
	 * @return array Valori sanitizzati.
	 */
	function mavida_core_sanitize_options( $input ) {
		$existing   = get_option( 'mavida_core_options', array() );
		$active_tab = isset( $_POST['mavida_core_active_tab'] ) ? sanitize_key( wp_unslash( $_POST['mavida_core_active_tab'] ) ) : '';

		if ( 'opzioni' === $active_tab ) {
			$class        = isset( $existing['menu_css_class'] ) ? $existing['menu_css_class'] : MAVIDA_CORE_DEFAULT_MENU_CSS_CLASS;
			$show_columns = ! empty( $input['show_product_columns'] );
		} else {
			$class = isset( $input['menu_css_class'] ) ? sanitize_html_class( $input['menu_css_class'] ) : '';

			// Se l'utente svuota il campo o inserisce un valore non valido come classe CSS,
			// si torna al default invece di lasciare l'iniezione del menu silenziosamente disattivata.
			if ( '' === $class ) {
				$class = MAVIDA_CORE_DEFAULT_MENU_CSS_CLASS;
			}

			$show_columns = ! empty( $existing['show_product_columns'] );
		}

		return array(
			'menu_css_class'       => $class,
			'show_product_columns' => $show_columns,
		);
	}
}

if ( ! function_exists( 'mavida_core_render_menu_css_class_field' ) ) {
	/**
	 * Stampa il campo input della classe CSS.
	 */
	function mavida_core_render_menu_css_class_field() {
		$options = get_option( 'mavida_core_options', array() );
		$value   = isset( $options['menu_css_class'] ) ? $options['menu_css_class'] : MAVIDA_CORE_DEFAULT_MENU_CSS_CLASS;
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

if ( ! function_exists( 'mavida_core_render_show_product_columns_field' ) ) {
	/**
	 * Stampa la spunta "Visualizza colonne extra su prodotti".
	 */
	function mavida_core_render_show_product_columns_field() {
		$options = get_option( 'mavida_core_options', array() );
		$checked = ! empty( $options['show_product_columns'] );
		?>
		<label>
			<input
				type="checkbox"
				name="mavida_core_options[show_product_columns]"
				value="1"
				<?php checked( $checked ); ?>
			/>
			<?php esc_html_e( 'Visualizza colonne extra su prodotti', 'mavida-core' ); ?>
		</label>
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
			'opzioni'       => __( 'Opzioni', 'mavida-core' ),
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
				'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
				'nonce'      => wp_create_nonce( MAVIDA_CORE_ADMIN_NONCE_ACTION ),
				// Usati dal pulsante "Svuota cache" della tab Generale, che chiama
				// direttamente l'endpoint REST invece di admin-ajax.php.
				'restUrl'    => rest_url( 'mavida-core/v1/purge-cache' ),
				'restNonce'  => wp_create_nonce( 'wp_rest' ),
				'i18n'       => array(
					'checkingUpdate'  => __( 'Controllo aggiornamenti in corso…', 'mavida-core' ),
					'upToDate'        => __( 'Il plugin è già aggiornato all\'ultima versione.', 'mavida-core' ),
					/* translators: %s: numero della versione disponibile, es. "1.4.0". */
					'updateAvailable' => __( 'È disponibile una nuova versione: %s', 'mavida-core' ),
					'errorCheck'      => __( 'Impossibile controllare gli aggiornamenti. Riprova più tardi.', 'mavida-core' ),
					'cachePurged'     => __( 'Cache svuotata.', 'mavida-core' ),
					'cachePurgeError' => __( 'Impossibile svuotare la cache. Riprova.', 'mavida-core' ),
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
				<?php elseif ( 'opzioni' === $current_tab ) : ?>
					<form action="options.php" method="post">
						<input type="hidden" name="mavida_core_active_tab" value="opzioni" />
						<?php
						settings_fields( 'mavida_core_settings' );
						do_settings_sections( 'mavida-core-opzioni' );
						submit_button();
						?>
					</form>
				<?php else : ?>
					<form action="options.php" method="post">
						<input type="hidden" name="mavida_core_active_tab" value="generale" />
						<?php
						settings_fields( 'mavida_core_settings' );
						do_settings_sections( 'mavida-core' );
						submit_button();
						?>
					</form>

					<hr />

					<h2><?php esc_html_e( 'Cache griglia categorie', 'mavida-core' ); ?></h2>
					<p class="description">
						<?php esc_html_e( 'Svuota la cache del blocco "Griglia categorie prodotto" su tutto il sito, per tutte le sue istanze (stesso effetto del pulsante "Svuota cache" nel pannello del blocco).', 'mavida-core' ); ?>
					</p>
					<p>
						<button type="button" class="button" id="mavida-core-purge-grid-cache">
							<?php esc_html_e( 'Svuota cache', 'mavida-core' ); ?>
						</button>
						<span id="mavida-core-purge-grid-cache-status" style="margin-left: 10px;"></span>
					</p>
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
