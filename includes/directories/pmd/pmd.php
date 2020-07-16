<?php
/**
 * Imports data from PMD
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


/**
 * Converts PhpMyDirectory to GeoDirectory
 *
 * @since GeoDirectory Converter 1.0.0
 */
class GDCONVERTER_PMD {

	/**
	 * Stores our on instance of $wpdb
	 * 
	 * Used to connect to and query the PMD db
	 * 
	 */
	private $db = null;

	/**
	 * Stores PMD db tables prefix string
	 * 
	 */
	private $prefix = null;

	/**
	 * Stores PMD site URL string
	 *
	 */
	private $url = null;

	/**
	 * Stores PMD test mode status
	 *
	 */
	private $test_mode = false;



	/**
	 * The main class constructor
	 * 
	 * Initializes the PMD converter and registers custom actions and filter hooks
	 *
	 * @since GeoDirectory Converter 1.0.0
	 *
	 */
	public function __construct() {


		//define( 'GEODIR_CONVERTER_TEST_MODE', true ); // uncomment this line to enable test mode

		// Set doing import constant.
		if ( defined( 'GEODIR_CONVERTER_TEST_MODE' ) ) {
			$this->test_mode = true;
		}

		//register our converter
		add_action( 'geodir_converter_importers',	array( $this, 'register_importer' ));

		//Render converter form fields
		add_action( 'geodirectory_pmd_importer_fields',	array( $this, 'show_initial_settings' ), 10, 2);
		add_action( 'geodirectory_pmd_importer_fields',	array( $this, 'step_2' ), 10, 2);
		add_action( 'geodirectory_pmd_importer_fields',	array( $this, 'step_3' ), 10, 2);

		//Handles ajax requers for imports progress
		add_action( 'wp_ajax_gdconverter_pmd_handle_progress', array( $this, 'handle_progress' ) );

		//Handle logins for imported users
		add_filter( 'wp_authenticate_user',	array( $this, 'handle_login' ), 10, 2 );

		// handel 404 rescue
		add_action('wp',array($this,'_404_rescue'), 11);


	}

	/**
	 * Check the 404 page to see if its a GD CPT and if we can find the correct page.
	 *
	 * This can help with GDv1 -> GDv2 sites auto tell search engines the urls have moved.
	 */
	public function _404_rescue(){
		if(is_404() && geodir_get_option("enable_404_rescue",1)){
			global $wp_query,$wp,$wpdb,$plugin_prefix;

			$post_type = isset($wp_query->query_vars['post_type']) ? $wp_query->query_vars['post_type'] : '';
			$url_segments = explode("/",$wp->request);

			$maybe_slug = end($url_segments);


			// check for single pages
			if($maybe_slug && stripos(strrev($maybe_slug), "lmth.") === 0){
				$parts = explode("-",$maybe_slug);
				if(!empty($parts)){
					$num_html = end($parts);
					$num_html_parts = explode(".",$num_html);
					if(!empty($num_html_parts[0])){
						$old_listing_id = absint($num_html_parts[0]);

						// check places
						$places_table = $plugin_prefix . 'gd_place_detail';
						$new_listing_id  = $wpdb->get_var($wpdb->prepare("SELECT post_id FROM $places_table WHERE pmd_id = %d",$old_listing_id));

						if(!$new_listing_id ){
							// check events
							$events_table = $plugin_prefix . 'gd_event_detail';
							$new_listing_id  = $wpdb->get_var($wpdb->prepare("SELECT post_id FROM $events_table WHERE pmd_id = %d",$old_listing_id));
						}

						if($new_listing_id ){
							$listing_url = get_permalink($new_listing_id);
							if($listing_url){
								wp_redirect($listing_url,'301');exit;
							}
						}

					}

				}
			}

			// check for categories
			if(!empty($url_segments) && reset($url_segments)=='category' && $maybe_slug!='category'){
				$term = get_term_by( 'slug', $maybe_slug, "gd_placecategory");
				if(!empty($term)){
					$term_link = get_term_link($term, "gd_placecategory");
					if($term_link){
						wp_redirect($term_link,'301');exit;
					}
				}
			}
		}
	}

	/**
	 * Registers the importer
	 *
	 * @param $importers array. An array of registered importers
	 * @since GeoDirectory Converter 1.0.0
	 */
	public function register_importer( $importers ) {
		$importers['pmd'] = array(
			'title' 		=> esc_html__( 'PhpMyDirectory', 'geodirectory-converter' ),
			'description' 	=> esc_html__( 'Import listings, events, users and invoices from your PhpMyDirectory installation.', 'geodirectory-converter' ),
		);
		return $importers;
	}
		
	/**
	 * Handles progress requests
	 *
	 * @since GeoDirectory Converter 1.0.0
	 */
	public function handle_progress() {

		//Abort if the current user does not have enough rights to run this import
	    if ( ! current_user_can( 'manage_options' )  ) {
		    $error = esc_html__( 'You are not allowed to run imports on this site.', 'geodirectory-converter' );
		    GDCONVERTER_Loarder::send_response( 'error', $error );
        }

        //Basic security check
	    if ( empty( $_REQUEST['gdconverter_nonce_field'] ) || ! wp_verify_nonce( $_REQUEST['gdconverter_nonce_field'], 'gdconverter_nonce_action' ) ) {
		    $error = esc_html__( 'An unknown error occured! Please refresh the page and try again.', 'geodirectory-converter' );
		    GDCONVERTER_Loarder::send_response( 'error', $error );
        }

		//Do we have any database connection details?
		$db_config = get_transient( 'geodir_converter_pmd_db_details');

		if(! $db_config || ! is_array($db_config) ){
			$error = esc_html__('Your PMD database settings are missing. Please refresh the page and try again.', 'geodirectory-converter');
			GDCONVERTER_Loarder::send_response( 'error', $error );
		}
		
		//Try connecting to the db
		$this->db = new wpdb( $db_config['user'] ,$db_config['pass'] ,$db_config['db_name'] ,$db_config['host'] );
		
		//If we are here, we connected successfully. Next, set the table prefix
		$this->prefix = $db_config['prefix'] ;

		// set url
		$this->url = $db_config['url'] ;

		//What data are we currently importing?
		$type = trim($_REQUEST['type']);

		//Suspend cache additions
		wp_suspend_cache_addition(true);

		call_user_func( array( $this, "import_" . $type) );
		
	}

	/**
	 * Updates the user on the current progress via ajax
	 *
	 * @param $fields string. String of HTML to show the user
	 * @param $count  Integer. Optional. The total number of items to import
	 * @param $offset Integer. Optional. The total number of processed items
	 * 
	 * @since GeoDirectory Converter 1.0.0
	 */
	public function update_progress( $fields, $count = 0, $offset = 0 ) {

		$form = '
				<form method="post" action="" class="geodir-converter-form">
					<input type="hidden" name="action" value="gdconverter_pmd_handle_progress">
		';
		$form .= wp_nonce_field( 'gdconverter_nonce_action', 'gdconverter_nonce_field', true, false );
		$form .= $fields;
		$form .= '</form>';

		//If offset and count has been set, display a progress bar
		$hasprogress = ($count && $offset);

		wp_send_json( array(
		'action'	=> 'custom',
        'body'	 	=> array(
				'count' 		=> $count,
				'offset' 		=> $offset,
				'form' 			=> $form,
				'hasprogress' 	=> $hasprogress,
			),	
        ) );
	}

	/**
	 * Displays initial setting fields
	 *
	 * @param $fields Form fields to display to the user
	 * @param $step The current import step
	 * @since GeoDirectory Converter 1.0.0
	 */
	public function show_initial_settings( $fields, $step ) {

		if( 1 != $step ){
			return $fields;
		}

		//Delete previous progress details
		delete_transient('_geodir_converter_pmd_progress');

		//Ensure there are no users since this tool deletes all of them
		$users = count_users();
		if( ! $this->test_mode && $users['total_users'] > 1){

			$message = sprintf(
				esc_html__('Detected %s users', 'geodirectory-converter'),
				$users['total_users']);

			return $fields . sprintf( 
				'<h3 class="geodir-converter-header-error">%s</h3><p>%s</p>',
				$message,
				esc_html__('You must use a fresh install of WordPress to use this converter since existing data will be overidden.', 'geodirectory-converter')
			);

		}

		//Display DB connection details
		$form     = '
			<h3>%s</h3>
			<label class="geodir-label-grid"><div class="geodir-label-grid-label">%s</div><input type="text" value="" placeholder="https://mysite.com/" name="site-url"></label>
			<label class="geodir-label-grid"><div class="geodir-label-grid-label">%s</div><input type="text" value="localhost" name="database-host"></label>
			<label class="geodir-label-grid"><div class="geodir-label-grid-label">%s</div><input type="text" value="pmd" name="database-name"></label>
			<label class="geodir-label-grid"><div class="geodir-label-grid-label">%s</div><input type="text" value="root" name="database-user"></label>
			<label class="geodir-label-grid"><div class="geodir-label-grid-label">%s</div><input type="password" name="database-password"></label>
			<label class="geodir-label-grid"><div class="geodir-label-grid-label">%s</div><input type="text" value="pmd_" name="table-prefix"></label>		
			<input type="submit" class="button button-primary" value="%s">
		';
		$fields  .= sprintf(
			$form,
			esc_html__('Next, we need to connect to your PhpMyDirectory installation', 'geodirectory-converter'),
			esc_html__('PMD root URL (eg: https://mysite.com/)', 'geodirectory-converter'),
			esc_html__('Database Host Name', 'geodirectory-converter'),
			esc_html__('Database Name', 'geodirectory-converter'),
			esc_html__('Database Username', 'geodirectory-converter'),
			esc_html__('Database Password', 'geodirectory-converter'),
			esc_html__('Table Prefix', 'geodirectory-converter'),
			esc_attr__('Connect', 'geodirectory-converter')
		);
		return $fields;
	}

	/**
	 * Verify the user db details
	 *
	 * @param $fields Form fields to display to the user
	 * @param $step The current import step
	 * @since GeoDirectory Converter 1.0.0
	 */
	public function step_2( $fields, $step ) {

		if( 2 != $step ){
			return $fields;
		}
	
		//Prepare db connection details
		$url 		= '';
		$host 		= '';
		$db_name    = '';
		$name 		= '';
		$pass 		= '';
		$pre  		= 'pmd_';

		if( ! empty( $_REQUEST['site-url'] ) ){
			$url = sanitize_text_field($_REQUEST['site-url']);
		}

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

		//If we are here, db connection details are correct
		//Let's cache them for a day
		$cache = array(
			'url' 		=> $url,
			'host' 		=> $host,
			'db_name' 	=> $db_name,
			'pass'		=> $pass,
			'user'		=> $name,
			'prefix'	=> $pre
		);
		set_transient( 'geodir_converter_pmd_db_details', $cache, DAY_IN_SECONDS  );

		//Display the next step to the user
		$title 			= esc_html__( 'Successfully connected to PhpMyDirectory', 'geodirectory-converter');
		$sub_title 		= esc_html__( 'Click the button below to import all your PhpMyDirectory data into this website.', 'geodirectory-converter');
		$notes_title	= esc_html__( 'Important Notes', 'geodirectory-converter');
		$button			= esc_attr__( 'Start Importing Data', 'geodirectory-converter');

		$fields .= "
				<h3 class='geodir-converter-header-success'>$title</h3>";
		$fields .= "<h4>$notes_title</h4>
				<ul class='geodir-conveter-notes' style='color:red;list-style-type: disc;'>
		";

		foreach ($this->get_notes() as $note) {
			$fields .= "<li>$note</li>";
		}

		$fields .= '</ul>';

		$fields .="<p>$sub_title</p>
				<div class='geodir-conveter-centered'>
					<input type='submit' class='button button-primary' value='$button'>
				</div>";


		return $fields;
	}

	/**
	 * Generates import notes
	 *
	 * @since GeoDirectory Converter 1.0.0
	 */
	public function get_notes() {

		$notes	= array();
		
		$notes[]= esc_html__( "Don't forget to set up your default location before running this tool.", 'geodirectory-converter');

		//Inform the user that invoices won't be imported since the invoicing plugin is not active
		if ( !defined( 'WPINV_VERSION' ) ) {
			$url 	 = esc_url( 'https://wordpress.org/plugins/invoicing' );
			$notes[] = 	sprintf( 
				esc_html__( 'The Invoicing plugin is not active. Invoices will not be imported unless you %s install and activate the Invoicing plugin %s first.', 'geodirectory-converter'),
				"<a href='$url'>",
				'</a>'
			);
		}else{
			$notes[] = esc_html__( 'Setup your Invoicing details BEFORE runing the conversion so tax rates etc are set right.', 'geodirectory-converter');
		}

		//Inform the user that events won't be imported unless they activate the events addon
		if ( !defined( 'GEODIR_EVENT_VERSION' ) ) {
			$url 	 = esc_url( 'https://wpgeodirectory.com/downloads/events/' );
			$notes[] = 	sprintf( 
				esc_html__( 'The Events Addon is not active. Events will not be imported unless you %s install and activate the Events Addon %s first.', 'geodirectory-converter'),
				"<a href='$url'>",
				'</a>'
			);
		}

		$notes[] = esc_html__( 'You will be able to import your blog posts and pages at the end if you wish.', 'geodirectory-converter');

		return $notes;

	}

	/**
	 * Kickstarts the data import process
	 * @param $fields Form fields to display to the user
	 * @param $step The current import step
	 * @since GeoDirectory Converter 1.0.0
	 */
	public function step_3( $fields, $step ) {

		if( $step != 3 ){
			return $fields;
		}

		//Fetch database connection details...
		$db_config = get_transient( 'geodir_converter_pmd_db_details');

		//... and alert the user in case none is available
		if(! $db_config || ! is_array($db_config) ){
			$error = esc_html__('Your PhpMyDirectory database settings are missing. Please refresh the page and try again.', 'geodirectory-converter');
			GDCONVERTER_Loarder::send_response( 'error', $error );
		}

		//Try connecting to the db
		$this->db = new wpdb( $db_config['user'] ,$db_config['pass'] ,$db_config['db_name'] ,$db_config['host'] );

		//Set the PMD db prefix
		$this->prefix = $db_config['prefix'] ;

		//Set the PMD site url
		$this->url = $db_config['url'] ;

		//Then start the import process
		if(empty($_REQUEST['import_blog_data'])){
			$this->import_users();
		} else {
			$this->import_blog_categories();
		}
		

	}

