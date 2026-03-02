<?php
/**
 * MyListing Sample Data Generator.
 *
 * Creates full sample listings with all possible field types and combinations
 * to test the GeoDir Converter MyListing importer.
 *
 * Usage: Run via WP-CLI: wp eval-file mylisting-sample-data.php
 * Or include in a WordPress context and call mylisting_generate_sample_data().
 *
 * @package GeoDir_Converter
 */

defined( 'ABSPATH' ) || exit;

/**
 * Generate all MyListing sample data.
 */
function mylisting_generate_sample_data() {
	global $wpdb;

	echo "=== MyListing Sample Data Generator ===\n\n";

	// Step 1: Register post types and taxonomies.
	mylisting_register_types();

	// Step 2: Create listing types with field configurations.
	$listing_types = mylisting_create_listing_types();

	// Step 3: Create categories.
	$categories = mylisting_create_categories();

	// Step 4: Create tags.
	$tags = mylisting_create_tags();

	// Step 5: Create regions.
	$regions = mylisting_create_regions();

	// Step 6: Create WooCommerce packages.
	$packages = mylisting_create_packages();

	// Step 7: Create work hours table if it doesn't exist.
	mylisting_create_workhours_table();

	// Step 8: Create sample listings with all field combinations.
	mylisting_create_listings( $listing_types, $categories, $tags, $regions, $packages );

	echo "\n=== MyListing Sample Data Generation Complete ===\n";
}

/**
 * Register MyListing custom post types and taxonomies.
 */
function mylisting_register_types() {
	// Register listing post type.
	if ( ! post_type_exists( 'job_listing' ) ) {
		register_post_type( 'job_listing', array(
			'public'   => true,
			'label'    => 'Listings',
			'supports' => array( 'title', 'editor', 'excerpt', 'thumbnail', 'comments', 'custom-fields' ),
		) );
	}

	// Register listing type post type.
	if ( ! post_type_exists( 'case27_listing_type' ) ) {
		register_post_type( 'case27_listing_type', array(
			'public'   => true,
			'label'    => 'Listing Types',
			'supports' => array( 'title', 'editor', 'custom-fields' ),
		) );
	}

	// Register listing categories.
	if ( ! taxonomy_exists( 'job_listing_category' ) ) {
		register_taxonomy( 'job_listing_category', 'job_listing', array(
			'hierarchical' => true,
			'public'       => true,
			'label'        => 'Listing Categories',
		) );
	}

	// Register listing tags.
	if ( ! taxonomy_exists( 'case27_job_listing_tags' ) ) {
		register_taxonomy( 'case27_job_listing_tags', 'job_listing', array(
			'hierarchical' => false,
			'public'       => true,
			'label'        => 'Listing Tags',
		) );
	}

	// Register regions.
	if ( ! taxonomy_exists( 'region' ) ) {
		register_taxonomy( 'region', 'job_listing', array(
			'hierarchical' => true,
			'public'       => true,
			'label'        => 'Regions',
		) );
	}

	echo "Registered MyListing post types and taxonomies.\n";
}

/**
 * Create listing types with full field configurations.
 *
 * @return array Map of listing type slug => post ID.
 */
