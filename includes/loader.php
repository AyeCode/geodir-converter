<?php

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;


/**
 * Loads the plugin 
 *
 * @since GeoDirectory Converter 1.0.0
 */
class GDCONVERTER_Loarder {
    
	/**
	 * @var string Path to the includes directory
	 */
	public $includes_dir = '';

	/**
	 * @var string URL to the includes directory
	 */
    public $includes_url = '';
    
	/**
	 * The main class constructor
	 *
	 * @since GeoDirectory Converter 1.0.0
	 *
	 */
	public function __construct() {

        //Maybe abort early and save resources
        if(! is_admin() && !wp_doing_ajax()){
            return;
        }
        
        //Setup class globals
        $this->includes_dir = plugin_dir_path( GEODIR_CONVERTER_PLUGIN_FILE ) . 'includes/';
        $this->includes_url = plugin_dir_url( GEODIR_CONVERTER_PLUGIN_FILE ) . 'includes/';

        //Include plugin files
        $this->includes();

        //Init the Admin
        new GDCONVERTER_Admin();

        //Init PMD
        new GDCONVERTER_PMD();
    }
    
    /**
	 * Includes plugin files and dependancies
	 *
	 * @since GeoDirectory Converter 1.0.0
	 *
	 */
	private function includes() {
        require_once( $this->includes_dir . 'admin/admin.php' );
        require_once( $this->includes_dir . 'directories/pmd.php' );
	}


}