	/**
	 * Convert PMD hours to GD hours.
	 *
	 * @param array $hours
	 * @param int $offset
	 *
	 * @return string
	 */
	private function convert_business_hours($hours=array(),$offset = 0){
		$new = "";
		//["Mo 09:00-17:00,19:00-23:00","Tu 09:00-17:00","We 09:00-17:00","Th 09:00-17:00","Fr 09:00-17:00"],["UTC":"+1"]
		$new_map = array(
			"1" => "Mo",
			"2" => "Tu",
			"3" => "We",
			"4" => "Th",
			"5" => "Fr",
			"6" => "Sa",
			"7" => "Su",
		);
		$new_parts = array();


		if(!empty($hours)){
			foreach($hours as $times){
				$time_parts = explode(" ",$times);
				$key = isset($time_parts[0]) ? $time_parts[0] : '';
				if($key){
					$map_key = $new_map[$key];
					if(!isset($new_parts[$map_key] )){
						$new_parts[$map_key] = $map_key." ".$time_parts[1]."-".$time_parts[2];
					}else{
						$new_parts[$map_key] .=",".$time_parts[1]."-".$time_parts[2];
					}
				}
			}
		}

		// construct
		if(!empty($new_parts)){
			$new .='["';
			$new .= implode('","', $new_parts);
			$new .='"]';
			$new .= ',["UTC":"'. $offset .'"]';
		}

		return $new;
	}


	/**
	 * Get the GD post images string from the PMD listing id.
	 *
	 * @param string $pmd_id
	 *
	 * @return string
	 */
	private function get_post_images($pmd_id = ''){
		global $wpdb;

		// Set doing import constant so that image paths are added
		if ( ! defined( 'GEODIR_DOING_IMPORT' ) ) {
			define( 'GEODIR_DOING_IMPORT', true );
		}

		$image_string = '';
		$image_array = array();
		$allowed_extensions = array('jpg','jpeg','gif','png','svg');
		$images_table 	= $this->prefix . 'images';
		$images 	= $this->db->get_results($wpdb->prepare("SELECT * FROM $images_table WHERE listing_id = %d",$pmd_id));

		if($images){
			foreach ( $images as $image ){
				if( empty( $image->id ) ){
					continue;
				}

				if(!in_array(strtolower($image->extension),$allowed_extensions)){
					continue;
				}

				// create a random key prefixed with the ordering so that we try to keep the image original ordering via the array keys.
				$key = (int) $image->ordering .wp_rand(100000,900000);

				// only text
				$image->title =  preg_replace("/[^A-Za-z0-9 ]/", '', $image->title);
				$image->description =  preg_replace("/[^A-Za-z0-9 ]/", '',  $image->description);

				$image_array[$key] = array(
					"url"   => trailingslashit( $this->url )."files/images/$image->id.$image->extension", // this will end up in the current upload directory folder
					"title" => wp_slash(esc_attr($image->title)),
					"caption" => wp_slash(esc_attr($image->description))
				);
			}
		}

		if(!empty($image_array)){
			foreach ($image_array as $img){
				if(!$image_string){
					$image_string .= $img['url']."||".$img['title']."|".$img['caption'];
				}else{
					$image_string .= "::".$img['url']."||".$img['title']."|".$img['caption'];
				}
			}
		}

		return $image_string;
	}

	/**
	 * Imports listings
	 *
	 * @since GeoDirectory Converter 1.0.0
	 */
	private function import_listings() {
		global $wpdb;
		global $geodirectory;

		$table 				= $this->prefix . 'listings';
		$posts_table 		= $wpdb->posts;
		$places_table		= geodir_db_cpt_table( 'gd_place' );
		$total 				= $this->db->get_var("SELECT COUNT(id) as count from $table");
		$form   			= '<h3>' . esc_html__('Importing listings', 'geodirectory-converter') . '</h3>';
		$progress 			= get_transient('_geodir_converter_pmd_progress');
		if(! $progress ){
			$progress = '';
		}
		$form = $progress . $form;

		//Abort early if there are no listings
		if( 0 == $total ){
			$form   .= $this->get_hidden_field_html( 'type', $this->get_next_import_type('listings'));
			$message = '<em>' . esc_html__('There are no listings in your PhpMyDirectory installation. Skipping...', 'geodirectory-converter') . '</em><br>';
			$form   .= $message;
			set_transient('_geodir_converter_pmd_progress', $progress . $message, DAY_IN_SECONDS);
			$this->update_progress( $form );
		}
		
		//Where should we start from
		$offset = 0;
		$limit  = 1;
		if(! empty($_REQUEST['offset']) ){
			$offset = absint($_REQUEST['offset']);
			$limit  = 3;
		}

		//Fetch the listings and abort in case we have imported all of them
		$listings_results 	= $this->db->get_results("SELECT * from $table LIMIT $offset,$limit");

		if( empty($listings_results) || ( $this->test_mode && $offset > 10 ) ){
			$form   .= $this->get_hidden_field_html( 'type', $this->get_next_import_type('listings'));
			$message = '<em>' . esc_html__('Finished importing listings...', 'geodirectory-converter') . '</em><br>';
			$form   .= $message;
			set_transient('_geodir_converter_pmd_progress', $progress . $message, DAY_IN_SECONDS);
			$this->update_progress( $form );
		}

		$imported = 0;
		if(! empty($_REQUEST['imported']) ){
			$imported = absint($_REQUEST['imported']);
		}

		$failed   = 0;
		if(! empty($_REQUEST['failed']) ){
			$failed = absint($_REQUEST['failed']);
		}

		//Re-enable cache additions
		wp_suspend_cache_addition(false);

		//Insert the listings into the db
		foreach ( $listings_results as $key => $listing ){
			$offset ++;

			//Skip if the id is not set
			if( empty( $listing->id ) ){
				$failed ++;
				continue;
			}

			//Sanitize listing status...
			$status  = ( !empty( $listing->status ) && 'active' == $listing->status )? 'publish': $listing->status;
			$status  = ( !empty( $listing->status ) && 'suspended' == $listing->status )? 'trash': $status;



			//Prepare the categories
			$sql  = $this->db->prepare("SELECT cat_id from {$table}_categories WHERE list_id = %d", $listing->id );
			$cats =  $this->db->get_col($sql);
			if(! is_array($cats) ){
				$cats = array( $listing->primary_category_id );
			}

			// get new cat ids
			$new_cats = array();
			if(!empty($cats)){
				foreach($cats as $cat){
					$new_cats[] = get_transient( '_pmd_place_category_original_id_' . $cat);
				}
			}

			//Primary category
			$primary_cat = get_transient( '_pmd_place_category_original_id_' . $listing->primary_category_id);

			//Insert the listing into the places_detail table
			$address = '';
			if( $listing->listing_address1 ){
				$address = $listing->listing_address1;
			}
			$address2 = '';
			if( $listing->listing_address2 ){
				$address2 = $listing->listing_address2;
			}

			//Set the default locations
			$default_location   = $geodirectory->location->get_default_location();
			$country    		= !empty( $default_location->country ) ? $default_location->country : '';
			$region     		= !empty( $default_location->region ) ? $default_location->region : '';
			$city       		= !empty( $default_location->city ) ? $default_location->city : '';
			$latitude   		= !empty( $default_location->latitude ) ? $default_location->latitude : '';
			$longitude  		= !empty( $default_location->longitude ) ? $default_location->longitude : '';


			// set listings with no GPS to draft
			if($status=='publish' && empty($latitude) ){
				$status='draft';
			}
			
			//... then insert it into the table

			$post_array = array(
				// Standard WP Fields
				'post_author'           => ( $listing->user_id )? $listing->user_id : 1,
				'post_content'          => ( $listing->description )? $listing->description : '',
				'post_content_filtered' => ( $listing->description )? $listing->description : '',
				'post_title'            => ( $listing->title )? $listing->title : 'NO TITLE',
				'post_excerpt'          => ( $listing->description_short )? $listing->description_short : '',
				'post_status'           => $status,
				'post_type'             => 'gd_place',
				'comment_status'        => 'open',
				'ping_status'           => 'closed',
				'post_name'				=> ( $listing->friendly_url )? $listing->friendly_url : 'listing-' . $listing->id,
				'post_date_gmt'         => ( $listing->date )? $listing->date : current_time( 'mysql', 1 ),
				'post_date'             => ( $listing->date )? $listing->date : current_time( 'mysql' ),
				'post_modified_gmt'     => ( $listing->date_update )? $listing->date_update : current_time( 'mysql', 1 ),
				'post_modified'         => ( $listing->date_update )? $listing->date_update : current_time( 'mysql' ),
				"tax_input"             => array(
					"gd_placecategory" => $new_cats,
					"gd_place_tags" => !empty( $listing->keywords ) ? array_map('trim', explode(',', $listing->keywords)) : array(),
				),


				// GD fields
				'default_category'  => !empty( $primary_cat )? $primary_cat : 0,
				'featured_image' 	=> '',
				'submit_ip' 		=> !empty( $listing->ip )? $listing->ip : '',
				'overall_rating' 	=> !empty( $listing->rating )? $listing->rating : 0,
				'rating_count' 		=> !empty( $listing->votes )? $listing->votes : 0,
				'street' 			=> $address,
				'street2' 			=> $address2,
				'city' 				=> !empty( $listing->location_text_1 )? $listing->location_text_1 : $city,
				'region' 			=> !empty( $listing->location_text_2 )? $listing->location_text_2 : $region,
				'country' 			=> $country, // @todo we need to add a not about setting the default location first.
				'zip' 				=> !empty( $listing->listing_zip )? $listing->listing_zip : '',
				'latitude' 			=> !empty( $listing->latitude )? $listing->latitude : $latitude,
				'longitude' 		=> !empty( $listing->longitude )? $listing->longitude : $longitude,
				'mapview' 			=> '',
				'mapzoom' 			=> '',

				// PMD standard fields
				'pmd_id' 			=> !empty( $listing->id )? $listing->id : '',
				'phone' 			=> !empty( $listing->phone )? $listing->phone : '',
				'fax' 				=> !empty( $listing->phone )? $listing->fax : '',
				'business_hours' 	=> !empty( $listing->hours ) && $listing->hours != "N;" ? $this->convert_business_hours( maybe_unserialize( $listing->hours ) ) : '',
				'website' 			=> !empty( $listing->www )? $listing->www : '',
				'email' 			=> !empty( $listing->mail )? $listing->mail : '',
				'claimed' 			=> !empty( $listing->claimed )? $listing->claimed : '',
				'facebook' 			=> !empty( $listing->facebook_page_id )? 'https://facebook.com/' . $listing->facebook_page_id : '',
				'google' 			=> !empty( $listing->google_page_id )? 'https://plus.google.com/' . $listing->google_page_id : '',
				'linkedin' 			=> !empty( $listing->linkedin_company_id )? 'https://linkedin.com/company/' . $listing->linkedin_company_id : '',
				'twitter' 			=> !empty( $listing->twitter_id )? 'https://twitter.com/' . $listing->twitter_id : '',
				'pinterest' 	    => !empty( $listing->pinterest_id )? 'https://pinterest.com/' . $listing->pinterest_id : '',
				'youtube' 			=> !empty( $listing->youtube_id )? 'https://youtube.com/user/' . $listing->youtube_id : '',
				'foursquare' 		=> !empty( $listing->foursquare_id )? 'https://foursquare.com/' . $listing->foursquare_id : '',
				'instagram'			=> !empty( $listing->instagram_id )? 'https://instagram.com/' . $listing->instagram_id : '',
				'featured' 			=> !empty( $listing->featured )? $listing->featured : '',
			);

			// add package id
			if(defined( 'GEODIR_PRICING_VERSION' )){
				$package_id = self::get_package_id($listing->id);
				if($package_id){
					$post_array['package_id'] = $package_id;
				}

			}

			// add custom fields
			$fields_table 	= $this->prefix . 'fields';
			$total_fields 	= $this->db->get_var("SELECT COUNT(id) AS count FROM $table");
			if($total_fields){
				$fields = $this->db->get_results("SELECT * from $fields_table");
				if($fields){
					foreach ( $fields as $key => $field ){
						if( empty( $field->id ) ){
							continue;
						}
						$field_key = "custom_".$field->id;
						$post_array['pmd_'.$field->name] = !empty( $listing->{$field_key} )? $listing->{$field_key}: '';
					}
				}
			}

			// add images
			$image_string = $this->get_post_images($listing->id);
			if($image_string){
				$post_array['post_images'] = $image_string;
			}

			// add logo if present
			if(!empty($listing->logo_extension)){
				$post_array['logo'] = trailingslashit( $this->url )."files/logo/".$listing->id.".".$listing->logo_extension."|||";
			}

			// add hero image if present
			if(!empty($listing->logo_background)){
				$post_array['hero'] = trailingslashit( $this->url )."files/logo/background/".$listing->id.".".$listing->logo_background."|||";
			}

			// insert post
			$id = wp_insert_post($post_array, true);

			//Save the original ID
			set_transient('_pmd_place_original_id_' . $listing->id, $id, DAY_IN_SECONDS);

			if( is_wp_error( $id ) ){
				$failed ++;
				continue;
			}

			$imported ++;
		}
		
		//Update the user on their progress
		$total_text  	 = esc_html__( 'Total Listings', 'geodirectory-converter' );
		$imported_text   = esc_html__( 'Imported Listings', 'geodirectory-converter' );
		$processed_text  = esc_html__( 'Processed Listings', 'geodirectory-converter' );
		$failed_text  	 = esc_html__( 'Failed', 'geodirectory-converter' );
		$form  			.= "<div><strong>$total_text &mdash;</strong><em> $total</em></div>";
		$form  			.= "<div><strong>$processed_text &mdash;</strong><em> $offset</em></div>";
		$form  			.= "<div><strong>$imported_text &mdash;</strong><em> $imported</em></div>";
		$form  			.= "<div><strong>$failed_text &mdash;</strong><em> $failed</em></div>";
		$form  			.= $this->get_hidden_field_html( 'imported', $imported);
		$form  			.= $this->get_hidden_field_html( 'failed', $failed);
		$form  			.= $this->get_hidden_field_html( 'type', 'listings');
		$form  			.= $this->get_hidden_field_html( 'offset', $offset);
		$this->update_progress( $form, $total, $offset );
	}

	/**
	 * Get the latest package ID for the listing.
	 *
	 * @param $listing_id
	 *
	 * @return mixed
	 */
	private function get_package_id($listing_id){
		global $wpdb;
		$table		= $this->prefix . 'orders';
		$package_id = '';
		$pmd_package_id = $this->db->get_var($wpdb->prepare("SELECT pricing_id FROM $table WHERE type_id = %d ORDER BY date DESC",$listing_id));

		if($pmd_package_id){
			$key = '_pmd_package_original_id_' . $pmd_package_id;
			$package_id = get_transient( $key );
		}

		return $package_id;
	}

