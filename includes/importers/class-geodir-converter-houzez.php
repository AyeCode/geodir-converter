<?php
/**
 * Houzez Converter Class.
 *
 * @since     2.3.0
 * @package   GeoDir_Converter
 */

namespace GeoDir_Converter\Importers;

use WP_Error;
use GeoDir_Media;
use GeoDir_Comments;
use GeoDir_Converter\Abstracts\GeoDir_Converter_Importer;

defined( 'ABSPATH' ) || exit;

/**
 * Main converter class for importing from the Houzez real estate theme.
 *
 * @since 2.3.0
 */
class GeoDir_Converter_Houzez extends GeoDir_Converter_Importer {
	/**
	 * Post type identifier for properties.
	 *
	 * @var string
	 */
	const POST_TYPE_PROPERTY = 'property';

	/**
	 * Post type identifier for agents.
	 *
	 * @var string
	 */
	const POST_TYPE_AGENT = 'houzez_agent';

	/**
	 * Post type identifier for agencies.
	 *
	 * @var string
	 */
	const POST_TYPE_AGENCY = 'houzez_agency';

	/**
	 * Post type identifier for reviews.
	 *
	 * @var string
	 */
	const POST_TYPE_REVIEW = 'houzez_reviews';

	/**
	 * Taxonomy identifier for property types.
	 *
	 * @var string
	 */
	const TAX_PROPERTY_TYPE = 'property_type';

	/**
	 * Taxonomy identifier for property status (for sale, for rent).
	 *
	 * @var string
	 */
	const TAX_PROPERTY_STATUS = 'property_status';

	/**
	 * Taxonomy identifier for property features.
	 *
	 * @var string
	 */
	const TAX_PROPERTY_FEATURE = 'property_feature';

	/**
	 * Taxonomy identifier for property labels.
	 *
	 * @var string
	 */
	const TAX_PROPERTY_LABEL = 'property_label';

	/**
	 * Taxonomy identifier for property cities.
	 *
	 * @var string
	 */
	const TAX_PROPERTY_CITY = 'property_city';

	/**
	 * Taxonomy identifier for property areas (neighbourhoods).
	 *
	 * @var string
	 */
	const TAX_PROPERTY_AREA = 'property_area';

	/**
	 * Taxonomy identifier for property states.
	 *
	 * @var string
	 */
	const TAX_PROPERTY_STATE = 'property_state';

	/**
	 * Taxonomy identifier for property countries.
	 *
	 * @var string
	 */
	const TAX_PROPERTY_COUNTRY = 'property_country';

	/**
	 * Houzez post status for sold properties.
	 *
	 * @var string
	 */
	const STATUS_SOLD = 'houzez_sold';

	/**
	 * Houzez fields builder table name, without the database prefix.
	 *
	 * @var string
	 */
	const FIELDS_BUILDER_TABLE = 'houzez_fields_builder';

	/**
	 * Number of properties queued per import task.
	 *
	 * @var int
	 */
	const BATCH_SIZE_IMPORT = 10;

	/**
	 * Houzez property meta keys.
	 */
	const META_PRICE              = 'fave_property_price';
	const META_SEC_PRICE          = 'fave_property_sec_price';
	const META_PRICE_PREFIX       = 'fave_property_price_prefix';
	const META_PRICE_POSTFIX      = 'fave_property_price_postfix';
	const META_CURRENCY           = 'fave_currency';
	const META_SIZE               = 'fave_property_size';
	const META_SIZE_PREFIX        = 'fave_property_size_prefix';
	const META_LAND               = 'fave_property_land';
	const META_LAND_POSTFIX       = 'fave_property_land_postfix';
	const META_BEDROOMS           = 'fave_property_bedrooms';
	const META_BATHROOMS          = 'fave_property_bathrooms';
	const META_ROOMS              = 'fave_property_rooms';
	const META_GARAGE             = 'fave_property_garage';
	const META_GARAGE_SIZE        = 'fave_property_garage_size';
	const META_YEAR               = 'fave_property_year';
	const META_PROPERTY_ID        = 'fave_property_id';
	const META_ADDRESS            = 'fave_property_address';
	const META_MAP_ADDRESS        = 'fave_property_map_address';
	const META_ZIP                = 'fave_property_zip';
	const META_LOCATION           = 'fave_property_location';
	const META_LAT                = 'houzez_geolocation_lat';
	const META_LNG                = 'houzez_geolocation_long';
	const META_FEATURED           = 'fave_featured';
	const META_IMAGES             = 'fave_property_images';
	const META_ATTACHMENTS        = 'fave_attachments';
	const META_VIDEO_URL          = 'fave_video_url';
	const META_VIRTUAL_TOUR       = 'fave_virtual_tour';
	const META_FLOOR_PLANS        = 'floor_plans';
	const META_ADDITIONAL         = 'additional_features';
	const META_MULTI_UNITS        = 'fave_multi_units';
	const META_PRIVATE_NOTE       = 'fave_private_note';
	const META_DISCLAIMER         = 'fave_property_disclaimer';
	const META_AGENT_DISPLAY      = 'fave_agent_display_option';
	const META_AGENTS             = 'fave_agents';
	const META_AGENCY             = 'fave_property_agency';
	const META_ENERGY_CLASS       = 'fave_energy_class';
	const META_ENERGY_INDEX       = 'fave_energy_global_index';
	const META_RENEWABLE_INDEX    = 'fave_renewable_energy_global_index';
	const META_ENERGY_PERFORMANCE = 'fave_energy_performance';
	const META_EPC_CURRENT        = 'fave_epc_current_rating';
	const META_EPC_POTENTIAL      = 'fave_epc_potential_rating';
	const META_GHG_CLASS          = 'fave_ghg_emissions_class';
	const META_GHG_INDEX          = 'fave_ghg_emissions_index';
	const META_DIAGNOSTIC_DATE    = 'fave_diagnostic_date';
	const META_PRICE_PLACEHOLDER  = 'fave_property_price_placeholder';
	const META_SHOW_PLACEHOLDER   = 'fave_show_price_placeholder';
	const META_COUNTRY_CODE       = 'fave_property_country';
	const META_EXPIRATION_DATE    = '_houzez_expiration_date';
	const META_MANUAL_EXPIRE      = 'houzez_manual_expire';

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
	protected $importer_id = 'houzez';

	/**
	 * Property post statuses to import.
	 *
	 * @var array
	 */
	protected $post_statuses = array( 'publish', 'pending', 'draft', 'future', 'private', 'expired', 'on_hold', 'disapproved', self::STATUS_SOLD );

	/**
	 * Cached custom fields definition.
	 *
	 * @var array|null
	 */
	private $custom_fields_cache = null;

	/**
	 * Cached Houzez fields builder definitions.
	 *
	 * @var array|null
	 */
	private $builder_fields_cache = null;

	/**
	 * Cached list of property meta keys that hold data.
	 *
	 * @var array|null
	 */
	private $meta_keys_in_use_cache = null;

	/**
	 * Cached agent and agency details, keyed by post ID.
	 *
	 * @var array
	 */
	private $contact_cache = array();

	/**
	 * Whether any Houzez property reviews exist, or null when not yet checked.
	 *
	 * @var bool|null
	 */
	private $has_reviews_cache = null;

	/**
	 * Initialize hooks.
	 *
	 * @since 2.3.0
	 *
	 * @return void
	 */
	protected function init() {
		add_action( 'init', array( $this, 'maybe_register_post_types' ), 0 );
	}

	/**
	 * Register Houzez post types and taxonomies if not already registered.
	 *
	 * Lets the importer read existing data when the Houzez theme and its
	 * functionality plugin are no longer active.
	 *
	 * @since 2.3.0
	 *
	 * @return void
	 */
	public function maybe_register_post_types() {
		$post_types = array(
			self::POST_TYPE_PROPERTY => 'Properties',
			self::POST_TYPE_AGENT    => 'Agents',
			self::POST_TYPE_AGENCY   => 'Agencies',
			self::POST_TYPE_REVIEW   => 'Reviews',
		);

		foreach ( $post_types as $post_type => $label ) {
			if ( ! post_type_exists( $post_type ) ) {
				register_post_type(
					$post_type,
					array(
						'label'  => $label,
						'public' => false,
					)
				);
			}
		}

		$taxonomies = array(
			self::TAX_PROPERTY_TYPE    => 'Property Types',
			self::TAX_PROPERTY_STATUS  => 'Property Status',
			self::TAX_PROPERTY_FEATURE => 'Property Features',
			self::TAX_PROPERTY_LABEL   => 'Property Labels',
			self::TAX_PROPERTY_CITY    => 'Property Cities',
			self::TAX_PROPERTY_AREA    => 'Property Areas',
			self::TAX_PROPERTY_STATE   => 'Property States',
			self::TAX_PROPERTY_COUNTRY => 'Property Countries',
		);

		foreach ( $taxonomies as $taxonomy => $label ) {
			if ( ! taxonomy_exists( $taxonomy ) ) {
				register_taxonomy(
					$taxonomy,
					self::POST_TYPE_PROPERTY,
					array(
						'label'        => $label,
						'public'       => false,
						'hierarchical' => true,
					)
				);
			}
		}
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
			'post_images',
			'images',
		);

		if ( in_array( $field_name, $skip_fields, true ) ) {
			return true;
		}

