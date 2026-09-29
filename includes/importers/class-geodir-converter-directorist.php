<?php
/**
 * Directorist Converter Class.
 *
 * @since     2.1.3
 * @package   GeoDir_Converter
 */

namespace GeoDir_Converter\Importers;

use WP_Error;
use GeoDir_Media;
use GeoDir_Pricing_Package;
use GeoDir_Admin_Install;
use GeoDir_Admin_Import_Export;
use GeoDir_Converter\GeoDir_Converter_Utils;
use GeoDir_Converter\Abstracts\GeoDir_Converter_Importer;

defined( 'ABSPATH' ) || exit;

/**
 * Main converter class for importing from Directorist.
 *
 * @since 2.1.3
 */
class GeoDir_Converter_Directorist extends GeoDir_Converter_Importer {
	/**
	 * Action identifier for import directories.
	 *
	 * directories -> post_types
	 *
	 * @var string
	 */
	const ACTION_IMPORT_DIRECTORIES = 'import_directories';

	/**
	 * Post type identifier for listings.
	 *
	 * @var string
	 */
	private const POST_TYPE_LISTING = 'at_biz_dir';

	/**
	 * Taxonomy identifier for listing categories.
	 *
	 * @var string
	 */
	private const TAX_LISTING_CATEGORY = 'at_biz_dir-category';

	/**
	 * Taxonomy identifier for listing tags.
	 *
	 * @var string
	 */
	private const TAX_LISTING_TAG = 'at_biz_dir-tags';

	/**
	 * Listing location taxonomy (ATBDP_LOCATION, directorist/config.php:89-90).
	 *
	 * @var string
	 */
	private const TAX_LISTING_LOCATION = 'at_biz_dir-location';

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
	protected $importer_id = 'directorist';

	/**
	 * The import listing status ID.
	 *
	 * @var array
	 */
	protected $post_statuses = array( 'publish', 'expired', 'draft', 'pending', 'private', 'future' );

	/**
	 * Batch size for processing items.
	 *
	 * @var int
	 */
	private $batch_size = 50;

	/**
	 * Cached directory terms for the current request.
	 *
	 * @since 2.3.0
	 * @var array|null
	 */
	private $directories_cache = null;

	/**
	 * Cached directory form fields, keyed by directory ID and post type.
	 *
	 * @since 2.3.0
	 * @var array
	 */
	private $fields_cache = array();

	/**
	 * Cached category terms for the current request.
	 *
	 * @since 2.3.0
	 * @var array|null
	 */
	private $categories_cache = null;

	/**
	 * Cached tag terms for the current request.
	 *
	 * @since 2.3.0
	 * @var array|null
	 */
	private $tags_cache = null;

	/**
	 * Directorist price range values mapped to the GeoDirectory price_range options.
	 *
	 * Directorist renders skimming/moderate/economy/bellow_economy as 4/3/2/1 active
	 * currency symbols; GeoDirectory's predefined price_range field uses $ to $$$$.
	 *
	 * @since 2.3.0
	 * @var array
	 */
	private $price_ranges = array(
		'skimming'       => '$$$$',
		'moderate'       => '$$$',
		'economy'        => '$$',
		'bellow_economy' => '$',
	);

	/**
	 * Initialize hooks.
	 *
	 * @since 2.1.3
	 */
	protected function init() {
	}

	/**
	 * Get importer title.
	 *
	 * @since 2.1.3
	 *
	 * @return string The importer title.
	 */
	public function get_title() {
		return __( 'Directorist', 'geodir-converter' );
	}

	/**
	 * Get importer description.
	 *
	 * @since 2.1.3
	 *
	 * @return string The importer description.
	 */
	public function get_description() {
		return __( 'Import directories and listings data from your Directorist installation.', 'geodir-converter' );
	}

	/**
	 * Get importer icon URL.
	 *
	 * @since 2.1.3
	 *
	 * @return string The URL to the importer icon.
	 */
	public function get_icon() {
		return GEODIR_CONVERTER_PLUGIN_URL . 'assets/images/directorist.png';
	}

	/**
	 * Get importer task action.
	 *
	 * @since 2.1.3
	 *
	 * @return string The initial import action identifier.
	 */
	public function get_action() {
		return self::ACTION_IMPORT_DIRECTORIES;
	}