	/**
	 * Imports users
	 *
	 * @since GeoDirectory Converter 1.0.0
	 */
	private function import_users() {

		global $wpdb;

		$table		= $this->prefix . 'users';
		$roles		= $this->prefix . 'users_groups_lookup';
		$total 		= $this->db->get_var("SELECT COUNT(id) as count from $table");
		$form   	= '<h3>' . esc_html__('Importing users', 'geodirectory-converter') . '</h3>';
		$progress 	= get_transient('_geodir_converter_pmd_progress');
		if(! $progress ){
			$progress = '';
		}
		$form   = $progress . $form;

		//Abort early if there are no users
		if( 0 == $total ){

			$form  		.= $this->get_hidden_field_html( 'type', $this->get_next_import_type('users'));
			$message	 = '<em>' . esc_html__('There are no users in your PhpMyDirectory installation. Skipping...', 'geodirectory-converter') . '</em><br>';
			set_transient('_geodir_converter_pmd_progress', $progress . $message, DAY_IN_SECONDS);
			$form 		.= $message;
			$this->update_progress( $form );

		}

		//Where should we start from
		$offset = 0;
		$limit  = 1;
		if(! empty($_REQUEST['offset']) ){
			$offset = absint($_REQUEST['offset']);
			$limit = 5;
		}

		//Fetch the users and abort in case we have imported all of them
		$pmd_users 	= $this->db->get_results("SELECT * from $table LIMIT $offset,$limit");
		if( empty($pmd_users) || ( $this->test_mode && $offset > 10 )  ){

			$form  .= $this->get_hidden_field_html( 'type', $this->get_next_import_type('users'));
			$message= '<em>' . esc_html__('Finished importing users...', 'geodirectory-converter') . '</em><br>';
			set_transient('_geodir_converter_pmd_progress', $progress . $message, DAY_IN_SECONDS);
			$form .= $message;
			$this->update_progress( $form );

		}

		$imported = 0;
		if(! empty($_REQUEST['imported']) ){
			$imported = absint($_REQUEST['imported']);
		}

		$failed   = 0;
		if(! empty($_REQUEST['failed']) ){
			$failed = absint($_REQUEST['failed']);
		}

		$current_user_id = get_current_user_id();

		if( empty( $pmd_users ) ){
			return;
		}

		foreach ( $pmd_users as $key => $user ){
			$offset++;

			//Abort if the user id or login is missing
			if( empty( $user->id ) || empty( $user->login ) ){
				$failed++;
				continue;
			}

			//Skip the user running this conversion to prevent logging him out
			if( $current_user_id == $user->id ){
				continue;
			}

			//Set the user display name
			$display_name = $user->login;
			if(! empty($user->user_first_name) ){
				$display_name = $user->user_first_name . ' ' . $user->user_last_name;
			}

			//The method below throws an error if a user with the given id exists, so let's delete them first
			$sql = $wpdb->prepare( "DELETE FROM `{$wpdb->users}` WHERE `{$wpdb->users}`.`ID` = %d", $user->id );
			$wpdb->query( $sql );

			//Since WP and PMD user different hashing algos, users will have to reset their passwords
			$wpdb->insert(
				$wpdb->users,
				array(
					'id' 				=> $user->id,
					'user_login' 		=> sanitize_user( $user->login ),
					'user_pass' 		=> ( $user->pass )? $user->pass : '',
					'user_nicename' 	=> sanitize_title( $user->login ),
					'user_email' 		=> ( $user->user_email ) ? sanitize_email( $user->user_email ) : '',
					'user_registered' 	=> ( $user->created )? $user->created : date('Y-m-d'),
					'display_name' 		=> sanitize_title( $display_name ),
				),
				array('%d','%s','%s','%s','%s','%s','%s' )
			);

			$_user = new WP_User( $user->id );
			$sql   = $wpdb->prepare( "SELECT `group_id` FROM `$roles` WHERE `user_id` = %d", $_user->ID );
			$level = absint( $this->db->get_var($sql) );

			//Set the user role
			switch($level){
			case 1:
        		$role = 'administrator';
        		break;
    		case 2:
				$role = 'editor';
        		break;
    		case 3:
				$role = 'author';
        		break;
    		default:
				$role = 'subscriber';
			}
			$_user->set_role( $role );

			//Update user meta
			update_user_meta( $_user->ID, 'first_name', $user->user_first_name );
			update_user_meta( $_user->ID, 'last_name', $user->user_last_name );
			update_user_meta( $_user->ID, 'pmd_password_hash', $user->password_hash );
			update_user_meta( $_user->ID, 'pmd_password_salt', $user->password_salt );
			update_user_meta( $_user->ID, 'user_organization', $user->user_organization );
			update_user_meta( $_user->ID, 'user_address1', $user->user_address1 );
			update_user_meta( $_user->ID, 'user_address2', $user->user_address2 );
			update_user_meta( $_user->ID, 'user_city', $user->user_city );
			update_user_meta( $_user->ID, 'user_state', $user->user_state );
			update_user_meta( $_user->ID, 'user_country', $user->user_country );
			update_user_meta( $_user->ID, 'user_zip', $user->user_zip );
			update_user_meta( $_user->ID, 'user_phone', $user->user_phone );

			$imported++;
		}

		//Update the user on their progress
		$total_text  	 = esc_html__( 'Total Users', 'geodirectory-converter' );
		$imported_text   = esc_html__( 'Imported Users', 'geodirectory-converter' );
		$processed_text  = esc_html__( 'Processed Users', 'geodirectory-converter' );
		$failed_text  	 = esc_html__( 'Failed', 'geodirectory-converter' );
		$form  			.= "<div><strong>$total_text &mdash;</strong><em> $total</em></div>";
		$form  			.= "<div><strong>$processed_text &mdash;</strong><em> $offset</em></div>";
		$form  			.= "<div><strong>$imported_text &mdash;</strong><em> $imported</em></div>";
		$form  			.= "<div><strong>$failed_text &mdash;</strong><em> $failed</em></div>";
		$form  			.= $this->get_hidden_field_html( 'imported', $imported);
		$form  			.= $this->get_hidden_field_html( 'failed', $failed);
		$form  			.= $this->get_hidden_field_html( 'type', 'users');
		$form  			.= $this->get_hidden_field_html( 'offset', $offset);
		$this->update_progress( $form, $total, $offset );

	}

	/**
	 * Handles logins for imported users
	 *
	 * @since GeoDirectory Converter 1.0.0
	 */
	public function handle_login( $user, $password ) {
		
		//abort early if the user has not been set
		if (! $user instanceof WP_User ) {
			return $user;
		}
		
		$login= false;

		//Get user's pmd hash and salt
		$hash = get_user_meta( $user->ID, 'pmd_password_hash' );
		$salt = get_user_meta( $user->ID, 'pmd_password_salt' );

		//If this is not a pmd user abort early
		if(empty($hash)){
			return $user;
		}
		
		//Not all users have salts
		if(!$salt){
			$salt = '';	 
		}
		
		//Check if the provided password matches the original pmd password
		if( 'md5' == $hash  ){
				if( md5( $password . $salt ) == $user->user_pass ){
					$login= true;
				} else if( md5( $salt . $password ) == $user->user_pass ){
					$login= true;
				}
		}

		if( 'sha256' == $hash  ){
			if( hash ( 'sha256' , $password . $salt ) == $user->user_pass ){
				$login= true;
			} else if( hash ( 'sha256' , $salt . $password ) == $user->user_pass ){
				$login= true;
			}
		}

		if( true == $login){
				$user->user_pass = wp_hash_password( $password );
				wp_set_password( $password, $user->ID );
				delete_user_meta( $user->ID, 'pmd_password_hash' );
				delete_user_meta( $user->ID, 'pmd_password_hash' );
		}

		return $user;
	}

	/**
	 * Imports categories
	 *
	 * @since GeoDirectory Converter 1.0.0
	 */
	private function import_categories() {
		global $wpdb;


		$table 				= $this->prefix . 'categories';
		$posts_table 		= $wpdb->posts;
		$places_table		= geodir_db_cpt_table( 'gd_place' );
		$total 				= $this->db->get_var("SELECT COUNT(id) as count from $table");
		$form   			= '<h3>' . esc_html__('Importing categories', 'geodirectory-converter') . '</h3>';
		$progress 			= get_transient('_geodir_converter_pmd_progress');
		if(! $progress ){
			$progress = '';
		}
		$form   = $progress . $form;
		
		//Abort early if there are no cats
		if( 0 == $total ){
			$form  .= $this->get_hidden_field_html( 'type', $this->get_next_import_type('categories'));
			$message= '<em>' . esc_html__('There are no categories in your PhpMyDirectory installation. Skipping...', 'geodirectory-converter') . '</em><br>';
			set_transient('_geodir_converter_pmd_progress', $progress . $message, DAY_IN_SECONDS);
			$form .= $message;
			$this->update_progress( $form );
		}

		
		//Where should we start from
		$offset = 0;
		$limit  = 1;
		if(! empty($_REQUEST['offset']) ){
			$offset = absint($_REQUEST['offset']);
			$limit  = 4;
		}

		//Fetch the listings and abort in case we have imported all of them
		$pmd_cats 	= $this->db->get_results("SELECT * from $table LIMIT $offset,$limit");
		if( empty($pmd_cats) || ( $this->test_mode && $offset > 10 ) ){
			$form  .= $this->get_hidden_field_html( 'type', $this->get_next_import_type('categories'));
			$message= '<em>' . esc_html__('Finished importing categories...', 'geodirectory-converter') . '</em><br>';
			set_transient('_geodir_converter_pmd_progress', $progress . $message, DAY_IN_SECONDS);
			$form .= $message;
			$this->update_progress( $form );
		}

		$imported = 0;
		if(! empty($_REQUEST['imported']) ){
			$imported = absint($_REQUEST['imported']);
		}

		$failed   = 0;
		if(! empty($_REQUEST['failed']) ){
			$failed = absint($_REQUEST['failed']);
		}

		foreach ( $pmd_cats as $key => $cat ){
			$offset++;

			if( empty( $cat->id ) ){
				$failed++;
				continue;
			}
			
			
			$args = array();

			//Maybe set slug
			if(!empty ( $cat->friendly_url ) ) {
				$args['slug'] = $cat->friendly_url;
			}

			//Maybe set parent
			if(!empty ( $cat->parent_id && $cat->parent_id > 1 ) ) {
				$parent = get_transient( '_pmd_place_category_original_id_' . $cat->parent_id );
				$args['parent'] = $parent;
			}

			//Maybe set description
			if(!empty ( $cat->description ) ) {
				$args['description'] = $cat->description;
			}

			
			//Insert it into the db
			$inserted = wp_insert_term( $cat->title, 'gd_placecategory', $args );

			//If insert was successful...
			if( is_array( $inserted )){

				//Save original id for later use...
				$key = '_pmd_place_category_original_id_' . $cat->id;
				set_transient( $key , $inserted['term_id'], DAY_IN_SECONDS );

				//Then maybe set the description
				if(! empty($cat->description) ){
					update_term_meta( $inserted['term_id'], 'ct_cat_top_desc', $cat->description );
				}

				// @todo we need to add script to import images, current site we are working with does not seem to have cat images

				//And move on to the next term
				$imported++;
				continue;
			}
			
			$failed++;
		}

		//Update the user on their progress
		$total_text  	 = esc_html__( 'Total Categories', 'geodirectory-converter' );
		$imported_text   = esc_html__( 'Imported Categories', 'geodirectory-converter' );
		$processed_text  = esc_html__( 'Processed Categories', 'geodirectory-converter' );
		$failed_text  	 = esc_html__( 'Failed', 'geodirectory-converter' );
		$form  			.= "<div><strong>$total_text &mdash;</strong><em> $total</em></div>";
		$form  			.= "<div><strong>$processed_text &mdash;</strong><em> $offset</em></div>";
		$form  			.= "<div><strong>$imported_text &mdash;</strong><em> $imported</em></div>";
		$form  			.= "<div><strong>$failed_text &mdash;</strong><em> $failed</em></div>";
		$form  			.= $this->get_hidden_field_html( 'imported', $imported);
		$form  			.= $this->get_hidden_field_html( 'failed', $failed);
		$form  			.= $this->get_hidden_field_html( 'type', 'categories');
		$form  			.= $this->get_hidden_field_html( 'offset', $offset);
		$this->update_progress( $form, $total, $offset );
	}