		return parent::should_skip_field( $field_name );
	}

	/**
	 * Get importer title.
	 *
	 * @since 2.3.0
	 *
	 * @return string Importer title.
	 */
	public function get_title() {
		return __( 'Houzez', 'geodir-converter' );
	}

	/**
	 * Get importer description.
	 *
	 * @since 2.3.0
	 *
	 * @return string Importer description.
	 */
	public function get_description() {
		return __( 'Import properties, property types, features, custom fields, agents and reviews from the Houzez real estate theme.', 'geodir-converter' );
	}

	/**
	 * Get importer icon URL.
	 *
	 * @since 2.3.0
	 *
	 * @return string Icon URL.
	 */
	public function get_icon() {
		return GEODIR_CONVERTER_PLUGIN_URL . 'assets/images/houzez.png';
	}

	/**
	 * Get the first import task action.
	 *
	 * @since 2.3.0
	 *
	 * @return string Import action identifier.
	 */
	public function get_action() {
		return self::ACTION_IMPORT_CATEGORIES;
	}

	/**
	 * Render the importer settings form.
	 *
	 * @since 2.3.0
	 *
	 * @return void
	 */
	public function render_settings() {
		?>
		<form class="geodir-converter-settings-form" method="post">
			<h6 class="fs-base"><?php esc_html_e( 'Houzez Importer Settings', 'geodir-converter' ); ?></h6>

			<?php
			if ( ! $this->is_houzez_active() ) {
				if ( $this->has_houzez_data() ) {
					aui()->alert(
						array(
							'type'    => 'warning',
							'heading' => esc_html__( 'Houzez theme not detected.', 'geodir-converter' ),
							'content' => esc_html__( 'The Houzez theme and Houzez Theme Functionality plugin are not active. The importer will still import the Houzez properties found in the database.', 'geodir-converter' ),
							'class'   => 'mb-3',
						),
						true
					);
				} else {
					$this->render_plugin_notice(
						esc_html__( 'Houzez Theme Functionality', 'geodir-converter' ),
						'properties',
						esc_url( 'https://themeforest.net/item/houzez-real-estate-wordpress-theme-/15752549' )
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
	 * Check if the Houzez theme or its functionality plugin is active.
	 *
	 * @since 2.3.0
	 *
	 * @return bool True if Houzez is active.
	 */
	private function is_houzez_active() {
		if ( defined( 'HOUZEZ_THEME_VERSION' ) || defined( 'HOUZEZ_PLUGIN_CORE_VERSION' ) || class_exists( 'Houzez_Fields_Builder' ) ) {
			return true;
		}

		$theme = wp_get_theme();

		return 'houzez' === $theme->get_template() || false !== stripos( (string) $theme->get( 'Name' ), 'houzez' );
	}

	/**
	 * Check if Houzez properties exist in the database.
	 *
	 * @since 2.3.0
	 *
	 * @return bool True if at least one property exists.
	 */
	private function has_houzez_data() {
		global $wpdb;

		$property_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s LIMIT 1",
				self::POST_TYPE_PROPERTY
			)
		);

		return ! empty( $property_id );
	}

	/**
	 * Validate and sanitize importer settings.
	 *
	 * @since 2.3.0
	 *
	 * @param array $settings The settings to validate.
	 * @param array $files    The uploaded files to validate.
	 * @return array|WP_Error Validated and sanitized settings, or WP_Error on failure.
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
	 * Get the next import task in the sequence.
	 *
	 * @since 2.3.0
	 *
	 * @param array $task         The current task data.
	 * @param bool  $reset_offset Whether to reset the offset counter.
	 * @return array|false The next task data, or false if all tasks are completed.
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
	 * Calculate and set the total number of items to be imported.
	 *
	 * @since 2.3.0
	 *
	 * @return void
	 */
	public function set_import_total() {
		$total_items  = count( $this->get_source_terms( self::TAX_PROPERTY_TYPE ) );
		$total_items += count( $this->get_source_terms( self::TAX_PROPERTY_LABEL ) );
		$total_items += count( $this->get_custom_fields() );
		$total_items += $this->count_listings();

		$this->increase_imports_total( $total_items );
	}

	/**
	 * Count the properties to import.
	 *
	 * @since 2.3.0
	 *
	 * @return int Number of properties.
	 */
	private function count_listings() {
		global $wpdb;

		$status_placeholders = implode( ',', array_fill( 0, count( $this->post_statuses ), '%s' ) );

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status IN ({$status_placeholders})",
				array_merge( array( self::POST_TYPE_PROPERTY ), $this->post_statuses )
			)
		);

		return (int) $count;
	}

	/**
	 * Get the terms of a Houzez taxonomy, parents before children.
	 *
	 * @since 2.3.0
	 *
	 * @param string $taxonomy The taxonomy.
	 * @return array Term objects.
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

		if ( empty( $terms ) || ! is_array( $terms ) ) {
			return array();
		}

		$sorted = array();
		$this->sort_terms_hierarchically( $terms, $sorted );

		// Keep orphaned terms whose parent no longer exists.
		if ( count( $sorted ) < count( $terms ) ) {
			$sorted_ids = wp_list_pluck( $sorted, 'term_id' );

			foreach ( $terms as $term ) {
				if ( ! in_array( $term->term_id, $sorted_ids, true ) ) {
					$sorted[] = $term;
				}
			}
		}

		return $sorted;
	}

	/**
	 * Sort terms so every parent precedes its children.
	 *
	 * @since 2.3.0
	 *
	 * @param array $terms  Term objects.
	 * @param array $sorted Sorted terms, passed by reference.
	 * @param int   $parent Parent term ID.
	 * @return void
	 */
	private function sort_terms_hierarchically( $terms, &$sorted, $parent = 0 ) {
		foreach ( $terms as $term ) {
			if ( (int) $term->parent === (int) $parent ) {
				$sorted[] = $term;
				$this->sort_terms_hierarchically( $terms, $sorted, $term->term_id );
			}
		}
	}

	/**
	 * Import property types as GeoDirectory categories.
	 *
	 * @since 2.3.0
	 *
	 * @param array $task Import task.
	 * @return array|false Next task.
	 */
	public function task_import_categories( $task ) {
		$this->log( __( 'Categories: Import started.', 'geodir-converter' ) );
		$this->set_import_total();

		$categories = $this->get_source_terms( self::TAX_PROPERTY_TYPE );

		if ( empty( $categories ) ) {
			$this->log( __( 'Categories: No property types to import.', 'geodir-converter' ), 'warning' );
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

		$this->import_category_meta( $categories, $post_type . 'category' );

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
	 * Copy the property type image, map marker and color to the GD categories.
	 *
	 * Houzez keeps the image and marker as attachment IDs in the term meta
	 * `fave_taxonomy_img` and `fave_marker_icon`, and the color in the
	 * `_houzez_property_type_{term_id}` option. Values already set on the GD
	 * category are left alone.
	 *
	 * @since 2.3.0
	 *
	 * @param array  $terms       Source term objects.
	 * @param string $gd_taxonomy Target GD category taxonomy.
	 * @return void
	 */
	private function import_category_meta( $terms, $gd_taxonomy ) {
		$icon_set = false;

		foreach ( $terms as $term ) {
			$gd_term_id = absint( get_term_meta( $term->term_id, 'gd_equivalent', true ) );

			if ( ! $gd_term_id || ! term_exists( $gd_term_id, $gd_taxonomy ) ) {
				continue;
			}

			$images = array(
				'ct_cat_default_img' => 'fave_taxonomy_img',
				'ct_cat_icon'        => 'fave_marker_icon',
			);

			foreach ( $images as $gd_key => $source_key ) {
				$attachment_id = absint( get_term_meta( $term->term_id, $source_key, true ) );
				$url           = $attachment_id ? wp_get_attachment_url( $attachment_id ) : '';

				if ( ! $url || ! empty( get_term_meta( $gd_term_id, $gd_key, true ) ) ) {
					continue;
				}

				update_term_meta(
					$gd_term_id,
					$gd_key,
					array(
						'id'  => $attachment_id,
						'src' => function_exists( 'geodir_file_relative_url' ) ? geodir_file_relative_url( $url ) : $url,
					)
				);

				if ( 'ct_cat_icon' === $gd_key ) {
					$icon_set = true;
				}
			}

			$meta  = get_option( '_houzez_property_type_' . $term->term_id );
			$color = is_array( $meta ) && isset( $meta['color_type'], $meta['color'] ) && 'custom' === $meta['color_type'] ? sanitize_hex_color( $meta['color'] ) : '';

			if ( $color && ! get_term_meta( $gd_term_id, 'ct_cat_color', true ) ) {
				update_term_meta( $gd_term_id, 'ct_cat_color', $color );
			}
		}

		// GD caches the category map icons in the gd_term_icons option.
		if ( $icon_set && function_exists( 'geodir_get_term_icon_rebuild' ) ) {
			geodir_get_term_icon_rebuild();
		}
	}

	/**
	 * Import property labels as GeoDirectory tags.
	 *
	 * @since 2.3.0
	 *
	 * @param array $task Import task.
	 * @return array|false Next task.
	 */
	public function task_import_tags( $task ) {
		$this->log( __( 'Tags: Import started.', 'geodir-converter' ) );

		$tags = $this->get_source_terms( self::TAX_PROPERTY_LABEL );

		if ( empty( $tags ) ) {
			$this->log( __( 'Tags: No property labels to import.', 'geodir-converter' ), 'warning' );
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

		// GD tags are flat, so drop the label hierarchy.
		foreach ( $tags as $tag ) {
			$tag->parent = 0;
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
	 * Each definition carries a `source` meta key and a `format` telling the
	 * listing import how to read the value. Fields flagged `optional` are only
	 * created when at least one property holds data for them.
	 *
	 * @since 2.3.0
	 *
	 * @return array Field definitions.
	 */
	private function get_custom_fields() {
		if ( null !== $this->custom_fields_cache ) {
			return $this->custom_fields_cache;
		}

		$price_extra = $this->get_price_extra();

		$fields = array(
			array(
				'type'           => 'text',
				'data_type'      => 'INT',
				'field_key'      => $this->importer_id . '_id',
				'label'          => __( 'Houzez ID', 'geodir-converter' ),
				'description'    => __( 'Original Houzez property ID.', 'geodir-converter' ),
				'placeholder'    => __( 'Houzez ID', 'geodir-converter' ),
				'icon'           => 'far fa-id-card',
				'only_for_admin' => 1,
				'format'         => 'special',
			),
			array(
				'type'        => 'text',
				'field_key'   => 'property_ref',
				'label'       => __( 'Property ID', 'geodir-converter' ),
				'description' => __( 'The property reference ID.', 'geodir-converter' ),
				'placeholder' => __( 'Property ID', 'geodir-converter' ),
				'icon'        => 'fas fa-hashtag',
				'source'      => self::META_PROPERTY_ID,
				'format'      => 'text',
			),
			array(
				'type'          => 'text',
				'data_type'     => 'FLOAT',
				'decimal_point' => 2,
				'field_key'     => 'price',
				'label'         => __( 'Price', 'geodir-converter' ),
				'description'   => __( 'The sale or rent price of the property.', 'geodir-converter' ),
				'placeholder'   => __( 'Price', 'geodir-converter' ),
				'icon'          => 'fas fa-dollar-sign',
				'show_in'       => '[detail],[listing]',
				'cat_sort'      => 1,
				'cat_filter'    => 1,
				'extra'         => $price_extra,
				'source'        => self::META_PRICE,
				'format'        => 'number',
			),
			array(
				'type'          => 'text',
				'data_type'     => 'FLOAT',
				'decimal_point' => 2,
				'field_key'     => 'second_price',
				'label'         => __( 'Second Price', 'geodir-converter' ),
				'description'   => __( 'Optional second price, e.g. a rental or per square foot price.', 'geodir-converter' ),
				'placeholder'   => __( 'Second Price', 'geodir-converter' ),
				'icon'          => 'fas fa-money-bill',
				'extra'         => $price_extra,
				'source'        => self::META_SEC_PRICE,
				'format'        => 'number',
				'optional'      => true,
			),
			array(
				'type'        => 'text',
				'field_key'   => 'price_prefix',
				'label'       => __( 'Price Prefix', 'geodir-converter' ),
				'description' => __( 'Label displayed before the price, e.g. Start From.', 'geodir-converter' ),
				'placeholder' => __( 'Price Prefix', 'geodir-converter' ),
				'icon'        => 'fas fa-tag',
				'source'      => self::META_PRICE_PREFIX,
				'format'      => 'text',
			),
			array(
				'type'        => 'text',
				'field_key'   => 'price_postfix',
				'label'       => __( 'Price Postfix', 'geodir-converter' ),
				'description' => __( 'Label displayed after the price, e.g. Per Month.', 'geodir-converter' ),
				'placeholder' => __( 'Price Postfix', 'geodir-converter' ),
				'icon'        => 'fas fa-tag',
				'source'      => self::META_PRICE_POSTFIX,
				'format'      => 'text',
			),
			array(
				'type'        => 'text',
				'field_key'   => 'price_placeholder',
				'label'       => __( 'Price Placeholder', 'geodir-converter' ),
				'description' => __( 'Text shown instead of the price, e.g. Price on Request.', 'geodir-converter' ),
				'placeholder' => __( 'Price Placeholder', 'geodir-converter' ),
				'icon'        => 'fas fa-tag',
				'show_in'     => '[detail],[listing]',
				'source'      => self::META_PRICE_PLACEHOLDER,
				'format'      => 'special',
				'optional'    => true,
			),
			array(
				'type'        => 'text',
				'field_key'   => 'price_currency',
				'label'       => __( 'Currency', 'geodir-converter' ),
				'description' => __( 'The currency of the property price.', 'geodir-converter' ),
				'placeholder' => __( 'Currency', 'geodir-converter' ),
				'icon'        => 'fas fa-coins',
				'source'      => self::META_CURRENCY,
				'format'      => 'text',
				'optional'    => true,
			),
			array(
				'type'          => 'text',
				'data_type'     => 'FLOAT',
				'decimal_point' => 2,
				'field_key'     => 'property_size',
				'label'         => __( 'Area Size', 'geodir-converter' ),
				'description'   => __( 'The area size of the property.', 'geodir-converter' ),
				'placeholder'   => __( 'Area Size', 'geodir-converter' ),
				'icon'          => 'fas fa-ruler-combined',
				'show_in'       => '[detail],[listing]',
				'cat_sort'      => 1,
				'cat_filter'    => 1,
				'source'        => self::META_SIZE,
				'format'        => 'number',
			),
			array(
				'type'        => 'text',
				'field_key'   => 'property_size_unit',
				'label'       => __( 'Area Size Unit', 'geodir-converter' ),
				'description' => __( 'The unit of the area size, e.g. Sq Ft.', 'geodir-converter' ),
				'placeholder' => __( 'Area Size Unit', 'geodir-converter' ),
				'icon'        => 'fas fa-ruler',
				'source'      => self::META_SIZE_PREFIX,
				'format'      => 'text',
			),
			array(
				'type'          => 'text',
				'data_type'     => 'FLOAT',
				'decimal_point' => 2,
				'field_key'     => 'land_area',
				'label'         => __( 'Land Area', 'geodir-converter' ),
				'description'   => __( 'The land area of the property.', 'geodir-converter' ),
				'placeholder'   => __( 'Land Area', 'geodir-converter' ),
				'icon'          => 'fas fa-expand',
				'source'        => self::META_LAND,
				'format'        => 'number',
			),
			array(
				'type'        => 'text',
				'field_key'   => 'land_area_unit',
				'label'       => __( 'Land Area Unit', 'geodir-converter' ),
				'description' => __( 'The unit of the land area, e.g. Sq Ft.', 'geodir-converter' ),
				'placeholder' => __( 'Land Area Unit', 'geodir-converter' ),
				'icon'        => 'fas fa-ruler',
				'source'      => self::META_LAND_POSTFIX,
				'format'      => 'text',
			),
			array(
				'type'        => 'text',
				'data_type'   => 'INT',
				'field_key'   => 'bedrooms',
				'label'       => __( 'Bedrooms', 'geodir-converter' ),
				'description' => __( 'Number of bedrooms.', 'geodir-converter' ),
				'placeholder' => __( 'Bedrooms', 'geodir-converter' ),
				'icon'        => 'fas fa-bed',
				'show_in'     => '[detail],[listing]',
				'cat_sort'    => 1,
				'cat_filter'  => 1,
				'source'      => self::META_BEDROOMS,
				'format'      => 'int',
			),
			array(
				'type'          => 'text',
				'data_type'     => 'FLOAT',
				'decimal_point' => 1,
				'field_key'     => 'bathrooms',
				'label'         => __( 'Bathrooms', 'geodir-converter' ),
				'description'   => __( 'Number of bathrooms.', 'geodir-converter' ),
				'placeholder'   => __( 'Bathrooms', 'geodir-converter' ),
				'icon'          => 'fas fa-bath',
				'show_in'       => '[detail],[listing]',
				'cat_sort'      => 1,
				'cat_filter'    => 1,
				'source'        => self::META_BATHROOMS,
				'format'        => 'number',
			),
			array(
				'type'        => 'text',
				'data_type'   => 'INT',
				'field_key'   => 'rooms',
				'label'       => __( 'Rooms', 'geodir-converter' ),
				'description' => __( 'Total number of rooms.', 'geodir-converter' ),
				'placeholder' => __( 'Rooms', 'geodir-converter' ),
				'icon'        => 'fas fa-door-open',
				'source'      => self::META_ROOMS,
				'format'      => 'int',
			),
			array(
				'type'        => 'text',
				'data_type'   => 'INT',
				'field_key'   => 'garages',
				'label'       => __( 'Garages', 'geodir-converter' ),
				'description' => __( 'Number of garages.', 'geodir-converter' ),
				'placeholder' => __( 'Garages', 'geodir-converter' ),
				'icon'        => 'fas fa-car',
				'source'      => self::META_GARAGE,
				'format'      => 'int',
			),
			array(
				'type'        => 'text',
				'field_key'   => 'garage_size',
				'label'       => __( 'Garage Size', 'geodir-converter' ),
				'description' => __( 'The size of the garage.', 'geodir-converter' ),
				'placeholder' => __( 'Garage Size', 'geodir-converter' ),
				'icon'        => 'fas fa-warehouse',
				'source'      => self::META_GARAGE_SIZE,
				'format'      => 'text',
			),
			array(
				'type'        => 'text',
				'data_type'   => 'INT',
				'field_key'   => 'year_built',
				'label'       => __( 'Year Built', 'geodir-converter' ),
				'description' => __( 'The year the property was built.', 'geodir-converter' ),
				'placeholder' => __( 'Year Built', 'geodir-converter' ),
				'icon'        => 'fas fa-calendar',
				'source'      => self::META_YEAR,
				'format'      => 'int',
			),
			array(
				'type'        => 'multiselect',
				'field_key'   => 'property_status',
				'label'       => __( 'Property Status', 'geodir-converter' ),
				'description' => __( 'The status of the property, e.g. For Sale or For Rent.', 'geodir-converter' ),
				'placeholder' => __( 'Property Status', 'geodir-converter' ),
				'icon'        => 'fas fa-info-circle',
				'show_in'     => '[detail],[listing]',
				'css_class'   => 'gd-comma-list',
				'cat_filter'  => 1,
				'options'     => $this->get_status_options(),
				'format'      => 'special',
			),
			array(
				'type'        => 'multiselect',
				'field_key'   => 'property_features',
				'label'       => __( 'Features', 'geodir-converter' ),
				'description' => __( 'The features and amenities of the property.', 'geodir-converter' ),
				'icon'        => 'fas fa-check-square',
				'css_class'   => 'gd-comma-list',
				'cat_filter'  => 1,
				'options'     => $this->get_taxonomy_term_names( self::TAX_PROPERTY_FEATURE ),
				'format'      => 'special',
			),
			array(
				'type'           => 'checkbox',
				'field_key'      => 'featured',
				'label'          => __( 'Is Featured?', 'geodir-converter' ),
				'description'    => __( 'Mark the property as featured.', 'geodir-converter' ),
				'icon'           => 'fas fa-star',
				'show_in'        => '',
				'only_for_admin' => 1,
				'format'         => 'special',
			),
			array(
				'type'        => 'textarea',
				'data_type'   => 'TEXT',
				'field_key'   => 'video',
				'label'       => __( 'Video', 'geodir-converter' ),
				'description' => __( 'Property video URL, e.g. YouTube or Vimeo.', 'geodir-converter' ),
				'placeholder' => __( 'Video URL', 'geodir-converter' ),
				'icon'        => 'fas fa-video',
				'show_in'     => '[owntab]',
				'source'      => self::META_VIDEO_URL,
				'format'      => 'text',
			),
			array(
				'type'        => 'textarea',
				'data_type'   => 'TEXT',
				'field_key'   => 'virtual_tour',
				'label'       => __( '360° Virtual Tour', 'geodir-converter' ),
				'description' => __( 'The virtual tour embed code or URL.', 'geodir-converter' ),
				'placeholder' => __( 'Virtual Tour', 'geodir-converter' ),
				'icon'        => 'fas fa-street-view',
				'show_in'     => '[owntab]',
				'extra'       => array( 'embed' => 1 ),
				'source'      => self::META_VIRTUAL_TOUR,
				'format'      => 'text',
				'optional'    => true,
			),
			array(
				'type'        => 'html',
				'field_key'   => 'floor_plans',
				'label'       => __( 'Floor Plans', 'geodir-converter' ),
				'description' => __( 'The floor plans of the property.', 'geodir-converter' ),
				'icon'        => 'fas fa-layer-group',
				'show_in'     => '[owntab]',
				'source'      => self::META_FLOOR_PLANS,
				'format'      => 'special',
				'optional'    => true,
			),
			array(
				'type'        => 'html',
				'field_key'   => 'additional_details',
				'label'       => __( 'Additional Details', 'geodir-converter' ),
				'description' => __( 'Additional details about the property.', 'geodir-converter' ),
				'icon'        => 'fas fa-list',
				'source'      => self::META_ADDITIONAL,
				'format'      => 'special',
				'optional'    => true,
			),
			array(
				'type'        => 'html',
				'field_key'   => 'sub_listings',
				'label'       => __( 'Sub Listings', 'geodir-converter' ),
				'description' => __( 'The units of a multi-unit property.', 'geodir-converter' ),
				'icon'        => 'fas fa-building',
				'show_in'     => '[owntab]',
				'source'      => self::META_MULTI_UNITS,
				'format'      => 'special',
				'optional'    => true,
			),
			array(
				'type'        => 'file',
				'field_key'   => 'property_documents',
				'label'       => __( 'Property Documents', 'geodir-converter' ),
				'description' => __( 'Documents attached to the property.', 'geodir-converter' ),
				'icon'        => 'fas fa-file-alt',
				'show_in'     => '[owntab]',
				'extra'       => array(
					'gd_file_types' => array( '*' ),
					'file_limit'    => 0,
				),
				'source'      => self::META_ATTACHMENTS,
				'format'      => 'special',
				'optional'    => true,
			),
			array(
				'type'        => 'text',
				'field_key'   => 'energy_class',
				'label'       => __( 'Energy Class', 'geodir-converter' ),
				'description' => __( 'The energy class of the property.', 'geodir-converter' ),
				'placeholder' => __( 'Energy Class', 'geodir-converter' ),
				'icon'        => 'fas fa-leaf',
				'source'      => self::META_ENERGY_CLASS,
				'format'      => 'text',
				'optional'    => true,
			),
			array(
				'type'        => 'text',
				'field_key'   => 'energy_global_index',
				'label'       => __( 'Global Energy Performance Index', 'geodir-converter' ),
				'description' => __( 'For example: 92.42 kWh / m²a.', 'geodir-converter' ),
				'placeholder' => __( 'Global Energy Performance Index', 'geodir-converter' ),
				'icon'        => 'fas fa-bolt',
				'source'      => self::META_ENERGY_INDEX,
				'format'      => 'text',
				'optional'    => true,
			),
			array(
				'type'        => 'text',
				'field_key'   => 'renewable_energy_index',
				'label'       => __( 'Renewable Energy Performance Index', 'geodir-converter' ),
				'description' => __( 'For example: 00.00 kWh / m²a.', 'geodir-converter' ),
				'placeholder' => __( 'Renewable Energy Performance Index', 'geodir-converter' ),
				'icon'        => 'fas fa-solar-panel',
				'source'      => self::META_RENEWABLE_INDEX,
				'format'      => 'text',
				'optional'    => true,
			),
			array(
				'type'        => 'text',
				'field_key'   => 'energy_performance',
				'label'       => __( 'Energy Performance', 'geodir-converter' ),
				'description' => __( 'Energy performance of the building.', 'geodir-converter' ),
				'placeholder' => __( 'Energy Performance', 'geodir-converter' ),
				'icon'        => 'fas fa-bolt',
				'source'      => self::META_ENERGY_PERFORMANCE,
				'format'      => 'text',
				'optional'    => true,
			),
			array(
				'type'        => 'text',
				'field_key'   => 'epc_current_rating',
				'label'       => __( 'EPC Current Rating', 'geodir-converter' ),
				'description' => __( 'The current EPC rating.', 'geodir-converter' ),
				'placeholder' => __( 'EPC Current Rating', 'geodir-converter' ),
				'icon'        => 'fas fa-chart-bar',
				'source'      => self::META_EPC_CURRENT,
				'format'      => 'text',
				'optional'    => true,
			),
			array(
				'type'        => 'text',
				'field_key'   => 'epc_potential_rating',
				'label'       => __( 'EPC Potential Rating', 'geodir-converter' ),
				'description' => __( 'The potential EPC rating.', 'geodir-converter' ),
				'placeholder' => __( 'EPC Potential Rating', 'geodir-converter' ),
				'icon'        => 'fas fa-chart-line',
				'source'      => self::META_EPC_POTENTIAL,
				'format'      => 'text',
				'optional'    => true,
			),
			array(
				'type'        => 'text',
				'field_key'   => 'ghg_emissions_class',
				'label'       => __( 'GHG Emissions Class', 'geodir-converter' ),
				'description' => __( 'The greenhouse gas emissions class.', 'geodir-converter' ),
				'placeholder' => __( 'GHG Emissions Class', 'geodir-converter' ),
				'icon'        => 'fas fa-smog',
				'source'      => self::META_GHG_CLASS,
				'format'      => 'text',
				'optional'    => true,
			),
			array(
				'type'        => 'text',
				'field_key'   => 'ghg_emissions_index',
				'label'       => __( 'GHG Emissions Index', 'geodir-converter' ),
				'description' => __( 'The greenhouse gas emissions index.', 'geodir-converter' ),
				'placeholder' => __( 'GHG Emissions Index', 'geodir-converter' ),
				'icon'        => 'fas fa-smog',
				'source'      => self::META_GHG_INDEX,
				'format'      => 'text',
				'optional'    => true,
			),
			array(
				'type'        => 'text',
				'field_key'   => 'energy_diagnostic_date',
				'label'       => __( 'Diagnostic Date', 'geodir-converter' ),
				'description' => __( 'The date of the energy diagnostic.', 'geodir-converter' ),
				'placeholder' => __( 'Diagnostic Date', 'geodir-converter' ),
				'icon'        => 'fas fa-calendar-check',
				'source'      => self::META_DIAGNOSTIC_DATE,
				'format'      => 'text',
				'optional'    => true,
			),
			array(
				'type'           => 'textarea',
				'data_type'      => 'TEXT',
				'field_key'      => 'private_note',
				'label'          => __( 'Private Note', 'geodir-converter' ),
				'description'    => __( 'A private note visible to admins only.', 'geodir-converter' ),
				'icon'           => 'fas fa-sticky-note',
				'only_for_admin' => 1,
				'source'         => self::META_PRIVATE_NOTE,
				'format'         => 'text',
				'optional'       => true,
			),
			array(
				'type'        => 'textarea',
				'data_type'   => 'TEXT',
				'field_key'   => 'property_disclaimer',
				'label'       => __( 'Disclaimer', 'geodir-converter' ),
				'description' => __( 'The property disclaimer.', 'geodir-converter' ),
				'icon'        => 'fas fa-exclamation-circle',
				'source'      => self::META_DISCLAIMER,
				'format'      => 'text',
				'optional'    => true,
			),
			array(
				'type'        => 'text',
				'field_key'   => 'agent_name',
				'label'       => __( 'Agent', 'geodir-converter' ),
				'description' => __( 'The agent(s) handling the property.', 'geodir-converter' ),
				'placeholder' => __( 'Agent Name', 'geodir-converter' ),
				'icon'        => 'fas fa-user-tie',
				'source'      => self::META_AGENTS,
				'format'      => 'special',
				'optional'    => true,
			),
			array(
				'type'        => 'email',
				'field_key'   => 'agent_email',
				'label'       => __( 'Agent Email', 'geodir-converter' ),
				'description' => __( 'The email address of the agent.', 'geodir-converter' ),
				'placeholder' => __( 'Agent Email', 'geodir-converter' ),
				'icon'        => 'far fa-envelope',
				'source'      => self::META_AGENTS,
				'format'      => 'special',
				'optional'    => true,
			),
			array(
				'type'        => 'phone',
				'field_key'   => 'agent_phone',
				'label'       => __( 'Agent Phone', 'geodir-converter' ),
				'description' => __( 'The office phone number of the agent.', 'geodir-converter' ),
				'placeholder' => __( 'Agent Phone', 'geodir-converter' ),
				'icon'        => 'fas fa-phone',
				'source'      => self::META_AGENTS,
				'format'      => 'special',
				'optional'    => true,
			),
			array(
				'type'        => 'phone',
				'field_key'   => 'agent_mobile',
				'label'       => __( 'Agent Mobile', 'geodir-converter' ),
				'description' => __( 'The mobile phone number of the agent.', 'geodir-converter' ),
				'placeholder' => __( 'Agent Mobile', 'geodir-converter' ),
				'icon'        => 'fas fa-mobile-alt',
				'source'      => self::META_AGENTS,
				'format'      => 'special',
				'optional'    => true,
			),
			array(
				'type'        => 'text',
				'field_key'   => 'agency_name',
				'label'       => __( 'Agency', 'geodir-converter' ),
				'description' => __( 'The agency handling the property.', 'geodir-converter' ),
				'placeholder' => __( 'Agency Name', 'geodir-converter' ),
				'icon'        => 'fas fa-building',
				'source'      => self::META_AGENCY,
				'format'      => 'special',
				'optional'    => true,
			),
			array(
				'type'        => 'email',
				'field_key'   => 'agency_email',
				'label'       => __( 'Agency Email', 'geodir-converter' ),
				'description' => __( 'The email address of the agency.', 'geodir-converter' ),
				'placeholder' => __( 'Agency Email', 'geodir-converter' ),
				'icon'        => 'far fa-envelope',
				'source'      => self::META_AGENCY,
				'format'      => 'special',
				'optional'    => true,
			),
			array(
				'type'        => 'phone',
				'field_key'   => 'agency_phone',
				'label'       => __( 'Agency Phone', 'geodir-converter' ),
				'description' => __( 'The phone number of the agency.', 'geodir-converter' ),
				'placeholder' => __( 'Agency Phone', 'geodir-converter' ),
				'icon'        => 'fas fa-phone',
				'source'      => self::META_AGENCY,
				'format'      => 'special',
				'optional'    => true,
			),
		);

		// Drop optional fields no property uses.
		$meta_keys_in_use = $this->get_meta_keys_in_use();

		$fields = array_values(
			array_filter(
				$fields,
				function ( $field ) use ( $meta_keys_in_use ) {
					return empty( $field['optional'] ) || in_array( $field['source'], $meta_keys_in_use, true );
				}
			)
		);

		// Append fields created with the Houzez fields builder.
		$fields = array_merge( $fields, $this->get_builder_fields( wp_list_pluck( $fields, 'field_key' ) ) );

		$this->custom_fields_cache = $fields;

		return $fields;
	}

	/**
	 * Get the property meta keys that hold a value on at least one property.
	 *
	 * @since 2.3.0
	 *
	 * @return array Meta keys.
	 */
	private function get_meta_keys_in_use() {
		if ( null !== $this->meta_keys_in_use_cache ) {
			return $this->meta_keys_in_use_cache;
		}

		global $wpdb;

		$meta_keys = array(
			self::META_SEC_PRICE,
			self::META_CURRENCY,
			self::META_VIRTUAL_TOUR,
			self::META_FLOOR_PLANS,
			self::META_ADDITIONAL,
			self::META_MULTI_UNITS,
			self::META_ATTACHMENTS,
			self::META_ENERGY_CLASS,
			self::META_ENERGY_INDEX,
			self::META_RENEWABLE_INDEX,
			self::META_ENERGY_PERFORMANCE,
			self::META_EPC_CURRENT,
			self::META_EPC_POTENTIAL,
			self::META_GHG_CLASS,
			self::META_GHG_INDEX,
			self::META_DIAGNOSTIC_DATE,
			self::META_PRICE_PLACEHOLDER,
			self::META_PRIVATE_NOTE,
			self::META_DISCLAIMER,
			self::META_AGENTS,
			self::META_AGENCY,
		);

		$placeholders = implode( ',', array_fill( 0, count( $meta_keys ), '%s' ) );

		$in_use = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT pm.meta_key
				FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				WHERE p.post_type = %s
				AND pm.meta_key IN ({$placeholders})
				AND pm.meta_value NOT IN ('', '0', '-1', 'a:0:{}')",
				array_merge( array( self::POST_TYPE_PROPERTY ), $meta_keys )
			)
		);

		$this->meta_keys_in_use_cache = is_array( $in_use ) ? $in_use : array();

		return $this->meta_keys_in_use_cache;
	}

	/**
	 * Build GeoDirectory price formatting from the Houzez currency settings.
	 *
	 * @since 2.3.0
	 *
	 * @return array Price field extras.
	 */
	private function get_price_extra() {
		$options = get_option( 'houzez_options', array() );
		$options = is_array( $options ) ? $options : array();

		$symbol    = isset( $options['currency_symbol'] ) && '' !== $options['currency_symbol'] ? $options['currency_symbol'] : '$';
		$position  = isset( $options['currency_position'] ) && 'after' === $options['currency_position'] ? 'right' : 'left';
		$thousands = isset( $options['thousands_separator'] ) ? $options['thousands_separator'] : ',';
		$decimal   = isset( $options['decimal_point_separator'] ) ? $options['decimal_point_separator'] : '.';

		// With multi-currency on, the default currency's settings live in the currencies table.
		$default_currency = $this->get_default_multi_currency( $options );

		if ( ! empty( $default_currency ) ) {
			$symbol    = '' !== (string) $default_currency['currency_symbol'] ? $default_currency['currency_symbol'] : $symbol;
			$position  = 'after' === $default_currency['currency_position'] ? 'right' : 'left';
			$thousands = (string) $default_currency['currency_thousand_separator'];
			$decimal   = (string) $default_currency['currency_decimal_separator'];
		}

		$thousands_map = array(
			','  => 'comma',
			'.'  => 'period',
			' '  => 'space',
			'\\' => 'slash',
			''   => 'none',
		);

		return array(
			'is_price'                  => 1,
			'thousand_separator'        => isset( $thousands_map[ $thousands ] ) ? $thousands_map[ $thousands ] : 'comma',
			'decimal_separator'         => ',' === $decimal ? 'comma' : 'period',
			'decimal_display'           => 'if',
			'currency_symbol'           => sanitize_text_field( $symbol ),
			'currency_symbol_placement' => $position,
		);
	}

	/**
	 * Get the default currency row when Houzez multi-currency is enabled.
	 *
	 * @since 2.3.0
	 *
	 * @param array $options Houzez theme options.
	 * @return array|null Currency row, or null when not in use.
	 */
	private function get_default_multi_currency( $options ) {
		global $wpdb;

		if ( empty( $options['multi_currency'] ) || empty( $options['default_multi_currency'] ) ) {
			return null;
		}

		$table = $wpdb->prefix . 'houzez_currencies';

		if ( $table !== $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) ) {
			return null;
		}

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT currency_symbol, currency_position, currency_decimal_separator, currency_thousand_separator FROM {$table} WHERE currency_code = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				(string) $options['default_multi_currency']
			),
			ARRAY_A
		);

		return ! empty( $row ) ? $row : null;
	}

	/**
	 * Build GeoDirectory option values from a list of option labels.
	 *
	 * GeoDirectory 2.3.19+ reads one option per line, which keeps values
	 * containing commas or slashes (e.g. "Washer/Dryer") intact. Older
	 * versions only understand the comma separated format.
	 *
	 * @since 2.3.0
	 *
	 * @param array $options Option lines.
	 * @return string Option values.
	 */
	private function format_option_values( $options ) {
		$options = array_values( array_filter( array_map( 'trim', array_map( 'strval', (array) $options ) ), 'strlen' ) );

		if ( function_exists( 'geodir_is_old_option_values' ) ) {
			return implode( PHP_EOL, $options );
		}

		return implode( ',', $options );
	}

	/**
	 * Get the value stored for a multiselect option label.
	 *
	 * GeoDirectory saves multiselect values as a comma separated list and
	 * splits it on "," when displaying and filtering, so a value must not hold
	 * a comma. " : " separates an option's value from its label.
	 *
	 * @since 2.3.0
	 *
	 * @param string $label Option label.
	 * @return string Option value.
	 */
	private function get_multiselect_value( $label ) {
		return trim( preg_replace( '/\s*(?:,|\s:\s)\s*/u', ' ', trim( (string) $label ) ) );
	}

	/**
	 * Get the option lines of a choice field, keyed by option value.
	 *
	 * Multiselect labels holding a comma get a comma free value, written in
	 * GeoDirectory's "value : label" option format.
	 *
	 * @since 2.3.0
	 *
	 * @param array $field Field definition.
	 * @return array Option lines keyed by value.
	 */
	private function get_option_lines( $field ) {
		$lines = array();

		foreach ( (array) ( isset( $field['options'] ) ? $field['options'] : array() ) as $label ) {
			$label = trim( (string) $label );

			if ( '' === $label ) {
				continue;
			}

			if ( 'multiselect' === $field['type'] ) {
				$value = $this->get_multiselect_value( $label );

				if ( '' !== $value && ! isset( $lines[ $value ] ) ) {
					$lines[ $value ] = $value !== $label ? $value . ' : ' . $label : $label;
				}
			} elseif ( ! isset( $lines[ $label ] ) ) {
				$lines[ $label ] = $label;
			}
		}

		return $lines;
	}

	/**
	 * Parse GeoDirectory option values into option lines and their values.
	 *
	 * @since 2.3.0
	 *
	 * @param string $option_values Stored option values.
	 * @return array {
	 *     @type array $lines  Option lines in the one-per-line format.
	 *     @type array $values Option values.
	 * }
	 */
	private function parse_option_values( $option_values ) {
		$lines  = array();
		$values = array();

		if ( '' === trim( (string) $option_values ) ) {
			return compact( 'lines', 'values' );
		}

		if ( ! function_exists( 'geodir_is_old_option_values' ) || ! function_exists( 'geodir_string_to_options' ) ) {
			$values = array_values( array_filter( array_map( 'trim', explode( ',', (string) $option_values ) ), 'strlen' ) );

			return array(
				'lines'  => $values,
				'values' => $values,
			);
		}

		$options = geodir_string_to_options( (string) $option_values );

		foreach ( (array) $options as $option ) {
			if ( ! is_array( $option ) ) {
				continue;
			}

			$label = isset( $option['label'] ) ? (string) $option['label'] : '';
			$value = isset( $option['value'] ) ? (string) $option['value'] : '';

			if ( isset( $option['optgroup'] ) && 'start' === $option['optgroup'] ) {
				$lines[] = 'optgroup : ' . $label;
			} elseif ( isset( $option['optgroup'] ) && 'end' === $option['optgroup'] ) {
				$lines[] = 'optgroup-close';
			} else {
				$lines[]  = ( '' !== $label && $label !== $value ) ? $value . ' : ' . $label : $value;
				$values[] = $value;
			}
		}

		return compact( 'lines', 'values' );
	}

	/**
	 * Get the property status options: the status terms, plus "Sold" when
	 * properties use the Houzez sold post status.
	 *
	 * @since 2.3.0
	 *
	 * @return array Option labels.
	 */
	private function get_status_options() {
		$options = $this->get_taxonomy_term_names( self::TAX_PROPERTY_STATUS );

		if ( $this->has_sold_properties() && ! in_array( $this->get_sold_label(), $options, true ) ) {
			$options[] = $this->get_sold_label();
		}

		return $options;
	}

	/**
	 * Get the term names of a Houzez taxonomy.
	 *
	 * @since 2.3.0
	 *
	 * @param string $taxonomy The taxonomy.
	 * @return array Term names.
	 */
	private function get_taxonomy_term_names( $taxonomy ) {
		$terms = $this->get_source_terms( $taxonomy );

		if ( empty( $terms ) ) {
			return array();
		}

		return $this->clean_term_names( wp_list_pluck( $terms, 'name' ) );
	}

	/**
	 * Decode and de-duplicate term names.
	 *
	 * WordPress stores "&" in term names as "&amp;"; GeoDirectory option values
	 * and location columns hold plain text.
	 *
	 * @since 2.3.0
	 *
	 * @param array $names Term names.
	 * @return array Clean term names.
	 */
	private function clean_term_names( $names ) {
		$clean = array();

		foreach ( (array) $names as $name ) {
			$name = trim( wp_specialchars_decode( (string) $name, ENT_QUOTES ) );

			if ( '' !== $name ) {
				$clean[] = $name;
			}
		}

		return array_values( array_unique( $clean ) );
	}

	/**
	 * Get the fields created with the Houzez fields builder.
	 *
	 * Houzez stores their definitions in the `houzez_fields_builder` table and
	 * their values in post meta keyed `fave_{field_id}`; checkbox lists and
	 * multi selects keep one meta row per selected value.
	 *
	 * @since 2.3.0
	 *
	 * @param array $taken_keys GD field keys already used by the built-in fields.
	 * @return array Field definitions.
	 */
	private function get_builder_fields( $taken_keys = array() ) {
		if ( null !== $this->builder_fields_cache ) {
			return $this->builder_fields_cache;
		}

		global $wpdb;

		$this->builder_fields_cache = array();

		$table = $wpdb->prefix . self::FIELDS_BUILDER_TABLE;

		if ( $table !== $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) ) {
			return $this->builder_fields_cache;
		}

		$rows = $wpdb->get_results( "SELECT id, label, field_id, type, fvalues, is_search, placeholder FROM {$table} ORDER BY id ASC", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( empty( $rows ) ) {
			return $this->builder_fields_cache;
		}

		$type_map = array(
			'text'          => 'text',
			'number'        => 'text',
			'url'           => 'url',
			'textarea'      => 'textarea',
			'select'        => 'select',
			'multiselect'   => 'multiselect',
			'checkbox_list' => 'multiselect',
			'radio'         => 'radio',
		);

		// GD detail table columns and core keys a builder field must not overwrite.
		$reserved = array( 'id', 'post_id', 'post_title', 'post_content', 'content', 'default_category', 'featured_image', 'overall_rating', 'rating_count', 'ratings', 'post_tags', 'post_category', 'submit_ip', 'marker_json', 'location_id', 'locations', 'post_dummy', '_search_title', 'street2', 'expire_date', 'package_id', 'claimed', 'post_badge', 'video' );

		foreach ( $rows as $row ) {
			$source_id = isset( $row['field_id'] ) ? (string) $row['field_id'] : '';
			$type      = isset( $row['type'] ) ? (string) $row['type'] : 'text';
			$label     = isset( $row['label'] ) ? wp_strip_all_tags( (string) $row['label'] ) : '';

			if ( '' === $source_id || '' === $label ) {
				continue;
			}

			$field_key = substr( str_replace( '-', '_', sanitize_key( $source_id ) ), 0, 50 );

			if ( '' === trim( $field_key, '_' ) ) {
				$field_key = 'houzez_cf_' . absint( $row['id'] );
			} elseif ( is_numeric( substr( $field_key, 0, 1 ) ) ) {
				$field_key = 'cf' . $field_key;
			}

			if ( in_array( $field_key, $taken_keys, true ) || in_array( $field_key, $reserved, true ) || $this->should_skip_field( $field_key ) ) {
				$field_key = substr( 'houzez_' . $field_key, 0, 50 );
			}

			if ( in_array( $field_key, $taken_keys, true ) ) {
				continue;
			}

			$gd_type = isset( $type_map[ $type ] ) ? $type_map[ $type ] : 'text';

			$field = array(
				'type'         => $gd_type,
				'field_key'    => $field_key,
				'label'        => $label,
				/* translators: %s: field label */
				'description'  => sprintf( __( 'Imported from the Houzez field: %s', 'geodir-converter' ), $label ),
				'placeholder'  => isset( $row['placeholder'] ) ? wp_strip_all_tags( (string) $row['placeholder'] ) : '',
				'icon'         => 'fas fa-info-circle',
				'cat_filter'   => isset( $row['is_search'] ) && 'yes' === $row['is_search'] ? 1 : 0,
				'source'       => 'fave_' . $source_id,
				'format'       => 'builder',
				'multiple'     => in_array( $type, array( 'multiselect', 'checkbox_list' ), true ),
				'source_alias' => 'fave_' . preg_replace( '/[^A-Za-z0-9\-]/', '', str_replace( ' ', '-', $source_id ) ),
			);

			if ( 'number' === $type ) {
				$field['data_type']     = 'FLOAT';
				$field['decimal_point'] = 2;
				$field['format']        = 'builder_number';
			}

			if ( 'checkbox_list' === $type ) {
				$field['extra'] = array( 'multi_display_type' => 'checkbox' );
			}

			if ( in_array( $gd_type, array( 'select', 'multiselect', 'radio' ), true ) ) {
				$field['options'] = $this->get_builder_field_options( $type, isset( $row['fvalues'] ) ? $row['fvalues'] : '' );
			}

			$taken_keys[]                 = $field_key;
			$this->builder_fields_cache[] = $field;
		}

		return $this->builder_fields_cache;
	}

	/**
	 * Parse the options of a Houzez fields builder field.
	 *
	 * Selects store a serialized value => label array; checkbox lists and radios
	 * store a serialized comma separated string.
	 *
	 * @since 2.3.0
	 *
	 * @param string $type    The Houzez field type.
	 * @param string $fvalues The stored option values.
	 * @return array Option labels.
	 */
	private function get_builder_field_options( $type, $fvalues ) {
		$values = maybe_unserialize( $fvalues );

		if ( is_string( $values ) ) {
			$values = explode( ',', $values );
		}

		if ( ! is_array( $values ) ) {
			return array();
		}

		$options = array();
		foreach ( $values as $value ) {
			if ( is_scalar( $value ) && '' !== trim( (string) $value ) ) {
				$options[] = trim( wp_strip_all_tags( (string) $value ) );
			}
		}

		return array_values( array_unique( $options ) );
	}

	/**
	 * Create the GeoDirectory custom fields.
	 *
	 * Fields that already exist are left untouched, except that missing
	 * options are added to existing choice fields so imported values validate.
	 *
	 * @since 2.3.0
	 *
	 * @param array $task Task details.
	 * @return array|false Next task.
	 */
	public function task_import_fields( array $task ) {
		$this->log( __( 'Fields: Import started.', 'geodir-converter' ) );

		$post_type   = $this->get_import_post_type();
		$fields      = $this->get_custom_fields();
		$package_ids = $this->get_package_ids( $post_type );

		if ( empty( $fields ) ) {
			$this->log( __( 'Fields: No custom fields to import.', 'geodir-converter' ), 'warning' );
			return $this->next_task( $task );
		}

		$imported = 0;
		$updated  = 0;
		$skipped  = 0;
		$failed   = 0;

		foreach ( $fields as $field ) {
			if ( $this->should_skip_field( $field['field_key'] ) ) {
				++$skipped;
				continue;
			}

			$field_id = $this->field_exists( $field['field_key'], $post_type );

			if ( $field_id ) {
				if ( ! $this->is_test_mode() && $this->maybe_merge_field_options( $field, $post_type ) ) {
					++$updated;
				} else {
					++$skipped;
				}
				continue;
			}

			if ( $this->is_test_mode() ) {
				++$imported;
				continue;
			}

			$result = geodir_custom_field_save( $this->prepare_single_field( $field, $post_type, $package_ids ) );

			if ( $result && ! is_wp_error( $result ) ) {
				++$imported;
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
	 * Add missing options to an existing GeoDirectory choice field.
	 *
	 * @since 2.3.0
	 *
	 * @param array  $field     Field definition.
	 * @param string $post_type Post type.
	 * @return bool True if the field was updated.
	 */
	private function maybe_merge_field_options( $field, $post_type ) {
		if ( empty( $field['options'] ) || ! in_array( $field['type'], array( 'select', 'multiselect', 'radio' ), true ) ) {
			return false;
		}

		$existing = geodir_get_field_infoby( 'htmlvar_name', $field['field_key'], $post_type );

		if ( empty( $existing ) || ! is_array( $existing ) || ! in_array( $existing['field_type'], array( 'select', 'multiselect', 'radio' ), true ) ) {
			return false;
		}

		$current = $this->parse_option_values( $existing['option_values'] );
		$missing = array_diff_key( $this->get_option_lines( $field ), array_flip( $current['values'] ) );

		if ( empty( $missing ) ) {
			return false;
		}

		$existing['field_id']      = (int) $existing['id'];
		$existing['option_values'] = $this->format_option_values( array_merge( $current['lines'], array_values( $missing ) ) );
		$existing['show_on_pkg']   = isset( $existing['packages'] ) ? $existing['packages'] : '';
		$existing['extra']         = ! empty( $existing['extra_fields'] ) ? maybe_unserialize( $existing['extra_fields'] ) : '';

		$result = geodir_custom_field_save( $existing );

		return $result && ! is_wp_error( $result );
	}

	/**
	 * Convert a field definition to GeoDirectory custom field data.
	 *
	 * @since 2.3.0
	 *
	 * @param array  $field       Field definition.
	 * @param string $post_type   Post type.
	 * @param array  $package_ids Package IDs.
	 * @return array GeoDirectory field data.
	 */
	private function prepare_single_field( $field, $post_type, $package_ids = array() ) {
		$field_type = isset( $field['type'] ) ? $field['type'] : 'text';

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
			'is_default'        => '0',
			'for_admin_use'     => ! empty( $field['only_for_admin'] ) ? 1 : 0,
			'is_required'       => 0,
			'show_in'           => isset( $field['show_in'] ) ? $field['show_in'] : '[detail]',
			'show_on_pkg'       => $package_ids,
			'clabels'           => $field['label'],
			'option_values'     => ! empty( $field['options'] ) ? $this->format_option_values( array_values( $this->get_option_lines( $field ) ) ) : '',
			'field_icon'        => isset( $field['icon'] ) ? $field['icon'] : 'fas fa-info-circle',
			'css_class'         => isset( $field['css_class'] ) ? $field['css_class'] : '',
			'cat_sort'          => ! empty( $field['cat_sort'] ) ? 1 : 0,
			'cat_filter'        => ! empty( $field['cat_filter'] ) ? 1 : 0,
		);

		if ( isset( $field['decimal_point'] ) ) {
			$gd_field['decimal_point'] = absint( $field['decimal_point'] );
		}

		if ( ! empty( $field['extra'] ) ) {
			$gd_field['extra'] = $field['extra'];
		}

		return $gd_field;
	}

	/**
	 * Get the database data type for a GeoDirectory field type.
	 *
	 * @since 2.3.0
	 *
	 * @param string $field_type Field type.
	 * @return string Data type.
	 */
	private function map_data_type( $field_type ) {
		$type_map = array(
			'text'        => 'VARCHAR',
			'email'       => 'VARCHAR',
			'phone'       => 'VARCHAR',
			'url'         => 'TEXT',
			'textarea'    => 'TEXT',
			'html'        => 'TEXT',
			'file'        => 'TEXT',
			'checkbox'    => 'TINYINT',
			'radio'       => 'VARCHAR',
			'select'      => 'VARCHAR',
			'multiselect' => 'VARCHAR',
		);

		return isset( $type_map[ $field_type ] ) ? $type_map[ $field_type ] : 'VARCHAR';
	}

	/**
	 * Queue properties for import in batches.
	 *
	 * @since 2.3.0
	 *
	 * @param array $task The task to import.
	 * @return array|false Updated task or next task.
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
			$this->log( __( 'Starting properties parsing process...', 'geodir-converter' ) );
		}

		if ( 0 === $total_listings ) {
			$this->log( __( 'No properties found for parsing. Skipping process.', 'geodir-converter' ), 'warning' );
			return $this->next_task( $task, true );
		}

		$status_placeholders = implode( ',', array_fill( 0, count( $this->post_statuses ), '%s' ) );

		$listings = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_title
				FROM {$wpdb->posts}
				WHERE post_type = %s
				AND post_status IN ({$status_placeholders})
				ORDER BY ID ASC
				LIMIT %d OFFSET %d",
				array_merge(
					array( self::POST_TYPE_PROPERTY ),
					$this->post_statuses,
					array( $batch_size, $offset )
				)
			)
		);

		if ( empty( $listings ) ) {
			$this->log( __( 'Parsing completed. No more properties found.', 'geodir-converter' ) );
			return $this->next_task( $task, true );
		}

		$import_tasks = array();
		foreach ( array_chunk( $listings, self::BATCH_SIZE_IMPORT ) as $batch ) {
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
	 * Import a batch of queued properties.
	 *
	 * Also handles retried items, which carry only the source ID.
	 *
	 * @since 2.3.0
	 *
	 * @param array $task The task to import.
	 * @return bool Always false, so the queue drops the completed task.
	 */
	public function task_import_listings( $task ) {
		$listings = isset( $task['listings'] ) && ! empty( $task['listings'] ) ? (array) $task['listings'] : array();

		if ( empty( $listings ) && ! empty( $task['source_id'] ) ) {
			$listings = array(
				(object) array(
					'ID'         => absint( $task['source_id'] ),
					'post_title' => get_the_title( absint( $task['source_id'] ) ),
				),
			);
		}

		return $this->import_queued_items(
			$listings,
			function ( $listing ) {
				return $this->import_single_listing( $listing );
			},
			array(
				'item_type'      => 'property',
				'label_callback' => function ( $listing ) {
					return $this->get_item_field( $listing, 'post_title' ) . ' (#' . (int) $this->get_item_field( $listing, 'ID' ) . ')';
				},
			)
		);
	}

	/**
	 * Convert a single Houzez property to a GeoDirectory listing.
	 *
	 * @since 2.3.0
	 *
	 * @param object|array $listing The queued property.
	 * @return int Import status.
	 */
	private function import_single_listing( $listing ) {
		$post = get_post( (int) $this->get_item_field( $listing, 'ID' ) );

		if ( ! $post || self::POST_TYPE_PROPERTY !== $post->post_type ) {
			return self::IMPORT_STATUS_FAILED;
		}

		$post_type  = $this->get_import_post_type();
		$gd_post_id = ! $this->is_test_mode() ? $this->get_gd_listing_id( $post->ID, $this->importer_id . '_id', $post_type ) : false;
		$is_update  = ! empty( $gd_post_id );
		$post_meta  = $this->get_post_meta( $post->ID );

		// Taxonomies.
		$categories    = $this->get_listings_terms( $post->ID, self::TAX_PROPERTY_TYPE, $post_type . 'category' );
		$tags          = $this->get_term_names( $post->ID, self::TAX_PROPERTY_LABEL );
		$statuses      = $this->get_term_names( $post->ID, self::TAX_PROPERTY_STATUS );
		$feature_names = $this->get_term_names( $post->ID, self::TAX_PROPERTY_FEATURE );

		// Location.
		$location = $this->get_location( $post, $post_meta );

		// Post status.
		$post_status = $this->map_post_status( $post->post_status );

		// Keep the sold state, which Houzez stores as a post status, in the status field.
		if ( self::STATUS_SOLD === $post->post_status && ! in_array( $this->get_sold_label(), $statuses, true ) ) {
			$statuses[] = $this->get_sold_label();
		}

		$listing_data = array(
			// Standard WP fields.
			'post_author'              => $post->post_author ? $post->post_author : $this->get_import_setting( 'wp_author_id', get_current_user_id() ),
			'post_title'               => $post->post_title,
			'post_content'             => $post->post_content ? $post->post_content : '',
			'post_excerpt'             => $post->post_excerpt ? $post->post_excerpt : '',
			'post_status'              => $post_status,
			'post_type'                => $post_type,
			'comment_status'           => 'open',
			'ping_status'              => $post->ping_status,
			'post_name'                => $post->post_name ? $post->post_name : 'property-' . $post->ID,
			'post_date_gmt'            => $post->post_date_gmt,
			'post_date'                => $post->post_date,
			'post_modified_gmt'        => $post->post_modified_gmt,
			'post_modified'            => $post->post_modified,
			'tax_input'                => array(
				$post_type . 'category' => $categories,
				$post_type . '_tags'    => $tags,
			),

			// GD fields.
			'default_category'         => ! empty( $categories ) ? $categories[0] : 0,

			// Location.
			'street'                   => $location['street'],
			'city'                     => $location['city'],
			'region'                   => $location['region'],
			'country'                  => $location['country'],
			'zip'                      => $location['zip'],
			'latitude'                 => $location['latitude'],
			'longitude'                => $location['longitude'],

			// Houzez fields.
			$this->importer_id . '_id' => $post->ID,
			'property_status'          => $this->format_multiselect_values( $statuses ),
			'property_features'        => $this->format_multiselect_values( $feature_names ),
			'featured'                 => ! empty( $post_meta[ self::META_FEATURED ] ) && is_scalar( $post_meta[ self::META_FEATURED ] ) && '0' !== (string) $post_meta[ self::META_FEATURED ] ? 1 : 0,
		);

		if ( '' !== (string) $location['mapzoom'] ) {
			$listing_data['mapzoom'] = $location['mapzoom'];
		}

		// Core GD does not save neighbourhoods; the Location Manager does.
		if ( '' !== $location['neighbourhood'] && class_exists( 'GeoDir_Location_Neighbourhood' ) && \GeoDir_Location_Neighbourhood::is_active() ) {
			$listing_data['neighbourhood'] = $location['neighbourhood'];
		}

		// Manual expiry date, kept when the Pricing Manager provides the expire_date field.
		if ( class_exists( 'GeoDir_Pricing_Package' ) && ! empty( $post_meta[ self::META_MANUAL_EXPIRE ] ) && ! empty( $post_meta[ self::META_EXPIRATION_DATE ] ) && is_numeric( $post_meta[ self::META_EXPIRATION_DATE ] ) ) {
			$listing_data['expire_date'] = gmdate( 'Y-m-d', (int) $post_meta[ self::META_EXPIRATION_DATE ] );
		}

		// Simple meta mapped fields and fields builder values.
		$field_keys = array();
		foreach ( $this->get_custom_fields() as $field ) {
			$field_keys[] = $field['field_key'];

			if ( 'special' === $field['format'] || empty( $field['source'] ) ) {
				continue;
			}

			$value = $this->get_field_value( $post->ID, $post_meta, $field );

			if ( null !== $value ) {
				$listing_data[ $field['field_key'] ] = $value;
			}
		}

		// Structured data.
		$show_placeholder = ! empty( $post_meta[ self::META_SHOW_PLACEHOLDER ] ) && is_scalar( $post_meta[ self::META_SHOW_PLACEHOLDER ] ) && '0' !== (string) $post_meta[ self::META_SHOW_PLACEHOLDER ];

		$extra_data = array(
			'price_placeholder'  => $show_placeholder && isset( $post_meta[ self::META_PRICE_PLACEHOLDER ] ) && is_scalar( $post_meta[ self::META_PRICE_PLACEHOLDER ] ) ? sanitize_text_field( (string) $post_meta[ self::META_PRICE_PLACEHOLDER ] ) : '',
			'floor_plans'        => $this->format_floor_plans( isset( $post_meta[ self::META_FLOOR_PLANS ] ) ? $post_meta[ self::META_FLOOR_PLANS ] : array() ),
			'additional_details' => $this->format_additional_details( isset( $post_meta[ self::META_ADDITIONAL ] ) ? $post_meta[ self::META_ADDITIONAL ] : array() ),
			'sub_listings'       => $this->format_sub_listings( isset( $post_meta[ self::META_MULTI_UNITS ] ) ? $post_meta[ self::META_MULTI_UNITS ] : array() ),
		);

		$extra_data = array_merge( $extra_data, $this->get_contact_data( $post->ID, $post_meta ) );

		foreach ( $extra_data as $key => $value ) {
			if ( in_array( $key, $field_keys, true ) ) {
				$listing_data[ $key ] = $value;
			}
		}

		if ( $this->is_test_mode() ) {
			return self::IMPORT_STATUS_SUCCESS;
		}

		$has_documents = in_array( 'property_documents', $field_keys, true );

		if ( $is_update ) {
			GeoDir_Media::delete_files( (int) $gd_post_id, 'post_images' );

			if ( $has_documents ) {
				GeoDir_Media::delete_files( (int) $gd_post_id, 'property_documents' );
			}
		}

		$listing_data['post_images'] = $this->get_post_images( $post->ID );

		if ( $has_documents ) {
			$listing_data['property_documents'] = $this->get_documents( $post->ID );
		}

		if ( $is_update ) {
			$listing_data['ID'] = absint( $gd_post_id );
			$gd_post_id         = wp_update_post( $listing_data, true );
		} else {
			$gd_post_id = wp_insert_post( $listing_data, true );
		}

		if ( is_wp_error( $gd_post_id ) ) {
			$this->log( $gd_post_id->get_error_message(), 'error' );
			return self::IMPORT_STATUS_FAILED;
		}

		if ( ! $gd_post_id ) {
			return self::IMPORT_STATUS_FAILED;
		}

		$this->import_reviews( $post->ID, (int) $gd_post_id );

		return $is_update ? self::IMPORT_STATUS_UPDATED : self::IMPORT_STATUS_SUCCESS;
	}

	/**
	 * Map a Houzez post status to a GeoDirectory post status.
	 *
	 * Expired properties become `gd-expired` when the Pricing Manager, which
	 * registers that status, is active. Sold properties stay public, as they
	 * are in Houzez; their sold state is kept in the property status field.
	 *
	 * @since 2.3.0
	 *
	 * @param string $status Houzez post status.
	 * @return string GD post status.
	 */
	private function map_post_status( $status ) {
		switch ( $status ) {
			case 'expired':
				return class_exists( 'GeoDir_Pricing_Package' ) ? 'gd-expired' : 'draft';
			case 'on_hold':
			case 'disapproved':
				return 'draft';
			case self::STATUS_SOLD:
				return 'publish';
			default:
				return $status;
		}
	}

	/**
	 * Get the label Houzez shows for its sold post status.
	 *
	 * @since 2.3.0
	 *
	 * @return string Sold label.
	 */
	private function get_sold_label() {
		return __( 'Sold', 'geodir-converter' );
	}

	/**
	 * Check if any property uses the Houzez sold post status.
	 *
	 * @since 2.3.0
	 *
	 * @return bool True if at least one property is sold.
	 */
	private function has_sold_properties() {
		global $wpdb;

		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s LIMIT 1",
				self::POST_TYPE_PROPERTY,
				self::STATUS_SOLD
			)
		);
	}

	/**
	 * Read the value of a meta mapped field.
	 *
	 * @since 2.3.0
	 *
	 * @param int   $post_id   Source post ID.
	 * @param array $post_meta Source post meta.
	 * @param array $field     Field definition.
	 * @return string|int|float|null The value, or null when the property has none.
	 */
	private function get_field_value( $post_id, $post_meta, $field ) {
		$source = $field['source'];

		if ( in_array( $field['format'], array( 'builder', 'builder_number' ), true ) && ! isset( $post_meta[ $source ] ) && ! empty( $field['source_alias'] ) ) {
			$source = $field['source_alias'];
		}

		if ( ! empty( $field['multiple'] ) ) {
			$values = $this->format_multiselect_values( array_filter( (array) get_post_meta( $post_id, $source, false ), 'is_scalar' ) );

			return '' !== $values ? $values : null;
		}

		if ( ! isset( $post_meta[ $source ] ) || ! is_scalar( $post_meta[ $source ] ) ) {
			return null;
		}

		$value = trim( (string) $post_meta[ $source ] );

		switch ( $field['format'] ) {
			case 'number':
			case 'builder_number':
				// GD turns an empty INT into NULL but saves an empty FLOAT as 0.00, which shows as a price.
				$number = $this->parse_number( $value );
				return '' === $number ? null : $number;
			case 'int':
				$number = $this->parse_number( $value );
				return '' === $number ? '' : (int) round( (float) $number );
			default:
				return $value;
		}
	}

	/**
	 * Build a GeoDirectory multiselect value from option labels.
	 *
	 * @since 2.3.0
	 *
	 * @param array $labels Option labels.
	 * @return string Comma separated option values.
	 */
	private function format_multiselect_values( $labels ) {
		$values = array();

		foreach ( (array) $labels as $label ) {
			$value = $this->get_multiselect_value( $label );

			if ( '' !== $value ) {
				$values[] = $value;
			}
		}

		return implode( ',', array_unique( $values ) );
	}

	/**
	 * Extract a number from a free text value.
	 *
	 * @since 2.3.0
	 *
	 * @param string $value The raw value.
	 * @return string The number, or an empty string when none is found.
	 */
	private function parse_number( $value ) {
		// Houzez stores plain numbers (its price display runs doubleval() on them); drop stray thousands commas.
		$value = str_replace( array( ',', ' ' ), '', (string) $value );

		if ( preg_match( '/-?\d+(\.\d+)?/', $value, $matches ) ) {
			return $matches[0];
		}

		return '';
	}

	/**
	 * Build the GeoDirectory location of a property.
	 *
	 * Houzez keeps the city, area, state and country in taxonomies and the
	 * coordinates in post meta. Coordinates are reverse geocoded so the values
	 * match GeoDirectory's location format.
	 *
	 * @since 2.3.0
	 *
	 * @param \WP_Post $post      Source post.
	 * @param array    $post_meta Source post meta.
	 * @return array Location data.
	 */
	private function get_location( $post, $post_meta ) {
		$city    = $this->get_term_names( $post->ID, self::TAX_PROPERTY_CITY );
		$state   = $this->get_term_names( $post->ID, self::TAX_PROPERTY_STATE );
		$country = $this->get_term_names( $post->ID, self::TAX_PROPERTY_COUNTRY );
		$area    = $this->get_term_names( $post->ID, self::TAX_PROPERTY_AREA );
		$zip     = isset( $post_meta[ self::META_ZIP ] ) && is_scalar( $post_meta[ self::META_ZIP ] ) ? trim( (string) $post_meta[ self::META_ZIP ] ) : '';

		$location = array(
			'street'        => '',
			'city'          => ! empty( $city ) ? $city[0] : '',
			'region'        => ! empty( $state ) ? $state[0] : '',
			'country'       => ! empty( $country ) ? $country[0] : '',
			'zip'           => $zip,
			'neighbourhood' => ! empty( $area ) ? $area[0] : '',
			'latitude'      => '',
			'longitude'     => '',
			'mapzoom'       => '',
		);

		// Older imports keep only the ISO2 country code in post meta.
		if ( '' === $location['country'] && ! empty( $post_meta[ self::META_COUNTRY_CODE ] ) && is_scalar( $post_meta[ self::META_COUNTRY_CODE ] ) ) {
			$location['country'] = $this->get_country_name( (string) $post_meta[ self::META_COUNTRY_CODE ] );
		}

		// Coordinates: dedicated meta first, then the map field ("lat,lng,zoom").
		$latitude  = isset( $post_meta[ self::META_LAT ] ) && is_scalar( $post_meta[ self::META_LAT ] ) ? trim( (string) $post_meta[ self::META_LAT ] ) : '';
		$longitude = isset( $post_meta[ self::META_LNG ] ) && is_scalar( $post_meta[ self::META_LNG ] ) ? trim( (string) $post_meta[ self::META_LNG ] ) : '';
		$map_parts = ! empty( $post_meta[ self::META_LOCATION ] ) && is_scalar( $post_meta[ self::META_LOCATION ] ) ? array_map( 'trim', explode( ',', (string) $post_meta[ self::META_LOCATION ] ) ) : array();

		if ( ( ! is_numeric( $latitude ) || ! is_numeric( $longitude ) ) && count( $map_parts ) >= 2 ) {
			$latitude  = $map_parts[0];
			$longitude = $map_parts[1];
		}

		if ( isset( $map_parts[2] ) && is_numeric( $map_parts[2] ) ) {
			$location['mapzoom'] = absint( $map_parts[2] );
		}

		$has_coordinates = is_numeric( $latitude ) && is_numeric( $longitude ) && ( 0.0 !== (float) $latitude || 0.0 !== (float) $longitude );

		if ( $has_coordinates ) {
			$location['latitude']  = $latitude;
			$location['longitude'] = $longitude;

			// The Houzez terms win; reverse geocoding only fills the parts Houzez lacks.
			// Test mode writes nothing, including the geocoder's cache, so no lookup is made.
			if ( ! $this->is_test_mode() && ( '' === $location['city'] || '' === $location['region'] || '' === $location['country'] ) ) {
				$geocoded = $this->geocode_location( $latitude, $longitude, array(), $post->ID );

				foreach ( array( 'city', 'region', 'country', 'zip' ) as $key ) {
					if ( '' === $location[ $key ] && ! empty( $geocoded[ $key ] ) ) {
						$location[ $key ] = $geocoded[ $key ];
					}
				}
			}
		} else {
			// No coordinates: place the property on the default location.
			$default_location = $this->get_default_location();

			$location['latitude']  = $default_location['latitude'];
			$location['longitude'] = $default_location['longitude'];

			if ( '' === $location['city'] && '' === $location['region'] && '' === $location['country'] ) {
				$location['city']    = $default_location['city'];
				$location['region']  = $default_location['region'];
				$location['country'] = $default_location['country'];
			}
		}

		$street = isset( $post_meta[ self::META_ADDRESS ] ) && is_scalar( $post_meta[ self::META_ADDRESS ] ) ? trim( (string) $post_meta[ self::META_ADDRESS ] ) : '';

		if ( '' === $street && isset( $post_meta[ self::META_MAP_ADDRESS ] ) && is_scalar( $post_meta[ self::META_MAP_ADDRESS ] ) ) {
			$street = trim( (string) $post_meta[ self::META_MAP_ADDRESS ] );
		}

		$location['street'] = $street;

		return $location;
	}

	/**
	 * Get a country name from an ISO2 code, or a name that is already a name.
	 *
	 * @since 2.3.0
	 *
	 * @param string $code The country code or name.
	 * @return string Country name, or an empty string when unknown.
	 */
	private function get_country_name( $code ) {
		$code = trim( $code );

		if ( '' === $code ) {
			return '';
		}

		if ( 2 !== strlen( $code ) ) {
			return sanitize_text_field( $code );
		}

		if ( function_exists( 'wp_country_database' ) ) {
			$countries = wp_country_database()->get_countries(
				array(
					'where' => array( 'alpha2Code' => strtoupper( $code ) ),
					'limit' => 1,
				)
			);

			if ( ! empty( $countries[0]->name ) ) {
				return $countries[0]->name;
			}
		}

		return '';
	}

	/**
	 * Get the terms a property is assigned to in a Houzez taxonomy.
	 *
	 * @since 2.3.0
	 *
	 * @param int    $post_id  Source post ID.
	 * @param string $taxonomy Source taxonomy.
	 * @return array Term rows with term_id and name, parents first.
	 */
	private function get_post_terms( $post_id, $taxonomy ) {
		global $wpdb;

		$terms = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT t.term_id, t.name
				FROM {$wpdb->terms} t
				INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
				INNER JOIN {$wpdb->term_relationships} tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
				WHERE tr.object_id = %d AND tt.taxonomy = %s
				ORDER BY tt.parent ASC, t.name ASC",
				$post_id,
				$taxonomy
			)
		);

		return is_array( $terms ) ? $terms : array();
	}

	/**
	 * Get the names of the terms a property is assigned to.
	 *
	 * @since 2.3.0
	 *
	 * @param int    $post_id  Source post ID.
	 * @param string $taxonomy Source taxonomy.
	 * @return array Term names.
	 */
	private function get_term_names( $post_id, $taxonomy ) {
		return $this->clean_term_names( wp_list_pluck( $this->get_post_terms( $post_id, $taxonomy ), 'name' ) );
	}

	/**
	 * Get the GeoDirectory term IDs for a property's Houzez terms.
	 *
	 * @since 2.3.0
	 *
	 * @param int    $post_id     Source post ID.
	 * @param string $taxonomy    Source taxonomy.
	 * @param string $gd_taxonomy Target GeoDirectory taxonomy.
	 * @return array GD term IDs.
	 */
	private function get_listings_terms( $post_id, $taxonomy, $gd_taxonomy ) {
		global $wpdb;

		$source_ids = array_map( 'absint', wp_list_pluck( $this->get_post_terms( $post_id, $taxonomy ), 'term_id' ) );

		if ( empty( $source_ids ) ) {
			return array();
		}

		$placeholders = implode( ',', array_fill( 0, count( $source_ids ), '%d' ) );

		// Only accept equivalents that live in the target taxonomy.
		$gd_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT tt.term_id
				FROM {$wpdb->termmeta} tm
				INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = CAST( tm.meta_value AS UNSIGNED ) AND tt.taxonomy = %s
				WHERE tm.meta_key = 'gd_equivalent' AND tm.term_id IN ({$placeholders})",
				array_merge( array( $gd_taxonomy ), $source_ids )
			)
		);

		return array_values( array_unique( array_map( 'absint', (array) $gd_ids ) ) );
	}

	/**
	 * Get the property gallery formatted for GeoDirectory.
	 *
	 * The featured image comes first, followed by the gallery images, which
	 * Houzez stores as one `fave_property_images` meta row per attachment.
	 *
	 * @since 2.3.0
	 *
	 * @param int $post_id Source post ID.
	 * @return string Formatted images string.
	 */
	private function get_post_images( $post_id ) {
		$attachment_ids = array();
		$thumbnail_id   = (int) get_post_thumbnail_id( $post_id );

		if ( $thumbnail_id ) {
			$attachment_ids[] = $thumbnail_id;
		}

		foreach ( (array) get_post_meta( $post_id, self::META_IMAGES, false ) as $value ) {
			$ids = is_array( $value ) ? $value : explode( ',', (string) $value );

			foreach ( $ids as $id ) {
				if ( is_numeric( $id ) && (int) $id > 0 ) {
					$attachment_ids[] = (int) $id;
				}
			}
		}

		$attachment_ids = array_values( array_unique( $attachment_ids ) );
		$images         = array();

		foreach ( $attachment_ids as $index => $attachment_id ) {
			$images[] = array(
				'id'      => $attachment_id,
				'caption' => get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
				'weight'  => $index,
			);
		}

		return $this->format_images_data( $images );
	}

	/**
	 * Get the property attachments formatted for a GeoDirectory file field.
	 *
	 * @since 2.3.0
	 *
	 * @param int $post_id Source post ID.
	 * @return string Formatted files string.
	 */
	private function get_documents( $post_id ) {
		$files = array();

		foreach ( (array) get_post_meta( $post_id, self::META_ATTACHMENTS, false ) as $value ) {
			$ids = is_array( $value ) ? $value : explode( ',', (string) $value );

			foreach ( $ids as $attachment_id ) {
				$attachment_id = absint( $attachment_id );
				$url           = $attachment_id ? wp_get_attachment_url( $attachment_id ) : '';

				if ( $url ) {
					// "|" and "::" are the GD file string delimiters.
					$title = str_replace( array( '|', '::' ), ' ', sanitize_text_field( get_the_title( $attachment_id ) ) );

					$files[ $attachment_id ] = sprintf( '%s||%s|', esc_url_raw( $url ), $title );
				}
			}
		}

		return implode( '::', $files );
	}

	/**
	 * Render Houzez floor plans as HTML.
	 *
	 * @since 2.3.0
	 *
	 * @param mixed $plans The `floor_plans` meta value.
	 * @return string HTML.
	 */
	private function format_floor_plans( $plans ) {
		if ( empty( $plans ) || ! is_array( $plans ) ) {
			return '';
		}

		$details = array(
			'fave_plan_rooms'     => __( 'Bedrooms', 'geodir-converter' ),
			'fave_plan_bathrooms' => __( 'Bathrooms', 'geodir-converter' ),
			'fave_plan_size'      => __( 'Size', 'geodir-converter' ),
			'fave_plan_price'     => __( 'Price', 'geodir-converter' ),
		);

		$html = '';

		foreach ( $plans as $plan ) {
			if ( ! is_array( $plan ) || empty( array_filter( $plan ) ) ) {
				continue;
			}

			$html .= '<div>';

			if ( ! empty( $plan['fave_plan_title'] ) ) {
				$html .= '<h4>' . esc_html( $plan['fave_plan_title'] ) . '</h4>';
			}

			$items = '';
			foreach ( $details as $key => $label ) {
				if ( empty( $plan[ $key ] ) || ! is_scalar( $plan[ $key ] ) ) {
					continue;
				}

				$value = (string) $plan[ $key ];

				if ( 'fave_plan_price' === $key && ! empty( $plan['fave_plan_price_postfix'] ) && is_scalar( $plan['fave_plan_price_postfix'] ) ) {
					$value .= ' ' . $plan['fave_plan_price_postfix'];
				}

				$items .= '<li><strong>' . esc_html( $label ) . ':</strong> ' . esc_html( $value ) . '</li>';
			}

			if ( $items ) {
				$html .= '<ul>' . $items . '</ul>';
			}

			if ( ! empty( $plan['fave_plan_image'] ) && is_string( $plan['fave_plan_image'] ) ) {
				$image_url = is_numeric( $plan['fave_plan_image'] ) ? wp_get_attachment_url( (int) $plan['fave_plan_image'] ) : $plan['fave_plan_image'];

				if ( $image_url ) {
					$html .= '<p><img src="' . esc_url( $image_url ) . '" alt="' . esc_attr( ! empty( $plan['fave_plan_title'] ) ? $plan['fave_plan_title'] : '' ) . '" /></p>';
				}
			}

			if ( ! empty( $plan['fave_plan_description'] ) && is_string( $plan['fave_plan_description'] ) ) {
				$html .= wpautop( wp_kses_post( $plan['fave_plan_description'] ) );
			}

			$html .= '</div>';
		}

		return $html;
	}

	/**
	 * Render Houzez additional features as an HTML list.
	 *
	 * @since 2.3.0
	 *
	 * @param mixed $features The `additional_features` meta value.
	 * @return string HTML.
	 */
	private function format_additional_details( $features ) {
		if ( empty( $features ) || ! is_array( $features ) ) {
			return '';
		}

		$items = '';

		foreach ( $features as $feature ) {
			$title = isset( $feature['fave_additional_feature_title'] ) && is_scalar( $feature['fave_additional_feature_title'] ) ? trim( (string) $feature['fave_additional_feature_title'] ) : '';
			$value = isset( $feature['fave_additional_feature_value'] ) && is_scalar( $feature['fave_additional_feature_value'] ) ? trim( (string) $feature['fave_additional_feature_value'] ) : '';

			if ( '' === $title && '' === $value ) {
				continue;
			}

			$items .= '<li>' . ( '' !== $title ? '<strong>' . esc_html( $title ) . ':</strong> ' : '' ) . esc_html( $value ) . '</li>';
		}

		return $items ? '<ul>' . $items . '</ul>' : '';
	}

	/**
	 * Render Houzez multi units (sub listings) as an HTML table.
	 *
	 * @since 2.3.0
	 *
	 * @param mixed $units The `fave_multi_units` meta value.
	 * @return string HTML.
	 */
	private function format_sub_listings( $units ) {
		if ( empty( $units ) || ! is_array( $units ) ) {
			return '';
		}

		$columns = array(
			'fave_mu_title'             => __( 'Title', 'geodir-converter' ),
			'fave_mu_type'              => __( 'Type', 'geodir-converter' ),
			'fave_mu_price'             => __( 'Price', 'geodir-converter' ),
			'fave_mu_beds'              => __( 'Bedrooms', 'geodir-converter' ),
			'fave_mu_baths'             => __( 'Bathrooms', 'geodir-converter' ),
			'fave_mu_size'              => __( 'Size', 'geodir-converter' ),
			'fave_mu_availability_date' => __( 'Availability', 'geodir-converter' ),
		);

		$rows = '';

		foreach ( $units as $unit ) {
			if ( ! is_array( $unit ) || empty( array_filter( $unit ) ) ) {
				continue;
			}

			$rows .= '<tr>';

			foreach ( array_keys( $columns ) as $key ) {
				$value = isset( $unit[ $key ] ) && is_scalar( $unit[ $key ] ) ? (string) $unit[ $key ] : '';

				if ( 'fave_mu_price' === $key && '' !== $value && ! empty( $unit['fave_mu_price_postfix'] ) && is_scalar( $unit['fave_mu_price_postfix'] ) ) {
					$value .= ' ' . $unit['fave_mu_price_postfix'];
				} elseif ( 'fave_mu_size' === $key && '' !== $value && ! empty( $unit['fave_mu_size_postfix'] ) && is_scalar( $unit['fave_mu_size_postfix'] ) ) {
					$value .= ' ' . $unit['fave_mu_size_postfix'];
				}

				$rows .= '<td>' . esc_html( $value ) . '</td>';
			}

			$rows .= '</tr>';
		}

		if ( '' === $rows ) {
			return '';
		}

		$head = '';
		foreach ( $columns as $label ) {
			$head .= '<th>' . esc_html( $label ) . '</th>';
		}

		return '<table class="table"><thead><tr>' . $head . '</tr></thead><tbody>' . $rows . '</tbody></table>';
	}

	/**
	 * Get the agent and agency contact details shown on a property.
	 *
	 * Follows the Houzez "agent display option": nothing for the author or
	 * hidden options (the listing keeps its original author), the agency for
	 * the agency option, and the agents plus their agency otherwise.
	 *
	 * @since 2.3.0
	 *
	 * @param int   $post_id   Source post ID.
	 * @param array $post_meta Source post meta.
	 * @return array Contact field values keyed by GD field key.
	 */
	private function get_contact_data( $post_id, $post_meta ) {
		$data = array(
			'agent_name'   => '',
			'agent_email'  => '',
			'agent_phone'  => '',
			'agent_mobile' => '',
			'agency_name'  => '',
			'agency_email' => '',
			'agency_phone' => '',
		);

		$display = isset( $post_meta[ self::META_AGENT_DISPLAY ] ) && is_scalar( $post_meta[ self::META_AGENT_DISPLAY ] ) ? (string) $post_meta[ self::META_AGENT_DISPLAY ] : '';

		if ( in_array( $display, array( 'author_info', 'none' ), true ) ) {
			return $data;
		}

		$agency_ids = $this->get_linked_ids( $post_id, self::META_AGENCY );

		if ( 'agency_info' !== $display ) {
			$agents = array();
			foreach ( $this->get_linked_ids( $post_id, self::META_AGENTS ) as $agent_id ) {
				$agent = $this->get_contact( $agent_id, 'agent' );

				if ( $agent ) {
					$agents[] = $agent;
				}
			}

			if ( ! empty( $agents ) ) {
				$data['agent_name']   = implode( ', ', wp_list_pluck( $agents, 'name' ) );
				$data['agent_email']  = $agents[0]['email'];
				$data['agent_phone']  = $agents[0]['phone'];
				$data['agent_mobile'] = $agents[0]['mobile'];

				if ( empty( $agency_ids ) && ! empty( $agents[0]['agency_id'] ) ) {
					$agency_ids = array( $agents[0]['agency_id'] );
				}
			}
		}

		if ( ! empty( $agency_ids ) ) {
			$agency = $this->get_contact( $agency_ids[0], 'agency' );

			if ( $agency ) {
				$data['agency_name']  = $agency['name'];
				$data['agency_email'] = $agency['email'];
				$data['agency_phone'] = $agency['phone'];
			}
		}

		return $data;
	}

	/**
	 * Get the post IDs stored in a multi-row relation meta key.
	 *
	 * @since 2.3.0
	 *
	 * @param int    $post_id  Source post ID.
	 * @param string $meta_key Meta key.
	 * @return array Positive post IDs.
	 */
	private function get_linked_ids( $post_id, $meta_key ) {
		$ids = array();

		foreach ( (array) get_post_meta( $post_id, $meta_key, false ) as $value ) {
			foreach ( (array) $value as $id ) {
				if ( is_numeric( $id ) && (int) $id > 0 ) {
					$ids[] = (int) $id;
				}
			}
		}

		return array_values( array_unique( $ids ) );
	}

	/**
	 * Get the contact details of a Houzez agent or agency.
	 *
	 * @since 2.3.0
	 *
	 * @param int    $contact_id Agent or agency post ID.
	 * @param string $type       Either 'agent' or 'agency'.
	 * @return array|false Contact details, or false if not found.
	 */
	private function get_contact( $contact_id, $type ) {
		$cache_key = $type . '_' . $contact_id;

		if ( isset( $this->contact_cache[ $cache_key ] ) ) {
			return $this->contact_cache[ $cache_key ];
		}

		$post_type = 'agency' === $type ? self::POST_TYPE_AGENCY : self::POST_TYPE_AGENT;
		$post      = get_post( $contact_id );

		if ( ! $post || $post_type !== $post->post_type ) {
			$this->contact_cache[ $cache_key ] = false;
			return false;
		}

		$prefix = 'agency' === $type ? 'fave_agency_' : 'fave_agent_';
		$phone  = 'agency' === $type ? get_post_meta( $contact_id, 'fave_agency_phone', true ) : get_post_meta( $contact_id, 'fave_agent_office_num', true );

		$this->contact_cache[ $cache_key ] = array(
			'name'      => $post->post_title,
			'email'     => sanitize_email( (string) get_post_meta( $contact_id, $prefix . 'email', true ) ),
			'phone'     => sanitize_text_field( (string) $phone ),
			'mobile'    => sanitize_text_field( (string) get_post_meta( $contact_id, $prefix . 'mobile', true ) ),
			'agency_id' => 'agent' === $type ? absint( get_post_meta( $contact_id, 'fave_agent_agencies', true ) ) : 0,
		);

		return $this->contact_cache[ $cache_key ];
	}

	/**
	 * Import the Houzez reviews of a property as GeoDirectory reviews.
	 *
	 * Houzez stores reviews as `houzez_reviews` posts linked through the
	 * `review_property_id` meta, with the 1-5 rating in `review_stars`.
	 *
	 * @since 2.3.0
	 *
	 * @param int $source_id  Source property ID.
	 * @param int $gd_post_id GeoDirectory listing ID.
	 * @return void
	 */
	private function import_reviews( $source_id, $gd_post_id ) {
		global $wpdb, $user_ID;

		if ( null === $this->has_reviews_cache ) {
			$this->has_reviews_cache = (bool) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s LIMIT 1",
					self::POST_TYPE_REVIEW
				)
			);
		}

		// Skip the per-property lookup on sites without reviews.
		if ( ! $this->has_reviews_cache ) {
			return;
		}

		$reviews = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID, p.post_content, p.post_author, p.post_date, p.post_date_gmt, p.post_status, stars.meta_value AS stars
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} link ON link.post_id = p.ID AND link.meta_key = 'review_property_id' AND link.meta_value = %s
				LEFT JOIN {$wpdb->postmeta} stars ON stars.post_id = p.ID AND stars.meta_key = 'review_stars'
				WHERE p.post_type = %s AND p.post_status IN ('publish', 'pending')
				ORDER BY p.ID ASC",
				(string) $source_id,
				self::POST_TYPE_REVIEW
			)
		);

		if ( empty( $reviews ) ) {
			return;
		}

		$imported = 0;

		foreach ( $reviews as $review ) {
			$comment_agent = 'geodir-converter-houzez-' . (int) $review->ID;

			$existing = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT comment_ID FROM {$wpdb->comments} WHERE comment_post_ID = %d AND comment_agent = %s LIMIT 1",
					$gd_post_id,
					$comment_agent
				)
			);

			if ( $existing ) {
				continue;
			}

			$user = $review->post_author ? get_userdata( (int) $review->post_author ) : false;

			// GD reviews have no title, so the Houzez review title is not imported.
			$comment_id = wp_insert_comment(
				array(
					'comment_post_ID'      => $gd_post_id,
					'user_id'              => $user ? (int) $user->ID : 0,
					'comment_author'       => $user ? $user->display_name : '',
					'comment_author_email' => $user ? $user->user_email : '',
					'comment_date'         => $review->post_date,
					'comment_date_gmt'     => $review->post_date_gmt,
					'comment_content'      => wp_kses_post( (string) $review->post_content ),
					'comment_agent'        => $comment_agent,
					'comment_approved'     => 'publish' === $review->post_status ? 1 : 0,
					// GD's own review form saves the core "comment" type.
					'comment_type'         => 'comment',
				)
			);

			if ( ! $comment_id ) {
				continue;
			}

			++$imported;

			$rating = absint( $review->stars );

			if ( $rating && class_exists( 'GeoDir_Comments' ) ) {
				// save_rating() records the global $user_ID as the reviewer.
				$current_user_id = $user_ID;
				$user_ID         = $user ? (int) $user->ID : 0; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

				$_REQUEST['geodir_overallrating'] = max( 1, min( 5, $rating ) );
				GeoDir_Comments::save_rating( $comment_id );
				unset( $_REQUEST['geodir_overallrating'] );

				$user_ID = $current_user_id; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			}
		}

		if ( $imported > 0 ) {
			wp_update_comment_count( $gd_post_id );

			$this->log(
				sprintf(
					/* translators: %1$d: number of reviews, %2$d: source property ID */
					__( 'Imported %1$d review(s) for property #%2$d.', 'geodir-converter' ),
					$imported,
					$source_id
				)
			);
		}
	}
}
