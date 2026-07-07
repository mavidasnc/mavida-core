<?php
/**
 * Pagina opzioni "Mavida Core": permette di definire il nome della classe CSS
 * a cui agganciare l'iniezione dinamica delle categorie prodotto nel menu
 * (vedi includes/menu-injection.php).
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

if ( ! function_exists( 'mavida_core_render_settings_page' ) ) {
	/**
	 * Renderizza la pagina delle opzioni.
	 */
	function mavida_core_render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Mavida Core', 'mavida-core' ); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields( 'mavida_core_settings' );
				do_settings_sections( 'mavida-core' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}
}
