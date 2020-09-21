<?php
/**
 * Imports data from Listify + WPJM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Converts Listify + WPJM to GeoDirectory
 *
 * @since GeoDirectory Converter 1.0.0
 */
class GDCONVERTER_Listify {

	/**
	 * Stores test mode status
	 *
	 */
	private $test_mode = false;

	/**
	 * The main class constructor
	 * 
	 * Initializes the LIstify's converter and registers custom actions and filter hooks
	 *
	 * @since GeoDirectory Converter 1.0.2
	 *
	 */
	public function __construct() {

		// Set doing import constant.
		if ( defined( 'GEODIR_CONVERTER_TEST_MODE' ) ) {
			$this->test_mode = true;
		}

		// register our converter.
		add_action( 'geodir_converter_importers', array( $this, 'register_importer' ));

		// Render converter form fields.
		add_action( 'geodirectory_listify_importer_fields',	array( $this, 'show_initial_settings' ), 10, 2);
		add_action( 'geodirectory_listify_importer_fields',	array( $this, 'step_2' ), 10, 2);

		// Handles ajax requers for imports progress.
		add_action( 'wp_ajax_gdconverter_listify_handle_progress', array( $this, 'handle_progress' ) );

	}

	/**
	 * Registers the importer
	 *
	 * @param $importers array. An array of registered importers
	 * @since GeoDirectory Converter 1.0.0
	 */
	public function register_importer( $importers ) {
		$importers['listify'] = array(
			'title' 		=> esc_html__( 'Listify', 'geodirectory-converter' ),
			'description' 	=> esc_html__( 'Import listings from your Listify installation.', 'geodirectory-converter' ),
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
		    GDCONVERTER_Loarder::send_response( 'error', $error );
        }

        //Basic security check
	    if ( empty( $_REQUEST['gdconverter_nonce_field'] ) || ! wp_verify_nonce( $_REQUEST['gdconverter_nonce_field'], 'gdconverter_nonce_action' ) ) {
		    $error = esc_html__( 'An unknown error occured! Please refresh the page and try again.', 'geodirectory-converter' );
		    GDCONVERTER_Loarder::send_response( 'error', $error );
        }

		// What data are we currently importing?
		$type = trim( $_REQUEST['type'] );

		//Suspend cache additions
		wp_suspend_cache_addition(true);

		call_user_func( array( $this, "import_" . $type ) );
		
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
					<input type="hidden" name="action" value="gdconverter_listify_handle_progress">
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
	 * Handle the actual import.
	 *
	 * @param $fields Form fields to display to the user
	 * @param $step The current import step
	 * @since GeoDirectory Converter 1.0.2
	 */
	public function show_initial_settings( $fields, $step ) {

		if ( 1 != $step ){
			return $fields;
		}

		//Display the next step to the user
		$title 			= esc_html__( 'Run the import', 'geodirectory-converter');
		$sub_title 		= esc_html__( 'Click the button below to import all your Listify data into GeoDirectory.', 'geodirectory-converter');
		$button			= esc_attr__( 'Start Importing Data', 'geodirectory-converter');
		$notes_title	= esc_html__( 'Important Notes', 'geodirectory-converter');

		$fields .= "<h3 class='geodir-converter-header-success'>$title</h3>";

		$fields .= "<h4>$notes_title</h4> <ul class='geodir-conveter-notes' style='color:red;list-style-type: disc;'>";

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

		return $notes;

	}

	/**
	 * Kickstarts the data import process
	 * @param $fields Form fields to display to the user
	 * @param $step The current import step
	 * @since GeoDirectory Converter 1.0.0
	 */
	public function step_2( $fields, $step ) {

		if ( $step != 2 ){
			return $fields;
		}

		// Then start the import process.
		$this->import_categories();

	}

	/**
	 * Convert Listify hours to GD hours.
	 *
	 * @param array $hours
	 * @param int $offset
	 *
	 * @return string
	 */
	private function get_business_hours(){
		global $post;

		// Retrive post hours.
		$hours = $post->_job_hours;

		// Abort if not set.
		if ( empty( $hours ) ) {
			return 'N;';
		}

		// Map days to strings.
		$new_map = array(
			"1" => "Mo",
			"2" => "Tu",
			"3" => "We",
			"4" => "Th",
			"5" => "Fr",
			"6" => "Sa",
			"0" => "Su",
		);

		$new_parts = array();

		foreach ( $hours as $day => $schedule ) {

			if ( ! isset( $new_map["$day"] ) || empty( $schedule['start'] ) || $schedule['start'] == 'Closed' ) {
				continue;
			}

			$day = $new_map["$day"];

			$start = date( 'H:i', strtotime( $schedule['start'] ) );
			$end   = date( 'H:i', strtotime( $schedule['end'] ) );

			$new_parts[] = "$day $start-$end";
		}

		if ( empty( $new_parts ) ){
			return 'N;';
		}

		// Retrieve the timezone offset.
		$offset = $post->_job_hours_gmt;
		if ( empty( $offset ) ) {
			$offset = get_option('gmt_offset');;
		}

		// ["Mo 09:00-17:00,19:00-23:00","Tu 09:00-17:00","We 09:00-17:00","Th 09:00-17:00","Fr 09:00-17:00"],["UTC":"+1"]
		$new =  wp_json_encode( $new_parts );
		$new .= ',["UTC":"'. $offset .'"]';

		return $new;
	}

	/**
	 * Retrieves featured images.
	 */
	private function get_featured_image() {
		global $post;

		$image = wp_get_attachment_image_src( get_post_thumbnail_id( $post->ID ), 'full' );

		if ( isset( $image[0] ) ) {
			return esc_url( $image[0] );
		}

		return '';
	}

	/**
	 * Get the GD post images string from the gallery images shortcode.
	 *
	 * @return string
	 */
	private function get_gallery_images(){
		global $post;

		$shortcode = $post->_gallery;

		// Set doing import constant so that image paths are added
		if ( ! defined( 'GEODIR_DOING_IMPORT' ) ) {
			define( 'GEODIR_DOING_IMPORT', true );
		}

		// Check if we have gallery images.
		if ( ! has_shortcode( $shortcode, 'gallery' ) ) {
			return '';
		}

		// Get gallery images;
		$gallery = do_shortcode_tag( $shortcode );
		$srcs    = array();

		preg_match_all( '#src=([\'"])(.+?)\1#is', $gallery, $src, PREG_SET_ORDER );
		if ( ! empty( $src ) ) {
			foreach ( $src as $s ) {
				$srcs[] = $s[2];
			}
		}

		$image_string       = '';
		$image_array        = array();

		foreach ( $srcs as $index => $src ){

			// create a random key prefixed with the ordering so that we try to keep the image original ordering via the array keys.
			$key = (int) $index .wp_rand( 100000,900000 );

			$image_array[$key] = array(
				"url"     => $src,
				"title"   => '',
				"caption" => ''
			);
		}

		if ( ! empty( $image_array ) ) {
			foreach ( $image_array as $img) {
				if ( ! $image_string ) {
					$image_string .= $img['url']."||".$img['title']."|".$img['caption'];
				} else {
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

		// Calculate total listings.
		$_total = wp_count_posts( 'job_listing' );
		$total  = 0;
		foreach ( $_total as $post_type => $count ) {
			$total += $count;
		}

		// Update the progress.
		$form     = '<h3>' . esc_html__('Importing listings', 'geodirectory-converter') . '</h3>';
		$progress = get_transient('_geodir_converter_listify_progress');
		if ( ! $progress ) {
			$progress = '';
		}
		$form = $progress . $form;

		// Abort early if there are no listings.
		if ( 0 == $total ) {
			$form   .= $this->get_hidden_field_html( 'type', $this->get_next_import_type( 'listings' ) );
			$message = '<em>' . esc_html__( 'You have no listings. Skipping...', 'geodirectory-converter' ) . '</em><br>';
			$form   .= $message;
			set_transient( '_geodir_converter_listify_progress', $progress . $message, DAY_IN_SECONDS );
			$this->update_progress( $form );
		}

		// Where should we start from.
		$offset = 0;
		if ( ! empty( $_REQUEST['offset'] ) ){
			$offset = absint( $_REQUEST['offset'] );
		}

		// Fetch the listings and abort in case we have imported all of them.
		add_filter( 'get_job_listings_cache_results', '__return_false' );
		$result = get_job_listings( array(
			'offset'         => $offset,
			'posts_per_page' => 5,
			'post_status'    => array( 'publish', 'expired', 'draft' ),
		) );

		if ( ! $result->have_posts() || ( $this->test_mode && $offset > 10 ) ) {
			$form   .= $this->get_hidden_field_html( 'type', $this->get_next_import_type( 'listings' ));
			$message = '<em>' . esc_html__('Finished importing listings...', 'geodirectory-converter') . '</em><br>';
			$form   .= $message;
			set_transient( '_geodir_converter_listify_progress', $progress . $message, DAY_IN_SECONDS );
			$this->update_progress( $form );
		}

		$imported = 0;
		if ( ! empty( $_REQUEST['imported'] ) ){
			$imported = absint( $_REQUEST['imported'] );
		}

		$failed   = 0;
		if ( ! empty( $_REQUEST['failed'] ) ){
			$failed = absint( $_REQUEST['failed'] );
		}

		// Re-enable cache additions.
		wp_suspend_cache_addition(false);

		// Insert the listings into the db.
		while ( $result->have_posts() ) {
			$result->the_post();

			$offset ++;

			$listing = $this->convert_listing();

			// insert post
			$id = wp_insert_post( $listing, true );

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
	 * Converts a listing to GD
	 *
	 * @return array
	 */
	private function convert_listing(){
		global $post, $geodirectory;

		// Prepare the default locations.
		$default_location   = $geodirectory->location->get_default_location();
		$country    		= ! empty( $default_location->country ) ? $default_location->country : '';
		$region     		= ! empty( $default_location->region ) ? $default_location->region : '';
		$city       		= ! empty( $default_location->city ) ? $default_location->city : '';
		$latitude   		= ! empty( $default_location->latitude ) ? $default_location->latitude : '';
		$longitude  		= ! empty( $default_location->longitude ) ? $default_location->longitude : '';
		$categories         = $this->get_categories();
		$listing = array(

			// Standard WP Fields.
			'post_author'           => ( $post->post_author ) ? $post->post_author : get_current_user_id(),
			'post_content'          => ( $post->post_content ) ? $post->post_content : '',
			'post_content_filtered' => wpjm_get_the_job_description(),
			'post_title'            => wpjm_get_the_job_title(),
			'post_excerpt'          => ( $post->post_excerpt ) ? $post->post_excerpt : '',
			'post_status'           => $post->post_status,
			'post_type'             => 'gd_place',
			'comment_status'        => $post->comment_status,
			'ping_status'           => $post->ping_status,
			'post_name'				=> ( $post->post_name ) ? $post->post_name : 'listing-' . $post->ID,
			'post_date_gmt'         => $post->post_date_gmt,
			'post_date'             => $post->post_date,
			'post_modified_gmt'     => $post->post_modified_gmt,
			'post_modified'         => $post->post_modified,
			"tax_input"             => array(
				"gd_placecategory"  =>  $categories,
				"gd_place_tags"     => array(),
			),

			// GD fields.
			'default_category'  => isset( $categories[0] ) ? $categories[0] : 0,
			'featured_image' 	=> $this->get_featured_image(),
			'submit_ip' 		=> '',
			'overall_rating' 	=> 0,
			'rating_count' 		=> 0,
			'street' 			=> ! empty( $post->geolocation_street_number ) ? $post->geolocation_street_number : '',
			'street2' 			=> '',
			'city' 				=> ! empty( $post->geolocation_city ) ? $post->geolocation_city : $city,
			'region' 			=> ! empty( $post->geolocation_state ) ? $post->geolocation_state : $region,
			'country' 			=> ! empty( $post->geolocation_country ) ? $post->geolocation_country : $country,
			'zip' 				=> ! empty( $post->geolocation_postcode ) ? $post->geolocation_postcode : '',
			'latitude' 			=> ! empty( $post->geolocation_lat ) ? $post->geolocation_lat : $latitude,
			'longitude' 		=> ! empty( $post->geolocation_long ) ? $post->geolocation_long : $longitude,
			'mapview' 			=> '',
			'mapzoom' 			=> '',

			// WPJM standard fields.
			'wpjm_id' 			=> $post->ID,
			'company_twitter'   => get_the_company_twitter(),
			'company_video'     => get_the_company_video(),
			'application_method' => $post->_application,
			'featured' 			=> $post->_featured,
			'company_name'      => get_the_company_name(),
			'company_website'   => get_the_company_website(),
			'company_tagline'   => get_the_company_tagline(),
			'company_logo'      => get_the_company_logo(),
			'position_filled'   => $post->_filled,

			// Listify.
			'business_hours' 	=> $this->get_business_hours(),
			'phone'             => $post->_phone,
			'claimed'           => $post->_claimed,
		);

		// add images
		$image_string = $this->get_gallery_images();
		if ( $image_string ) {
			$listing['post_images'] = $image_string;
		}

		return $listing;

	}

	/**
	 * Retrieves the current post's categories.
	 */
	private function get_categories() {

		// Retrieve the categories.
		$categories = wpjm_get_the_job_categories();

		if ( empty( $categories ) ) {
			return array();
		}

		$new = array();

		foreach( $categories as $category ) {
			$new[] = get_term_meta( $category->term_id, 'gd_equivalent', true );
		}
		return array_filter( $new );
		
	}

	/**
	 * Imports categories
	 *
	 * @since GeoDirectory Converter 1.0.0
	 */
	private function import_categories() {

		$form     = '<h3>' . esc_html__( 'Importing categories', 'geodirectory-converter' ) . '</h3>';
		$progress = get_transient( '_geodir_converter_listify_progress' );
		if ( ! $progress ){
			$progress = '';
		}
		$form   = $progress . $form;

		// Abort early if there are no cats.
		if ( ! get_option( 'job_manager_enable_categories' ) || 0 === intval( wp_count_terms( 'job_listing_category' ) ) ) {
			$form  .= $this->get_hidden_field_html( 'type', $this->get_next_import_type( 'categories' ) );
			$message= '<em>' . esc_html__('There are no categories to import. Skipping...', 'geodirectory-converter') . '</em><br>';
			set_transient( '_geodir_converter_listify_progress', $progress . $message, DAY_IN_SECONDS );
			$form .= $message;
			$this->update_progress( $form );
		}

		$categories = get_terms( 'taxonomy=job_listing_category&hide_empty=0&orderby=parent');
		$imported   = 0;
		$failed     = 0;

		foreach ( $categories as $term ) {
			
			// check if the term is already in the new taxonomy & if not create it
			if ( ! ( $id = term_exists( $term->slug, 'gd_placecategory' ) ) ) {
				$args = array(
					'description' => $term->description,
					'slug'        => $term->slug,
				);

				if ( ! empty( $term->parent ) ) {
					$parent = get_term_meta( $term->parent, 'gd_equivalent', true );

					if ( $parent ) {
						$args['parent'] = $parent;
					}
				}

				$id = wp_insert_term( $term->name, 'gd_placecategory', array( 'slug' => $term->slug ) );

			}

			// if the term couldn't be created...
			if ( is_wp_error( $id ) ) {
				$failed ++;
				$form .= '<em style="color: red;">' . $id->get_error_message() . '</em><br>';
				continue;
			}

			if ( ! empty( $term->description ) ){
				update_term_meta( $id['term_id'], 'ct_cat_top_desc', $term->description );
			}

			update_term_meta( $term->term_id, 'gd_equivalent', $id['term_id'] );
			$imported++;

		}

		// Update the user on their progress.
		$imported_text   = esc_html__( 'Imported Categories', 'geodirectory-converter' );
		$failed_text  	 = esc_html__( 'Failed', 'geodirectory-converter' );
		$form  			.= "<div><strong>$imported_text &mdash;</strong><em> $imported</em></div>";
		$form  			.= "<div><strong>$failed_text &mdash;</strong><em> $failed</em></div>";
		$form  			.= $this->get_hidden_field_html( 'type', $this->get_next_import_type( 'categories' ) );
		$this->update_progress( $form );
	}

	/**
	 * Complete import
	 *
	 * @since GeoDirectory Converter 1.0.2
	 */
	private function import_done() {
		$progress	= get_transient('_geodir_converter_listify_progress');
		if(! $progress ){
			$progress = '';
		}
		$form  = '<h2>' . esc_html__( 'Successfully imported your content. Happy Browsing.', 'geodirectory-converter' ) . '</h3>';
		$form .= "<div>$progress</div>";
		delete_transient( '_geodir_converter_listify_progress' );
		GDCONVERTER_Loarder::send_response( 'success', $form );
	}

	/**
	 * Returns an array of WPJM fields.
	 */
	public function get_fields() {

		$allowed_application_method = get_option( 'job_manager_allowed_application_method', '' );
		switch ( $allowed_application_method ) {
			case 'email':
				$application_method_label       = __( 'Application email', 'geodirectory-converter' );
				$application_method_placeholder = __( 'you@example.com', 'geodirectory-converter' );
				$application_method_sanitizer   = 'email';
				break;
			case 'url':
				$application_method_label       = __( 'Application URL', 'geodirectory-converter' );
				$application_method_placeholder = __( 'https://', 'geodirectory-converter' );
				$application_method_sanitizer   = 'url';
				break;
			default:
				$application_method_label       = __( 'Application email/URL', 'geodirectory-converter' );
				$application_method_placeholder = __( 'Enter an email address or website URL', 'geodirectory-converter' );
				$application_method_sanitizer   = 'text';
				break;
		}

		if ( job_manager_multi_job_type() ) {
			$job_type = 'multiselect';
		} else {
			$job_type = 'select';
		}

		return apply_filters(
			'submit_job_form_fields',
			[
				'job'     => [

					'job_title'       => [
						'label'       => __( 'Job Title', 'geodirectory-converter' ),
						'type'        => 'text',
						'required'    => true,
						'placeholder' => '',
						'priority'    => 1,
					],

					'job_location'    => [
						'label'       => __( 'Location', 'geodirectory-converter' ),
						'description' => __( 'Leave this blank if the location is not important', 'geodirectory-converter' ),
						'type'        => 'text',
						'required'    => false,
						'placeholder' => __( 'e.g. "London"', 'geodirectory-converter' ),
						'priority'    => 2,
					],

					'job_type'        => [
						'label'       => __( 'Job type', 'geodirectory-converter' ),
						'type'        => $job_type,
						'required'    => true,
						'placeholder' => __( 'Choose job type&hellip;', 'geodirectory-converter' ),
						'priority'    => 3,
						'default'     => 'Full Time',
					],

					'application'     => [
						'label'       => $application_method_label,
						'type'        => $application_method_sanitizer,
						'required'    => true,
						'placeholder' => $application_method_placeholder,
						'priority'    => 6,
					],

				],
				'company' => [

					'company_name'    => [
						'label'       => __( 'Company name', 'geodirectory-converter' ),
						'type'        => 'text',
						'required'    => true,
						'placeholder' => __( 'Enter the name of the company', 'geodirectory-converter' ),
						'priority'    => 1,
					],

					'company_website' => [
						'label'       => __( 'Website', 'geodirectory-converter' ),
						'type'        => 'text',
						'sanitizer'   => 'url',
						'required'    => false,
						'placeholder' => __( 'http://', 'geodirectory-converter' ),
						'priority'    => 2,
					],

					'company_tagline' => [
						'label'       => __( 'Tagline', 'geodirectory-converter' ),
						'type'        => 'text',
						'required'    => false,
						'placeholder' => __( 'Briefly describe your company', 'geodirectory-converter' ),
						'maxlength'   => 64,
						'priority'    => 3,
					],

					'company_video'   => [
						'label'       => __( 'Video', 'geodirectory-converter' ),
						'type'        => 'text',
						'sanitizer'   => 'url',
						'required'    => false,
						'placeholder' => __( 'A link to a video about your company', 'geodirectory-converter' ),
						'priority'    => 4,
					],

					'company_twitter' => [
						'label'       => __( 'Twitter username', 'geodirectory-converter' ),
						'type'        => 'text',
						'required'    => false,
						'placeholder' => __( '@yourcompany', 'geodirectory-converter' ),
						'priority'    => 5,
					],

					'company_logo'    => [
						'label'              => __( 'Logo', 'geodirectory-converter' ),
						'type'               => 'file',
						'required'           => false,
						'placeholder'        => '',
						'priority'           => 6,
						'ajax'               => true,
						'multiple'           => false,
					],
				],
			]
		);
	}

	/**
	 * Imports standard fields.
	 */
	private function import_standard_fields( $post_type='gd_place', $package_id='' ) {

		$package = ( $package_id=='' ) ? '' : array( $package_id );

		// show on all packages if none set
		if ( ! $package_id && function_exists( 'geodir_pricing_get_packages' ) ) {
			$packages = geodir_pricing_get_packages( array( 'post_type' => $post_type ) );
			if ( ! empty( $packages ) ) {
				$package = array();
				foreach ( $packages as $pkg ) {
					$package[] = $pkg->id;
				}
			}
		}

		// Prepare the standard fields.
		geodir_custom_field_save(
			array(
				'post_type'      => $post_type,
				'data_type'      => 'INT',
				'field_type'     => 'number',
				'admin_title'    => __( 'WPJM ID', 'geodirectory' ),
				'frontend_desc'  => __( 'Original Job ID', 'geodirectory' ),
				'frontend_title' => __( 'Job ID', 'geodirectory' ),
				'htmlvar_name'   => 'wpjm_id',
				'default_value'  => '',
				'is_active' 	 => '1',
				'option_values'  => '',
				'is_default'     => '0',
				'show_in'        =>  '',
				'show_on_pkg'    => $package,
				'for_admin_use'  => true,
				'clabels'        => __( 'WPJM ID', 'geodirectory' )
			)
		);

		foreach ( $this->get_fields() as $group => $fields ) {

			foreach ( $fields as $key => $field ) {

				$args = array(
					'post_type'      => $post_type,
					'data_type'      => 'TEXT',
					'field_type'     => $field['type'],
					'admin_title'    => $field['label'],
					'frontend_desc'  => empty( $field['description'] ) ? '' : $field['description'],
					'placeholder_value' => empty( $field['placeholder'] ) ? '' : $field['placeholder'],
					'frontend_title' => $field['label'],
					'htmlvar_name'   => $key,
					'default_value'  => empty( $field['default'] ) ? '' : $field['default'],
					'is_active'      => '1',
					'is_required'    => empty( $field['required'] ) ? 0 : 1,
					'option_values'  => '',
					'is_default'     => '0',
					'show_in'        =>  ( $key == 'job_title' ) ? '[owntab],[detail],[mapbubble]' : '[owntab],[detail]',
					'show_on_pkg'    => $package,
					'clabels'        => $field['label']
				);

				if ( in_array( $key, array( 'gallery_images', 'featured_image', 'job_title' ) ) ) {
					continue;
				}

				if ( 'file' == $field['type'] ) {
					$args['extra'] = array(
						'gd_file_types'     => array( 'jpg','jpe','jpeg','gif','png','bmp','ico'),
						'file_limit'        => empty( $field['multiple'] ) ? 1 : 100,
					);
				}

				if ( 'checkbox' == $field['type'] ) {
					$args['data_type'] = 'TINYINT';
				}

				if ( 'job_type' == $key ) {
					$job_types = get_terms( 'taxonomy=job_listing_type&hide_empty=0&fields=names');

					// Job types are not enabled.
					if ( ! get_option( 'job_manager_enable_types' ) ) {
						continue;
					}

					if ( is_array( $job_types ) ) {
						$args['option_values'] = implode( ',', $job_types );
					}
				}

				// Listify's business hours.
				if ( 'business-hours' == $args['field_type'] ) {
					$args['field_type']   = 'business_hours';
					$args['htmlvar_name'] = 'business_hours';
					$args['field_icon']   = 'fas fa-clock';
				}

				geodir_custom_field_save( $args );

			}
		}

	}

	/**
	 * Imports fields
	 *
	 * @since GeoDirectory Converter 1.0.0
	 */
	private function import_fields() {

		$form   = '<h3>' . esc_html__('Importing custom fields', 'geodirectory-converter') . '</h3>';
		$progress 	= get_transient('_geodir_converter_listify_progress');
		if ( ! $progress ) {
			$progress = '';
		}

		// Import the fields;
		$this->import_standard_fields();

		$form   = $progress . $form;

		$form  .= $this->get_hidden_field_html( 'type', $this->get_next_import_type( 'fields' ) );
		$message = '<em>' . esc_html__('Finished importing custom fields...', 'geodirectory-converter') . '</em><br>';
		set_transient('_geodir_converter_listify_progress',  $progress . $message, DAY_IN_SECONDS);
		$form .= $message;
		$this->update_progress( $form );

	}

	/**
	 * Gets the current data to import
	 *
	 * @since GeoDirectory Converter 1.0.0
	 */
	private function get_next_import_type( $current = 'categories' ) {

		$order = array(
			'categories' 	    => 'fields',
			'fields'            => 'listings',
			'listings'          => 'done',
		);

		if ( isset( $order[$current] ) ) {
			return $order[ $current ];
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