	/**
	 * Imports invoices
	 *
	 * @since GeoDirectory Converter 1.0.0
	 */
	private function import_invoices() {
		global $wpdb;

		$form		= '<h3>' . esc_html__('Importing invoices', 'geodirectory-converter') . '</h3>';
		$progress 	= get_transient('_geodir_converter_pmd_progress');
		if(! $progress ){
			$progress = '';
		}
		$form   = $progress . $form;

		//Abort early if the invoicing plugin is not installed
		if ( !defined( 'WPINV_VERSION' ) ) {
			$form  		.= $this->get_hidden_field_html( 'type', $this->get_next_import_type('invoices'));
			$message	 = '<em>' . esc_html__('The Invoicing plugin is not active. Skipping invoices...', 'geodirectory-converter') . '</em><br>';
			set_transient('_geodir_converter_pmd_progress', $progress . $message, DAY_IN_SECONDS);
			$form 		.= $message;
			$this->update_progress( $form );
		}

		$table 			= $this->prefix . 'invoices';
		$posts_table 	= $wpdb->posts;
		$total 			= $this->db->get_var("SELECT COUNT(id) as count from $table");
		
		//Abort early if there are no invoices
		if( 0 == $total ){
			$form  .= $this->get_hidden_field_html( 'type', $this->get_next_import_type('invoices'));
			$message= '<em>' . esc_html__('There are no invoices in your PhpMyDirectory installation. Skipping...', 'geodirectory-converter') . '</em><br>';
			set_transient('_geodir_converter_pmd_progress', $progress . $message, DAY_IN_SECONDS);
			$form .= $message;
			$this->update_progress( $form );
		}
		
		//Where should we start from
		$offset = 0;
		$limit  = 1;
		if(! empty($_REQUEST['offset']) ){
			$offset = absint($_REQUEST['offset']);
			$limit  = 5;
		}

		//Fetch the invoices and abort in case we have imported all of them
		$pmd_invoices 	= $this->db->get_results("SELECT * from $table LIMIT $offset,$limit");
		if( empty($pmd_invoices)){
			$form  .= $this->get_hidden_field_html( 'type', $this->get_next_import_type('invoices'));
			$message= '<em>' . esc_html__('Finished importing invoices...', 'geodirectory-converter') . '</em><br>';
			set_transient('_geodir_converter_pmd_progress', $progress . $message, DAY_IN_SECONDS);
			$form .= $message;
			$this->update_progress( $form );
		}

		$imported = 0;
		if(! empty($_REQUEST['imported']) ){
			$imported = absint($_REQUEST['imported']);
		}

		$failed   = 0;
		if(! empty($_REQUEST['failed']) ){
			$failed = absint($_REQUEST['failed']);
		}

		foreach ( $pmd_invoices as $key => $invoice ){

			$offset++;

			if( empty( $invoice->id ) ){
				$failed++;
				continue;
			}
			
			$status  = ( !empty( $invoice->status ) && 'unpaid' == $invoice->status )? 'wpi-pending': $invoice->status;
			$status  = ( !empty( $invoice->status ) && 'canceled' == $invoice->status )? 'wpi-cancelled': $status;
			$status  = ( !empty( $invoice->status ) && 'paid' == $invoice->status )? 'publish': $status;
			$excerpt = ( !empty( $invoice->description ) )? $invoice->description: '';
			
			$id = wp_insert_post( array(
				'post_author'           => isset( $invoice->user_id )? $invoice->user_id : 1,
				'post_content'          => isset( $invoice->description )? $invoice->description : '',
				'post_content_filtered' => isset( $invoice->description )? $invoice->description : '',
				'post_title'            => 'WPINV-00'.$invoice->id ,
				'post_name' 			=> 'inv-'.$invoice->id,
				'post_excerpt'          => '',
				'post_status'           => $status,
				'post_type'             => 'wpi_invoice',
				'comment_status'        => 'closed',
				'ping_status'           => 'closed',
				'post_date_gmt'         => isset( $invoice->date )? $invoice->date : '',
				'post_date'             => isset( $invoice->date )? $invoice->date : '',
				'post_modified_gmt'     => isset( $invoice->date_update )? $invoice->date_update : '',
				'post_modified'         => isset( $invoice->date_update )? $invoice->date_update : '',
			), true);

			if( is_wp_error( $id ) ){
				$failed++;
				continue;
			}

			if( $id ){
				update_post_meta( $id, '_wpinv_subtotal', $invoice->subtotal );
				update_post_meta( $id, '_wpinv_tax', $invoice->tax );
				update_post_meta( $id, '_wpinv_total', $invoice->total );
				update_post_meta( $id, '_wpinv_due_date', $invoice->date_due );
				update_post_meta( $id, '_wpinv_gateway', strtolower($invoice->gateway_id) );
				update_post_meta( $id, '_wpinv_completed_date', $invoice->date_paid );

				$sql 		= $wpdb->prepare( "SELECT * FROM `{$wpdb->usermeta}` WHERE user_id = %d", $invoice->user_id );
				$user_meta 	= $wpdb->query( $sql );

				if(! empty( $user_meta->first_name )){
					update_post_meta( $id, '_wpinv_first_name', $user_meta->first_name );
				}
				if(! empty( $user_meta->last_name )){
					update_post_meta( $id, '_wpinv_last_name', $user_meta->last_name );
				}
				if(! empty( $user_meta->user_country )){
					update_post_meta( $id, '_wpinv_country', $user_meta->user_country );
				}
				if(! empty( $user_meta->user_state )){
					update_post_meta( $id, '_wpinv_state', $user_meta->user_state );
				}
				if(! empty( $user_meta->user_city )){
					update_post_meta( $id, '_wpinv_city', $user_meta->user_city );
				}
				if(! empty( $user_meta->user_zip )){
					update_post_meta( $id, '_wpinv_zip', $user_meta->user_zip );
				}
				if(! empty( $user_meta->user_phone )){
					update_post_meta( $id, '_wpinv_phone', $user_meta->user_phone );
				}
				if(! empty( $user_meta->user_organization )){
					update_post_meta( $id, '_wpinv_company', $user_meta->user_organization );
				}
				if(! empty( $user_meta->user_address1 )){
					$address = $user_meta->user_address1;

					if(! empty( $user_meta->user_address2 ) ){
						$address .= " $user_meta->user_address2";
					}
					update_post_meta( $id, '_wpinv_address', $address );
				}
			}

			$imported++;
		}
		
		//Update the user on their progress
		$total_text  	 = esc_html__( 'Total Invoices', 'geodirectory-converter' );
		$imported_text   = esc_html__( 'Imported Invoices', 'geodirectory-converter' );
		$processed_text  = esc_html__( 'Processed Invoices', 'geodirectory-converter' );
		$failed_text  	 = esc_html__( 'Failed', 'geodirectory-converter' );
		$form  			.= "<div><strong>$total_text &mdash;</strong><em> $total</em></div>";
		$form  			.= "<div><strong>$processed_text &mdash;</strong><em> $offset</em></div>";
		$form  			.= "<div><strong>$imported_text &mdash;</strong><em> $imported</em></div>";
		$form  			.= "<div><strong>$failed_text &mdash;</strong><em> $failed</em></div>";
		$form  			.= $this->get_hidden_field_html( 'imported', $imported);
		$form  			.= $this->get_hidden_field_html( 'failed', $failed);
		$form  			.= $this->get_hidden_field_html( 'type', 'invoices');
		$form  			.= $this->get_hidden_field_html( 'offset', $offset);
		$this->update_progress( $form, $total, $offset );
	}

	/**
	 * Imports discount codes
	 *
	 * @since GeoDirectory Converter 1.0.0
	 */
	private function import_discounts() {
		global $wpdb;

		$form		= '<h3>' . esc_html__('Importing discount codes', 'geodirectory-converter') . '</h3>';
		$progress 	= get_transient('_geodir_converter_pmd_progress');
		if(! $progress ){
			$progress = '';
		}
		$form   = $progress . $form;

		//Abort early if the invoicing plugin is not installed
		if ( !defined( 'WPINV_VERSION' ) ) {
			$form  		.= $this->get_hidden_field_html( 'type', $this->get_next_import_type('discounts'));
			$message	 = '<em>' . esc_html__('The Invoicing plugin is not active. Skipping discount codes...', 'geodirectory-converter') . '</em><br>';
			set_transient('_geodir_converter_pmd_progress', $progress . $message, DAY_IN_SECONDS);
			$form 		.= $message;
			$this->update_progress( $form );
		}

		$table 			= $this->prefix . 'discount_codes';
		$posts_table 	= $wpdb->posts;
		$total 			= $this->db->get_var("SELECT COUNT(id) as count from $table");
		
		//Abort early if there are no discounts
		if( 0 == $total ){
			$form  .= $this->get_hidden_field_html( 'type', $this->get_next_import_type('discounts'));
			$message= '<em>' . esc_html__('There are no discount codes in your PhpMyDirectory installation. Skipping...', 'geodirectory-converter') . '</em><br>';
			set_transient('_geodir_converter_pmd_progress', $progress . $message, DAY_IN_SECONDS);
			$form .= $message;
			$this->update_progress( $form );
		}
		
		//Where should we start from
		$offset = 0;
		$limit  = 1;
		if(! empty($_REQUEST['offset']) ){
			$offset = absint($_REQUEST['offset']);
			$limit = 5;
		}

		//Fetch the discounts and abort in case we have imported all of them
		$pmd_discounts 	= $this->db->get_results("SELECT * from $table LIMIT $offset,$limit");
		if( empty($pmd_discounts)){
			$form  .= $this->get_hidden_field_html( 'type', $this->get_next_import_type('discounts'));
			$message= '<em>' . esc_html__('Finished importing discount codes...', 'geodirectory-converter') . '</em><br>';
			set_transient('_geodir_converter_pmd_progress', $progress . $message, DAY_IN_SECONDS);
			$form .= $message;
			$this->update_progress( $form );
		}

		$imported = 0;
		if(! empty($_REQUEST['imported']) ){
			$imported = absint($_REQUEST['imported']);
		}

		$failed   = 0;
		if(! empty($_REQUEST['failed']) ){
			$failed = absint($_REQUEST['failed']);
		}

		foreach ( $pmd_discounts as $key => $discount ){

			$offset++;

			if( empty( $discount->id ) || empty( $discount->code ) ){
				$failed++;
				continue;
			}
			
			$id = wp_insert_post( array(
				'post_name' 			=> $discount->id,
				'post_title' 			=> ( $discount->title ) ? $discount->title : '',
				'post_status'           => 'publish',
				'post_type'             => 'wpi_discount',
				'comment_status'        => 'closed',
				'ping_status'           => 'closed',
			), true);

			$discount_types = array(
				'percent'   => __( 'Percentage', 'invoicing' ),
				'flat'     => __( 'Flat Amount', 'invoicing' ),
			);

			if( is_wp_error( $id ) ){
				$failed++;
				continue;
			}

			$post = get_post($id);
			$data = array(
				'code'              => $discount->code,
				'type'              => ( $discount->discount_type == 'percentage' )    ?   'percent'  : 'flat',
				'amount'            => (int) $discount->value,
				'start'             => $discount->date_start,
				'expiration'        => $discount->date_expire,
				'max_uses'          => $discount->used_limit,
				'items'             => ( $discount->pricing_ids ) ?  explode(',', $discount->pricing_ids)   : array(),
				'is_recurring'      => ( $discount->type == 'onetime' )    ?   false  : true,
				'uses'              => $discount->used,
			);
			wpinv_store_discount( $id, $data, $post );
			update_post_meta($id, '_pmd_original_id', $discount->id);
			$imported++;
		}
		
		//Update the user on their progress
		$total_text  	 = esc_html__( 'Total Codes', 'geodirectory-converter' );
		$imported_text   = esc_html__( 'Imported Codes', 'geodirectory-converter' );
		$processed_text  = esc_html__( 'Processed Codes', 'geodirectory-converter' );
		$failed_text  	 = esc_html__( 'Failed', 'geodirectory-converter' );
		$form  			.= "<div><strong>$total_text &mdash;</strong><em> $total</em></div>";
		$form  			.= "<div><strong>$processed_text &mdash;</strong><em> $offset</em></div>";
		$form  			.= "<div><strong>$imported_text &mdash;</strong><em> $imported</em></div>";
		$form  			.= "<div><strong>$failed_text &mdash;</strong><em> $failed</em></div>";
		$form  			.= $this->get_hidden_field_html( 'imported', $imported);
		$form  			.= $this->get_hidden_field_html( 'failed', $failed);
		$form  			.= $this->get_hidden_field_html( 'type', 'discounts');
		$form  			.= $this->get_hidden_field_html( 'offset', $offset);
		$this->update_progress( $form, $total, $offset );
	}

	/**
	 * Imports products
	 *
	 * @since GeoDirectory Converter 1.0.0
	 */
	private function import_products() {
		global $wpdb;

		$form		= '<h3>' . esc_html__('Importing products', 'geodirectory-converter') . '</h3>';
		$progress 	= get_transient('_geodir_converter_pmd_progress');
		if(! $progress ){
			$progress = '';
		}
		$form   = $progress . $form;

		//Abort early if the payment manager plugin is not installed
		if ( !class_exists( 'GeoDir_Pricing_Package' ) ) {
			$form  		.= $this->get_hidden_field_html( 'type', $this->get_next_import_type('products'));
			$message	 = '<em>' . esc_html__('The Payment Manager Addon is not active. Skipping products...', 'geodirectory-converter') . '</em><br>';
			set_transient('_geodir_converter_pmd_progress', $progress . $message, DAY_IN_SECONDS);
			$form 		.= $message;
			$this->update_progress( $form );
		}

		$table 			= $this->prefix . 'products';
		$posts_table 	= $wpdb->posts;
		$total 			= $this->db->get_var("SELECT COUNT(id) as count from $table");
	
		//Abort early if there are no discounts
		if( 0 == $total ){
			$form  .= $this->get_hidden_field_html( 'type', $this->get_next_import_type('products'));
			$message= '<em>' . esc_html__('There are no products in your PhpMyDirectory installation. Skipping...', 'geodirectory-converter') . '</em><br>';
			set_transient('_geodir_converter_pmd_progress', $progress . $message, DAY_IN_SECONDS);
			$form .= $message;
			$this->update_progress( $form );
		}
		
		//Where should we start from
		$offset = 0;
		$limit  = 1;
		if(! empty($_REQUEST['offset']) ){
			$offset = absint($_REQUEST['offset']);
			$limit  = 5;
		}

		//Fetch the products and abort in case we have imported all of them
		$pricing_table  = $table . '_pricing';
		$pmd_products 	= $this->db->get_results("SELECT `$pricing_table`.`id` as package_id, `$pricing_table`.`overdue_pricing_id`, `$pricing_table`.`ordering`, `$table`.`id` as product_id, `name`, `$table`.`active`, `description`, `period`, `period_count`, `setup_price`, `price`, `renewable` FROM `$table` LEFT JOIN `$pricing_table` ON `$table`.`id` = `$pricing_table`.`product_id` LIMIT $offset,$limit");
			
		if( empty($pmd_products)){
			$form  .= $this->get_hidden_field_html( 'type', $this->get_next_import_type('products'));
			$message= '<em>' . esc_html__('Finished importing products...', 'geodirectory-converter') . '</em><br>';
			set_transient('_geodir_converter_pmd_progress', $progress . $message, DAY_IN_SECONDS);
			$form .= $message;
			$this->update_progress( $form );
		}

		$imported = 0;
		if(! empty($_REQUEST['imported']) ){
			$imported = absint($_REQUEST['imported']);
		}

		$failed   = 0;
		if(! empty($_REQUEST['failed']) ){
			$failed = absint($_REQUEST['failed']);
		}

		foreach ( $pmd_products as $key => $product ){

			$offset++;

			if( empty( $product->product_id ) ){
				$failed++;
				continue;
			}


			$data = array(
				'post_type' => 'gd_place',
				'name' => $product->name,
				'title' => $product->name,
				'description' => $product->description,
				'fa_icon' => '',
				'amount' => $product->price,
				'time_interval' => $product->period_count ? absint($product->period_count) : 0,
				'time_unit' => ( $product->period ) ? strtoupper( substr( $product->period, 0, 1) ) : 'M',
				'recurring' => $product->renewable,
				'recurring_limit' =>  0,
				'trial' => '',
				'trial_amount' => '',
				'trial_interval' => '',
				'trial_unit' => '',
				'is_default' => 0,
				'display_order' => $product->ordering,
				'downgrade_pkg' => $product->overdue_pricing_id ? absint($product->overdue_pricing_id) : 0, //@todo this will be old ID, we will need to update these after all inserted
				'post_status' => 'pending',
				'status' => $product->active ? 1 : 0,
			);

			$data = GeoDir_Pricing_Package::prepare_data_for_save( $data );

			$package_id = GeoDir_Pricing_Package::insert_package( $data );

			if( $package_id ){
				$key = '_pmd_package_original_id_' . $product->package_id;
				set_transient( $key , $package_id, DAY_IN_SECONDS );
				$imported++;
			} else {
				$failed++;
			}
			
			
		}


		
		//Update the user on their progress
		$total_text  	 = esc_html__( 'Total Products', 'geodirectory-converter' );
		$imported_text   = esc_html__( 'Imported Products', 'geodirectory-converter' );
		$processed_text  = esc_html__( 'Processed Products', 'geodirectory-converter' );
		$failed_text  	 = esc_html__( 'Failed', 'geodirectory-converter' );
		$form  			.= "<div><strong>$total_text &mdash;</strong><em> $total</em></div>";
		$form  			.= "<div><strong>$processed_text &mdash;</strong><em> $offset</em></div>";
		$form  			.= "<div><strong>$imported_text &mdash;</strong><em> $imported</em></div>";
		$form  			.= "<div><strong>$failed_text &mdash;</strong><em> $failed</em></div>";
		$form  			.= $this->get_hidden_field_html( 'imported', $imported);
		$form  			.= $this->get_hidden_field_html( 'failed', $failed);
		$form  			.= $this->get_hidden_field_html( 'type', 'products');
		$form  			.= $this->get_hidden_field_html( 'offset', $offset);
		$this->update_progress( $form, $total, $offset );
	}



