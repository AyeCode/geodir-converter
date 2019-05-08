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
		add_action( 'geodirectory_pmd_importer_fields',	array( $this, 'import_posts' ), 10, 2);

		//Handles progress requests
		add_action( 'wp_ajax_gdconverter_pmd_handle_progress', array( $this, 'handle_progress' ) );

		//Handle logins for imported users
		add_filter( 'wp_authenticate_user',	array( $this, 'handle_login' ), 10, 2 );

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
	    if ( empty( $_REQUEST['gdconverter_nonce_field'] ) || ! wp_verify_nonce( $_REQUEST['gdconverter_nonce_field'], 'gdconverter_nonce_action' ) ) {
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

		$type = trim($_REQUEST['type']);
		if( 'done' == $type ){
			$progress 			= get_transient('_geodir_converter_pmd_progress');
			if(! $progress ){
				$progress = '';
			}
			$html = '
				<form method="post" action="" class="geodir-converter-form">
					<input type="hidden" name="action" value="gdconverter_handle_import">
					<input type="hidden" name="step" value="14">
					<input type="hidden" name="gd-converter" value="pmd">
					' . $progress .'
					<h3 class="geodir-converter-header-success">Successfully imported all data</h3>
					<p>Click on the button below to import blog posts.</p>
					<div class="geodir-conveter-centered">
						<input type="submit"  class="button button-primary" value="Import Blog Posts">
					</div>
			';
			$html .= wp_nonce_field( 'gdconverter_nonce_action', 'gdconverter_nonce_field', true, false );
			$html .= '</form>';
			GDCONVERTER_Loarder::send_response( 'success', $html );

		} else {
			call_user_func( array( $this, "import_" . $type) );
		}
		
	}

	/**
	 * Updates the user on the current progress
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
	 * @since GeoDirectory Converter 1.0.0
	 */
	public function show_initial_settings( $fields, $step ) {

		if( 1 != $step ){
			return $fields;
		}
		delete_transient('_geodir_converter_pmd_progress');
		$published_posts = wp_count_posts()->publish;
		$users           = count_users();
		$message 		 = false;

		if( $published_posts > 1 && $users['total_users'] > 1){

			$message = sprintf(
				__('Detected %s users and %s published blog posts.', 'geodirectory-converter'),
				$users['total_users'],
				$published_posts);

		} elseif( $published_posts > 1 ){
			$message = sprintf(
				__('Detected %s published blog posts.', 'geodirectory-converter'),
				$published_posts);
		} elseif( $users['total_users'] > 1 ){
			$message = sprintf(
				__('Detected %s users.', 'geodirectory-converter'),
				$users['total_users']);
		}

		if( $message ) {
			return $fields . sprintf( 
				'<h3 class="geodir-converter-header-error">%s</h3><p>%s</p>',
				$message,
				__('You must use a fresh install of WordPress to use this converter since existing data will be overidden.', 'geodirectory-converter')
			);
		}

		$fields .= '
		<h3>Next, we need to connect to your PhpMyDirectory installation</h3>
		<label class="geodir-label-grid"><div class="geodir-label-grid-label">Database Host Name</div><input type="text" value="localhost" name="database-host"></label>
		<label class="geodir-label-grid"><div class="geodir-label-grid-label">Database Name</div><input type="text" value="pmd" name="database-name"></label>
		<label class="geodir-label-grid"><div class="geodir-label-grid-label">Database Username</div><input type="text" value="root" name="database-user"></label>
		<label class="geodir-label-grid"><div class="geodir-label-grid-label">Database Password</div><input type="text" name="database-password"></label>
		<label class="geodir-label-grid"><div class="geodir-label-grid-label">Table Prefix</div><input type="text" value="pmd_" name="table-prefix"></label>
		<input type="submit" class="button button-primary" value="Connect">
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
		set_transient( 'geodir_converter_pmd_db_details', $cache, DAY_IN_SECONDS  );

		$title 			  = esc_html__( 'Successfully connected to PhpMyDirectory', 'geodirectory-converter');
		$sub_title 		= esc_html__( 'Click the button below to import all your PhpMyDirectory data into this website.', 'geodirectory-converter');
		$notes_title	= esc_html__( 'Notes', 'geodirectory-converter');
		
		$fields .= "
				<h3 class='geodir-converter-header-success'>$title</h3>
				<p>$sub_title</p>
				<div class='geodir-conveter-centered'>
						<input type='submit' class='button button-primary' value='Start Importing Data'>
				</div>
				<h4>$notes_title</h4>
				<ul class='geodir-conveter-notes'>
		";
		
		foreach ($this->get_notes() as $note) {
			$fields .= "<li>$note</li>";
		}
		
		$fields .= '</ul>';

		return $fields;
	}

	/**
	 * Generates import notes
	 *
	 * @since GeoDirectory Converter 1.0.0
	 */
	public function get_notes() {

		$notes			= array(
			esc_html__( 'You will be able to import your blog posts later.', 'geodirectory-converter'),
		);

		if ( !defined( 'WPINV_VERSION' ) ) {
			$url 			= esc_url( 'https://wordpress.org/plugins/invoicing' );
			$notes[] = 	sprintf( 
				esc_html__( 'The Invoicing plugin is not active. Invoices will not be imported unless you %s install and activate the Invoicing plugin %s first.', 'geodirectory-converter'),
				"<a href='$url'>",
				'</a>'
			);
		}

		if ( !defined( 'GEODIR_EVENT_VERSION' ) ) {
			$url 			= esc_url( 'https://wpgeodirectory.com/downloads/events/' );
			$notes[] = 	sprintf( 
				esc_html__( 'The Events Addon is not active. Events will not be imported unless you %s install and activate the Events Addon %s first.', 'geodirectory-converter'),
				"<a href='$url'>",
				'</a>'
			);
		}

		return $notes;

	}

	/**
	 * Handles all the other steps
	 *
	 * @since GeoDirectory Converter 1.0.0
	 */
	public function step_3( $fields, $step ) {

		if( $step != 3 ){
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

		//What should we import next?
		$current = 'fields';
		if(! empty($_REQUEST['pmd-current'])  ){
			$current = trim( sanitize_text_field( $_REQUEST['pmd-current'] ) );
		}

		//can we import it?
		if(! method_exists($this, "import_$current") ){
			$error = __('There was a problem importing the data. Please try again.', 'geodirectory-converter');
			GDCONVERTER_Loarder::send_response( 'error', $error );
		}

		//Let's import it
		call_user_func( array( $this, "import_$current") );

	}

	/**
	 * Imports listings
	 *
	 * @since GeoDirectory Converter 1.0.0
	 */
	private function import_listings() {
		global $wpdb;

		$table 				= $this->prefix . 'listings';
		$posts_table 		= $wpdb->posts;
		$places_table		= geodir_db_cpt_table( 'gd_place' );
		$total 				= $this->db->get_var("SELECT COUNT(id) as count from $table");
		$form   			= '<h3>' . __('Importing listings', 'geodirectory-converter') . '</h3>';
		$progress 			= get_transient('_geodir_converter_pmd_progress');
		if(! $progress ){
			$progress = '';
		}
		$form = $progress . $form;

		//Abort early if there are no listings
		if( 0 == $total ){
			$form   .= $this->get_hidden_field_html( 'type', $this->get_next_import_type('listings'));
			$message = '<em>' . __('There are no listings in your PhpMyDirectory installation. Skipping...', 'geodirectory-converter') . '</em><br>';
			$form   .= $message;
			set_transient('_geodir_converter_pmd_progress', $progress . $message, DAY_IN_SECONDS);
			$this->update_progress( $form );
		}
		
		//Where should we start from
		$offset = 0;
		if(! empty($_REQUEST['offset']) ){
			$offset = absint($_REQUEST['offset']);
		}

		//Fetch the listings and abort in case we have imported all of them
		$listings_results 	= $this->db->get_results("SELECT * from $table LIMIT $offset,10");

		if( empty($listings_results)){
			$form  .= $this->get_hidden_field_html( 'type', $this->get_next_import_type('listings'));
			$message = '<em>' . __('Finished importing listings...', 'geodirectory-converter') . '</em><br>';
			$form   .= $message;
			set_transient('_geodir_converter_pmd_progress', $progress . $message, DAY_IN_SECONDS);
			$this->update_progress( $form );
		}

		$imported = 0;
		if(! empty($_REQUEST['imported']) ){
			$imported = absint($_REQUEST['imported']);
		}

		$failed   = 0;
		if( empty($_REQUEST['failed']) ){
			$failed = absint($_REQUEST['failed']);
		}

		foreach ( $listings_results as $key => $listing ){
			$offset ++;

			if( empty( $listing->id ) ){
				$failed ++;
				continue;
			}
			
			//Avoid throwing an error in case a post with this id already exists
			$sql = $wpdb->prepare( "DELETE FROM `$posts_table` WHERE `$posts_table`.`ID` = %d", $listing->id );
			$wpdb->query( $sql );

			//Prepare and insert the listing into the db
			$slug    = ( $listing->friendly_url )? $listing->friendly_url : 'listing-' . $listing->id;
			$status  = ( !empty( $listing->status ) && 'active' == $listing->status )? 'publish': $listing->status;
			$status  = ( !empty( $listing->status ) && 'suspended' == $listing->status )? 'trash': $status;			
			$wpdb->insert(
				$posts_table,
				array(
					'ID' 				=> $listing->id,
					'post_author' 		=> ( $listing->user_id )? $listing->user_id : 1,
					'post_title' 		=> ( $listing->title )? $listing->title : 'NO TITLE',
					'post_name' 		=> $slug,
					'post_excerpt' 		=> ( $listing->description_short )? $listing->description_short : '',
					'post_content' 		=> ( $listing->description )? $listing->description : '',
					'post_date' 		=> ( $listing->date )? $listing->date : date('Y-m-d'),
					'post_date_gmt' 	=> ( $listing->date )? $listing->date : date('Y-m-d'),
					'post_modified' 	=> ( $listing->date_update )? $listing->date_update : date('Y-m-d'),
					'post_modified_gmt' => ( $listing->date_update )? $listing->date_update : date('Y-m-d'),
					'comment_status' 	=> 'open',
					'ping_status' 		=> 'closed',
					'post_parent' 		=> 0,
					'guid' 				=> get_site_url() . '/places/' . $slug,
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

			if( $cats ){
				wp_set_post_terms( $listing->id, $cats, 'gd_placecategory' );
			}

			//In case there was a listing with this id, delete it
			$sql = $wpdb->prepare( "DELETE FROM `{$places_table}` WHERE `{$places_table}`.`post_id` = %d", $listing->id );
			$wpdb->query( $sql );

			$wpdb->insert(
				$places_table,
				array(
					'post_id' 			=> $listing->id,
					'post_title' 		=> ( $listing->title )? $listing->title : 'NO TITLE',
					'post_status' 		=> $status,
					'post_tags' 		=> '',
					'post_category' 	=> $cats,
					'default_category'  => ( $listing->primary_category_id )? $listing->primary_category_id : 0,
					'featured_image' 	=> '',
					'submit_ip' 		=> ( $listing->ip )? $listing->ip : '',
					'overall_rating' 	=> ( $listing->rating )? $listing->rating : 0,
					'rating_count' 		=> ( $listing->rating )? $listing->votes : 0,
					'street' 			=> ( $listing->listing_address1 )? $listing->listing_address1 : '',
					'city' 				=> ( $listing->listing_address2 )? $listing->listing_address2 : '',
					'region' 			=> ( $listing->location_text_1 )? $listing->location_text_1 : '',
					'country' 			=> ( $listing->location_text_2 )? $listing->location_text_2 : '',
					'zip' 				=>  ( $listing->listing_zip )? $listing->listing_zip : '',
					'latitude' 			=> ( $listing->latitude )? $listing->latitude : '',
					'longitude' 		=> ( $listing->longitude )? $listing->longitude : '',
					'mapview' 			=> '',
					'mapzoom' 			=> '',
					'phone' 			=> ( $listing->phone )? $listing->phone : '',
					'post_dummy' 		=> 0,
					'email' 			=> ( $listing->mail )? $listing->mail : '',
					'website' 			=> ( $listing->www )? $listing->www : '',
					'twitter' 			=> ( $listing->twitter_id )? 'http://twitter.com/' . $listing->twitter_id : '',
					'facebook' 			=> ( $listing->facebook_page_id )? 'http://facebook.com/' . $listing->facebook_page_id : '',
					'video' 			=> '',
					'special_offers' 	=> '',
					'business_hours' 	=> ( $listing->hours )? $listing->hours : '',
					'featured' 			=> ( $listing->featured )? $listing->featured : '',
				),
				array( '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%f', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d')
			);

			$imported ++;
		}
		
		//Update the user on their progress
		$form  .= $progress;
		$form  .= "<div><strong>Total Listings &mdash;</strong><em> $total</em></div>";
		$form  .= "<div><strong>Processed Listings &mdash;</strong><em> $offset</em></div>";
		$form  .= "<div><strong>Imported &mdash;</strong><em> $imported</em></div>";
		$form  .= "<div><strong>Failed &mdash;</strong><em> $failed</em></div>";
		$form  .= $this->get_hidden_field_html( 'imported', $imported);
		$form  .= $this->get_hidden_field_html( 'failed', $failed);
		$form  .= $this->get_hidden_field_html( 'type', 'listings');
		$form  .= $this->get_hidden_field_html( 'offset', $offset);
		$this->update_progress( $form, $total, $offset );
	}

	/**
	 * Imports users
	 *
	 * @since GeoDirectory Converter 1.0.0
	 */
	private function import_users() {

		global $wpdb;

		$table	= $this->prefix . 'users';
		$roles	= $this->prefix . 'users_groups_lookup';
		$total 	= $this->db->get_var("SELECT COUNT(id) as count from $table");
		$form   = '<h3>' . __('Importing users', 'geodirectory-converter') . '</h3>';
		$progress 			= get_transient('_geodir_converter_pmd_progress');
		if(! $progress ){
			$progress = '';
		}
		$form   = $progress . $form;
		
		//Abort early if there are no users
		if( 0 == $total ){
			$form  .= $this->get_hidden_field_html( 'type', $this->get_next_import_type('users'));
			$message= '<em>' . __('There are no users in your PhpMyDirectory installation. Skipping...', 'geodirectory-converter') . '</em><br>';
			set_transient('_geodir_converter_pmd_progress', $progress . $message, DAY_IN_SECONDS);
			$form .= $message;
			$this->update_progress( $form );
		}
		
		//Where should we start from
		$offset = 0;
		if(! empty($_REQUEST['offset']) ){
			$offset = absint($_REQUEST['offset']);
		}

		//Fetch the listings and abort in case we have imported all of them
		$pmd_users 	= $this->db->get_results("SELECT * from $table LIMIT $offset,8");
		if( empty($pmd_users) ){
			$form  .= $this->get_hidden_field_html( 'type', $this->get_next_import_type('users'));
			$message= '<em>' . __('Finished importing users...', 'geodirectory-converter') . '</em><br>';
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

			if( empty( $user->id ) || empty( $user->login ) ){
				$failed++;
				continue;
			}

			if( $current_user_id == $user->id ){
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
					'user_pass' 		=> ( $user->pass )? $user->pass : '',
					'user_nicename' 	=> $user->login,
					'user_email' 		=> ( $user->user_email ) ? $user->user_email : '',
					'user_registered' 	=> ( $user->created )? $user->pass : date('Y-m-d'),
					'display_name' 		=> $display_name,
				),
				array('%d','%s','%s','%s','%s','%s','%s' )
			);

			$_user = new WP_User( $user->id );
			$sql  = $wpdb->prepare( "SELECT `group_id` FROM `$roles` WHERE `user_id` = %d", $_user->ID );
			$level = absint( $this->db->get_var($sql) );
			
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
		$form  .= "<div><strong>Total Users &mdash;</strong><em> $total</em></div>";
		$form  .= "<div><strong>Processed Users &mdash;</strong><em> $offset</em></div>";
		$form  .= "<div><strong>Imported &mdash;</strong><em> $imported</em></div>";
		$form  .= "<div><strong>Failed &mdash;</strong><em> $failed</em></div>";
		$form  .= $this->get_hidden_field_html( 'imported', $imported);
		$form  .= $this->get_hidden_field_html( 'failed', $failed);
		$form  .= $this->get_hidden_field_html( 'type', 'users');
		$form  .= $this->get_hidden_field_html( 'offset', $offset);
		$this->update_progress( $form, $total, $offset );

	}

	/**
	 * Handles logins for imported users
	 *
	 * @since GeoDirectory Converter 1.0.0
	 */
	public function handle_login( $user, $password ) {
		
		if (! $user instanceof WP_User ) {
			return $user;
		}

		
		$login= false;
		$hash = get_user_meta( $user->ID, 'pmd_password_hash' );
		$salt = get_user_meta( $user->ID, 'pmd_password_salt' );
		if(!$salt){
			$salt = '';	 
		}
		
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
		$form   			= '<h3>' . __('Importing categories', 'geodirectory-converter') . '</h3>';
		$progress 			= get_transient('_geodir_converter_pmd_progress');
		if(! $progress ){
			$progress = '';
		}
		$form   = $progress . $form;
		
		//Abort early if there are no cats
		if( 0 == $total ){
			$form  .= $this->get_hidden_field_html( 'type', $this->get_next_import_type('categories'));
			$message= '<em>' . __('There are no categories in your PhpMyDirectory installation. Skipping...', 'geodirectory-converter') . '</em><br>';
			set_transient('_geodir_converter_pmd_progress', $progress . $message, DAY_IN_SECONDS);
			$form .= $message;
			$this->update_progress( $form );
		}

		
		//Where should we start from
		$offset = 0;
		if(! empty($_REQUEST['offset']) ){
			$offset = absint($_REQUEST['offset']);
		}

		//Fetch the listings and abort in case we have imported all of them
		$pmd_cats 	= $this->db->get_results("SELECT * from $table LIMIT $offset,10");
		if( empty($pmd_cats)){
			$form  .= $this->get_hidden_field_html( 'type', $this->get_next_import_type('categories'));
			$message= '<em>' . __('Finished importing categories...', 'geodirectory-converter') . '</em><br>';
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

			$sql = $wpdb->prepare( "DELETE FROM `{$wpdb->terms}` WHERE `{$wpdb->terms}`.`term_id` = %d", $cat->id );
			$wpdb->query( $sql );

			$sql = $wpdb->prepare( "DELETE FROM `{$wpdb->term_taxonomy}` WHERE `{$wpdb->term_taxonomy}`.`term_id` = %d", $cat->id );	
			$wpdb->query( $sql );

			$wpdb->insert(
				$wpdb->terms,
				array(
					'term_id' 	  => $cat->id,
					'name' 		  => ( $cat->title ) ? $cat->title : 'Category ' . $cat->id,
					'slug' 		  => ( $cat->friendly_url ) ? $cat->friendly_url : 'category-' . $cat->id,
				),
				array('%d','%s', '%s')
			);
		
			$wpdb->insert(
				$wpdb->term_taxonomy,
				array(
					'term_id' 		=> $cat->id,
					'taxonomy' 		=> 'gd_placecategory',
					'parent' 		=> ( $cat->parent_id ) ? $cat->parent_id : 0,
					'description' 	=> ( $cat->description ) ? $cat->description : '',
					'count' 		=> ( $cat->count_total ) ? $cat->count_total : 0, //? $cat->count??
				),
				array('%d','%s', '%d', '%s', '%d')
			);

			if(! empty($cat->description) ){
				update_term_meta( $cat->id, 'ct_cat_top_desc', $cat->description );
			}

			$imported++;
		}

		//Update the user on their progress
		$form  .= "<div><strong>Total Categories &mdash;</strong><em> $total</em></div>";
		$form  .= "<div><strong>Processed Categories &mdash;</strong><em> $offset</em></div>";
		$form  .= "<div><strong>Imported &mdash;</strong><em> $imported</em></div>";
		$form  .= "<div><strong>Failed &mdash;</strong><em> $failed</em></div>";
		$form  .= $this->get_hidden_field_html( 'imported', $imported);
		$form  .= $this->get_hidden_field_html( 'failed', $failed);
		$form  .= $this->get_hidden_field_html( 'type', 'categories');
		$form  .= $this->get_hidden_field_html( 'offset', $offset);
		$this->update_progress( $form, $total, $offset );
	}

	/**
	 * Imports invoices
	 *
	 * @since GeoDirectory Converter 1.0.0
	 */
	private function import_invoices() {
		global $wpdb;

		$form	= '<h3>' . __('Importing invoices', 'geodirectory-converter') . '</h3>';
		$progress 			= get_transient('_geodir_converter_pmd_progress');
		if(! $progress ){
			$progress = '';
		}
		$form   = $progress . $form;

		//Abort early if the invoicing plugin is not installed
		if ( !defined( 'WPINV_VERSION' ) ) {
			$form  .= $this->get_hidden_field_html( 'type', $this->get_next_import_type('invoices'));
			$message= '<em>' . __('The Invoicing plugin is not active. Skipping...', 'geodirectory-converter') . '</em><br>';
			set_transient('_geodir_converter_pmd_progress', $progress . $message, DAY_IN_SECONDS);
			$form .= $message;
			$this->update_progress( $form );
		}

		$table 				  = $this->prefix . 'invoices';
		$posts_table 		= $wpdb->posts;
		$total 					= $this->db->get_var("SELECT COUNT(id) as count from $table");
		
		//Abort early if there are no invoices
		if( 0 == $total ){
			$form  .= $this->get_hidden_field_html( 'type', $this->get_next_import_type('invoices'));
			$message= '<em>' . __('There are no invoices in your PhpMyDirectory installation. Skipping...', 'geodirectory-converter') . '</em><br>';
			set_transient('_geodir_converter_pmd_progress', $progress . $message, DAY_IN_SECONDS);
			$form .= $message;
			$this->update_progress( $form );
		}
		
		//Where should we start from
		$offset = 0;
		if(! empty($_REQUEST['offset']) ){
			$offset = absint($_REQUEST['offset']);
		}

		//Fetch the invoices and abort in case we have imported all of them
		$pmd_invoices 	= $this->db->get_results("SELECT * from $table LIMIT $offset,10");
		if( empty($pmd_invoices)){
			$form  .= $this->get_hidden_field_html( 'type', $this->get_next_import_type('invoices'));
			$message= '<em>' . __('Finished importing invoices...', 'geodirectory-converter') . '</em><br>';
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
			
			$status = ( !empty( $invoice->status ) && 'unpaid' == $invoice->status )? 'wpi-pending': $invoice->status;
			$status = ( !empty( $invoice->status ) && 'canceled' == $invoice->status )? 'wpi-cancelled': $status;
			$status = ( !empty( $invoice->status ) && 'paid' == $invoice->status )? 'publish': $status;
			$excerpt = ( !empty( $invoice->description ) )? $invoice->description: '';
			
			$id = wp_insert_post( array(
				'post_author'           => ( $invoice->user_id )? $invoice->user_id : 1,
				'post_content'          => ( $invoice->description )? $invoice->description : '',
				'post_content_filtered' => ( $invoice->description )? $invoice->description : '',
				'post_title'            => 'WPINV-00'.$invoice->id ,
				'post_name' 			=> 'inv-'.$invoice->id,
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
		$form  .= "<div><strong>Total Invoices &mdash;</strong><em> $total</em></div>";
		$form  .= "<div><strong>Processed Invoices &mdash;</strong><em> $offset</em></div>";
		$form  .= "<div><strong>Imported &mdash;</strong><em> $imported</em></div>";
		$form  .= "<div><strong>Failed &mdash;</strong><em> $failed</em></div>";
		$form  .= $this->get_hidden_field_html( 'imported', $imported);
		$form  .= $this->get_hidden_field_html( 'failed', $failed);
		$form  .= $this->get_hidden_field_html( 'type', 'invoices');
		$form  .= $this->get_hidden_field_html( 'offset', $offset);
		$this->update_progress( $form, $total, $offset );
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
		$form   				= '<h3>' . __('Importing reviews', 'geodirectory-converter') . '</h3>';
		$progress 			= get_transient('_geodir_converter_pmd_progress');
		if(! $progress ){
			$progress = '';
		}
		$form   = $progress . $form;
		
		//Abort early if there are no reviews
		if( 0 == $total ){
			$form  .= $this->get_hidden_field_html( 'type', $this->get_next_import_type('reviews'));
			$message= '<em>' . __('There are no reviews in your PhpMyDirectory installation. Skipping...', 'geodirectory-converter') . '</em><br>';
			set_transient('_geodir_converter_pmd_progress', $progress . $message, DAY_IN_SECONDS);
			$form .= $message;
			$this->update_progress( $form );
		}
		
		//Where should we start from
		$offset = 0;
		if(! empty($_REQUEST['offset']) ){
			$offset = absint($_REQUEST['offset']);
		}

		//Fetch the reviews and abort in case we have imported all of them
		$pmd_reviews   = $this->db->get_results(
			"SELECT `$table`.`id` as `review_id`, `status`, `listing_id`, `user_id`, `date`, `review`, `user_first_name`, `user_last_name`, `user_email` 
			FROM `$table` LEFT JOIN `$users_table` ON `$table`.`user_id` = `$users_table`.`id`  LIMIT $offset,10");

		if( empty($pmd_reviews)){
			$form  .= $this->get_hidden_field_html( 'type', $this->get_next_import_type('reviews'));
			$message= '<em>' . __('Finished importing reviews...', 'geodirectory-converter') . '</em><br>';
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
		$form  .= "<div><strong>Total Reviews &mdash;</strong><em> $total</em></div>";
		$form  .= "<div><strong>Processed Reviews &mdash;</strong><em> $offset</em></div>";
		$form  .= "<div><strong>Imported &mdash;</strong><em> $imported</em></div>";
		$form  .= "<div><strong>Failed &mdash;</strong><em> $failed</em></div>";
		$form  .= $this->get_hidden_field_html( 'imported', $imported);
		$form  .= $this->get_hidden_field_html( 'failed', $failed);
		$form  .= $this->get_hidden_field_html( 'type', 'reviews');
		$form  .= $this->get_hidden_field_html( 'offset', $offset);
		$this->update_progress( $form, $total, $offset );
	}

	/**
	 * Imports events
	 *
	 * @since GeoDirectory Converter 1.0.0
	 */
	private function import_events() {
		global $wpdb;

		$form	= '<h3>' . __('Importing events', 'geodirectory-converter') . '</h3>';
		$progress 			= get_transient('_geodir_converter_pmd_progress');
		if(! $progress ){
			$progress = '';
		}
		$form   = $progress . $form;

		//Abort early if the events addon is not installed
		if ( !defined( 'GEODIR_EVENT_VERSION' ) ) {
			$form  .= $this->get_hidden_field_html( 'type', $this->get_next_import_type('events'));
			$message = '<em>' . __('The events addon is not active. Skipping...', 'geodirectory-converter') . '</em><br>';
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
			$message = '<em>' . __('There are no events in your PhpMyDirectory installation. Skipping...', 'geodirectory-converter') . '</em><br>';
			set_transient('_geodir_converter_pmd_progress', $progress . $message, DAY_IN_SECONDS);
			$form .= $message;
			$this->update_progress( $form );
		}
		
		//Where should we start from
		$offset = 0;
		if(! empty($_REQUEST['offset']) ){
			$offset = absint($_REQUEST['offset']);
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

		$sql 		 .= "  FROM `$table` LEFT JOIN `$listings_table` ON `$table`.`listing_id` = `$listings_table`.`id`  LIMIT $offset,10 ";
		$pmd_events   = $this->db->get_results( $sql );
		
		if( empty($pmd_events)){
			$form  .= $this->get_hidden_field_html( 'type', $this->get_next_import_type('events'));
			$message= '<em>' . __('Finished importing events...', 'geodirectory-converter') . '</em><br>';
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
		$form  .= "<div><strong>Total Events &mdash;</strong><em> $total</em></div>";
		$form  .= "<div><strong>Processed Events &mdash;</strong><em> $offset</em></div>";
		$form  .= "<div><strong>Imported &mdash;</strong><em> $imported</em></div>";
		$form  .= "<div><strong>Failed &mdash;</strong><em> $failed</em></div>";
		$form  .= $this->get_hidden_field_html( 'imported', $imported);
		$form  .= $this->get_hidden_field_html( 'failed', $failed);
		$form  .= $this->get_hidden_field_html( 'type', 'events');
		$form  .= $this->get_hidden_field_html( 'offset', $offset);
		$this->update_progress( $form, $total, $offset );
	}

	/**
	 * Imports blog posts
	 *
	 * @since GeoDirectory Converter 1.0.0
	 */
	public function import_posts( $fields, $step ) {
		global $wpdb;

		if( $step != 14 ){
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

		$table 				= $this->prefix . 'blog';
		$total 				= $this->db->get_var("SELECT COUNT(id) as count from $table");
			
		//Abort early if there are no blog posts
		if( 0 == $total ){
			$error = __('There are no blog posts in your PhpMyDirectory installation.', 'geodirectory-converter');
			GDCONVERTER_Loarder::send_response( 'error', $error );
		}

		//Fetch the posts
		$posts 	= $this->db->get_results("SELECT * from $table");
		$imported = 0;
		$failed   = 0;

		foreach ( $posts as $key => $post ){

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
		$fields  .= "<div><strong>Total Posts &mdash;</strong><em> $total</em></div>";
		$fields  .= "<div><strong>Imported &mdash;</strong><em> $imported</em></div>";
		$fields  .= "<div><strong>Failed &mdash;</strong><em> $failed</em></div>";
		return $fields;
	}

	/**
	 * Imports fields
	 *
	 * @since GeoDirectory Converter 1.0.0
	 */
	private function import_fields() {
		global $wpdb;

		$table 	= $this->prefix . 'fields';
		$total 	= $this->db->get_var("SELECT COUNT(id) as count from $table");
		$form   = '<h3>' . __('Importing custom fields', 'geodirectory-converter') . '</h3>';
		$progress 			= get_transient('_geodir_converter_pmd_progress');
		if(! $progress ){
			$progress = '';
		}
		$form   = $progress . $form;

		//Abort early if there are no fields
		if( 0 == $total ){
			$form  .= $this->get_hidden_field_html( 'type', $this->get_next_import_type('fields'));
			$message = '<em>' . __('There are no custom fields in your PhpMyDirectory installation. Skipping...', 'geodirectory-converter') . '</em><br>';
			set_transient('_geodir_converter_pmd_progress', $progress . $message, DAY_IN_SECONDS);
			$form .= $message;
			$this->update_progress( $form );
		}
		
		//Where should we start from
		$offset = 0;
		if(! empty($_REQUEST['offset']) ){
			$offset = absint($_REQUEST['offset']);
		}

		//Fetch the fields and abort in case we have imported all of them
		$fields 	= $this->db->get_results("SELECT * from $table LIMIT $offset,3");
		if( empty($fields)){
			$form  .= $this->get_hidden_field_html( 'type', $this->get_next_import_type('fields'));
			$message = '<em>' . __('Finished importing custom fields...', 'geodirectory-converter') . '</em><br>';
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
		$form  .= "<div><strong>Total Fields &mdash;</strong><em> $total</em></div>";
		$form  .= "<div><strong>Processed Fields &mdash;</strong><em> $offset</em></div>";
		$form  .= "<div><strong>Imported &mdash;</strong><em> $imported</em></div>";
		$form  .= "<div><strong>Failed &mdash;</strong><em> $failed</em></div>";
		$form  .= $this->get_hidden_field_html( 'imported', $imported);
		$form  .= $this->get_hidden_field_html( 'failed', $failed);
		$form  .= $this->get_hidden_field_html( 'type', 'fields');
		$form  .= $this->get_hidden_field_html( 'offset', $offset);
		$this->update_progress( $form, $total, $offset );
	}

	/**
	 * Gets the current data to import
	 *
	 * @since GeoDirectory Converter 1.0.0
	 */
	private function get_next_import_type( $current = 'fields' ) {

		$order = array(
			'fields' 			  => 'users',
			'users'  			  => 'categories',
			'categories' 		=> 'listings',
			'listings' 			=> 'reviews',
			'reviews' 			=> 'events',
			'events'			  => 'invoices',
			'invoices'			=> 'done',
			//'pages',
			//'blog'
			//ratings
			//products
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