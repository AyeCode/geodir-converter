<?php
/**
 * Imports data from PMD
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


/**
 * Loads the plugin admin area
 *
 * @since GeoDirectory Converter 1.0.0
 */
class GDCONVERTER_PMD {

	/**
	 * The main class constructor
	 *
	 * @since GeoDirectory Converter 1.0.0
	 *
	 */
	public function __construct() {

		//register our importer
		add_action( 'geodir_converter_importers',	array( $this, 'register_importer' ));

		//render settings fields
		add_action( 'geodirectory_pmd_importer_fields',	array( $this, 'show_initial_settings' ), 10, 2);
		add_action( 'geodirectory_pmd_importer_fields',	array( $this, 'step_2' ), 10, 2);

	}

	/**
	 * Registers the importer
	 *
	 * @since GeoDirectory Converter 1.0.0
	 */
	public function register_importer( $importers ) {
		$importers['pmd'] = array(
			'title' 		=> __( 'PhpMyDirectory', 'geodir-converter' ),
			'description' 	=> __( 'Import listings, events, users and invoices from your PhpMyDirectory installation.', 'geodir-converter' ),
		);
		return $importers;
	}

	/**
	 * Displays initial setting fields
	 *
	 * @since GeoDirectory Converter 1.0.0
	 */
	public function show_initial_settings( $fields, $step ) {

		if( 1 != $step ){
			return $fields;
		}

		$fields .= '
		<h3>Next, we need to connect to your PhpMyDirectory installation</h3>
		<label>Database Host Name<input type="text" value="localhost" name="database-host"></label>
		<label>Database Name<input type="text" value="pmd" name="database-name"></label>
		<label>Database Username<input type="text" value="root" name="database-user"></label>
		<label>Database Password<input type="text" name="database-password"></label>
		<label>Table Prefix<input type="text" value="pmd_" name="table-prefix"></label>
		<input type="submit" value="Connect">
		';
		return $fields;
	}

	/**
	 * Tries to connect to the pmd database
	 *
	 * @since GeoDirectory Converter 1.0.0
	 */
	public function step_2( $fields, $step ) {

		if( 2 != $step ){
			return $fields;
		}
	
		//Prepare db connection details
		$host 		= '';
		$db_name    = '';
		$name 		= '';
		$pass 		= '';
		$pre  		= 'pmd_';

		if( ! empty( $_REQUEST['database-host'] ) ){
			$host = sanitize_text_field($_REQUEST['database-host']);
		}

		if( ! empty( $_REQUEST['database-name'] ) ){
			$db_name = sanitize_text_field($_REQUEST['database-name']);
		}

		if( ! empty( $_REQUEST['database-user'] ) ){
			$name = sanitize_text_field($_REQUEST['database-user']);
		}

		if( ! empty( $_REQUEST['database-password'] ) ){
			$pass = sanitize_text_field($_REQUEST['database-password']);
		}

		if( ! empty( $_REQUEST['table-prefix'] ) ){
			$pre = sanitize_text_field($_REQUEST['table-prefix']);
		}

		//Try connecting to the db
		$db = new wpdb( $name ,$pass ,$db_name ,$host );
		$fields .= 'We have successfuly connected to the database';
		return $fields;
	}


}