	/**
	 * Render importer settings.
	 *
	 * @since 2.1.3
	 *
	 * @return void
	 */
	public function render_settings() {
		?>
		<form class="geodir-converter-settings-form" method="post">
			<h6 class="fs-base"><?php esc_html_e( 'Directorist Importer Settings', 'geodir-converter' ); ?></h6>
			<?php
			if ( ! class_exists( 'GeoDir_CP' ) ) {
				$this->render_plugin_notice( esc_html__( 'GeoDirectory Custom Post Types', 'geodir-converter' ), 'posttypes', esc_url( 'https://wpgeodirectory.com/downloads/custom-post-types/' ) );
			}

			if ( ! defined( 'GEODIR_PRICING_VERSION' ) ) {
				$this->render_plugin_notice( esc_html__( 'GeoDirectory Pricing Manager', 'geodir-converter' ), 'plans', esc_url( 'https://wpgeodirectory.com/downloads/pricing-manager/' ) );
			}

			if ( ! $this->is_multi_directory_enabled() ) {
				$this->display_post_type_select();
			}

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
	 * Validate importer settings.
	 *
	 * @since 2.1.3
	 *
	 * @param array $settings The settings to validate.
	 * @param array $files    The files to validate.
	 *
	 * @return array|WP_Error Validated and sanitized settings or WP_Error on failure.
	 */
	public function validate_settings( array $settings, array $files = array() ) {
		$post_types = geodir_get_posttypes();
		$errors     = array();

		$settings['test_mode']    = ( isset( $settings['test_mode'] ) && ! empty( $settings['test_mode'] ) && $settings['test_mode'] != 'no' ) ? 'yes' : 'no';
		$settings['gd_post_type'] = isset( $settings['gd_post_type'] ) && ! empty( $settings['gd_post_type'] ) ? sanitize_text_field( $settings['gd_post_type'] ) : 'gd_place';

		if ( ! in_array( $settings['gd_post_type'], $post_types, true ) && ! $this->is_multi_directory_enabled() ) {
			$errors[] = esc_html__( 'The selected post type is invalid. Please choose a valid post type.', 'geodir-converter' );
		}

		// Each directory becomes a new post type, and only the Custom Post Types
		// addon registers the category and tag taxonomies of a new post type.
		if ( $this->is_multi_directory_enabled() && ! class_exists( 'GeoDir_CP' ) ) {
			$errors[] = esc_html__( 'Directorist multi-directory mode imports each directory as a new post type, which requires the GeoDirectory Custom Post Types addon. Please install and activate it first.', 'geodir-converter' );
		}

		if ( ! empty( $errors ) ) {
			return new WP_Error( 'invalid_import_settings', implode( '<br>', $errors ) );
		}

		return $settings;
	}

	/**
	 * Get next task.
	 *
	 * @since 2.1.3
	 *
	 * @param array $task         The current task.
	 * @param bool  $reset_offset Whether to reset the offset.
	 *
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
			self::ACTION_IMPORT_DIRECTORIES,
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
	 * @since 2.1.3
	 *
	 * @return void
	 */
	public function set_import_total() {
		// The directories task runs once; a repeated attempt must not count twice.
		$stats = $this->get_stats();
		if ( ! empty( $stats['total'] ) ) {
			return;
		}

		$total_items = 0;

		// Post types.
		$directories = $this->get_import_directories();
		$total_items += count( $directories ) * 5;

		// Every per-directory task accounts for each of these items, even when
		// the directory has no post type, so the totals always add up.
		foreach ( $directories as $directory ) {
			$post_type = $this->resolve_post_type( $directory->term_id );

			$total_items += count( $this->get_directory_categories( $directory ) );
			$total_items += count( $this->get_directory_tags() );
			$total_items += count( $this->get_directory_fields( $directory->term_id, $post_type ? $post_type : 'gd_place' ) );
		}

		// Count listings.
		$total_items += (int) $this->count_listings();

		$this->increase_imports_total( $total_items );
	}

	/**
	 * Get custom fields.
	 *
	 * @since 2.1.3
	 *
	 * @return array The custom fields.
	 */
	private function get_custom_fields() {
		$fields = array(
			array(
				'type'           => 'number',
				'field_key'      => 'package_id',
				'label'          => __( 'Package', 'geodir-converter' ),
				'description'    => __( 'Select your package.', 'geodir-converter' ),
				'icon'           => 'fas fa-dollar-sign',
				'only_for_admin' => 0,
				'required'       => 1,
			),
			array(
				'type'           => 'datepicker',
				'field_key'      => 'expire_date',
				'label'          => __( 'Expire Date', 'geodir-converter' ),
				'description'    => __( 'Post expire date, usually set automatically. Leave blank to set expire date "Never".', 'geodir-converter' ),
				'placeholder'    => __( 'Expire Date', 'geodir-converter' ),
				'icon'           => 'fas fa-clock',
				'only_for_admin' => 1,
				'required'       => 0,
			),
			array(
				'type'           => 'number',
				'field_key'      => $this->importer_id . '_id',
				'label'          => __( 'Directorist ID', 'geodir-converter' ),
				'description'    => __( 'Original Directorist Listing ID.', 'geodir-converter' ),
				'placeholder'    => __( 'Directorist ID', 'geodir-converter' ),
				'icon'           => 'far fa-id-card',
				'only_for_admin' => 1,
				'required'       => 0,
			),
			array(
				'type'        => 'email',
				'field_key'   => 'email',
				'label'       => __( 'Email', 'geodir-converter' ),
				'description' => __( 'The email of the listing.', 'geodir-converter' ),
				'placeholder' => __( 'Email', 'geodir-converter' ),
				'icon'        => 'far fa-envelope',
				'required'    => 0,
			),
			array(
				'type'        => 'tel',
				'field_key'   => 'phone',
				'label'       => __( 'Phone', 'geodir-converter' ),
				'description' => __( 'The phone number of the listing.', 'geodir-converter' ),
				'placeholder' => __( 'Phone', 'geodir-converter' ),
				'icon'        => 'fa-solid fa-phone',
				'required'    => 0,
			),
			array(
				'type'        => 'url',
				'field_key'   => 'website',
				'label'       => __( 'Website', 'geodir-converter' ),
				'description' => __( 'The website of the listing.', 'geodir-converter' ),
				'placeholder' => __( 'Website', 'geodir-converter' ),
				'icon'        => 'fa-solid fa-globe',
				'required'    => 0,
			),
			array(
				'type'        => 'url',
				'field_key'   => 'facebook',
				'label'       => __( 'Facebook', 'geodir-converter' ),
				'description' => __( 'The Facebook page of the listing.', 'geodir-converter' ),
				'placeholder' => __( 'Facebook', 'geodir-converter' ),
				'icon'        => 'fa-brands fa-facebook',
				'required'    => 0,
			),
			array(
				'type'        => 'url',
				'field_key'   => 'twitter',
				'label'       => __( 'Twitter', 'geodir-converter' ),
				'description' => __( 'The Twitter page of the listing.', 'geodir-converter' ),
				'placeholder' => __( 'Twitter', 'geodir-converter' ),
				'icon'        => 'fa-brands fa-twitter',
				'required'    => 0,
			),
			array(
				'type'        => 'url',
				'field_key'   => 'instagram',
				'label'       => __( 'Instagram', 'geodir-converter' ),
				'description' => __( 'The Instagram page of the listing.', 'geodir-converter' ),
				'placeholder' => __( 'Instagram', 'geodir-converter' ),
				'icon'        => 'fa-brands fa-instagram',
				'required'    => 0,
			),
			array(
				'type'        => 'url',
				'field_key'   => 'youtube',
				'label'       => __( 'YouTube', 'geodir-converter' ),
				'description' => __( 'The YouTube page of the listing.', 'geodir-converter' ),
				'placeholder' => __( 'YouTube', 'geodir-converter' ),
				'icon'        => 'fa-brands fa-youtube',
				'required'    => 0,
			),
			array(
				'type'        => 'url',
				'field_key'   => 'pinterest',
				'label'       => __( 'Pinterest', 'geodir-converter' ),
				'description' => __( 'The Pinterest page of the listing.', 'geodir-converter' ),
				'placeholder' => __( 'Pinterest', 'geodir-converter' ),
				'icon'        => 'fa-brands fa-pinterest',
				'required'    => 0,
			),
			array(
				'type'        => 'url',
				'field_key'   => 'linkedin',
				'label'       => __( 'LinkedIn', 'geodir-converter' ),
				'description' => __( 'The LinkedIn page of the listing.', 'geodir-converter' ),
				'placeholder' => __( 'LinkedIn', 'geodir-converter' ),
				'icon'        => 'fa-brands fa-linkedin',
				'required'    => 0,
			),
			array(
				'type'        => 'checkbox',
				'field_key'   => 'featured',
				'label'       => __( 'Is Featured?', 'geodir-converter' ),
				'description' => __( 'Mark listing as a featured.', 'geodir-converter' ),
				'placeholder' => __( 'Is Featured?', 'geodir-converter' ),
				'icon'        => 'fas fa-certificate',
				'required'    => 0,
			),
			array(
				'type'        => 'checkbox',
				'field_key'   => 'claimed',
				'label'       => __( 'Business Owner/Associate?', 'geodir-converter' ),
				'description' => __( 'Mark listing as a claimed.', 'geodir-converter' ),
				'placeholder' => __( 'Is Claimed?', 'geodir-converter' ),
				'icon'        => 'far fa-check',
				'required'    => 0,
			),
		);

		// package_id and expire_date are Pricing Manager fields; without it they hold nothing.
		if ( ! defined( 'GEODIR_PRICING_VERSION' ) ) {
			$fields = array_values(
				array_filter(
					$fields,
					function ( $field ) {
						return ! in_array( $field['field_key'], array( 'package_id', 'expire_date' ), true );
					}
				)
			);
		}

		return $fields;
	}

	/**
	 * Get the corresponding GD field key for a given shortname.
	 *
	 * @since 2.1.3
	 *
	 * @param string $shortname The field shortname.
	 * @param array  $gd_field  The GD field.
	 * @param array  $dir_field The Directorist field.
	 *
	 * @return string The mapped field key or the original shortname if no match is found.
	 */
	private function map_field_key( $shortname, $gd_field = array(), $dir_field = array() ) {
		$shortname = str_replace( '-', '_', $shortname );

		$fields_map = array(
			'listing_title'                    => 'post_title',
			'listing_content'                  => 'post_content',
			'zip_code'                         => 'zip',
			'videourl'                         => 'video',
			'listing_img'                      => 'post_images',
			'gallery_img'                      => 'post_images',
			'expiry_date'                      => 'expire_date',
			'bdbh'                             => 'business_hours',
			'map'                              => 'custom_map',
			'excerpt'                          => 'post_excerpt',
			'admin_category_select[]'          => 'post_category',
			'tax_input[at_biz_dir_tags][]'     => 'post_tags',
			'tax_input[at_biz_dir_location][]' => 'custom_location',
		);

		foreach ( $this->get_directories() as $directory ) {
			$post_type = $this->resolve_post_type( $directory->term_id );

			$fields_map[ 'swbdp_dirlink_type_' . $directory->term_id ] = $post_type ? $post_type : 'swbdp_dirlink_type_' . $directory->term_id;
		}

		return isset( $fields_map[ $shortname ] ) ? $fields_map[ $shortname ] : $shortname;
	}

	/**
	 * Map Directorist field type to GeoDirectory field type.
	 *
	 * @since 2.1.3
	 *
	 * @param string $field_type The Directorist field type.
	 * @param array  $gd_field   The GD field.
	 * @param array  $dir_field  The Directorist field.
	 *
	 * @return string|false The GeoDirectory field type or false if not supported.
	 */
	private function map_field_type( $field_type, $gd_field = array(), $dir_field = array() ) {
		if ( ! empty( $gd_field['field_key'] ) ) {
			if ( 'website' === $gd_field['field_key'] || ( isset( $dir_field['widget_name'] ) && 'url' === $dir_field['widget_name'] ) ) {
				return 'url';
			} elseif ( in_array( $gd_field['field_key'], array( 'phone', 'phone2', 'fax' ), true ) ) {
				// Directorist saves these as plain text (Fields::translate_key_to_field).
				return 'phone';
			} elseif ( 'post_content' === $gd_field['field_key'] ) {
				// Keep GeoDirectory's default description field type.
				return 'textarea';
			} elseif ( 'business_hours' === $gd_field['field_key'] ) {
				return 'business_hours';
			} elseif ( 'price_range' === $gd_field['field_key'] ) {
				return 'select';
			} elseif ( 'faqs' === $gd_field['field_key'] ) {
				return 'html';
			} elseif ( 'privacy_policy' === $gd_field['field_key'] ) {
				return 'checkbox';
			} elseif ( 'post_category' === $gd_field['field_key'] ) {
				return 'categories';
			} elseif ( 'post_tags' === $gd_field['field_key'] ) {
				return 'tags';
			} elseif ( 'post_images' === $gd_field['field_key'] ) {
				return 'images';
			} elseif ( 'video' === $gd_field['field_key'] ) {
				return 'textarea';
			} elseif ( ( isset( $dir_field['field_key'] ) && strpos( $dir_field['field_key'], 'swbdp_dirlink_type' ) === 0 ) || geodir_is_gd_post_type( $gd_field['field_key'] ) ) {
				return 'link_posts';
			}
		}

		switch ( $field_type ) {
			case 'text':
			case 'color':
				return 'text';
			case 'date':
			case 'datepicker':
				return 'datepicker';
			case 'email':
				return 'email';
			case 'fieldset':
				return 'fieldset';
			case 'file':
				return 'file';
			case 'hours':
				return 'business_hours';
			case 'number':
				return 'number';
			case 'select':
				return 'select';
			case 'url':
				return 'url';
			case 'radio':
				return 'radio';
			case 'checkbox':
				if ( ! empty( $dir_field['options'] ) && ! empty( $dir_field['options'][0] ) && isset( $dir_field['options'][0]['option_value'] ) ) {
					return 'multiselect';
				} else {
					return 'checkbox';
				}
			case 'tel':
				return 'phone';
			case 'time':
				return 'time';
			case 'wp_editor':
				return 'html';
			default:
				return 'textarea';
		}
	}

	/**
	 * Map field type key to field data type.
	 *
	 * @since 2.1.3
	 *
	 * @param string $field_type The field type key.
	 *
	 * @return string The field data type.
	 */
	private function map_data_type( $field_type ) {
		switch ( $field_type ) {
			case 'address':
			case 'email':
			case 'phone':
			case 'radio':
			case 'select':
			case 'text':
			case 'time':
				return 'VARCHAR';
			case 'business_hours':
			case 'categories':
			case 'file':
			case 'images':
			case 'multiselect':
			case 'tags':
			case 'textarea':
			case 'url':
			case 'html':
				return 'TEXT';
			case 'checkbox':
				return 'TINYINT';
			case 'datepicker':
				return 'DATE';
			case 'number':
				return 'INT';
			default:
				return 'VARCHAR';
		}
	}

	/**
	 * Parse listings from Directorist for import into GeoDirectory.
	 *
	 * @since 2.1.3
	 *
	 * @param array $task The current task data including offset.
	 *
	 * @return array|false The next task or false if all tasks are completed.
	 */
	public function task_parse_listings( array $task ) {
		global $wpdb;

		$offset         = isset( $task['offset'] ) ? (int) $task['offset'] : 0;
		$total_listings = isset( $task['total_listings'] ) ? (int) $task['total_listings'] : 0;
		$batch_size     = (int) $this->get_batch_size();

		// Determine total listings count if not set.
		if ( ! isset( $task['total_listings'] ) ) {
			$total_listings         = (int) $this->count_listings();
			$task['total_listings'] = $total_listings;
		}

		// Log the import start message only for the first batch.
		if ( 0 === $offset ) {
			$this->log( __( 'Listings: Import started.', 'geodir-converter' ) );
		}

		// Exit early if there are no listings to import.
		if ( 0 === $total_listings ) {
			$this->log( __( 'No listings found for parsing. Skipping process.', 'geodir-converter' ) );
			return $this->next_task( $task, true );
		}

		$params   = array();
		$params[] = self::POST_TYPE_LISTING;
		$params   = array_merge( $params, $this->post_statuses );
		$params[] = $batch_size;
		$params[] = $offset;

		$listings = $wpdb->get_results( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status IN (" . implode( ',', array_fill( 0, count( $this->post_statuses ), '%s' ) ) . ') ORDER BY ID ASC LIMIT %d OFFSET %d', $params ) );

		if ( empty( $listings ) ) {
			$this->log( __( 'Parsing process completed. No more listings found.', 'geodir-converter' ) );
			return $this->next_task( $task, true );
		}

		// Batch the tasks.
		$batched_tasks = array_chunk( $listings, $this->batch_size );
		$import_tasks  = array();
		foreach ( $batched_tasks as $batch ) {
			$import_tasks[] = array(
				'action'   => self::ACTION_IMPORT_LISTINGS,
				'post_ids' => array_map( 'absint', wp_list_pluck( $batch, 'ID' ) ),
			);
		}

		$this->background_process->add_import_tasks( $import_tasks );

		$complete = ( $offset + $batch_size >= $total_listings );

		if ( ! $complete ) {
			// Continue import with the next batch.
			$task['offset'] = $offset + $batch_size;
			return $task;
		}

		return $this->next_task( $task, true );
	}

	/**
	 * Import listings from Directorist to GeoDirectory.
	 *
	 * Accepts the post IDs queued by the parser, or a single source_id when the
	 * task was re-queued by "Retry Failed".
	 *
	 * @since 2.1.3
	 *
	 * @param array $task The task data containing post IDs to import.
	 *
	 * @return false Always returns false to indicate task completion.
	 */
	public function task_import_listings( $task ) {
		global $wpdb;

		$post_ids = isset( $task['post_ids'] ) && ! empty( $task['post_ids'] ) ? (array) $task['post_ids'] : array();

		if ( empty( $post_ids ) && ! empty( $task['source_id'] ) ) {
			$post_ids = array( $task['source_id'] );
		}

		$post_ids = array_values( array_unique( array_filter( array_map( 'absint', $post_ids ) ) ) );

		if ( empty( $post_ids ) ) {
			return false;
		}

		$placeholders = implode( ',', array_fill( 0, count( $post_ids ), '%d' ) );
		$rows         = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_title FROM {$wpdb->posts} WHERE ID IN ({$placeholders}) ORDER BY ID ASC",
				$post_ids
			),
			OBJECT_K
		);

		// Every queued ID is counted in the total, so each one must be reported,
		// including a listing deleted since the parse step.
		$items = array();
		foreach ( $post_ids as $post_id ) {
			$items[] = isset( $rows[ $post_id ] ) ? $rows[ $post_id ] : (object) array(
				'ID'         => $post_id,
				'post_title' => '#' . $post_id,
			);
		}

		return $this->import_queued_items(
			$items,
			function ( $item ) {
				return $this->import_single_listing( $item );
			}
		);
	}

	/**
	 * Convert a single Directorist listing to GeoDirectory format.
	 *
	 * @since 2.1.3
	 *
	 * @param object $item Object holding the source listing ID.
	 *
	 * @return int The import status.
	 */
	private function import_single_listing( $item ) {
		$post = get_post( (int) $item->ID );

		if ( ! $post || self::POST_TYPE_LISTING !== $post->post_type ) {
			return self::IMPORT_STATUS_SKIPPED;
		}

		$directory_id = $this->get_listing_directory_id( $post->ID );
		$post_type    = $directory_id ? $this->resolve_post_type( $directory_id ) : '';

		if ( empty( $post_type ) || ! geodir_is_gd_post_type( $post_type ) ) {
			/* translators: %s: listing title */
			$this->log( wp_sprintf( __( 'No post type found for the listing %s.', 'geodir-converter' ), $post->post_title ), 'warning' );

			return self::IMPORT_STATUS_FAILED;
		}

		$is_test = $this->is_test_mode();

		// Handle test mode before anything is looked up or written.
		if ( $is_test ) {
			return self::IMPORT_STATUS_SUCCESS;
		}

		$gd_post_id = (int) $this->get_gd_listing_id( $post->ID, $this->importer_id . '_id', $post_type );
		$is_update  = ! empty( $gd_post_id );

		// Get post meta.
		$post_meta = $this->get_post_meta( $post->ID );

		// Get categories and tags.
		$categories = $this->get_categories( $post->ID, self::TAX_LISTING_CATEGORY, 'ids', $post_type );
		$tags       = $this->get_categories( $post->ID, self::TAX_LISTING_TAG, 'names', $post_type );

		// Location & Address.
		$location        = $this->get_default_location();
		$has_coordinates = ! empty( $post_meta['_manual_lat'] ) && ! empty( $post_meta['_manual_lng'] );
		$location_lookup = null;
		$latitude        = $has_coordinates ? $post_meta['_manual_lat'] : $location['latitude'];
		$longitude       = $has_coordinates ? $post_meta['_manual_lng'] : $location['longitude'];
		$address         = ! empty( $post_meta['_address'] ) && is_scalar( $post_meta['_address'] ) ? $post_meta['_address'] : '';
		$zip             = ! empty( $post_meta['_zip'] ) && is_scalar( $post_meta['_zip'] ) ? $post_meta['_zip'] : '';

		if ( $has_coordinates ) {
			$location_lookup = GeoDir_Converter_Utils::get_location_from_coords( $latitude, $longitude );

			if ( ! is_wp_error( $location_lookup ) ) {
				unset( $location_lookup['_source'] );

				$address = isset( $location_lookup['address'] ) && ! empty( $location_lookup['address'] ) ? $location_lookup['address'] : $address;

				if ( ! empty( $address ) && ! empty( $location_lookup['city'] ) && ! empty( $location_lookup['region'] ) && ! empty( $location_lookup['country'] ) ) {
					$_address = array();

					if ( strpos( $address, ', ' . $location_lookup['city'] . ', ' . $location_lookup['region'] ) !== false ) {
						$_address = explode( ', ' . $location_lookup['city'] . ', ' . $location_lookup['region'], $address );
					} elseif ( strpos( $address, ', ' . $location_lookup['region'] . ', ' . $location_lookup['country'] ) !== false ) {
						$_address = explode( ', ' . $location_lookup['region'] . ', ' . $location_lookup['country'], $address );
					} elseif ( ! empty( $location_lookup['zip'] ) && strpos( $address, ', ' . $location_lookup['region'] . ', ' . $location_lookup['zip'] ) !== false ) {
						$_address = explode( ', ' . $location_lookup['region'] . ', ' . $location_lookup['zip'], $address );
					}

					if ( ! empty( $_address ) && ! empty( $_address[0] ) ) {
						$address = trim( $_address[0] );
					}
				}

				$location = array_merge( $location, $location_lookup );
			} else {
				/* translators: 1: listing ID, 2: error message */
				$this->log( wp_sprintf( __( 'Geocoding failed (#%1$d): %2$s', 'geodir-converter' ), $post->ID, $location_lookup->get_error_message() ), 'warning' );
			}

			// Keep the listing's own coordinates.
			$location['latitude']  = $latitude;
			$location['longitude'] = $longitude;
		} else {
			$location['zip'] = '';
		}

		// Without a successful lookup, use the listing's Directorist location terms rather than the default city/region.
		if ( ! $has_coordinates || is_wp_error( $location_lookup ) ) {
			$location = array_merge( $location, $this->get_location_from_terms( $post->ID, $location ) );
		}

		if ( ! empty( $zip ) ) {
			$location['zip'] = $zip;
		}

		// Social links: Directorist stores a list of array( 'id' => network, 'url' => link ).
		$_social_links = isset( $post_meta['_social'] ) ? maybe_unserialize( $post_meta['_social'] ) : array();
		$social_links  = array();
		if ( ! empty( $_social_links ) && is_array( $_social_links ) ) {
			foreach ( $_social_links as $social_link ) {
				if ( is_array( $social_link ) && ! empty( $social_link['id'] ) && ! empty( $social_link['url'] ) ) {
					$social_links[ $social_link['id'] ] = $social_link['url'];
				}
			}
		}

		// Excerpt: saved to post_excerpt and to the _excerpt meta.
		$post_excerpt = $post->post_excerpt;
		if ( '' === $post_excerpt && ! empty( $post_meta['_excerpt'] ) && is_scalar( $post_meta['_excerpt'] ) ) {
			$post_excerpt = $post_meta['_excerpt'];
		}

		// Prepare the listing data.
		$listing = array(
			// Standard WP Fields.
			'post_author'              => $post->post_author ? $post->post_author : get_current_user_id(),
			'post_title'               => $post->post_title,
			'post_content'             => $post->post_content,
			'post_content_filtered'    => $post->post_content_filtered,
			'post_excerpt'             => $post_excerpt,
			'post_status'              => $this->map_post_status( $post->post_status ),
			'post_type'                => $post_type,
			'comment_status'           => $post->comment_status,
			'ping_status'              => $post->ping_status,
			'post_name'                => $post->post_name ? $post->post_name : $post->ID . '-' . sanitize_title( $post->post_title ),
			'post_date_gmt'            => $post->post_date_gmt,
			'post_date'                => $post->post_date,
			'post_modified_gmt'        => $post->post_modified_gmt,
			'post_modified'            => $post->post_modified,
			'tax_input'                => array(
				"{$post_type}category" => $categories,
				"{$post_type}_tags"    => $tags,
			),

			// GD fields.
			'default_category'         => ! empty( $categories ) ? $categories[0] : 0,
			'featured_image'           => $this->get_featured_image( $post->ID ),
			'submit_ip'                => '',
			'overall_rating'           => 0,
			'rating_count'             => 0,

			'street'                   => $address,
			'street2'                  => '',
			'city'                     => isset( $location['city'] ) ? $location['city'] : '',
			'region'                   => isset( $location['region'] ) ? $location['region'] : '',
			'country'                  => isset( $location['country'] ) ? $location['country'] : '',
			'zip'                      => isset( $location['zip'] ) ? $location['zip'] : '',
			'latitude'                 => isset( $location['latitude'] ) ? $location['latitude'] : '',
			'longitude'                => isset( $location['longitude'] ) ? $location['longitude'] : '',
			'mapview'                  => '',
			'mapzoom'                  => '',

			// Directorist standard fields.
			$this->importer_id . '_id' => $post->ID,
			'phone'                    => ! empty( $post_meta['_phone'] ) ? $post_meta['_phone'] : '',
			'website'                  => ! empty( $post_meta['_website'] ) ? $post_meta['_website'] : '',
			'email'                    => ! empty( $post_meta['_email'] ) ? $post_meta['_email'] : '',
			'facebook'                 => ! empty( $post_meta['_facebook'] ) ? $post_meta['_facebook'] : ( ! empty( $social_links['facebook'] ) ? $social_links['facebook'] : '' ),
			'twitter'                  => ! empty( $post_meta['_twitter'] ) ? $post_meta['_twitter'] : ( ! empty( $social_links['twitter'] ) ? $social_links['twitter'] : '' ),
			'instagram'                => ! empty( $post_meta['_instagram'] ) ? $post_meta['_instagram'] : ( ! empty( $social_links['instagram'] ) ? $social_links['instagram'] : '' ),
			'youtube'                  => ! empty( $post_meta['_youtube'] ) ? $post_meta['_youtube'] : ( ! empty( $social_links['youtube'] ) ? $social_links['youtube'] : '' ),
			'pinterest'                => ! empty( $post_meta['_pinterest'] ) ? $post_meta['_pinterest'] : ( ! empty( $social_links['pinterest'] ) ? $social_links['pinterest'] : '' ),
			'linkedin'                 => ! empty( $post_meta['_linkedin'] ) ? $post_meta['_linkedin'] : ( ! empty( $social_links['linkedin'] ) ? $social_links['linkedin'] : '' ),
			'featured'                 => ! empty( $post_meta['_featured'] ) ? 1 : 0,
		);

		// Process package.
		if ( class_exists( 'GeoDir_Pricing_Package' ) ) {
			$listing['package_id'] = geodir_get_post_package_id( $gd_post_id, $post_type );

			$_never_expire = ! empty( $post_meta['_never_expire'] );
			$expire_date   = ! empty( $post_meta['_expiry_date'] ) && is_scalar( $post_meta['_expiry_date'] ) ? $post_meta['_expiry_date'] : '';

			// Process expiration date.
			if ( ! $_never_expire && $expire_date && strtotime( $expire_date ) ) {
				$listing['expire_date'] = date( 'Y-m-d', strtotime( $expire_date ) );
			}
		}

		// Delete existing media if updating. Images and file fields are re-added
		// from their URLs, which GeoDirectory never matches to the stored copies.
		if ( $is_update ) {
			GeoDir_Media::delete_files( (int) $gd_post_id, 'post_images' );

			foreach ( $this->get_directory_fields( $directory_id, $post_type ) as $form_field ) {
				if ( 'file' === $form_field['field_type'] ) {
					GeoDir_Media::delete_files( (int) $gd_post_id, $form_field['field_key'] );
				}
			}
		}

		// Process gallery images.
		$listing['post_images'] = $this->get_post_images( $post_meta );

		// Update custom fields.
		$fields = $this->process_form_fields( $post, $post_meta, $post_type, $directory_id );

		if ( ! empty( $fields ) ) {
			foreach ( $fields as $key => $value ) {
				if ( empty( $listing[ $key ] ) ) {
					$listing[ $key ] = $value;
				}
			}
		}

		// Insert or update the post.
		if ( $is_update ) {
			$gd_post_id = wp_update_post( array_merge( array( 'ID' => $gd_post_id ), $listing ), true );
		} else {
			$gd_post_id = wp_insert_post( $listing, true );
		}

		// Handle errors during post insertion/update.
		if ( is_wp_error( $gd_post_id ) ) {
			$this->log( $gd_post_id->get_error_message() );

			return self::IMPORT_STATUS_FAILED;
		}

		// Reviews and favourites need the GD listing to exist.
		$this->import_reviews( $post->ID, (int) $gd_post_id );
		$this->import_favorites( $post->ID, (int) $gd_post_id );

		return $is_update ? self::IMPORT_STATUS_UPDATED : self::IMPORT_STATUS_SUCCESS;
	}

	/**
	 * Map a Directorist listing status to a GeoDirectory post status.
	 *
	 * Directorist moves listings past their expiry date to an "expired" status,
	 * which only exists in GeoDirectory as the Pricing Manager's gd-expired.
	 *
	 * @since 2.3.0
	 *
	 * @param string $status Directorist post status.
	 * @return string GeoDirectory post status.
	 */
	private function map_post_status( $status ) {
		if ( 'expired' === $status ) {
			return class_exists( 'GeoDir_Pricing_Package' ) ? 'gd-expired' : 'draft';
		}

		return in_array( $status, array( 'publish', 'pending', 'draft', 'private', 'future' ), true ) ? $status : 'draft';
	}

	/**
	 * Get the featured image URL.
	 *
	 * @since 2.1.3
	 *
	 * @param int $post_id The post ID.
	 *
	 * @return string The featured image URL.
	 */
	private function get_featured_image( $post_id ) {
		$image = wp_get_attachment_image_src( get_post_thumbnail_id( $post_id ), 'full' );
		return isset( $image[0] ) ? esc_url( $image[0] ) : '';
	}

	/**
	 * Get gallery images.
	 *
	 * Directorist keeps the first image as the preview image (_listing_prv_img,
	 * also set as the post thumbnail) and the rest in _listing_img.
	 *
	 * @since 2.1.3
	 *
	 * @param array $post_meta The post meta data.
	 *
	 * @return string The gallery images.
	 */
	private function get_post_images( $post_meta ) {
		$attachment_ids = array();
		$image_urls     = array();

		$image_meta_keys = array( '_listing_prv_img', '_thumbnail_id', '_listing_img', '_gallery_img' );

		foreach ( $image_meta_keys as $image_meta_key ) {
			if ( empty( $post_meta[ $image_meta_key ] ) ) {
				continue;
			}

			// get_post_meta() has already unserialized the value.
			$value = maybe_unserialize( $post_meta[ $image_meta_key ] );

			if ( is_scalar( $value ) && ( strpos( (string) $value, 'https://' ) === 0 || strpos( (string) $value, 'http://' ) === 0 ) ) {
				$image_urls[] = esc_url_raw( $value );
				continue;
			}

			foreach ( wp_parse_id_list( is_array( $value ) ? $value : array( $value ) ) as $image_id ) {
				if ( $image_id ) {
					$attachment_ids[] = (int) $image_id;
				}
			}
		}

		$images = array();

		if ( ! empty( $attachment_ids ) ) {
			$attachment_ids = array_values( array_unique( $attachment_ids ) );

			foreach ( $attachment_ids as $i => $id ) {
				$images[] = array(
					'id'      => (int) $id,
					'caption' => get_post_meta( $id, '_wp_attachment_image_alt', true ),
					'weight'  => $i + 1,
				);
			}
		}

		return $this->format_images_data( $images, array_values( array_unique( $image_urls ) ) );
	}

	/**
	 * Process form fields and extract values from post meta.
	 *
	 * @since 2.1.3
	 * @since 2.3.0 Added the $directory_id parameter.
	 *
	 * @param object $post         The post object.
	 * @param array  $post_meta    The post meta data.
	 * @param string $post_type    The post type.
	 * @param int    $directory_id Optional. The listing's directory ID.
	 *
	 * @return array The processed fields.
	 */
	private function process_form_fields( $post, $post_meta, $post_type, $directory_id = 0 ) {
		if ( ! $directory_id ) {
			$directory_id = $this->get_listing_directory_id( $post->ID );
		}

		$form_fields = $this->get_directory_fields( $directory_id, $post_type );
		$fields      = array();

		foreach ( $form_fields as $field ) {
			if ( empty( $field['dir_field_key'] ) || ! isset( $post_meta[ '_' . $field['dir_field_key'] ] ) ) {
				continue;
			}

			if ( $this->should_skip_field( $field['field_key'] ) ) {
				continue;
			}

			$value = maybe_unserialize( $post_meta[ '_' . $field['dir_field_key'] ] );

			if ( 'multiselect' === $field['field_type'] && is_array( $value ) ) {
				$value = array_values( array_filter( array_map( 'trim', array_filter( $value, 'is_scalar' ) ), 'strlen' ) );
			}

			if ( 'business_hours' === $field['field_key'] ) {
				if ( ! empty( $post_meta['_disable_bz_hour_listing'] ) ) {
					continue;
				}

				$timezone = ! empty( $post_meta['_timezone'] ) && is_scalar( $post_meta['_timezone'] ) ? $post_meta['_timezone'] : '';
				$open24x7 = ! empty( $post_meta['_enable247hour'] );
				$value    = $this->parse_field_business_hours( $value, $timezone, $open24x7 );
			} elseif ( 'faqs' === $field['field_key'] ) {
				$value = $this->parse_field_faqs( $value );
			} elseif ( 'price_range' === $field['field_key'] ) {
				$value = is_scalar( $value ) && isset( $this->price_ranges[ $value ] ) ? $this->price_ranges[ $value ] : '';
			} elseif ( 'file' === $field['field_type'] && is_scalar( $value ) ) {
				// Directorist separates the stored file URL with "|||".
				$value = trim( str_replace( '|||', '', (string) $value ) );
			}

			$fields[ $field['field_key'] ] = $value;
		}

		return $fields;
	}

	/**
	 * Parses a field value for business hours.
	 *
	 * Converts the Business Hours extension data (keyed by full day name, with
	 * start/close times) into GeoDirectory's schema string, e.g.
	 * ["Mo 09:00-17:00","Tu 09:00-17:00"],["Timezone":"Europe/London"].
	 *
	 * @since 2.1.3
	 *
	 * @param array  $value    The field value.
	 * @param string $timezone The timezone.
	 * @param bool   $open24x7 Whether the business is open 24/7.
	 *
	 * @return string The parsed field value.
	 */
	public function parse_field_business_hours( $value, $timezone = '', $open24x7 = false ) {
		$day_names = array(
			'monday'    => 'Mo',
			'tuesday'   => 'Tu',
			'wednesday' => 'We',
			'thursday'  => 'Th',
			'friday'    => 'Fr',
			'saturday'  => 'Sa',
			'sunday'    => 'Su',
		);

		$days = array();

		if ( $open24x7 ) {
			// GeoDirectory treats an opens and closes of 00:00 as open 24 hours.
			foreach ( $day_names as $short_name ) {
				$days[] = $short_name . ' 00:00-00:00';
			}
		} elseif ( ! empty( $value ) && is_array( $value ) ) {
			foreach ( $value as $day => $slot ) {
				// Match on the first two letters, as GeoDirectory's short names do.
				$short_name = ucfirst( substr( strtolower( trim( (string) $day ) ), 0, 2 ) );

				if ( ! in_array( $short_name, $day_names, true ) || ! is_array( $slot ) ) {
					continue;
				}

				if ( empty( $slot['enable'] ) || ! in_array( strtolower( (string) $slot['enable'] ), array( 'enable', '1', 'on', 'yes', 'true' ), true ) ) {
					continue;
				}

				$times = array();

				if ( ! empty( $slot['remain_close'] ) && 'open' === $slot['remain_close'] ) {
					$times[] = '00:00-00:00';
				} else {
					$starts = isset( $slot['start'] ) ? (array) $slot['start'] : array();
					$closes = isset( $slot['close'] ) ? (array) $slot['close'] : array();

					foreach ( $starts as $key => $start ) {
						$opens     = $this->format_business_hour( $start );
						$closes_at =isset( $closes[ $key ] ) ? $this->format_business_hour( $closes[ $key ] ) : '';

						if ( $opens && $closes_at ) {
							$times[] = $opens . '-' . $closes_at;
						}
					}
				}

				if ( ! empty( $times ) ) {
					$days[] = $short_name . ' ' . implode( ',', $times );
				}
			}
		}

		if ( empty( $days ) ) {
			return '';
		}

		$_value = '["' . implode( '","', $days ) . '"]';

		if ( $timezone ) {
			$_value .= ',["Timezone":"' . $timezone . '"]';
		}

		return geodir_sanitize_business_hours( $_value );
	}

	/**
	 * Normalise a business hour to 24-hour H:i.
	 *
	 * GeoDirectory splits each day on spaces, so "9:00 am" must become "09:00".
	 *
	 * @since 2.3.0
	 *
	 * @param string $time The time.
	 * @return string The time as H:i, or empty when it cannot be parsed.
	 */
	private function format_business_hour( $time ) {
		if ( ! is_scalar( $time ) || '' === trim( (string) $time ) ) {
			return '';
		}

		$timestamp = strtotime( trim( (string) $time ) );

		return false !== $timestamp ? gmdate( 'H:i', $timestamp ) : '';
	}

	/**
	 * Copy a listing's reviews and comments to the GeoDirectory listing.
	 *
	 * Since Directorist 7.1 reviews are comments of type "review" with the
	 * star rating in the "rating" comment meta; replies are plain comments.
	 * Reviews are copied rather than moved so the source data is left intact,
	 * and matched on date and author so re-runs do not duplicate them.
	 *
	 * @since 2.3.0
	 *
	 * @param int $source_id  Source listing ID.
	 * @param int $gd_post_id GeoDirectory post ID.
	 * @return void
	 */
	private function import_reviews( $source_id, $gd_post_id ) {
		global $wpdb, $user_ID;

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

			$rating = (int) round( (float) get_comment_meta( $comment->comment_ID, 'rating', true ) );

			// GeoDir_Comments::save_rating() only rates top-level comments and
			// records the global $user_ID as the reviewer.
			if ( $rating > 0 && ! $parent && class_exists( 'GeoDir_Comments' ) ) {
				$current_user_id = $user_ID;
				$user_ID         = (int) $comment->user_id; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

				$_REQUEST['geodir_overallrating'] = max( 1, min( 5, $rating ) );
				\GeoDir_Comments::save_rating( $new_comment_id );
				unset( $_REQUEST['geodir_overallrating'] );

				$user_ID = $current_user_id; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			}
		}

		wp_update_comment_count( $gd_post_id );
	}

