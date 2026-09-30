<?php
/**
 * Classified Listing Converter Class.
 *
 * @since     2.3.0
 * @package   GeoDir_Converter
 */

namespace GeoDir_Converter\Importers;

use WP_Error;
use GeoDir_Media;
use GeoDir_User;
use GeoDir_Converter\Abstracts\GeoDir_Converter_Importer;

defined( 'ABSPATH' ) || exit;

/**
 * Main converter class for importing from the Classified Listing plugin by RadiusTheme.
 *
 * @since 2.3.0
 */
class GeoDir_Converter_Classified_Listing extends GeoDir_Converter_Importer {
	/**
	 * Listing post type.
	 *
	 * @var string
	 */
	const POST_TYPE_LISTING = 'rtcl_listing';

	/**
	 * Legacy custom field post type.
	 *
	 * @var string
	 */
	const POST_TYPE_CUSTOM_FIELD = 'rtcl_cf';

	/**
	 * Taxonomy identifier for categories.
	 *
	 * @var string
	 */
	const TAX_CATEGORY = 'rtcl_category';

	/**
	 * Taxonomy identifier for locations.
	 *
	 * @var string
	 */
	const TAX_LOCATION = 'rtcl_location';

	/**
	 * Taxonomy identifier for tags.
	 *
	 * @var string
	 */
	const TAX_TAG = 'rtcl_tag';

	/**
	 * Form builder forms table (without prefix).
	 *
	 * @var string
	 */
	const FORMS_TABLE = 'rtcl_forms';

	/**
	 * GD field holding the source listing ID.
	 *
	 * The importer ID contains a hyphen, which is not valid in a column name.
	 *
	 * @var string
	 */
	const SOURCE_ID_FIELD = 'classified_listing_id';

	/**
	 * Classified Listing meta keys.
	 */
	const META_PRICE          = 'price';
	const META_MAX_PRICE      = '_rtcl_max_price';
	const META_PRICE_TYPE     = 'price_type';
	const META_PRICE_UNIT     = '_rtcl_price_unit';
	const META_PRICING_TYPE   = '_rtcl_listing_pricing';
	const META_AD_TYPE        = 'ad_type';
	const META_ADDRESS        = 'address';
	const META_GEO_ADDRESS    = '_rtcl_geo_address';
	const META_ZIPCODE        = 'zipcode';
	const META_PHONE          = 'phone';
	const META_WHATSAPP       = '_rtcl_whatsapp_number';
	const META_TELEGRAM       = '_rtcl_telegram';
	const META_EMAIL          = 'email';
	const META_WEBSITE        = 'website';
	const META_LATITUDE       = 'latitude';
	const META_LONGITUDE      = 'longitude';
	const META_VIDEO_URLS     = '_rtcl_video_urls';
	const META_SOCIAL         = '_rtcl_social_profiles';
	const META_BUSINESS_HOURS = '_rtcl_bhs';
	const META_EXPIRY_DATE    = 'expiry_date';
	const META_NEVER_EXPIRES  = 'never_expires';
	const META_FEATURED       = 'featured';
	const META_SOLD           = '_rtcl_mark_as_sold';
	const META_IMAGES_ORDER   = '_rtcl_attachments_order';
	const META_FAVOURITES     = 'rtcl_favourites';

	/**
	 * The single instance of the class.
	 *
	 * @var static
	 */
	protected static $instance;

	/**
	 * The importer ID.
	 *
	 * @var string
	 */
	protected $importer_id = 'classified-listing';

	/**
	 * Listing statuses to import.
	 *
	 * The temporary 'rtcl-temp' status holds abandoned submissions and is excluded.
	 *
	 * @var array
	 */
	protected $post_statuses = array( 'publish', 'future', 'pending', 'draft', 'private', 'rtcl-reviewed', 'rtcl-pending', 'rtcl-expired' );

	/**
	 * Number of listings per queued import task.
	 *
	 * @var int
	 */
	protected $batch_size = 10;

	/**
	 * Cached custom fields definition.
	 *
	 * @var array|null
	 */
	private $custom_fields_cache = null;

	/**
	 * Source custom fields that have no GeoDirectory equivalent.
	 *
	 * @var array
	 */
	private $unsupported_fields = array();

	/**
	 * Cached location terms, keyed by term ID.
	 *
	 * @var array|null
	 */
	private $location_terms_cache = null;

	/**
	 * Whether any ad is marked as sold.
	 *
	 * @var bool|null
	 */
	private $has_sold_ads = null;

	/**
	 * Cached lowercase GeoDirectory country names mapped to their canonical name.
	 *
	 * @var array|null
	 */
	private $countries_cache = null;

	/**
	 * Initialize hooks.
	 *
	 * @since 2.3.0
	 */
	protected function init() {}

	/**
	 * Get importer title.
	 *
	 * @since 2.3.0
	 *
	 * @return string Importer title.
	 */
	public function get_title() {
		return __( 'Classified Listing', 'geodir-converter' );
	}

	/**
	 * Get importer description.
	 *
	 * @since 2.3.0
	 *
	 * @return string Importer description.
	 */
	public function get_description() {
		return __( 'Import ads, categories, tags, custom fields, locations, reviews and favourites from the Classified Listing plugin (Classima theme).', 'geodir-converter' );
	}

	/**
	 * Get importer icon URL.
	 *
	 * @since 2.3.0
	 *
	 * @return string Icon URL.
	 */
	public function get_icon() {
		return GEODIR_CONVERTER_PLUGIN_URL . 'assets/images/classified-listing.png';
	}

	/**
	 * Get importer task action.
	 *
	 * @since 2.3.0
	 *
	 * @return string Import action identifier.
	 */
	public function get_action() {
		return self::ACTION_IMPORT_CATEGORIES;
	}

	/**
	 * Check if a field should be skipped during import.
	 *
	 * @since 2.3.0
	 *
	 * @param string $field_name The field name to check.
	 * @return bool True if the field should be skipped, false otherwise.
	 */
	protected function should_skip_field( $field_name ) {
		$skip_fields = array(
			'images',
			'post_images',
		);

		if ( in_array( $field_name, $skip_fields, true ) ) {
			return true;
		}

		return parent::should_skip_field( $field_name );
	}

	/**
	 * Render importer settings.
	 *
	 * @since 2.3.0
	 *
	 * @return void
	 */
	public function render_settings() {
		?>
		<form class="geodir-converter-settings-form" method="post">
			<h6 class="fs-base"><?php esc_html_e( 'Classified Listing Importer Settings', 'geodir-converter' ); ?></h6>

			<?php
			if ( ! $this->is_plugin_active() ) {
				if ( $this->has_source_data() ) {
					aui()->alert(
						array(
							'type'    => 'warning',
							'heading' => esc_html__( 'Classified Listing plugin not detected.', 'geodir-converter' ),
							'content' => esc_html__( 'Classified Listing data was found in the database, so the importer will still work with it. Activate the plugin to make sure all of its settings are available.', 'geodir-converter' ),
							'class'   => 'mb-3',
						),
						true
					);
				} else {
					$this->render_plugin_notice(
						esc_html__( 'Classified Listing', 'geodir-converter' ),
						'listings',
						esc_url( 'https://wordpress.org/plugins/classified-listing/' )
					);
				}
			}

			$this->display_post_type_select();
			$this->display_author_select( true );
			$this->display_test_mode_checkbox();
			$this->display_progress();
			$this->display_logs( $this->get_logs() );
			$this->display_error_alert();
			?>

			<?php $this->display_action_buttons(); ?>
		</form>
		<?php
	}

	/**
	 * Check if the Classified Listing plugin is active.
	 *
	 * @since 2.3.0
	 *
	 * @return bool True if active.
	 */
	private function is_plugin_active() {
		return defined( 'RTCL_VERSION' ) || class_exists( 'Rtcl' );
	}