function mylisting_create_listing_types() {
	$types = array();

	// =========================================================================
	// Listing Type 1: Place (restaurants, shops, general businesses)
	// =========================================================================
	$place_fields = array(
		// Built-in fields (will be skipped by the importer's discover method).
		array( 'slug' => 'job_title', 'type' => 'text', 'label' => 'Title', 'required' => true ),
		array( 'slug' => 'job_description', 'type' => 'wp-editor', 'label' => 'Description', 'required' => false ),
		array( 'slug' => 'job_category', 'type' => 'term-select', 'label' => 'Category', 'required' => true ),
		array( 'slug' => 'job_tags', 'type' => 'term-select', 'label' => 'Tags', 'required' => false ),
		array( 'slug' => 'job_region', 'type' => 'term-select', 'label' => 'Region', 'required' => false ),
		array( 'slug' => 'job_location', 'type' => 'location', 'label' => 'Location', 'required' => false ),
		array( 'slug' => 'job_email', 'type' => 'email', 'label' => 'Email', 'required' => false ),
		array( 'slug' => 'job_phone', 'type' => 'text', 'label' => 'Phone', 'required' => false ),
		array( 'slug' => 'job_website', 'type' => 'url', 'label' => 'Website', 'required' => false ),
		array( 'slug' => 'job_video_url', 'type' => 'url', 'label' => 'Video URL', 'required' => false ),
		array( 'slug' => 'job_gallery', 'type' => 'file', 'label' => 'Gallery', 'required' => false ),
		array( 'slug' => 'job_logo', 'type' => 'file', 'label' => 'Logo', 'required' => false ),
		array( 'slug' => 'job_cover_image', 'type' => 'file', 'label' => 'Cover Image', 'required' => false ),
		array( 'slug' => 'job_links', 'type' => 'links', 'label' => 'Social Links', 'required' => false ),

		// Custom fields that WILL be discovered by the importer.
		array(
			'slug'     => 'price_range',
			'type'     => 'select',
			'label'    => 'Price Range',
			'required' => false,
			'options'  => array(
				array( 'label' => '$' ),
				array( 'label' => '$$' ),
				array( 'label' => '$$$' ),
				array( 'label' => '$$$$' ),
			),
		),
		array(
			'slug'     => 'cuisine_type',
			'type'     => 'multiselect',
			'label'    => 'Cuisine Type',
			'required' => false,
			'options'  => array(
				array( 'label' => 'Italian' ),
				array( 'label' => 'Chinese' ),
				array( 'label' => 'Mexican' ),
				array( 'label' => 'Japanese' ),
				array( 'label' => 'Indian' ),
				array( 'label' => 'Thai' ),
				array( 'label' => 'French' ),
				array( 'label' => 'American' ),
				array( 'label' => 'Mediterranean' ),
			),
		),
		array(
			'slug'     => 'amenities',
			'type'     => 'checkboxes',
			'label'    => 'Amenities',
			'required' => false,
			'options'  => array(
				array( 'label' => 'WiFi' ),
				array( 'label' => 'Parking' ),
				array( 'label' => 'Outdoor Seating' ),
				array( 'label' => 'Live Music' ),
				array( 'label' => 'Delivery' ),
				array( 'label' => 'Takeout' ),
				array( 'label' => 'Reservations' ),
				array( 'label' => 'Wheelchair Accessible' ),
				array( 'label' => 'Pet Friendly' ),
				array( 'label' => 'Valet Parking' ),
			),
		),
		array( 'slug' => 'work_hours', 'type' => 'work-hours', 'label' => 'Working Hours', 'required' => false ),
		array( 'slug' => 'tagline', 'type' => 'text', 'label' => 'Tagline', 'required' => false ),
		array( 'slug' => 'founded_year', 'type' => 'number', 'label' => 'Founded Year', 'required' => false ),
		array( 'slug' => 'about_owner', 'type' => 'textarea', 'label' => 'About the Owner', 'required' => false ),
		array( 'slug' => 'owner_bio', 'type' => 'wp-editor', 'label' => 'Owner Biography', 'required' => false ),
		array(
			'slug'     => 'atmosphere',
			'type'     => 'radio',
			'label'    => 'Atmosphere',
			'required' => false,
			'options'  => array(
				array( 'label' => 'Casual' ),
				array( 'label' => 'Fine Dining' ),
				array( 'label' => 'Family Friendly' ),
				array( 'label' => 'Romantic' ),
				array( 'label' => 'Trendy' ),
			),
		),
		array( 'slug' => 'accepts_reservations', 'type' => 'switcher', 'label' => 'Accepts Reservations', 'required' => false ),
		array( 'slug' => 'reservation_url', 'type' => 'url', 'label' => 'Reservation URL', 'required' => false ),
		array( 'slug' => 'menu_url', 'type' => 'url', 'label' => 'Menu URL', 'required' => false ),
		array( 'slug' => 'special_notes', 'type' => 'texteditor', 'label' => 'Special Notes', 'required' => false ),
		array( 'slug' => 'opening_date', 'type' => 'date', 'label' => 'Opening Date', 'required' => false ),
		array( 'slug' => 'menu_file', 'type' => 'file', 'label' => 'Menu PDF', 'required' => false ),
		array( 'slug' => 'password_wifi', 'type' => 'password', 'label' => 'WiFi Password', 'required' => false ),
	);

	$place_id = wp_insert_post( array(
		'post_title'   => 'Place',
		'post_name'    => 'place',
		'post_type'    => 'case27_listing_type',
		'post_status'  => 'publish',
		'post_content' => 'General business and place listing type.',
	), true );

	if ( is_wp_error( $place_id ) ) {
		$existing = get_page_by_path( 'place', OBJECT, 'case27_listing_type' );
		$place_id = $existing ? $existing->ID : 0;
	}

	if ( $place_id ) {
		update_post_meta( $place_id, 'case27_listing_type_fields', $place_fields );
		$types['place'] = $place_id;
		echo "Created listing type: Place (ID: {$place_id}) with " . count( $place_fields ) . " fields.\n";
	}

	// =========================================================================
	// Listing Type 2: Event
	// =========================================================================
	$event_fields = array(
		// Built-in fields.
		array( 'slug' => 'job_title', 'type' => 'text', 'label' => 'Event Name', 'required' => true ),
		array( 'slug' => 'job_description', 'type' => 'wp-editor', 'label' => 'Event Description', 'required' => false ),
		array( 'slug' => 'job_category', 'type' => 'term-select', 'label' => 'Category', 'required' => true ),
		array( 'slug' => 'job_tags', 'type' => 'term-select', 'label' => 'Tags', 'required' => false ),
		array( 'slug' => 'job_region', 'type' => 'term-select', 'label' => 'Region', 'required' => false ),
		array( 'slug' => 'job_location', 'type' => 'location', 'label' => 'Venue Location', 'required' => false ),
		array( 'slug' => 'job_email', 'type' => 'email', 'label' => 'Contact Email', 'required' => false ),
		array( 'slug' => 'job_phone', 'type' => 'text', 'label' => 'Contact Phone', 'required' => false ),
		array( 'slug' => 'job_website', 'type' => 'url', 'label' => 'Event Website', 'required' => false ),
		array( 'slug' => 'job_video_url', 'type' => 'url', 'label' => 'Promo Video', 'required' => false ),
		array( 'slug' => 'job_gallery', 'type' => 'file', 'label' => 'Event Photos', 'required' => false ),
		array( 'slug' => 'job_cover_image', 'type' => 'file', 'label' => 'Event Banner', 'required' => false ),
		array( 'slug' => 'job_links', 'type' => 'links', 'label' => 'Social Media', 'required' => false ),

		// Custom event fields.
		array( 'slug' => 'event_date', 'type' => 'date', 'label' => 'Event Date', 'required' => true ),
		array( 'slug' => 'event_end_date', 'type' => 'date', 'label' => 'Event End Date', 'required' => false ),
		array( 'slug' => 'event_time', 'type' => 'text', 'label' => 'Event Time', 'required' => false ),
		array( 'slug' => 'ticket_price', 'type' => 'number', 'label' => 'Ticket Price', 'required' => false ),
		array( 'slug' => 'ticket_url', 'type' => 'url', 'label' => 'Buy Tickets URL', 'required' => false ),
		array(
			'slug'     => 'event_type',
			'type'     => 'select',
			'label'    => 'Event Type',
			'required' => false,
			'options'  => array(
				array( 'label' => 'Conference' ),
				array( 'label' => 'Concert' ),
				array( 'label' => 'Workshop' ),
				array( 'label' => 'Exhibition' ),
				array( 'label' => 'Festival' ),
				array( 'label' => 'Meetup' ),
				array( 'label' => 'Sports' ),
				array( 'label' => 'Theater' ),
			),
		),
		array(
			'slug'     => 'age_restriction',
			'type'     => 'radio',
			'label'    => 'Age Restriction',
			'required' => false,
			'options'  => array(
				array( 'label' => 'All Ages' ),
				array( 'label' => '18+' ),
				array( 'label' => '21+' ),
			),
		),
		array( 'slug' => 'organizer_name', 'type' => 'text', 'label' => 'Organizer', 'required' => false ),
		array( 'slug' => 'organizer_email', 'type' => 'email', 'label' => 'Organizer Email', 'required' => false ),
		array( 'slug' => 'venue_name', 'type' => 'text', 'label' => 'Venue Name', 'required' => false ),
		array( 'slug' => 'max_attendees', 'type' => 'number', 'label' => 'Max Attendees', 'required' => false ),
		array( 'slug' => 'is_free', 'type' => 'switcher', 'label' => 'Free Event', 'required' => false ),
		array( 'slug' => 'dress_code', 'type' => 'text', 'label' => 'Dress Code', 'required' => false ),
		array(
			'slug'     => 'event_features',
			'type'     => 'checkboxes',
			'label'    => 'Event Features',
			'required' => false,
			'options'  => array(
				array( 'label' => 'Food & Drinks' ),
				array( 'label' => 'Live Streaming' ),
				array( 'label' => 'Recording Available' ),
				array( 'label' => 'Q&A Session' ),
				array( 'label' => 'Networking' ),
				array( 'label' => 'Certificate' ),
			),
		),
		array( 'slug' => 'event_schedule', 'type' => 'textarea', 'label' => 'Event Schedule', 'required' => false ),
	);

	$event_id = wp_insert_post( array(
		'post_title'   => 'Event',
		'post_name'    => 'event',
		'post_type'    => 'case27_listing_type',
		'post_status'  => 'publish',
		'post_content' => 'Events and activities listing type.',
	), true );

	if ( is_wp_error( $event_id ) ) {
		$existing = get_page_by_path( 'event', OBJECT, 'case27_listing_type' );
		$event_id = $existing ? $existing->ID : 0;
	}

	if ( $event_id ) {
		update_post_meta( $event_id, 'case27_listing_type_fields', $event_fields );
		$types['event'] = $event_id;
		echo "Created listing type: Event (ID: {$event_id}) with " . count( $event_fields ) . " fields.\n";
	}

	// =========================================================================
	// Listing Type 3: Hotel
	// =========================================================================
	$hotel_fields = array(
		// Built-in fields.
		array( 'slug' => 'job_title', 'type' => 'text', 'label' => 'Hotel Name', 'required' => true ),
		array( 'slug' => 'job_description', 'type' => 'wp-editor', 'label' => 'Description', 'required' => false ),
		array( 'slug' => 'job_category', 'type' => 'term-select', 'label' => 'Category', 'required' => true ),
		array( 'slug' => 'job_tags', 'type' => 'term-select', 'label' => 'Tags', 'required' => false ),
		array( 'slug' => 'job_region', 'type' => 'term-select', 'label' => 'Region', 'required' => false ),
		array( 'slug' => 'job_location', 'type' => 'location', 'label' => 'Location', 'required' => false ),
		array( 'slug' => 'job_email', 'type' => 'email', 'label' => 'Email', 'required' => false ),
		array( 'slug' => 'job_phone', 'type' => 'text', 'label' => 'Phone', 'required' => false ),
		array( 'slug' => 'job_website', 'type' => 'url', 'label' => 'Website', 'required' => false ),
		array( 'slug' => 'job_gallery', 'type' => 'file', 'label' => 'Photos', 'required' => false ),
		array( 'slug' => 'job_logo', 'type' => 'file', 'label' => 'Logo', 'required' => false ),
		array( 'slug' => 'job_cover_image', 'type' => 'file', 'label' => 'Cover', 'required' => false ),
		array( 'slug' => 'job_links', 'type' => 'links', 'label' => 'Links', 'required' => false ),

		// Custom hotel fields.
		array(
			'slug'     => 'star_rating',
			'type'     => 'select',
			'label'    => 'Star Rating',
			'required' => false,
			'options'  => array(
				array( 'label' => '1 Star' ),
				array( 'label' => '2 Stars' ),
				array( 'label' => '3 Stars' ),
				array( 'label' => '4 Stars' ),
				array( 'label' => '5 Stars' ),
			),
		),
		array( 'slug' => 'price_per_night', 'type' => 'number', 'label' => 'Price Per Night ($)', 'required' => false ),
		array( 'slug' => 'check_in_time', 'type' => 'text', 'label' => 'Check-in Time', 'required' => false ),
		array( 'slug' => 'check_out_time', 'type' => 'text', 'label' => 'Check-out Time', 'required' => false ),
		array( 'slug' => 'total_rooms', 'type' => 'number', 'label' => 'Total Rooms', 'required' => false ),
		array( 'slug' => 'booking_url', 'type' => 'url', 'label' => 'Booking URL', 'required' => false ),
		array(
			'slug'     => 'hotel_amenities',
			'type'     => 'checkboxes',
			'label'    => 'Hotel Amenities',
			'required' => false,
			'options'  => array(
				array( 'label' => 'Pool' ),
				array( 'label' => 'Gym' ),
				array( 'label' => 'Spa' ),
				array( 'label' => 'Restaurant' ),
				array( 'label' => 'Bar' ),
				array( 'label' => 'Room Service' ),
				array( 'label' => 'Free WiFi' ),
				array( 'label' => 'Parking' ),
				array( 'label' => 'Airport Shuttle' ),
				array( 'label' => 'Pet Friendly' ),
				array( 'label' => 'Business Center' ),
				array( 'label' => 'Concierge' ),
			),
		),
		array(
			'slug'     => 'property_type',
			'type'     => 'radio',
			'label'    => 'Property Type',
			'required' => false,
			'options'  => array(
				array( 'label' => 'Hotel' ),
				array( 'label' => 'Resort' ),
				array( 'label' => 'Boutique Hotel' ),
				array( 'label' => 'Hostel' ),
				array( 'label' => 'Bed & Breakfast' ),
			),
		),
		array( 'slug' => 'allows_pets', 'type' => 'switcher', 'label' => 'Pet Friendly', 'required' => false ),
		array( 'slug' => 'cancellation_policy', 'type' => 'textarea', 'label' => 'Cancellation Policy', 'required' => false ),
		array( 'slug' => 'work_hours', 'type' => 'work-hours', 'label' => 'Front Desk Hours', 'required' => false ),
	);

	$hotel_id = wp_insert_post( array(
		'post_title'   => 'Hotel',
		'post_name'    => 'hotel',
		'post_type'    => 'case27_listing_type',
		'post_status'  => 'publish',
		'post_content' => 'Hotels and accommodation listing type.',
	), true );

	if ( is_wp_error( $hotel_id ) ) {
		$existing = get_page_by_path( 'hotel', OBJECT, 'case27_listing_type' );
		$hotel_id = $existing ? $existing->ID : 0;
	}

	if ( $hotel_id ) {
		update_post_meta( $hotel_id, 'case27_listing_type_fields', $hotel_fields );
		$types['hotel'] = $hotel_id;
		echo "Created listing type: Hotel (ID: {$hotel_id}) with " . count( $hotel_fields ) . " fields.\n";
	}

	return $types;
}

