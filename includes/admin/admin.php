<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;


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
        $this->admin_dir = plugin_dir_path( GEODIR_CONVERTER_PLUGIN_FILE ) . 'admin';
        $this->admin_url = plugin_dir_url( GEODIR_CONVERTER_PLUGIN_FILE ) . 'admin';

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
		do_action( 'GeoDirectory Converter_admin_init' );
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
			echo 'You can now import stuff';
		}

	}

	/**
	 * Adds our styles to the admin page
	 *
	 * @since GeoDirectory Converter 1.0.0
	 */
	public function enqueue_styles() {
	}

	/**
	 * Adds our scripts to the admin page
	 *
	 * @since GeoDirectory Converter 1.0.0
	 */
	public function enqueue_scripts() {
	}

}
