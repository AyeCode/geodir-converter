<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


/**
 * Loads the plugin admin area
 *
 * @since GeoDirectory Converter 1.0.0
 */
class GDCONVERTER_Admin {

	/**
	 * @var string Path to the admin directory
	 */
	public $admin_dir = '';

	/**
	 * @var string URL to the admin directory
	 */
	public $admin_url = '';

	/**
	 * The main class constructor
	 *
	 * @since GeoDirectory Converter 1.0.0
	 *
	 */
	public function __construct() {

		//Setup class globals
        $this->admin_dir = plugin_dir_path( GEODIR_CONVERTER_PLUGIN_FILE ) . 'includes/admin/';
        $this->admin_url = plugin_dir_url( GEODIR_CONVERTER_PLUGIN_FILE ) . 'includes/admin/';

		//Setup hooks
		add_action( 'admin_menu',              	   		array( $this, 'admin_menus'     ));
		add_action( 'admin_enqueue_scripts',       		array( $this, 'enqueue_styles'  ));
		add_action( 'admin_enqueue_scripts',       		array( $this, 'enqueue_scripts' ));

		/**
		 * Fires after GeoDirectory Converter admin initializes
		 *
		 * @since 1.0.0
		 *
		*/
		do_action( 'geodir_converter_admin_init' );
	}

	/**
	 * Add the admin menus
	 *
	 * @since GeoDirectory Converter 1.0.0
	 *
	 * @uses add_submenu_page() To add our custom menus to the Products menu
	 */
	public function admin_menus() {

		add_submenu_page(
			'tools.php',
			esc_html__( 'GeoDirectory Converter', 'GeoDirectory Converter' ),
			esc_html__( 'GeoDirectory Converter', 'GeoDirectory Converter' ),
			'manage_options',
			'geodir-converter',
			array( $this, 'render_admin_page' )
		);

	}

	/**
	 * Renders the admin page
	 *
	 * @since GeoDirectory Converter 1.0.0
	 */
	public function render_admin_page() {

		if( current_user_can( 'manage_options' ) ){
			include 'template.php';
		}

	}

	/**
	 * Adds our styles to the admin page
	 *
	 * @since GeoDirectory Converter 1.0.0
	 */
	public function enqueue_styles() {
        wp_enqueue_style(
            "geodir-converter",
            $this->admin_url . 'assets/styles.css',
            array(),
            filemtime($this->admin_dir . 'assets/styles.css')
        );
	}

	/**
	 * Adds our scripts to the admin page
	 *
	 * @since GeoDirectory Converter 1.0.0
	 */
	public function enqueue_scripts() {
        $params = array(
            'ajaxurl' 				=> admin_url( 'admin-ajax.php' ),
        );

        wp_register_script(
            "geodir-converter",
            $this->admin_url . 'assets/scripts.js',
            array('jquery'),
            filemtime($this->admin_dir . 'assets/scripts.js'),
            true
        );
        wp_localize_script( 'geodir-converter', 'GD_Converter', $params );
		wp_enqueue_script( 'geodir-converter' );

	}

}
