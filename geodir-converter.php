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
 * Requires at least: 4.9
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