	/**
	 * Copy users' favourites of a listing to the GeoDirectory listing.
	 *
	 * Directorist keeps each user's favourites as an array of listing IDs in
	 * the atbdp_favourites user meta; GeoDirectory uses gd_user_favourite_post.
	 *
	 * @since 2.3.0
	 *
	 * @param int $source_id  Source listing ID.
	 * @param int $gd_post_id GeoDirectory post ID.
	 * @return void
	 */
	private function import_favorites( $source_id, $gd_post_id ) {
		global $wpdb;

		$source_id = (int) $source_id;

		// The LIKE only narrows the rows; each match is confirmed after unserializing.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT user_id, meta_value FROM {$wpdb->usermeta}
				WHERE meta_key = %s
				AND ( meta_value LIKE %s OR meta_value LIKE %s )",
				'atbdp_favourites',
				'%' . $wpdb->esc_like( 'i:' . $source_id . ';' ) . '%',
				'%' . $wpdb->esc_like( '"' . $source_id . '"' ) . '%'
			)
		);

		if ( empty( $rows ) ) {
			return;
		}

		$site_id = '';
		if ( is_multisite() ) {
			$blog_id = get_current_blog_id();
			if ( $blog_id && 1 !== (int) $blog_id ) {
				$site_id = '_' . $blog_id;
			}
		}

		foreach ( $rows as $row ) {
			$favorites = maybe_unserialize( $row->meta_value );

			if ( ! is_array( $favorites ) || ! in_array( $source_id, array_map( 'absint', $favorites ), true ) ) {
				continue;
			}

			$user_favs = get_user_meta( (int) $row->user_id, 'gd_user_favourite_post' . $site_id, true );
			$user_favs = is_array( $user_favs ) ? $user_favs : array();

			if ( in_array( $gd_post_id, array_map( 'absint', $user_favs ), true ) ) {
				continue;
			}

			$user_favs[] = $gd_post_id;

			update_user_meta( (int) $row->user_id, 'gd_user_favourite_post' . $site_id, $user_favs );
		}
	}

	/**
	 * Parses a field value for FAQs.
	 *
	 * @since 2.1.3
	 *
	 * @param array $value The field value.
	 *
	 * @return string The parsed field value.
	 */
	public function parse_field_faqs( $value ) {
		$_value = '';

		if ( ! empty( $value ) && is_array( $value ) ) {
			foreach ( $value as $faq ) {
				if ( ! empty( $faq['quez'] ) && ! empty( $faq['ans'] ) ) {
					$_value .= '<ul><li><b>' . $faq['quez'] . '</b><br>' . $faq['ans'] . '</li></ul>';
				}
			}
		}

		return $_value;
	}

	/**
	 * Build city/region/country from a listing's Directorist location terms.
	 *
	 * Terms are read root to leaf. A top-level term that matches a GD country name
	 * (geodir_get_countries() is keyed by name) is the country, the next term is the
	 * region and the deepest is the city. The default city and region are dropped when
	 * the listing is in another country.
	 *
	 * @since 2.3.0
	 *
	 * @param int   $post_id  Directorist listing ID.
	 * @param array $location The current location.
	 * @return array Location parts to merge.
	 */
	private function get_location_from_terms( $post_id, array $location ) {
		global $wpdb;

		// Direct SQL, so it works while Directorist is inactive.
		$term = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT t.name, tt.parent
				FROM {$wpdb->terms} t
				INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
				INNER JOIN {$wpdb->term_relationships} tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
				WHERE tr.object_id = %d AND tt.taxonomy = %s
				ORDER BY tt.parent DESC, t.term_id ASC
				LIMIT 1",
				$post_id,
				self::TAX_LISTING_LOCATION
			)
		);

		if ( ! $term ) {
			return array();
		}

		// Walk up to the root to build the chain root to leaf.
		$chain = array();
		$guard = 0;

		while ( $term && $guard++ < 10 ) {
			array_unshift( $chain, html_entity_decode( $term->name, ENT_QUOTES, 'UTF-8' ) );

			$term = $term->parent ? $wpdb->get_row(
				$wpdb->prepare(
					"SELECT t.name, tt.parent FROM {$wpdb->terms} t INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id WHERE t.term_id = %d AND tt.taxonomy = %s",
					(int) $term->parent,
					self::TAX_LISTING_LOCATION
				)
			) : null;
		}

		$parts     = array();
		$countries = function_exists( 'geodir_get_countries' ) ? (array) geodir_get_countries() : array();

		if ( isset( $countries[ $chain[0] ] ) ) {
			$parts['country'] = array_shift( $chain );

			// The default city and region belong to the default country.
			if ( $parts['country'] !== $location['country'] ) {
				$parts['region'] = '';
				$parts['city']   = '';
			}
		}

		if ( count( $chain ) >= 2 ) {
			$parts['region'] = $chain[0];
			$parts['city']   = end( $chain );
		} elseif ( 1 === count( $chain ) ) {
			$parts['city'] = $chain[0];
		}

		return $parts;
	}

	/**
	 * Retrieves the current post's categories.
	 *
	 * @since 2.1.3
	 *
	 * @param int    $post_id     The post ID.
	 * @param string $taxonomy    The taxonomy to query for.
	 * @param string $return_type Determines whether to return IDs or names.
	 * @param string $post_type   The GeoDirectory post type.
	 *
	 * @return array An array of category IDs or names based on the $return_type.
	 */
	private function get_categories( $post_id, $taxonomy = self::TAX_LISTING_CATEGORY, $return_type = 'ids', $post_type = '' ) {
		global $wpdb;

		$equivalent_key = 'gd_equivalent' . ( $post_type ? '_' . $post_type : '' );

		$terms = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT t.term_id, tm.meta_value as gd_equivalent
				FROM {$wpdb->terms} t
				INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
				INNER JOIN {$wpdb->term_relationships} tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
				LEFT JOIN {$wpdb->termmeta} tm ON t.term_id = tm.term_id AND tm.meta_key = %s
				WHERE tr.object_id = %d AND tt.taxonomy = %s
				ORDER BY tr.term_order ASC, t.term_id ASC",
				$equivalent_key,
				$post_id,
				$taxonomy
			)
		);

		$candidate_ids = array();
		foreach ( $terms as $term ) {
			$gd_term_id = (int) $term->gd_equivalent;

			if ( $gd_term_id ) {
				$candidate_ids[] = $gd_term_id;
			}
		}

		$candidate_ids = array_values( array_unique( $candidate_ids ) );

		if ( empty( $candidate_ids ) ) {
			return array();
		}

		// Validate all GD terms in a single query.
		$placeholders = implode( ',', array_fill( 0, count( $candidate_ids ), '%d' ) );
		$gd_terms     = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT term_id, name FROM {$wpdb->terms} WHERE term_id IN ({$placeholders})",
				$candidate_ids
			),
			OBJECT_K
		);

		$categories = array();

		foreach ( $candidate_ids as $gd_term_id ) {
			if ( isset( $gd_terms[ $gd_term_id ] ) ) {
				$categories[] = ( 'names' === $return_type ) ? $gd_terms[ $gd_term_id ]->name : (int) $gd_term_id;
			}
		}

		return $categories;
	}

	/**
	 * Counts the number of listings.
	 *
	 * @since 2.1.3
	 *
	 * @return int The number of listings.
	 */
	private function count_listings() {
		global $wpdb;

		$sql   = $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status IN (" . implode( ',', array_fill( 0, count( $this->post_statuses ), '%s' ) ) . ')', array_merge( array( self::POST_TYPE_LISTING ), $this->post_statuses ) );
		$count = (int) $wpdb->get_var( $sql );

		return $count;
	}

	/**
	 * Checks if multi-directory is enabled.
	 *
	 * @since 2.1.3
	 *
	 * @return bool True if multi-directory is enabled, false otherwise.
	 */
	public function is_multi_directory_enabled() {
		return (bool) $this->get_option( 'enable_multi_directory', false );
	}

	/**
	 * Get option.
	 *
	 * @since 2.1.3
	 *
	 * @param string $key           Option key.
	 * @param mixed  $default       Default value.
	 * @param bool   $force_default Force default value.
	 *
	 * @return mixed Option value.
	 */
	public function get_option( $key, $default = false, $force_default = false ) {
		if ( empty( $key ) ) {
			return $default;
		}

		$options = (array) get_option( 'atbdp_option' );
		$value   = array_key_exists( $key, $options ) ? $options[ sanitize_key( $key ) ] : null;

		$newvalue = apply_filters( 'directorist_option', $value, $key );

		if ( $newvalue != $value ) {
			return $newvalue;
		}

		if ( is_null( $value ) ) {
			return $default;
		}

		if ( $force_default ) {
			if ( empty( $value ) ) {
				return $default;
			}
		}

		return isset( $value ) ? $value : $default;
	}

	/**
	 * Get directories.
	 *
	 * Read straight from the database so the import does not depend on
	 * Directorist being active to register its taxonomy.
	 *
	 * @since 2.1.3
	 *
	 * @param array $args Arguments. Supports 'default_only'.
	 *
	 * @return array Directories.
	 */
	public function get_directories( $args = array() ) {
		global $wpdb;

		if ( null === $this->directories_cache ) {
			$directories = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT t.term_id, t.name, t.slug, tt.term_taxonomy_id, tt.taxonomy, tt.description, tt.parent, tt.count
					FROM {$wpdb->terms} AS t
					INNER JOIN {$wpdb->term_taxonomy} AS tt ON t.term_id = tt.term_id
					WHERE tt.taxonomy = %s
					ORDER BY t.term_id ASC",
					$this->get_directory_taxonomy()
				)
			);

			foreach ( (array) $directories as $directory ) {
				$directory->term_id = (int) $directory->term_id;
			}

			$this->directories_cache = ! empty( $directories ) ? $directories : array();
		}

		if ( ! empty( $args['default_only'] ) ) {
			$default_id = $this->get_default_directory_id();

			foreach ( $this->directories_cache as $directory ) {
				if ( $directory->term_id === $default_id ) {
					return array( $directory );
				}
			}

			return array();
		}

		return $this->directories_cache;
	}

	/**
	 * Get the directories to import.
	 *
	 * With multi-directory disabled Directorist only uses the default directory,
	 * and every listing goes to the post type chosen in the settings.
	 *
	 * @since 2.3.0
	 *
	 * @return array Directories.
	 */
	private function get_import_directories() {
		if ( $this->is_multi_directory_enabled() ) {
			return $this->get_directories();
		}

		return $this->get_directories( array( 'default_only' => true ) );
	}

	/**
	 * Get the default directory ID.
	 *
	 * @since 2.3.0
	 *
	 * @return int The default directory ID, the first directory, or 0.
	 */
	private function get_default_directory_id() {
		$directories = $this->get_directories();

		if ( empty( $directories ) ) {
			return 0;
		}

		foreach ( $directories as $directory ) {
			if ( ! empty( get_term_meta( $directory->term_id, '_default', true ) ) ) {
				return (int) $directory->term_id;
			}
		}

		return (int) $directories[0]->term_id;
	}

	/**
	 * Get a listing's directory ID.
	 *
	 * Listings without a (valid) _directory_type belong to the default directory.
	 *
	 * @since 2.3.0
	 *
	 * @param int $post_id Listing ID.
	 * @return int Directory ID.
	 */
	private function get_listing_directory_id( $post_id ) {
		$value = get_post_meta( $post_id, '_directory_type', true );

		if ( is_array( $value ) ) {
			$value = reset( $value );
		}

		if ( ! empty( $value ) && is_scalar( $value ) ) {
			foreach ( $this->get_directories() as $directory ) {
				if ( ( is_numeric( $value ) && (int) $value === $directory->term_id ) || (string) $value === $directory->slug ) {
					return (int) $directory->term_id;
				}
			}
		}

		return $this->get_default_directory_id();
	}

	/**
	 * Resolve the GeoDirectory post type for a directory.
	 *
	 * @since 2.3.0
	 *
	 * @param int $directory_id Directory ID.
	 * @return string GD post type, or empty when none.
	 */
	private function resolve_post_type( $directory_id ) {
		if ( ! $this->is_multi_directory_enabled() ) {
			$post_type = $this->get_import_post_type();

			if ( geodir_is_gd_post_type( $post_type ) ) {
				return $post_type;
			}

			return $this->is_test_mode() ? 'gd_place' : '';
		}

		return self::get_dir2gd_post_type( $directory_id, $this->is_test_mode() );
	}

	/**
	 * Get directory taxonomy.
	 *
	 * @since 2.1.3
	 *
	 * @return string Directory taxonomy.
	 */
	public function get_directory_taxonomy() {
		if ( defined( 'ATBDP_DIRECTORY_TYPE' ) && ATBDP_DIRECTORY_TYPE ) {
			$taxonomy = ATBDP_DIRECTORY_TYPE;
		} else {
			$taxonomy = 'atbdp_listing_types';
		}

		return $taxonomy;
	}

	/**
	 * Get directory categories.
	 *
	 * In multi-directory mode Directorist assigns each category to directories
	 * through the _directory_type term meta; only those assigned to the
	 * directory (or to none), plus their parents, are returned.
	 *
	 * @since 2.1.3
	 * @since 2.3.0 Added the $directory parameter. Parents are returned before children.
	 *
	 * @param object|null $directory Optional. Directory to filter by.
	 *
	 * @return array Directory categories.
	 */
	public function get_directory_categories( $directory = null ) {
		global $wpdb;

		if ( null === $this->categories_cache ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT t.term_id, t.name, t.slug, tt.term_taxonomy_id, tt.taxonomy, tt.description, tt.parent, tt.count, tm.meta_value AS directory_types
					FROM {$wpdb->terms} AS t
					INNER JOIN {$wpdb->term_taxonomy} AS tt ON t.term_id = tt.term_id
					LEFT JOIN {$wpdb->termmeta} AS tm ON tm.term_id = t.term_id AND tm.meta_key = '_directory_type'
					WHERE tt.taxonomy = %s
					ORDER BY tt.parent ASC, t.term_id ASC",
					self::TAX_LISTING_CATEGORY
				)
			);

			$categories = array();
			foreach ( (array) $rows as $row ) {
				if ( ! isset( $categories[ (int) $row->term_id ] ) ) {
					$categories[ (int) $row->term_id ] = $row;
				}
			}

			$this->categories_cache = $this->sort_terms_by_hierarchy( array_values( $categories ) );
		}

		if ( empty( $directory ) || ! $this->is_multi_directory_enabled() ) {
			return $this->categories_cache;
		}

		$by_id    = array();
		$included = array();

		foreach ( $this->categories_cache as $category ) {
			$by_id[ (int) $category->term_id ] = $category;

			$assigned = maybe_unserialize( $category->directory_types );
			$assigned = is_array( $assigned ) ? $assigned : ( '' !== (string) $assigned ? array( $assigned ) : array() );

			if ( empty( $assigned ) || in_array( (string) $directory->term_id, array_map( 'strval', $assigned ), true ) || in_array( $directory->slug, $assigned, true ) ) {
				$included[ (int) $category->term_id ] = true;
			}
		}

		// Keep the parents of included categories so the hierarchy survives.
		foreach ( array_keys( $included ) as $term_id ) {
			$parent = (int) $by_id[ $term_id ]->parent;

			while ( $parent && isset( $by_id[ $parent ] ) && ! isset( $included[ $parent ] ) ) {
				$included[ $parent ] = true;
				$parent              = (int) $by_id[ $parent ]->parent;
			}
		}

		$categories = array();
		foreach ( $this->categories_cache as $category ) {
			if ( isset( $included[ (int) $category->term_id ] ) ) {
				$categories[] = $category;
			}
		}

		return $categories;
	}

	/**
	 * Order terms so that every parent comes before its children.
	 *
	 * import_taxonomy_terms() resolves a term's parent from the already
	 * imported terms, so a child imported first would lose its parent.
	 *
	 * @since 2.3.0
	 *
	 * @param array $terms Terms with term_id and parent.
	 * @return array Sorted terms.
	 */
	private function sort_terms_by_hierarchy( array $terms ) {
		$ids = array();
		foreach ( $terms as $term ) {
			$ids[ (int) $term->term_id ] = true;
		}

		$children = array();
		foreach ( $terms as $term ) {
			$parent = (int) $term->parent;
			$parent = ( $parent && isset( $ids[ $parent ] ) ) ? $parent : 0;

			$children[ $parent ][] = $term;
		}

		$sorted = array();
		$seen   = array();
		$queue  = array( 0 );

		while ( ! empty( $queue ) ) {
			$parent = array_shift( $queue );

			if ( empty( $children[ $parent ] ) ) {
				continue;
			}

			foreach ( $children[ $parent ] as $term ) {
				$term_id = (int) $term->term_id;

				if ( isset( $seen[ $term_id ] ) ) {
					continue;
				}

				$seen[ $term_id ] = true;
				$sorted[]         = $term;
				$queue[]          = $term_id;
			}
		}

		// Terms caught in a parent loop are appended rather than dropped.
		foreach ( $terms as $term ) {
			if ( ! isset( $seen[ (int) $term->term_id ] ) ) {
				$sorted[] = $term;
			}
		}

		return $sorted;
	}

	/**
	 * Get directory tags.
	 *
	 * @since 2.1.3
	 *
	 * @return array Directory tags.
	 */
	public function get_directory_tags() {
		global $wpdb;

		if ( null === $this->tags_cache ) {
			$tags = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT t.term_id, t.name, t.slug, tt.term_taxonomy_id, tt.taxonomy, tt.description, tt.parent, tt.count
					FROM {$wpdb->terms} AS t
					INNER JOIN {$wpdb->term_taxonomy} AS tt ON t.term_id = tt.term_id
					WHERE tt.taxonomy = %s
					ORDER BY t.term_id ASC",
					self::TAX_LISTING_TAG
				)
			);

			$this->tags_cache = ! empty( $tags ) ? $tags : array();
		}

		return $this->tags_cache;
	}

	/**
	 * Get directory fields.
	 *
	 * @since 2.1.3
	 *
	 * @param int    $directory_id Directory ID.
	 * @param string $post_type    GD post type.
	 *
	 * @return array Directory fields.
	 */
	public function get_directory_fields( $directory_id, $post_type ) {
		$cache_key = (int) $directory_id . '|' . $post_type;

		if ( isset( $this->fields_cache[ $cache_key ] ) ) {
			return $this->fields_cache[ $cache_key ];
		}

		$meta_fields = $directory_id ? get_term_meta( (int) $directory_id, 'submission_form_fields', true ) : array();

		// Mapped keys that are not imported as fields: map and address data go
		// to GeoDirectory's own address field, while locations, the terms
		// consent and the contact-owner toggle have no GeoDirectory equivalent.
		$skip_fields = array( 'bdb', 'custom_map', 'custom_location', 'privacy_policy', 't_c_check', 'hide_contact_owner' );
		$form_fields = array();
		$package_ids = $this->get_package_ids( $post_type );
		$package_ids = is_array( $package_ids ) ? implode( ',', $package_ids ) : $package_ids;

		if ( ! empty( $meta_fields['groups'] ) && is_array( $meta_fields['groups'] ) && ! empty( $meta_fields['fields'] ) && is_array( $meta_fields['fields'] ) ) {
			$groups        = array_values( $meta_fields['groups'] );
			$custom_fields = $this->get_custom_fields();
			$append_keys   = array();
			$appended_keys = false;

			foreach ( $custom_fields as $custom_field ) {
				if ( empty( $meta_fields['fields'][ $custom_field['field_key'] ] ) ) {
					$meta_fields['fields'][ $custom_field['field_key'] ] = $custom_field;
					$append_keys[] = $custom_field['field_key'];
				}
			}

			foreach ( $groups as $i => $group ) {
				$group_fields = ! empty( $group['fields'] ) && is_array( $group['fields'] ) ? $group['fields'] : array();
				$group_label  = ! empty( $group['label'] ) && is_scalar( $group['label'] ) ? (string) $group['label'] : '';
				$group_id     = ! empty( $group['id'] ) && is_scalar( $group['id'] ) ? (string) $group['id'] : '';

				if ( false !== stripos( $group_label, 'contact' ) || false !== stripos( $group_id, 'contact' ) || array_intersect( array( 'phone', 'email', 'website' ), $group_fields ) ) {
					$groups[ $i ]['fields'] = array_merge( $group_fields, $append_keys );
					$appended_keys          = true;

					break;
				}
			}

			$used_keys = array();
			$seen_keys = array();

			foreach ( $groups as $j => $group ) {
				$group_fields = ! empty( $group['fields'] ) && is_array( $group['fields'] ) ? $group['fields'] : array();

				if ( ! $appended_keys && 0 === $j ) {
					$group_fields = array_merge( $group_fields, $append_keys );
				}

				$section              = array(
					'label' => ! empty( $group['label'] ) && is_scalar( $group['label'] ) ? (string) $group['label'] : '',
				);
				$section['type']      = 'fieldset';
				$section['field_key'] = $this->get_group_key( $group, $j, $used_keys );
				$section['fields']    = array();

				foreach ( $group_fields as $widget_key ) {
					if ( ! is_scalar( $widget_key ) || empty( $meta_fields['fields'][ $widget_key ] ) || ! is_array( $meta_fields['fields'][ $widget_key ] ) ) {
						continue;
					}

					$dir_field  = $meta_fields['fields'][ $widget_key ];
					$dir_fields = $this->is_pricing_widget( $dir_field ) ? $this->get_pricing_fields( $dir_field ) : array( $widget_key => $dir_field );

					foreach ( $dir_fields as $key => $_dir_field ) {
						$_field = $this->prepare_dir2gd_field( $_dir_field, $post_type );

						// Fields GeoDirectory already has (address, zip, images, excerpt) are
						// left out here, so a section holding only those is not created empty.
						if ( empty( $_field['field_key'] ) || in_array( $_field['field_key'], $skip_fields, true ) || isset( $seen_keys[ $_field['field_key'] ] ) || $this->should_skip_field( $_field['field_key'] ) ) {
							continue;
						}

						$seen_keys[ $_field['field_key'] ] = true;
						$section['fields'][ $key ]         = $_field;
					}
				}

				if ( ! empty( $section['fields'] ) ) {
					$form_fields[] = $section;
				}
			}
		}

		$order  = 30;
		$fields = array();

		foreach ( $form_fields as $group ) {
			++$order;

			$_field                = $this->prepare_dir2gd_field( $group, $post_type, $package_ids, true );
			$_field['sort_order']  = $order;
			$_field['package_ids'] = $package_ids;
			$fields[]              = $_field;

			foreach ( $group['fields'] as $field ) {
				++$order;

				if ( in_array( $field['field_key'], array( 'package_id', 'expire_date' ), true ) ) {
					$field['sort_order'] = 1;
				} elseif ( in_array( $field['field_key'], array( 'directorist_id', 'featured', 'claimed' ), true ) ) {
					$field['sort_order'] = 100;
				} else {
					$field['sort_order'] = $order;
				}

				$field['tab_parent_key'] = $_field['field_key'];
				$field['package_ids']    = $package_ids;
				$fields[]                = $field;
			}
		}

		$this->fields_cache[ $cache_key ] = $fields;

		return $fields;
	}

	/**
	 * Build a unique fieldset key for a form group.
	 *
	 * Directorist 8 groups have no ID, so the key comes from the label.
	 *
	 * @since 2.3.0
	 *
	 * @param array $group     The Directorist form group.
	 * @param int   $index     The group position.
	 * @param array $used_keys Keys already used, updated in place.
	 * @return string The fieldset key.
	 */
	private function get_group_key( $group, $index, array &$used_keys ) {
		if ( ! empty( $group['id'] ) && is_scalar( $group['id'] ) ) {
			$base = (string) $group['id'];
		} elseif ( ! empty( $group['label'] ) && is_scalar( $group['label'] ) ) {
			$base = sanitize_title( (string) $group['label'] );
		} else {
			$base = '';
		}

		$base = str_replace( '-', '_', sanitize_key( $base ) );

		if ( '' === $base ) {
			$base = 'section_' . ( (int) $index + 1 );
		}

		if ( 0 !== strpos( $base, 'group_' ) ) {
			$base = 'group_' . $base;
		}

		$key    = $base;
		$suffix = 2;

		while ( isset( $used_keys[ $key ] ) ) {
			$key = $base . '_' . $suffix;
			++$suffix;
		}

		$used_keys[ $key ] = true;

		return $key;
	}

	/**
	 * Whether a Directorist form field is the pricing widget.
	 *
	 * @since 2.3.0
	 *
	 * @param array $dir_field The Directorist field.
	 * @return bool
	 */
	private function is_pricing_widget( $dir_field ) {
		return ( isset( $dir_field['widget_name'] ) && 'pricing' === $dir_field['widget_name'] ) || ( isset( $dir_field['field_key'] ) && 'pricing' === $dir_field['field_key'] );
	}

	/**
	 * Split the Directorist pricing widget into GeoDirectory's price fields.
	 *
	 * The widget saves _price (a number) or _price_range (skimming, moderate,
	 * economy or bellow_economy), which map to GeoDirectory's predefined
	 * "price" and "price_range" fields.
	 *
	 * @since 2.3.0
	 *
	 * @param array $dir_field The Directorist pricing field.
	 * @return array Directorist-style field definitions, keyed by field key.
	 */
	private function get_pricing_fields( $dir_field ) {
		$pricing_type = ! empty( $dir_field['pricing_type'] ) ? $dir_field['pricing_type'] : 'both';
		$common       = array(
			'only_for_admin' => ! empty( $dir_field['only_for_admin'] ),
			'required'       => ! empty( $dir_field['required'] ),
			'widget_name'    => 'pricing',
		);
		$fields       = array();

		if ( 'price_range' !== $pricing_type ) {
			$fields['price'] = array_merge(
				$common,
				array(
					'type'        => 'number',
					'field_key'   => 'price',
					'label'       => ! empty( $dir_field['price_unit_field_label'] ) ? $dir_field['price_unit_field_label'] : __( 'Price', 'geodir-converter' ),
					'placeholder' => ! empty( $dir_field['price_unit_field_placeholder'] ) ? $dir_field['price_unit_field_placeholder'] : '',
				)
			);
		}

		if ( 'price_unit' !== $pricing_type ) {
			$fields['price_range'] = array_merge(
				$common,
				array(
					'type'        => 'select',
					'field_key'   => 'price_range',
					'label'       => ! empty( $dir_field['price_range_label'] ) ? $dir_field['price_range_label'] : __( 'Price Range', 'geodir-converter' ),
					'placeholder' => ! empty( $dir_field['price_range_placeholder'] ) ? $dir_field['price_range_placeholder'] : '',
				)
			);
		}

		return $fields;
	}

	/**
	 * Prepare directory to GD field.
	 *
	 * @since 2.1.3
	 *
	 * @param array  $data           Directory field data.
	 * @param string $post_type      GD post type.
	 * @param int    $package_ids    Package IDs.
	 * @param bool   $is_placeholder Whether the field is a placeholder.
	 *
	 * @return array Prepared field data.
	 */
	public function prepare_dir2gd_field( $data, $post_type, $package_ids = 0, $is_placeholder = false ) {
		if ( ! is_array( $data ) ) {
			return array();
		}

		if ( empty( $data['type'] ) || ! is_scalar( $data['type'] ) ) {
			$data['type'] = 'text';
		}

		if ( empty( $data['field_key'] ) || ! is_scalar( $data['field_key'] ) ) {
			return array();
		}

		if ( empty( $data['label'] ) ) {
			$data['label'] = 'privacy_policy' === $data['field_key'] ? 'Terms & Privacy' : $data['field_key'];
		}

		if ( 'add_new' === $data['type'] && false !== strpos( $data['field_key'], 'social' ) ) {
			return array();
		}

		$field                   = array();
		$field['post_type']      = $post_type;
		$field['admin_title']    = $data['label'];
		$field['frontend_title'] = $data['label'];
		$field['field_key']      = $this->map_field_key( $data['field_key'], $field, $data );
		$field['field_type']     = $this->map_field_type( $data['type'], $field, $data );
		$field['data_type']      = $this->map_data_type( $field['field_type'], $field, $data );

		if ( 'link_posts' === $field['field_type'] ) {
			$field['field_type_key'] = $field['field_key'];
		}

		if ( ! empty( $data['description'] ) ) {
			$field['description'] = $data['description'];
		} elseif ( ! empty( $data['text'] ) ) {
			$field['description'] = $data['text'];
		} else {
			$field['description'] = '';
		}

		if ( ! empty( $data['placeholder'] ) ) {
			$field['placeholder_text'] = $data['placeholder'];
		} elseif ( ! empty( $data['select_files_label'] ) ) {
			$field['placeholder_text'] = $data['select_files_label'];
		} else {
			$field['placeholder_text'] = '';
		}

		if ( ! empty( $data['options'] ) && is_array( $data['options'] ) ) {
			$option_values = array();
			foreach ( $data['options'] as $option ) {
				$option_value = isset( $option['option_value'] ) && is_scalar( $option['option_value'] ) ? trim( (string) $option['option_value'] ) : '';
				$option_label = isset( $option['option_label'] ) && is_scalar( $option['option_label'] ) ? trim( (string) $option['option_label'] ) : '';

				if ( '' !== $option_value ) {
					$option_values[] = $option_value . ' : ' . ( '' !== $option_label ? $option_label : $option_value );
				}
			}
			$field['option_values'] = implode( "\n", $option_values );
		} else {
			$field['option_values'] = '';
		}

		$field['for_admin_use']      = ! empty( $data['only_for_admin'] ) ? 1 : 0;
		$field['package_ids']        = 0;
		$field['field_icon']         = ! empty( $data['icon'] ) ? $data['icon'] : '';
		$field['is_required']        = ! empty( $data['required'] ) ? 1 : 0;
		$field['default_value']      = '';
		$field['is_active']          = 1;
		$field['sort_order']         = 0;
		$field['tab_parent']         = 0;
		$field['tab_level']          = $is_placeholder ? 0 : 1;
		$field['output_location']    = 'detail';
		$field['show_in_sorting']    = 0;
		$field['required_message']   = '';
		$field['validation_pattern'] = '';
		$field['validation_message'] = '';
		$field['conditional_fields'] = '';
		$extra_fields                = array();

		if ( 'file' === $field['field_type'] ) {
			if ( ! empty( $data['file_type'] ) ) {
				$file_types = geodir_allowed_mime_types();

				if ( 'all_types' === $data['file_type'] ) {
					$_file_types = array( '*' );
				} elseif ( 'image' === $data['file_type'] ) {
					$_file_types = array_keys( $file_types['Image'] );
				} elseif ( 'audio' === $data['file_type'] ) {
					$_file_types = array_keys( $file_types['Audio'] );
				} elseif ( 'video' === $data['file_type'] ) {
					$_file_types = array_keys( $file_types['Video'] );
				} elseif ( 'document' === $data['file_type'] ) {
					$_file_types = array_keys( $file_types['Application'] );
				} else {
					$_file_types = array( $data['file_type'] );
				}

				$extra_fields = array( 'gd_file_types' => $_file_types );
			}
		} elseif ( 'link_posts' === $field['field_type'] ) {
			$max_posts = 'multiple' === $data['type'] ? 0 : 1;

			$extra_fields = array( 'max_posts' => $max_posts );
		} elseif ( 'multiselect' === $field['field_type'] && ! empty( $data['type'] ) && 'checkbox' === $data['type'] ) {
			$extra_fields = array( 'multi_display_type' => 'checkbox' );
		} elseif ( 'price' === $field['field_key'] ) {
			// GeoDirectory's predefined price field settings.
			$currency     = (string) $this->get_option( 'g_currency', 'USD' );
			$extra_fields = array(
				'is_price'                  => 1,
				'thousand_separator'        => 'comma',
				'decimal_separator'         => 'period',
				'decimal_display'           => 'if',
				'currency_symbol'           => function_exists( 'atbdp_currency_symbol' ) ? atbdp_currency_symbol( $currency ) : ( 'USD' === $currency ? '$' : $currency ),
				'currency_symbol_placement' => 'after' === $this->get_option( 'g_currency_position', 'before' ) ? 'right' : 'left',
			);
		}

		if ( 'price_range' === $field['field_key'] ) {
			// GeoDirectory's predefined price_range options.
			$field['option_values'] = 'Select Price Range/,$,$$,$$$,$$$$';
		}

		$field['extra_fields'] = ! empty( $extra_fields ) ? maybe_serialize( $extra_fields ) : '';

		if ( 'number' === $field['field_type'] ) {
			// GeoDirectory numbers are text fields with a numeric data type.
			// Directorist rounds number fields to two decimals.
			$field['field_type'] = 'text';

			if ( in_array( $field['field_key'], array( 'package_id', $this->importer_id . '_id' ), true ) ) {
				$field['data_type'] = 'INT';
			} else {
				$field['data_type']     = 'FLOAT';
				$field['decimal_point'] = 2;
			}
		}

		$field['dir_field_key'] = $data['field_key'];

		return $field;
	}

	/**
	 * Get directory to GD post type.
	 *
	 * @since 2.1.3
	 *
	 * @param int  $directory_id Directory ID.
	 * @param bool $is_test      Whether in test mode.
	 *
	 * @return string GD post type.
	 */
	public static function get_dir2gd_post_type( $directory_id, $is_test = false ) {
		$post_type = $directory_id ? get_term_meta( $directory_id, 'gd_post_type', true ) : '';

		if ( ! $post_type && $is_test ) {
			$post_type = 'gd_place';
		}

		return $post_type;
	}

	/**
	 * Get listing GD post type.
	 *
	 * @since 2.1.3
	 *
	 * @param int  $post_id Post ID.
	 * @param bool $is_test Whether in test mode.
	 *
	 * @return string GD post type.
	 */
	public static function get_listing_gd_post_type( $post_id, $is_test = false ) {
		$directory_id = get_post_meta( $post_id, '_directory_type', true );

		return self::get_dir2gd_post_type( $directory_id, $is_test );
	}

	/**
	 * Import directories to GeoDirectory post types.
	 *
	 * @since 2.1.3
	 *
	 * @param array $task Import task.
	 *
	 * @return array|false The next task or false if all tasks are completed.
	 */
	public function task_import_directories( $task ) {
		// Set total number of items to import.
		$this->set_import_total();

		// Log import started.
		$this->log( __( 'Directories: Import started.', 'geodir-converter' ) );

		$directories = $this->get_import_directories();

		if ( empty( $directories ) ) {
			$this->log( __( 'Directories: No directories to import.', 'geodir-converter' ), 'warning' );

			return $this->next_task( $task, true );
		}

		foreach ( $directories as $directory ) {
			$this->import_directory( $directory );
		}

		return $this->next_task( $task, true );
	}

	/**
	 * Import categories from Directorist to GeoDirectory.
	 *
	 * @since 2.1.3
	 *
	 * @param array $task Import task.
	 *
	 * @return array|false The next task or false if all tasks are completed.
	 */
	public function task_import_categories( $task ) {
		// Log import started.
		$this->log( __( 'Categories: Import started.', 'geodir-converter' ) );

		$directories = $this->get_import_directories();

		if ( empty( $directories ) ) {
			$this->log( __( 'Categories: No directories to import.', 'geodir-converter' ), 'warning' );

			return $this->next_task( $task, true );
		}

		foreach ( $directories as $directory ) {
			$this->import_directory_categories( $directory );
		}

		return $this->next_task( $task, true );
	}

	/**
	 * Import tags.
	 *
	 * @since 2.1.3
	 *
	 * @param array $task Task details.
	 *
	 * @return array|false The next task or false if all tasks are completed.
	 */
	public function task_import_tags( array $task ) {
		// Log import started.
		$this->log( __( 'Tags: Import started.', 'geodir-converter' ) );

		$directories = $this->get_import_directories();

		if ( empty( $directories ) ) {
			$this->log( __( 'Tags: No directories to import.', 'geodir-converter' ), 'warning' );

			return $this->next_task( $task, true );
		}

		foreach ( $directories as $directory ) {
			$this->import_directory_tags( $directory );
		}

		return $this->next_task( $task, true );
	}

	/**
	 * Import fields from Directorist to GeoDirectory.
	 *
	 * @since 2.1.3
	 *
	 * @param array $task Task details.
	 *
	 * @return array|false The next task or false if all tasks are completed.
	 */
	public function task_import_fields( $task ) {
		// Log import started.
		$this->log( __( 'Fields: Import started.', 'geodir-converter' ) );

		$directories = $this->get_import_directories();

		if ( empty( $directories ) ) {
			$this->log( __( 'Fields: No directories to import.', 'geodir-converter' ), 'warning' );

			return $this->next_task( $task, true );
		}

		if ( ! defined( 'GEODIR_CONVERT_CF_DIRECTORIST' ) ) {
			define( 'GEODIR_CONVERT_CF_DIRECTORIST', true );
		}

		foreach ( $directories as $directory ) {
			$this->import_directory_fields( $directory, $task );
		}

		return $this->next_task( $task, true );
	}

	/**
	 * Import directory.
	 *
	 * @since 2.1.3
	 *
	 * @param object $directory Directory details.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function import_directory( $directory ) {
		global $gd_cpt_order;

		// Single directory mode: listings go to the post type chosen in the settings.
		if ( ! $this->is_multi_directory_enabled() ) {
			$post_type = $this->resolve_post_type( $directory->term_id );

			if ( empty( $post_type ) ) {
				$this->increase_failed_imports( 5 );

				/* translators: %s: directory name */
				$this->log( wp_sprintf( __( '%s Directory: The selected post type is invalid.', 'geodir-converter' ), $directory->name ), 'error' );

				return false;
			}

			$this->increase_succeed_imports( 5 );

			/* translators: %1$s: directory name, %2$s: post type */
			$this->log( wp_sprintf( __( '%1$s Directory: Listings will be imported to %2$s.', 'geodir-converter' ), $directory->name, $post_type ), 'success' );

			return true;
		}

		if ( empty( $gd_cpt_order ) ) {
			$gd_cpt_order = count( geodir_get_posttypes() ) + 1;
		} else {
			++$gd_cpt_order;
		}

		$post_type = str_replace( '-', '_', sanitize_key( $directory->slug ) );

		if ( empty( $post_type ) ) {
			$post_type = 'dir' . $directory->term_id;
		}

		if ( strpos( $post_type, 'gd_' ) !== 0 ) {
			$post_type = 'gd_' . $post_type;
		}

		// WordPress post type names are limited to 20 characters.
		if ( strlen( $post_type ) > 20 ) {
			$post_type = rtrim( substr( $post_type, 0, 20 ), '_' );
		}

		if ( post_type_exists( $post_type ) ) {
			$this->increase_skipped_imports( 5 );

			// Still link the directory to an existing GD post type, otherwise
			// none of its listings could be imported.
			if ( ! $this->is_test_mode() && geodir_is_gd_post_type( $post_type ) && ! get_term_meta( $directory->term_id, 'gd_post_type', true ) ) {
				update_term_meta( $directory->term_id, 'gd_post_type', $post_type );
			}

			/* translators: %1$s: directory name, %2$s: post type */
			$this->log( wp_sprintf( __( '%1$s Directory: Post type %2$s already exists.', 'geodir-converter' ), $directory->name, $post_type ) );

			return false;
		}

		if ( $this->is_test_mode() ) {
			$this->increase_succeed_imports( 5 );

			/* translators: %1$s: directory name, %2$s: post type */
			$this->log( wp_sprintf( __( '%1$s Directory: Post type %2$s created.', 'geodir-converter' ), $directory->name, $post_type ), 'success' );

			return true;
		}

		$default_preview_image = $this->default_preview_image_src( $directory->term_id );

		if ( is_numeric( $default_preview_image ) ) {
			$default_image = (int) $default_preview_image;
		} elseif ( $default_preview_image ) {
			$default_image = GeoDir_Admin_Import_Export::generate_attachment_id( $default_preview_image );
		} else {
			$default_image = '';
		}

		$data = array(
			'post_type'                => $post_type,
			'slug'                     => $directory->slug,
			'name'                     => $directory->name,
			'singular_name'            => $directory->name,
			'listing_order'            => $gd_cpt_order,
			'default_image'            => $default_image ? $default_image : '',
			'menu_icon'                => '',
			'disable_comments'         => 0,
			'disable_reviews'          => 0,
			'single_review'            => 0,
			'disable_favorites'        => 0,
			'disable_frontend_add'     => 0,
			'supports_events'          => 0,
			'disable_location'         => 0,
			'supports_franchise'       => 0,
			'wpml_duplicate'           => 0,
			'author_posts_private'     => 0,
			'author_favorites_private' => 0,
			'limit_posts'              => '',
			'page_add'                 => '',
			'page_details'             => '',
			'page_archive'             => '',
			'page_archive_item'        => '',
			'classified_features'      => '',
			'link_posts_fields'        => '',
			'label-add_new'            => '',
			'label-add_new_item'       => '',
			'label-edit_item'          => '',
			'label-new_item'           => '',
			'label-view_item'          => '',
			'label-search_items'       => '',
			'label-not_found'          => '',
			'label-not_found_in_trash' => '',
			'label-listing_owner'      => '',
			'description'              => '',
			'seo-title'                => '',
			'seo-meta_title'           => '',
			'seo-meta_description'     => '',
		);

		if ( ! defined( 'GEODIR_EVENT_VERSION' ) ) {
			unset( $data['supports_events'] );
		}

		if ( ! defined( 'GEODIRLOCATION_VERSION' ) ) {
			unset( $data['disable_location'] );
		}

		if ( ! defined( 'GEODIR_FRANCHISE_VERSION' ) ) {
			unset( $data['supports_franchise'] );
		}

		if ( ! defined( 'GEODIR_MULTILINGUAL_VERSION' ) ) {
			unset( $data['wpml_duplicate'] );
		}

		$result = $this->save_post_type( $data );

		if ( is_wp_error( $result ) ) {
			$this->increase_failed_imports( 5 );

			/* translators: %1$s: directory name, %2$s: error message */
			$this->log( wp_sprintf( __( '%1$s Directory: Import failed. %2$s', 'geodir-converter' ), $directory->name, $result->get_error_message() ), 'error' );

			return false;
		} else {
			update_term_meta( $directory->term_id, 'gd_post_type', $post_type );

			$this->increase_succeed_imports( 5 );

			/* translators: %1$s: directory name, %2$s: post type */
			$this->log( wp_sprintf( __( '%1$s Directory: Post type %2$s created.', 'geodir-converter' ), $directory->name, $post_type ), 'success' );

			return true;
		}
	}

	/**
	 * Save post type.
	 *
	 * @since 2.1.3
	 *
	 * @param array $data      Post type data.
	 * @param array $prev_data Previous post type data.
	 *
	 * @return true Always returns true on success.
	 */
	public function save_post_type( $data, $prev_data = array() ) {
		$post_type     = str_replace( '-', '_', sanitize_key( $data['post_type'] ) );
		$name          = sanitize_text_field( $data['name'] );
		$singular_name = sanitize_text_field( $data['singular_name'] );
		$slug          = sanitize_key( $data['slug'] );

		$args                             = array();
		$args['labels']                   = array(
			'name'               => $name,
			'singular_name'      => $singular_name,
			'add_new'            => ! empty( $data['add_new'] ) ? sanitize_text_field( $data['add_new'] ) : _x( 'Add New', $post_type, 'geodirectory' ),
			'add_new_item'       => ! empty( $data['add_new_item'] ) ? sanitize_text_field( $data['add_new_item'] ) : __( 'Add New ' . $singular_name, 'geodirectory' ),
			'edit_item'          => ! empty( $data['edit_item'] ) ? sanitize_text_field( $data['edit_item'] ) : __( 'Edit ' . $singular_name, 'geodirectory' ),
			'new_item'           => ! empty( $data['new_item'] ) ? sanitize_text_field( $data['new_item'] ) : __( 'New ' . $singular_name, 'geodirectory' ),
			'view_item'          => ! empty( $data['view_item'] ) ? sanitize_text_field( $data['view_item'] ) : __( 'View ' . $singular_name, 'geodirectory' ),
			'search_items'       => ! empty( $data['search_items'] ) ? sanitize_text_field( $data['search_items'] ) : __( 'Search ' . $name, 'geodirectory' ),
			'not_found'          => ! empty( $data['not_found'] ) ? sanitize_text_field( $data['not_found'] ) : __( 'No ' . $name . ' found.', 'geodirectory' ),
			'not_found_in_trash' => ! empty( $data['not_found_in_trash'] ) ? sanitize_text_field( $data['not_found_in_trash'] ) : __( 'No ' . $name . ' found in trash.', 'geodirectory' ),
			'listing_owner'      => ! empty( $data['listing_owner'] ) ? sanitize_text_field( $data['listing_owner'] ) : '',
		);
		$args['description']              = ! empty( $data['description'] ) ? trim( $data['description'] ) : '';
		$args['can_export']               = true;
		$args['capability_type']          = 'post';
		$args['has_archive']              = $slug;
		$args['hierarchical']             = false;
		$args['map_meta_cap']             = true;
		$args['public']                   = true;
		$args['query_var']                = true;
		$args['show_in_nav_menus']        = true;
		$args['rewrite']                  = array(
			'slug'         => $slug,
			'with_front'   => false,
			'hierarchical' => true,
			'feeds'        => true,
		);
		$args['supports']                 = array(
			'title',
			'editor',
			'author',
			'thumbnail',
			'excerpt',
			'custom-fields',
			'comments',
			'revisions',
		);
		$args['taxonomies']               = array(
			$post_type . 'category',
			$post_type . '_tags',
		);
		$args['listing_order']            = ! empty( $data['listing_order'] ) ? absint( $data['listing_order'] ) : 0;
		$args['disable_comments']         = ! empty( $data['disable_comments'] ) ? absint( $data['disable_comments'] ) : 0;
		$args['disable_reviews']          = ! empty( $data['disable_reviews'] ) ? absint( $data['disable_reviews'] ) : 0;
		$args['single_review']            = ! empty( $data['single_review'] ) ? absint( $data['single_review'] ) : 0;
		$args['disable_favorites']        = ! empty( $data['disable_favorites'] ) ? absint( $data['disable_favorites'] ) : 0;
		$args['disable_frontend_add']     = ! empty( $data['disable_frontend_add'] ) ? absint( $data['disable_frontend_add'] ) : 0;
		$args['author_posts_private']     = ! empty( $data['author_posts_private'] ) ? absint( $data['author_posts_private'] ) : 0;
		$args['author_favorites_private'] = ! empty( $data['author_favorites_private'] ) ? absint( $data['author_favorites_private'] ) : 0;
		$args['limit_posts']              = ! empty( $data['limit_posts'] ) && $data['limit_posts'] ? (int) $data['limit_posts'] : 0;
		$args['seo']['title']             = ! empty( $data['title'] ) ? sanitize_text_field( $data['title'] ) : '';
		$args['seo']['meta_title']        = ! empty( $data['meta_title'] ) ? sanitize_text_field( $data['meta_title'] ) : '';
		$args['seo']['meta_description']  = ! empty( $data['meta_description'] ) ? sanitize_text_field( $data['meta_description'] ) : '';
		$args['menu_icon']                = ! empty( $data['menu_icon'] ) ? \GeoDir_Post_types::sanitize_menu_icon( $data['menu_icon'] ) : 'dashicons-admin-post';
		$args['default_image']            = ! empty( $data['default_image'] ) ? $data['default_image'] : '';
		$args['page_add']                 = ! empty( $data['page_add'] ) ? (int) $data['page_add'] : 0;
		$args['page_details']             = ! empty( $data['page_details'] ) ? (int) $data['page_details'] : 0;
		$args['page_archive']             = ! empty( $data['page_archive'] ) ? (int) $data['page_archive'] : 0;
		$args['page_archive_item']        = ! empty( $data['page_archive_item'] ) ? (int) $data['page_archive_item'] : 0;
		$args['template_add']             = ! empty( $data['template_add'] ) ? (int) $data['template_add'] : 0;
		$args['template_details']         = ! empty( $data['template_details'] ) ? (int) $data['template_details'] : 0;
		$args['template_archive']         = ! empty( $data['template_archive'] ) ? (int) $data['template_archive'] : 0;

		$save_data               = array();
		$save_data[ $post_type ] = $args;

		$set_vars = array( 'prev_classified_features', 'prev_supports_events', 'prev_supports_franchise', 'prev_disable_location' );
		foreach ( $set_vars as $var ) {
			if ( isset( $data[ $var ] ) ) {
				$_POST[ $var ] = $data[ $var ]; // @codingStandardsIgnoreLine
			}
		}

		$save_data = apply_filters( 'geodir_save_post_type', $save_data, $post_type, $data );

		$current_post_types = geodir_get_option( 'post_types', array() );
		if ( empty( $current_post_types ) ) {
			$post_types = $save_data;
		} else {
			$post_types = array_merge( $current_post_types, $save_data );
		}

		foreach ( $save_data as $_post_type => $_args ) {
			$cpt_before = ! empty( $current_post_types[ $_post_type ] ) ? $current_post_types[ $_post_type ] : array();

			do_action( 'geodir_pre_save_post_type', $_post_type, $_args, $cpt_before );
		}

		// Update custom post types.
		geodir_update_option( 'post_types', $post_types );

		// create tables if needed.
		GeoDir_Admin_Install::create_tables();

		foreach ( $save_data as $_post_type => $_args ) {
			do_action( 'geodir_post_type_saved', $_post_type, $_args, empty( $cpt_before ) );
		}

		$post_types = geodir_get_option( 'post_types', array() );

		foreach ( $save_data as $_post_type => $_args ) {
			$cpt_before = ! empty( $current_post_types[ $_post_type ] ) ? $current_post_types[ $_post_type ] : array();
			$cpt_after  = ! empty( $post_types[ $_post_type ] ) ? $post_types[ $_post_type ] : array();

			do_action( 'geodir_post_type_updated', $_post_type, $cpt_after, $cpt_before );
		}

		// flush rewrite rules
		flush_rewrite_rules();
		do_action( 'geodir_flush_rewrite_rules' );
		wp_schedule_single_event( time(), 'geodir_flush_rewrite_rules' );

		return true;
	}

	/**
	 * Get default preview image source.
	 *
	 * @since 2.1.3
	 *
	 * @param int $directory_id Directory ID.
	 *
	 * @return string Default preview image source.
	 */
	public function default_preview_image_src( $directory_id ) {
		$settings = get_term_meta( $directory_id, 'general_config', true );
		$preview  = is_array( $settings ) && ! empty( $settings['preview_image'] ) ? $settings['preview_image'] : get_term_meta( $directory_id, 'preview_image', true );

		// The builder saves array( 'id' => attachment ID, 'url' => image URL ).
		if ( is_array( $preview ) ) {
			if ( ! empty( $preview['id'] ) && wp_attachment_is_image( (int) $preview['id'] ) ) {
				return (int) $preview['id'];
			}

			$preview = ! empty( $preview['url'] ) ? $preview['url'] : '';
		}

		if ( empty( $preview ) || ! is_string( $preview ) ) {
			$preview = $this->get_option( 'default_preview_image' );
		}

		// Directorist's bundled placeholder is not a site image.
		if ( empty( $preview ) || ! is_string( $preview ) || false !== strpos( $preview, 'directorist/assets/images/grid.jpg' ) ) {
			return '';
		}

		return $preview;
	}

	/**
	 * Import categories from Directorist to GeoDirectory.
	 *
	 * @since 2.1.3
	 *
	 * @param object $directory Directory details.
	 *
	 * @return void|false Void on success or false if skipped.
	 */
	public function import_directory_categories( $directory ) {
		$post_type  = $this->resolve_post_type( $directory->term_id );
		$categories = $this->get_directory_categories( $directory );

		if ( ! geodir_is_gd_post_type( $post_type ) ) {
			// These were counted in the total, so report them.
			$this->increase_skipped_imports( count( $categories ) );

			/* translators: %1$s: directory name, %2$s: directory slug */
			$this->log( wp_sprintf( __( '%1$s Categories: No post type found for directory %2$s.', 'geodir-converter' ), $directory->name, $directory->name ), 'warning' );

			return false;
		}

		if ( empty( $categories ) ) {
			/* translators: %s: directory name */
			$this->log( wp_sprintf( __( '%s Categories: No items to import.', 'geodir-converter' ), $directory->name ), 'warning' );

			return false;
		}

		if ( $this->is_test_mode() ) {
			$this->increase_succeed_imports( count( $categories ) );
			$this->log(
				sprintf(
				/* translators: %1$s: post type name, %2$d: number of imported terms, %3$d: number of failed imports */
					__( '%1$s Categories: Import completed. %2$d imported, %3$d failed.', 'geodir-converter' ),
					$directory->name,
					count( $categories ),
					0
				),
				'success'
			);

			return;
		}

		$result = $this->import_taxonomy_terms(
			$categories,
			$post_type . 'category',
			'ct_cat_top_desc',
			array(
				'importer_id' => $this->importer_id,
				'eq_suffix'   => '_' . $post_type,
			)
		);

		$this->increase_succeed_imports( (int) $result['imported'] );
		$this->increase_failed_imports( (int) $result['failed'] );

		$this->log(
			wp_sprintf(
				/* translators: %1$s: post type name, %2$d: number of imported terms, %3$d: number of failed imports */
				__( '%1$s Categories: Import completed. %2$d imported, %3$d failed.', 'geodir-converter' ),
				$directory->name,
				$result['imported'],
				$result['failed']
			),
			'success'
		);

		return;
	}

	/**
	 * Import tags from Directorist to GeoDirectory.
	 *
	 * @since 2.1.3
	 *
	 * @param object $directory Directory details.
	 *
	 * @return void
	 */
	public function import_directory_tags( $directory ) {
		$post_type = $this->resolve_post_type( $directory->term_id );
		$tags      = $this->get_directory_tags();

		if ( ! geodir_is_gd_post_type( $post_type ) ) {
			// These were counted in the total, so report them.
			$this->increase_skipped_imports( count( $tags ) );

			/* translators: %1$s: directory name, %2$s: directory slug */
			$this->log( wp_sprintf( __( '%1$s Tags: No post type found for directory %2$s.', 'geodir-converter' ), $directory->name, $directory->name ), 'warning' );

			return;
		}

		if ( empty( $tags ) ) {
			/* translators: %s: directory name */
			$this->log( wp_sprintf( __( '%s Tags: No items to import.', 'geodir-converter' ), $directory->name ), 'warning' );

			return;
		}

		if ( $this->is_test_mode() ) {
			$this->increase_succeed_imports( count( $tags ) );
			$this->log(
				sprintf(
				/* translators: %1$s: post type name, %2$d: number of imported terms, %3$d: number of failed imports */
					__( '%1$s Tags: Import completed. %2$d imported, %3$d failed.', 'geodir-converter' ),
					$directory->name,
					count( $tags ),
					0
				),
				'success'
			);

			return;
		}

		$result = $this->import_taxonomy_terms(
			$tags,
			$post_type . '_tags',
			'ct_cat_top_desc',
			array(
				'importer_id' => $this->importer_id,
				'eq_suffix'   => '_' . $post_type,
			)
		);

		$this->increase_succeed_imports( (int) $result['imported'] );
		$this->increase_failed_imports( (int) $result['failed'] );

		$this->log(
			wp_sprintf(
				/* translators: %1$s: post type name, %2$d: number of imported terms, %3$d: number of failed imports */
				__( '%1$s Tags: Import completed. %2$d imported, %3$d failed.', 'geodir-converter' ),
				$directory->name,
				$result['imported'],
				$result['failed']
			),
			'success'
		);

		return;
	}

	/**
	 * Import fields from Directorist to GeoDirectory.
	 *
	 * @since 2.1.3
	 *
	 * @param object $directory Directory details.
	 * @param array  $task      Task details.
	 *
	 * @return bool|false True on success or false if skipped.
	 */
	public function import_directory_fields( $directory, $task ) {
		global $geodir_cf_parent;

		if ( empty( $geodir_cf_parent ) ) {
			$geodir_cf_parent = array();
		}

		$post_type = $this->resolve_post_type( $directory->term_id );

		if ( ! geodir_is_gd_post_type( $post_type ) ) {
			// These were counted in the total, so report them.
			$this->increase_skipped_imports( count( $this->get_directory_fields( $directory->term_id, 'gd_place' ) ) );

			/* translators: %1$s: directory name, %2$s: directory slug */
			$this->log( wp_sprintf( __( '%1$s Fields: No post type found for directory %2$s.', 'geodir-converter' ), $directory->name, $directory->name ), 'warning' );

			return false;
		}

		$fields = $this->get_directory_fields( $directory->term_id, $post_type );

		if ( empty( $fields ) ) {
			/* translators: %s: directory name */
			$this->log( wp_sprintf( __( '%s Fields: No items to import.', 'geodir-converter' ), $directory->name ), 'warning' );

			return false;
		}

		$imported = isset( $task['imported'] ) ? absint( $task['imported'] ) : 0;
		$failed   = isset( $task['failed'] ) ? absint( $task['failed'] ) : 0;
		$skipped  = isset( $task['skipped'] ) ? absint( $task['skipped'] ) : 0;
		$updated  = isset( $task['updated'] ) ? absint( $task['updated'] ) : 0;

		foreach ( $fields as $row ) {
			if ( ! empty( $row['tab_level'] ) && empty( $row['tab_parent'] ) && ! empty( $row['tab_parent_key'] ) ) {
				if ( ! empty( $geodir_cf_parent[ $row['post_type'] ][ $row['tab_parent_key'] ] ) ) {
					$row['tab_parent'] = $geodir_cf_parent[ $row['post_type'] ][ $row['tab_parent_key'] ];
				}
			}

			if ( in_array( $row['field_key'], array( 'directorist_id', 'featured', 'claimed', 'package_id', 'expire_date' ), true ) ) {
				$row['tab_parent'] = 0;
				$row['tab_level']  = 0;
			}

			$field = self::sanitize_custom_field( $row );

			// Invalid.
			if ( is_wp_error( $field ) ) {
				++$failed;

				/* translators: %1$s: directory name, %2$s: field name, %3$s: error message */
				$this->log( wp_sprintf( __( 'Fail to import %1$s field: %2$s. Error: %3$s', 'geodir-converter' ), $directory->name, $row['admin_title'], $field->get_error_message() ), 'error' );

				continue;
			}

			// Skip fields that shouldn't be imported.
			if ( $this->should_skip_field( $field['htmlvar_name'] ) ) {
				++$skipped;

				// $this->log( wp_sprintf( __( '%1$s skipped field: %2$s', 'geodir-converter' ), $directory->name, $field['admin_title'] ), 'warning' );

				continue;
			}

			$exists = ! empty( $field['prev_field_data'] ) && is_array( $field['prev_field_data'] ) ? $field['prev_field_data'] : array();

			if ( $this->is_test_mode() ) {
				$exists ? $updated++ : $imported++;

				continue;
			}

			do_action( 'geodir_cp_pre_import_custom_field_data', $field, $exists );

			// Save post type
			$response = geodir_custom_field_save( $field );

			if ( is_wp_error( $response ) ) {
				++$failed;

				/* translators: %1$s: directory name, %2$s: field name, %3$s: error message */
				$this->log( wp_sprintf( __( 'Fail to import %1$s field: %2$s. Error: %3$s', 'geodir-converter' ), $directory->name, $field['admin_title'], $response->get_error_message() ), 'error' );

				continue;
			}

			$geodir_cf_parent[ $field['post_type'] ][ $field['htmlvar_name'] ] = $response;

			if ( ! empty( $exists ) ) {
				++$updated;
			} else {
				++$imported;
			}

			do_action( 'geodir_cp_after_import_custom_field_data', $field, $exists );
		}

		$this->sync_task_counters( $task, $imported, $failed, $skipped, $updated );

		$this->log(
			wp_sprintf(
				/* translators: %1$s: directory name, %2$d: imported count, %3$d: updated count, %4$d: skipped count, %5$d: failed count */
				__( '%1$s Fields import completed: %2$d imported, %3$d updated, %4$d skipped, %5$d failed.', 'geodir-converter' ),
				$directory->name,
				$imported,
				$updated,
				$skipped,
				$failed
			),
			'success'
		);

		return true;
	}

	/**
	 * Sanitize custom field data.
	 *
	 * @since 2.1.3
	 *
	 * @param array $data The custom field data.
	 * @param bool  $skip Whether to skip the custom field.
	 *
	 * @return array|WP_Error The sanitized custom field data or error.
	 */
	public static function sanitize_custom_field( $data, $skip = false ) {
		$data = array_map( 'trim', $data );

		if ( isset( $data['field_id'] ) ) {
			unset( $data['field_id'] );
		}

		$args        = $data;
		$switch_keys = self::switch_fields_keys();

		$data_keys = array_keys( $args );
		foreach ( $switch_keys as $imp_key => $exp_key ) {
			if ( in_array( $exp_key, $data_keys ) && ! in_array( $imp_key, $data_keys ) ) {
				$args[ $imp_key ] = $args[ $exp_key ];
				unset( $args[ $exp_key ] );
			}
		}

		// Post type.
		$post_type = ! empty( $args['post_type'] ) ? sanitize_text_field( $args['post_type'] ) : null;

		if ( ! ( $post_type && geodir_is_gd_post_type( $post_type ) ) ) {
			return new WP_Error( 'gd_invalid_post_type', __( 'Invalid post type.', 'geodir_custom_posts' ) );
		}

		// htmlvar_name.
		$prev_field_data = array();
		if ( ! empty( $args['htmlvar_name'] ) ) {
			global $geodir_field_infoby;

			$args['htmlvar_name'] = sanitize_text_field( $args['htmlvar_name'] );

			// GeoDirectory caches misses for the whole request, so a field added since
			// (e.g. a new post type's default fields) would still be reported missing.
			if ( is_array( $geodir_field_infoby ) ) {
				unset( $geodir_field_infoby[ 'htmlvar_name::' . $args['htmlvar_name'] . '::' . $post_type . '::1' ] );
			}

			$prev_field_data = geodir_get_field_infoby( 'htmlvar_name', $args['htmlvar_name'], $post_type );

			if ( ! empty( $prev_field_data ) ) {
				$args['prev_field_data'] = $prev_field_data;

				// Skip row on field exists for skip action.
				if ( $skip ) {
					return $args;
				}

				$args['field_id']     = $prev_field_data['id'];
				$args['htmlvar_name'] = $prev_field_data['htmlvar_name'];
			}
		}

		if ( empty( $args['admin_title'] ) && empty( $args['frontend_title'] ) ) {
			return new WP_Error( 'gd_invalid_title', __( 'Missing or invalid admin title & frontend title.', 'geodir_custom_posts' ) );
		}

		if ( ! empty( $args['extra_fields'] ) ) {
			$_extra_fields = $args['extra_fields'];

			// Fields prepared by this importer carry serialized extras, which
			// shortcode_parse_atts() would break apart.
			if ( is_serialized( $_extra_fields ) ) {
				$_extra_fields = maybe_unserialize( $_extra_fields );
			} else {
				$_extra_fields = shortcode_parse_atts( $_extra_fields );
			}

			$_extra_fields = is_array( $_extra_fields ) ? $_extra_fields : array();

			$extra_fields = ! empty( $prev_field_data['extra_fields'] ) ? stripslashes_deep( maybe_unserialize( $prev_field_data['extra_fields'] ) ) : array();
			if ( ! is_array( $extra_fields ) ) {
				$extra_fields = (array) $extra_fields;
			}

			$field_keys = self::extra_fields_keys( true );
			foreach ( $_extra_fields as $key => $value ) {
				if ( isset( $field_keys[ $key ] ) ) {
					$key = $field_keys[ $key ];
				}
				$extra_fields[ $key ] = geodir_clean( $value );
			}
			$args['extra_fields'] = $extra_fields;
		}

		if ( isset( $args['conditional_fields'] ) ) {
			$_conditional_fields = $args['conditional_fields'];
			$_conditional_fields = $_conditional_fields ? shortcode_parse_atts( $_conditional_fields ) : array();
			$conditional_fields  = array( 'TEMP' => array() );

			if ( ! empty( $_conditional_fields ) && is_array( $_conditional_fields ) ) {
				for ( $k = 1; $k <= 10; $k++ ) {
					if ( ! empty( $_conditional_fields[ 'action_' . $k ] ) && ! empty( $_conditional_fields[ 'field_' . $k ] ) && ! empty( $_conditional_fields[ 'condition_' . $k ] ) ) {
						$conditional_fields[] = array(
							'action'    => $_conditional_fields[ 'action_' . $k ],
							'field'     => $_conditional_fields[ 'field_' . $k ],
							'condition' => $_conditional_fields[ 'condition_' . $k ],
							'value'     => ( isset( $_conditional_fields[ 'value_' . $k ] ) ? $_conditional_fields[ 'value_' . $k ] : '' ),
						);
					}
				}
			}
			$args['conditional_fields'] = $conditional_fields;
		}

		if ( ! empty( $prev_field_data ) ) {
			$field_keys = array_keys( $prev_field_data );

			$cf_keys = array( 'field_id', 'post_type', 'admin_title', 'frontend_title', 'field_type', 'field_type_key', 'htmlvar_name', 'frontend_desc', 'clabels', 'default_value', 'db_default', 'placeholder_value', 'sort_order', 'is_active', 'is_default', 'is_required', 'required_msg', 'css_class', 'field_icon', 'show_in', 'option_values', 'packages', 'cat_sort', 'cat_filter', 'data_type', 'extra_fields', 'decimal_point', 'validation_pattern', 'validation_msg', 'for_admin_use', 'add_column', 'data_type' );

			foreach ( $cf_keys as $key ) {
				if ( ! in_array( $key, $data_keys ) && in_array( $key, $field_keys ) ) {
					$args[ $key ] = $prev_field_data[ $key ];
				}
			}
		}

		if ( ! empty( $args['show_in'] ) ) {
			$_show_in = explode( ',', str_replace( array( '[', ']' ), array( '', '' ), sanitize_text_field( $args['show_in'] ) ) );

			$show_in = array();
			foreach ( $_show_in as $key ) {
				$key = trim( $key );
				if ( $key ) {
					$show_in[] = '[' . $key . ']';
				}
			}
			$args['show_in'] = $show_in;
		}

		if ( ! empty( $args['data_type'] ) ) {
			$args['data_type'] = strtoupper( $args['data_type'] );
		}

		if ( ! empty( $args['validation_pattern'] ) ) {
			$args['validation_pattern'] = wp_slash( $args['validation_pattern'] );
		}

		if ( isset( $args['packages'] ) ) {
			$args['show_on_pkg'] = ! empty( $args['packages'] ) ? explode( ',', sanitize_text_field( $args['packages'] ) ) : '';
			unset( $args['packages'] );
		}

		if ( isset( $args['extra_fields'] ) ) {
			$args['extra'] = ! empty( $args['extra_fields'] ) && is_serialized( $args['extra_fields'] ) ? maybe_unserialize( $args['extra_fields'] ) : $args['extra_fields'];
			unset( $args['extra_fields'] );
		}

		if ( ! empty( $args['tab_parent'] ) || ! empty( $args['tab_level'] ) ) {
			if ( ! empty( $args['tab_parent'] ) ) {
				$exists = self::is_field_exists( (int) $args['tab_parent'], $post_type );

				if ( ! empty( $exists ) ) {
					$args['tab_level'] = 1;
				} else {
					$args['tab_parent'] = 0;
					$args['tab_level']  = 0;
				}
			} elseif ( isset( $args['tab_parent'] ) && empty( $args['tab_parent'] ) && ! empty( $args['tab_level'] ) ) {
				$args['tab_level'] = 0;
			}
		}

		return apply_filters( 'geodir_cp_import_sanitize_custom_field', $args, $data, $prev_field_data );
	}

	/**
	 * Switch custom field keys.
	 *
	 * @since 2.1.3
	 *
	 * @param bool $import Whether to import or export.
	 *
	 * @return array Custom field keys.
	 */
	public static function switch_fields_keys( $import = false ) {
		$keys = array(
			'htmlvar_name'      => 'field_key',
			'frontend_desc'     => 'description',
			'placeholder_value' => 'placeholder_text',
			'show_in'           => 'output_location',
			'packages'          => 'package_ids',
			'cat_sort'          => 'show_in_sorting',
			'required_msg'      => 'required_message',
			'validation_msg'    => 'validation_message',
		);

		// Flip during import.
		if ( $import ) {
			$keys = array_flip( $keys );
		}

		return $keys;
	}

	/**
	 * Check if a custom field exists.
	 *
	 * @since 2.1.3
	 *
	 * @param int    $field_id  The ID of the custom field.
	 * @param string $post_type The post type of the custom field.
	 *
	 * @return int The count of the custom field.
	 */
	public static function is_field_exists( $field_id, $post_type ) {
		global $wpdb;

		$exists = $wpdb->get_var( $wpdb->prepare( 'SELECT count(*) FROM `' . GEODIR_CUSTOM_FIELDS_TABLE . '` WHERE `id` = %d AND `post_type` = %s LIMIT 1', array( $field_id, $post_type ) ) );

		return $exists;
	}

	/**
	 * Get extra fields keys.
	 *
	 * @since 2.1.3
	 *
	 * @param bool $import Whether to import or export.
	 *
	 * @return array Extra fields keys.
	 */
	public static function extra_fields_keys( $import = false ) {
		$keys = array(
			'cat_display_type'          => 'category_input_type',
			'city_lable'                => 'city_label',
			'region_lable'              => 'region_label',
			'country_lable'             => 'country_label',
			'neighbourhood_lable'       => 'neighbourhood_label',
			'street2_lable'             => 'street2_label',
			'zip_lable'                 => 'zip_label',
			'map_lable'                 => 'map_label',
			'mapview_lable'             => 'mapview_label',
			'multi_display_type'        => 'multiselect_input_type',
			'currency_symbol_placement' => 'currency_symbol_position',
			'gd_file_types'             => 'allowed_file_types',
			'max_posts'                 => 'link_max_posts',
			'all_posts'                 => 'link_all_posts',
		);

		// Flip during import.
		if ( $import ) {
			$keys = array_flip( $keys );
		}

		return $keys;
	}
}
