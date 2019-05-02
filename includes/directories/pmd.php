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

	//PMD db connection
	private $db = null;

	//PMD table prefix
	private $prefix = null;

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
		add_action( 'geodirectory_pmd_importer_fields',	array( $this, 'step_3' ), 10, 2);

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

		//Cache the db connection details for an hour
		$cache = array(
			'db' 		=> $db,
			'host' 		=> $host,
			'db_name' 	=> $db_name,
			'pass'		=> $pass,
			'prefix'	=> $prefix
		);
		set_transient( 'geodir_converter_pmd_db_details', $cache, HOUR_IN_SECONDS  );

		$fields .= sprintf(
			__('%s Successfully connected to the PMD database %s
			  %s Next, select the data you wish to import %s', 'geodirectory-converter'),
			'<h3 class="geodir-converter-header-success">',
			'</h3>',
			'<p>',
			'</p>'
		);
		$fields .= $this->get_types_select_html();
		return $fields;
	}

	/**
	 * Generates HTML for importable items
	 *
	 * @since GeoDirectory Converter 1.0.0
	 */
	public function get_types_select_html( $done = array() ) {

		$items = array(
			'users'  		=> esc_html__( 'Users', 'geodirectory-converter'),
			'prices' 		=> esc_html__( 'Price packages', 'geodirectory-converter'),
			'invoices' 		=> esc_html__( 'Invoices', 'geodirectory-converter'),
			'categories' 	=> esc_html__( 'Categories', 'geodirectory-converter'),
			'listings' 		=> esc_html__( 'Listings', 'geodirectory-converter'),
			'events' 		=> esc_html__( 'Events', 'geodirectory-converter'),
			'reviews' 		=> esc_html__( 'Reviews', 'geodirectory-converter'),
		);

		$return = '';
		foreach ( $items as $value => $label ) {

			//If it has already been imported, move on
			if( in_array($value, $done)){
				continue;
			}

			$return .= "
				<label class='geodir-converter-select'>
					<input class='screen-reader-text' name='gd-import' value='$value' type='radio'>
					<span class='dashicons dashicons-admin-page'></span> $label
				</label>
			";
		}
		return $return;
	}

	/**
	 * Handles all the other steps
	 *
	 * @since GeoDirectory Converter 1.0.0
	 */
	public function step_3( $fields, $step ) {

		if( $step < 3 ){
			return $fields;
		}

		//Do we have any database connection details?
		$db_config = get_transient( 'geodir_converter_pmd_db_details');

		if(! $db_config || ! is_array($db_config) ){
			$error = __('Your PMD database settings are missing. Please refresh the page and try again.', 'geodirectory-converter');
			GDCONVERTER_Loarder::send_response( 'error', $error );
		}

		//Try connecting to the db
		$this->db = new wpdb( $db_config['db'] ,$db_config['pass'] ,$db_config['db_name'] ,$db_config['host'] );

		//Tables
		$prefix = $db_config['prefix'] ;
		$tables = array(
			'categories' => "{$prefix}categories",
			'listings'	 => "{$prefix}listings",
			'users'	 	 => "{$prefix}users",
		);

		//What is the user trying to import?
		if( empty($_REQUEST['gd-import']) ){
			$error = __('Please select the data you want to import.', 'geodirectory-converter');
			GDCONVERTER_Loarder::send_response( 'error', $error );
		}
		$import = sanitize_text_field( $_REQUEST['gd-import'] );

		//can we import it?
		if(! array_key_exists( $import, $tables ) ) {
			$error = __('This importer is not configured to import the selected data.', 'geodirectory-converter');
			GDCONVERTER_Loarder::send_response( 'error', $error );
		}

		return $fields;
	}

	/**
	 * Imports listings
	 *
	 * @since GeoDirectory Converter 1.0.0
	 */
	private function import_listings( $table ) {
		global $wpdb;

		$listings_results = $this->db->get_results("SELECT * from $table");

		if( empty( $listings_results ) ){
			$error = __('There are no listings in your PMD directory.', 'geodirectory-converter');
			GDCONVERTER_Loarder::send_response( 'error', $error );
		}

		$posts_table = $wpdb->posts;
		$places_table= geodir_db_cpt_table( 'gd_place' );

		foreach ( $listings_results as $key => $listing ){

			if( !empty( $listing->id ) ){
				continue;
			}

			$status = ( !empty( $listing->status ) && 'active' == $listing->status )? 'publish': $listing->status;
			$status = ( !empty( $listing->status ) && 'suspended' == $listing->status )? 'trash': $status;
			$excerpt = ( !empty( $listing->description_Short ) )? $listing->description_Short: '';
			
			$wpdb->insert(
				$posts_table,
				array(
					'id' 				=> $listing->id,
					'post_author' 		=> $listing->user_id,
					'post_title' 		=> $listing->title,
					'post_name' 		=> $listing->friendly_url,
					'post_excerpt' 		=> $excerpt,
					'post_content' 		=> $listing->description,
					'post_date' 		=> $listing->date,
					'post_date_gmt' 	=> $listing->date,
					'post_modified' 	=> $listing->date_update,
					'post_modified_gmt' => $listing->date_update,
					'comment_status' 	=> 'open',
					'ping_status' 		=> 'closed',
					'post_parent' 		=> 0,
					'guid' 				=> get_site_url() . '/places/' . $listing->friendly_url,
					'menu_order' 		=> 0,
					'post_type' 		=> 'gd_place',
					'comment_count' 	=> 0,
					),
				array('%d','%d','%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%d')
			);

			$inserted_post = $wpdb->insert(
				$places_table,
				array(
					'post_id' 			=> $listing->id,
					'post_title' 		=> $listing->title,
					'post_status' 		=> $status,
					'post_tags' 		=> '',
					'post_category' 	=> $listing->primary_category_id,
					'default_category'  => $listing->primary_category_id,
					'featured_image' 	=> '',
					'submit_ip' 		=> $listing->ip,
					'overall_rating' 	=> $listing->rating,
					'rating_count' 		=> $listing->rating,
					'street' 			=> $listing->listing_address1,
					'city' 				=> $listing->listing_address2,
					'region' 			=> $listing->location_text_1,
					'country' 			=> $listing->location_text_2,
					'zip' 				=>  $listing->listing_zip,
					'latitude' 			=> $listing->latitude,
					'longitude' 		=> $listing->longitude,
					'mapview' 			=> '',
					'mapzoom' 			=> '',
					'phone' 			=> $listing->phone,
					'post_dummy' 		=> 0,
					'email' 			=> $listing->mail,
					'website' 			=> $listing->www,
					'twitter' 			=> 'http://twitter.com/' . $listing->twitter_id,
					'facebook' 			=> 'http://facebook.com/' . $listing->facebook_page_id,
					'video' 			=> '',
					'special_offers' 	=> '',
					'business_hours' 	=> $listing->hours,
					'featured' 			=> $listing->featured,
				),
				array( '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%f', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d')
			);
		
			
		}
	}

	/**
	 * Imports users
	 *
	 * @since GeoDirectory Converter 1.0.0
	 */
	private function import_users( $table ) {
		global $wpdb;

		$pmd_users = $this->db->get_results("SELECT * from $table");

		if( empty( $pmd_users ) ){
			$error = __('There are no users in your PMD directory.', 'geodirectory-converter');
			GDCONVERTER_Loarder::send_response( 'error', $error );
		}

		foreach ( $pmd_users as $key => $user ){

			if( !empty( $user->id ) ){
				continue;
			}

			//Avoid replacing existing users
			if( email_exists( $user->user_email )){
				continue;
			}

			//Since WP and PMD user different hashing algos, users will have to reset their passwords
			$inserted_id = $wpdb->insert(
				$wpdb->users,
				array(
					'id' 				=> $user->id,
					'user_login' 		=> $user->login,
					'user_pass' 		=> $user->pass,
					'user_nicename' 	=> $user->login,
					'user_email' 		=> $user->user_email,
					'user_registered' 	=> $user->created,
					'display_name' 		=> $user->user_first_name . ' ' . $user->user_last_name
				),
				array('%d','%s','%s','%s','%s','%s','%s' )
			);
		
			
		}
	}

	/**
	 * Imports categories
	 *
	 * @since GeoDirectory Converter 1.0.0
	 */
	private function import_categories( $table ) {
		global $wpdb;

		$pmd_cats = $this->db->get_results("SELECT * from $table");

		if( empty( $pmd_cats ) ){
			$error = __('There are no categories in your PMD directory.', 'geodirectory-converter');
			GDCONVERTER_Loarder::send_response( 'error', $error );
		}

		foreach ( $pmd_cats as $key => $cat ){

			if( !empty( $cat->id ) ){
				continue;
			}

			$inserted_id = $wpdb->insert(
				$wpdb->terms,
				array(
					'term_id' 	=> $cat->id,
					'name' 		=> $cat->title,
					'slug' 		=> $cat->friendly_url
				),
				array('%d','%s', '%s')
			);
		
			$wpdb->insert(
				$wpdb->term_taxonomy,
				array(
					'term_id' 	=> $cat->id,
					'taxonomy' 	=> 'gd_placecategory',
					'parent' 	=> $cat->parent_id,
					'count' 	=> $cat->count_total, //? $cat->count??
				),
				array('%d','%d', '%s', '%d', '%d')
			);

		}
	}

}