<?php
/**
 * Implements example command.
 */
class Convert_Listing_Command {

	/**
	 * Prints a greeting.
	 *
	 * ## OPTIONS
	 *
	 *
	 * [--removetable]
	 * : Whether or not to greet the person with success or error.
	 * ---
	 * default: success
	 * options:
	 *   - success
	 *   - error
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp convert listing --removetable=false/true
	 *
	 * @when after_wp_load
	 */
	function listing( $args, $assoc_args ) {

		// Print the message with type
		$removetable = $assoc_args['removetable'];
		global $wpdb;
		$pmd_listings = 'select * from pmd_listings';
		$listings_results = $wpdb->get_results( $pmd_listings );
//		error_log( print_r( $listings_results, true) );
		if( !empty( $listings_results ) ){
			foreach ( $listings_results as $key => $listing ){
				global $wpdb;
				$post_table = $wpdb->prefix.'posts';
				if( !empty( $listing->id ) ){
					$status = ( !empty( $listing->status ) && 'active' == $listing->status )? 'publish': $listing->status;
					$status = ( !empty( $listing->status ) && 'suspended' == $listing->status )? 'trash': $status;
					$excerpt = ( !empty( $listing->description_Short ) )? $listing->description_Short: '';
//					$wpdb->insert(
//						$post_table,
//						array(
//							'id' => $listing->id,
//							'post_author' => $listing->user_id,
//							'post_title' => $listing->title,
//							'post_name' => $listing->friendly_url,
//							'post_excerpt' => $excerpt,
//							'post_content' => $listing->description,
//							'post_date' => $listing->date,
//							'post_date_gmt' => $listing->date,
//							'post_modified' => $listing->date_update,
//							'post_modified_gmt' => $listing->date_update,
//							'comment_status' => 'open',
//							'ping_status' => 'closed',
//							'post_parent' => 0,
//							'guid' => get_site_url() . '/places/' . $listing->friendly_url,
//							'menu_order' => 0,
//							'post_type' => 'gd_place',
//							'comment_count' => 0,
//
//						),
//						array('%d','%d','%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%d')
//					);

					$gd_place_detail_table = $wpdb->prefix.'geodir_gd_place_detail';
					$inserted_post = $wpdb->insert(
						$gd_place_detail_table,
						array(
							'post_id' => $listing->id,
							'post_title' => $listing->title,
							'post_status' => $status,
							'post_tags' => '',

							'post_category' => $listing->primary_category_id,
							'default_category' => $listing->primary_category_id,

							'featured_image' => '',
							'submit_ip' => $listing->ip,
							'overall_rating' => $listing->rating,
							'rating_count' => $listing->rating,

							'street' => $listing->listing_address1,
							'city' => $listing->listing_address2,
							'region' => $listing->location_text_1,
							'country' => $listing->location_text_2,
							'zip' =>  $listing->listing_zip,
							'latitude' => $listing->latitude,
							'longitude' => $listing->longitude,
							'mapview' => '',
							'mapzoom' => '',
							'phone' => $listing->phone,
							'post_dummy' => 0,
							'email' => '',
							'website' => '',
							'twitter' => $listing->twitter_id,
							'facebook' => $listing->facebook_page_id,
							'video' => '',
							'special_offers' => '',
							'timing' => '',
							'price' => '',
							'claimed' => $listing->claimed,
							'is_featured' => $listing->featured,
							'expire_date' => $listing->pagerank_expiration,
						),
						array('%s' ),
						array('%s' ),
						array('%s' ),
						array('%s' ),
						array('%s' ),
						array('%s' ),
						array('%s' ),
						array('%s' ),
						array('%s' ),
						array('%s' ),
						array('%s' ),
						array('%s' ),
						array('%s' ),
						array('%s' ),
						array('%s' ),
						array('%s' ),
						array('%s' ),
						array('%s' ),
						array('%s' ),
						array('%s' ),
						array('%s' ),
						array('%s' ),
						array('%s' ),
						array('%s' ),
						array('%s' ),
						array('%s' ),
						array('%s' ),
						array('%s' ),
						array('%s' ),
						array('%s' ),
						array('%s' ),
						array('%s' )
					);
				}
				error_log( print_r( $listing->id, true) );
				error_log( print_r( $listing->title, true) );
				error_log( print_r( $status, true) );
//				error_log( print_r( $listing->slug, true) );
//				error_log( print_r( $listing->description_Short, true) );
//				error_log( print_r( $listing->description, true) );
//				error_log( print_r( $listing->meta_title, true) );
//				error_log( print_r( $listing->meta_description, true) );
//				error_log( print_r( $listing->meta_keywords, true) );
//				error_log( print_r( $listing->keywords, true) );
//				error_log( print_r( $listing->phone, true) );
//				error_log( print_r( $listing->fax, true) );
//				error_log( print_r( $listing->listing_address1, true) );
//				error_log( print_r( $listing->listing_address2, true) );
//				error_log( print_r( $listing->listing_zip, true) );
//				error_log( print_r( $listing->location_text_1, true) );
//				error_log( print_r( $listing->location_text_2, true) );
//				error_log( print_r( $listing->location_text_3, true) );
//				error_log( print_r( $listing->location_search_text, true) );
//				error_log( print_r( $listing->hours, true) );
//				error_log( print_r( $listing->latitude, true) );
//				error_log( print_r( $listing->longitude, true) );
//				error_log( print_r( $listing->www, true) );
//				error_log( print_r( $listing->www_date_checked, true) );
//				error_log( print_r( $listing->website_clicks, true) );
//				error_log( print_r( $listing->pagerank, true) );
//				error_log( print_r( $listing->pagerank_expiration, true) );
//				error_log( print_r( $listing->ip, true) );
//				error_log( print_r( $listing->date, true) );
//				error_log( print_r( $listing->date_update, true) );
//				error_log( print_r( $listing->ip_update, true) );
//				error_log( print_r( $listing->search_impressions, true) );
//				error_log( print_r( $listing->emails, true) );
//				error_log( print_r( $listing->rating, true) );
//				error_log( print_r( $listing->banner_impression, true) );
//				error_log( print_r( $listing->banner_clicks, true) );
//				error_log( print_r( $listing->countryip, true) );
//				error_log( print_r( $listing->mail, true) );
//				error_log( print_r( $listing->claimed, true) );
//				error_log( print_r( $listing->votes, true) );
//				error_log( print_r( $listing->category_limit, true) );
//				error_log( print_r( $listing->featured, true) );
//				error_log( print_r( $listing->featured_date, true) );
//				error_log( print_r( $listing->facebook_page_id, true) );
//				error_log( print_r( $listing->linkedin_id, true) );
//				error_log( print_r( $listing->linkedin_company_id, true) );
//				error_log( print_r( $listing->twitter_id, true) );
//				error_log( print_r( $listing->pinterest_id, true) );
//				error_log( print_r( $listing->youtube_id, true) );
//				error_log( print_r( $listing->foursquare_id, true) );
//				error_log( print_r( $listing->social_links_allow, true) );
//				error_log( print_r( $listing->instagram_id, true) );
//				error_log( print_r( $listing->status, true) );
//				error_log( print_r( $listing->priority, true) );

			}
			WP_CLI::success('Successfully Updated Listing');
		}

		/**
		 * Import all categories
		 * Import all post from pmd_listings to wp_posts and then to wp_geodir_gd_place_detail
		 * import id and few other extra fields in post meta incase we need that
		 */


		WP_CLI::log('comming here');
		WP_CLI::log($removetable);

	}


