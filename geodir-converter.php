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

/**
 ***> Plan:
 ** Create a parent abstract class
 ** create folders with related classes for each directory systems. ex. PDP
 *
 ** Create setting in backend
 ***> Setting for entering database entries
 ***> Show directory system that supported in form of radio button
 ***> Setting for table prefix
 ***> Convert button on clicking on which the process will begin
 */



if ( ! defined( 'WPINC' ) ) {
	die;
}

if ( ! defined( 'GEODIR_CONVERTER_VERSION' ) ) {
	define( 'GEODIR_CONVERTER_VERSION', '1.0.0' );
}

/**
 * Begins execution of the plugin.
 *
 * @since    1.0.0
 */
function geodir_load_geodir_converter() {

	if ( ! defined( 'GEODIR_CONVERTER_PLUGIN_FILE' ) ) {
		define( 'GEODIR_CONVERTER_PLUGIN_FILE', __FILE__ );
	}

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

	if ( did_action( 'geodirectory_loaded' ) ) {
		return;
	}

	$class   = 'notice notice-error';
    $message = __( 'Irks! You need to install GeoDirectory before using this plugin.', 'geodir-converter' );
	$url     = network_admin_url( 'plugin-install.php?tab=plugin-information&plugin=geodirectory&TB_iframe=true&width=600&height=550' );

	printf( '<div class="%s"><p>%s</p><p><a href="%s" class="thickbox button button-primary">%s</a></p></div>', 
		esc_attr( $class ), 
		esc_html( $message ),
		esc_url( $url ),
		esc_html__( 'Install GeoDirectory', 'geodir-converter' )
	 );

}
add_action( 'admin_notices', 'geodir_converter_check_if_geodir_is_installed' );

