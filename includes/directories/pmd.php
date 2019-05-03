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

		//Handles progress requests
		add_action( 'wp_ajax_gdconverter_handle_progress', array( $this, 'handle_progress' ) );

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
	 * Handles progress requests
	 *
	 * @since GeoDirectory Converter 1.0.0
	 */
	public function handle_progress() {

		//Abort if the current user does not have enough rights to run this import
	    if ( !current_user_can( 'manage_options' )  ) {
		    $error = esc_html__( 'You are not allowed to run imports on this site.', 'geodirectory-converter' );
		    self::send_response( 'error', $error );
        }

        //Basic security check
	    if ( empty( $_REQUEST['nonce'] ) || ! wp_verify_nonce( $_REQUEST['nonce'], 'gd_converter_nonce' ) ) {
		    $error = esc_html__( 'An unknown error occured! Please refresh the page and try again.', 'geodirectory-converter' );
		    self::send_response( 'error', $error );
		}

				//Do we have any database connection details?
				$db_config = get_transient( 'geodir_converter_pmd_db_details');

				if(! $db_config || ! is_array($db_config) ){
					$error = __('Your PMD database settings are missing. Please refresh the page and try again.', 'geodirectory-converter');
					GDCONVERTER_Loarder::send_response( 'error', $error );
				}
		
				//Try connecting to the db
				$this->db = new wpdb( $db_config['user'] ,$db_config['pass'] ,$db_config['db_name'] ,$db_config['host'] );
		
				//Tables
				$this->prefix = $db_config['prefix'] ;

				call_user_func( array( $this, "import_" . $_REQUEST['type']) );
	}

	/**
	 * Updates the user on the current progress
	 *
	 * @since GeoDirectory Converter 1.0.0
	 */
	public function update_progress( $count, $offset, $imported, $failed, $type ) {
		wp_send_json( array(
        'action' 		=> 'progress',
        'body'	 		=> array(
					'count' 							=> $count,
					'progress-offset' 		=> $offset,
					'imported' 						=> $imported,
					'failed' 							=> $failed,
					'type' 								=> $type
			),	
        ) );
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
			'host' 		=> $host,
			'db_name' 	=> $db_name,
			'pass'		=> $pass,
			'user'		=> $name,
			'prefix'	=> $pre
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
			//'prices' 		=> esc_html__( 'Price packages', 'geodirectory-converter'),
			'invoices' 		=> esc_html__( 'Invoices', 'geodirectory-converter'),
			'categories' 	=> esc_html__( 'Categories', 'geodirectory-converter'),
			'listings' 		=> esc_html__( 'Listings', 'geodirectory-converter'),
			'events' 		=> esc_html__( 'Events', 'geodirectory-converter'),
			'reviews' 		=> esc_html__( 'Reviews', 'geodirectory-converter'),
			//'ratings' 		=> esc_html__( 'Ratings', 'geodirectory-converter'),
			'posts'			=> esc_html__( 'Blog Posts', 'geodirectory-converter'),
			'fields'		=> esc_html__( 'Custom Fields', 'geodirectory-converter'),
		);

		$return = '';
		foreach ( $items as $value => $label ) {

			//If it has already been imported, move on
			if( in_array($value, $done)){
				continue;
			}

			$icon    = $this->get_dashicons( $value );
			$return .= "
				<label class='geodir-converter-select'>
					<input class='screen-reader-text' name='gd-import' value='$value' type='radio'>
					<span class='dashicons $icon'></span> $label
				</label>
			";
		}
		return $return;
	}

	/**
	 * Retrieves a dashicon
	 *
	 * @since GeoDirectory Converter 1.0.0
	 */
	public function get_dashicons( $key ) {
		$icons = array(
			'listings' => 'dashicons-location-alt',
			'events'   => 'dashicons-calendar-alt',
			'reviews'  => 'dashicons-admin-comments',
			'invoices' => 'dashicons-media-spreadsheet',
			'prices'   => 'dashicons-products',
			'users'	   => 'dashicons-admin-users',
			'posts'	   => 'dashicons-admin-post',
		);

		if( empty($key) || empty($icons[$key])){
			return 'dashicons-admin-page';
		}
		return $icons[$key];
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
		$this->db = new wpdb( $db_config['user'] ,$db_config['pass'] ,$db_config['db_name'] ,$db_config['host'] );

		//Tables
		$this->prefix = $db_config['prefix'] ;

		//What is the user trying to import?
		if( empty($_REQUEST['gd-import']) ){
			$error = __('Please select the data you want to import.', 'geodirectory-converter');
			GDCONVERTER_Loarder::send_response( 'error', $error );
		}
		$import = sanitize_text_field( $_REQUEST['gd-import'] );

		//can we import it?
		if(! method_exists($this, "import_$import") ){
			$error = __('This importer is not configured to import the selected data.', 'geodirectory-converter');
			GDCONVERTER_Loarder::send_response( 'error', $error );
		}

		//Let's import it
		call_user_func( array( $this, "import_$import") );

		$fields .= sprintf(
			__('%s Successfully imported %s %s
			  %s You can select any other data you wish to import %s', 'geodirectory-converter'),
			 '<h3 class="geodir-converter-header-success">',
			 $import,
			 '</h3>',
			 '<p>',
			 '</p>'
		);

		$done = array();
		if(! empty($_REQUEST['pmd-done']) ){
			$done = explode( ',', $_REQUEST['pmd-done'] );
		}
		$done[] = $import;
		$fields .= $this->get_types_select_html( $done );
		$done = esc_attr( implode( ',', $done ));
		$fields .= "<input type='hidden' name='pmd-done' value='$done'/>";

		return $fields;
	}

	/**
	 * Imports listings
	 *
	 * @since GeoDirectory Converter 1.0.0
	 */
	private function import_listings() {
		global $wpdb;

		$table 					= $this->prefix . 'listings';
		$posts_table 		= $wpdb->posts;
		$places_table		= geodir_db_cpt_table( 'gd_place' );
		$total 					= $this->db->get_var("SELECT COUNT(id) as count from $table");
		
		//Abort early if there are no listings
		if( 0 == $total ){
			$error = __('There are no listings in your PhpMyDirectory installation.', 'geodirectory-converter');
			GDCONVERTER_Loarder::send_response( 'error', $error );
		}

		//Stop importing in case the user has pressed on the end button
		if(! empty($_REQUEST['progress-stop']) ){
			return;
		}
		
		//Where should we start from
		$offset = 0;
		if(! empty($_REQUEST['progress-offset']) ){
			$offset = absint($_REQUEST['progress-offset']);
		}

		//Fetch the listings and abort in case we have imported all of them
		$listings_results 	= $this->db->get_results("SELECT * from $table LIMIT $offset,3");
		if( empty($listings_results)){
			return;
		}

		$imported = 0;
		$failed   = 0;

		foreach ( $listings_results as $key => $listing ){
			$offset ++;

			if( empty( $listing->id ) ){
				$failed ++;
				continue;
			}
			
			//Avoid throwing an error in case a post with this id already exists
			$sql = $wpdb->prepare( "DELETE FROM `{$wpdb->posts}` WHERE `{$wpdb->posts}`.`ID` = %d", $listing->id );
			$wpdb->query( $sql );

			//Prepare and insert the listing into the db
			$excerpt = ( !empty( $listing->description_short ) )? $listing->description_short: '';
			$status  = ( !empty( $listing->status ) && 'active' == $listing->status )? 'publish': $listing->status;
			$status  = ( !empty( $listing->status ) && 'suspended' == $listing->status )? 'trash': $status;			
			$wpdb->insert(
				$posts_table,
				array(
					'ID' 				=> $listing->id,
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

			//Prepare the categories
			$sql  = $this->db->prepare("SELECT cat_id from {$table}_categories WHERE list_id = %d", $listing->id );
			$cats =  $this->db->get_col($sql);
			if( is_array($cats) ){
				$cats = implode( ',', $cats );
			} else {
				$cats = $listing->primary_category_id;
			}
			
			//In case there was a listing with this id, delete it
			$sql = $wpdb->prepare( "DELETE FROM `{$places_table}` WHERE `{$places_table}`.`post_id` = %d", $listing->id );
			$wpdb->query( $sql );

			$wpdb->insert(
				$places_table,
				array(
					'post_id' 			=> $listing->id,
					'post_title' 		=> $listing->title,
					'post_status' 		=> $status,
					'post_tags' 		=> '',
					'post_category' 	=> $cats,
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
		
			$imported ++;
		}
		
		//Update the user on their progress
		$this->update_progress( $total, $offset, $imported, $failed, 'listings' );
	}

	/**
	 * Imports users
	 *
	 * @since GeoDirectory Converter 1.0.0
	 */
	private function import_users() {

		global $wpdb;

		$table	= $this->prefix . 'users';
		$total 					= $this->db->get_var("SELECT COUNT(id) as count from $table");
		
		//Abort early if there are no users
		if( 0 == $total ){
			$error = __('There are no users in your PhpMyDirectory installation.', 'geodirectory-converter');
			GDCONVERTER_Loarder::send_response( 'error', $error );
		}

		//Stop importing in case the user has pressed on the end button
		if(! empty($_REQUEST['progress-stop']) ){
			return;
		}
		
		//Where should we start from
		$offset = 0;
		if(! empty($_REQUEST['progress-offset']) ){
			$offset = absint($_REQUEST['progress-offset']);
		}

		//Fetch the listings and abort in case we have imported all of them
		$pmd_users 	= $this->db->get_results("SELECT * from $table LIMIT $offset,3");
		if( empty($pmd_users) ){
			return;
		}

		$imported = 0;
		$failed   = 0;

		if( empty( $pmd_users ) ){
			return;
		}

		foreach ( $pmd_users as $key => $user ){
			$offset++;

			if( empty( $user->id ) ){
				$failed++;
				continue;
			}

			if( 1 == $user->id ){
				continue;
			}

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
					'user_login' 		=> $user->login,
					'user_pass' 		=> $user->pass,
					'user_nicename' 	=> $user->login,
					'user_email' 		=> $user->user_email,
					'user_registered' 	=> $user->created,
					'display_name' 		=> $display_name,
				),
				array('%d','%s','%s','%s','%s','%s','%s' )
			);
			
			update_user_meta( $user->id, 'first_name', $user->user_first_name );
			update_user_meta( $user->id, 'last_name', $user->user_last_name );
			update_user_meta( $user->id, 'pmd_password_hash', $user->password_hash );
			update_user_meta( $user->id, 'pmd_password_salt', $user->password_salt );
			update_user_meta( $user->id, 'user_organization', $user->user_organization );
			update_user_meta( $user->id, 'user_address1', $user->user_address1 );
			update_user_meta( $user->id, 'user_address2', $user->user_address2 );
			update_user_meta( $user->id, 'user_city', $user->user_city );
			update_user_meta( $user->id, 'user_state', $user->user_state );
			update_user_meta( $user->id, 'user_country', $user->user_country );
			update_user_meta( $user->id, 'user_zip', $user->user_zip );
			update_user_meta( $user->id, 'user_phone', $user->user_phone );
		
			$imported++;
		}

		//Update the user on their progress
		$this->update_progress( $total, $offset, $imported, $failed, 'users' );

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
		$total 					= $this->db->get_var("SELECT COUNT(id) as count from $table");
		
		//Abort early if there are no cats
		if( 0 == $total ){
			$error = __('There are no categories in your PhpMyDirectory installation.', 'geodirectory-converter');
			GDCONVERTER_Loarder::send_response( 'error', $error );
		}

		//Stop importing in case the user has pressed on the end button
		if(! empty($_REQUEST['progress-stop']) ){
			return;
		}
		
		//Where should we start from
		$offset = 0;
		if(! empty($_REQUEST['progress-offset']) ){
			$offset = absint($_REQUEST['progress-offset']);
		}

		//Fetch the listings and abort in case we have imported all of them
		$pmd_cats 	= $this->db->get_results("SELECT * from $table LIMIT $offset,3");
		if( empty($pmd_cats)){
			return;
		}

		$imported = 0;
		$failed   = 0;

		foreach ( $pmd_cats as $key => $cat ){
			$offset++;

			if( empty( $cat->id ) ){
				$failed++;
				continue;
			}

			$sql = $wpdb->prepare( "DELETE FROM `{$wpdb->terms}` WHERE `{$wpdb->terms}`.`term_id` = %d", $cat->id );
			$wpdb->query( $sql );

			$wpdb->insert(
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
				array('%d','%s', '%d', '%d')
			);

			if(! empty($cat->description) ){
				update_term_meta( $cat->id, 'ct_cat_top_desc', $cat->description );
			}

			$imported++;
		}

		//Update the user on their progress
		$this->update_progress( $total, $offset, $imported, $failed, 'categories' );
	}

	/**
	 * Imports invoices
	 *
	 * @since GeoDirectory Converter 1.0.0
	 */
	private function import_invoices() {
		global $wpdb;

		//Abort early if the invoicing plugin is not installed
		if ( !defined( 'WPINV_VERSION' ) ) {
			$message = __( 'Irks! You need to install the Invoicing plugin before importing invoices.', 'geodir-converter' );
			$url     = network_admin_url( 'plugin-install.php?tab=plugin-information&plugin=invoicing&TB_iframe=true&width=600&height=550' );

			$error = sprintf( '<div class="%s"><p>%s</p><p><a href="%s" class="thickbox button button-primary">%s</a></p></div>', 
					esc_attr( $class ), 
					esc_html( $message ),
					esc_url( $url ),
					esc_html__( 'Install Invoicing', 'geodir-converter' )
	 		);
			GDCONVERTER_Loarder::send_response( 'error', $error );
		}

		$table 				  = $this->prefix . 'invoices';
		$posts_table 		= $wpdb->posts;
		$total 					= $this->db->get_var("SELECT COUNT(id) as count from $table");
		
		//Abort early if there are no invoices
		if( 0 == $total ){
			$error = __('There are no invoices in your PhpMyDirectory installation.', 'geodirectory-converter');
			GDCONVERTER_Loarder::send_response( 'error', $error );
		}

		//Stop importing in case the user has pressed on the end button
		if(! empty($_REQUEST['progress-stop']) ){
			return;
		}
		
		//Where should we start from
		$offset = 0;
		if(! empty($_REQUEST['progress-offset']) ){
			$offset = absint($_REQUEST['progress-offset']);
		}

		//Fetch the invoices and abort in case we have imported all of them
		$pmd_invoices 	= $this->db->get_results("SELECT * from $table LIMIT $offset,3");
		if( empty($pmd_invoices)){
			return;
		}

		$imported = 0;
		$failed   = 0;

		foreach ( $pmd_invoices as $key => $invoice ){

			$offset++;

			if( empty( $invoice->id ) ){
				$failed++;
				continue;
			}
			
			$status = ( !empty( $invoice->status ) && 'unpaid' == $invoice->status )? 'wpi-pending': $invoice->status;
			$status = ( !empty( $invoice->status ) && 'canceled' == $invoice->status )? 'wpi-cancelled': $status;
			$status = ( !empty( $invoice->status ) && 'paid' == $invoice->status )? 'publish': $status;
			$excerpt = ( !empty( $invoice->description ) )? $invoice->description: '';
			
			$id = wp_insert_post( array(
				'post_author'           => ( $invoice->user_id )? $invoice->user_id : 1,
				'post_content'          => ( $invoice->description )? $invoice->description : '',
				'post_content_filtered' => ( $invoice->description )? $invoice->description : '',
				'post_title'            => 'WPINV-00'.$invoice->id ,
				'post_name' 						=> 'inv-'.$invoice->id,
				'post_excerpt'          => '',
				'post_status'           => $status,
				'post_type'             => 'wpi_invoice',
				'comment_status'        => 'closed',
				'ping_status'           => 'closed',
				'post_date_gmt'         => ( $invoice->date )? $invoice->date : '',
				'post_date'             => ( $invoice->date )? $invoice->date : '',
				'post_modified_gmt'     => ( $invoice->date_update )? $invoice->date_update : '',
				'post_modified'         => ( $invoice->date_update )? $invoice->date_update : '',
			), true);

			if( is_wp_error( $id ) ){
				var_dump($id); exit;
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
		$this->update_progress( $total, $offset, $imported, $failed, 'invoices' );
	}

		/**
	 * Imports categories
	 *
	 * @since GeoDirectory Converter 1.0.0
	 */
	private function import_reviews() {
		global $wpdb;

		$table 					= $this->prefix . 'reviews';
		$users_table		= $this->prefix . 'users';
		$total 					= $this->db->get_var("SELECT COUNT(id) as count from $table");
		
		//Abort early if there are no reviews
		if( 0 == $total ){
			$error = __('There are no reviews in your PhpMyDirectory installation.', 'geodirectory-converter');
			GDCONVERTER_Loarder::send_response( 'error', $error );
		}

		//Stop importing in case the user has pressed on the end button
		if(! empty($_REQUEST['progress-stop']) ){
			return;
		}
		
		//Where should we start from
		$offset = 0;
		if(! empty($_REQUEST['progress-offset']) ){
			$offset = absint($_REQUEST['progress-offset']);
		}

		//Fetch the reviews and abort in case we have imported all of them
		$pmd_reviews   = $this->db->get_results(
			"SELECT `$table`.`id` as `review_id`, `status`, `listing_id`, `user_id`, `date`, `review`, `user_first_name`, `user_last_name`, `user_email` 
			FROM `$table` LEFT JOIN `$users_table` ON `$table`.`user_id` = `$users_table`.`id`  LIMIT $offset,3");

		if( empty($pmd_reviews)){
			return;
		}

		$imported = 0;
		$failed   = 0;

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

			$id = wp_insert_comment( array(
				'comment_post_ID' 		=> $review->listing_id,
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
				$imported++;
			}
		}

		//Update the user on their progress
		$this->update_progress( $total, $offset, $imported, $failed, 'reviews' );
	}

	/**
	 * Imports events
	 *
	 * @since GeoDirectory Converter 1.0.0
	 */
	private function import_events() {
		global $wpdb;

		//Abort early if the events addon is not installed
		if ( !defined( 'GEODIR_EVENT_VERSION' ) ) {
			$message = __( 'Irks! You need to install the Events addon before importing events.', 'geodir-converter' );

			$error = sprintf( '<div class="%s"><p>%s</p><p><a href="%s" class="thickbox button button-primary">%s</a></p></div>', 
					esc_attr( $class ), 
					esc_html( $message ),
					esc_url( 'https://wpgeodirectory.com/downloads/events/' ),
					esc_html__( 'Install Events', 'geodir-converter' )
	 		);
			GDCONVERTER_Loarder::send_response( 'error', $error );
		}

		$table 						= $this->prefix . 'events';
		$listings_table		= $this->prefix . 'listings';
		$total 						= $this->db->get_var("SELECT COUNT(id) as count from $table");
		
		//Abort early if there are no events
		if( 0 == $total ){
			$error = __('There are no events in your PhpMyDirectory installation.', 'geodirectory-converter');
			GDCONVERTER_Loarder::send_response( 'error', $error );
		}
		
		//Stop importing in case the user has pressed on the end button
		if(! empty($_REQUEST['progress-stop']) ){
			return;
		}
		
		//Where should we start from
		$offset = 0;
		if(! empty($_REQUEST['progress-offset']) ){
			$offset = absint($_REQUEST['progress-offset']);
		}

		//Fetch the events and abort in case we have imported all of them
		$events_fields = array(	
			'user_id','description','title','description_short',
			'status','date','date_update','date_start','date_end',
			'recurring_type', 'recurring_interval', 'recurring_days',
			'latitude', 'longitude', 'phone', 'email', 'website'
		);
		$listings_fields = array(
			'facebook_page_id', 'twitter_id', 'ip', 'location_text_1', 'location_text_2',
			'location_text_3', 'listing_zip', 'listing_address2', 
		);

		$sql = 'SELECT ';
		foreach( $events_fields as $field){
			$sql .= " `$table`.`$field` as `$field`, ";
		}

		foreach( $listings_fields as $field){
			$sql .= " `$listings_table`.`$field` as `$field`, ";
		}

		$sql = rtrim($sql, ', ');

		$sql 		 .= "  FROM `$table` LEFT JOIN `$listings_table` ON `$table`.`listing_id` = `$listings_table`.`id`  LIMIT $offset,3 ";
		$pmd_events   = $this->db->get_results( $sql );
		
		if( empty($pmd_events)){
			return;
		}

		$imported 		= 0;
		$failed   		= 0;
		$events_table	= geodir_db_cpt_table( 'gd_events' );

		foreach ( $pmd_events as $key => $event ){
			$offset++;

			if( empty( $event->user_id ) ){
				$failed++;
				continue;
			}
			

			$status  = ( !empty( $event->status ) && 'active' == $event->status )? 'publish': $event->status;
			$status  = ( !empty( $event->status ) && 'suspended' == $event->status )? 'trash': $status;
		
			$id = wp_insert_post( array(
				'post_author'           => $event->user_id,
				'post_content'          => ( $event->description ) ? $event->description : '',
				'post_content_filtered' => ( $event->description ) ? $event->description : '',
				'post_title'            => ( $event->title ) ? $event->title : '',
				'post_excerpt'          => ( $event->description_short ) ? $event->description_short : '',
				'post_status'           => $status,
				'post_type'             => 'gd_event',
				'comment_status'        => 'open',
				'ping_status'           => 'closed',
				'post_date_gmt'         => ( $event->date ) ? $event->date : '',
				'post_date'             => ( $event->date ) ? $event->date : '',
				'post_modified_gmt'     => ( $event->date ) ? $event->date_update : '',
				'post_modified'         => ( $event->date ) ? $event->date_update : '',
			), true);

			if( is_wp_error( $id ) ){
				$failed++;
				continue;
			}

			$event_dates = maybe_serialize( array(
				'recurring' 		=> $event->recurring,
				'start_date' 		=> date( "Y-m-d", strtotime( $event->date_start  ) ),
				'end_date' 			=> date( "Y-m-d", strtotime( $event->date_end  ) ),
				'all_day' 			=> 0,
				'start_time' 		=> date( 'g:i a', strtotime( $event->date_start  ) ),
				'end_time' 			=> date( 'g:i a', strtotime( $event->date_end  ) ),
				'duration_x' 		=> '',
				'repeat_type' 		=> $event->recurring_type,
				'repeat_x' 			=> $event->recurring_interval,
				'repeat_end_type' 	=> '',
				'max_repeat' 		=> '',
				'recurring_dates' 	=> '',
				'different_times' 	=> '',
				'start_times' 		=> '',
				'end_times' 		=> '',
				'repeat_days' 		=> $event->recurring_days,	
				'repeat_weeks' 		=> '',
			));

			$wpdb->insert(
				$events_table,
				array(
					'post_id' 			=> $id,
					'post_title' 		=> $event->title,
					'post_status' 		=> $status,
					'submit_ip' 		=> $event->ip,
					'street' 			=> $event->listing_address2,
					'city' 				=> $event->location_text_1,
					'region' 			=> $event->location_text_2,
					'country' 			=> $event->location_text_3,
					'zip' 				=> $event->listing_zip,
					'latitude' 			=> $event->latitude,
					'longitude' 		=> $event->longitude,
					'phone' 			=> $event->phone,
					'email' 			=> $event->email,
					'website' 			=> $event->website,
					'twitter' 			=> 'http://twitter.com/' . $event->twitter_id,
					'facebook' 			=> 'http://facebook.com/' . $event->facebook_page_id,
					'featured' 			=> 0,
					'recurring' 		=> $event->recurring,
					'event_dates' 		=> $event_dates,
					'rsvp_count' 		=> 0,
				),
				array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%d')
			);
			
			$imported++;
		}

		
		//Update the user on their progress
		$this->update_progress( $total, $offset, $imported, $failed, 'events' );
	}

	/**
	 * Imports blog posts
	 *
	 * @since GeoDirectory Converter 1.0.0
	 */
	private function import_posts() {
		global $wpdb;

		$table 				= $this->prefix . 'blog';
		$total 				= $this->db->get_var("SELECT COUNT(id) as count from $table");
		
		//Abort early if there are no blog posts
		if( 0 == $total ){
			$error = __('There are no blog posts in your PhpMyDirectory installation.', 'geodirectory-converter');
			GDCONVERTER_Loarder::send_response( 'error', $error );
		}

		//Stop importing in case the user has pressed on the end button
		if(! empty($_REQUEST['progress-stop']) ){
			return;
		}
		
		//Where should we start from
		$offset = 0;
		if(! empty($_REQUEST['progress-offset']) ){
			$offset = absint($_REQUEST['progress-offset']);
		}

		//Fetch the posts and abort in case we have imported all of them
		$posts 	= $this->db->get_results("SELECT * from $table LIMIT $offset,3");
		if( empty($posts)){
			return;
		}
		
		$imported = 0;
		$failed   = 0;

		foreach ( $posts as $key => $post ){

			$offset++;

			if( empty( $post->id ) ){
				$failed++;
				continue;
			}
			
			$status = ( !empty( $post->status ) && 'active' == $post->status )? 'publish': 'draft';

			$id = wp_insert_post( array(
				'post_author'           => ( $post->user_id )? $post->user_id : 1,
				'post_content'          => ( $post->content )? $post->content : '',
				'post_title'            => ( $post->title )? $post->title : '',
				'post_name' 						=> ( $post->friendly_url )? $post->friendly_url : '',
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

			if( $id ){
				update_post_meta( $id, '_pmd_original_id', $post->id );
			}

			$imported++;
		}
		
		//Update the user on their progress
		$this->update_progress( $total, $offset, $imported, $failed, 'posts' );
	}

	/**
	 * Imports fields
	 *
	 * @since GeoDirectory Converter 1.0.0
	 */
	private function import_fields() {
		global $wpdb;

		$table 				= $this->prefix . 'fields';
		$total 					= $this->db->get_var("SELECT COUNT(id) as count from $table");
		
		//Abort early if there are no fields
		if( 0 == $total ){
			$error = __('There are no custom fields in your PhpMyDirectory installation.', 'geodirectory-converter');
			GDCONVERTER_Loarder::send_response( 'error', $error );
		}

		//Stop importing in case the user has pressed on the end button
		if(! empty($_REQUEST['progress-stop']) ){
			return;
		}
		
		//Where should we start from
		$offset = 0;
		if(! empty($_REQUEST['progress-offset']) ){
			$offset = absint($_REQUEST['progress-offset']);
		}

		//Fetch the invoices and abort in case we have imported all of them
		$fields 	= $this->db->get_results("SELECT * from $table LIMIT $offset,3");
		if( empty($fields)){
			return;
		}

		$imported = 0;
		$failed   = 0;

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
		        'frontend_title' 	=> $field->type,
		        'htmlvar_name' 		=> 'pmd_' . $field->name,
		        'option_values' 	=> $field->options,
		        'is_required'		=> $field->required,
			
			));

			if( is_string( $id ) ){
				$failed++;
				continue;
			}

			$imported++;
		}
		
		//Update the user on their progress
		$this->update_progress( $total, $offset, $imported, $failed, 'fields' );
	}
}