/**
 * Create MyListing categories (hierarchical).
 *
 * @return array Map of category name => term_id.
 */
function mylisting_create_categories() {
	$cats = array();

	// Parent categories.
	$parents = array(
		'Restaurants'     => 'restaurants',
		'Hotels'          => 'hotels',
		'Shopping'        => 'shopping',
		'Nightlife'       => 'nightlife',
		'Events'          => 'events',
		'Health & Beauty' => 'health-beauty',
		'Automotive'      => 'automotive',
		'Education'       => 'education',
		'Real Estate'     => 'real-estate',
	);

	foreach ( $parents as $name => $slug ) {
		$term = wp_insert_term( $name, 'job_listing_category', array( 'slug' => $slug ) );
		$cats[ $name ] = is_wp_error( $term ) ? get_term_by( 'slug', $slug, 'job_listing_category' )->term_id : $term['term_id'];
	}

	// Child categories.
	$children = array(
		'Restaurants' => array(
			'Italian Restaurant'  => 'italian-restaurant',
			'Chinese Restaurant'  => 'chinese-restaurant',
			'Mexican Restaurant'  => 'mexican-restaurant',
			'Japanese Restaurant' => 'japanese-restaurant',
			'Fast Food'           => 'fast-food',
			'Fine Dining'         => 'fine-dining',
			'Cafe'                => 'cafe',
			'Bar & Grill'         => 'bar-grill',
		),
		'Hotels' => array(
			'Luxury Hotel'    => 'luxury-hotel',
			'Budget Hotel'    => 'budget-hotel',
			'Resort'          => 'resort',
			'Bed & Breakfast' => 'bed-breakfast',
			'Hostel'          => 'hostel',
		),
		'Events' => array(
			'Concerts'    => 'concerts',
			'Conferences' => 'conferences',
			'Workshops'   => 'workshops',
			'Festivals'   => 'festivals',
			'Sports'      => 'sports-events',
		),
	);

	foreach ( $children as $parent_name => $child_cats ) {
		foreach ( $child_cats as $name => $slug ) {
			$term = wp_insert_term( $name, 'job_listing_category', array(
				'slug'   => $slug,
				'parent' => $cats[ $parent_name ],
			) );
			$cats[ $name ] = is_wp_error( $term ) ? get_term_by( 'slug', $slug, 'job_listing_category' )->term_id : $term['term_id'];
		}
	}

	echo "Created " . count( $cats ) . " categories.\n";
	return $cats;
}

/**
 * Create MyListing tags.
 *
 * @return array Map of tag name => term_id.
 */
function mylisting_create_tags() {
	$tags_data = array(
		'WiFi', 'Parking', 'Pet Friendly', 'Wheelchair Accessible',
		'Family Friendly', 'Open Late', 'Delivery', 'Takeout',
		'Outdoor Seating', 'Live Music', 'Happy Hour', 'Vegan',
		'Organic', 'Locally Owned', 'Award Winning', 'Top Rated',
		'New', 'Popular', 'Budget Friendly', 'Luxury',
		'Open 24 Hours', 'Reservations', 'Walk-ins Welcome', 'Drive Through',
		'Romantic', 'Kid Friendly', 'Group Friendly', 'Solo Traveler',
	);

	$tags = array();
	foreach ( $tags_data as $name ) {
		$slug = sanitize_title( $name );
		$term = wp_insert_term( $name, 'case27_job_listing_tags', array( 'slug' => $slug ) );
		$tags[ $name ] = is_wp_error( $term ) ? get_term_by( 'slug', $slug, 'case27_job_listing_tags' )->term_id : $term['term_id'];
	}

	echo "Created " . count( $tags ) . " tags.\n";
	return $tags;
}

/**
 * Create MyListing regions (hierarchical).
 *
 * @return array Map of region name => term_id.
 */
function mylisting_create_regions() {
	$regions = array();

	$data = array(
		'North America' => array(
			'slug'     => 'north-america',
			'children' => array(
				'New York'      => 'new-york',
				'Los Angeles'   => 'los-angeles',
				'San Francisco' => 'san-francisco',
				'Chicago'       => 'chicago',
				'Miami'         => 'miami',
				'Austin'        => 'austin',
				'Seattle'       => 'seattle',
				'Denver'        => 'denver',
			),
		),
		'Europe' => array(
			'slug'     => 'europe',
			'children' => array(
				'London'    => 'london',
				'Paris'     => 'paris',
				'Barcelona' => 'barcelona',
				'Berlin'    => 'berlin',
				'Amsterdam' => 'amsterdam',
			),
		),
	);

	foreach ( $data as $continent => $info ) {
		$term = wp_insert_term( $continent, 'region', array( 'slug' => $info['slug'] ) );
		$parent_id = is_wp_error( $term ) ? get_term_by( 'slug', $info['slug'], 'region' )->term_id : $term['term_id'];
		$regions[ $continent ] = $parent_id;

		foreach ( $info['children'] as $name => $slug ) {
			$term = wp_insert_term( $name, 'region', array( 'slug' => $slug, 'parent' => $parent_id ) );
			$regions[ $name ] = is_wp_error( $term ) ? get_term_by( 'slug', $slug, 'region' )->term_id : $term['term_id'];
		}
	}

	echo "Created " . count( $regions ) . " regions.\n";
	return $regions;
}

/**
 * Create WooCommerce job_package products.
 *
 * @return array Map of package name => product ID.
 */
function mylisting_create_packages() {
	$packages = array();

	// Only create if WooCommerce exists or simulate the data.
	$package_data = array(
		array(
			'title'       => 'Free Listing',
			'content'     => 'Basic free listing with limited features. Perfect for getting started.',
			'price'       => '0',
			'duration'    => '30',
			'limit'       => '1',
			'featured'    => 'no',
		),
		array(
			'title'       => 'Basic Listing',
			'content'     => 'Standard listing package with moderate features and visibility.',
			'price'       => '9.99',
			'duration'    => '60',
			'limit'       => '5',
			'featured'    => 'no',
		),
		array(
			'title'       => 'Premium Listing',
			'content'     => 'Premium listing with featured placement, extended duration, and all features.',
			'price'       => '29.99',
			'duration'    => '180',
			'limit'       => '20',
			'featured'    => 'yes',
		),
		array(
			'title'       => 'Enterprise Listing',
			'content'     => 'Unlimited listings with permanent duration and priority support.',
			'price'       => '99.99',
			'duration'    => '365',
			'limit'       => '100',
			'featured'    => 'yes',
		),
	);

	foreach ( $package_data as $pkg ) {
		$post_id = wp_insert_post( array(
			'post_title'   => $pkg['title'],
			'post_content' => $pkg['content'],
			'post_type'    => 'product',
			'post_status'  => 'publish',
		), true );

		if ( is_wp_error( $post_id ) ) {
			echo "  Warning: Could not create package '{$pkg['title']}': " . $post_id->get_error_message() . "\n";
			continue;
		}

		// Set product meta.
		update_post_meta( $post_id, '_price', $pkg['price'] );
		update_post_meta( $post_id, '_regular_price', $pkg['price'] );
		update_post_meta( $post_id, '_job_listing_duration', $pkg['duration'] );
		update_post_meta( $post_id, '_job_listing_limit', $pkg['limit'] );
		update_post_meta( $post_id, '_job_listing_featured', $pkg['featured'] );
		update_post_meta( $post_id, '_virtual', 'yes' );

		// Assign product_type taxonomy (job_package).
		if ( taxonomy_exists( 'product_type' ) ) {
			wp_set_object_terms( $post_id, 'job_package', 'product_type' );
		} else {
			// Register it temporarily and set term.
			register_taxonomy( 'product_type', 'product', array( 'public' => false ) );
			wp_set_object_terms( $post_id, 'job_package', 'product_type' );
		}

		$packages[ $pkg['title'] ] = $post_id;
		echo "Created package: '{$pkg['title']}' (ID: {$post_id}, \${$pkg['price']}, {$pkg['duration']} days)\n";
	}

	return $packages;
}

/**
 * Create the MyListing work hours table if not present.
 */
function mylisting_create_workhours_table() {
	global $wpdb;

	$table = $wpdb->prefix . 'mylisting_workhours';
	$charset_collate = $wpdb->get_charset_collate();

	if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) !== $table ) {
		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			listing_id bigint(20) unsigned NOT NULL,
			start int(11) NOT NULL,
			end int(11) NOT NULL,
			timezone varchar(50) DEFAULT 'America/New_York',
			PRIMARY KEY (id),
			KEY listing_id (listing_id)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
		echo "Created wp_mylisting_workhours table.\n";
	} else {
		echo "Work hours table already exists.\n";
	}
}

/**
 * Insert work hours for a listing.
 *
 * MyListing stores hours as minute offsets from Monday midnight.
 * Monday 00:00 = 0, Tuesday 00:00 = 1440, etc.
 *
 * @param int    $listing_id The listing post ID.
 * @param array  $hours      Array of day => array of time slot arrays.
 * @param string $timezone   Timezone string.
 */
