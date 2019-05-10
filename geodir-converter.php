<?php
/**
 * This is the main GeoDirectory Converter plugin file, here we declare and call the important stuff
 *
 * @package           Geodir_Converter
 * @copyright         2016 AyeCode Ltd
 * @license           GPLv3
 * @since             1.0.0
 *
 * @geodir_converter
 * Plugin Name: GeoDirectory Converter
 * Plugin URI: https://ayecode.com
 * Description: A plugin to convert other directories to GeoDirectory
 * Version: 1.0.0
 * Author: AyeCode Ltd
 * Author URI: https://wpgeodirectory.com/
 * Requires at least: 4.7
 * Tested up to: 5.1
 * License: GPLv3
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: geodir-converter
 * Domain Path: /languages
 * Update URL: https://wpgeodirectory.com
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

if ( ! defined( 'GEODIR_CONVERTER_VERSION' ) ) {
	define( 'GEODIR_CONVERTER_VERSION', '1.0.0' );
}

if ( ! defined( 'GEODIR_CONVERTER_PLUGIN_FILE' ) ) {
	define( 'GEODIR_CONVERTER_PLUGIN_FILE', __FILE__ );
}

/**
 * Begins execution of the plugin.
 * 
 * Loads the plugin after GD has been loaded
 * 
 * @since    1.0.0
 */
function geodir_load_geodir_converter() {	
	require_once ( plugin_dir_path( GEODIR_CONVERTER_PLUGIN_FILE ) . 'includes/loader.php' );
	new GDCONVERTER_Loarder();
}
add_action( 'geodirectory_loaded', 'geodir_load_geodir_converter' );

/**
 * Tells the user to install GeoDirectory, if they haven't
 *
 * @since    1.0.0
 */
function geodir_converter_check_if_geodir_is_installed() {

	//If this is not an admin page or GD is activated, abort early
	if ( !is_admin() || did_action( 'geodirectory_loaded' ) ) {
		return;
	}

	include_once( ABSPATH . 'wp-admin/includes/plugin.php' );

	$class   = 'notice notice-warning is-dismissible';
	$action  = 'install-plugin';
	$slug	 = 'geodirectory';
	$basename= 'geodirectory/geodirectory.php';

	//Ask the user to activate GD in case they have installed it. Otherwise ask them to install it
	if( is_plugin_inactive($basename) ){

		$activation_url = esc_url( 
			wp_nonce_url( 
				admin_url("plugins.php?action=activate&plugin=$basename"), 
				"activate-plugin_$basename" ) 
			);

		printf( 
			esc_html__( '%s requires the %sGeodirectory%s plugin to be installed and active. %sClick here to activate it.%s', 'geodirectory-converter' ),
			"<div class='$class'><p><strong>GeoDirectory Converter", 
			'<a href="https://wpgeodirectory.com" target="_blank" title=" GeoDirectory">', 
			'</a>', 
			"<a href='$activation_url'  title='GeoDirectory'>", 
			'</a></strong></p></div>' );

	}else{

		$install_url = esc_url( wp_nonce_url(
			add_query_arg(
				array(
					'action' => $action,
					'plugin' => $slug
				),
				admin_url( 'update.php' )
			),
			$action.'_'.$slug
		) );
		
		printf( 
			esc_html__( '%s requires the %sGeodirectory%s plugin to be installed and active. %sClick here to install it.%s', 'geodirectory-converter' ),
			"<div class='$class'><p><strong>GeoDirectory Converter", 
			'<a href="https://wpgeodirectory.com" target="_blank" title=" GeoDirectory">', 
			'</a>', 
			"<a href='$install_url'  title='GeoDirectory'>",
			'</a></strong></p></div>' );

	}

}
add_action( 'admin_notices', 'geodir_converter_check_if_geodir_is_installed' );


/**
 * The code that runs during plugin activation.
 * 
 * @since 1.0.0
 */
function activate_geodir_converter() {
	//Set a transient showing the plugin has been activated. Used to redirect users to the plugin page
    set_transient( '_geodir_converter_installed', '1', MINUTE_IN_SECONDS  );
}
register_activation_hook( __FILE__, 'activate_geodir_converter' );