	/**
	 * Check if Classified Listing data exists in the database.
	 *
	 * @since 2.3.0
	 *
	 * @return bool True if listings exist.
	 */
	private function has_source_data() {
		global $wpdb;

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s",
				self::POST_TYPE_LISTING
			)
		);

		return (int) $count > 0;
	}

	/**
	 * Validate importer settings.
	 *
	 * @since 2.3.0
	 *
	 * @param array $settings The settings to validate.
	 * @param array $files    The files to validate.
	 * @return array|WP_Error Validated and sanitized settings.
	 */
	public function validate_settings( array $settings, array $files = array() ) {
		$post_types = geodir_get_posttypes();
		$errors     = array();

		$settings['gd_post_type'] = isset( $settings['gd_post_type'] ) && ! empty( $settings['gd_post_type'] ) ? sanitize_text_field( $settings['gd_post_type'] ) : 'gd_place';
		$settings['wp_author_id'] = ( isset( $settings['wp_author_id'] ) && ! empty( $settings['wp_author_id'] ) ) ? absint( $settings['wp_author_id'] ) : get_current_user_id();
		$settings['test_mode']    = ( isset( $settings['test_mode'] ) && ! empty( $settings['test_mode'] ) && 'no' !== $settings['test_mode'] ) ? 'yes' : 'no';

		if ( ! in_array( $settings['gd_post_type'], $post_types, true ) ) {
			$errors[] = esc_html__( 'The selected post type is invalid. Please choose a valid post type.', 'geodir-converter' );
		}

		if ( empty( $settings['wp_author_id'] ) || ! get_userdata( (int) $settings['wp_author_id'] ) ) {
			$errors[] = esc_html__( 'The selected WordPress author is invalid. Please select a valid author to import listings to.', 'geodir-converter' );
		}

		if ( ! empty( $errors ) ) {
			return new WP_Error( 'invalid_import_settings', implode( '<br>', $errors ) );
		}

		return $settings;
	}

	/**
	 * Get next task.
	 *
	 * @since 2.3.0
	 *
	 * @param array $task         The current task.
	 * @param bool  $reset_offset Whether to reset the offset.
	 * @return array|false The next task or false if all tasks are completed.
	 */
	public function next_task( $task, $reset_offset = false ) {
		$task['imported'] = 0;
		$task['failed']   = 0;
		$task['skipped']  = 0;
		$task['updated']  = 0;

		if ( $reset_offset ) {
			$task['offset'] = 0;
		}

		$tasks = array(
			self::ACTION_IMPORT_CATEGORIES,
			self::ACTION_IMPORT_TAGS,
			self::ACTION_IMPORT_FIELDS,
			self::ACTION_PARSE_LISTINGS,
		);

		$key = array_search( $task['action'], $tasks, true );
		if ( false !== $key && $key + 1 < count( $tasks ) ) {
			$task['action'] = $tasks[ $key + 1 ];
			return $task;
		}

		return false;
	}

	/**
	 * Calculate the total number of items to be imported.
	 *
	 * @since 2.3.0
	 *
	 * @return void
	 */
	public function set_import_total() {
		$total_items  = $this->count_terms( self::TAX_CATEGORY );
		$total_items += $this->count_terms( self::TAX_TAG );
		$total_items += count( $this->get_custom_fields() );
		$total_items += $this->count_listings();

		$this->increase_imports_total( $total_items );
	}

	/**
	 * Count the terms of a taxonomy.
	 *
	 * Queried directly so it works whether or not the taxonomy is registered.
	 *
	 * @since 2.3.0
	 *
	 * @param string $taxonomy Taxonomy name.
	 * @return int Number of terms.
	 */
	private function count_terms( $taxonomy ) {
		global $wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s",
				$taxonomy
			)
		);
	}

	/**
	 * Count the listings to import.
	 *
	 * @since 2.3.0
	 *
	 * @return int Number of listings.
	 */
	private function count_listings() {
		global $wpdb;

		$status_placeholders = implode( ',', array_fill( 0, count( $this->post_statuses ), '%s' ) );

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status IN ({$status_placeholders})", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				array_merge( array( self::POST_TYPE_LISTING ), $this->post_statuses )
			)
		);

		return (int) $count;
	}

	/**
	 * Get all terms of a taxonomy, parents ordered before their children.
	 *
	 * @since 2.3.0
	 *
	 * @param string $taxonomy Taxonomy name.
	 * @return array Array of term objects.
	 */
	private function get_source_terms( $taxonomy ) {
		global $wpdb;

		$terms = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT t.*, tt.*
				FROM {$wpdb->terms} AS t
				INNER JOIN {$wpdb->term_taxonomy} AS tt ON t.term_id = tt.term_id
				WHERE tt.taxonomy = %s
				ORDER BY t.name ASC",
				$taxonomy
			)
		);

		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return array();
		}

		return $this->sort_terms_by_hierarchy( $terms );
	}

	/**
	 * Order terms so every parent comes before its children.
	 *
	 * import_taxonomy_terms() resolves a parent through its GD equivalent, which
	 * only exists once the parent has been imported.
	 *
	 * @since 2.3.0
	 *
	 * @param array $terms Term objects.
	 * @return array Sorted term objects.
	 */
	private function sort_terms_by_hierarchy( array $terms ) {
		$by_id    = array();
		$children = array();

		foreach ( $terms as $term ) {
			$by_id[ (int) $term->term_id ] = $term;
		}

		foreach ( $terms as $term ) {
			$parent = (int) $term->parent;

			// Treat terms with a missing parent as top level.
			if ( $parent && ! isset( $by_id[ $parent ] ) ) {
				$parent = 0;
			}

			$children[ $parent ][] = (int) $term->term_id;
		}

		$sorted = array();
		$queue  = isset( $children[0] ) ? $children[0] : array();

		while ( ! empty( $queue ) ) {
			$term_id = array_shift( $queue );

			if ( isset( $sorted[ $term_id ] ) ) {
				continue;
			}

			$sorted[ $term_id ] = $by_id[ $term_id ];

			if ( isset( $children[ $term_id ] ) ) {
				$queue = array_merge( $queue, $children[ $term_id ] );
			}
		}

		// Anything left over belongs to a parent cycle; append it unchanged.
		foreach ( $by_id as $term_id => $term ) {
			if ( ! isset( $sorted[ $term_id ] ) ) {
				$sorted[ $term_id ] = $term;
			}
		}

		return array_values( $sorted );
	}

	/**
	 * Import categories from Classified Listing to GeoDirectory.
	 *
	 * @since 2.3.0
	 *
	 * @param array $task Import task.
	 * @return array|false Result of the import operation.
	 */
	public function task_import_categories( $task ) {
		$this->log( __( 'Categories: Import started.', 'geodir-converter' ) );
		$this->set_import_total();

		$categories = $this->get_source_terms( self::TAX_CATEGORY );

		if ( empty( $categories ) ) {
			$this->log( __( 'Categories: No items to import.', 'geodir-converter' ), 'warning' );
			return $this->next_task( $task );
		}

		if ( $this->is_test_mode() ) {
			$this->increase_succeed_imports( count( $categories ) );
			$this->log(
				sprintf(
					/* translators: %1$d: number imported, %2$d: number failed */
					__( 'Categories: Import completed. %1$d imported, %2$d failed.', 'geodir-converter' ),
					count( $categories ),
					0
				),
				'success'
			);
			return $this->next_task( $task );
		}

		$post_type = $this->get_import_post_type();
		$result    = $this->import_taxonomy_terms( $categories, $post_type . 'category', 'ct_cat_top_desc' );

		$this->import_category_images( $categories );

		$this->increase_succeed_imports( (int) $result['imported'] );
		$this->increase_failed_imports( (int) $result['failed'] );

		$this->log(
			sprintf(
				/* translators: %1$d: number imported, %2$d: number failed */
				__( 'Categories: Import completed. %1$d imported, %2$d failed.', 'geodir-converter' ),
				$result['imported'],
				$result['failed']
			),
			'success'
		);

		return $this->next_task( $task );
	}

	/**
	 * Copy Classified Listing category images to the GD categories.
	 *
	 * The category icon is not imported: it is an rtcl icon-font class with no
	 * Font Awesome equivalent.
	 *
	 * @since 2.3.0
	 *
	 * @param array $categories Source category term objects.
	 * @return void
	 */
	private function import_category_images( array $categories ) {
		foreach ( $categories as $category ) {
			$image_id   = absint( get_term_meta( $category->term_id, '_rtcl_image', true ) );
			$gd_term_id = absint( get_term_meta( $category->term_id, 'gd_equivalent', true ) );

			if ( ! $image_id || ! $gd_term_id ) {
				continue;
			}

			$image_url = wp_get_attachment_url( $image_id );

			if ( ! $image_url ) {
				continue;
			}

			update_term_meta(
				$gd_term_id,
				'ct_cat_default_img',
				array(
					'id'  => $image_id,
					'src' => geodir_file_relative_url( $image_url ),
				)
			);
		}
	}

	/**
	 * Import tags from Classified Listing to GeoDirectory.
	 *
	 * @since 2.3.0
	 *
	 * @param array $task Import task.
	 * @return array|false Result of the import operation.
	 */
	public function task_import_tags( $task ) {
		$this->log( __( 'Tags: Import started.', 'geodir-converter' ) );

		$tags = $this->get_source_terms( self::TAX_TAG );

		if ( empty( $tags ) ) {
			$this->log( __( 'Tags: No items to import.', 'geodir-converter' ), 'warning' );
			return $this->next_task( $task );
		}

		if ( $this->is_test_mode() ) {
			$this->increase_succeed_imports( count( $tags ) );
			$this->log(
				sprintf(
					/* translators: %1$d: number imported, %2$d: number failed */
					__( 'Tags: Import completed. %1$d imported, %2$d failed.', 'geodir-converter' ),
					count( $tags ),
					0
				),
				'success'
			);
			return $this->next_task( $task );
		}

		$post_type = $this->get_import_post_type();
		$result    = $this->import_taxonomy_terms( $tags, $post_type . '_tags', 'ct_cat_top_desc' );

		$this->increase_succeed_imports( (int) $result['imported'] );
		$this->increase_failed_imports( (int) $result['failed'] );

		$this->log(
			sprintf(
				/* translators: %1$d: number imported, %2$d: number failed */
				__( 'Tags: Import completed. %1$d imported, %2$d failed.', 'geodir-converter' ),
				$result['imported'],
				$result['failed']
			),
			'success'
		);

		return $this->next_task( $task );
	}

	/**
	 * Get the custom fields to create in GeoDirectory.
	 *
	 * Combines the fixed Classified Listing fields with the custom fields
	 * discovered in the Form Builder forms and in the legacy field groups.
	 *
	 * @since 2.3.0
	 *
	 * @return array The custom fields.
	 */
	private function get_custom_fields() {
		if ( null !== $this->custom_fields_cache ) {
			return $this->custom_fields_cache;
		}

		$fields = $this->get_standard_fields();

		$reserved = wp_list_pluck( $fields, 'field_key' );
		$reserved = array_merge( $reserved, $this->get_reserved_field_keys() );

		$custom_fields = $this->discover_custom_fields( $reserved );

		if ( ! empty( $custom_fields ) ) {
			$fields = array_merge( $fields, $custom_fields );
		}

		$this->custom_fields_cache = $fields;

		return $fields;
	}

	/**
	 * Field keys that discovered custom fields must not take.
	 *
	 * @since 2.3.0
	 *
	 * @return array Reserved field keys.
	 */
	private function get_reserved_field_keys() {
		return array(
			'post_id',
			'post_title',
			'_search_title',
			'post_content',
			'post_status',
			'post_category',
			'post_tags',
			'default_category',
			'featured_image',
			'overall_rating',
			'rating_count',
			'submit_ip',
			'street',
			'street2',
			'city',
			'region',
			'country',
			'zip',
			'latitude',
			'longitude',
			'mapview',
			'mapzoom',
			'address',
			'neighbourhood',
			'marker_json',
			'location_id',
			'package_id',
			'expire_date',
			'post_dummy',
			'ratings',
			'special_offers',
		);
	}

	/**
	 * Get the fixed Classified Listing fields.
	 *
	 * @since 2.3.0
	 *
	 * @return array Field definitions.
	 */
	private function get_standard_fields() {
		$post_type = $this->get_import_post_type();

		$fields = array(
			array(
				'type'           => 'text',
				'data_type'      => 'INT',
				'field_key'      => self::SOURCE_ID_FIELD,
				'label'          => __( 'Classified Listing ID', 'geodir-converter' ),
				'description'    => __( 'Original Classified Listing ad ID.', 'geodir-converter' ),
				'placeholder'    => __( 'Classified Listing ID', 'geodir-converter' ),
				'icon'           => 'far fa-id-card',
				'only_for_admin' => 1,
				'required'       => 0,
			),
			array(
				'type'          => 'text',
				'data_type'     => 'FLOAT',
				'decimal_point' => 2,
				'field_key'     => 'price',
				'label'         => __( 'Price', 'geodir-converter' ),
				'description'   => __( 'The price of the ad.', 'geodir-converter' ),
				'placeholder'   => __( 'Price', 'geodir-converter' ),
				'icon'          => 'fas fa-dollar-sign',
				'required'      => 0,
				'show_in'       => '[detail],[listing]',
				'extra'         => $this->get_price_extra(),
			),
			array(
				'type'          => 'text',
				'data_type'     => 'FLOAT',
				'decimal_point' => 2,
				'field_key'     => 'max_price',
				'label'         => __( 'Maximum Price', 'geodir-converter' ),
				'description'   => __( 'The upper price of a price range.', 'geodir-converter' ),
				'placeholder'   => __( 'Maximum Price', 'geodir-converter' ),
				'icon'          => 'fas fa-dollar-sign',
				'required'      => 0,
				'extra'         => $this->get_price_extra(),
			),
			array(
				'type'        => 'select',
				'field_key'   => 'price_type',
				'label'       => __( 'Price Type', 'geodir-converter' ),
				'description' => __( 'Whether the price is fixed, negotiable or on call.', 'geodir-converter' ),
				'icon'        => 'fas fa-tag',
				'required'    => 0,
				'options'     => $this->build_option_values(
					array(
						'fixed'      => __( 'Fixed', 'geodir-converter' ),
						'negotiable' => __( 'Negotiable', 'geodir-converter' ),
						'on_call'    => __( 'On Call', 'geodir-converter' ),
					)
				),
			),
			array(
				'type'        => 'select',
				'field_key'   => 'price_unit',
				'label'       => __( 'Price Unit', 'geodir-converter' ),
				'description' => __( 'The unit the price applies to.', 'geodir-converter' ),
				'icon'        => 'fas fa-ruler',
				'required'    => 0,
				'options'     => $this->build_option_values( $this->get_price_units() ),
			),
			array(
				'type'        => 'select',
				'field_key'   => 'ad_type',
				'label'       => __( 'Ad Type', 'geodir-converter' ),
				'description' => __( 'The type of the ad.', 'geodir-converter' ),
				'icon'        => 'fas fa-bullhorn',
				'required'    => 0,
				'show_in'     => '[detail],[listing]',
				'options'     => $this->build_option_values( $this->get_listing_types() ),
			),
			array(
				'type'        => 'phone',
				'field_key'   => 'phone',
				'label'       => __( 'Phone', 'geodir-converter' ),
				'description' => __( 'The phone number of the ad.', 'geodir-converter' ),
				'placeholder' => __( 'Phone', 'geodir-converter' ),
				'icon'        => 'fas fa-phone',
				'required'    => 0,
			),
			array(
				'type'        => 'phone',
				'field_key'   => 'whatsapp',
				'label'       => __( 'WhatsApp Number', 'geodir-converter' ),
				'description' => __( 'The WhatsApp number of the ad.', 'geodir-converter' ),
				'placeholder' => __( 'WhatsApp Number', 'geodir-converter' ),
				'icon'        => 'fab fa-whatsapp',
				'required'    => 0,
			),
			array(
				'type'        => 'text',
				'field_key'   => 'telegram',
				'label'       => __( 'Telegram ID', 'geodir-converter' ),
				'description' => __( 'The Telegram ID of the ad.', 'geodir-converter' ),
				'placeholder' => __( 'Telegram ID', 'geodir-converter' ),
				'icon'        => 'fab fa-telegram',
				'required'    => 0,
			),
			array(
				'type'        => 'email',
				'field_key'   => 'email',
				'label'       => __( 'Email', 'geodir-converter' ),
				'description' => __( 'The email address of the ad.', 'geodir-converter' ),
				'placeholder' => __( 'Email', 'geodir-converter' ),
				'icon'        => 'far fa-envelope',
				'required'    => 0,
			),
			array(
				'type'        => 'url',
				'field_key'   => 'website',
				'label'       => __( 'Website', 'geodir-converter' ),
				'description' => __( 'The website of the ad.', 'geodir-converter' ),
				'placeholder' => __( 'Website', 'geodir-converter' ),
				'icon'        => 'fas fa-globe',
				'required'    => 0,
			),
			// Same definition as GD's predefined "Video" field, which embeds the URL.
			array(
				'type'          => 'textarea',
				'data_type'     => 'TEXT',
				'field_key'     => 'video',
				'label'         => __( 'Video', 'geodir-converter' ),
				'description'   => __( 'Add video url or code here, YouTube, Vimeo etc.', 'geodir-converter' ),
				'icon'          => 'fas fa-video',
				'required'      => 0,
				'show_in'       => '[owntab]',
				'keep_existing' => true,
			),
			array(
				'type'          => 'business_hours',
				'field_key'     => 'business_hours',
				'label'         => __( 'Business Hours', 'geodir-converter' ),
				'description'   => __( 'The opening hours of the ad.', 'geodir-converter' ),
				'icon'          => 'fas fa-clock',
				'required'      => 0,
				'keep_existing' => true,
			),
		);

		// "Mark as sold" (a Classified Listing Pro feature) has no GD equivalent, so keep it as a flag.
		if ( $this->has_sold_ads() ) {
			$fields[] = array(
				'type'        => 'checkbox',
				'field_key'   => 'sold',
				'label'       => __( 'Sold', 'geodir-converter' ),
				'description' => __( 'The item of the ad has been sold.', 'geodir-converter' ),
				'icon'        => 'fas fa-check-circle',
				'required'    => 0,
				'show_in'     => '[detail],[listing]',
			);
		}

		foreach ( $this->get_social_platforms() as $platform_key => $platform ) {
			// Leave predefined GD social fields as they are.
			if ( ! empty( $post_type ) && $this->field_exists( $platform_key, $post_type ) ) {
				continue;
			}

			$fields[] = array(
				'type'        => 'url',
				'field_key'   => $platform_key,
				'label'       => $platform['label'],
				'description' => sprintf(
					/* translators: %s: social media platform name */
					__( 'The %s page of the ad.', 'geodir-converter' ),
					$platform['label']
				),
				'icon'        => $platform['icon'],
				'required'    => 0,
			);
		}

		return $fields;
	}

	/**
	 * Get the social profiles supported by Classified Listing.
	 *
	 * @since 2.3.0
	 *
	 * @return array Platform key => label and icon.
	 */
	private function get_social_platforms() {
		return array(
			'facebook'  => array(
				'label' => __( 'Facebook', 'geodir-converter' ),
				'icon'  => 'fab fa-facebook',
			),
			'twitter'   => array(
				'label' => __( 'Twitter', 'geodir-converter' ),
				'icon'  => 'fab fa-twitter',
			),
			'youtube'   => array(
				'label' => __( 'YouTube', 'geodir-converter' ),
				'icon'  => 'fab fa-youtube',
			),
			'instagram' => array(
				'label' => __( 'Instagram', 'geodir-converter' ),
				'icon'  => 'fab fa-instagram',
			),
			'linkedin'  => array(
				'label' => __( 'LinkedIn', 'geodir-converter' ),
				'icon'  => 'fab fa-linkedin',
			),
			'pinterest' => array(
				'label' => __( 'Pinterest', 'geodir-converter' ),
				'icon'  => 'fab fa-pinterest',
			),
			'reddit'    => array(
				'label' => __( 'Reddit', 'geodir-converter' ),
				'icon'  => 'fab fa-reddit',
			),
			'tiktok'    => array(
				'label' => __( 'TikTok', 'geodir-converter' ),
				'icon'  => 'fab fa-tiktok',
			),
		);
	}

	/**
	 * Get the GD price display settings, using the Classified Listing currency.
	 *
	 * @since 2.3.0
	 *
	 * @return array Price field extra settings.
	 */
	private function get_price_extra() {
		$symbol             = '';
		$placement          = 'left';
		$thousand_separator = 'comma';
		$decimal_separator  = 'period';
		$currency_settings  = get_option( 'rtcl_general_currency_settings', array() );
		$currency_settings  = is_array( $currency_settings ) ? $currency_settings : array();

		if ( class_exists( '\Rtcl\Helpers\Functions' ) && is_callable( array( '\Rtcl\Helpers\Functions', 'get_currency_symbol' ) ) ) {
			$rtcl_symbol = \Rtcl\Helpers\Functions::get_currency_symbol();

			if ( ! empty( $rtcl_symbol ) ) {
				$symbol = html_entity_decode( $rtcl_symbol, ENT_QUOTES, 'UTF-8' );
			}
		}

		// Without the plugin the symbol table is unavailable, so fall back to the currency code.
		if ( '' === $symbol ) {
			$symbol = ! empty( $currency_settings['currency'] ) && 'USD' !== $currency_settings['currency'] ? sanitize_text_field( $currency_settings['currency'] ) : '$';
		}

		if ( ! empty( $currency_settings['currency_position'] ) && 0 === strpos( $currency_settings['currency_position'], 'right' ) ) {
			$placement = 'right';
		}

		if ( isset( $currency_settings['currency_thousands_separator'] ) ) {
			$separators = array(
				','  => 'comma',
				'.'  => 'period',
				' '  => 'space',
				''   => 'none',
				'\\' => 'slash',
			);
			$separator  = stripslashes( (string) $currency_settings['currency_thousands_separator'] );

			if ( isset( $separators[ $separator ] ) ) {
				$thousand_separator = $separators[ $separator ];
			}
		}

		if ( isset( $currency_settings['currency_decimal_separator'] ) && ',' === stripslashes( (string) $currency_settings['currency_decimal_separator'] ) ) {
			$decimal_separator = 'comma';
		}

		return array(
			'is_price'                  => 1,
			'thousand_separator'        => $thousand_separator,
			'decimal_separator'         => $decimal_separator,
			'decimal_display'           => 'if',
			'currency_symbol'           => $symbol,
			'currency_symbol_placement' => $placement,
		);
	}

	/**
	 * Get the Classified Listing ad types.
	 *
	 * @since 2.3.0
	 *
	 * @return array Type key => label.
	 */
	private function get_listing_types() {
		$types = get_option( 'rtcl_listing_types', array() );

		if ( empty( $types ) || ! is_array( $types ) ) {
			$types = array(
				'sell'     => __( 'Sell', 'geodir-converter' ),
				'buy'      => __( 'Buy', 'geodir-converter' ),
				'exchange' => __( 'Exchange', 'geodir-converter' ),
				'job'      => __( 'Job', 'geodir-converter' ),
				'to_let'   => __( 'To-Let', 'geodir-converter' ),
			);
		}

		return $types;
	}

	/**
	 * Get the Classified Listing price units.
	 *
	 * @since 2.3.0
	 *
	 * @return array Unit key => label.
	 */
	private function get_price_units() {
		$units = array(
			'year'  => __( 'Year', 'geodir-converter' ),
			'month' => __( 'Month', 'geodir-converter' ),
			'week'  => __( 'Week', 'geodir-converter' ),
			'day'   => __( 'Day', 'geodir-converter' ),
			'hour'  => __( 'Hour', 'geodir-converter' ),
			'sqft'  => __( 'Square Feet', 'geodir-converter' ),
			'total' => __( 'Total Price', 'geodir-converter' ),
		);

		if ( class_exists( '\Rtcl\Resources\Options' ) && is_callable( array( '\Rtcl\Resources\Options', 'get_price_unit_list' ) ) ) {
			$rtcl_units = \Rtcl\Resources\Options::get_price_unit_list();

			if ( is_array( $rtcl_units ) ) {
				foreach ( $rtcl_units as $unit_key => $unit ) {
					if ( ! isset( $units[ $unit_key ] ) ) {
						$units[ $unit_key ] = is_array( $unit ) && ! empty( $unit['title'] ) ? $unit['title'] : $unit_key;
					}
				}
			}
		}

		return $units;
	}

	/**
	 * Build a GD option_values string from value => label pairs.
	 *
	 * @since 2.3.0
	 *
	 * @param array $options Value => label pairs.
	 * @return string Newline separated "value : label" lines.
	 */
	private function build_option_values( array $options ) {
		$lines = array();

		foreach ( $options as $value => $label ) {
			$value = trim( wp_strip_all_tags( (string) $value ) );
			$label = trim( wp_strip_all_tags( (string) $label ) );

			if ( '' === $value ) {
				continue;
			}

			if ( '' === $label || $label === $value ) {
				$lines[] = $value;
			} else {
				$lines[] = $value . ' : ' . $label;
			}
		}

		return implode( "\n", $lines );
	}

	/**
	 * Discover custom fields from the Form Builder and from legacy field groups.
	 *
	 * Both sources are read when present. A site that switched to the Form
	 * Builder keeps its old values under the legacy keys, so reading both keeps
	 * the older ads intact. Fields that share a label and type are merged into
	 * one GD field.
	 *
	 * @since 2.3.0
	 *
	 * @param array $reserved Field keys already taken.
	 * @return array Field definitions.
	 */
	private function discover_custom_fields( array $reserved ) {
		$fields     = array();
		$taken      = array_fill_keys( $reserved, true );
		$meta_index = array();

		$source_fields = array_merge( $this->get_form_builder_fields(), $this->get_legacy_fields() );

		foreach ( $source_fields as $source ) {
			$field_key = $this->generate_field_key( $source['label'], $source['meta_key'] );

			// The same meta key used by several forms, or a field of the same label and type, becomes one GD field.
			if ( isset( $meta_index[ $source['meta_key'] ] ) ) {
				$merge_key = $meta_index[ $source['meta_key'] ];
			} elseif ( isset( $fields[ $field_key ] ) && $fields[ $field_key ]['type'] === $source['type'] ) {
				$merge_key = $field_key;
			} else {
				$merge_key = '';
			}

			if ( '' !== $merge_key ) {
				if ( ! in_array( $source['source'], $fields[ $merge_key ]['_sources'], true ) ) {
					$fields[ $merge_key ]['_sources'][] = $source['source'];
				}

				if ( ! empty( $source['options'] ) ) {
					$fields[ $merge_key ]['_options'] = $fields[ $merge_key ]['_options'] + $source['options'];
					$fields[ $merge_key ]['options']  = $this->build_option_values( $fields[ $merge_key ]['_options'] );
				}

				$meta_index[ $source['meta_key'] ] = $merge_key;

				continue;
			}

			$base_key = $field_key;
			$suffix   = 2;

			// Also steer clear of GD columns and keys the importer never writes.
			while ( isset( $taken[ $field_key ] ) || isset( $fields[ $field_key ] ) || $this->should_skip_field( $field_key ) ) {
				$field_key = substr( $base_key, 0, 46 ) . '_' . $suffix;
				++$suffix;
			}

			$meta_index[ $source['meta_key'] ] = $field_key;

			$field = array(
				'type'        => $source['type'],
				'field_key'   => $field_key,
				'label'       => $source['label'],
				/* translators: %s: field name */
				'description' => ! empty( $source['description'] ) ? $source['description'] : sprintf( __( 'Imported from Classified Listing field: %s', 'geodir-converter' ), $source['label'] ),
				'placeholder' => ! empty( $source['placeholder'] ) ? $source['placeholder'] : '',
				'icon'        => $this->get_icon_for_field( $field_key ),
				'required'    => ! empty( $source['required'] ) ? 1 : 0,
				'show_in'     => ! empty( $source['listable'] ) ? '[detail],[listing]' : '[detail]',
				'cat_filter'  => ! empty( $source['searchable'] ) ? 1 : 0,
				'_options'    => ! empty( $source['options'] ) ? $source['options'] : array(),
				'_sources'    => array( $source['source'] ),
			);

			if ( ! empty( $source['options'] ) ) {
				$field['options'] = $this->build_option_values( $source['options'] );
			}

			if ( 'multiselect' === $source['type'] && ! empty( $source['checkbox_display'] ) ) {
				$field['extra'] = array( 'multi_display_type' => 'checkbox' );
			}

			// Without explicit file types GD only accepts images for a file field.
			if ( 'file' === $source['type'] ) {
				$field['extra'] = array(
					'gd_file_types' => ! empty( $source['file_types'] ) ? $source['file_types'] : array( '*' ),
					'file_limit'    => isset( $source['file_limit'] ) ? absint( $source['file_limit'] ) : 0,
				);
			}

			$fields[ $field_key ] = $field;
		}

		return array_values( $fields );
	}

	/**
	 * Generate a GD field key from a label, falling back to the source meta key.
	 *
	 * @since 2.3.0
	 *
	 * @param string $label    Field label.
	 * @param string $meta_key Source meta key.
	 * @return string Field key.
	 */
	private function generate_field_key( $label, $meta_key ) {
		$field_key = str_replace( '-', '_', sanitize_title( $label ) );
		$field_key = substr( $field_key, 0, 50 );

		// Labels in non-Latin scripts sanitize to percent-encoded strings.
		if ( '' === $field_key || ! preg_match( '/^[a-z][a-z0-9_]*$/', $field_key ) ) {
			$field_key = 'rtcl_' . trim( str_replace( '-', '_', sanitize_key( $meta_key ) ), '_' );
			$field_key = substr( $field_key, 0, 50 );
		}

		return $field_key;
	}

	/**
	 * Check whether the Form Builder forms table exists.
	 *
	 * @since 2.3.0
	 *
	 * @return bool True if the table exists.
	 */
	private function forms_table_exists() {
		global $wpdb;

		$table = $wpdb->prefix . self::FORMS_TABLE;

		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
	}

	/**
	 * Read the custom fields defined in the Form Builder forms.
	 *
	 * Preset fields (title, pricing, location, contact details, etc.) are
	 * handled by the fixed mapping; only custom fields are returned. Custom
	 * values are stored in post meta under the field's "name".
	 *
	 * @since 2.3.0
	 *
	 * @return array Normalized source field definitions.
	 */
	private function get_form_builder_fields() {
		global $wpdb;

		if ( ! $this->forms_table_exists() ) {
			return array();
		}

		$table = $wpdb->prefix . self::FORMS_TABLE;
		$forms = $wpdb->get_results( "SELECT id, fields FROM {$table} ORDER BY `default` DESC, id ASC" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( empty( $forms ) ) {
			return array();
		}

		$result = array();

		foreach ( $forms as $form ) {
			$form_fields = json_decode( (string) $form->fields, true );

			if ( empty( $form_fields ) || ! is_array( $form_fields ) ) {
				continue;
			}

			foreach ( $form_fields as $field ) {
				if ( ! is_array( $field ) || ! empty( $field['preset'] ) || empty( $field['name'] ) || empty( $field['element'] ) ) {
					continue;
				}

				$normalized = $this->normalize_form_builder_field( $field );

				if ( $normalized ) {
					$result[] = $normalized;
				}
			}
		}

		return $result;
	}

	/**
	 * Normalize a Form Builder field definition.
	 *
	 * @since 2.3.0
	 *
	 * @param array $field Form Builder field.
	 * @return array|false Normalized field, or false if unsupported.
	 */
	private function normalize_form_builder_field( array $field ) {
		$element  = $field['element'];
		$meta_key = $field['name'];
		$label    = ! empty( $field['label'] ) ? wp_strip_all_tags( $field['label'] ) : $meta_key;
		$multiple = ! empty( $field['multiple'] );
		$mode     = '';
		$type     = false;

		switch ( $element ) {
			case 'text':
			case 'color_picker':
			case 'number':
				$type = 'text';
				break;
			case 'textarea':
				$type = 'textarea';
				break;
			case 'url':
				$type = 'url';
				break;
			case 'select':
				$type = $multiple ? 'multiselect' : 'select';
				break;
			case 'radio':
				$type = 'radio';
				break;
			case 'checkbox':
				$type     = 'multiselect';
				$multiple = true;
				break;
			case 'switch':
				$type = 'checkbox';
				break;
			case 'file':
				$type = 'file';
				break;
			case 'date':
				$is_range  = ! empty( $field['date_type'] ) && 'range' === $field['date_type'];
				$format    = ! empty( $field['date_format'] ) ? $field['date_format'] : '';
				$with_time = false !== strpos( $format, 'H:i' ) || false !== strpos( $format, 'h:i' );

				if ( $is_range ) {
					$mode = 'range';
					$type = 'text';
				} elseif ( $with_time ) {
					$mode = 'datetime';
					$type = 'text';
				} else {
					$mode = 'date';
					$type = 'datepicker';
				}
				break;
		}

		if ( ! $type ) {
			if ( ! in_array( $element, array( 'custom_html', 'input_hidden', 'recaptcha', 'section' ), true ) ) {
				$this->unsupported_fields[] = sprintf( '%s (%s)', $label, $element );
			}

			return false;
		}

		$options = array();

		if ( ! empty( $field['options'] ) && is_array( $field['options'] ) ) {
			foreach ( $field['options'] as $option ) {
				if ( is_array( $option ) && isset( $option['value'] ) && '' !== (string) $option['value'] ) {
					$options[ (string) $option['value'] ] = isset( $option['label'] ) ? (string) $option['label'] : (string) $option['value'];
				}
			}
		}

		$file_types = array();
		$file_limit = 0;

		if ( 'file' === $element ) {
			$allowed = isset( $field['validation']['allowed_file_types']['value'] ) ? (array) $field['validation']['allowed_file_types']['value'] : array();

			foreach ( $allowed as $group ) {
				if ( is_string( $group ) ) {
					$file_types = array_merge( $file_types, array_filter( array_map( 'trim', explode( '|', strtolower( $group ) ) ) ) );
				}
			}

			$file_types = array_values( array_unique( $file_types ) );
			$file_limit = isset( $field['validation']['max_file_count']['value'] ) ? absint( $field['validation']['max_file_count']['value'] ) : 0;
		}

		return array(
			'type'             => $type,
			'label'            => $label,
			'meta_key'         => $meta_key,
			'description'      => ! empty( $field['help_message'] ) ? wp_strip_all_tags( $field['help_message'] ) : '',
			'placeholder'      => ! empty( $field['placeholder'] ) && is_string( $field['placeholder'] ) ? $field['placeholder'] : '',
			'required'         => ! empty( $field['validation']['required']['value'] ),
			'listable'         => ! empty( $field['archive_view'] ),
			'searchable'       => ! empty( $field['filterable'] ),
			'file_types'       => $file_types,
			'file_limit'       => $file_limit,
			'options'          => in_array( $type, array( 'select', 'multiselect', 'radio' ), true ) ? $options : array(),
			'checkbox_display' => 'checkbox' === $element,
			'source'           => array(
				'meta_key' => $meta_key,
				'element'  => $element,
				'multiple' => $multiple,
				'mode'     => $mode,
			),
		);
	}

	/**
	 * Read the custom fields defined in legacy field groups.
	 *
	 * Legacy fields are rtcl_cf posts (children of rtcl_cfg groups); a value is
	 * stored in post meta under "_field_{field ID}".
	 *
	 * @since 2.3.0
	 *
	 * @return array Normalized source field definitions.
	 */
	private function get_legacy_fields() {
		global $wpdb;

		$posts = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_title FROM {$wpdb->posts}
				WHERE post_type = %s AND post_status NOT IN ( 'trash', 'auto-draft' )
				ORDER BY post_parent ASC, menu_order ASC, ID ASC",
				self::POST_TYPE_CUSTOM_FIELD
			)
		);

		if ( empty( $posts ) ) {
			return array();
		}

		$result = array();

		foreach ( $posts as $post ) {
			$field_type = get_post_meta( $post->ID, '_type', true );
			$label      = get_post_meta( $post->ID, '_label', true );
			$label      = ! empty( $label ) ? wp_strip_all_tags( $label ) : $post->post_title;
			$meta_key   = '_field_' . $post->ID;
			$mode       = '';
			$multiple   = false;
			$type       = false;

			if ( empty( $label ) ) {
				$label = $meta_key;
			}

			switch ( $field_type ) {
				case 'text':
				case 'number':
					$type = 'text';
					break;
				case 'textarea':
					$type = 'textarea';
					break;
				case 'url':
					$type = 'url';
					break;
				case 'select':
					$type = 'select';
					break;
				case 'radio':
					$type = 'radio';
					break;
				case 'checkbox':
					$type     = 'multiselect';
					$multiple = true;
					break;
				case 'date':
					$date_type = get_post_meta( $post->ID, '_date_type', true );

					if ( in_array( $date_type, array( 'date_range', 'date_time_range' ), true ) ) {
						$mode = 'range';
						$type = 'text';
					} elseif ( 'date_time' === $date_type ) {
						$mode = 'datetime';
						$type = 'text';
					} else {
						$mode = 'date';
						$type = 'datepicker';
					}
					break;
			}

			if ( ! $type ) {
				$this->unsupported_fields[] = sprintf( '%s (%s)', $label, $field_type );
				continue;
			}

			$options     = array();
			$raw_options = get_post_meta( $post->ID, '_options', true );

			if ( is_array( $raw_options ) && ! empty( $raw_options['choices'] ) && is_array( $raw_options['choices'] ) ) {
				foreach ( $raw_options['choices'] as $value => $title ) {
					$options[ (string) $value ] = (string) $title;
				}
			}

			$result[] = array(
				'type'             => $type,
				'label'            => $label,
				'meta_key'         => $meta_key,
				'description'      => wp_strip_all_tags( (string) get_post_meta( $post->ID, '_description', true ) ),
				'placeholder'      => (string) get_post_meta( $post->ID, '_placeholder', true ),
				'required'         => (bool) get_post_meta( $post->ID, '_required', true ),
				'listable'         => (bool) get_post_meta( $post->ID, '_listable', true ),
				'searchable'       => (bool) get_post_meta( $post->ID, '_searchable', true ),
				'options'          => in_array( $type, array( 'select', 'multiselect', 'radio' ), true ) ? $options : array(),
				'checkbox_display' => 'checkbox' === $field_type,
				'source'           => array(
					'meta_key' => $meta_key,
					'element'  => $field_type,
					'multiple' => $multiple,
					'mode'     => $mode,
				),
			);
		}

		return $result;
	}

	/**
	 * Map a GD field type to its database data type.
	 *
	 * @since 2.3.0
	 *
	 * @param string $field_type Field type.
	 * @return string Data type.
	 */
	private function map_data_type( $field_type ) {
		$type_map = array(
			'text'           => 'VARCHAR',
			'email'          => 'VARCHAR',
			'url'            => 'TEXT',
			'phone'          => 'VARCHAR',
			'textarea'       => 'TEXT',
			'checkbox'       => 'TINYINT',
			'datepicker'     => 'DATE',
			'radio'          => 'VARCHAR',
			'select'         => 'VARCHAR',
			'multiselect'    => 'VARCHAR',
			'file'           => 'TEXT',
			'business_hours' => 'TEXT',
		);

		return isset( $type_map[ $field_type ] ) ? $type_map[ $field_type ] : 'VARCHAR';
	}

	/**
	 * Get an icon for a field based on its key.
	 *
	 * @since 2.3.0
	 *
	 * @param string $field_key Field key.
	 * @return string Icon class.
	 */
	private function get_icon_for_field( $field_key ) {
		$icon_map = array(
			'phone'     => 'fas fa-phone',
			'email'     => 'fas fa-envelope',
			'website'   => 'fas fa-globe',
			'address'   => 'fas fa-map-marker-alt',
			'price'     => 'fas fa-dollar-sign',
			'condition' => 'fas fa-check-circle',
			'brand'     => 'fas fa-tag',
			'model'     => 'fas fa-tag',
			'color'     => 'fas fa-palette',
			'year'      => 'fas fa-calendar',
			'date'      => 'fas fa-calendar',
			'time'      => 'far fa-clock',
			'size'      => 'fas fa-ruler',
			'feature'   => 'fas fa-list',
		);

		foreach ( $icon_map as $keyword => $icon ) {
			if ( false !== strpos( $field_key, $keyword ) ) {
				return $icon;
			}
		}

		return 'fas fa-info-circle';
	}

	/**
	 * Import custom fields from Classified Listing to GeoDirectory.
	 *
	 * @since 2.3.0
	 *
	 * @param array $task Task details.
	 * @return array|false Result of the import operation.
	 */
	public function task_import_fields( array $task ) {
		$this->log( __( 'Importing custom fields...', 'geodir-converter' ) );

		$post_type   = $this->get_import_post_type();
		$fields      = $this->get_custom_fields();
		$package_ids = $this->get_package_ids( $post_type );

		foreach ( array_unique( $this->unsupported_fields ) as $unsupported ) {
			$this->log(
				sprintf(
					/* translators: %s: field label and type */
					__( 'Skipped unsupported custom field: %s', 'geodir-converter' ),
					$unsupported
				),
				'warning'
			);
		}

		if ( empty( $fields ) ) {
			$this->log( __( 'No custom fields to import.', 'geodir-converter' ), 'warning' );
			return $this->next_task( $task );
		}

		$imported = 0;
		$updated  = 0;
		$skipped  = 0;
		$failed   = 0;

		foreach ( $fields as $field ) {
			$gd_field = $this->prepare_single_field( $field, $post_type, $package_ids );

			if ( $this->should_skip_field( $gd_field['htmlvar_name'] ) ) {
				++$skipped;
				continue;
			}

			$field_exists = ! empty( $gd_field['field_id'] );

			// Do not overwrite GD's own definition of shared fields.
			if ( $field_exists && ! empty( $field['keep_existing'] ) ) {
				++$skipped;
				continue;
			}

			if ( $this->is_test_mode() ) {
				$field_exists ? ++$updated : ++$imported;
				continue;
			}

			$result = geodir_custom_field_save( $gd_field );

			if ( $result && ! is_wp_error( $result ) ) {
				if ( $field_exists ) {
					++$updated;
				} else {
					++$imported;
				}
			} else {
				++$failed;
				$error_msg = is_wp_error( $result ) ? $result->get_error_message() : __( 'Unknown error', 'geodir-converter' );
				/* translators: %1$s: field name, %2$s: error message */
				$this->log( sprintf( __( 'Failed to import field: %1$s - %2$s', 'geodir-converter' ), $field['label'], $error_msg ), 'error' );
			}
		}

		$this->increase_succeed_imports( $imported + $updated );
		$this->increase_skipped_imports( $skipped );
		$this->increase_failed_imports( $failed );

		$this->log(
			sprintf(
				/* translators: %1$d: imported count, %2$d: updated count, %3$d: skipped count, %4$d: failed count */
				__( 'Fields import completed: %1$d imported, %2$d updated, %3$d skipped, %4$d failed.', 'geodir-converter' ),
				$imported,
				$updated,
				$skipped,
				$failed
			),
			'success'
		);

		return $this->next_task( $task );
	}

	/**
	 * Prepare a single field for GeoDirectory.
	 *
	 * @since 2.3.0
	 *
	 * @param array  $field       Field data.
	 * @param string $post_type   Post type.
	 * @param array  $package_ids Package IDs.
	 * @return array GeoDirectory field data.
	 */
	private function prepare_single_field( $field, $post_type, $package_ids = array() ) {
		$field_type = isset( $field['type'] ) ? $field['type'] : 'text';
		$field_id   = $this->field_exists( $field['field_key'], $post_type );

		$gd_field = array(
			'post_type'         => $post_type,
			'data_type'         => isset( $field['data_type'] ) ? $field['data_type'] : $this->map_data_type( $field_type ),
			'field_type'        => $field_type,
			'htmlvar_name'      => $field['field_key'],
			'admin_title'       => $field['label'],
			'frontend_title'    => $field['label'],
			'frontend_desc'     => isset( $field['description'] ) ? $field['description'] : '',
			'placeholder_value' => isset( $field['placeholder'] ) ? $field['placeholder'] : '',
			'default_value'     => '',
			'is_active'         => '1',
			'for_admin_use'     => ! empty( $field['only_for_admin'] ) ? 1 : 0,
			'is_required'       => isset( $field['required'] ) && 1 === $field['required'] ? 1 : 0,
			'show_in'           => isset( $field['show_in'] ) ? $field['show_in'] : '[detail]',
			'show_on_pkg'       => $package_ids,
			'clabels'           => $field['label'],
			'option_values'     => isset( $field['options'] ) ? $field['options'] : '',
			'field_icon'        => isset( $field['icon'] ) ? $field['icon'] : 'fas fa-info-circle',
		);

		if ( isset( $field['decimal_point'] ) ) {
			$gd_field['decimal_point'] = absint( $field['decimal_point'] );
		}

		if ( ! empty( $field['cat_filter'] ) ) {
			$gd_field['cat_filter'] = 1;
		}

		if ( ! empty( $field['extra'] ) ) {
			$gd_field['extra'] = $field['extra'];
		}

		if ( $field_id ) {
			$gd_field['field_id'] = $field_id;
		}

		return $gd_field;
	}

	/**
	 * Parse and batch listings for background import.
	 *
	 * @since 2.3.0
	 *
	 * @param array $task The task to import.
	 * @return array|false Result of the import operation.
	 */
	public function task_parse_listings( array $task ) {
		global $wpdb;

		$offset         = isset( $task['offset'] ) ? (int) $task['offset'] : 0;
		$total_listings = isset( $task['total_listings'] ) ? (int) $task['total_listings'] : 0;
		$batch_size     = (int) $this->get_batch_size();

		if ( ! isset( $task['total_listings'] ) ) {
			$total_listings         = $this->count_listings();
			$task['total_listings'] = $total_listings;
		}

		if ( 0 === $offset ) {
			$this->log( __( 'Starting listings parsing process...', 'geodir-converter' ) );
		}

		if ( 0 === $total_listings ) {
			$this->log( __( 'No listings found for parsing. Skipping process.', 'geodir-converter' ) );
			return $this->next_task( $task, true );
		}

		$status_placeholders = implode( ',', array_fill( 0, count( $this->post_statuses ), '%s' ) );

		$listings = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_title, post_status
				FROM {$wpdb->posts}
				WHERE post_type = %s
				AND post_status IN ({$status_placeholders})
				ORDER BY ID ASC
				LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				array_merge(
					array( self::POST_TYPE_LISTING ),
					$this->post_statuses,
					array( $batch_size, $offset )
				)
			)
		);

		if ( empty( $listings ) ) {
			$this->log( __( 'Import process completed. No more listings found.', 'geodir-converter' ) );
			return $this->next_task( $task, true );
		}

		$import_tasks = array();
		foreach ( array_chunk( $listings, $this->batch_size ) as $batch ) {
			$import_tasks[] = array(
				'action'   => self::ACTION_IMPORT_LISTINGS,
				'listings' => $batch,
			);
		}

		$this->background_process->add_import_tasks( $import_tasks );

		if ( $offset + $batch_size < $total_listings ) {
			$task['offset'] = $offset + $batch_size;
			return $task;
		}

		return $this->next_task( $task, true );
	}

	/**
	 * Import a batch of listings (called by background process).
	 *
	 * Also handles a retried failed item, which carries only its source ID.
	 *
	 * @since 2.3.0
	 *
	 * @param array $task The task to import.
	 * @return bool Result of the import operation.
	 */
	public function task_import_listings( $task ) {
		$listings = isset( $task['listings'] ) && ! empty( $task['listings'] ) ? (array) $task['listings'] : array();

		if ( empty( $listings ) && ! empty( $task['source_id'] ) ) {
			$listings[] = (object) array(
				'ID'         => absint( $task['source_id'] ),
				'post_title' => isset( $task['title'] ) ? (string) $task['title'] : '',
			);
		}

		return $this->import_queued_items(
			$listings,
			function ( $listing ) {
				return $this->import_single_listing( $listing );
			}
		);
	}

	/**
	 * Convert a single Classified Listing ad to GeoDirectory format.
	 *
	 * @since 2.3.0
	 *
	 * @param object $listing The listing row to convert.
	 * @return int Import status.
	 */
	private function import_single_listing( $listing ) {
		$post = get_post( $listing->ID );

		if ( ! $post || self::POST_TYPE_LISTING !== $post->post_type ) {
			return self::IMPORT_STATUS_FAILED;
		}

		$post_type  = $this->get_import_post_type();
		$gd_post_id = ! $this->is_test_mode() ? $this->get_gd_listing_id( $post->ID, self::SOURCE_ID_FIELD, $post_type ) : false;
		$is_update  = ! empty( $gd_post_id );
		$post_meta  = $this->get_post_meta( $post->ID );

		$categories = $this->get_listings_terms( $post->ID, self::TAX_CATEGORY, 'ids' );
		$tags       = $this->get_listings_terms( $post->ID, self::TAX_TAG, 'names' );
		$location   = $this->get_listing_location( $post->ID, $post_meta );

		$social_profiles = isset( $post_meta[ self::META_SOCIAL ] ) && is_array( $post_meta[ self::META_SOCIAL ] ) ? $post_meta[ self::META_SOCIAL ] : array();
		$video_urls      = isset( $post_meta[ self::META_VIDEO_URLS ] ) ? (array) $post_meta[ self::META_VIDEO_URLS ] : array();
		$video_urls      = array_values( array_filter( array_map( 'trim', array_filter( $video_urls, 'is_string' ) ) ) );
		$pricing_type    = isset( $post_meta[ self::META_PRICING_TYPE ] ) ? $post_meta[ self::META_PRICING_TYPE ] : 'price';

		$listing_data = array(
			// Standard WP fields.
			'post_author'           => $post->post_author ? $post->post_author : $this->get_import_setting( 'wp_author_id', get_current_user_id() ),
			'post_title'            => $post->post_title,
			'post_content'          => $post->post_content ? $post->post_content : '',
			'post_content_filtered' => $post->post_content,
			'post_excerpt'          => $post->post_excerpt ? $post->post_excerpt : '',
			'post_status'           => $this->map_post_status( $post->post_status ),
			'post_type'             => $post_type,
			'comment_status'        => $this->get_comment_status(),
			'ping_status'           => $post->ping_status,
			// Drafts and pending ads have no slug yet; WordPress makes one from the title on publish.
			'post_name'             => $post->post_name,
			'post_date_gmt'         => $post->post_date_gmt,
			'post_date'             => $post->post_date,
			'post_modified_gmt'     => $post->post_modified_gmt,
			'post_modified'         => $post->post_modified,
			'tax_input'             => array(
				"{$post_type}category" => $categories,
				"{$post_type}_tags"    => $tags,
			),

			// GD fields.
			'default_category'      => ! empty( $categories ) ? $categories[0] : 0,

			// Location.
			'street'                => $location['street'],
			'city'                  => $location['city'],
			'region'                => $location['region'],
			'country'               => $location['country'],
			'zip'                   => $location['zip'],
			'latitude'              => $location['latitude'],
			'longitude'             => $location['longitude'],

			// Classified Listing fields.
			self::SOURCE_ID_FIELD   => $post->ID,
			'price_type'            => $this->get_meta_string( $post_meta, self::META_PRICE_TYPE ),
			'price_unit'            => $this->get_meta_string( $post_meta, self::META_PRICE_UNIT ),
			'ad_type'               => $this->get_meta_string( $post_meta, self::META_AD_TYPE ),
			'phone'                 => $this->get_meta_string( $post_meta, self::META_PHONE ),
			'whatsapp'              => $this->get_meta_string( $post_meta, self::META_WHATSAPP ),
			'telegram'              => $this->get_meta_string( $post_meta, self::META_TELEGRAM ),
			'email'                 => $this->get_meta_string( $post_meta, self::META_EMAIL ),
			'website'               => $this->get_meta_string( $post_meta, self::META_WEBSITE ),
			'video'                 => ! empty( $video_urls ) ? esc_url_raw( $video_urls[0] ) : '',
			'featured'              => ! empty( $post_meta[ self::META_FEATURED ] ) ? 1 : 0,
		);

		if ( $this->has_sold_ads() ) {
			$listing_data['sold'] = ! empty( $post_meta[ self::META_SOLD ] ) ? 1 : 0;
		}

		// An empty string in a DECIMAL column is stored as 0.00, so only pass real prices.
		$price     = 'disabled' !== $pricing_type ? $this->get_meta_string( $post_meta, self::META_PRICE ) : '';
		$max_price = 'range' === $pricing_type ? $this->get_meta_string( $post_meta, self::META_MAX_PRICE ) : '';

		if ( is_numeric( $price ) ) {
			$listing_data['price'] = $price;
		}

		if ( is_numeric( $max_price ) ) {
			$listing_data['max_price'] = $max_price;
		}

		// Social profiles.
		foreach ( array_keys( $this->get_social_platforms() ) as $platform ) {
			if ( ! empty( $social_profiles[ $platform ] ) && is_string( $social_profiles[ $platform ] ) ) {
				$listing_data[ $platform ] = esc_url_raw( $social_profiles[ $platform ] );
			}
		}

		// Business hours.
		$business_hours = $this->format_business_hours( isset( $post_meta[ self::META_BUSINESS_HOURS ] ) ? $post_meta[ self::META_BUSINESS_HOURS ] : array() );
		if ( ! empty( $business_hours ) ) {
			$listing_data['business_hours'] = $business_hours;
		}

		// Expiry dates are handled by the Pricing Manager, which owns the expire_date field.
		if ( class_exists( 'GeoDir_Pricing_Package' ) && empty( $post_meta[ self::META_NEVER_EXPIRES ] ) && ! empty( $post_meta[ self::META_EXPIRY_DATE ] ) ) {
			$expiry = strtotime( $post_meta[ self::META_EXPIRY_DATE ] );

			if ( $expiry ) {
				$listing_data['expire_date'] = gmdate( 'Y-m-d', $expiry );
			}
		}

		// Custom fields; values already mapped above take precedence.
		foreach ( $this->get_custom_field_values( $post->ID, $post_meta ) as $key => $value ) {
			if ( isset( $listing_data[ $key ] ) && '' !== $listing_data[ $key ] ) {
				continue;
			}

			$listing_data[ $key ] = $value;
		}

		if ( $this->is_test_mode() ) {
			return self::IMPORT_STATUS_SUCCESS;
		}

		// Delete existing media if updating; GD adds rather than replaces files given by URL.
		if ( $is_update ) {
			GeoDir_Media::delete_files( (int) $gd_post_id, 'post_images' );

			foreach ( $this->get_custom_fields() as $field ) {
				if ( 'file' === $field['type'] ) {
					GeoDir_Media::delete_files( (int) $gd_post_id, $field['field_key'] );
				}
			}
		}

		$listing_data['post_images'] = $this->get_post_images( $post->ID, $post_meta );

		if ( $is_update ) {
			$gd_post_id = wp_update_post( array_merge( array( 'ID' => $gd_post_id ), $listing_data ), true );
		} else {
			$gd_post_id = wp_insert_post( $listing_data, true );
		}

		if ( is_wp_error( $gd_post_id ) ) {
			$this->log( $gd_post_id->get_error_message(), 'error' );
			return self::IMPORT_STATUS_FAILED;
		}

		$this->import_reviews( $post->ID, (int) $gd_post_id );
		$this->import_favourites( $post->ID, (int) $gd_post_id );

		return $is_update ? self::IMPORT_STATUS_UPDATED : self::IMPORT_STATUS_SUCCESS;
	}

	/**
	 * Copy the users' Classified Listing favourites of an ad to the GD listing.
	 *
	 * Favourites are kept per user as a serialized array of listing IDs.
	 *
	 * @since 2.3.0
	 *
	 * @param int $source_id  Source listing ID.
	 * @param int $gd_post_id GeoDirectory post ID.
	 * @return void
	 */
	private function import_favourites( $source_id, $gd_post_id ) {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value LIKE %s",
				self::META_FAVOURITES,
				'%' . $wpdb->esc_like( 'i:' . (int) $source_id . ';' ) . '%'
			)
		);

		if ( empty( $rows ) || ! class_exists( 'GeoDir_User' ) ) {
			return;
		}

		foreach ( $rows as $row ) {
			$source_favs = array_map( 'absint', (array) maybe_unserialize( $row->meta_value ) );

			// The LIKE also matches array keys, so confirm the ID is a value.
			if ( ! in_array( (int) $source_id, $source_favs, true ) ) {
				continue;
			}

			// GD's own API; it skips a post that is already a favourite.
			GeoDir_User::add_fav( $gd_post_id, (int) $row->user_id );
		}
	}

	/**
	 * Check whether any ad is marked as sold.
	 *
	 * @since 2.3.0
	 *
	 * @return bool True if at least one ad is marked as sold.
	 */
	private function has_sold_ads() {
		global $wpdb;

		if ( null === $this->has_sold_ads ) {
			$this->has_sold_ads = (bool) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT pm.meta_id FROM {$wpdb->postmeta} pm
					INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
					WHERE p.post_type = %s AND pm.meta_key = %s AND pm.meta_value NOT IN ( '', '0' )
					LIMIT 1",
					self::POST_TYPE_LISTING,
					self::META_SOLD
				)
			);
		}

		return $this->has_sold_ads;
	}

	/**
	 * Get the comment status for the imported listings.
	 *
	 * Classified Listing ignores the ads' own comment status: its "Enable
	 * Comment Form" setting opens or closes reviews on every ad
	 * (Rtcl\Controllers\Hooks\Comments::comments_open()).
	 *
	 * @since 2.3.0
	 *
	 * @return string 'open' or 'closed'.
	 */
	private function get_comment_status() {
		$settings = get_option( 'rtcl_single_listing_settings', array() );

		return is_array( $settings ) && isset( $settings['has_comment_form'] ) && 'yes' === $settings['has_comment_form'] ? 'open' : 'closed';
	}

	/**
	 * Map a Classified Listing post status to a GeoDirectory post status.
	 *
	 * @since 2.3.0
	 *
	 * @param string $status Source post status.
	 * @return string GeoDirectory post status.
	 */
	private function map_post_status( $status ) {
		switch ( $status ) {
			case 'rtcl-reviewed':
			case 'rtcl-pending':
				return 'pending';
			case 'rtcl-expired':
				return class_exists( 'GeoDir_Pricing_Package' ) ? 'gd-expired' : 'draft';
			case 'publish':
			case 'future':
			case 'pending':
			case 'draft':
			case 'private':
				return $status;
		}

		return 'draft';
	}

	/**
	 * Read a scalar meta value as a trimmed string.
	 *
	 * @since 2.3.0
	 *
	 * @param array  $post_meta Post meta.
	 * @param string $key       Meta key.
	 * @return string The value, or an empty string.
	 */
	private function get_meta_string( array $post_meta, $key ) {
		if ( ! isset( $post_meta[ $key ] ) || ! is_scalar( $post_meta[ $key ] ) ) {
			return '';
		}

		return trim( (string) $post_meta[ $key ] );
	}

	/**
	 * Resolve the GD address of a listing.
	 *
	 * Uses the rtcl_location terms (state > city > town by default, sometimes
	 * with a country on top) when assigned. Whatever they leave out, as on
	 * sites set to the "geo" location type or when the terms only name a
	 * country, is reverse geocoded from the coordinates. The ad's own
	 * coordinates and address are always kept.
	 *
	 * @since 2.3.0
	 *
	 * @param int   $post_id   Source listing ID.
	 * @param array $post_meta Source listing meta.
	 * @return array Location data.
	 */
	private function get_listing_location( $post_id, array $post_meta ) {
		$default   = $this->get_default_location();
		$latitude  = $this->get_meta_string( $post_meta, self::META_LATITUDE );
		$longitude = $this->get_meta_string( $post_meta, self::META_LONGITUDE );
		$has_coord = is_numeric( $latitude ) && is_numeric( $longitude ) && ( 0.0 !== (float) $latitude || 0.0 !== (float) $longitude );
		$street    = $this->get_meta_string( $post_meta, self::META_ADDRESS );

		if ( '' === $street ) {
			$street = $this->get_meta_string( $post_meta, self::META_GEO_ADDRESS );
		}

		$location = array(
			'street'    => $street,
			'city'      => '',
			'region'    => '',
			'country'   => '',
			'zip'       => $this->get_meta_string( $post_meta, self::META_ZIPCODE ),
			'latitude'  => $has_coord ? $latitude : $default['latitude'],
			'longitude' => $has_coord ? $longitude : $default['longitude'],
		);

		$chain = $this->get_location_chain( $post_id );

		if ( ! empty( $chain ) ) {
			$country = $this->match_country( $chain[0] );

			if ( $country ) {
				$location['country'] = $country;
				array_shift( $chain );
			}

			if ( ! empty( $chain ) ) {
				$location['region'] = $chain[0];
				$location['city']   = isset( $chain[1] ) ? $chain[1] : $chain[0];
			}
		}

		// Fill what the terms leave out from the coordinates. Test mode writes nothing, including the geocoder's cache, so no lookup is made.
		if ( $has_coord && ! $this->is_test_mode() && ( '' === $location['city'] || '' === $location['country'] ) ) {
			$geocoded = $this->geocode_location( $latitude, $longitude, array(), $post_id );

			foreach ( array( 'city', 'region', 'country', 'zip' ) as $key ) {
				if ( '' === $location[ $key ] && ! empty( $geocoded[ $key ] ) ) {
					$location[ $key ] = $geocoded[ $key ];
				}
			}

			if ( '' === $location['street'] && ! empty( $geocoded['address'] ) ) {
				$location['street'] = $geocoded['address'];
			}
		}

		if ( ! $has_coord && ! $this->is_test_mode() ) {
			$this->log(
				sprintf(
					/* translators: %d: source listing ID */
					__( 'Listing #%d has no coordinates; the default location coordinates were used.', 'geodir-converter' ),
					$post_id
				),
				'warning'
			);
		}

		if ( '' === $location['country'] ) {
			$location['country'] = $default['country'];
		}

		// The default city and region only make sense inside the default country.
		if ( '' === $location['city'] && '' === $location['region'] && $location['country'] === $default['country'] ) {
			$location['city']   = $default['city'];
			$location['region'] = $default['region'];
		}

		return $location;
	}

	/**
	 * Get the names of a listing's location terms, from the top level down.
	 *
	 * @since 2.3.0
	 *
	 * @param int $post_id Source listing ID.
	 * @return array Term names ordered from root to leaf.
	 */
	private function get_location_chain( $post_id ) {
		global $wpdb;

		$term_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT tt.term_id
				FROM {$wpdb->term_taxonomy} tt
				INNER JOIN {$wpdb->term_relationships} tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
				WHERE tr.object_id = %d AND tt.taxonomy = %s",
				$post_id,
				self::TAX_LOCATION
			)
		);

		if ( empty( $term_ids ) ) {
			return array();
		}

		$terms   = $this->get_location_terms();
		$deepest = array();

		// A listing is assigned every level; follow the deepest one to the root.
		foreach ( $term_ids as $term_id ) {
			$chain   = array();
			$current = (int) $term_id;
			$guard   = 0;

			while ( $current && isset( $terms[ $current ] ) && $guard < 10 ) {
				array_unshift( $chain, $terms[ $current ]['name'] );
				$current = $terms[ $current ]['parent'];
				++$guard;
			}

			if ( count( $chain ) > count( $deepest ) ) {
				$deepest = $chain;
			}
		}

		return $deepest;
	}

	/**
	 * Load all location terms once.
	 *
	 * @since 2.3.0
	 *
	 * @return array Term ID => name and parent.
	 */
	private function get_location_terms() {
		global $wpdb;

		if ( null !== $this->location_terms_cache ) {
			return $this->location_terms_cache;
		}

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT t.term_id, t.name, tt.parent
				FROM {$wpdb->terms} t
				INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
				WHERE tt.taxonomy = %s",
				self::TAX_LOCATION
			)
		);

		$this->location_terms_cache = array();

		foreach ( (array) $rows as $row ) {
			$this->location_terms_cache[ (int) $row->term_id ] = array(
				'name'   => html_entity_decode( $row->name, ENT_QUOTES, 'UTF-8' ),
				'parent' => (int) $row->parent,
			);
		}

		return $this->location_terms_cache;
	}

	/**
	 * Match a location name against the GeoDirectory country list.
	 *
	 * @since 2.3.0
	 *
	 * @param string $name Location name.
	 * @return string The GD country name, or an empty string if not a country.
	 */
	private function match_country( $name ) {
		if ( null === $this->countries_cache ) {
			$this->countries_cache = array();

			if ( function_exists( 'geodir_get_countries' ) ) {
				foreach ( (array) geodir_get_countries() as $country => $translated ) {
					$this->countries_cache[ strtolower( $country ) ]    = $country;
					$this->countries_cache[ strtolower( $translated ) ] = $country;
				}
			}
		}

		$key = strtolower( trim( $name ) );

		return isset( $this->countries_cache[ $key ] ) ? $this->countries_cache[ $key ] : '';
	}

	/**
	 * Format Classified Listing business hours for GeoDirectory.
	 *
	 * Handles both formats: the legacy one (an array of week days) and the
	 * Form Builder one (active/type/days).
	 *
	 * @since 2.3.0
	 *
	 * @param mixed $raw Raw _rtcl_bhs meta.
	 * @return string GeoDirectory business hours string, or an empty string.
	 */
	private function format_business_hours( $raw ) {
		if ( empty( $raw ) || ! is_array( $raw ) ) {
			return '';
		}

		// Classified Listing keys days as in $wp_locale->weekday: 0 = Sunday.
		$day_map = array(
			1 => 'Mo',
			2 => 'Tu',
			3 => 'We',
			4 => 'Th',
			5 => 'Fr',
			6 => 'Sa',
			0 => 'Su',
		);

		$days = $raw;

		if ( array_key_exists( 'active', $raw ) || array_key_exists( 'type', $raw ) || array_key_exists( 'days', $raw ) ) {
			if ( empty( $raw['active'] ) ) {
				return '';
			}

			if ( empty( $raw['type'] ) || 'selective' !== $raw['type'] ) {
				// Open all the time.
				$days = array_fill_keys( array_keys( $day_map ), array( 'open' => true ) );
			} else {
				$days = ! empty( $raw['days'] ) && is_array( $raw['days'] ) ? $raw['days'] : array();
			}
		}

		$parts = array();

		foreach ( $day_map as $day_key => $abbr ) {
			if ( empty( $days[ $day_key ] ) || ! is_array( $days[ $day_key ] ) ) {
				continue;
			}

			$day = $days[ $day_key ];

			if ( empty( $day['open'] ) || 'false' === $day['open'] ) {
				continue;
			}

			$hours = array();

			if ( ! empty( $day['times'] ) && is_array( $day['times'] ) ) {
				foreach ( $day['times'] as $period ) {
					if ( empty( $period['start'] ) || empty( $period['end'] ) ) {
						continue;
					}

					$start = $this->convert_to_24h( $period['start'] );
					$end   = $this->convert_to_24h( $period['end'] );

					if ( $start && $end ) {
						$hours[] = $start . '-' . $end;
					}
				}
			}

			// Open with no time slots means open the whole day.
			$parts[] = $abbr . ' ' . ( ! empty( $hours ) ? implode( ',', $hours ) : '00:00-00:00' );
		}

		if ( empty( $parts ) ) {
			return '';
		}

		$result  = '["' . implode( '","', $parts ) . '"]';
		$result .= ',["UTC":"' . get_option( 'gmt_offset', 0 ) . '"]';

		if ( function_exists( 'geodir_sanitize_business_hours' ) ) {
			$result = geodir_sanitize_business_hours( $result );
		}

		return $result;
	}

	/**
	 * Convert a time to 24-hour HH:MM format.
	 *
	 * @since 2.3.0
	 *
	 * @param string $time Time string in 12h or 24h format.
	 * @return string Time in HH:MM format, or an empty string on failure.
	 */
	private function convert_to_24h( $time ) {
		$time = trim( (string) $time );

		if ( preg_match( '/^(\d{1,2}):(\d{2})(:\d{2})?$/', $time, $matches ) ) {
			return sprintf( '%02d:%02d', (int) $matches[1], (int) $matches[2] );
		}

		$timestamp = strtotime( $time );

		return false === $timestamp ? '' : gmdate( 'H:i', $timestamp );
	}

	/**
	 * Get the GD custom field values of a listing.
	 *
	 * @since 2.3.0
	 *
	 * @param int   $post_id   Source listing ID.
	 * @param array $post_meta Source listing meta.
	 * @return array Field key => value.
	 */
	private function get_custom_field_values( $post_id, array $post_meta ) {
		$values = array();

		foreach ( $this->get_custom_fields() as $field ) {
			if ( empty( $field['_sources'] ) ) {
				continue;
			}

			foreach ( $field['_sources'] as $source ) {
				$value = $this->get_source_value( $post_id, $post_meta, $source, $field['type'] );

				if ( '' !== $value && null !== $value ) {
					$values[ $field['field_key'] ] = $value;
					break;
				}
			}
		}

		return $values;
	}

	/**
	 * Read the value of one source custom field.
	 *
	 * @since 2.3.0
	 *
	 * @param int    $post_id   Source listing ID.
	 * @param array  $post_meta Source listing meta.
	 * @param array  $source    Source field details.
	 * @param string $gd_type   Target GD field type.
	 * @return string|int The value, or an empty string when unset.
	 */
	private function get_source_value( $post_id, array $post_meta, array $source, $gd_type ) {
		$meta_key = $source['meta_key'];

		if ( 'range' === $source['mode'] ) {
			$start = $this->get_meta_string( $post_meta, $meta_key . '_start' );
			$end   = $this->get_meta_string( $post_meta, $meta_key . '_end' );

			if ( '' === $start && '' === $end ) {
				return '';
			}

			return trim( $start . ( '' !== $end ? ' - ' . $end : '' ), ' -' );
		}

		// Multiple values are stored one row each, or as a single serialized array.
		if ( ! empty( $source['multiple'] ) ) {
			$items = array();

			foreach ( (array) get_post_meta( $post_id, $meta_key, false ) as $row ) {
				foreach ( (array) maybe_unserialize( $row ) as $item ) {
					if ( is_scalar( $item ) && '' !== trim( (string) $item ) ) {
						$items[] = trim( (string) $item );
					}
				}
			}

			return implode( ',', array_unique( $items ) );
		}

		if ( ! isset( $post_meta[ $meta_key ] ) ) {
			return '';
		}

		$value = $post_meta[ $meta_key ];

		if ( 'file' === $gd_type ) {
			return $this->format_files_data( (array) $value );
		}

		if ( 'checkbox' === $gd_type ) {
			$flag = is_scalar( $value ) ? strtolower( trim( (string) $value ) ) : '';

			return ( '' === $flag || in_array( $flag, array( '0', 'no', 'false', 'off' ), true ) ) ? 0 : 1;
		}

		if ( is_array( $value ) ) {
			$value = implode( ',', array_filter( array_map( 'strval', array_filter( $value, 'is_scalar' ) ) ) );
		}

		$value = trim( (string) $value );

		if ( '' !== $value && 'date' === $source['mode'] ) {
			$timestamp = strtotime( $value );
			$value     = $timestamp ? gmdate( 'Y-m-d', $timestamp ) : '';
		}

		return $value;
	}

	/**
	 * Format uploaded file attachments for a GD file field.
	 *
	 * @since 2.3.0
	 *
	 * @param array $attachment_ids Attachment IDs.
	 * @return string GD file field value.
	 */
	private function format_files_data( array $attachment_ids ) {
		$files = array();

		foreach ( $attachment_ids as $attachment_id ) {
			$attachment_id = absint( $attachment_id );
			$url           = $attachment_id ? wp_get_attachment_url( $attachment_id ) : '';

			if ( ! $url ) {
				continue;
			}

			$files[] = sprintf( '%s||%s|', esc_url( $url ), esc_html( get_the_title( $attachment_id ) ) );
		}

		return implode( '::', $files );
	}

	/**
	 * Get the gallery images of a listing.
	 *
	 * Classified Listing stores images as attachments of the listing. The
	 * featured image comes first, then the saved order, then upload order.
	 *
	 * @since 2.3.0
	 *
	 * @param int   $post_id   Source listing ID.
	 * @param array $post_meta Source listing meta.
	 * @return string Formatted gallery images string for GeoDirectory.
	 */
	private function get_post_images( $post_id, array $post_meta ) {
		global $wpdb;

		$attached = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT p.ID
				FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = '_rtcl_attachment_type'
				WHERE p.post_parent = %d
				AND p.post_type = 'attachment'
				AND p.post_mime_type LIKE %s
				AND ( pm.meta_value IS NULL OR pm.meta_value = 'image' )
				ORDER BY p.menu_order ASC, p.ID ASC",
				$post_id,
				$wpdb->esc_like( 'image/' ) . '%'
			)
		);

		$attached = array_map( 'absint', (array) $attached );
		$ordered  = array();

		$thumbnail_id = absint( get_post_thumbnail_id( $post_id ) );
		if ( $thumbnail_id ) {
			$ordered[] = $thumbnail_id;
		}

		if ( ! empty( $post_meta[ self::META_IMAGES_ORDER ] ) && is_array( $post_meta[ self::META_IMAGES_ORDER ] ) ) {
			foreach ( $post_meta[ self::META_IMAGES_ORDER ] as $attachment_id ) {
				if ( in_array( absint( $attachment_id ), $attached, true ) ) {
					$ordered[] = absint( $attachment_id );
				}
			}
		}

		$ordered = array_values( array_unique( array_merge( $ordered, $attached ) ) );
		$images  = array();

		foreach ( $ordered as $index => $attachment_id ) {
			$images[] = array(
				'id'      => $attachment_id,
				'caption' => get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
				'weight'  => $index + 1,
			);
		}

		return $this->format_images_data( $images );
	}

	/**
	 * Get a listing's GD terms through their GD equivalents.
	 *
	 * @since 2.3.0
	 *
	 * @param int    $post_id     The post ID.
	 * @param string $taxonomy    The source taxonomy.
	 * @param string $return_type 'ids' or 'names'. Tags are passed by name.
	 * @return array GD term IDs or names.
	 */
	private function get_listings_terms( $post_id, $taxonomy, $return_type = 'ids' ) {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT gt.term_id, gt.name
				FROM {$wpdb->term_relationships} tr
				INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
				INNER JOIN {$wpdb->termmeta} tm ON tm.term_id = tt.term_id AND tm.meta_key = 'gd_equivalent'
				INNER JOIN {$wpdb->terms} gt ON gt.term_id = tm.meta_value
				WHERE tr.object_id = %d AND tt.taxonomy = %s",
				$post_id,
				$taxonomy
			)
		);

		$terms = array();

		foreach ( (array) $rows as $row ) {
			$terms[] = 'names' === $return_type ? $row->name : (int) $row->term_id;
		}

		return array_values( array_unique( $terms ) );
	}

	/**
	 * Copy a listing's reviews to the GeoDirectory listing.
	 *
	 * Reviews are copied rather than moved so the source data is left intact,
	 * and matched on date and author so re-runs do not duplicate them. The
	 * Classified Listing rating (1-5) is saved as the GD overall rating.
	 *
	 * @since 2.3.0
	 *
	 * @param int $source_id  Source listing ID.
	 * @param int $gd_post_id GeoDirectory post ID.
	 * @return void
	 */
	private function import_reviews( $source_id, $gd_post_id ) {
		global $wpdb;

		$comments = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->comments}
				WHERE comment_post_ID = %d
				AND comment_approved IN ( '0', '1' )
				AND comment_type IN ( '', 'comment', 'review' )
				ORDER BY comment_ID ASC",
				$source_id
			)
		);

		if ( empty( $comments ) ) {
			return;
		}

		$id_map = array();

		foreach ( $comments as $comment ) {
			$existing = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT comment_ID FROM {$wpdb->comments}
					WHERE comment_post_ID = %d
					AND comment_date = %s
					AND comment_author = %s
					AND comment_author_email = %s
					LIMIT 1",
					$gd_post_id,
					$comment->comment_date,
					$comment->comment_author,
					$comment->comment_author_email
				)
			);

			if ( $existing ) {
				$id_map[ (int) $comment->comment_ID ] = (int) $existing;
				continue;
			}

			$parent = (int) $comment->comment_parent;

			$new_comment_id = wp_insert_comment(
				array(
					'comment_post_ID'      => $gd_post_id,
					'comment_author'       => $comment->comment_author,
					'comment_author_email' => $comment->comment_author_email,
					'comment_author_url'   => $comment->comment_author_url,
					'comment_author_IP'    => $comment->comment_author_IP,
					'comment_date'         => $comment->comment_date,
					'comment_date_gmt'     => $comment->comment_date_gmt,
					'comment_content'      => $comment->comment_content,
					'comment_karma'        => $comment->comment_karma,
					'comment_approved'     => $comment->comment_approved,
					'comment_agent'        => $comment->comment_agent,
					'comment_type'         => '',
					'comment_parent'       => $parent && isset( $id_map[ $parent ] ) ? $id_map[ $parent ] : 0,
					'user_id'              => $comment->user_id,
				)
			);

			if ( ! $new_comment_id ) {
				continue;
			}

			$id_map[ (int) $comment->comment_ID ] = (int) $new_comment_id;

			$rating = (int) get_comment_meta( $comment->comment_ID, 'rating', true );

			if ( $rating > 0 && ! $parent ) {
				$this->save_review_rating( $new_comment_id, $rating );
			}
		}

		wp_update_comment_count( $gd_post_id );
	}
}