function mylisting_insert_workhours( $listing_id, $hours, $timezone = 'America/New_York' ) {
	global $wpdb;

	$table    = $wpdb->prefix . 'mylisting_workhours';
	$day_map  = array(
		'monday'    => 0,
		'tuesday'   => 1440,
		'wednesday' => 2880,
		'thursday'  => 4320,
		'friday'    => 5760,
		'saturday'  => 7200,
		'sunday'    => 8640,
	);

	foreach ( $hours as $day => $slots ) {
		if ( ! isset( $day_map[ $day ] ) ) {
			continue;
		}
		$day_offset = $day_map[ $day ];

		foreach ( $slots as $slot ) {
			$start_parts = explode( ':', $slot['open'] );
			$end_parts   = explode( ':', $slot['close'] );

			$start_min = $day_offset + ( (int) $start_parts[0] * 60 ) + (int) $start_parts[1];
			$end_min   = $day_offset + ( (int) $end_parts[0] * 60 ) + (int) $end_parts[1];

			$wpdb->insert( $table, array(
				'listing_id' => $listing_id,
				'start'      => $start_min,
				'end'        => $end_min,
				'timezone'   => $timezone,
			), array( '%d', '%d', '%d', '%s' ) );
		}
	}
}

/**
 * Create all sample listings.
 *
 * @param array $listing_types Listing type IDs.
 * @param array $categories    Category term IDs.
 * @param array $tags          Tag term IDs.
 * @param array $regions       Region term IDs.
 * @param array $packages      Package product IDs.
 */
