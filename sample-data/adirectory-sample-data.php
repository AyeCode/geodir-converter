<?php
/**
 * aDirectory Sample Data Generator.
 *
 * Creates 20 fully populated sample listings with images, custom fields,
 * reviews, business hours, social links, and all taxonomy assignments.
 *
 * Usage: wp eval-file adirectory-sample-data.php
 *
 * @package GeoDir_Converter
 */

defined( 'ABSPATH' ) || exit;

/**
 * Generate all aDirectory sample data.
 */
function adirectory_generate_sample_data() {
	echo "=== aDirectory Sample Data Generator ===\n\n";

	adirectory_register_types();

	$directory_types = adirectory_create_directory_types();
	$categories      = adirectory_create_categories();
	$tags            = adirectory_create_tags();
	$locations       = adirectory_create_locations();

	adirectory_create_listings( $directory_types, $categories, $tags, $locations );

	echo "\n=== Done ===\n";
}

/**
 * Register aDirectory custom taxonomies and post types.
 */
function adirectory_register_types() {
	if ( ! taxonomy_exists( 'adqs_listing_types' ) ) {
		register_taxonomy( 'adqs_listing_types', array(), array(
			'hierarchical' => true,
			'public'       => true,
			'label'        => 'Directory Types',
		) );
	}

	if ( ! taxonomy_exists( 'adqs_category' ) ) {
		register_taxonomy( 'adqs_category', array(), array(
			'hierarchical' => true,
			'public'       => true,
			'label'        => 'Categories',
		) );
	}

	if ( ! taxonomy_exists( 'adqs_tags' ) ) {
		register_taxonomy( 'adqs_tags', array(), array(
			'hierarchical' => false,
			'public'       => true,
			'label'        => 'Tags',
		) );
	}

	if ( ! taxonomy_exists( 'adqs_location' ) ) {
		register_taxonomy( 'adqs_location', array(), array(
			'hierarchical' => true,
			'public'       => true,
			'label'        => 'Locations',
		) );
	}

	echo "Registered taxonomies.\n";
}

/**
 * Get or create a term, returns term_id.
 *
 * @param string $name     Term name.
 * @param string $taxonomy Taxonomy slug.
 * @param array  $args     Optional args (slug, parent).
 * @return int Term ID.
 */
function adirectory_get_or_create_term( $name, $taxonomy, $args = array() ) {
	$slug = isset( $args['slug'] ) ? $args['slug'] : sanitize_title( $name );
	$existing = get_term_by( 'slug', $slug, $taxonomy );
	if ( $existing ) {
		return $existing->term_id;
	}
	$term = wp_insert_term( $name, $taxonomy, $args );
	return is_wp_error( $term ) ? 0 : $term['term_id'];
}

/**
 * Create directory types with custom field definitions.
 *
 * @return array Directory type data.
 */
function adirectory_create_directory_types() {
	$types = array();

	// --- Business Directory ---
	$term_id   = adirectory_get_or_create_term( 'Business Directory', 'adqs_listing_types', array( 'slug' => 'business-directory' ) );
	$post_type = 'adqs_business';

	if ( ! post_type_exists( $post_type ) ) {
		register_post_type( $post_type, array(
			'public'   => true,
			'label'    => 'Business Listings',
			'supports' => array( 'title', 'editor', 'excerpt', 'thumbnail', 'comments' ),
		) );
		register_taxonomy_for_object_type( 'adqs_category', $post_type );
		register_taxonomy_for_object_type( 'adqs_tags', $post_type );
		register_taxonomy_for_object_type( 'adqs_location', $post_type );
		register_taxonomy_for_object_type( 'adqs_listing_types', $post_type );
	}

	update_term_meta( $term_id, 'adqs_directory_post_type', $post_type );

	$business_fields = array(
		array(
			'sectiontitle' => 'General',
			'id'           => 'general',
			'fields'       => array(
				array( 'input_type' => 'tagline', 'label' => 'Tagline' ),
				array( 'input_type' => 'address', 'label' => 'Address' ),
				array( 'input_type' => 'zip', 'label' => 'Zip Code' ),
				array( 'input_type' => 'map', 'label' => 'Map' ),
				array( 'input_type' => 'phone', 'label' => 'Phone' ),
				array( 'input_type' => 'email', 'label' => 'Email' ),
				array( 'input_type' => 'fax', 'label' => 'Fax' ),
				array( 'input_type' => 'website', 'label' => 'Website' ),
				array( 'input_type' => 'video', 'label' => 'Video' ),
				array( 'input_type' => 'social_media_link', 'label' => 'Social Media Links' ),
			),
		),
		array(
			'sectiontitle' => 'Business Details',
			'id'           => 'business_details',
			'fields'       => array(
				array( 'input_type' => 'pricing', 'label' => 'Pricing' ),
				array( 'input_type' => 'businesshour', 'label' => 'Business Hours' ),
				array( 'input_type' => 'view_count', 'label' => 'View Count' ),
				array(
					'fieldid'    => 'cf_amenities',
					'input_type' => 'checkbox',
					'label'      => 'Amenities',
					'options'    => array(
						array( 'id' => 'amenity_wifi', 'label' => 'WiFi', 'value' => 'wifi' ),
						array( 'id' => 'amenity_parking', 'label' => 'Parking', 'value' => 'parking' ),
						array( 'id' => 'amenity_ac', 'label' => 'Air Conditioning', 'value' => 'ac' ),
						array( 'id' => 'amenity_pet_friendly', 'label' => 'Pet Friendly', 'value' => 'pet_friendly' ),
						array( 'id' => 'amenity_wheelchair', 'label' => 'Wheelchair Accessible', 'value' => 'wheelchair' ),
						array( 'id' => 'amenity_outdoor_seating', 'label' => 'Outdoor Seating', 'value' => 'outdoor_seating' ),
					),
				),
				array(
					'fieldid'    => 'cf_payment_methods',
					'input_type' => 'select',
					'label'      => 'Payment Methods',
					'options'    => array(
						array( 'id' => 'pm_cash', 'value' => 'cash' ),
						array( 'id' => 'pm_credit_card', 'value' => 'credit_card' ),
						array( 'id' => 'pm_debit_card', 'value' => 'debit_card' ),
						array( 'id' => 'pm_paypal', 'value' => 'paypal' ),
						array( 'id' => 'pm_apple_pay', 'value' => 'apple_pay' ),
					),
				),
				array(
					'fieldid'    => 'cf_year_established',
					'input_type' => 'number',
					'label'      => 'Year Established',
				),
				array(
					'fieldid'    => 'cf_number_of_employees',
					'input_type' => 'select',
					'label'      => 'Number of Employees',
					'options'    => array(
						array( 'id' => 'emp_1_10', 'value' => '1-10' ),
						array( 'id' => 'emp_11_50', 'value' => '11-50' ),
						array( 'id' => 'emp_51_200', 'value' => '51-200' ),
						array( 'id' => 'emp_201_500', 'value' => '201-500' ),
						array( 'id' => 'emp_500_plus', 'value' => '500+' ),
					),
				),
				array(
					'fieldid'    => 'cf_about_owner',
					'input_type' => 'textarea',
					'label'      => 'About the Owner',
				),
				array(
					'fieldid'    => 'cf_certifications',
					'input_type' => 'text',
					'label'      => 'Certifications',
				),
			),
		),
		array(
			'sectiontitle' => 'Additional Info',
			'id'           => 'additional_info',
			'fields'       => array(
				array(
					'fieldid'    => 'cf_founding_date',
					'input_type' => 'date',
					'label'      => 'Founding Date',
				),
				array(
					'fieldid'    => 'cf_brand_color',
					'input_type' => 'color',
					'label'      => 'Brand Color',
				),
				array(
					'fieldid'    => 'cf_menu_pdf',
					'input_type' => 'file',
					'label'      => 'Menu PDF',
				),
				array(
					'fieldid'    => 'cf_service_area',
					'input_type' => 'radio',
					'label'      => 'Service Area',
					'options'    => array(
						array( 'id' => 'sa_local', 'value' => 'local' ),
						array( 'id' => 'sa_regional', 'value' => 'regional' ),
						array( 'id' => 'sa_national', 'value' => 'national' ),
						array( 'id' => 'sa_international', 'value' => 'international' ),
					),
				),
			),
		),
	);

	update_term_meta( $term_id, 'adqs_metafields_types', $business_fields );

	$types['Business Directory'] = array(
		'term_id'   => $term_id,
		'post_type' => $post_type,
		'fields'    => $business_fields,
	);

	echo "Created directory type: Business Directory (term_id: {$term_id})\n";

	// --- Events Directory ---
	$term_id   = adirectory_get_or_create_term( 'Events Directory', 'adqs_listing_types', array( 'slug' => 'events-directory' ) );
	$post_type = 'adqs_events';

	if ( ! post_type_exists( $post_type ) ) {
		register_post_type( $post_type, array(
			'public'   => true,
			'label'    => 'Event Listings',
			'supports' => array( 'title', 'editor', 'excerpt', 'thumbnail', 'comments' ),
		) );
		register_taxonomy_for_object_type( 'adqs_category', $post_type );
		register_taxonomy_for_object_type( 'adqs_tags', $post_type );
		register_taxonomy_for_object_type( 'adqs_location', $post_type );
		register_taxonomy_for_object_type( 'adqs_listing_types', $post_type );
	}

	update_term_meta( $term_id, 'adqs_directory_post_type', $post_type );

	$events_fields = array(
		array(
			'sectiontitle' => 'General',
			'id'           => 'evt_general',
			'fields'       => array(
				array( 'input_type' => 'tagline', 'label' => 'Tagline' ),
				array( 'input_type' => 'address', 'label' => 'Address' ),
				array( 'input_type' => 'zip', 'label' => 'Zip Code' ),
				array( 'input_type' => 'map', 'label' => 'Map' ),
				array( 'input_type' => 'phone', 'label' => 'Phone' ),
				array( 'input_type' => 'email', 'label' => 'Email' ),
				array( 'input_type' => 'website', 'label' => 'Website' ),
				array( 'input_type' => 'video', 'label' => 'Video' ),
				array( 'input_type' => 'social_media_link', 'label' => 'Social Media Links' ),
				array( 'input_type' => 'pricing', 'label' => 'Pricing' ),
				array( 'input_type' => 'view_count', 'label' => 'View Count' ),
			),
		),
		array(
			'sectiontitle' => 'Event Details',
			'id'           => 'evt_details',
			'fields'       => array(
				array( 'fieldid' => 'cf_event_date', 'input_type' => 'date', 'label' => 'Event Date' ),
				array( 'fieldid' => 'cf_event_time', 'input_type' => 'time', 'label' => 'Event Time' ),
				array( 'fieldid' => 'cf_event_end_date', 'input_type' => 'date', 'label' => 'Event End Date' ),
				array( 'fieldid' => 'cf_ticket_price', 'input_type' => 'number', 'label' => 'Ticket Price' ),
				array( 'fieldid' => 'cf_ticket_url', 'input_type' => 'url', 'label' => 'Ticket URL' ),
				array(
					'fieldid'    => 'cf_event_type',
					'input_type' => 'select',
					'label'      => 'Event Type',
					'options'    => array(
						array( 'id' => 'et_conference', 'value' => 'conference' ),
						array( 'id' => 'et_workshop', 'value' => 'workshop' ),
						array( 'id' => 'et_concert', 'value' => 'concert' ),
						array( 'id' => 'et_exhibition', 'value' => 'exhibition' ),
						array( 'id' => 'et_meetup', 'value' => 'meetup' ),
						array( 'id' => 'et_festival', 'value' => 'festival' ),
					),
				),
				array(
					'fieldid'    => 'cf_age_restriction',
					'input_type' => 'radio',
					'label'      => 'Age Restriction',
					'options'    => array(
						array( 'id' => 'ar_all_ages', 'value' => 'all_ages' ),
						array( 'id' => 'ar_18_plus', 'value' => '18_plus' ),
						array( 'id' => 'ar_21_plus', 'value' => '21_plus' ),
					),
				),
			),
		),
		array(
			'sectiontitle' => 'Organizer Info',
			'id'           => 'evt_organizer',
			'fields'       => array(
				array( 'fieldid' => 'cf_organizer_name', 'input_type' => 'text', 'label' => 'Organizer Name' ),
				array( 'fieldid' => 'cf_organizer_email', 'input_type' => 'email', 'label' => 'Organizer Email' ),
			),
		),
	);

	update_term_meta( $term_id, 'adqs_metafields_types', $events_fields );

	$types['Events Directory'] = array(
		'term_id'   => $term_id,
		'post_type' => $post_type,
		'fields'    => $events_fields,
	);

	echo "Created directory type: Events Directory (term_id: {$term_id})\n";

	return $types;
}

