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

        //Setup hooks
        $this->setup_hooks();

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
    
    /**
	 * Attaches handlers to various hooks
	 *
	 * @since GeoDirectory Converter 1.0.0
	 *
	 */
	private function setup_hooks() {
        add_action( 'wp_ajax_gdconverter_handle_first_form', array( $this, 'handle_first_form' ) );

	}

    /**
	 * Retrieves a list of all registerd importers
	 *
	 * @since GeoDirectory Converter 1.0.0
	 *
	 */
	public static function get_importers() {
        return apply_filters( 'geodir_converter_importers', array());
    }

    /**
	 * Sends ajax response to the browser
	 *
	 * @since GeoDirectory Converter 1.0.0
	 *
	 */
	public static function send_response( $action, $body ) {
        die( wp_json_encode( array(
            'action' => $action,
            'body'	 => $body,	
        )));
    }
    
    /**
	 * Processes the first form
	 *
	 * @since GeoDirectory Converter 1.0.0
	 *
	 */
	public function handle_first_form() {

        //Abort if the current user does not have enough rights to run this import
	    if ( !current_user_can( 'manage_options' )  ) {
		    $error = esc_html__( 'You are not allowed to run imports on this site.', 'geodirectory-converter' );
		    self::send_response( 'error', $error );
        }

        //Basic security check
	    if ( empty( $_REQUEST['gdconverter_nonce_field'] ) || ! wp_verify_nonce( $_REQUEST['gdconverter_nonce_field'], 'gdconverter_nonce_action' ) ) {
		    $error = esc_html__( 'An unknown error occured! Please refresh the page and try again.', 'geodirectory-converter' );
		    self::send_response( 'error', $error );
        }

        //Ensure that an importer has been selected...
        if( empty( $_REQUEST['gd-converter'] ) ){
            $error = esc_html__( 'Error: Please select a converter.', 'geodirectory-converter' );
		    self::send_response( 'error', $error );
        }

        //...and is registered
        $importer = sanitize_text_field( $_REQUEST['gd-converter'] );
        $importers= self::get_importers();
        if( !array_key_exists( $importer, $importers ) ){
            $error = esc_html__( 'Error: The converter you selected is not registered on this site.', 'geodirectory-converter' );
		    self::send_response( 'error', $error );
        }
        
        //Let's fetch the next step
        $next_step = '';

        /**
	     * Filters the response returned to the user after selecting an importer
	     *
	     * @since 1.0.0
	     *
	     */
        $next_step = apply_filters( 'geodirectory_importer_fields', $next_step );
        
        /**
	     * Filters the response returned to the user after selecting an importer
	     *
	     * @since 1.0.0
	     *
	     */
        $next_step = apply_filters( "geodirectory_{$importer}_importer_fields", $next_step );

        if( empty($next_step) ){
            $next_step = esc_html__('The selected importer is incorrectly configured', 'geodirectory-converter');
        }

        //Return our response
        self::send_response( 'success', $next_step );
	}
}