function mylisting_create_listings( $listing_types, $categories, $tags, $regions, $packages ) {
	$author_id = get_current_user_id() ?: 1;

	// =========================================================================
	// PLACE LISTINGS
	// =========================================================================

	$place_listings = array(
		// --- Listing 1: Fully populated restaurant (ALL fields) ---
		array(
			'post_data' => array(
				'post_title'     => 'La Bella Vita Ristorante',
				'post_content'   => '<p>An authentic Italian dining experience in the heart of New York. Our chef, trained in Rome, brings centuries-old recipes to life with locally sourced ingredients and imported Italian specialties.</p><p>Features include a stunning wine cellar with over 300 selections, a private dining room, and a beautiful courtyard for al fresco dining in the warmer months.</p>',
				'post_excerpt'   => 'Authentic Italian restaurant with handmade pasta and an extensive wine list.',
				'post_status'    => 'publish',
				'comment_status' => 'open',
			),
			'meta' => array(
				'_case27_listing_type'  => 'place',
				'_featured'             => '1',
				'_claimed'              => '1',
				'_job_email'            => 'reservations@labellavita.example.com',
				'_job_phone'            => '+1 (212) 555-0101',
				'_job_website'          => 'https://www.labellavita.example.com',
				'_job_video_url'        => 'https://youtube.com/watch?v=labellavita2025',
				'_job_expires'          => '2027-12-31',
				'_links'                => serialize( array(
					array( 'network' => 'Facebook', 'url' => 'https://facebook.com/labellavita' ),
					array( 'network' => 'Instagram', 'url' => 'https://instagram.com/labellavita' ),
					array( 'network' => 'Twitter', 'url' => 'https://twitter.com/labellavita' ),
					array( 'network' => 'Yelp', 'url' => 'https://yelp.com/biz/labellavita' ),
					array( 'network' => 'TripAdvisor', 'url' => 'https://tripadvisor.com/labellavita' ),
				) ),
				'_case27_average_rating' => '4.5',
				// Custom fields (stored as _slug).
				'_price_range'          => '$$$$',
				'_cuisine_type'         => serialize( array( 'Italian', 'Mediterranean' ) ),
				'_amenities'            => serialize( array( 'WiFi', 'Outdoor Seating', 'Reservations', 'Valet Parking' ) ),
				'_tagline'              => 'Where Every Meal is a Masterpiece.',
				'_founded_year'         => '1995',
				'_about_owner'          => 'Chef Marco Rossi trained at the prestigious Culinary Academy of Rome before bringing his passion for authentic Italian cuisine to New York.',
				'_owner_bio'            => '<p><strong>Chef Marco Rossi</strong> is a James Beard Award nominee with over 30 years of culinary experience. Born in Tuscany, Italy, he moved to New York in 1992 and has since become one of the city\'s most celebrated chefs.</p><p>His philosophy centers on simplicity, quality ingredients, and respect for traditional Italian cooking methods.</p>',
				'_atmosphere'           => 'Romantic',
				'_accepts_reservations' => '1',
				'_reservation_url'      => 'https://opentable.com/labellavita',
				'_menu_url'             => 'https://www.labellavita.example.com/menu',
				'_special_notes'        => '<p>We offer a special tasting menu on Friday and Saturday evenings. Private dining available for parties of 8-20 guests.</p><p><em>Please note: We are a nut-free kitchen.</em></p>',
				'_opening_date'         => '1995-06-15',
				'_password_wifi'        => 'BuonAppetito2025',
			),
			'geolocation' => array(
				'lat'     => '40.7580',
				'lng'     => '-73.9855',
				'city'    => 'New York',
				'state'   => 'New York',
				'country' => 'United States',
				'street'  => '245 W 52nd St',
				'zip'     => '10019',
			),
			'workhours' => array(
				'monday'    => array( array( 'open' => '11:30', 'close' => '14:30' ), array( 'open' => '17:00', 'close' => '23:00' ) ),
				'tuesday'   => array( array( 'open' => '11:30', 'close' => '14:30' ), array( 'open' => '17:00', 'close' => '23:00' ) ),
				'wednesday' => array( array( 'open' => '11:30', 'close' => '14:30' ), array( 'open' => '17:00', 'close' => '23:00' ) ),
				'thursday'  => array( array( 'open' => '11:30', 'close' => '14:30' ), array( 'open' => '17:00', 'close' => '23:00' ) ),
				'friday'    => array( array( 'open' => '11:30', 'close' => '14:30' ), array( 'open' => '17:00', 'close' => '23:59' ) ),
				'saturday'  => array( array( 'open' => '10:00', 'close' => '23:59' ) ),
				'sunday'    => array( array( 'open' => '10:00', 'close' => '22:00' ) ),
			),
			'workhours_tz' => 'America/New_York',
			'categories'   => array( 'Restaurants', 'Italian Restaurant', 'Fine Dining' ),
			'tags'         => array( 'Outdoor Seating', 'Award Winning', 'Reservations', 'Vegan', 'Romantic' ),
			'regions'      => array( 'New York' ),
			'package'      => 'Premium Listing',
			'reviews'      => array(
				array(
					'author'  => 'Michael Thompson',
					'email'   => 'mthompson@example.com',
					'content' => 'The best Italian restaurant in New York, hands down. The homemade tagliatelle with truffle cream sauce was divine. Wine pairing was excellent.',
					'rating'  => 10,
					'date'    => '2024-01-20 20:30:00',
				),
				array(
					'author'  => 'Jessica Lee',
					'email'   => 'jlee@example.com',
					'content' => 'Beautiful ambiance and great food. The tiramisu was heavenly. Service was attentive but the wait for a table was quite long even with a reservation.',
					'rating'  => 8,
					'date'    => '2024-02-14 21:15:00',
				),
				array(
					'author'  => 'Robert Chen',
					'email'   => 'rchen@example.com',
					'content' => 'Overpriced for what you get. The pasta was good but not exceptional. The wine list is impressive though.',
					'rating'  => 5,
					'date'    => '2024-03-08 19:45:00',
				),
				array(
					'author'  => 'Amanda Davis',
					'email'   => 'adavis@example.com',
					'content' => 'Perfect for a special occasion. The tasting menu was incredible and the sommelier really knows his stuff.',
					'rating'  => 9,
					'date'    => '2024-04-22 20:00:00',
				),
			),
		),

		// --- Listing 2: Cafe - Featured, claimed, all amenities ---
		array(
			'post_data' => array(
				'post_title'     => 'Brew & Bean Artisan Coffee',
				'post_content'   => '<p>Third-wave coffee shop roasting our own beans in-house. We source directly from farmers in Colombia, Ethiopia, and Guatemala to bring you the freshest, most flavorful coffee experience.</p><p>Also serving fresh pastries, breakfast sandwiches, and our famous avocado toast.</p>',
				'post_excerpt'   => 'Artisan coffee roaster and cafe with house-roasted beans.',
				'post_status'    => 'publish',
				'comment_status' => 'open',
			),
			'meta' => array(
				'_case27_listing_type'  => 'place',
				'_featured'             => '1',
				'_claimed'              => '1',
				'_job_email'            => 'hello@brewandbean.example.com',
				'_job_phone'            => '+1 (415) 555-0201',
				'_job_website'          => 'https://brewandbean.example.com',
				'_job_video_url'        => 'https://vimeo.com/brewandbean-story',
				'_job_expires'          => '2028-06-30',
				'_links'                => serialize( array(
					array( 'network' => 'Instagram', 'url' => 'https://instagram.com/brewandbean' ),
					array( 'network' => 'Facebook', 'url' => 'https://facebook.com/brewandbean' ),
					array( 'network' => 'TikTok', 'url' => 'https://tiktok.com/@brewandbean' ),
				) ),
				'_case27_average_rating' => '4.8',
				'_price_range'           => '$$',
				'_cuisine_type'          => serialize( array( 'American' ) ),
				'_amenities'             => serialize( array( 'WiFi', 'Parking', 'Outdoor Seating', 'Pet Friendly', 'Takeout', 'Delivery' ) ),
				'_tagline'               => 'Roasted with Love.',
				'_founded_year'          => '2018',
				'_about_owner'           => 'Sarah and James Miller left their corporate jobs to pursue their passion for specialty coffee.',
				'_atmosphere'            => 'Casual',
				'_accepts_reservations'  => '0',
				'_special_notes'         => 'Ask about our single-origin pour-over flights! We also host monthly cupping events.',
				'_opening_date'          => '2018-03-15',
				'_password_wifi'         => 'BrewCoffee2025',
			),
			'geolocation' => array(
				'lat'     => '37.7749',
				'lng'     => '-122.4194',
				'city'    => 'San Francisco',
				'state'   => 'California',
				'country' => 'United States',
				'street'  => '1234 Valencia St',
				'zip'     => '94110',
			),
			'workhours' => array(
				'monday'    => array( array( 'open' => '06:00', 'close' => '20:00' ) ),
				'tuesday'   => array( array( 'open' => '06:00', 'close' => '20:00' ) ),
				'wednesday' => array( array( 'open' => '06:00', 'close' => '20:00' ) ),
				'thursday'  => array( array( 'open' => '06:00', 'close' => '20:00' ) ),
				'friday'    => array( array( 'open' => '06:00', 'close' => '22:00' ) ),
				'saturday'  => array( array( 'open' => '07:00', 'close' => '22:00' ) ),
				'sunday'    => array( array( 'open' => '07:00', 'close' => '18:00' ) ),
			),
			'workhours_tz' => 'America/Los_Angeles',
			'categories'   => array( 'Restaurants', 'Cafe' ),
			'tags'         => array( 'WiFi', 'Outdoor Seating', 'Pet Friendly', 'Popular', 'Locally Owned', 'Organic' ),
			'regions'      => array( 'San Francisco' ),
			'package'      => 'Basic Listing',
			'reviews'      => array(
				array(
					'author'  => 'Emma Wilson',
					'email'   => 'emma.w@example.com',
					'content' => 'Best coffee in the Mission! The Ethiopian single-origin pour-over changed my life.',
					'rating'  => 10,
					'date'    => '2024-05-10 08:30:00',
				),
				array(
					'author'  => 'David Kim',
					'email'   => 'dkim@example.com',
					'content' => 'Great coffee, cozy atmosphere. The avocado toast is a must-try!',
					'rating'  => 9,
					'date'    => '2024-06-05 10:00:00',
				),
			),
		),

		// --- Listing 3: Pending status, not featured, not claimed, minimal ---
		array(
			'post_data' => array(
				'post_title'     => 'Quick Bites Food Truck',
				'post_content'   => 'Mobile food truck serving gourmet tacos, burritos, and quesadillas made with fresh, locally sourced ingredients.',
				'post_excerpt'   => 'Gourmet Mexican food truck.',
				'post_status'    => 'pending',
				'comment_status' => 'open',
			),
			'meta' => array(
				'_case27_listing_type'  => 'place',
				'_featured'             => '0',
				'_claimed'              => '0',
				'_job_email'            => 'quickbites@example.com',
				'_job_phone'            => '+1 (310) 555-0301',
				'_price_range'          => '$',
				'_cuisine_type'         => serialize( array( 'Mexican', 'American' ) ),
				'_amenities'            => serialize( array( 'Takeout' ) ),
				'_tagline'              => 'Street Food. Elevated.',
				'_atmosphere'           => 'Casual',
				'_accepts_reservations' => '0',
			),
			'geolocation' => array(
				'lat'     => '34.0522',
				'lng'     => '-118.2437',
				'city'    => 'Los Angeles',
				'state'   => 'California',
				'country' => 'United States',
				'street'  => 'Various Locations',
				'zip'     => '90015',
			),
			'categories' => array( 'Restaurants', 'Mexican Restaurant' ),
			'tags'       => array( 'Takeout', 'Budget Friendly', 'Walk-ins Welcome', 'New' ),
			'regions'    => array( 'Los Angeles' ),
			'package'    => 'Free Listing',
			'reviews'    => array(),
		),

		// --- Listing 4: Expired status, with geolocation fallback meta ---
		array(
			'post_data' => array(
				'post_title'     => 'Retro Games Arcade Bar',
				'post_content'   => '<p>Vintage arcade games, craft cocktails, and a selection of local beers on tap. Over 50 classic arcade machines and pinball tables from the 80s and 90s.</p>',
				'post_excerpt'   => 'Retro arcade bar with vintage games and craft cocktails.',
				'post_status'    => 'expired',
				'comment_status' => 'open',
			),
			'meta' => array(
				'_case27_listing_type'  => 'place',
				'_featured'             => '0',
				'_claimed'              => '1',
				'_job_email'            => 'play@retrogames.example.com',
				'_job_phone'            => '+1 (512) 555-0401',
				'_job_website'          => 'https://retrogamesarcade.example.com',
				'_job_expires'          => '2024-06-30',
				'_links'                => serialize( array(
					array( 'network' => 'Instagram', 'url' => 'https://instagram.com/retrogamesbar' ),
				) ),
				'_case27_average_rating' => '4.2',
				'_price_range'           => '$$',
				'_amenities'             => serialize( array( 'WiFi', 'Live Music', 'Parking' ) ),
				'_tagline'               => 'Press Start.',
				'_atmosphere'            => 'Trendy',
				// Using WP Job Manager geolocation meta (fallback).
				'geolocation_lat'          => '30.2672',
				'geolocation_long'         => '-97.7431',
				'geolocation_city'         => 'Austin',
				'geolocation_state_long'   => 'Texas',
				'geolocation_country_long' => 'United States',
				'geolocation_street'       => '401 E 6th St',
				'geolocation_postcode'     => '78701',
			),
			'categories' => array( 'Nightlife' ),
			'tags'       => array( 'Open Late', 'Popular', 'Live Music', 'Group Friendly' ),
			'regions'    => array( 'Austin' ),
			'package'    => 'Premium Listing',
			'reviews'    => array(
				array(
					'author'  => 'Chris Rodriguez',
					'email'   => 'chris.r@example.com',
					'content' => 'So much nostalgia! Pac-Man, Street Fighter, and cheap drinks. What more could you want?',
					'rating'  => 9,
					'date'    => '2024-02-28 22:00:00',
				),
				array(
					'author'  => 'Katie Brown',
					'email'   => 'kbrown@example.com',
					'content' => 'Fun concept but it gets way too crowded on weekends. Good drinks though.',
					'rating'  => 6,
					'date'    => '2024-03-15 23:30:00',
				),
			),
		),

		// --- Listing 5: Draft status, no location data ---
		array(
			'post_data' => array(
				'post_title'     => 'Zen Garden Day Spa',
				'post_content'   => '<p>Full-service day spa offering massage therapy, facials, body treatments, and nail services in a tranquil, zen-inspired environment.</p>',
				'post_excerpt'   => 'Luxury day spa with massage, facials, and body treatments.',
				'post_status'    => 'draft',
				'comment_status' => 'open',
			),
			'meta' => array(
				'_case27_listing_type'  => 'place',
				'_featured'             => '1',
				'_claimed'              => '1',
				'_job_email'            => 'relax@zengarden.example.com',
				'_job_phone'            => '+1 (305) 555-0501',
				'_job_website'          => 'https://zengardenspa.example.com',
				'_job_expires'          => '2026-12-31',
				'_links'                => serialize( array(
					array( 'network' => 'Instagram', 'url' => 'https://instagram.com/zengardenspa' ),
					array( 'network' => 'Facebook', 'url' => 'https://facebook.com/zengardenspa' ),
				) ),
				'_price_range'           => '$$$$',
				'_amenities'             => serialize( array( 'WiFi', 'Parking', 'Wheelchair Accessible' ) ),
				'_tagline'               => 'Find Your Peace.',
				'_atmosphere'            => 'Fine Dining',
				'_accepts_reservations'  => '1',
				'_reservation_url'       => 'https://zengardenspa.example.com/book',
			),
			'categories' => array( 'Health & Beauty' ),
			'tags'       => array( 'Luxury', 'Reservations', 'Wheelchair Accessible', 'Top Rated' ),
			'regions'    => array( 'Miami' ),
			'package'    => 'Enterprise Listing',
			'reviews'    => array(
				array(
					'author'  => 'Jennifer Adams',
					'email'   => 'jadams@example.com',
					'content' => 'The hot stone massage was absolutely incredible. Best spa experience in Miami.',
					'rating'  => 10,
					'date'    => '2024-07-10 15:00:00',
				),
			),
		),

		// --- Listing 6: Preview status (unpublished draft) ---
		array(
			'post_data' => array(
				'post_title'     => 'Mountain View Brewing Company',
				'post_content'   => 'Craft brewery and taproom with 12 rotating taps featuring our house-brewed IPAs, stouts, sours, and seasonal specials. Live music on weekends.',
				'post_excerpt'   => 'Craft brewery with 12 rotating taps and live music.',
				'post_status'    => 'preview',
				'comment_status' => 'open',
			),
			'meta' => array(
				'_case27_listing_type'  => 'place',
				'_featured'             => '0',
				'_claimed'              => '0',
				'_job_email'            => 'info@mountainviewbrewing.example.com',
				'_job_phone'            => '+1 (720) 555-0601',
				'_job_website'          => 'https://mountainviewbrewing.example.com',
				'_price_range'          => '$$',
				'_amenities'            => serialize( array( 'Outdoor Seating', 'Live Music', 'Pet Friendly', 'Parking' ) ),
				'_tagline'              => 'Brewed with Altitude.',
				'_founded_year'         => '2020',
				'_atmosphere'           => 'Casual',
			),
			'geolocation' => array(
				'lat'     => '39.7392',
				'lng'     => '-104.9903',
				'city'    => 'Denver',
				'state'   => 'Colorado',
				'country' => 'United States',
				'street'  => '2000 Larimer St',
				'zip'     => '80205',
			),
			'categories' => array( 'Nightlife' ),
			'tags'       => array( 'Live Music', 'Outdoor Seating', 'Pet Friendly', 'New', 'Locally Owned' ),
			'regions'    => array( 'Denver' ),
			'reviews'    => array(),
		),

		// --- Listing 7: Unpublish status ---
		array(
			'post_data' => array(
				'post_title'     => 'Sunrise Yoga Studio',
				'post_content'   => 'Hot yoga and meditation studio with classes for all levels. Experienced instructors in Vinyasa, Hatha, Yin, and Kundalini yoga.',
				'post_excerpt'   => 'Hot yoga and meditation studio for all levels.',
				'post_status'    => 'unpublish',
				'comment_status' => 'open',
			),
			'meta' => array(
				'_case27_listing_type'  => 'place',
				'_featured'             => '0',
				'_claimed'              => '1',
				'_job_email'            => 'namaste@sunriseyoga.example.com',
				'_job_phone'            => '+1 (206) 555-0701',
				'_job_website'          => 'https://sunriseyogastudio.example.com',
				'_price_range'          => '$$',
				'_amenities'            => serialize( array( 'Parking', 'WiFi' ) ),
			),
			'geolocation' => array(
				'lat'     => '47.6062',
				'lng'     => '-122.3321',
				'city'    => 'Seattle',
				'state'   => 'Washington',
				'country' => 'United States',
				'street'  => '500 Pike St',
				'zip'     => '98101',
			),
			'categories' => array( 'Health & Beauty' ),
			'tags'       => array( 'Family Friendly' ),
			'regions'    => array( 'Seattle' ),
			'reviews'    => array(),
		),
	);

	// Insert all place listings.
	foreach ( $place_listings as $index => $listing ) {
		$post_id = mylisting_insert_listing( $listing, $categories, $tags, $regions, $packages, $author_id );
		if ( $post_id ) {
			echo "Created place listing #{$index}: '{$listing['post_data']['post_title']}' (ID: {$post_id}, status: {$listing['post_data']['post_status']})\n";
		}
	}

	// =========================================================================
	// EVENT LISTINGS
	// =========================================================================

	$event_listings = array(
		// --- Event 1: Conference, all fields populated ---
		array(
			'post_data' => array(
				'post_title'     => 'DevConnect 2025 - Global Developer Summit',
				'post_content'   => '<p>The premier developer conference bringing together 10,000+ engineers, architects, and tech leaders from around the world. Four days of keynotes, workshops, hackathons, and unmatched networking opportunities.</p><p>Topics include AI/ML, Cloud Native, Web3, DevOps, Security, and more. This year features 200+ sessions across 8 tracks.</p>',
				'post_excerpt'   => 'Four-day global developer summit with 200+ sessions and 10,000+ attendees.',
				'post_status'    => 'publish',
				'comment_status' => 'open',
			),
			'meta' => array(
				'_case27_listing_type'   => 'event',
				'_featured'              => '1',
				'_claimed'               => '1',
				'_job_email'             => 'info@devconnect2025.example.com',
				'_job_phone'             => '+1 (415) 555-0801',
				'_job_website'           => 'https://devconnect2025.example.com',
				'_job_video_url'         => 'https://youtube.com/watch?v=devconnect-promo',
				'_job_expires'           => '2025-11-30',
				'_links'                 => serialize( array(
					array( 'network' => 'Twitter', 'url' => 'https://twitter.com/devconnect' ),
					array( 'network' => 'LinkedIn', 'url' => 'https://linkedin.com/company/devconnect' ),
					array( 'network' => 'YouTube', 'url' => 'https://youtube.com/devconnect' ),
					array( 'network' => 'GitHub', 'url' => 'https://github.com/devconnect' ),
				) ),
				'_case27_average_rating' => '4.7',
				'_event_date'            => '2025-10-15',
				'_event_end_date'        => '2025-10-18',
				'_event_time'            => '8:00 AM - 6:00 PM',
				'_ticket_price'          => '799',
				'_ticket_url'            => 'https://tickets.devconnect2025.example.com',
				'_event_type'            => 'Conference',
				'_age_restriction'       => 'All Ages',
				'_organizer_name'        => 'DevConnect Foundation',
				'_organizer_email'       => 'organizers@devconnect.example.com',
				'_venue_name'            => 'Moscone Center',
				'_max_attendees'         => '10000',
				'_is_free'               => '0',
				'_dress_code'            => 'Business Casual',
				'_event_features'        => serialize( array( 'Food & Drinks', 'Live Streaming', 'Recording Available', 'Q&A Session', 'Networking', 'Certificate' ) ),
				'_event_schedule'        => "Day 1: Opening Keynote, AI/ML Track, Welcome Reception\nDay 2: Cloud Native Track, DevOps Track, Hackathon Kickoff\nDay 3: Security Track, Web3 Track, Hackathon Finals\nDay 4: Closing Keynote, Award Ceremony, Afterparty",
			),
			'geolocation' => array(
				'lat'     => '37.7849',
				'lng'     => '-122.4005',
				'city'    => 'San Francisco',
				'state'   => 'California',
				'country' => 'United States',
				'street'  => '747 Howard St',
				'zip'     => '94103',
			),
			'categories' => array( 'Events', 'Conferences' ),
			'tags'       => array( 'Popular', 'Award Winning', 'Group Friendly' ),
			'regions'    => array( 'San Francisco' ),
			'package'    => 'Enterprise Listing',
			'reviews'    => array(
				array(
					'author'  => 'Alex Nguyen',
					'email'   => 'anguyen@example.com',
					'content' => 'Best tech conference I\'ve attended! The keynotes were inspiring and the workshops were hands-on and practical.',
					'rating'  => 10,
					'date'    => '2024-11-01 17:00:00',
				),
				array(
					'author'  => 'Sarah Kim',
					'email'   => 'skim@example.com',
					'content' => 'Great content and networking. The venue was a bit overwhelming in size. Would have liked smaller breakout sessions.',
					'rating'  => 7,
					'date'    => '2024-11-02 09:00:00',
				),
				array(
					'author'  => 'Marcus Johnson',
					'email'   => 'mjohnson@example.com',
					'content' => 'Well organized and the live streaming quality was excellent for remote attendees.',
					'rating'  => 8,
					'date'    => '2024-11-03 14:00:00',
				),
			),
		),

		// --- Event 2: Free workshop, minimal ---
		array(
			'post_data' => array(
				'post_title'     => 'Introduction to Watercolor Painting',
				'post_content'   => 'A beginner-friendly workshop where you will learn basic watercolor techniques including wet-on-wet, dry brush, and color mixing. All materials provided.',
				'post_excerpt'   => 'Free beginner watercolor painting workshop.',
				'post_status'    => 'publish',
				'comment_status' => 'open',
			),
			'meta' => array(
				'_case27_listing_type'  => 'event',
				'_featured'             => '0',
				'_claimed'              => '0',
				'_job_email'            => 'art@communityarts.example.com',
				'_event_date'           => '2025-05-20',
				'_event_time'           => '2:00 PM - 5:00 PM',
				'_ticket_price'         => '0',
				'_event_type'           => 'Workshop',
				'_age_restriction'      => 'All Ages',
				'_organizer_name'       => 'Community Arts Foundation',
				'_venue_name'           => 'Lincoln Center Community Room',
				'_max_attendees'        => '30',
				'_is_free'              => '1',
				'_event_features'       => serialize( array( 'Certificate' ) ),
			),
			'geolocation' => array(
				'lat'     => '40.7725',
				'lng'     => '-73.9835',
				'city'    => 'New York',
				'state'   => 'New York',
				'country' => 'United States',
				'street'  => '10 Lincoln Center Plaza',
				'zip'     => '10023',
			),
			'categories' => array( 'Events', 'Workshops' ),
			'tags'       => array( 'Family Friendly', 'Budget Friendly', 'Kid Friendly' ),
			'regions'    => array( 'New York' ),
			'package'    => 'Free Listing',
			'reviews'    => array(),
		),

		// --- Event 3: Concert, 21+, private status ---
		array(
			'post_data' => array(
				'post_title'     => 'Neon Nights Electronic Music Festival',
				'post_content'   => '<p>Three-day electronic music festival featuring world-renowned DJs and producers across 4 stages. Experience immersive light shows, art installations, and sunrise sets in the desert.</p>',
				'post_excerpt'   => 'Three-day electronic music festival with world-class DJs.',
				'post_status'    => 'private',
				'comment_status' => 'open',
			),
			'meta' => array(
				'_case27_listing_type'   => 'event',
				'_featured'              => '1',
				'_claimed'               => '1',
				'_job_email'             => 'info@neonnights.example.com',
				'_job_phone'             => '+1 (702) 555-0901',
				'_job_website'           => 'https://neonnightsfestival.example.com',
				'_job_video_url'         => 'https://youtube.com/watch?v=neonnights-teaser',
				'_job_expires'           => '2025-09-01',
				'_links'                 => serialize( array(
					array( 'network' => 'Instagram', 'url' => 'https://instagram.com/neonnights' ),
					array( 'network' => 'TikTok', 'url' => 'https://tiktok.com/@neonnights' ),
					array( 'network' => 'Spotify', 'url' => 'https://spotify.com/neonnights-playlist' ),
				) ),
				'_event_date'            => '2025-08-22',
				'_event_end_date'        => '2025-08-24',
				'_event_time'            => '4:00 PM - 6:00 AM',
				'_ticket_price'          => '349',
				'_ticket_url'            => 'https://tickets.neonnightsfestival.example.com',
				'_event_type'            => 'Festival',
				'_age_restriction'       => '21+',
				'_organizer_name'        => 'Neon Productions LLC',
				'_organizer_email'       => 'team@neonproductions.example.com',
				'_venue_name'            => 'Desert Oasis Grounds',
				'_max_attendees'         => '50000',
				'_is_free'               => '0',
				'_dress_code'            => 'Festival Attire',
				'_event_features'        => serialize( array( 'Food & Drinks', 'Live Streaming' ) ),
				'_event_schedule'        => "Friday: Gates Open 4PM, Main Stage 6PM\nSaturday: All stages 2PM-6AM\nSunday: All stages 2PM-2AM, Closing Set",
			),
			'geolocation' => array(
				'lat'     => '36.1699',
				'lng'     => '-115.1398',
				'city'    => 'Las Vegas',
				'state'   => 'Nevada',
				'country' => 'United States',
				'street'  => 'Las Vegas Blvd',
				'zip'     => '89109',
			),
			'categories' => array( 'Events', 'Concerts', 'Festivals' ),
			'tags'       => array( 'Popular', 'Open Late', 'Group Friendly', 'Luxury' ),
			'regions'    => array( 'North America' ),
			'package'    => 'Enterprise Listing',
			'reviews'    => array(
				array(
					'author'  => 'Jake Miller',
					'email'   => 'jmiller@example.com',
					'content' => 'UNREAL experience! The production quality was insane and the lineup was stacked.',
					'rating'  => 10,
					'date'    => '2024-08-26 12:00:00',
				),
				array(
					'author'  => 'Mia Garcia',
					'email'   => 'mgarcia@example.com',
					'content' => 'Amazing vibes and music. Could use better water station access though.',
					'rating'  => 7,
					'date'    => '2024-08-27 15:00:00',
				),
			),
		),
	);

	// Insert all event listings.
	foreach ( $event_listings as $index => $listing ) {
		$post_id = mylisting_insert_listing( $listing, $categories, $tags, $regions, $packages, $author_id );
		if ( $post_id ) {
			echo "Created event listing #{$index}: '{$listing['post_data']['post_title']}' (ID: {$post_id})\n";
		}
	}

	// =========================================================================
	// HOTEL LISTINGS
	// =========================================================================

	$hotel_listings = array(
		// --- Hotel 1: Luxury 5-star, all fields ---
		array(
			'post_data' => array(
				'post_title'     => 'The Grand Meridian Hotel & Spa',
				'post_content'   => '<p>Experience unparalleled luxury at The Grand Meridian, a five-star hotel in the heart of Manhattan. Featuring 350 elegantly appointed rooms and suites, a world-class spa, three award-winning restaurants, and panoramic rooftop bar with skyline views.</p><p>Our concierge team provides personalized experiences including theater tickets, private tours, and exclusive dining reservations.</p>',
				'post_excerpt'   => 'Five-star luxury hotel with spa, rooftop bar, and skyline views.',
				'post_status'    => 'publish',
				'comment_status' => 'open',
			),
			'meta' => array(
				'_case27_listing_type'   => 'hotel',
				'_featured'              => '1',
				'_claimed'               => '1',
				'_job_email'             => 'reservations@grandmeridian.example.com',
				'_job_phone'             => '+1 (212) 555-1001',
				'_job_website'           => 'https://www.grandmeridianhotel.example.com',
				'_job_video_url'         => 'https://youtube.com/watch?v=grandmeridian-tour',
				'_job_expires'           => '2030-12-31',
				'_links'                 => serialize( array(
					array( 'network' => 'Instagram', 'url' => 'https://instagram.com/grandmeridian' ),
					array( 'network' => 'Facebook', 'url' => 'https://facebook.com/grandmeridian' ),
					array( 'network' => 'Twitter', 'url' => 'https://twitter.com/grandmeridian' ),
					array( 'network' => 'Pinterest', 'url' => 'https://pinterest.com/grandmeridian' ),
				) ),
				'_case27_average_rating' => '4.9',
				'_star_rating'           => '5 Stars',
				'_price_per_night'       => '450',
				'_check_in_time'         => '3:00 PM',
				'_check_out_time'        => '11:00 AM',
				'_total_rooms'           => '350',
				'_booking_url'           => 'https://booking.grandmeridianhotel.example.com',
				'_hotel_amenities'       => serialize( array( 'Pool', 'Gym', 'Spa', 'Restaurant', 'Bar', 'Room Service', 'Free WiFi', 'Parking', 'Airport Shuttle', 'Business Center', 'Concierge' ) ),
				'_property_type'         => 'Hotel',
				'_allows_pets'           => '0',
				'_cancellation_policy'   => "Free cancellation up to 48 hours before check-in.\nLate cancellation (within 48 hours): First night charge.\nNo-show: Full stay charge.\nGroup bookings (5+ rooms): 7-day cancellation policy.",
			),
			'geolocation' => array(
				'lat'     => '40.7614',
				'lng'     => '-73.9776',
				'city'    => 'New York',
				'state'   => 'New York',
				'country' => 'United States',
				'street'  => '300 Park Avenue',
				'zip'     => '10022',
			),
			'workhours' => array(
				'monday'    => array( array( 'open' => '00:00', 'close' => '23:59' ) ),
				'tuesday'   => array( array( 'open' => '00:00', 'close' => '23:59' ) ),
				'wednesday' => array( array( 'open' => '00:00', 'close' => '23:59' ) ),
				'thursday'  => array( array( 'open' => '00:00', 'close' => '23:59' ) ),
				'friday'    => array( array( 'open' => '00:00', 'close' => '23:59' ) ),
				'saturday'  => array( array( 'open' => '00:00', 'close' => '23:59' ) ),
				'sunday'    => array( array( 'open' => '00:00', 'close' => '23:59' ) ),
			),
			'workhours_tz' => 'America/New_York',
			'categories'   => array( 'Hotels', 'Luxury Hotel' ),
			'tags'         => array( 'Luxury', 'Award Winning', 'Top Rated', 'Reservations', 'Wheelchair Accessible' ),
			'regions'      => array( 'New York' ),
			'package'      => 'Enterprise Listing',
			'reviews'      => array(
				array(
					'author'  => 'Catherine Moore',
					'email'   => 'cmoore@example.com',
					'content' => 'Absolutely breathtaking hotel. The rooftop bar views are incredible and the spa is world-class. Worth every penny.',
					'rating'  => 10,
					'date'    => '2024-06-15 11:00:00',
				),
				array(
					'author'  => 'William Hart',
					'email'   => 'whart@example.com',
					'content' => 'Excellent service and beautiful rooms. The concierge arranged a private Central Park tour for us. Amazing experience.',
					'rating'  => 9,
					'date'    => '2024-07-22 14:30:00',
				),
				array(
					'author'  => 'Linda Park',
					'email'   => 'lpark@example.com',
					'content' => 'Very luxurious but the valet parking is ridiculously expensive. Room was spotless and the bed was heavenly.',
					'rating'  => 8,
					'date'    => '2024-09-05 10:00:00',
				),
				array(
					'author'  => 'Daniel Foster',
					'email'   => 'dfoster@example.com',
					'content' => 'Stayed for a business trip. Business center was well-equipped and the restaurant breakfast buffet was excellent.',
					'rating'  => 9,
					'date'    => '2024-10-12 08:00:00',
				),
			),
		),

		// --- Hotel 2: Budget hostel, minimal fields ---
		array(
			'post_data' => array(
				'post_title'     => 'Wanderlust Hostel & Social Hub',
				'post_content'   => 'Budget-friendly hostel for backpackers and solo travelers. Dorms and private rooms available. Common kitchen, lounge, rooftop terrace, and weekly social events.',
				'post_excerpt'   => 'Budget-friendly hostel for backpackers with social events.',
				'post_status'    => 'publish',
				'comment_status' => 'open',
			),
			'meta' => array(
				'_case27_listing_type'  => 'hotel',
				'_featured'             => '0',
				'_claimed'              => '1',
				'_job_email'            => 'stay@wanderlusthostel.example.com',
				'_job_phone'            => '+1 (305) 555-1101',
				'_job_website'          => 'https://wanderlusthostel.example.com',
				'_star_rating'          => '2 Stars',
				'_price_per_night'      => '35',
				'_check_in_time'        => '2:00 PM',
				'_check_out_time'       => '10:00 AM',
				'_total_rooms'          => '50',
				'_booking_url'          => 'https://hostelworld.com/wanderlust',
				'_hotel_amenities'      => serialize( array( 'Free WiFi', 'Bar' ) ),
				'_property_type'        => 'Hostel',
				'_allows_pets'          => '0',
				'_cancellation_policy'  => 'Free cancellation up to 24 hours before check-in.',
			),
			'geolocation' => array(
				'lat'     => '25.7617',
				'lng'     => '-80.1918',
				'city'    => 'Miami',
				'state'   => 'Florida',
				'country' => 'United States',
				'street'  => '236 Collins Ave',
				'zip'     => '33139',
			),
			'categories' => array( 'Hotels', 'Hostel' ),
			'tags'       => array( 'Budget Friendly', 'Solo Traveler', 'Group Friendly', 'WiFi', 'Walk-ins Welcome' ),
			'regions'    => array( 'Miami' ),
			'package'    => 'Basic Listing',
			'reviews'    => array(
				array(
					'author'  => 'Backpacker Bob',
					'email'   => 'bob@example.com',
					'content' => 'Perfect for budget travelers! Clean, great social atmosphere, and unbeatable location in South Beach.',
					'rating'  => 8,
					'date'    => '2024-12-01 16:00:00',
				),
				array(
					'author'  => 'Travel Jane',
					'email'   => 'jane@example.com',
					'content' => 'The rooftop terrace is amazing for sunsets. Dorms could be cleaner. Met great people at the social events!',
					'rating'  => 6,
					'date'    => '2025-01-10 12:00:00',
				),
			),
		),

		// --- Hotel 3: B&B, pet-friendly ---
		array(
			'post_data' => array(
				'post_title'     => 'Rosewood Cottage Bed & Breakfast',
				'post_content'   => '<p>Charming Victorian bed & breakfast nestled in the hills of San Francisco. Each of our 8 individually decorated rooms features antique furnishings, luxury linens, and modern amenities.</p><p>Wake up to a gourmet breakfast prepared by our in-house chef using local, organic ingredients. Complimentary afternoon tea and wine hour daily.</p>',
				'post_excerpt'   => 'Charming Victorian B&B with gourmet breakfast and wine hour.',
				'post_status'    => 'publish',
				'comment_status' => 'open',
			),
			'meta' => array(
				'_case27_listing_type'   => 'hotel',
				'_featured'              => '1',
				'_claimed'               => '1',
				'_job_email'             => 'stay@rosewoodcottage.example.com',
				'_job_phone'             => '+1 (415) 555-1201',
				'_job_website'           => 'https://rosewoodcottagebb.example.com',
				'_job_expires'           => '2027-12-31',
				'_links'                 => serialize( array(
					array( 'network' => 'Instagram', 'url' => 'https://instagram.com/rosewoodcottage' ),
					array( 'network' => 'TripAdvisor', 'url' => 'https://tripadvisor.com/rosewoodcottage' ),
				) ),
				'_case27_average_rating' => '4.6',
				'_star_rating'           => '4 Stars',
				'_price_per_night'       => '189',
				'_check_in_time'         => '3:00 PM',
				'_check_out_time'        => '11:00 AM',
				'_total_rooms'           => '8',
				'_booking_url'           => 'https://rosewoodcottagebb.example.com/book',
				'_hotel_amenities'       => serialize( array( 'Free WiFi', 'Parking', 'Restaurant', 'Pet Friendly', 'Concierge' ) ),
				'_property_type'         => 'Bed & Breakfast',
				'_allows_pets'           => '1',
				'_cancellation_policy'   => "Free cancellation up to 72 hours before check-in.\nWithin 72 hours: 50% charge.\nPeak season (Jun-Sep): Non-refundable.",
			),
			'geolocation' => array(
				'lat'     => '37.7749',
				'lng'     => '-122.4194',
				'city'    => 'San Francisco',
				'state'   => 'California',
				'country' => 'United States',
				'street'  => '1800 Pacific Ave',
				'zip'     => '94109',
			),
			'workhours' => array(
				'monday'    => array( array( 'open' => '07:00', 'close' => '22:00' ) ),
				'tuesday'   => array( array( 'open' => '07:00', 'close' => '22:00' ) ),
				'wednesday' => array( array( 'open' => '07:00', 'close' => '22:00' ) ),
				'thursday'  => array( array( 'open' => '07:00', 'close' => '22:00' ) ),
				'friday'    => array( array( 'open' => '07:00', 'close' => '22:00' ) ),
				'saturday'  => array( array( 'open' => '07:00', 'close' => '22:00' ) ),
				'sunday'    => array( array( 'open' => '07:00', 'close' => '22:00' ) ),
			),
			'workhours_tz' => 'America/Los_Angeles',
			'categories'   => array( 'Hotels', 'Bed & Breakfast' ),
			'tags'         => array( 'Pet Friendly', 'Romantic', 'Locally Owned', 'Top Rated', 'Luxury' ),
			'regions'      => array( 'San Francisco' ),
			'package'      => 'Premium Listing',
			'reviews'      => array(
				array(
					'author'  => 'Helen Wright',
					'email'   => 'hwright@example.com',
					'content' => 'Absolutely charming! The breakfast was incredible and our hosts made us feel right at home. The wine hour was a lovely touch.',
					'rating'  => 10,
					'date'    => '2024-09-20 10:00:00',
				),
				array(
					'author'  => 'Tom & Sue Baker',
					'email'   => 'bakers@example.com',
					'content' => 'Perfect anniversary getaway. The room was beautifully decorated and they even brought us champagne. So happy they allow dogs!',
					'rating'  => 9,
					'date'    => '2024-10-05 09:30:00',
				),
				array(
					'author'  => 'Nancy Cole',
					'email'   => 'ncole@example.com',
					'content' => 'Nice B&B but parking is limited (only 4 spots). The neighborhood is lovely for walks though.',
					'rating'  => 7,
					'date'    => '2024-11-18 11:00:00',
				),
			),
		),
	);

	// Insert all hotel listings.
	foreach ( $hotel_listings as $index => $listing ) {
		$post_id = mylisting_insert_listing( $listing, $categories, $tags, $regions, $packages, $author_id );
		if ( $post_id ) {
			echo "Created hotel listing #{$index}: '{$listing['post_data']['post_title']}' (ID: {$post_id})\n";
		}
	}
}