/**
 * Create categories.
 *
 * @return array Map of name => term_id.
 */
function adirectory_create_categories() {
	$cats = array();

	$parents = array(
		'Restaurants & Food'    => 'restaurants-food',
		'Hotels & Lodging'      => 'hotels-lodging',
		'Shopping & Retail'     => 'shopping-retail',
		'Health & Medical'      => 'health-medical',
		'Professional Services' => 'professional-services',
		'Entertainment'         => 'entertainment',
		'Automotive'            => 'automotive',
		'Education'             => 'education',
		'Real Estate'           => 'real-estate',
		'Technology'            => 'technology',
	);

	foreach ( $parents as $name => $slug ) {
		$cats[ $name ] = adirectory_get_or_create_term( $name, 'adqs_category', array( 'slug' => $slug ) );
	}

	$children = array(
		'Restaurants & Food' => array(
			'Italian' => 'italian', 'Chinese' => 'chinese', 'Mexican' => 'mexican',
			'Japanese' => 'japanese', 'Fast Food' => 'fast-food', 'Fine Dining' => 'fine-dining',
			'Cafes & Coffee' => 'cafes-coffee', 'Bakeries' => 'bakeries', 'Seafood' => 'seafood',
			'BBQ & Grill' => 'bbq-grill',
		),
		'Health & Medical' => array(
			'Doctors' => 'doctors', 'Dentists' => 'dentists', 'Pharmacies' => 'pharmacies',
			'Hospitals' => 'hospitals', 'Chiropractors' => 'chiropractors', 'Veterinary' => 'veterinary',
		),
		'Entertainment' => array(
			'Movies & Cinema' => 'movies-cinema', 'Live Music' => 'live-music',
			'Theater' => 'theater', 'Nightlife' => 'nightlife', 'Sports & Rec' => 'sports-rec',
		),
		'Shopping & Retail' => array(
			'Clothing' => 'clothing', 'Electronics' => 'electronics', 'Bookstores' => 'bookstores',
			'Grocery' => 'grocery',
		),
	);

	foreach ( $children as $parent_name => $child_list ) {
		foreach ( $child_list as $name => $slug ) {
			$cats[ $name ] = adirectory_get_or_create_term( $name, 'adqs_category', array(
				'slug'   => $slug,
				'parent' => $cats[ $parent_name ],
			) );
		}
	}

	echo 'Created ' . count( $cats ) . " categories.\n";
	return $cats;
}

/**
 * Create tags.
 *
 * @return array Map of name => term_id.
 */
function adirectory_create_tags() {
	$tag_names = array(
		'Free WiFi', 'Pet Friendly', 'Wheelchair Accessible', 'Family Friendly',
		'Open Late', 'Delivery Available', 'Takeout', 'Outdoor Seating',
		'Live Music', 'Happy Hour', 'Vegan Options', 'Gluten Free',
		'Organic', 'Locally Owned', 'Certified', 'Award Winning',
		'New', 'Popular', 'Top Rated', 'Budget Friendly',
		'Luxury', 'Open 24 Hours', 'Reservations Required', 'Walk-ins Welcome',
	);

	$tags = array();
	foreach ( $tag_names as $name ) {
		$tags[ $name ] = adirectory_get_or_create_term( $name, 'adqs_tags' );
	}

	echo 'Created ' . count( $tags ) . " tags.\n";
	return $tags;
}

/**
 * Create locations.
 *
 * @return array Map of name => term_id.
 */
function adirectory_create_locations() {
	$locs = array();

	$us = adirectory_get_or_create_term( 'United States', 'adqs_location', array( 'slug' => 'united-states' ) );
	$locs['United States'] = $us;

	$states = array(
		'New York'   => array( 'slug' => 'new-york', 'cities' => array( 'Manhattan' => 'manhattan', 'Brooklyn' => 'brooklyn', 'Queens' => 'queens' ) ),
		'California' => array( 'slug' => 'california', 'cities' => array( 'Los Angeles' => 'los-angeles', 'San Francisco' => 'san-francisco', 'San Diego' => 'san-diego' ) ),
		'Texas'      => array( 'slug' => 'texas', 'cities' => array( 'Houston' => 'houston', 'Austin' => 'austin', 'Dallas' => 'dallas' ) ),
		'Illinois'   => array( 'slug' => 'illinois', 'cities' => array( 'Chicago' => 'chicago' ) ),
		'Florida'    => array( 'slug' => 'florida', 'cities' => array( 'Miami' => 'miami' ) ),
	);

	foreach ( $states as $state_name => $state_data ) {
		$state_id = adirectory_get_or_create_term( $state_name, 'adqs_location', array( 'slug' => $state_data['slug'], 'parent' => $us ) );
		$locs[ $state_name ] = $state_id;

		foreach ( $state_data['cities'] as $city_name => $city_slug ) {
			$locs[ $city_name ] = adirectory_get_or_create_term( $city_name, 'adqs_location', array( 'slug' => $city_slug, 'parent' => $state_id ) );
		}
	}

	echo 'Created ' . count( $locs ) . " locations.\n";
	return $locs;
}

/**
 * Create a placeholder image attachment.
 *
 * @param string $title  Image title.
 * @param int    $width  Image width.
 * @param int    $height Image height.
 * @param string $color  Hex color (without #).
 * @param string $text   Text to render on the image.
 * @return int Attachment ID.
 */
function adirectory_create_placeholder_image( $title, $width = 800, $height = 600, $color = '4A90D9', $text = '' ) {
	$upload_dir = wp_upload_dir();
	$filename   = sanitize_file_name( $title . '-' . uniqid() ) . '.jpg';
	$filepath   = $upload_dir['path'] . '/' . $filename;

	// Create image with GD.
	if ( function_exists( 'imagecreatetruecolor' ) ) {
		$img = imagecreatetruecolor( $width, $height );

		$r = hexdec( substr( $color, 0, 2 ) );
		$g = hexdec( substr( $color, 2, 2 ) );
		$b = hexdec( substr( $color, 4, 2 ) );
		$bg = imagecolorallocate( $img, $r, $g, $b );
		imagefill( $img, 0, 0, $bg );

		// Add text.
		$white = imagecolorallocate( $img, 255, 255, 255 );
		$label = ! empty( $text ) ? $text : $title;

		// Center the text.
		$font_size = 5;
		$text_width  = imagefontwidth( $font_size ) * strlen( $label );
		$text_height = imagefontheight( $font_size );
		$x = max( 0, ( $width - $text_width ) / 2 );
		$y = ( $height - $text_height ) / 2;
		imagestring( $img, $font_size, (int) $x, (int) $y, $label, $white );

		imagejpeg( $img, $filepath, 90 );
		imagedestroy( $img );
	} else {
		// Fallback: create a 1x1 pixel JPEG.
		file_put_contents( $filepath, base64_decode( '/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/2wBDAQkJCQwLDBgNDRgyIRwhMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjL/wAARCAABAAEDASIAAhEBAxEB/8QAFAABAAAAAAAAAAAAAAAAAAAACf/EABQQAQAAAAAAAAAAAAAAAAAAAAD/xAAUAQEAAAAAAAAAAAAAAAAAAAAA/8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAwDAQACEQMRAD8AKwA//9k=' ) );
	}

	$filetype = wp_check_filetype( $filename );
	$attachment_id = wp_insert_attachment( array(
		'guid'           => $upload_dir['url'] . '/' . $filename,
		'post_mime_type' => $filetype['type'],
		'post_title'     => $title,
		'post_content'   => '',
		'post_status'    => 'inherit',
	), $filepath );

	if ( ! is_wp_error( $attachment_id ) ) {
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$metadata = wp_generate_attachment_metadata( $attachment_id, $filepath );
		wp_update_attachment_metadata( $attachment_id, $metadata );
	}

	return $attachment_id;
}

/**
 * Create multiple gallery images for a listing.
 *
 * @param string $prefix Listing name prefix.
 * @param array  $images Array of [title, color].
 * @return array Attachment IDs.
 */
function adirectory_create_gallery_images( $prefix, $images ) {
	$ids = array();
	foreach ( $images as $img ) {
		$id = adirectory_create_placeholder_image( $prefix . ' - ' . $img[0], 800, 600, $img[1], $img[0] );
		if ( $id && ! is_wp_error( $id ) ) {
			$ids[] = $id;
		}
	}
	return $ids;
}

/**
 * Build a standard business hours array.
 *
 * @param array $schedule Assoc array of day => [[open,close],...] or 'closed' or '24h'.
 * @return string Serialized business data.
 */