	/**
	 * Imports reviews
	 *
	 * @since GeoDirectory Converter 1.0.0
	 */
	private function import_reviews() {
		global $wpdb;

		$table 			= $this->prefix . 'reviews';
		$users_table	= $this->prefix . 'users';
		$ratings_table	= $this->prefix . 'ratings';
		$total 			= $this->db->get_var("SELECT COUNT(id) as count from $table");
		$form   		= '<h3>' . esc_html__('Importing reviews', 'geodirectory-converter') . '</h3>';
		$progress 		= get_transient('_geodir_converter_pmd_progress');
		if(! $progress ){
			$progress = '';
		}
		$form   = $progress . $form;
		
		//Abort early if there are no reviews
		if( 0 == $total ){
			$form  .= $this->get_hidden_field_html( 'type', $this->get_next_import_type('reviews'));
			$message= '<em>' . esc_html__('There are no reviews in your PhpMyDirectory installation. Skipping...', 'geodirectory-converter') . '</em><br>';
			set_transient('_geodir_converter_pmd_progress', $progress . $message, DAY_IN_SECONDS);
			$form .= $message;
			$this->update_progress( $form );
		}
		
		//Where should we start from
		$offset = 0;
		$limit  = 1;
		if(! empty($_REQUEST['offset']) ){
			$offset = absint($_REQUEST['offset']);
			$limit  = 5;
		}

		//Fetch the reviews and abort in case we have imported all of them
		$pmd_reviews   = $this->db->get_results(
			"SELECT `$table`.`id` as `review_id`, `status`, `listing_id`, `user_id`, `date`, `review`, `user_first_name`, `user_last_name`, `user_email`, `rating_id` 
			FROM `$table` LEFT JOIN `$users_table` ON `$table`.`user_id` = `$users_table`.`id`  LIMIT $offset,$limit");

		if( empty($pmd_reviews) || ( $this->test_mode && $offset > 10 ) ){
			$form  .= $this->get_hidden_field_html( 'type', $this->get_next_import_type('reviews'));
			$message= '<em>' . esc_html__('Finished importing reviews...', 'geodirectory-converter') . '</em><br>';
			set_transient('_geodir_converter_pmd_progress', $progress . $message, DAY_IN_SECONDS);
			$form .= $message;
			$this->update_progress( $form );
		}

		$imported = 0;
		if(! empty($_REQUEST['imported']) ){
			$imported = absint($_REQUEST['imported']);
		}

		$failed   = 0;
		if(! empty($_REQUEST['failed']) ){
			$failed = absint($_REQUEST['failed']);
		}

		foreach ( $pmd_reviews as $key => $review ){

			$offset++;

			if( empty( $review->review_id ) ){
				$failed++;
				continue;
			}

			$approved = 0;
			if( 'active' == $review->status ){
				$approved = 1;
			}

			$place_id = get_transient('_pmd_place_original_id_' . $review->listing_id);

			// set the rating value if set
			unset($_REQUEST['geodir_overallrating']);
			$review_id = !empty($review->rating_id) ? absint($review->rating_id) : '';
			$rating ='';
			if($review_id){
				$rating = $this->db->get_var("SELECT rating FROM $ratings_table WHERE id = $review_id");
				if($rating){
					$_REQUEST['geodir_overallrating'] = absint($rating);
				}
			}

			$id = wp_insert_comment( array(
				'comment_post_ID' 		=> $place_id,
				'user_id' 				=> $review->user_id,
				'comment_date' 			=> $review->date,
				'comment_date_gmt' 		=> $review->date,
				'comment_content' 		=> $review->review,
				'comment_author' 		=> $review->user_first_name . ' ' . $review->user_last_name,
				'comment_author_email'	=> $review->user_email,
				'comment_agent'			=> 'geodir-converter',
				'comment_approved'		=> $approved,
			));
			
			if(! $id ){
				$failed++;
			} else {

				// insert the review score if set
				if($rating){
					GeoDir_Comments::save_rating( $id );
				}

				$imported++;
			}
		}

		//Update the user on their progress
		$total_text  	 = esc_html__( 'Total Reviews', 'geodirectory-converter' );
		$imported_text   = esc_html__( 'Imported Reviews', 'geodirectory-converter' );
		$processed_text  = esc_html__( 'Processed Reviews', 'geodirectory-converter' );
		$failed_text  	 = esc_html__( 'Failed', 'geodirectory-converter' );
		$form  			.= "<div><strong>$total_text &mdash;</strong><em> $total</em></div>";
		$form  			.= "<div><strong>$processed_text &mdash;</strong><em> $offset</em></div>";
		$form  			.= "<div><strong>$imported_text &mdash;</strong><em> $imported</em></div>";
		$form  			.= "<div><strong>$failed_text &mdash;</strong><em> $failed</em></div>";
		$form  			.= $this->get_hidden_field_html( 'imported', $imported);
		$form  			.= $this->get_hidden_field_html( 'failed', $failed);
		$form  			.= $this->get_hidden_field_html( 'type', 'reviews');
		$form  			.= $this->get_hidden_field_html( 'offset', $offset);
		$this->update_progress( $form, $total, $offset );
	}

	/**
	 * Imports events
	 *
	 * @since GeoDirectory Converter 1.0.0
	 */
	private function import_events() {
		global $wpdb;
		global $geodirectory;

		$form		= '<h3>' . esc_html__('Importing events', 'geodirectory-converter') . '</h3>';
		$progress 	= get_transient('_geodir_converter_pmd_progress');
		if(! $progress ){
			$progress = '';
		}
		$form   = $progress . $form;

		//Abort early if the events addon is not installed
		if ( !defined( 'GEODIR_EVENT_VERSION' ) ) {
			$form  .= $this->get_hidden_field_html( 'type', $this->get_next_import_type('events'));
			$message = '<em>' . esc_html__('The events addon is not active. Skipping events...', 'geodirectory-converter') . '</em><br>';
			set_transient('_geodir_converter_pmd_progress', $progress . $message, DAY_IN_SECONDS);
			$form .= $message;
			$this->update_progress( $form );
		}

		$table 				= $this->prefix . 'events';
		$listings_table		= $this->prefix . 'listings';
		$total 				= $this->db->get_var("SELECT COUNT(id) as count from $table");
		
		//Abort early if there are no events
		if( 0 == $total ){
			$form  .= $this->get_hidden_field_html( 'type', $this->get_next_import_type('events'));
			$message = '<em>' . esc_html__('There are no events in your PhpMyDirectory installation. Skipping...', 'geodirectory-converter') . '</em><br>';
			set_transient('_geodir_converter_pmd_progress', $progress . $message, DAY_IN_SECONDS);
			$form .= $message;
			$this->update_progress( $form );
		}
		
		//Where should we start from
		$offset = 0;
		$limit  = 1;
		if(! empty($_REQUEST['offset']) ){
			$offset = absint($_REQUEST['offset']);
			$limit  = 5;
		} else {
			//No events have been imported yet
			$this->import_standard_fields( 'gd_event' );
		}

		//Fetch the events and abort in case we have imported all of them
		$listings_fields = array(
			'facebook_page_id', 'twitter_id', 'ip', 'location_text_1', 'location_text_2',
			'location_text_3', 'listing_zip', 'listing_address1', 'listing_address2', 
			'www', 'mail'
		);

		$sql = "SELECT $table.*, ";

		foreach( $listings_fields as $field){
			$sql .= " `$listings_table`.`$field` as `$field`, ";
		}

		$sql 		  = rtrim($sql, ', ');
		$sql 		 .= "  FROM `$table` LEFT JOIN `$listings_table` ON `$table`.`listing_id` = `$listings_table`.`id`  LIMIT $offset,$limit ";
		$pmd_events   = $this->db->get_results( $sql );

		if( empty($pmd_events) || ( $this->test_mode && $offset > 10 ) ){
			$form  .= $this->get_hidden_field_html( 'type', $this->get_next_import_type('events'));
			$message= '<em>' . esc_html__('Finished importing events...', 'geodirectory-converter') . '</em><br>';
			set_transient('_geodir_converter_pmd_progress', $progress . $message, DAY_IN_SECONDS);
			$form .= $message;
			$this->update_progress( $form );
		}

		$imported = 0;
		if(! empty($_REQUEST['imported']) ){
			$imported = absint($_REQUEST['imported']);
		}

		$failed   = 0;
		if(! empty($_REQUEST['failed']) ){
			$failed = absint($_REQUEST['failed']);
		}

		$events_table	= geodir_db_cpt_table( 'gd_event' );

		foreach ( $pmd_events as $key => $event ){
			$offset++;

			//Abort early if id not set
			if( empty( $event->id ) ){
				$failed++;
				continue;
			}
			
			//Sanitize listing status...
			$status  = ( !empty( $event->status ) && 'active' == $event->status )? 'publish': $event->status;
			$status  = ( !empty( $event->status ) && 'suspended' == $event->status )? 'trash': $status;
			
			//Fetch the categories
			$sql  = $this->db->prepare("SELECT category_id from {$table}_categories_lookup WHERE event_id = %d", $event->id );
			$cats =  $this->db->get_col($sql);

			//Then replace the ids with new ids
			$new_cats = array();
			if(!empty($cats)){
				foreach($cats as $cat){
					$new_cats[] = get_transient( '_pmd_event_category_original_id_' . $cat);
				}
			}

			//Set the default locations
			$default_location   = $geodirectory->location->get_default_location();
			$country    		= !empty( $default_location->country ) ? $default_location->country : '';
			$region     		= !empty( $default_location->region ) ? $default_location->region : '';
			$city       		= !empty( $default_location->city ) ? $default_location->city : '';
			$latitude   		= !empty( $default_location->latitude ) ? $default_location->latitude : '';
			$longitude  		= !empty( $default_location->longitude ) ? $default_location->longitude : '';


			//Prepare Event data
			$post_array = array(

				// Standard WP Fields
				'post_author'           => !empty( $event->user_id )? $event->user_id : 1,
				'post_content'          => !empty( $event->description )? $event->description : '',
				'post_content_filtered' => !empty( $event->description )? $event->description : '',
				'post_title'            => !empty( $event->title )? $event->title : '&mdash;',
				'post_excerpt'          => !empty( $event->description_short )? $event->description_short : '',
				'post_status'           => $status,
				'post_type'             => 'gd_event',
				'comment_status'        => 'open',
				'ping_status'           => 'closed',
				'post_name'				=> !empty( $event->friendly_url )? $event->friendly_url : 'event-' . $event->id,
				'post_date_gmt'         => !empty( $event->date )? $event->date : current_time( 'mysql', 1 ),
				'post_date'             => !empty( $event->date )? $event->date : current_time( 'mysql' ),
				'post_modified_gmt'     => !empty( $event->date_update )? $event->date_update : current_time( 'mysql', 1 ),
				'post_modified'         => !empty( $event->date_update )? $event->date_update : current_time( 'mysql' ),
				"tax_input"             => array(
					"gd_eventcategory"  => $new_cats,
					"gd_event_tags"     => !empty( $event->keywords ) ? array_map('trim', explode(',', $event->keywords)) : array(),
				),


				// GD fields
				'featured_image' 	=> '',
				'submit_ip' 		=> !empty( $event->ip )? $event->ip : '',
				'street' 			=> !empty( $event->listing_address1 )? $event->listing_address1 : '',
				'street2' 			=> !empty( $event->listing_address2)? $event->listing_address2 : '',
				'city' 				=> !empty( $event->location_text_2 )? $event->location_text_2 : $city,
				'region' 			=> !empty( $event->location_text_3 )? $event->location_text_3 : $region,
				'country' 			=> $country,
				'zip' 				=> !empty( $event->listing_zip )? $event->listing_zip : '',
				'latitude' 			=> !empty( $event->latitude )? $event->latitude : $latitude,
				'longitude' 		=> !empty( $event->longitude )? $event->longitude : $longitude,
				'mapview' 			=> '',
				'mapzoom' 			=> '',
				'recurring' 		=> $event->recurring,

				// PMD standard fields
				'pmd_id' 			=> !empty( $event->id )? $event->id : '',
				'phone' 			=> !empty( $event->phone )? $event->phone : '',
				'website' 			=> !empty( $event->website )? $event->website : '',
				'email' 			=> !empty( $event->email )? $event->email : '',
				'venue' 			=> !empty( $event->venue )? $event->venue : '',
				'location' 			=> !empty( $event->location )? $event->location : '',
				'admission' 		=> !empty( $event->admission )? $event->admission : '',
				'contact_name' 		=> !empty( $event->contact_name )? $event->contact_name : '',
			);

			//Sanitize event dates
			switch( $event->recurring_type ){
				case 'daily':
        			$repeat_type = 'day';
        			break;
    			case 'weekly':
					$repeat_type = 'week';
        			break;
    			case 'monthly':
					$repeat_type = 'month';
					break;
				case 'yearly':
					$repeat_type = 'year';
        			break;
    			default:
					$repeat_type = 'custom';
			}

			$post_array[ 'event_dates' ] = array(
				'recurring' 		=> $event->recurring,
				'start_date' 		=> date( "Y-m-d", strtotime( $event->date_start  ) ),
				'end_date' 			=> date( "Y-m-d", strtotime( $event->date_end  ) ),
				'all_day' 			=> 0,
				'start_time' 		=> date( 'g:i a', strtotime( $event->date_start  ) ),
				'end_time' 			=> date( 'g:i a', strtotime( $event->date_end  ) ),
				'duration_x' 		=> '',
				'repeat_type' 		=> $repeat_type,
				'repeat_x' 			=> $event->recurring_interval,
				'repeat_end_type' 	=> '',
				'max_repeat' 		=> '',
				'recurring_dates' 	=> '',
				'different_times' 	=> '',
				'start_times' 		=> '',
				'end_times' 		=> '',
				'repeat_days' 		=> $event->recurring_days,	
				'repeat_weeks' 		=> '',
			);

			// add images
			$image_string = $this->get_post_images($event->id);
			if($image_string){
				$post_array['post_images'] = $image_string;
			}

			$id = wp_insert_post($post_array, true);

			if( is_wp_error( $id ) ){
				$failed++;
				continue;
			}

			$imported++;
		}

		//Update the user on their progress
		$total_text  	= esc_html__( 'Total Events', 'geodirectory-converter' );
		$imported_text  = esc_html__( 'Imported Events', 'geodirectory-converter' );
		$processed_text = esc_html__( 'Processed Events', 'geodirectory-converter' );
		$failed_text  	= esc_html__( 'Failed Events', 'geodirectory-converter' );
		$form  		   .= "<div><strong>$total_text &mdash;</strong><em> $total</em></div>";
		$form          .= "<div><strong>$processed_text &mdash;</strong><em> $offset</em></div>";
		$form          .= "<div><strong>$imported_text &mdash;</strong><em> $imported</em></div>";
		$form          .= "<div><strong>$failed_text &mdash;</strong><em> $failed</em></div>";
		$form          .= $this->get_hidden_field_html( 'imported', $imported);
		$form          .= $this->get_hidden_field_html( 'failed', $failed);
		$form          .= $this->get_hidden_field_html( 'type', 'events');
		$form          .= $this->get_hidden_field_html( 'offset', $offset);
		$this->update_progress( $form, $total, $offset );
	}

	/**
	 * Imports event categories
	 *
	 * @since GeoDirectory Converter 1.0.0
	 */
	private function import_event_categories() {
		global $wpdb;

		$form	= '<h3>' . esc_html__('Importing event categories', 'geodirectory-converter') . '</h3>';
		$progress 	= get_transient('_geodir_converter_pmd_progress');
		if(! $progress ){
			$progress = '';
		}
		$form   = $progress . $form;

		//Abort early if the events addon is not installed
		if ( !defined( 'GEODIR_EVENT_VERSION' ) ) {
			$form  .= $this->get_hidden_field_html( 'type', $this->get_next_import_type('event_categories'));
			$message = '<em>' . esc_html__('The events addon is not active. Skipping event categories...', 'geodirectory-converter') . '</em><br>';
			set_transient('_geodir_converter_pmd_progress', $progress . $message, DAY_IN_SECONDS);
			$form .= $message;
			$this->update_progress( $form );
		}

		$table 	= $this->prefix . 'events_categories';
		$total 	= $this->db->get_var("SELECT COUNT(id) as count from $table");
		
		//Abort early if there are no events
		if( 0 == $total ){
			$form  .= $this->get_hidden_field_html( 'type', $this->get_next_import_type('event_categories'));
			$message = '<em>' . esc_html__('There are no event categories in your PhpMyDirectory installation. Skipping...', 'geodirectory-converter') . '</em><br>';
			set_transient('_geodir_converter_pmd_progress', $progress . $message, DAY_IN_SECONDS);
			$form .= $message;
			$this->update_progress( $form );
		}
		
		//Where should we start from
		$offset = 0;
		$limit  = 1;
		if(! empty($_REQUEST['offset']) ){
			$offset = absint($_REQUEST['offset']);
			$limit  = 10;
		}

		$cats   = $this->db->get_results( "SELECT id, title FROM `$table` LIMIT $offset,$limit" );
		
		if( empty($cats) || ( $this->test_mode && $offset > 10) ){
			$form  .= $this->get_hidden_field_html( 'type', $this->get_next_import_type('event_categories'));
			$message= '<em>' . esc_html__('Finished importing event categories...', 'geodirectory-converter') . '</em><br>';
			set_transient('_geodir_converter_pmd_progress', $progress . $message, DAY_IN_SECONDS);
			$form .= $message;
			$this->update_progress( $form );
		}

		$imported = 0;
		if(! empty($_REQUEST['imported']) ){
			$imported = absint($_REQUEST['imported']);
		}

		$failed   = 0;
		if(! empty($_REQUEST['failed']) ){
			$failed = absint($_REQUEST['failed']);
		}

		foreach ( $cats as $cat ){
			$offset++;

			if( empty( $cat->id ) ){
				$failed++;
				continue;
			}

			$inserted = wp_insert_term( $cat->title, 'gd_eventcategory' );
			if( is_array( $inserted )){
				$key = '_pmd_event_category_original_id_' . $cat->id;
				set_transient( $key , $inserted['term_id'], DAY_IN_SECONDS );
				$imported++;
				continue;
			}
			
			$failed++;
		}

		//Update the user on their progress
		$total_text  	= esc_html__( 'Total Event Categories', 'geodirectory-converter' );
		$imported_text  = esc_html__( 'Imported Event Categories', 'geodirectory-converter' );
		$processed_text = esc_html__( 'Processed Event Categories', 'geodirectory-converter' );
		$failed_text  	= esc_html__( 'Failed', 'geodirectory-converter' );
		$form  		   .= "<div><strong>$total_text &mdash;</strong><em> $total</em></div>";
		$form          .= "<div><strong>$processed_text &mdash;</strong><em> $offset</em></div>";
		$form          .= "<div><strong>$imported_text &mdash;</strong><em> $imported</em></div>";
		$form          .= "<div><strong>$failed_text &mdash;</strong><em> $failed</em></div>";
		$form          .= $this->get_hidden_field_html( 'imported', $imported);
		$form          .= $this->get_hidden_field_html( 'failed', $failed);
		$form          .= $this->get_hidden_field_html( 'type', 'event_categories');
		$form          .= $this->get_hidden_field_html( 'offset', $offset);
		$this->update_progress( $form, $total, $offset );
	}

	/**
	 * Imports blog categories
	 *
	 * @since GeoDirectory Converter 1.0.0
	 */
	private function import_blog_categories() {
		global $wpdb;

		$form		= '<h3>' . esc_html__('Importing blog categories', 'geodirectory-converter') . '</h3>';
		$progress 	= get_transient('_geodir_converter_pmd_progress');
		if(! $progress ){
			$progress = '';
		}
		$form   = $progress . $form;
		$table  = $this->prefix . 'blog_categories';
		$total  = $this->db->get_var("SELECT COUNT(id) as count from $table");
		
		//Abort early if there are no categories
		if( 0 == $total ){
			$form  .= $this->get_hidden_field_html( 'type', $this->get_next_import_type('blog_categories'));
			$message = '<em>' . esc_html__('There are no blog categories in your PhpMyDirectory installation. Skipping...', 'geodirectory-converter') . '</em><br>';
			set_transient('_geodir_converter_pmd_progress', $progress . $message, DAY_IN_SECONDS);
			$form .= $message;
			$this->update_progress( $form );
		}
		
		//Where should we start from
		$offset = 0;
		$limit  = 1;
		if(! empty($_REQUEST['offset']) ){
			$offset = absint($_REQUEST['offset']);
			$limit  = 10;
		}

		$cats   = $this->db->get_results( "SELECT id, title FROM `$table` LIMIT $offset,$limit" );
		
		if( empty($cats)){
			$form  .= $this->get_hidden_field_html( 'type', $this->get_next_import_type('blog_categories'));
			$message= '<em>' . esc_html__('Finished importing blog categories...', 'geodirectory-converter') . '</em><br>';
			set_transient('_geodir_converter_pmd_progress', $progress . $message, DAY_IN_SECONDS);
			$form .= $message;
			$this->update_progress( $form );
		}

		$imported = 0;
		if(! empty($_REQUEST['imported']) ){
			$imported = absint($_REQUEST['imported']);
		}

		$failed   = 0;
		if(! empty($_REQUEST['failed']) ){
			$failed = absint($_REQUEST['failed']);
		}

		foreach ( $cats as $cat ){
			$offset++;

			if( empty( $cat->id ) ){
				$failed++;
				continue;
			}

			$inserted = wp_insert_category( array('cat_name'=>$cat->title) );

			if( $inserted ){
				$key = '_pmd_post_category_original_id_' . $cat->id;
				set_transient( $key , $inserted, DAY_IN_SECONDS );
				$imported++;
				continue;
			}
			
			$failed++;
		}

		//Update the user on their progress
		$total_text  	= esc_html__( 'Total Blog Categories', 'geodirectory-converter' );
		$imported_text  = esc_html__( 'Imported Blog Categories', 'geodirectory-converter' );
		$processed_text = esc_html__( 'Processed Blog Categories', 'geodirectory-converter' );
		$failed_text  	= esc_html__( 'Failed', 'geodirectory-converter' );
		$form  		   .= "<div><strong>$total_text &mdash;</strong><em> $total</em></div>";
		$form          .= "<div><strong>$processed_text &mdash;</strong><em> $offset</em></div>";
		$form          .= "<div><strong>$imported_text &mdash;</strong><em> $imported</em></div>";
		$form          .= "<div><strong>$failed_text &mdash;</strong><em> $failed</em></div>";
		$form          .= $this->get_hidden_field_html( 'imported', $imported);
		$form          .= $this->get_hidden_field_html( 'failed', $failed);
		$form          .= $this->get_hidden_field_html( 'type', 'blog_categories');
		$form          .= $this->get_hidden_field_html( 'offset', $offset);
		$this->update_progress( $form, $total, $offset );
	}

	/**
	 * Imports blog comments
	 *
	 * @since GeoDirectory Converter 1.0.0
	 */
	private function import_blog_comments() {
		global $wpdb;

		$table 			= $this->prefix . 'blog_comments';
		$users_table	= $this->prefix . 'users';
		$total 			= $this->db->get_var("SELECT COUNT(id) as count from $table");
		$form   		= '<h3>' . esc_html__('Importing comments', 'geodirectory-converter') . '</h3>';
		$progress 		= get_transient('_geodir_converter_pmd_progress');
		if(! $progress ){
			$progress = '';
		}
		$form   = $progress . $form;
		
		//Abort early if there are no comments
		if( 0 == $total ){
			$form  .= $this->get_hidden_field_html( 'type', $this->get_next_import_type('blog_comments'));
			$message= '<em>' . esc_html__('There are no comments in your PhpMyDirectory installation. Skipping...', 'geodirectory-converter') . '</em><br>';
			set_transient('_geodir_converter_pmd_progress', $progress . $message, DAY_IN_SECONDS);
			$form .= $message;
			$this->update_progress( $form );
		}
		
		//Where should we start from
		$offset = 0;
		$limit  = 1;
		if(! empty($_REQUEST['offset']) ){
			$offset = absint($_REQUEST['offset']);
			$limit  = 5;
		}

		//Fetch the comments and abort in case we have imported all of them
		$pmd_comments   = $this->db->get_results(
			"SELECT `$table`.`id` as `comment_id`, `status`, `blog_id`, `user_id`, `date`, `comment`, `user_first_name`, `user_last_name`, `user_email` 
			FROM `$table` LEFT JOIN `$users_table` ON `$table`.`user_id` = `$users_table`.`id`  LIMIT $offset,$limit");

		if( empty($pmd_comments)){
			$form  .= $this->get_hidden_field_html( 'type', $this->get_next_import_type('blog_comments'));
			$message= '<em>' . esc_html__('Finished importing comments...', 'geodirectory-converter') . '</em><br>';
			set_transient('_geodir_converter_pmd_progress', $progress . $message, DAY_IN_SECONDS);
			$form .= $message;
			$this->update_progress( $form );
		}

		$imported = 0;
		if(! empty($_REQUEST['imported']) ){
			$imported = absint($_REQUEST['imported']);
		}

		$failed   = 0;
		if(! empty($_REQUEST['failed']) ){
			$failed = absint($_REQUEST['failed']);
		}

		foreach ( $pmd_comments as $key => $comment ){

			$offset++;

			if( empty( $comment->comment_id ) ){
				$failed++;
				continue;
			}

			$approved = 0;
			if( 'active' == $comment->status ){
				$approved = 1;
			}

			$post_id = get_transient('_pmd_post_original_id_' . $comment->blog_id);

			$id = wp_insert_comment( array(
				'comment_post_ID' 		=> $post_id,
				'user_id' 				=> $comment->user_id,
				'comment_date' 			=> $comment->date,
				'comment_date_gmt' 		=> $comment->date,
				'comment_content' 		=> $comment->comment,
				'comment_author' 		=> $comment->user_first_name . ' ' . $comment->user_last_name,
				'comment_author_email'	=> $comment->user_email,
				'comment_agent'			=> 'geodir-converter',
				'comment_approved'		=> $approved,
			));
			
			if(! $id ){
				$failed++;
			} else {
				$imported++;
			}
		}

		//Update the user on their progress
		$total_text  	 = esc_html__( 'Total Comments', 'geodirectory-converter' );
		$imported_text   = esc_html__( 'Imported Comments', 'geodirectory-converter' );
		$processed_text  = esc_html__( 'Processed Comments', 'geodirectory-converter' );
		$failed_text  	 = esc_html__( 'Failed', 'geodirectory-converter' );
		$form  			.= "<div><strong>$total_text &mdash;</strong><em> $total</em></div>";
		$form  			.= "<div><strong>$processed_text &mdash;</strong><em> $offset</em></div>";
		$form  			.= "<div><strong>$imported_text &mdash;</strong><em> $imported</em></div>";
		$form  			.= "<div><strong>$failed_text &mdash;</strong><em> $failed</em></div>";
		$form  			.= $this->get_hidden_field_html( 'imported', $imported);
		$form  			.= $this->get_hidden_field_html( 'failed', $failed);
		$form  			.= $this->get_hidden_field_html( 'type', 'blog_comments');
		$form  			.= $this->get_hidden_field_html( 'offset', $offset);
		$this->update_progress( $form, $total, $offset );
	}

	/**
	 * Imports blog posts
	 *
	 * @since GeoDirectory Converter 1.0.0
	 */
	public function import_posts() {

		global $wpdb;

		$form		= '<h3>' . esc_html__('Importing blog posts', 'geodirectory-converter') . '</h3>';
		$progress 	= get_transient('_geodir_converter_pmd_progress');
		if(! $progress ){
			$progress = '';
		}
		$form   = $progress . $form;
		$table  = $this->prefix . 'blog';
		$total  = $this->db->get_var("SELECT COUNT(id) as count from $table");

		//Abort early if there are no posts
		if( 0 == $total ){
			$form  .= $this->get_hidden_field_html( 'type', $this->get_next_import_type('posts'));
			$message = '<em>' . esc_html__('There are no blog posts in your PhpMyDirectory installation. Skipping...', 'geodirectory-converter') . '</em><br>';
			set_transient('_geodir_converter_pmd_progress', $progress . $message, DAY_IN_SECONDS);
			$form .= $message;
			$this->update_progress( $form );
		}

		//Where should we start from
		$offset = 0;
		$limit  = 1;
		if(! empty($_REQUEST['offset']) ){
			$offset = absint($_REQUEST['offset']);
			$limit  = 4;
		}

		$posts   = $this->db->get_results( "SELECT * FROM `$table` LIMIT $offset,$limit" );
		
		if( empty($posts)){
			$form  .= $this->get_hidden_field_html( 'type', $this->get_next_import_type('posts'));
			$message= '<em>' . esc_html__('Finished importing blog posts...', 'geodirectory-converter') . '</em><br>';
			set_transient('_geodir_converter_pmd_progress', $progress . $message, DAY_IN_SECONDS);
			$form .= $message;
			$this->update_progress( $form );
		}

		$imported = 0;
		if(! empty($_REQUEST['imported']) ){
			$imported = absint($_REQUEST['imported']);
		}

		$failed   = 0;
		if(! empty($_REQUEST['failed']) ){
			$failed = absint($_REQUEST['failed']);
		}

		foreach ( $posts as $key => $post ){
			$offset ++;

			if( empty( $post->id ) ){
				$failed++;
				continue;
			}
			
			$status = ( !empty( $post->status ) && 'active' == $post->status )? 'publish': 'draft';

			$id = wp_insert_post( array(
				'post_author'           => ( $post->user_id )? $post->user_id : 1,
				'post_content'          => ( $post->content )? $post->content : '',
				'post_title'            => ( $post->title )? $post->title : '',
				'post_name' 			=> ( $post->friendly_url )? $post->friendly_url : '',
				'post_excerpt'          => ( $post->content_short )? $post->content_short : '',
				'post_status'           => $status,
				'post_date_gmt'         => ( $post->date )? $post->date : '',
				'post_date'             => ( $post->date )? $post->date : '',
				'post_modified_gmt'     => ( $post->date_updated )? $post->date_updated : '',
				'post_modified'         => ( $post->date_updated )? $post->date_updated : '',
			), true);
			
			if( is_wp_error( $id ) ){
				$failed++;
				continue;
			}

			// maybe attach featured image
			if($id && !empty($post->image_extension)){
				self::import_featured_image($id,$post->id,$post->image_extension);
			}

			set_transient('_pmd_post_original_id_' . $post->id, $id, DAY_IN_SECONDS );

			//Prepare the categories
			$sql  = $this->db->prepare("SELECT category_id from {$table}_categories_lookup WHERE blog_id = %d", $post->id );
			$cats =  $this->db->get_col($sql);
			if( is_array($cats) ){

				$modified_cats = array();
				foreach( $cats as $cat ){
					$saved_cat_id = get_transient( '_pmd_post_category_original_id_' . $cat );
					if( $saved_cat_id ){
						$modified_cats[] = $saved_cat_id;
					}
					wp_set_post_categories( $id, $modified_cats );
				}

			}

			$imported++;
		}
		
		//Update the user on their progress
		$total_text  	= esc_html__( 'Total Blog Posts', 'geodirectory-converter' );
		$imported_text  = esc_html__( 'Imported Blog Posts', 'geodirectory-converter' );
		$processed_text = esc_html__( 'Processed Blog Posts', 'geodirectory-converter' );
		$failed_text  	= esc_html__( 'Failed', 'geodirectory-converter' );
		$form  		   .= "<div><strong>$total_text &mdash;</strong><em> $total</em></div>";
		$form          .= "<div><strong>$processed_text &mdash;</strong><em> $offset</em></div>";
		$form          .= "<div><strong>$imported_text &mdash;</strong><em> $imported</em></div>";
		$form          .= "<div><strong>$failed_text &mdash;</strong><em> $failed</em></div>";
		$form          .= $this->get_hidden_field_html( 'imported', $imported);
		$form          .= $this->get_hidden_field_html( 'failed', $failed);
		$form          .= $this->get_hidden_field_html( 'type', 'posts');
		$form          .= $this->get_hidden_field_html( 'offset', $offset);
		$this->update_progress( $form, $total, $offset );
	}

	/**
	 * Imports pages
	 *
	 * @since GeoDirectory Converter 1.0.0
	 */
	public function import_pages() {

		global $wpdb;

		$form		= '<h3>' . esc_html__('Importing pages', 'geodirectory-converter') . '</h3>';
		$progress 	= get_transient('_geodir_converter_pmd_progress');
		if(! $progress ){
			$progress = '';
		}
		$form   = $progress . $form;
		$table  = $this->prefix . 'pages';
		$total  = $this->db->get_var("SELECT COUNT(id) as count from $table");
		
		//Abort early if there are no pages
		if( 0 == $total ){
			$form  .= $this->get_hidden_field_html( 'type', $this->get_next_import_type('pages'));
			$message = '<em>' . esc_html__('There are no pages in your PhpMyDirectory installation. Skipping...', 'geodirectory-converter') . '</em><br>';
			set_transient('_geodir_converter_pmd_progress', $progress . $message, DAY_IN_SECONDS);
			$form .= $message;
			$this->update_progress( $form );
		}

		//Where should we start from
		$offset = 0;
		$limit  = 1;
		if(! empty($_REQUEST['offset']) ){
			$offset = absint($_REQUEST['offset']);
			$limit  = 5;
		}

		$pages   = $this->db->get_results( "SELECT * FROM `$table` LIMIT $offset,$limit" );
		
		if( empty($pages)){
			$form  .= $this->get_hidden_field_html( 'type', $this->get_next_import_type('pages'));
			$message= '<em>' . esc_html__('Finished importing pages...', 'geodirectory-converter') . '</em><br>';
			set_transient('_geodir_converter_pmd_progress', $progress . $message, DAY_IN_SECONDS);
			$form .= $message;
			$this->update_progress( $form );
		}

		$imported = 0;
		if(! empty($_REQUEST['imported']) ){
			$imported = absint($_REQUEST['imported']);
		}

		$failed   = 0;
		if(! empty($_REQUEST['failed']) ){
			$failed = absint($_REQUEST['failed']);
		}

		foreach ( $pages as $key => $page ){
			$offset++;

			if( empty( $page->id ) ){
				$failed++;
				continue;
			}
			
			$status = ( !empty( $page->active ) && '1' == $page->active )? 'publish': 'draft';

			$id = wp_insert_post( array(
				'post_author'           => isset( $page->user_id )? $page->user_id : 1,
				'post_content'          => isset( $page->content )? $page->content : '',
				'post_title'            => isset( $page->title )? $page->title : '',
				'post_name' 			=> isset( $page->friendly_url )? $page->friendly_url : '',
				'post_excerpt'          => isset( $page->content_short )? $page->content_short : '',
				'post_status'           => $status,
				'post_type'             => 'page',
				'post_date_gmt'         => isset( $page->date )? $page->date : '',
				'post_date'             => isset( $page->date )? $page->date : '',
				'post_modified_gmt'     => isset( $page->date_updated )? $page->date_updated : '',
				'post_modified'         => isset( $page->date_updated )? $page->date_updated : '',
			), true);

			if( is_wp_error( $id ) ){
				$failed++;
				continue;
			}



			$imported++;
		}
		
		//Update the user on their progress
		$total_text  	= esc_html__( 'Total Pages', 'geodirectory-converter' );
		$imported_text  = esc_html__( 'Imported Pages', 'geodirectory-converter' );
		$processed_text = esc_html__( 'Processed Pages', 'geodirectory-converter' );
		$failed_text  	= esc_html__( 'Failed', 'geodirectory-converter' );
		$form  		   .= "<div><strong>$total_text &mdash;</strong><em> $total</em></div>";
		$form          .= "<div><strong>$processed_text &mdash;</strong><em> $offset</em></div>";
		$form          .= "<div><strong>$imported_text &mdash;</strong><em> $imported</em></div>";
		$form          .= "<div><strong>$failed_text &mdash;</strong><em> $failed</em></div>";
		$form          .= $this->get_hidden_field_html( 'imported', $imported);
		$form          .= $this->get_hidden_field_html( 'failed', $failed);
		$form          .= $this->get_hidden_field_html( 'type', 'pages');
		$form          .= $this->get_hidden_field_html( 'offset', $offset);
		$this->update_progress( $form, $total, $offset );
	}

	/**
	 * Set the post thumbnail from an external image.
	 *
	 * @param $post_id
	 * @param $pmd_id
	 * @param string $image_extension
	 * @param string $title
	 */
	private function import_featured_image($post_id,$pmd_id,$image_extension='jpg',$title=''){
		$url = trailingslashit($this->url) ."files/blog/".absint($pmd_id).".".$image_extension;
		$attachment_id = media_sideload_image($url, $post_id, $title, 'id'); // uses the post date for the upload time /2009/12/image.jpg

		// return error object if its an error
		if ($attachment_id && !is_wp_error( $attachment_id ) ) {
			set_post_thumbnail($post_id, $attachment_id);
		}

	}

	/**
	 * Imports done
	 *
	 * @since GeoDirectory Converter 1.0.0
	 */
	private function import_done() {
		$progress	= get_transient('_geodir_converter_pmd_progress');
			if(! $progress ){
				$progress = '';
			}
			$import_posts_text  = esc_attr__( 'Import Blog Content', 'geodirectory-converter' );
			$success_text 		= esc_html__( 'Successfully imported all data', 'geodirectory-converter' );
			$next_step_text     = esc_html__( 'Click on the button below to import blog content.', 'geodirectory-converter' );

			$html = '
				<form method="post" action="" class="geodir-converter-form">
					<input type="hidden" name="action" value="gdconverter_handle_import">
					<input type="hidden" name="import_blog_data" value="1">
					<input type="hidden" name="step" value="3">
					<input type="hidden" name="gd-converter" value="pmd">
					' . $progress .'
					<h3 class="geodir-converter-header-success">' . $success_text .'</h3>
					<p>' . $next_step_text .'</p>
					<div class="geodir-conveter-centered">
						<input type="submit"  class="button button-primary" value="' . $import_posts_text .'">
					</div>
			';
			$html .= wp_nonce_field( 'gdconverter_nonce_action', 'gdconverter_nonce_field', true, false );
			$html .= '</form>';
			GDCONVERTER_Loarder::send_response( 'success', $html );
	}

	/**
	 * Blog Imports done
	 *
	 * @since GeoDirectory Converter 1.0.0
	 */
	private function import_blog_done() {
		$progress	= get_transient('_geodir_converter_pmd_progress');
		if(! $progress ){
			$progress = '';
		}
		$form  = '<h2>' . esc_html__( 'Successfully imported blog content. Happy Browsing.', 'geodirectory-converter' ) . '</h3>';
		$form .= "<div>$progress</div>";
		GDCONVERTER_Loarder::send_response( 'success', $form );
	}
	
	private function import_standard_fields($post_type='gd_place',$package_id=''){
		$fields = array();
		$package = ($package_id=='') ? '' : array($package_id);


		// show on all packages if none set
		if(!$package_id && function_exists('geodir_pricing_get_packages')){
			$packages = geodir_pricing_get_packages( array( 'post_type' => $post_type ) );
			if(!empty($packages)){
				$package = array();
				foreach($packages as $pkg){
					$package[] = $pkg->id;
				}
			}
		}

		$fields[] = array('post_type' => $post_type,
		                  'data_type' => 'INT',
		                  'field_type' => 'number',
		                  'admin_title' => __('PMD ID', 'geodirectory'),
		                  'frontend_desc' => __('Original PMD ID', 'geodirectory'),
		                  'frontend_title' => __('PMD ID', 'geodirectory'),
		                  'htmlvar_name' => 'pmd_id',
		                  'default_value' => '',
		                  'is_active' => '1',
		                  'option_values' => '',
		                  'is_default' => '0',
		                  'show_in' =>  '',
		                  'show_on_pkg' => $package,
					      'for_admin_use' => true,
		                  'clabels' => __('PMD ID', 'geodirectory')
		);

		$fields[] = array(
			'post_type' => $post_type,
			'data_type' => 'TEXT',
			'field_type' => 'file',
			'admin_title' => __('Company Logo', 'geodirectory'),
			'frontend_desc' => __('You can upload your company logo.', 'geodirectory'),
			'frontend_title' => __('Company Logo', 'geodirectory'),
			'htmlvar_name' => 'logo',
			'default_value' => '',
			'is_active' => '1',
			'option_values' => '',
			'is_default' => '0',
			'show_in' =>  '',
			'show_on_pkg' => $package,
			'clabels' => __('Company Logo', 'geodirectory'),
			'field_icon'         => 'far fa-image',
			'cat_sort'           => false,
			'cat_filter'         => false,
			'extra'       => array(
				'gd_file_types'     => array( 'jpg','jpe','jpeg','gif','png','bmp','ico'),
				'file_limit'        => 1,
			),
			'single_use'         => true,
		);

		$fields[] = array(
			'post_type' => $post_type,
			'data_type' => 'TEXT',
			'field_type' => 'file',
			'admin_title' => __('Hero Image', 'geodirectory'),
			'frontend_desc' => __('You can upload your hero logo.', 'geodirectory'),
			'frontend_title' => __('Hero Image', 'geodirectory'),
			'htmlvar_name' => 'hero',
			'default_value' => '',
			'is_active' => '1',
			'option_values' => '',
			'is_default' => '0',
			'show_in' =>  '',
			'show_on_pkg' => $package,
			'clabels' => __('Hero Image', 'geodirectory'),
			'field_icon'         => 'far fa-image',
			'cat_sort'           => false,
			'cat_filter'         => false,
			'extra'       => array(
				'gd_file_types'     => array( 'jpg','jpe','jpeg','gif','png','bmp','ico'),
				'file_limit'        => 1,
			),
			'single_use'         => true,
		);

		$fields[] = array('post_type' => $post_type,
		                  'data_type' => 'VARCHAR',
		                  'field_type' => 'phone',
		                  'admin_title' => __('Phone', 'geodirectory'),
		                  'frontend_desc' => __('You can enter phone number.', 'geodirectory'),
		                  'frontend_title' => __('Phone', 'geodirectory'),
		                  'htmlvar_name' => 'phone',
		                  'default_value' => '',
		                  'is_active' => '1',
		                  'option_values' => '',
		                  'is_default' => '0',
		                  'show_in' =>  '[detail],[mapbubble]',
		                  'show_on_pkg' => $package,
		                  'clabels' => __('Phone', 'geodirectory'));


		$fields[] = array('post_type' => $post_type,
		                  'data_type' => 'VARCHAR',
		                  'field_type' => 'phone',
		                  'admin_title' => __('Fax', 'geodirectory'),
		                  'frontend_desc' => __('You can enter fax number here.', 'geodirectory'),
		                  'frontend_title' => __('Fax', 'geodirectory'),
		                  'htmlvar_name' => 'fax',
		                  'default_value' => '',
		                  'is_active' => '1',
		                  'option_values' => '',
		                  'is_default' => '0',
		                  'show_in' =>  '[detail],[mapbubble]',
		                  'show_on_pkg' => $package,
		                  'clabels' => __('Fax', 'geodirectory'));


		$fields[] = array('post_type' => $post_type,
	                       'data_type' => 'TEXT',
	                       'field_type' => 'business_hours',
	                       'admin_title' => __('Business Hours', 'geodirectory'),
	                       'frontend_desc' => __('Select your business opening/operating hours.', 'geodirectory'),
	                       'frontend_title' => __('Business Hours', 'geodirectory'),
	                       'htmlvar_name' => 'business_hours',
	                       'default_value' => '',
	                       'is_active' => '1',
	                       'option_values' => '',
	                       'is_default' => '0',
	                       'show_in' => '[owntab],[detail]',
	                       'field_icon' => 'fas fa-clock',
                          'show_on_pkg' => $package,
		                  'clabels' => __('Business Hours', 'geodirectory'));

		$fields[] = array('post_type' => $post_type,
		                  'data_type' => 'TEXT',
		                  'field_type' => 'url',
		                  'admin_title' => __('Website', 'geodirectory'),
		                  'frontend_desc' => __('You can enter your business or listing website.', 'geodirectory'),
		                  'frontend_title' => __('Website', 'geodirectory'),
		                  'htmlvar_name' => 'website',
		                  'default_value' => '',
		                  'is_active' => '1',
		                  'option_values' => '',
		                  'is_default' => '0',
		                  'show_in' => '[detail]',
		                  'show_on_pkg' => $package,
		                  'clabels' => __('Website', 'geodirectory'));

		$fields[] = array('post_type' => $post_type,
		                  'data_type' => 'VARCHAR',
		                  'field_type' => 'email',
		                  'admin_title' => __('Email', 'geodirectory'),
		                  'frontend_desc' => __('You can enter your business or listing email.', 'geodirectory'),
		                  'frontend_title' => __('Email', 'geodirectory'),
		                  'htmlvar_name' => 'email',
		                  'is_active' => '1',
		                  'default_value' => '',
		                  'option_values' => '',
		                  'is_default' => '0',
		                  'show_in' => '[detail]',
		                  'show_on_pkg' => $package,
		                  'clabels' => __('Email', 'geodirectory'));

		$fields[] = array('post_type' => $post_type,
		                  'data_type' => 'TINYINT',
		                  'field_type' => 'checkbox',
		                  'admin_title' => __('Is Claimed?', 'geodirectory'),
		                  'frontend_desc' => __('Mark listing as a claimed.', 'geodirectory'),
		                  'frontend_title' => __('Business Owner/Associate?', 'geodirectory'),
		                  'htmlvar_name' => 'claimed',
		                  'is_active' => '1',
		                  'default_value' => '',
		                  'option_values' => '',
		                  'is_default' => '0',
		                  'show_in' => '[detail]',
		                  'show_on_pkg' => $package,
		                  'clabels' => __('Claimed', 'geodirectory'));

		$fields[] = array('post_type' => $post_type,
		                  'data_type' => 'TEXT',
		                  'field_type' => 'url',
		                  'admin_title' => __('Facebook', 'geodirectory'),
		                  'frontend_desc' => __('You can enter your business or listing facebook url.', 'geodirectory'),
		                  'frontend_title' => __('Facebook', 'geodirectory'),
		                  'htmlvar_name' => 'facebook',
		                  'default_value' => '',
		                  'is_active' => '1',
		                  'option_values' => '',
		                  'is_default' => '0',
		                  'show_in' => '[detail]',
		                  'show_on_pkg' => $package,
		                  'clabels' => __('Facebook', 'geodirectory'));

		$fields[] = array('post_type' => $post_type,
		                  'data_type' => 'TEXT',
		                  'field_type' => 'url',
		                  'admin_title' => __('Google', 'geodirectory'),
		                  'frontend_desc' => __('You can enter your business or listing google url.', 'geodirectory'),
		                  'frontend_title' => __('Google', 'geodirectory'),
		                  'htmlvar_name' => 'google',
		                  'default_value' => '',
		                  'is_active' => '1',
		                  'option_values' => '',
		                  'is_default' => '0',
		                  'show_in' => '[detail]',
		                  'show_on_pkg' => $package,
		                  'clabels' => __('Google', 'geodirectory'));

		$fields[] = array('post_type' => $post_type,
		                  'data_type' => 'TEXT',
		                  'field_type' => 'url',
		                  'admin_title' => __('Linkedin', 'geodirectory'),
		                  'frontend_desc' => __('You can enter your business or listing Linkedin url.', 'geodirectory'),
		                  'frontend_title' => __('Linkedin', 'geodirectory'),
		                  'htmlvar_name' => 'linkedin',
		                  'default_value' => '',
		                  'is_active' => '1',
		                  'option_values' => '',
		                  'is_default' => '0',
		                  'show_in' => '[detail]',
		                  'show_on_pkg' => $package,
		                  'clabels' => __('Linkedin', 'geodirectory'));

		$fields[] = array('post_type' => $post_type,
		                  'data_type' => 'TEXT',
		                  'field_type' => 'url',
		                  'admin_title' => __('Twitter', 'geodirectory'),
		                  'frontend_desc' => __('You can enter your business or listing Twitter url.', 'geodirectory'),
		                  'frontend_title' => __('Twitter', 'geodirectory'),
		                  'htmlvar_name' => 'twitter',
		                  'default_value' => '',
		                  'is_active' => '1',
		                  'option_values' => '',
		                  'is_default' => '0',
		                  'show_in' => '[detail]',
		                  'show_on_pkg' => $package,
		                  'clabels' => __('Twitter', 'geodirectory'));

		$fields[] = array('post_type' => $post_type,
		                  'data_type' => 'TEXT',
		                  'field_type' => 'url',
		                  'admin_title' => __('Pinterest', 'geodirectory'),
		                  'frontend_desc' => __('You can enter your business or listing Pinterest url.', 'geodirectory'),
		                  'frontend_title' => __('Pinterest', 'geodirectory'),
		                  'htmlvar_name' => 'pinterest',
		                  'default_value' => '',
		                  'is_active' => '1',
		                  'option_values' => '',
		                  'is_default' => '0',
		                  'show_in' => '[detail]',
		                  'show_on_pkg' => $package,
		                  'clabels' => __('Pinterest', 'geodirectory'));

		$fields[] = array('post_type' => $post_type,
		                  'data_type' => 'TEXT',
		                  'field_type' => 'url',
		                  'admin_title' => __('YouTube', 'geodirectory'),
		                  'frontend_desc' => __('You can enter your business or listing YouTube url.', 'geodirectory'),
		                  'frontend_title' => __('YouTube', 'geodirectory'),
		                  'htmlvar_name' => 'youtube',
		                  'default_value' => '',
		                  'is_active' => '1',
		                  'option_values' => '',
		                  'is_default' => '0',
		                  'show_in' => '[detail]',
		                  'show_on_pkg' => $package,
		                  'clabels' => __('YouTube', 'geodirectory'));

		$fields[] = array('post_type' => $post_type,
		                  'data_type' => 'TEXT',
		                  'field_type' => 'url',
		                  'admin_title' => __('Foursquare', 'geodirectory'),
		                  'frontend_desc' => __('You can enter your business or listing Foursquare url.', 'geodirectory'),
		                  'frontend_title' => __('Foursquare', 'geodirectory'),
		                  'htmlvar_name' => 'foursquare',
		                  'default_value' => '',
		                  'is_active' => '1',
		                  'option_values' => '',
		                  'is_default' => '0',
		                  'show_in' => '[detail]',
		                  'show_on_pkg' => $package,
		                  'clabels' => __('Foursquare', 'geodirectory'));

		$fields[] = array('post_type' => $post_type,
		                  'data_type' => 'TEXT',
		                  'field_type' => 'url',
		                  'admin_title' => __('Instagram', 'geodirectory'),
		                  'frontend_desc' => __('You can enter your business or listing Instagram url.', 'geodirectory'),
		                  'frontend_title' => __('Instagram', 'geodirectory'),
		                  'htmlvar_name' => 'instagram',
		                  'default_value' => '',
		                  'is_active' => '1',
		                  'option_values' => '',
		                  'is_default' => '0',
		                  'show_in' => '[detail]',
		                  'show_on_pkg' => $package,
		                  'clabels' => __('Instagram', 'geodirectory'));

		$fields[] = array('post_type' => $post_type,
		                  'data_type' => 'TINYINT',
		                  'field_type' => 'checkbox',
		                  'admin_title' => __('Featured', 'geodirectory'),
		                  'frontend_desc' => __('Mark listing as a featured.', 'geodirectory'),
		                  'frontend_title' => __('Is Featured?', 'geodirectory'),
		                  'htmlvar_name' => 'featured',
		                  'is_active' => '1',
		                  'default_value' => '',
		                  'option_values' => '',
		                  'is_default' => '0',
		                  'show_in' => '[detail]',
		                  'show_on_pkg' => $package,
		                  'clabels' => __('Featured', 'geodirectory'));

		if( 'gd_event' == $post_type ) {

			//Ignore nonn-event related custom fieldss
			$ignore  = 'twitter pinterest featured instagram foursquare youtube fax business_hours claimed facebook google linkedin';
			$ignore  = explode( ' ', $ignore );
			foreach( $fields as $key => $args ){
				if( in_array( $args['htmlvar_name'], $ignore )){
					unset( $fields[$key] );
				}
			}

			//Add event related custom fields
			$fields[] = array('post_type' => $post_type,
		                  'data_type' => 'TEXT',
		                  'field_type' => 'text',
		                  'admin_title' => __('Venue', 'geodirectory'),
		                  'frontend_desc' => __('The venue that will host this event.', 'geodirectory'),
		                  'frontend_title' => __('Venue', 'geodirectory'),
		                  'htmlvar_name' => 'venue',
		                  'default_value' => '',
		                  'is_active' => '1',
		                  'option_values' => '',
		                  'is_default' => '0',
		                  'show_in' => '[detail]',
		                  'show_on_pkg' => $package,
						  'clabels' => __('Venue', 'geodirectory'));
						  
			$fields[] = array('post_type' => $post_type,
		                  'data_type' => 'TEXT',
		                  'field_type' => 'text',
		                  'admin_title' => __('Location', 'geodirectory'),
		                  'frontend_desc' => __('The actual location of this event.', 'geodirectory'),
		                  'frontend_title' => __('Location', 'geodirectory'),
		                  'htmlvar_name' => 'location',
		                  'default_value' => '',
		                  'is_active' => '1',
		                  'option_values' => '',
		                  'is_default' => '0',
		                  'show_in' => '[detail]',
		                  'show_on_pkg' => $package,
						  'clabels' => __('Location', 'geodirectory'));
						  
			$fields[] = array('post_type' => $post_type,
		                  'data_type' => 'TEXT',
		                  'field_type' => 'text',
		                  'admin_title' => __('Admission', 'geodirectory'),
		                  'frontend_desc' => __('Event admission requirements.', 'geodirectory'),
		                  'frontend_title' => __('Admission', 'geodirectory'),
		                  'htmlvar_name' => 'admission',
		                  'default_value' => '',
		                  'is_active' => '1',
		                  'option_values' => '',
		                  'is_default' => '0',
		                  'show_in' => '[detail]',
		                  'show_on_pkg' => $package,
						  'clabels' => __('Admission', 'geodirectory'));
						  
			$fields[] = array('post_type' => $post_type,
		                  'data_type' => 'TEXT',
		                  'field_type' => 'text',
		                  'admin_title' => __('Contact Name', 'geodirectory'),
		                  'frontend_desc' => __('The contact person.', 'geodirectory'),
		                  'frontend_title' => __('Contact Name', 'geodirectory'),
		                  'htmlvar_name' => 'contact_name',
		                  'default_value' => '',
		                  'is_active' => '1',
		                  'option_values' => '',
		                  'is_default' => '0',
		                  'show_in' => '[detail]',
		                  'show_on_pkg' => $package,
		                  'clabels' => __('Contact Name', 'geodirectory'));

			
		}

		// insert custom fields
		if( !empty($fields) ){
			foreach ($fields as $field_index => $field) {
				geodir_custom_field_save($field);
			}
		}
	}

	/**
	 * Imports fields
	 *
	 * @since GeoDirectory Converter 1.0.0
	 */
	private function import_fields() {
		global $wpdb;

		$table 	= $this->prefix . 'fields';
		$total 	= $this->db->get_var("SELECT COUNT(id) AS count FROM $table");
		$form   = '<h3>' . esc_html__('Importing custom fields', 'geodirectory-converter') . '</h3>';
		$progress 	= get_transient('_geodir_converter_pmd_progress');
		if(! $progress ){
			$progress = '';
		}

		//Where should we start from
		$offset = 0;
		if(! empty($_REQUEST['offset']) ){
			$offset = absint($_REQUEST['offset']);
		}

		// import standard fields
		if( $offset==$total || $offset > $total ){
			$this->import_standard_fields();
			if ( defined( 'GEODIR_EVENT_VERSION' ) ) {
				$this->import_standard_fields('gd_event');
			}
		}

		$form   = $progress . $form;

		//Abort early if there are no fields
		if( 0 == $total ){
			$form  .= $this->get_hidden_field_html( 'type', $this->get_next_import_type('fields'));
			$message = '<em>' . esc_html__('There are no custom fields in your PhpMyDirectory installation. Skipping...', 'geodirectory-converter') . '</em><br>';
			set_transient('_geodir_converter_pmd_progress', $progress . $message, DAY_IN_SECONDS);
			$form .= $message;
			$this->update_progress( $form );
		}
		


		//Fetch the fields and abort in case we have imported all of them
		$fields 	= $this->db->get_results("SELECT * from $table LIMIT $offset,3");
		if( empty($fields)){
			$form  .= $this->get_hidden_field_html( 'type', $this->get_next_import_type('fields'));
			$message = '<em>' . esc_html__('Finished importing custom fields...', 'geodirectory-converter') . '</em><br>';
			set_transient('_geodir_converter_pmd_progress',  $progress . $message, DAY_IN_SECONDS);
			$form .= $message;
			$this->update_progress( $form );
		}

		$imported = 0;
		if(! empty($_REQUEST['imported']) ){
			$imported = absint($_REQUEST['imported']);
		}

		$failed   = 0;
		if(! empty($_REQUEST['failed']) ){
			$failed = absint($_REQUEST['failed']);
		}

		foreach ( $fields as $key => $field ){

			$offset++;

			if( empty( $field->id ) ){
				$failed++;
				continue;
			}
							
			$id = geodir_custom_field_save( array(
				'post_type' 		=> 'gd_place',
		        'data_type' 		=> 'VARCHAR',
		        'field_type' 		=> $field->type,
		        'admin_title' 		=> $field->name,
		        'frontend_desc' 	=> $field->description,
		        'frontend_title' 	=> $field->name,
		        'htmlvar_name' 		=> 'pmd_' . $field->name,
		        'option_values' 	=> $field->options,
		        'is_required'		=> $field->required,
				'is_active' => '1',

			));

			if( is_string( $id ) ){
				$failed++;
				continue;
			}

			$imported++;
		}

		//Update the user on their progress
		$total_text  	= esc_html__( 'Total Fields', 'geodirectory-converter' );
		$imported_text  = esc_html__( 'Imported Fields', 'geodirectory-converter' );
		$processed_text = esc_html__( 'Processed Fields', 'geodirectory-converter' );
		$failed_text  	= esc_html__( 'Failed', 'geodirectory-converter' );
		$form  		   .= "<div><strong>$total_text &mdash;</strong><em> $total</em></div>";
		$form          .= "<div><strong>$processed_text &mdash;</strong><em> $offset</em></div>";
		$form          .= "<div><strong>$imported_text &mdash;</strong><em> $imported</em></div>";
		$form          .= "<div><strong>$failed_text &mdash;</strong><em> $failed</em></div>";
		$form          .= $this->get_hidden_field_html( 'imported', $imported);
		$form          .= $this->get_hidden_field_html( 'failed', $failed);
		$form          .= $this->get_hidden_field_html( 'type', 'fields');
		$form          .= $this->get_hidden_field_html( 'offset', $offset);
		$this->update_progress( $form, $total, $offset );
	}

	/**
	 * Gets the current data to import
	 *
	 * @since GeoDirectory Converter 1.0.0
	 */
	private function get_next_import_type( $current = 'users' ) {
		$order = array(
			'users'             => 'products',
			'products' 			=> 'discounts',
			'discounts'         => 'categories',
			'categories'        => 'event_categories',
			'event_categories'  => 'fields',
			'fields'            => 'listings',
			'listings'          => 'events',
			'events'            => 'reviews',
			'reviews'           => 'invoices',
			'invoices'			=> 'done',
			'done'				=> 'blog_categories',
			'blog_categories'   => 'posts',
			'posts'				=> 'blog_comments',
			'blog_comments'   	=> 'pages',
			'pages'		   		=> 'blog_done',
			//ratings
		);

		if(isset($order[$current])){
			return $order[$current];
		}

		return false;
	}

	/**
	 * Returns a hidden input fields html
	 *
	 * @since GeoDirectory Converter 1.0.0
	 */
	private function get_hidden_field_html( $name, $value ) {
		$name  = esc_attr($name);
		$value = esc_attr($value);
		return "<input type='hidden' name='$name' value='$value'>";
	}
}