	/**
	 * Prints a greeting.
	 *
	 * ## OPTIONS
	 *
	 *
	 * [--removetable]
	 * : Whether or not to greet the person with success or error.
	 * ---
	 * default: success
	 * options:
	 *   - success
	 *   - error
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp convert category --removetable
	 *
	 * @when after_wp_load
	 */
	function category( $args, $assoc_args ) {

		// Print the message with type
		$removetable = ( !empty( $assoc_args['removetable'] ) )? $assoc_args['removetable']: '';
		global $wpdb;
		// get all parent categories first
		$catquery = 'select * from pmd_categories';
		$category_results = $wpdb->get_results( $catquery );
		if( !empty( $category_results )){
			foreach ( $category_results as $key => $value ){
				error_log( print_r( $value->title, true) );
				error_log( print_r( $value->friendly_url, true) );
				error_log( print_r( $value->id, true) );
				error_log( print_r( $value->parent_id, true) );
				global $wpdb;
				$term_table = $wpdb->prefix.'terms';
				$wpdb->insert(
					$term_table,
					array(
						'term_id' => $value->id,
						'name' => $value->title,
						'slug' => $value->friendly_url
					),
					array('%d','%s', '%s')
				);

				$term_taxonomy = $wpdb->prefix.'term_taxonomy';
				$wpdb->insert(
					$term_taxonomy,
					array(
						'term_taxonomy_id' => $value->id,
						'term_id' => $value->id,
						'taxonomy' => 'gd_placecategory',
						'parent' => $value->parent_id,
						'count' =>$value->count_total,
					),
					array('%d','%d', '%s', '%d', '%d')
				);
			}
			WP_CLI::success('Successfully Updated Categories');
		}
		if( !empty( $removetable ) ){
			$dropcatquery = 'drop table pmd_categories';
			$wpdb->get_results( $dropcatquery );
			WP_CLI::success('Successfully Removed old table');
		}
	}



}

if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::add_command( 'convert', 'Convert_Listing_Command' );
}