function adirectory_build_hours( $schedule ) {
	$data = array( 'status' => 'open_specific' );

	$days = array( 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday' );

	foreach ( $days as $day ) {
		if ( ! isset( $schedule[ $day ] ) || $schedule[ $day ] === 'closed' ) {
			$data[ $day ] = array( 'enable' => 'off' );
		} elseif ( $schedule[ $day ] === '24h' ) {
			$data[ $day ] = array( 'enable' => 'on', 'open_24' => 'on' );
		} else {
			$day_data = array( 'enable' => 'on' );
			foreach ( $schedule[ $day ] as $i => $slot ) {
				// Convert 24h format to 12h AM/PM format to match aDirectory's select options.
				$day_data[ $i ] = array(
					'open'  => gmdate( 'h:i A', strtotime( $slot[0] ) ),
					'close' => gmdate( 'h:i A', strtotime( $slot[1] ) ),
				);
			}
			$data[ $day ] = $day_data;
		}
	}

	return $data;
}

/**
 * Create all sample listings.
 *
 * @param array $directory_types Directory type data.
 * @param array $categories      Category term IDs.
 * @param array $tags            Tag term IDs.
 * @param array $locations       Location term IDs.
 */
function adirectory_create_listings( $directory_types, $categories, $tags, $locations ) {
	$author_id     = get_current_user_id() ?: 1;
	$business_type = $directory_types['Business Directory'];
	$events_type   = $directory_types['Events Directory'];

	// =========================================================================
	// BUSINESS LISTINGS (15)
	// =========================================================================

	$business_listings = array(

		// --- 1: Full-featured Italian restaurant ---
		array(
			'post_data' => array(
				'post_title'   => 'The Golden Fork Italian Restaurant',
				'post_content' => '<p>Experience authentic Italian cuisine in the heart of Manhattan. Our award-winning chef brings the flavors of Tuscany to your table with handmade pasta, wood-fired pizzas, and an extensive wine list featuring over 200 Italian wines.</p><p>Features a stunning interior with exposed brick walls, warm lighting, and a private dining room for special occasions.</p>',
				'post_excerpt' => 'Award-winning Italian restaurant with handmade pasta and wood-fired pizzas.',
				'post_status'  => 'publish',
			),
			'meta' => array(
				'_address'     => '123 Main Street, Manhattan, New York, NY 10001',
				'_phone'       => '+1 (212) 555-0101',
				'_email'       => 'info@goldenfork.example.com',
				'_fax'         => '+1 (212) 555-0102',
				'_website'     => 'https://www.goldenfork.example.com',
				'_video'       => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
				'_tagline'     => 'Authentic Italian. Unforgettable Experience.',
				'_zip'         => '10001',
				'_map_lat'     => '40.7484',
				'_map_lon'     => '-73.9967',
				'_hide_map'    => '0',
				'_price_type'  => '_price_range',
				'_price'       => '25',
				'_price_sub'   => '75',
				'_price_range' => 'moderate',
				'_is_featured' => 'yes',
				'_expiry_date' => '2027-12-31',
				'_expiry_never' => 'no',
				'_view_count'  => '1523',
				'adqs_social_media_link' => wp_json_encode( array(
					array( 'url' => 'https://facebook.com/goldenfork', 'label' => 'Facebook', 'icon' => 'fab fa-facebook' ),
					array( 'url' => 'https://twitter.com/goldenfork', 'label' => 'Twitter', 'icon' => 'fab fa-twitter' ),
					array( 'url' => 'https://instagram.com/goldenfork', 'label' => 'Instagram', 'icon' => 'fab fa-instagram' ),
					array( 'url' => 'https://www.yelp.com/biz/goldenfork', 'label' => 'Yelp', 'icon' => 'fab fa-yelp' ),
				) ),
				'_checkbox_cf_amenities'         => array( 'wifi', 'parking', 'ac', 'wheelchair', 'outdoor_seating' ),
				'_select_cf_payment_methods'     => 'credit_card',
				'_number_cf_year_established'    => '1998',
				'_select_cf_number_of_employees' => '11-50',
				'_textarea_cf_about_owner'       => 'Chef Giovanni Rossi trained at the Culinary Institute of Bologna and has been bringing authentic Italian flavors to New York for over 25 years.',
				'_text_cf_certifications'        => 'Michelin Guide, James Beard Nominee, AAA Four Diamond',
				'_date_cf_founding_date'         => '1998-06-15',
				'_color_cf_brand_color'          => '#C41E3A',
				'_radio_cf_service_area'         => 'local',
			),
			'hours' => array(
				'monday'    => array( array( '11:00', '15:00' ), array( '17:00', '23:00' ) ),
				'tuesday'   => array( array( '11:00', '15:00' ), array( '17:00', '23:00' ) ),
				'wednesday' => array( array( '11:00', '15:00' ), array( '17:00', '23:00' ) ),
				'thursday'  => array( array( '11:00', '15:00' ), array( '17:00', '23:00' ) ),
				'friday'    => array( array( '11:00', '15:00' ), array( '17:00', '00:00' ) ),
				'saturday'  => array( array( '10:00', '00:00' ) ),
				'sunday'    => array( array( '10:00', '22:00' ) ),
			),
			'images' => array(
				array( 'Dining Room', 'C41E3A' ),
				array( 'Wood Fired Pizza', 'D4A84B' ),
				array( 'Wine Cellar', '5B2333' ),
				array( 'Outdoor Patio', '4CAF50' ),
			),
			'categories' => array( 'Restaurants & Food', 'Italian', 'Fine Dining' ),
			'tags'       => array( 'Outdoor Seating', 'Award Winning', 'Reservations Required', 'Vegan Options' ),
			'locations'  => array( 'Manhattan' ),
			'reviews'    => array(
				array( 'author' => 'John Smith', 'email' => 'john.smith@example.com', 'content' => 'Absolutely incredible dining experience! The homemade pasta was perfection and the wine pairing was spot on.', 'rating' => 5, 'date' => '2024-01-15 19:30:00' ),
				array( 'author' => 'Sarah Johnson', 'email' => 'sarah.j@example.com', 'content' => 'Great atmosphere and good food. The tiramisu was the best I\'ve had outside of Italy.', 'rating' => 4, 'date' => '2024-02-20 20:15:00' ),
				array( 'author' => 'Mike Chen', 'email' => 'mike.chen@example.com', 'content' => 'Good food but overpriced. The portions could be bigger for the price point.', 'rating' => 3, 'date' => '2024-03-10 21:00:00' ),
			),
		),

		// --- 2: 24/7 Convenience store, pending ---
		array(
			'post_data' => array(
				'post_title'   => 'QuickStop 24-Hour Convenience Store',
				'post_content' => '<p>Your neighborhood convenience store, open 24 hours a day, 7 days a week. Groceries, snacks, beverages, household items, and pharmacy basics.</p>',
				'post_excerpt' => 'Open 24/7 convenience store.',
				'post_status'  => 'pending',
			),
			'meta' => array(
				'_address'     => '456 Broadway, Brooklyn, New York, NY 11201',
				'_phone'       => '+1 (718) 555-0201',
				'_email'       => 'quickstop@example.com',
				'_website'     => 'https://quickstop.example.com',
				'_tagline'     => 'Always Open. Always Ready.',
				'_zip'         => '11201',
				'_map_lat'     => '40.6892',
				'_map_lon'     => '-73.9857',
				'_price_range' => 'bellow_economy',
				'_is_featured' => 'no',
				'_expiry_never' => 'yes',
				'_view_count'  => '342',
				'_checkbox_cf_amenities'     => array( 'parking' ),
				'_select_cf_payment_methods' => 'cash',
				'_radio_cf_service_area'     => 'local',
			),
			'hours' => 'always_open',
			'images' => array(
				array( 'Store Front', '2196F3' ),
				array( 'Interior', '64B5F6' ),
			),
			'categories' => array( 'Shopping & Retail', 'Grocery' ),
			'tags'       => array( 'Open 24 Hours', 'Budget Friendly', 'Walk-ins Welcome' ),
			'locations'  => array( 'Brooklyn' ),
			'reviews'    => array(
				array( 'author' => 'Alex Rivera', 'email' => 'alex.r@example.com', 'content' => 'Love that they\'re open 24/7. Great for late night snacks.', 'rating' => 4, 'date' => '2024-04-01 02:30:00' ),
			),
		),

		// --- 3: Closed bookshop, draft ---
		array(
			'post_data' => array(
				'post_title'     => 'Old Town Bookshop (Permanently Closed)',
				'post_content'   => '<p>This beloved independent bookshop served the community for over 40 years before closing its doors in 2024.</p>',
				'post_excerpt'   => 'Historic bookshop, permanently closed.',
				'post_status'    => 'draft',
				'comment_status' => 'closed',
			),
			'meta' => array(
				'_address'     => '789 5th Avenue, Manhattan, New York, NY 10022',
				'_phone'       => '+1 (212) 555-0301',
				'_email'       => 'info@oldtownbooks.example.com',
				'_zip'         => '10022',
				'_map_lat'     => '40.7636',
				'_map_lon'     => '-73.9712',
				'_is_featured' => 'no',
				'_expiry_date' => '2024-06-30',
				'_expiry_never' => 'no',
				'_view_count'  => '89',
				'_number_cf_year_established' => '1982',
			),
			'hours'      => 'closed',
			'images'     => array(),
			'categories' => array( 'Shopping & Retail', 'Bookstores' ),
			'tags'       => array( 'Locally Owned' ),
			'locations'  => array( 'Manhattan' ),
			'reviews'    => array(),
		),

		// --- 4: Medical practice, private, no coordinates ---
		array(
			'post_data' => array(
				'post_title'   => 'Dr. Emily Watson - Family Medicine',
				'post_content' => '<p>Comprehensive family medicine practice providing primary care for patients of all ages. Services include annual physicals, immunizations, chronic disease management, and minor procedures.</p><p>We accept most major insurance plans and offer telehealth appointments.</p>',
				'post_excerpt' => 'Experienced family medicine doctor accepting new patients.',
				'post_status'  => 'private',
			),
			'meta' => array(
				'_address'     => '1200 Medical Center Drive, Suite 300, San Francisco, CA 94102',
				'_phone'       => '+1 (415) 555-0401',
				'_email'       => 'dr.watson@example.com',
				'_fax'         => '+1 (415) 555-0402',
				'_website'     => 'https://www.drwatson-familymed.example.com',
				'_tagline'     => 'Your Health, Our Priority.',
				'_zip'         => '94102',
				'_price_range' => 'economy',
				'_is_featured' => 'yes',
				'_expiry_never' => 'yes',
				'_view_count'  => '756',
				'adqs_social_media_link' => wp_json_encode( array(
					array( 'url' => 'https://linkedin.com/in/drwatson', 'label' => 'LinkedIn', 'icon' => 'fab fa-linkedin' ),
					array( 'url' => 'https://healthgrades.com/drwatson', 'label' => 'Healthgrades', 'icon' => 'fab fa-linkedin' ),
				) ),
				'_checkbox_cf_amenities'     => array( 'wheelchair', 'parking' ),
				'_select_cf_payment_methods' => 'credit_card',
				'_text_cf_certifications'    => 'Board Certified, UCSF Medical School, AMA Member',
				'_radio_cf_service_area'     => 'regional',
			),
			'hours' => array(
				'monday'    => array( array( '08:00', '17:00' ) ),
				'tuesday'   => array( array( '08:00', '17:00' ) ),
				'wednesday' => array( array( '08:00', '17:00' ) ),
				'thursday'  => array( array( '08:00', '17:00' ) ),
				'friday'    => array( array( '08:00', '12:00' ) ),
				'saturday'  => 'closed',
				'sunday'    => 'closed',
			),
			'images' => array(
				array( 'Office Exterior', '1565C0' ),
				array( 'Waiting Room', '42A5F5' ),
				array( 'Exam Room', '90CAF9' ),
			),
			'categories' => array( 'Health & Medical', 'Doctors' ),
			'tags'       => array( 'Wheelchair Accessible', 'Certified', 'Top Rated' ),
			'locations'  => array( 'San Francisco' ),
			'reviews'    => array(
				array( 'author' => 'Patricia Lee', 'email' => 'plee@example.com', 'content' => 'Dr. Watson is thorough, patient, and truly cares. Highly recommended!', 'rating' => 5, 'date' => '2024-05-12 10:30:00' ),
				array( 'author' => 'Robert Garcia', 'email' => 'rgarcia@example.com', 'content' => 'Good doctor but long wait times. The office staff could be friendlier.', 'rating' => 3, 'date' => '2024-06-05 14:00:00' ),
			),
		),

		// --- 5: Auto repair, expired ---
		array(
			'post_data' => array(
				'post_title'   => 'Sunset Auto Repair & Service',
				'post_content' => '<p>Full-service automotive repair shop specializing in domestic and foreign vehicles. ASE certified mechanics with 20+ years of experience.</p><p>Services: Oil changes, brake repair, engine diagnostics, transmission service, tire rotation, AC repair, and state inspection.</p>',
				'post_excerpt' => 'ASE certified auto repair for all makes and models.',
				'post_status'  => 'expired',
			),
			'meta' => array(
				'_address'     => '5678 Sunset Blvd, Los Angeles, CA 90028',
				'_phone'       => '+1 (323) 555-0501',
				'_email'       => 'service@sunsetauto.example.com',
				'_fax'         => '+1 (323) 555-0502',
				'_website'     => 'https://www.sunsetautorepair.example.com',
				'_video'       => 'https://vimeo.com/123456789',
				'_tagline'     => 'Honest Service. Fair Prices.',
				'_zip'         => '90028',
				'_map_lat'     => '34.0983',
				'_map_lon'     => '-118.3267',
				'_price_type'  => '_price',
				'_price'       => '50',
				'_price_sub'   => '500',
				'_price_range' => 'economy',
				'_is_featured' => 'no',
				'_expiry_date' => '2024-01-01',
				'_expiry_never' => 'no',
				'_view_count'  => '2100',
				'adqs_social_media_link' => wp_json_encode( array(
					array( 'url' => 'https://facebook.com/sunsetauto', 'label' => 'Facebook', 'icon' => 'fab fa-facebook' ),
					array( 'url' => 'https://instagram.com/sunsetauto', 'label' => 'Instagram', 'icon' => 'fab fa-instagram' ),
				) ),
				'_checkbox_cf_amenities'         => array( 'wifi', 'parking', 'wheelchair' ),
				'_select_cf_payment_methods'     => 'credit_card',
				'_number_cf_year_established'    => '2005',
				'_select_cf_number_of_employees' => '1-10',
				'_textarea_cf_about_owner'       => 'Mike Thompson is an ASE Master Technician with over 20 years of automotive repair experience.',
				'_text_cf_certifications'        => 'ASE Master Technician, AAA Approved, BBB A+ Rating',
				'_date_cf_founding_date'         => '2005-03-01',
				'_color_cf_brand_color'          => '#FF6B00',
				'_radio_cf_service_area'         => 'regional',
			),
			'hours' => array(
				'monday'    => array( array( '07:00', '18:00' ) ),
				'tuesday'   => array( array( '07:00', '18:00' ) ),
				'wednesday' => array( array( '07:00', '18:00' ) ),
				'thursday'  => array( array( '07:00', '18:00' ) ),
				'friday'    => array( array( '07:00', '18:00' ) ),
				'saturday'  => array( array( '08:00', '14:00' ) ),
				'sunday'    => 'closed',
			),
			'images' => array(
				array( 'Shop Front', 'FF6B00' ),
				array( 'Service Bay', '795548' ),
				array( 'Waiting Area', 'FFA726' ),
			),
			'categories' => array( 'Automotive' ),
			'tags'       => array( 'Certified', 'Locally Owned', 'Top Rated', 'Walk-ins Welcome' ),
			'locations'  => array( 'Los Angeles' ),
			'reviews'    => array(
				array( 'author' => 'David Williams', 'email' => 'dwilliams@example.com', 'content' => 'Honest mechanics who won\'t upsell unnecessary repairs. Fair prices and quality work.', 'rating' => 5, 'date' => '2023-11-20 11:00:00' ),
				array( 'author' => 'Jennifer Martinez', 'email' => 'jmartinez@example.com', 'content' => 'They fixed my AC when two other shops couldn\'t. Highly recommend!', 'rating' => 5, 'date' => '2023-12-15 15:00:00' ),
				array( 'author' => 'Tom Brown', 'email' => 'tbrown@example.com', 'content' => 'Decent work but took longer than estimated.', 'rating' => 3, 'date' => '2024-01-05 09:30:00' ),
				array( 'author' => 'Lisa Anderson', 'email' => 'landerson@example.com', 'content' => 'Average experience. Nothing special but got the job done.', 'rating' => 2, 'date' => '2024-01-10 16:45:00' ),
			),
		),

		// --- 6: Minimal barbershop ---
		array(
			'post_data' => array(
				'post_title'   => 'Joe\'s Barber Shop',
				'post_content' => 'Traditional barbershop offering haircuts, shaves, and beard trims. No appointment necessary.',
				'post_status'  => 'publish',
			),
			'meta' => array(
				'_address'     => '900 Congress Ave, Austin, TX 78701',
				'_phone'       => '+1 (512) 555-0601',
				'_zip'         => '78701',
				'_map_lat'     => '30.2747',
				'_map_lon'     => '-97.7404',
				'_is_featured' => 'no',
				'_expiry_never' => 'yes',
				'_view_count'  => '45',
				'_radio_cf_service_area' => 'local',
			),
			'hours' => array(
				'monday'    => array( array( '09:00', '18:00' ) ),
				'tuesday'   => array( array( '09:00', '18:00' ) ),
				'wednesday' => array( array( '09:00', '18:00' ) ),
				'thursday'  => array( array( '09:00', '18:00' ) ),
				'friday'    => array( array( '09:00', '18:00' ) ),
				'saturday'  => array( array( '08:00', '15:00' ) ),
				'sunday'    => 'closed',
			),
			'images' => array(
				array( 'Barber Chair', '795548' ),
			),
			'categories' => array( 'Professional Services' ),
			'tags'       => array( 'Walk-ins Welcome', 'Budget Friendly' ),
			'locations'  => array( 'Austin' ),
			'reviews'    => array(),
		),

		// --- 7: Wellness center, map hidden ---
		array(
			'post_data' => array(
				'post_title'   => 'Harmony Wellness Center & Spa',
				'post_content' => '<p>Holistic wellness center offering massage therapy, acupuncture, chiropractic care, yoga classes, and spa treatments. Our team of licensed practitioners is dedicated to helping you achieve optimal health and relaxation.</p>',
				'post_excerpt' => 'Full-service wellness center with massage, acupuncture, and spa.',
				'post_status'  => 'publish',
			),
			'meta' => array(
				'_address'     => '2500 Pacific Coast Highway, San Diego, CA 92101',
				'_phone'       => '+1 (619) 555-0701',
				'_email'       => 'relax@harmonywellness.example.com',
				'_website'     => 'https://harmonywellness.example.com',
				'_tagline'     => 'Balance. Heal. Thrive.',
				'_zip'         => '92101',
				'_map_lat'     => '32.7157',
				'_map_lon'     => '-117.1611',
				'_hide_map'    => '1',
				'_price_range' => 'skimming',
				'_is_featured' => 'yes',
				'_expiry_never' => 'yes',
				'_view_count'  => '890',
				'adqs_social_media_link' => wp_json_encode( array(
					array( 'url' => 'https://instagram.com/harmonywellness', 'label' => 'Instagram', 'icon' => 'fab fa-instagram' ),
					array( 'url' => 'https://facebook.com/harmonywellness', 'label' => 'Facebook', 'icon' => 'fab fa-facebook' ),
					array( 'url' => 'https://youtube.com/harmonywellness', 'label' => 'YouTube', 'icon' => 'fab fa-youtube' ),
				) ),
				'_checkbox_cf_amenities'         => array( 'wifi', 'parking', 'ac', 'pet_friendly', 'wheelchair' ),
				'_select_cf_payment_methods'     => 'apple_pay',
				'_select_cf_number_of_employees' => '11-50',
				'_radio_cf_service_area'         => 'local',
			),
			'hours' => array(
				'monday'    => array( array( '09:00', '20:00' ) ),
				'tuesday'   => array( array( '09:00', '20:00' ) ),
				'wednesday' => array( array( '09:00', '20:00' ) ),
				'thursday'  => array( array( '09:00', '20:00' ) ),
				'friday'    => array( array( '09:00', '21:00' ) ),
				'saturday'  => array( array( '10:00', '18:00' ) ),
				'sunday'    => array( array( '10:00', '16:00' ) ),
			),
			'images' => array(
				array( 'Spa Entrance', '7B1FA2' ),
				array( 'Massage Room', 'CE93D8' ),
				array( 'Yoga Studio', 'AB47BC' ),
				array( 'Meditation Garden', '4CAF50' ),
				array( 'Treatment Room', '9C27B0' ),
			),
			'categories' => array( 'Health & Medical', 'Professional Services' ),
			'tags'       => array( 'Luxury', 'Top Rated', 'Reservations Required', 'Pet Friendly', 'Wheelchair Accessible' ),
			'locations'  => array( 'San Diego' ),
			'reviews'    => array(
				array( 'author' => 'Maria Rodriguez', 'email' => 'maria.r@example.com', 'content' => 'The deep tissue massage was exactly what I needed. Wonderful staff.', 'rating' => 5, 'date' => '2024-07-01 14:00:00' ),
				array( 'author' => 'James Wilson', 'email' => 'jwilson@example.com', 'content' => 'Expensive but worth every penny. The hot stone massage was heavenly.', 'rating' => 4, 'date' => '2024-07-15 16:30:00' ),
			),
		),

		// --- 8: Cafe & coworking ---
		array(
			'post_data' => array(
				'post_title'   => 'Night Owl Cafe & Coworking',
				'post_content' => '<p>A unique combination of specialty coffee shop and coworking space. Enjoy artisan coffee, pastries, and light meals while working in our modern, inspiring environment.</p><p>Features high-speed WiFi, private meeting rooms, standing desks, and a quiet zone.</p>',
				'post_excerpt' => 'Specialty coffee shop and coworking space.',
				'post_status'  => 'publish',
			),
			'meta' => array(
				'_address'     => '350 E 6th St, Austin, TX 78701',
				'_phone'       => '+1 (512) 555-0801',
				'_email'       => 'hello@nightowlcafe.example.com',
				'_website'     => 'https://nightowlcafe.example.com',
				'_video'       => 'https://www.youtube.com/watch?v=abcdef12345',
				'_tagline'     => 'Fuel Your Creativity.',
				'_zip'         => '78701',
				'_map_lat'     => '30.2672',
				'_map_lon'     => '-97.7431',
				'_price_range' => 'economy',
				'_is_featured' => 'no',
				'_expiry_never' => 'yes',
				'_view_count'  => '1200',
				'_checkbox_cf_amenities'         => array( 'wifi', 'ac', 'pet_friendly', 'outdoor_seating' ),
				'_select_cf_payment_methods'     => 'apple_pay',
				'_number_cf_year_established'    => '2019',
				'_select_cf_number_of_employees' => '1-10',
				'_radio_cf_service_area'         => 'local',
			),
			'hours' => array(
				'monday'    => array( array( '06:00', '23:00' ) ),
				'tuesday'   => array( array( '06:00', '23:00' ) ),
				'wednesday' => array( array( '06:00', '23:00' ) ),
				'thursday'  => array( array( '06:00', '23:00' ) ),
				'friday'    => array( array( '06:00', '02:00' ) ),
				'saturday'  => '24h',
				'sunday'    => array( array( '07:00', '22:00' ) ),
			),
			'images' => array(
				array( 'Coffee Bar', '6D4C41' ),
				array( 'Cowork Space', '8D6E63' ),
				array( 'Meeting Room', 'A1887F' ),
			),
			'categories' => array( 'Restaurants & Food', 'Cafes & Coffee' ),
			'tags'       => array( 'Free WiFi', 'Open Late', 'Outdoor Seating', 'Vegan Options', 'New', 'Popular', 'Pet Friendly' ),
			'locations'  => array( 'Austin' ),
			'reviews'    => array(
				array( 'author' => 'Emily Turner', 'email' => 'eturner@example.com', 'content' => 'Best coffee in Austin! Love the coworking setup too.', 'rating' => 5, 'date' => '2024-08-01 09:00:00' ),
			),
		),

		// --- 9: Sushi restaurant ---
		array(
			'post_data' => array(
				'post_title'   => 'Sakura Sushi Bar & Omakase',
				'post_content' => '<p>Premium Japanese sushi restaurant featuring an intimate 12-seat omakase counter helmed by Chef Tanaka. We source fish daily from Tsukiji Market and local sustainable fisheries.</p><p>Also offering a full a la carte menu, sake bar, and private tatami dining rooms.</p>',
				'post_excerpt' => 'Premium sushi bar with daily omakase and sake selection.',
				'post_status'  => 'publish',
			),
			'meta' => array(
				'_address'     => '88 Grant Avenue, San Francisco, CA 94108',
				'_phone'       => '+1 (415) 555-0901',
				'_email'       => 'reservations@sakurasushi.example.com',
				'_website'     => 'https://sakurasushi.example.com',
				'_video'       => 'https://vimeo.com/987654321',
				'_tagline'     => 'From Ocean to Table.',
				'_zip'         => '94108',
				'_map_lat'     => '37.7879',
				'_map_lon'     => '-122.4074',
				'_price_range' => 'skimming',
				'_is_featured' => 'yes',
				'_expiry_never' => 'yes',
				'_view_count'  => '3200',
				'adqs_social_media_link' => wp_json_encode( array(
					array( 'url' => 'https://instagram.com/sakurasushi', 'label' => 'Instagram', 'icon' => 'fab fa-instagram' ),
					array( 'url' => 'https://facebook.com/sakurasushi', 'label' => 'Facebook', 'icon' => 'fab fa-facebook' ),
				) ),
				'_checkbox_cf_amenities'         => array( 'ac', 'wheelchair' ),
				'_select_cf_payment_methods'     => 'credit_card',
				'_number_cf_year_established'    => '2015',
				'_select_cf_number_of_employees' => '11-50',
				'_textarea_cf_about_owner'       => 'Chef Kenji Tanaka trained for 15 years in Tokyo before opening Sakura. His omakase reflects decades of mastery.',
				'_text_cf_certifications'        => 'Michelin One Star, SF Chronicle Top 100',
				'_date_cf_founding_date'         => '2015-09-20',
				'_color_cf_brand_color'          => '#E91E63',
				'_radio_cf_service_area'         => 'local',
			),
			'hours' => array(
				'monday'    => 'closed',
				'tuesday'   => array( array( '17:30', '22:00' ) ),
				'wednesday' => array( array( '17:30', '22:00' ) ),
				'thursday'  => array( array( '17:30', '22:00' ) ),
				'friday'    => array( array( '17:30', '23:00' ) ),
				'saturday'  => array( array( '12:00', '14:30' ), array( '17:30', '23:00' ) ),
				'sunday'    => array( array( '12:00', '14:30' ), array( '17:30', '21:00' ) ),
			),
			'images' => array(
				array( 'Omakase Counter', 'E91E63' ),
				array( 'Sushi Platter', 'F48FB1' ),
				array( 'Sake Selection', 'FCE4EC' ),
				array( 'Tatami Room', 'AD1457' ),
			),
			'categories' => array( 'Restaurants & Food', 'Japanese', 'Fine Dining' ),
			'tags'       => array( 'Award Winning', 'Reservations Required', 'Luxury', 'Gluten Free' ),
			'locations'  => array( 'San Francisco' ),
			'reviews'    => array(
				array( 'author' => 'Diana Chung', 'email' => 'diana.c@example.com', 'content' => 'The omakase was a transcendent experience. Best sushi outside of Japan.', 'rating' => 5, 'date' => '2024-04-20 21:00:00' ),
				array( 'author' => 'Mark Stevens', 'email' => 'mstevens@example.com', 'content' => 'Exquisite quality. The uni was melt-in-your-mouth perfection.', 'rating' => 5, 'date' => '2024-05-10 20:30:00' ),
				array( 'author' => 'Rachel Kim', 'email' => 'rkim@example.com', 'content' => 'Beautiful presentation and incredible flavors. Worth the splurge.', 'rating' => 4, 'date' => '2024-06-18 19:45:00' ),
			),
		),

		// --- 10: Tech repair shop ---
		array(
			'post_data' => array(
				'post_title'   => 'FixIt Tech - Phone & Computer Repair',
				'post_content' => '<p>Expert repair services for smartphones, tablets, laptops, and desktop computers. Screen replacements, battery swaps, data recovery, virus removal, and custom PC builds.</p><p>Most repairs done same-day. Free diagnostics on all devices.</p>',
				'post_excerpt' => 'Same-day phone and computer repair with free diagnostics.',
				'post_status'  => 'publish',
			),
			'meta' => array(
				'_address'     => '4200 Westheimer Rd, Houston, TX 77027',
				'_phone'       => '+1 (713) 555-1001',
				'_email'       => 'repair@fixittech.example.com',
				'_website'     => 'https://fixittech.example.com',
				'_tagline'     => 'We Fix Everything.',
				'_zip'         => '77027',
				'_map_lat'     => '29.7372',
				'_map_lon'     => '-95.4321',
				'_price_range' => 'economy',
				'_is_featured' => 'no',
				'_expiry_never' => 'yes',
				'_view_count'  => '675',
				'adqs_social_media_link' => wp_json_encode( array(
					array( 'url' => 'https://facebook.com/fixittech', 'label' => 'Facebook', 'icon' => 'fab fa-facebook' ),
					array( 'url' => 'https://twitter.com/fixittech', 'label' => 'Twitter', 'icon' => 'fab fa-twitter' ),
				) ),
				'_checkbox_cf_amenities'         => array( 'wifi', 'parking', 'ac' ),
				'_select_cf_payment_methods'     => 'debit_card',
				'_number_cf_year_established'    => '2018',
				'_select_cf_number_of_employees' => '1-10',
				'_text_cf_certifications'        => 'Apple Certified, CompTIA A+, Google Certified',
				'_color_cf_brand_color'          => '#00BCD4',
				'_radio_cf_service_area'         => 'regional',
			),
			'hours' => array(
				'monday'    => array( array( '10:00', '19:00' ) ),
				'tuesday'   => array( array( '10:00', '19:00' ) ),
				'wednesday' => array( array( '10:00', '19:00' ) ),
				'thursday'  => array( array( '10:00', '19:00' ) ),
				'friday'    => array( array( '10:00', '19:00' ) ),
				'saturday'  => array( array( '10:00', '17:00' ) ),
				'sunday'    => 'closed',
			),
			'images' => array(
				array( 'Repair Bench', '00BCD4' ),
				array( 'Store Interior', '0097A7' ),
			),
			'categories' => array( 'Shopping & Retail', 'Electronics', 'Technology' ),
			'tags'       => array( 'Certified', 'Walk-ins Welcome', 'Popular' ),
			'locations'  => array( 'Houston' ),
			'reviews'    => array(
				array( 'author' => 'Carlos Mendez', 'email' => 'cmendez@example.com', 'content' => 'Fixed my cracked iPhone screen in 30 minutes. Great service!', 'rating' => 5, 'date' => '2024-09-01 11:00:00' ),
				array( 'author' => 'Amy Scott', 'email' => 'ascott@example.com', 'content' => 'Recovered all my photos from a dead hard drive. Lifesaver!', 'rating' => 5, 'date' => '2024-09-15 14:30:00' ),
			),
		),

		// --- 11: BBQ restaurant, Dallas ---
		array(
			'post_data' => array(
				'post_title'   => 'Smokey Pete\'s BBQ Pit',
				'post_content' => '<p>Authentic Texas barbecue smoked low and slow over post oak wood for up to 18 hours. Brisket, ribs, pulled pork, sausage links, and all the classic sides.</p><p>Family-owned since 1985. Catering available for events of all sizes.</p>',
				'post_excerpt' => 'Authentic Texas BBQ smoked low and slow over post oak.',
				'post_status'  => 'publish',
			),
			'meta' => array(
				'_address'     => '1800 Elm Street, Dallas, TX 75201',
				'_phone'       => '+1 (214) 555-1101',
				'_email'       => 'orders@smokeypetes.example.com',
				'_website'     => 'https://smokeypetes.example.com',
				'_tagline'     => 'Real Texas BBQ Since 1985.',
				'_zip'         => '75201',
				'_map_lat'     => '32.7831',
				'_map_lon'     => '-96.7998',
				'_price_range' => 'economy',
				'_is_featured' => 'yes',
				'_expiry_never' => 'yes',
				'_view_count'  => '4500',
				'adqs_social_media_link' => wp_json_encode( array(
					array( 'url' => 'https://instagram.com/smokeypetes', 'label' => 'Instagram', 'icon' => 'fab fa-instagram' ),
					array( 'url' => 'https://facebook.com/smokeypetes', 'label' => 'Facebook', 'icon' => 'fab fa-facebook' ),
					array( 'url' => 'https://tiktok.com/@smokeypetes', 'label' => 'TikTok', 'icon' => 'fab fa-tiktok' ),
				) ),
				'_checkbox_cf_amenities'         => array( 'parking', 'outdoor_seating', 'pet_friendly' ),
				'_select_cf_payment_methods'     => 'cash',
				'_number_cf_year_established'    => '1985',
				'_select_cf_number_of_employees' => '11-50',
				'_textarea_cf_about_owner'       => 'Pete Morrison learned the art of BBQ from his grandfather and has been perfecting the craft for over 40 years.',
				'_text_cf_certifications'        => 'Texas Monthly Top 50 BBQ, James Beard Semifinalist',
				'_date_cf_founding_date'         => '1985-07-04',
				'_color_cf_brand_color'          => '#BF360C',
				'_radio_cf_service_area'         => 'regional',
			),
			'hours' => array(
				'monday'    => 'closed',
				'tuesday'   => 'closed',
				'wednesday' => array( array( '11:00', '15:00' ) ),
				'thursday'  => array( array( '11:00', '15:00' ) ),
				'friday'    => array( array( '11:00', '20:00' ) ),
				'saturday'  => array( array( '11:00', '20:00' ) ),
				'sunday'    => array( array( '11:00', '15:00' ) ),
			),
			'images' => array(
				array( 'Brisket', 'BF360C' ),
				array( 'Smoker', '4E342E' ),
				array( 'Rib Plate', 'D84315' ),
				array( 'Dining Area', 'FF8A65' ),
				array( 'Pecan Pie', 'A1887F' ),
			),
			'categories' => array( 'Restaurants & Food', 'BBQ & Grill' ),
			'tags'       => array( 'Award Winning', 'Family Friendly', 'Outdoor Seating', 'Locally Owned', 'Delivery Available', 'Takeout' ),
			'locations'  => array( 'Dallas' ),
			'reviews'    => array(
				array( 'author' => 'Billy Crawford', 'email' => 'billy.c@example.com', 'content' => 'Best brisket in Texas, hands down. The bark is incredible.', 'rating' => 5, 'date' => '2024-03-15 12:30:00' ),
				array( 'author' => 'Sue Ellen', 'email' => 'sellen@example.com', 'content' => 'Worth the 2-hour wait. The ribs fall right off the bone.', 'rating' => 5, 'date' => '2024-04-22 13:00:00' ),
				array( 'author' => 'Nathan Drake', 'email' => 'ndrake@example.com', 'content' => 'Solid BBQ. Not the best I\'ve had but consistently good. Get there early before they sell out.', 'rating' => 4, 'date' => '2024-06-10 11:45:00' ),
			),
		),

		// --- 12: Dental office, Chicago ---
		array(
			'post_data' => array(
				'post_title'   => 'Bright Smiles Dental Care',
				'post_content' => '<p>Modern family dental practice offering comprehensive dental care including cleanings, fillings, crowns, bridges, root canals, Invisalign, and cosmetic dentistry.</p><p>State-of-the-art equipment including digital X-rays and same-day crowns. We make dental visits comfortable and stress-free.</p>',
				'post_excerpt' => 'Modern family dental practice with same-day crowns and Invisalign.',
				'post_status'  => 'publish',
			),
			'meta' => array(
				'_address'     => '333 N Michigan Ave, Suite 1200, Chicago, IL 60601',
				'_phone'       => '+1 (312) 555-1201',
				'_email'       => 'appointments@brightsmiles.example.com',
				'_fax'         => '+1 (312) 555-1202',
				'_website'     => 'https://brightsmilesdental.example.com',
				'_tagline'     => 'A Brighter Smile Starts Here.',
				'_zip'         => '60601',
				'_map_lat'     => '41.8873',
				'_map_lon'     => '-87.6246',
				'_price_range' => 'moderate',
				'_is_featured' => 'no',
				'_expiry_never' => 'yes',
				'_view_count'  => '520',
				'adqs_social_media_link' => wp_json_encode( array(
					array( 'url' => 'https://facebook.com/brightsmilesdental', 'label' => 'Facebook', 'icon' => 'fab fa-facebook' ),
					array( 'url' => 'https://instagram.com/brightsmilesdental', 'label' => 'Instagram', 'icon' => 'fab fa-instagram' ),
				) ),
				'_checkbox_cf_amenities'         => array( 'wifi', 'parking', 'ac', 'wheelchair' ),
				'_select_cf_payment_methods'     => 'credit_card',
				'_number_cf_year_established'    => '2010',
				'_select_cf_number_of_employees' => '11-50',
				'_text_cf_certifications'        => 'ADA Member, Invisalign Preferred Provider, CEREC Certified',
				'_date_cf_founding_date'         => '2010-01-15',
				'_color_cf_brand_color'          => '#00ACC1',
				'_radio_cf_service_area'         => 'local',
			),
			'hours' => array(
				'monday'    => array( array( '08:00', '17:00' ) ),
				'tuesday'   => array( array( '08:00', '17:00' ) ),
				'wednesday' => array( array( '08:00', '17:00' ) ),
				'thursday'  => array( array( '08:00', '19:00' ) ),
				'friday'    => array( array( '08:00', '14:00' ) ),
				'saturday'  => 'closed',
				'sunday'    => 'closed',
			),
			'images' => array(
				array( 'Reception', '00ACC1' ),
				array( 'Treatment Room', '4DD0E1' ),
				array( 'Equipment', '0097A7' ),
			),
			'categories' => array( 'Health & Medical', 'Dentists' ),
			'tags'       => array( 'Certified', 'Wheelchair Accessible', 'Family Friendly' ),
			'locations'  => array( 'Chicago' ),
			'reviews'    => array(
				array( 'author' => 'Kevin O\'Brien', 'email' => 'kobrien@example.com', 'content' => 'Dr. Chen is amazing. Got my crown done in a single visit with their CEREC machine!', 'rating' => 5, 'date' => '2024-02-28 10:00:00' ),
				array( 'author' => 'Jessica Park', 'email' => 'jpark@example.com', 'content' => 'Very gentle and professional. My kids actually look forward to their dental visits now.', 'rating' => 4, 'date' => '2024-05-15 09:00:00' ),
			),
		),

		// --- 13: Mexican restaurant, Miami ---
		array(
			'post_data' => array(
				'post_title'   => 'Casa Oaxaca Mexican Kitchen',
				'post_content' => '<p>Authentic Oaxacan cuisine featuring handmade tortillas, mole negro, tlayudas, and mezcal cocktails. Our ingredients are sourced directly from Oaxaca, Mexico.</p><p>Live mariachi on weekends. Rooftop bar with views of Biscayne Bay.</p>',
				'post_excerpt' => 'Authentic Oaxacan cuisine with rooftop bar and live mariachi.',
				'post_status'  => 'publish',
			),
			'meta' => array(
				'_address'     => '1500 Ocean Drive, Miami, FL 33139',
				'_phone'       => '+1 (305) 555-1301',
				'_email'       => 'hola@casaoaxaca.example.com',
				'_website'     => 'https://casaoaxaca.example.com',
				'_video'       => 'https://youtube.com/watch?v=casaoaxaca',
				'_tagline'     => 'Sabor Autentico de Oaxaca.',
				'_zip'         => '33139',
				'_map_lat'     => '25.7810',
				'_map_lon'     => '-80.1300',
				'_price_range' => 'moderate',
				'_is_featured' => 'yes',
				'_expiry_never' => 'yes',
				'_view_count'  => '2800',
				'adqs_social_media_link' => wp_json_encode( array(
					array( 'url' => 'https://instagram.com/casaoaxaca', 'label' => 'Instagram', 'icon' => 'fab fa-instagram' ),
					array( 'url' => 'https://facebook.com/casaoaxaca', 'label' => 'Facebook', 'icon' => 'fab fa-facebook' ),
					array( 'url' => 'https://tiktok.com/@casaoaxaca', 'label' => 'TikTok', 'icon' => 'fab fa-tiktok' ),
				) ),
				'_checkbox_cf_amenities'         => array( 'wifi', 'ac', 'outdoor_seating' ),
				'_select_cf_payment_methods'     => 'credit_card',
				'_number_cf_year_established'    => '2020',
				'_select_cf_number_of_employees' => '51-200',
				'_textarea_cf_about_owner'       => 'Chef Elena Ramirez grew up cooking alongside her grandmother in a Oaxacan village. She brings those same family recipes to Miami.',
				'_text_cf_certifications'        => 'Miami New Times Best Mexican 2024, Eater Miami Essential',
				'_date_cf_founding_date'         => '2020-11-01',
				'_color_cf_brand_color'          => '#E65100',
				'_radio_cf_service_area'         => 'local',
			),
			'hours' => array(
				'monday'    => array( array( '11:30', '22:00' ) ),
				'tuesday'   => array( array( '11:30', '22:00' ) ),
				'wednesday' => array( array( '11:30', '22:00' ) ),
				'thursday'  => array( array( '11:30', '23:00' ) ),
				'friday'    => array( array( '11:30', '00:00' ) ),
				'saturday'  => array( array( '10:00', '00:00' ) ),
				'sunday'    => array( array( '10:00', '22:00' ) ),
			),
			'images' => array(
				array( 'Rooftop Bar', 'E65100' ),
				array( 'Mole Negro', 'BF360C' ),
				array( 'Handmade Tortillas', 'FFB74D' ),
				array( 'Mezcal Flight', 'FF6F00' ),
			),
			'categories' => array( 'Restaurants & Food', 'Mexican' ),
			'tags'       => array( 'Live Music', 'Happy Hour', 'Outdoor Seating', 'Award Winning', 'New', 'Popular' ),
			'locations'  => array( 'Miami' ),
			'reviews'    => array(
				array( 'author' => 'Sofia Perez', 'email' => 'sperez@example.com', 'content' => 'The mole negro is the real deal. Tastes just like what I had in Oaxaca!', 'rating' => 5, 'date' => '2024-08-20 20:00:00' ),
				array( 'author' => 'Jake Morrison', 'email' => 'jmorrison@example.com', 'content' => 'Incredible rooftop views and even better food. The mezcal cocktails are fantastic.', 'rating' => 5, 'date' => '2024-09-05 21:30:00' ),
				array( 'author' => 'Linda Nguyen', 'email' => 'lnguyen@example.com', 'content' => 'Great ambiance and food. A bit noisy on weekends with the live music.', 'rating' => 4, 'date' => '2024-09-20 19:00:00' ),
				array( 'author' => 'Chris Taylor', 'email' => 'ctaylor@example.com', 'content' => 'Overrated and overpriced. Decent food but nothing mind-blowing.', 'rating' => 2, 'date' => '2024-10-02 20:00:00' ),
			),
		),

		// --- 14: Pet clinic, Queens ---
		array(
			'post_data' => array(
				'post_title'   => 'Paws & Claws Veterinary Hospital',
				'post_content' => '<p>Full-service veterinary hospital providing compassionate care for dogs, cats, rabbits, and exotic pets. Services include wellness exams, vaccinations, surgery, dental care, emergency services, and boarding.</p><p>24-hour emergency vet available. Online appointment scheduling.</p>',
				'post_excerpt' => 'Full-service vet hospital with 24-hour emergency care.',
				'post_status'  => 'publish',
			),
			'meta' => array(
				'_address'     => '45-10 Northern Blvd, Queens, NY 11101',
				'_phone'       => '+1 (718) 555-1401',
				'_email'       => 'care@pawsandclaws.example.com',
				'_fax'         => '+1 (718) 555-1402',
				'_website'     => 'https://pawsandclawsvet.example.com',
				'_tagline'     => 'Because They\'re Family.',
				'_zip'         => '11101',
				'_map_lat'     => '40.7527',
				'_map_lon'     => '-73.9231',
				'_price_range' => 'moderate',
				'_is_featured' => 'no',
				'_expiry_never' => 'yes',
				'_view_count'  => '1100',
				'adqs_social_media_link' => wp_json_encode( array(
					array( 'url' => 'https://instagram.com/pawsandclawsvet', 'label' => 'Instagram', 'icon' => 'fab fa-instagram' ),
					array( 'url' => 'https://facebook.com/pawsandclawsvet', 'label' => 'Facebook', 'icon' => 'fab fa-facebook' ),
				) ),
				'_checkbox_cf_amenities'         => array( 'parking', 'ac', 'wheelchair' ),
				'_select_cf_payment_methods'     => 'credit_card',
				'_number_cf_year_established'    => '2008',
				'_select_cf_number_of_employees' => '11-50',
				'_text_cf_certifications'        => 'AAHA Accredited, Fear Free Certified',
				'_date_cf_founding_date'         => '2008-04-22',
				'_color_cf_brand_color'          => '#4CAF50',
				'_radio_cf_service_area'         => 'regional',
			),
			'hours' => array(
				'monday'    => array( array( '07:00', '21:00' ) ),
				'tuesday'   => array( array( '07:00', '21:00' ) ),
				'wednesday' => array( array( '07:00', '21:00' ) ),
				'thursday'  => array( array( '07:00', '21:00' ) ),
				'friday'    => array( array( '07:00', '21:00' ) ),
				'saturday'  => array( array( '08:00', '18:00' ) ),
				'sunday'    => array( array( '09:00', '15:00' ) ),
			),
			'images' => array(
				array( 'Hospital Entrance', '4CAF50' ),
				array( 'Exam Room', '81C784' ),
				array( 'Surgery Suite', '388E3C' ),
				array( 'Boarding Area', 'A5D6A7' ),
			),
			'categories' => array( 'Health & Medical', 'Veterinary' ),
			'tags'       => array( 'Pet Friendly', 'Certified', 'Open Late', 'Family Friendly' ),
			'locations'  => array( 'Queens' ),
			'reviews'    => array(
				array( 'author' => 'Amanda Foster', 'email' => 'afoster@example.com', 'content' => 'Saved my dog\'s life in an emergency at 2am. Eternally grateful.', 'rating' => 5, 'date' => '2024-07-04 03:00:00' ),
				array( 'author' => 'George Lin', 'email' => 'glin@example.com', 'content' => 'Wonderful vets who clearly love animals. My cat is always at ease here.', 'rating' => 5, 'date' => '2024-08-15 10:00:00' ),
				array( 'author' => 'Rita Patel', 'email' => 'rpatel@example.com', 'content' => 'Good care but expensive. The emergency fees are steep.', 'rating' => 3, 'date' => '2024-09-01 16:00:00' ),
			),
		),

		// --- 15: Bakery, Brooklyn ---
		array(
			'post_data' => array(
				'post_title'   => 'Brooklyn Artisan Bakery',
				'post_content' => '<p>Small-batch artisan bakery specializing in sourdough breads, French pastries, and custom celebration cakes. Everything is baked fresh daily using organic flour and local ingredients.</p><p>Wholesale available for restaurants and cafes. Custom wedding cakes by appointment.</p>',
				'post_excerpt' => 'Artisan bakery with sourdough, pastries, and custom cakes.',
				'post_status'  => 'publish',
			),
			'meta' => array(
				'_address'     => '220 Smith Street, Brooklyn, NY 11201',
				'_phone'       => '+1 (718) 555-1501',
				'_email'       => 'hello@brooklynartisan.example.com',
				'_website'     => 'https://brooklynartisanbakery.example.com',
				'_tagline'     => 'Baked with Love, Daily.',
				'_zip'         => '11201',
				'_map_lat'     => '40.6826',
				'_map_lon'     => '-73.9894',
				'_price_range' => 'economy',
				'_is_featured' => 'no',
				'_expiry_never' => 'yes',
				'_view_count'  => '980',
				'adqs_social_media_link' => wp_json_encode( array(
					array( 'url' => 'https://instagram.com/brooklynartisan', 'label' => 'Instagram', 'icon' => 'fab fa-instagram' ),
				) ),
				'_checkbox_cf_amenities'     => array( 'wifi', 'ac' ),
				'_select_cf_payment_methods' => 'credit_card',
				'_number_cf_year_established' => '2017',
				'_select_cf_number_of_employees' => '1-10',
				'_textarea_cf_about_owner'   => 'Pastry chef Claire Dubois trained at Le Cordon Bleu in Paris before settling in Brooklyn to open her dream bakery.',
				'_text_cf_certifications'    => 'Organic Certified, Zagat Rated',
				'_date_cf_founding_date'     => '2017-03-15',
				'_color_cf_brand_color'      => '#F9A825',
				'_radio_cf_service_area'     => 'regional',
			),
			'hours' => array(
				'monday'    => array( array( '06:00', '18:00' ) ),
				'tuesday'   => array( array( '06:00', '18:00' ) ),
				'wednesday' => array( array( '06:00', '18:00' ) ),
				'thursday'  => array( array( '06:00', '18:00' ) ),
				'friday'    => array( array( '06:00', '19:00' ) ),
				'saturday'  => array( array( '07:00', '19:00' ) ),
				'sunday'    => array( array( '07:00', '15:00' ) ),
			),
			'images' => array(
				array( 'Sourdough Loaves', 'F9A825' ),
				array( 'Pastry Case', 'FFD54F' ),
				array( 'Wedding Cake', 'FFF9C4' ),
				array( 'Croissants', 'FFAB00' ),
			),
			'categories' => array( 'Restaurants & Food', 'Bakeries' ),
			'tags'       => array( 'Organic', 'Locally Owned', 'Vegan Options', 'Gluten Free', 'Delivery Available' ),
			'locations'  => array( 'Brooklyn' ),
			'reviews'    => array(
				array( 'author' => 'Nicole Adams', 'email' => 'nadams@example.com', 'content' => 'The sourdough is life-changing. Best bread in Brooklyn, no contest.', 'rating' => 5, 'date' => '2024-05-01 08:00:00' ),
				array( 'author' => 'Peter Weiss', 'email' => 'pweiss@example.com', 'content' => 'Our wedding cake was a work of art and tasted even better. Thank you Claire!', 'rating' => 5, 'date' => '2024-06-20 10:00:00' ),
				array( 'author' => 'Maya Johnson', 'email' => 'mjohnson@example.com', 'content' => 'Great bakery but they sell out of popular items early. Get there before 9am.', 'rating' => 4, 'date' => '2024-07-08 07:30:00' ),
			),
		),
	);

	// Insert all business listings.
	foreach ( $business_listings as $index => $listing ) {
		$post_id = adirectory_insert_listing(
			$listing,
			$business_type['post_type'],
			$business_type['term_id'],
			$categories,
			$tags,
			$locations,
			$author_id
		);
		if ( $post_id ) {
			echo "Created business listing #" . ( $index + 1 ) . ": '{$listing['post_data']['post_title']}' (ID: {$post_id})\n";
		}
	}

	// =========================================================================
	// EVENT LISTINGS (5)
	// =========================================================================

	$event_listings = array(

		// --- 16: Tech conference ---
		array(
			'post_data' => array(
				'post_title'   => 'TechConnect 2025 - Annual Developer Conference',
				'post_content' => '<p>Join over 5,000 developers, engineers, and tech leaders for the premier technology conference. Three days of keynotes, workshops, hackathons, and networking.</p><p>Featured speakers from Google, Microsoft, Meta, and top startups. Topics cover AI/ML, cloud computing, cybersecurity, and more.</p>',
				'post_excerpt' => 'Three-day developer conference with keynotes and workshops.',
				'post_status'  => 'publish',
			),
			'meta' => array(
				'_address'     => '747 Howard St, San Francisco, CA 94103',
				'_phone'       => '+1 (415) 555-1601',
				'_email'       => 'info@techconnect2025.example.com',
				'_website'     => 'https://techconnect2025.example.com',
				'_video'       => 'https://youtube.com/watch?v=techconnect2025',
				'_tagline'     => 'Connect. Learn. Build.',
				'_zip'         => '94103',
				'_map_lat'     => '37.7849',
				'_map_lon'     => '-122.4005',
				'_price_range' => 'moderate',
				'_is_featured' => 'yes',
				'_expiry_date' => '2025-10-15',
				'_expiry_never' => 'no',
				'_view_count'  => '5400',
				'adqs_social_media_link' => wp_json_encode( array(
					array( 'url' => 'https://twitter.com/techconnect', 'label' => 'Twitter', 'icon' => 'fab fa-twitter' ),
					array( 'url' => 'https://linkedin.com/company/techconnect', 'label' => 'LinkedIn', 'icon' => 'fab fa-linkedin' ),
				) ),
				'_date_cf_event_date'       => '2025-10-12',
				'_time_cf_event_time'       => '09:00',
				'_date_cf_event_end_date'   => '2025-10-14',
				'_number_cf_ticket_price'   => '599',
				'_url_cf_ticket_url'        => 'https://tickets.techconnect2025.example.com',
				'_select_cf_event_type'     => 'conference',
				'_radio_cf_age_restriction' => 'all_ages',
				'_text_cf_organizer_name'   => 'TechConnect Inc.',
				'_email_cf_organizer_email' => 'organizers@techconnect.example.com',
			),
			'hours'  => null,
			'images' => array(
				array( 'Main Stage', '311B92' ),
				array( 'Workshop Hall', '7C4DFF' ),
				array( 'Networking', '536DFE' ),
			),
			'categories' => array( 'Entertainment', 'Education', 'Technology' ),
			'tags'       => array( 'Popular', 'Award Winning', 'New' ),
			'locations'  => array( 'San Francisco' ),
			'reviews'    => array(
				array( 'author' => 'Dev Patel', 'email' => 'dev.p@example.com', 'content' => 'Amazing conference! The AI workshops were particularly insightful.', 'rating' => 5, 'date' => '2024-10-20 18:00:00' ),
				array( 'author' => 'Anna Schmidt', 'email' => 'anna.s@example.com', 'content' => 'Well organized with great speakers. The venue was a bit crowded.', 'rating' => 4, 'date' => '2024-10-21 10:00:00' ),
			),
		),

		// --- 17: Jazz concert ---
		array(
			'post_data' => array(
				'post_title'   => 'Jazz Under the Stars - Summer Series',
				'post_content' => '<p>An intimate outdoor jazz concert series featuring world-class musicians performing under the stars in Central Park. Bring a blanket and enjoy an evening of smooth jazz, soul, and blues.</p>',
				'post_excerpt' => 'Outdoor jazz concert series in Central Park.',
				'post_status'  => 'publish',
			),
			'meta' => array(
				'_address'     => 'Central Park, New York, NY 10024',
				'_phone'       => '+1 (212) 555-1701',
				'_email'       => 'jazz@centralpark.example.com',
				'_website'     => 'https://jazzunderthestars.example.com',
				'_tagline'     => 'Music. Magic. Moonlight.',
				'_zip'         => '10024',
				'_map_lat'     => '40.7812',
				'_map_lon'     => '-73.9665',
				'_price_range' => 'economy',
				'_is_featured' => 'yes',
				'_expiry_never' => 'yes',
				'_view_count'  => '3200',
				'_date_cf_event_date'       => '2025-07-04',
				'_time_cf_event_time'       => '20:00',
				'_date_cf_event_end_date'   => '2025-08-29',
				'_number_cf_ticket_price'   => '45',
				'_select_cf_event_type'     => 'concert',
				'_radio_cf_age_restriction' => '18_plus',
				'_text_cf_organizer_name'   => 'NYC Parks Foundation',
				'_email_cf_organizer_email' => 'events@nycparks.example.com',
			),
			'hours'  => null,
			'images' => array(
				array( 'Stage Setup', '1A237E' ),
				array( 'Audience', '283593' ),
			),
			'categories' => array( 'Entertainment', 'Live Music' ),
			'tags'       => array( 'Outdoor Seating', 'Popular', 'Live Music' ),
			'locations'  => array( 'Manhattan' ),
			'reviews'    => array(
				array( 'author' => 'Carol White', 'email' => 'carol.w@example.com', 'content' => 'What a magical evening! The music was incredible and the setting perfect.', 'rating' => 5, 'date' => '2024-08-10 22:00:00' ),
			),
		),

		// --- 18: Pottery workshop ---
		array(
			'post_data' => array(
				'post_title'   => 'Beginner Pottery Workshop',
				'post_content' => 'Learn the basics of pottery in this hands-on 3-hour workshop. All materials included. No experience necessary. Take home your creations after kiln firing.',
				'post_excerpt' => 'Hands-on pottery workshop for beginners.',
				'post_status'  => 'publish',
			),
			'meta' => array(
				'_address'     => '1500 Main St, Houston, TX 77002',
				'_phone'       => '+1 (713) 555-1801',
				'_email'       => 'clay@potteryworkshop.example.com',
				'_zip'         => '77002',
				'_map_lat'     => '29.7604',
				'_map_lon'     => '-95.3698',
				'_is_featured' => 'no',
				'_expiry_never' => 'yes',
				'_view_count'  => '120',
				'_date_cf_event_date'       => '2025-04-15',
				'_time_cf_event_time'       => '14:00',
				'_number_cf_ticket_price'   => '65',
				'_select_cf_event_type'     => 'workshop',
				'_radio_cf_age_restriction' => 'all_ages',
				'_text_cf_organizer_name'   => 'Clay & Co Studio',
			),
			'hours'  => null,
			'images' => array(
				array( 'Pottery Wheel', '795548' ),
				array( 'Finished Pieces', 'A1887F' ),
			),
			'categories' => array( 'Education' ),
			'tags'       => array( 'Family Friendly', 'New', 'Budget Friendly' ),
			'locations'  => array( 'Houston' ),
			'reviews'    => array(),
		),

		// --- 19: Food festival ---
		array(
			'post_data' => array(
				'post_title'   => 'Chicago Street Food Festival 2025',
				'post_content' => '<p>The Midwest\'s largest street food festival returns with over 100 food vendors, live cooking demonstrations, eating competitions, and craft beer garden.</p><p>Featuring cuisines from around the world: Thai, Ethiopian, Colombian, Polish, and more. Live music on three stages all weekend.</p>',
				'post_excerpt' => 'Midwest\'s largest street food festival with 100+ vendors.',
				'post_status'  => 'publish',
			),
			'meta' => array(
				'_address'     => 'Grant Park, Chicago, IL 60604',
				'_phone'       => '+1 (312) 555-1901',
				'_email'       => 'info@chistreetfood.example.com',
				'_website'     => 'https://chicagostreetfoodfest.example.com',
				'_tagline'     => 'Eat the World Without Leaving Chicago.',
				'_zip'         => '60604',
				'_map_lat'     => '41.8758',
				'_map_lon'     => '-87.6189',
				'_price_range' => 'economy',
				'_is_featured' => 'yes',
				'_expiry_date' => '2025-08-18',
				'_expiry_never' => 'no',
				'_view_count'  => '8900',
				'adqs_social_media_link' => wp_json_encode( array(
					array( 'url' => 'https://instagram.com/chistreetfood', 'label' => 'Instagram', 'icon' => 'fab fa-instagram' ),
					array( 'url' => 'https://facebook.com/chistreetfood', 'label' => 'Facebook', 'icon' => 'fab fa-facebook' ),
					array( 'url' => 'https://twitter.com/chistreetfood', 'label' => 'Twitter', 'icon' => 'fab fa-twitter' ),
				) ),
				'_date_cf_event_date'       => '2025-08-15',
				'_time_cf_event_time'       => '11:00',
				'_date_cf_event_end_date'   => '2025-08-17',
				'_number_cf_ticket_price'   => '15',
				'_url_cf_ticket_url'        => 'https://tickets.chistreetfood.example.com',
				'_select_cf_event_type'     => 'festival',
				'_radio_cf_age_restriction' => 'all_ages',
				'_text_cf_organizer_name'   => 'Chicago Culinary Events LLC',
				'_email_cf_organizer_email' => 'events@chistreetfood.example.com',
			),
			'hours'  => null,
			'images' => array(
				array( 'Food Stalls', 'FF6F00' ),
				array( 'Beer Garden', 'F9A825' ),
				array( 'Cooking Demo', 'E65100' ),
				array( 'Crowd Scene', 'FF8F00' ),
			),
			'categories' => array( 'Entertainment', 'Restaurants & Food' ),
			'tags'       => array( 'Family Friendly', 'Popular', 'Outdoor Seating', 'Live Music', 'Budget Friendly' ),
			'locations'  => array( 'Chicago' ),
			'reviews'    => array(
				array( 'author' => 'Tony Rizzo', 'email' => 'trizzo@example.com', 'content' => 'Best food festival in the Midwest! The Ethiopian booth was incredible.', 'rating' => 5, 'date' => '2024-08-20 15:00:00' ),
				array( 'author' => 'Jen Kowalski', 'email' => 'jkowalski@example.com', 'content' => 'So much variety. Came back all three days. The craft beer garden was a nice touch.', 'rating' => 5, 'date' => '2024-08-21 18:00:00' ),
				array( 'author' => 'Dan Murphy', 'email' => 'dmurphy@example.com', 'content' => 'Fun event but very crowded. Some booths had 30+ minute waits.', 'rating' => 3, 'date' => '2024-08-22 14:00:00' ),
			),
		),

		// --- 20: Art exhibition, pending ---
		array(
			'post_data' => array(
				'post_title'   => 'Modern Visions: Contemporary Art Exhibition',
				'post_content' => '<p>A curated exhibition featuring 40 emerging contemporary artists working across painting, sculpture, video installation, and mixed media. The exhibition explores themes of identity, technology, and environmental change.</p><p>Opening night reception includes artist talks and complimentary wine. Gallery hours extend through Labor Day.</p>',
				'post_excerpt' => 'Contemporary art exhibition with 40 emerging artists.',
				'post_status'  => 'pending',
			),
			'meta' => array(
				'_address'     => '1 E Mitchell Dr, Miami, FL 33131',
				'_phone'       => '+1 (305) 555-2001',
				'_email'       => 'info@modernvisions.example.com',
				'_website'     => 'https://modernvisions.example.com',
				'_tagline'     => 'See the World Differently.',
				'_zip'         => '33131',
				'_map_lat'     => '25.7617',
				'_map_lon'     => '-80.1918',
				'_price_range' => 'economy',
				'_is_featured' => 'no',
				'_expiry_date' => '2025-09-01',
				'_expiry_never' => 'no',
				'_view_count'  => '450',
				'adqs_social_media_link' => wp_json_encode( array(
					array( 'url' => 'https://instagram.com/modernvisions', 'label' => 'Instagram', 'icon' => 'fab fa-instagram' ),
				) ),
				'_date_cf_event_date'       => '2025-06-01',
				'_time_cf_event_time'       => '10:00',
				'_date_cf_event_end_date'   => '2025-09-01',
				'_number_cf_ticket_price'   => '20',
				'_url_cf_ticket_url'        => 'https://tickets.modernvisions.example.com',
				'_select_cf_event_type'     => 'exhibition',
				'_radio_cf_age_restriction' => 'all_ages',
				'_text_cf_organizer_name'   => 'Miami Contemporary Arts Foundation',
				'_email_cf_organizer_email' => 'curator@modernvisions.example.com',
			),
			'hours'  => null,
			'images' => array(
				array( 'Gallery Space', '424242' ),
				array( 'Installation', '757575' ),
				array( 'Opening Night', '212121' ),
			),
			'categories' => array( 'Entertainment', 'Education' ),
			'tags'       => array( 'New', 'Wheelchair Accessible' ),
			'locations'  => array( 'Miami' ),
			'reviews'    => array(
				array( 'author' => 'Isabelle Moreau', 'email' => 'imoreau@example.com', 'content' => 'Thought-provoking collection. The video installations were mesmerizing.', 'rating' => 5, 'date' => '2024-07-10 15:00:00' ),
			),
		),
	);

	// Insert all event listings.
	foreach ( $event_listings as $index => $listing ) {
		$post_id = adirectory_insert_listing(
			$listing,
			$events_type['post_type'],
			$events_type['term_id'],
			$categories,
			$tags,
			$locations,
			$author_id
		);
		if ( $post_id ) {
			echo "Created event listing #" . ( $index + 16 ) . ": '{$listing['post_data']['post_title']}' (ID: {$post_id})\n";
		}
	}
}

/**
 * Insert a single aDirectory listing with all data.
 *
 * @param array  $listing        Listing data.
 * @param string $post_type      Post type slug.
 * @param int    $dir_type_id    Directory type term ID.
 * @param array  $categories     Category term IDs map.
 * @param array  $tags           Tag term IDs map.
 * @param array  $locations      Location term IDs map.
 * @param int    $author_id      Post author ID.
 * @return int|false Post ID or false on failure.
 */
function adirectory_insert_listing( $listing, $post_type, $dir_type_id, $categories, $tags, $locations, $author_id ) {
	$post_data = wp_parse_args( $listing['post_data'], array(
		'post_type'      => $post_type,
		'post_author'    => $author_id,
		'post_status'    => 'publish',
		'comment_status' => 'open',
		'ping_status'    => 'closed',
	) );

	$post_id = wp_insert_post( $post_data, true );
	if ( is_wp_error( $post_id ) ) {
		echo "  ERROR: " . $post_id->get_error_message() . "\n";
		return false;
	}

	// Set meta values.
	if ( ! empty( $listing['meta'] ) ) {
		foreach ( $listing['meta'] as $key => $value ) {
			update_post_meta( $post_id, $key, $value );
		}
	}

	// Set business hours.
	if ( ! empty( $listing['hours'] ) ) {
		if ( $listing['hours'] === 'always_open' ) {
			update_post_meta( $post_id, 'adqs_business_data', array( 'status' => 'open_twenty_four' ) );
		} elseif ( $listing['hours'] === 'closed' ) {
			update_post_meta( $post_id, 'adqs_business_data', array( 'status' => 'hide_b_h' ) );
		} elseif ( is_array( $listing['hours'] ) ) {
			update_post_meta( $post_id, 'adqs_business_data', adirectory_build_hours( $listing['hours'] ) );
		}
	}

	// Create and attach images.
	$attachment_ids = array();
	if ( ! empty( $listing['images'] ) ) {
		$title_prefix = $listing['post_data']['post_title'];
		$attachment_ids = adirectory_create_gallery_images( $title_prefix, $listing['images'] );

		// Set first image as featured image.
		if ( ! empty( $attachment_ids ) ) {
			set_post_thumbnail( $post_id, $attachment_ids[0] );
		}

		// Store gallery in _images meta (array of attachment IDs).
		if ( count( $attachment_ids ) > 1 ) {
			update_post_meta( $post_id, '_images', array_slice( $attachment_ids, 1 ) );
		}
	}

	// Assign categories.
	if ( ! empty( $listing['categories'] ) ) {
		$cat_ids = array();
		foreach ( $listing['categories'] as $cat_name ) {
			if ( isset( $categories[ $cat_name ] ) ) {
				$cat_ids[] = (int) $categories[ $cat_name ];
			}
		}
		if ( ! empty( $cat_ids ) ) {
			wp_set_object_terms( $post_id, $cat_ids, 'adqs_category' );
		}
	}

	// Assign tags.
	if ( ! empty( $listing['tags'] ) ) {
		$tag_ids = array();
		foreach ( $listing['tags'] as $tag_name ) {
			if ( isset( $tags[ $tag_name ] ) ) {
				$tag_ids[] = (int) $tags[ $tag_name ];
			}
		}
		if ( ! empty( $tag_ids ) ) {
			wp_set_object_terms( $post_id, $tag_ids, 'adqs_tags' );
		}
	}

	// Assign locations.
	if ( ! empty( $listing['locations'] ) ) {
		$loc_ids = array();
		foreach ( $listing['locations'] as $loc_name ) {
			if ( isset( $locations[ $loc_name ] ) ) {
				$loc_ids[] = (int) $locations[ $loc_name ];
			}
		}
		if ( ! empty( $loc_ids ) ) {
			wp_set_object_terms( $post_id, $loc_ids, 'adqs_location' );
		}
	}

	// Assign directory type.
	wp_set_object_terms( $post_id, array( (int) $dir_type_id ), 'adqs_listing_types' );

	// Create reviews.
	if ( ! empty( $listing['reviews'] ) ) {
		$total_rating = 0;
		$review_count = 0;

		foreach ( $listing['reviews'] as $review ) {
			$comment_id = wp_insert_comment( array(
				'comment_post_ID'      => $post_id,
				'comment_author'       => $review['author'],
				'comment_author_email' => $review['email'],
				'comment_content'      => $review['content'],
				'comment_date'         => $review['date'],
				'comment_date_gmt'     => get_gmt_from_date( $review['date'] ),
				'comment_approved'     => '1',
				'comment_type'         => 'comment',
				'comment_parent'       => 0,
			) );

			if ( $comment_id && ! is_wp_error( $comment_id ) ) {
				update_comment_meta( $comment_id, 'adqs_review_rating', $review['rating'] );
				$total_rating += $review['rating'];
				$review_count++;
			}
		}

		// Compute and store average rating (matches aDirectory's update_avg_ratings logic).
		if ( $review_count > 0 ) {
			$avg = round( $total_rating / $review_count, 1 );
			update_post_meta( $post_id, 'adqs_avg_ratings', $avg );
		}
	}

	return $post_id;
}

// Auto-run.
if ( defined( 'ABSPATH' ) ) {
	adirectory_generate_sample_data();
}