/**
 * Insert a single MyListing listing with all its data.
 *
 * @param array $listing    Listing data.
 * @param array $categories Category term IDs map.
 * @param array $tags       Tag term IDs map.
 * @param array $regions    Region term IDs map.
 * @param array $packages   Package product IDs map.
 * @param int   $author_id  Post author ID.
 *
 * @return int|false The post ID or false on failure.
 */
function mylisting_insert_listing( $listing, $categories, $tags, $regions, $packages, $author_id ) {
	global $wpdb;

	$post_data = wp_parse_args( $listing['post_data'], array(
		'post_type'      => 'job_listing',
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

	// Set all meta values.
	if ( ! empty( $listing['meta'] ) ) {
		foreach ( $listing['meta'] as $key => $value ) {
			update_post_meta( $post_id, $key, $value );
		}
	}

	// Set geolocation data (both in wp_mylisting_locations table and WP Job Manager meta).
	if ( ! empty( $listing['geolocation'] ) ) {
		$geo = $listing['geolocation'];

		// Store WP Job Manager geolocation meta (used as fallback by the importer).
		update_post_meta( $post_id, 'geolocation_lat', $geo['lat'] );
		update_post_meta( $post_id, 'geolocation_long', $geo['lng'] );
		update_post_meta( $post_id, 'geolocation_city', $geo['city'] );
		update_post_meta( $post_id, 'geolocation_state_long', $geo['state'] );
		update_post_meta( $post_id, 'geolocation_country_long', $geo['country'] );
		update_post_meta( $post_id, 'geolocation_street', $geo['street'] );
		update_post_meta( $post_id, 'geolocation_postcode', $geo['zip'] );

		// Also insert into wp_mylisting_locations table (primary source).
		$locations_table = $wpdb->prefix . 'mylisting_locations';
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$locations_table}'" ) === $locations_table ) {
			$wpdb->insert( $locations_table, array(
				'listing_id' => $post_id,
				'address'    => $geo['street'] . ', ' . $geo['city'] . ', ' . $geo['state'] . ' ' . $geo['zip'],
				'lat'        => $geo['lat'],
				'lng'        => $geo['lng'],
			), array( '%d', '%s', '%f', '%f' ) );
		} else {
			// Create the locations table.
			$charset_collate = $wpdb->get_charset_collate();
			$sql = "CREATE TABLE {$locations_table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				listing_id bigint(20) unsigned NOT NULL,
				address text,
				lat decimal(10,7),
				lng decimal(10,7),
				PRIMARY KEY (id),
				KEY listing_id (listing_id)
			) {$charset_collate};";

			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			dbDelta( $sql );

			$wpdb->insert( $locations_table, array(
				'listing_id' => $post_id,
				'address'    => $geo['street'] . ', ' . $geo['city'] . ', ' . $geo['state'] . ' ' . $geo['zip'],
				'lat'        => $geo['lat'],
				'lng'        => $geo['lng'],
			), array( '%d', '%s', '%f', '%f' ) );
		}
	}

	// Insert work hours.
	if ( ! empty( $listing['workhours'] ) ) {
		$tz = isset( $listing['workhours_tz'] ) ? $listing['workhours_tz'] : 'America/New_York';
		mylisting_insert_workhours( $post_id, $listing['workhours'], $tz );
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
			wp_set_object_terms( $post_id, $cat_ids, 'job_listing_category' );
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
			wp_set_object_terms( $post_id, $tag_ids, 'case27_job_listing_tags' );
		}
	}

	// Assign regions.
	if ( ! empty( $listing['regions'] ) ) {
		$region_ids = array();
		foreach ( $listing['regions'] as $region_name ) {
			if ( isset( $regions[ $region_name ] ) ) {
				$region_ids[] = (int) $regions[ $region_name ];
			}
		}
		if ( ! empty( $region_ids ) ) {
			wp_set_object_terms( $post_id, $region_ids, 'region' );
		}
	}

	// Assign package.
	if ( ! empty( $listing['package'] ) && ! empty( $packages[ $listing['package'] ] ) ) {
		$package_product_id = $packages[ $listing['package'] ];
		update_post_meta( $post_id, '_user_package_id', $package_product_id );
	}

	// Create reviews/comments.
	if ( ! empty( $listing['reviews'] ) ) {
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
			) );

			if ( $comment_id && ! is_wp_error( $comment_id ) ) {
				// MyListing uses 1-10 rating scale.
				update_comment_meta( $comment_id, '_case27_post_rating', $review['rating'] );
			}
		}
	}

	return $post_id;
}

// Auto-run if executed directly.
if ( defined( 'ABSPATH' ) ) {
	mylisting_generate_sample_data();
}
