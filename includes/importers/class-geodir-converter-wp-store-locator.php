<?php
/**
 * WP Store Locator Converter Class.
 *
 * @since     2.3.0
 * @package   GeoDir_Converter
 */

namespace GeoDir_Converter\Importers;

use WP_Error;
use DateTimeZone;
use GeoDir_Media;
use GeoDir_Converter\Abstracts\GeoDir_Converter_Importer;

defined( 'ABSPATH' ) || exit;

/**
 * Main converter class for importing stores from the WP Store Locator plugin.
 *
 * @since 2.3.0
 */
class GeoDir_Converter_WP_Store_Locator extends GeoDir_Converter_Importer {
	/**
	 * Post type identifier for stores.
	 *
	 * @var string
	 */
	const POST_TYPE_STORE = 'wpsl_stores';

	/**
	 * Taxonomy identifier for store categories.
	 *
	 * @var string
	 */
	const TAX_STORE_CATEGORY = 'wpsl_store_category';

	/**
	 * Option holding the WP Store Locator editor settings (field manager).
	 *
	 * @var string
	 */
	const OPTION_EDITOR = 'wpsl_editor';

	/**
	 * Meta key prefix used by WP Store Locator.
	 *
	 * @var string
	 */
	const META_PREFIX = 'wpsl_';

	/**
	 * WP Store Locator store meta keys.
	 */
	const META_ADDRESS     = 'wpsl_address';
	const META_ADDRESS2    = 'wpsl_address2';
	const META_CITY        = 'wpsl_city';
	const META_STATE       = 'wpsl_state';
	const META_ZIP         = 'wpsl_zip';
	const META_COUNTRY     = 'wpsl_country';
	const META_COUNTRY_ISO = 'wpsl_country_iso';
	const META_LAT         = 'wpsl_lat';
	const META_LNG         = 'wpsl_lng';
	const META_PHONE       = 'wpsl_phone';
	const META_FAX         = 'wpsl_fax';
	const META_EMAIL       = 'wpsl_email';
	const META_URL         = 'wpsl_url';
	const META_HOURS       = 'wpsl_hours';
	const META_TIMEZONE    = 'wpsl_timezone';
	const META_ONLINE      = 'wpsl_online';
	const META_STATUS      = 'wpsl_location_status';
	const META_REOPENS     = 'wpsl_reopens';

	/**
	 * GD field holding the source store ID.
	 *
	 * @var string
	 */
	const FIELD_SOURCE_ID = 'wpsl_id';

	/**
	 * GD fields for the store status, reopening date, online flag and hours note.
	 */
	const FIELD_STORE_STATUS  = 'store_status';
	const FIELD_REOPEN_DATE   = 'reopen_date';
	const FIELD_ONLINE_ONLY   = 'online_only';
	const FIELD_SPECIAL_HOURS = 'special_hours';

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
	protected $importer_id = 'wp-store-locator';

	/**
	 * The store post statuses to import.
	 *
	 * @var array
	 */
	protected $post_statuses = array( 'publish', 'pending', 'draft', 'private' );

	/**
	 * Number of stores per queued import task.
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
	 * GeoDirectory country names keyed by ISO2 code.
	 *
	 * @var array
	 */
	private $country_cache = array();

	/**
	 * Cached results of the source data checks.
	 *
	 * @var array
	 */
	private $source_checks = array();

	/**
	 * Initialize hooks.
	 *
	 * @since 2.3.0
	 *
	 * @return void
	 */
	protected function init() {
	}

	/**
	 * Get importer title.
	 *
	 * @since 2.3.0
	 *
	 * @return string The importer title.
	 */
	public function get_title() {
		return __( 'WP Store Locator', 'geodir-converter' );
	}

	/**
	 * Get importer description.
	 *
	 * @since 2.3.0
	 *
	 * @return string The importer description.
	 */
	public function get_description() {
		return __( 'Import stores, store categories, opening hours, store status and custom fields from your WP Store Locator installation.', 'geodir-converter' );
	}

	/**
	 * Get importer icon URL.
	 *
	 * @since 2.3.0
	 *
	 * @return string The importer icon URL.
	 */
	public function get_icon() {
		return GEODIR_CONVERTER_PLUGIN_URL . 'assets/images/wp-store-locator.png';
	}

	/**
	 * Get importer task action.
	 *
	 * @since 2.3.0
	 *
	 * @return string The initial import action identifier.
	 */
	public function get_action() {
		return self::ACTION_IMPORT_CATEGORIES;
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
			<h6 class="fs-base"><?php esc_html_e( 'WP Store Locator Importer Settings', 'geodir-converter' ); ?></h6>

			<?php
			if ( ! $this->is_wpsl_active() ) {
				if ( $this->count_listings() > 0 ) {
					aui()->alert(
						array(
							'type'    => 'warning',
							'heading' => esc_html__( 'WP Store Locator not detected.', 'geodir-converter' ),
							'content' => esc_html__( 'The WP Store Locator plugin is not active. The importer will still import the stores found in the database. Opening hours saved in the old free-text format can only be converted while the plugin is active; otherwise the text is kept as Special Opening Hours.', 'geodir-converter' ),
							'class'   => 'mb-3',
						),
						true
					);
				} else {
					$this->render_plugin_notice(
						esc_html__( 'WP Store Locator', 'geodir-converter' ),
						'stores',
						esc_url( 'https://wordpress.org/plugins/wp-store-locator/' )
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
	 * Check whether the WP Store Locator plugin is active.
	 *
	 * @since 2.3.0
	 *
	 * @return bool True if WP Store Locator is active.
	 */
	private function is_wpsl_active() {
		return defined( 'WPSL_VERSION_NUM' ) || class_exists( 'WP_Store_locator' );
	}

	/**
	 * Validate importer settings.
	 *
	 * @since 2.3.0
	 *
	 * @param array $settings The settings to validate.
	 * @param array $files    The files to validate.
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
		$total_items  = $this->count_categories();
		$total_items += count( $this->get_custom_fields() );
		$total_items += $this->count_listings();

		$this->increase_imports_total( $total_items );
	}

	/**
	 * Count the store categories.
	 *
	 * @since 2.3.0
	 *
	 * @return int Number of store categories.
	 */
	private function count_categories() {
		global $wpdb;

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s",
				self::TAX_STORE_CATEGORY
			)
		);

		return (int) $count;
	}

	/**
	 * Count the stores to import.
	 *
	 * @since 2.3.0
	 *
	 * @return int Number of stores.
	 */
	private function count_listings() {
		global $wpdb;

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts}
				WHERE post_type = %s
				AND post_status IN (" . implode( ',', array_fill( 0, count( $this->post_statuses ), '%s' ) ) . ')',
				array_merge( array( self::POST_TYPE_STORE ), $this->post_statuses )
			)
		);

		return (int) $count;
	}

	/**
	 * Import store categories from WP Store Locator to GeoDirectory.
	 *
	 * @since 2.3.0
	 *
	 * @param array $task Import task.
	 * @return array|false The next task.
	 */
	public function task_import_categories( $task ) {
		global $wpdb;

		$this->log( __( 'Categories: Import started.', 'geodir-converter' ) );
		$this->set_import_total();

		$categories = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT t.*, tt.*
				FROM {$wpdb->terms} AS t
				INNER JOIN {$wpdb->term_taxonomy} AS tt ON t.term_id = tt.term_id
				WHERE tt.taxonomy = %s
				ORDER BY tt.parent ASC, t.name ASC",
				self::TAX_STORE_CATEGORY
			)
		);

		if ( empty( $categories ) || is_wp_error( $categories ) ) {
			$this->log( __( 'Categories: No items to import.', 'geodir-converter' ), 'warning' );
			return $this->next_task( $task );
		}

		if ( $this->is_test_mode() ) {
			$this->increase_succeed_imports( count( $categories ) );
			$this->log(
				sprintf(
					/* translators: %1$d: number of imported terms, %2$d: number of failed imports */
					__( 'Categories: Import completed. %1$d imported, %2$d failed.', 'geodir-converter' ),
					count( $categories ),
					0
				),
				'success'
			);
			return $this->next_task( $task );
		}

		$post_type = $this->get_import_post_type();
		$result    = $this->import_taxonomy_terms( $this->sort_terms_by_hierarchy( $categories ), $post_type . 'category', 'ct_cat_top_desc' );

		$this->increase_succeed_imports( (int) $result['imported'] );
		$this->increase_failed_imports( (int) $result['failed'] );

		$this->log(
			sprintf(
				/* translators: %1$d: number of imported terms, %2$d: number of failed imports */
				__( 'Categories: Import completed. %1$d imported, %2$d failed.', 'geodir-converter' ),
				$result['imported'],
				$result['failed']
			),
			'success'
		);

		return $this->next_task( $task );
	}

	/**
	 * Order terms so every parent comes before its children.
	 *
	 * import_taxonomy_terms() resolves a term's parent through the parent's
	 * gd_equivalent meta, so the parent has to be imported first at any depth.
	 *
	 * @since 2.3.0
	 *
	 * @param array $terms Term rows.
	 * @return array The ordered term rows.
	 */
	private function sort_terms_by_hierarchy( array $terms ) {
		$children = array();
		$ids      = array();

		foreach ( $terms as $term ) {
			$ids[ (int) $term->term_id ] = true;
		}

		foreach ( $terms as $term ) {
			$parent = (int) $term->parent;

			// Orphans (parent missing from the taxonomy) are treated as top level.
			if ( ! isset( $ids[ $parent ] ) ) {
				$parent = 0;
			}

			$children[ $parent ][] = $term;
		}

		$sorted = array();
		$queue  = array( 0 );

		while ( ! empty( $queue ) ) {
			$parent = array_shift( $queue );

			if ( empty( $children[ $parent ] ) ) {
				continue;
			}

			foreach ( $children[ $parent ] as $term ) {
				$sorted[] = $term;
				$queue[]  = (int) $term->term_id;
			}

			unset( $children[ $parent ] );
		}

		// Anything left sits in a parent loop; import it rather than drop it.
		foreach ( $children as $orphans ) {
			foreach ( $orphans as $term ) {
				$sorted[] = $term;
			}
		}

		return $sorted;
	}

	/**
	 * Get the custom fields to create in GeoDirectory.
	 *
	 * @since 2.3.0
	 *
	 * @return array The custom fields.
	 */
	private function get_custom_fields() {
		if ( null !== $this->custom_fields_cache ) {
			return $this->custom_fields_cache;
		}

		$fields = array(
			array(
				'type'           => 'text',
				'data_type'      => 'INT',
				'field_key'      => self::FIELD_SOURCE_ID,
				'label'          => __( 'WP Store Locator ID', 'geodir-converter' ),
				'description'    => __( 'Original WP Store Locator store ID.', 'geodir-converter' ),
				'placeholder'    => __( 'WP Store Locator ID', 'geodir-converter' ),
				'icon'           => 'far fa-id-card',
				'only_for_admin' => 1,
				'required'       => 0,
			),
			array(
				'type'        => 'phone',
				'field_key'   => 'phone',
				'label'       => __( 'Phone', 'geodir-converter' ),
				'description' => __( 'Store phone number.', 'geodir-converter' ),
				'placeholder' => __( 'Phone', 'geodir-converter' ),
				'icon'        => 'fas fa-phone',
				'required'    => 0,
			),
			array(
				'type'        => 'phone',
				'field_key'   => 'fax',
				'label'       => __( 'Fax', 'geodir-converter' ),
				'description' => __( 'Store fax number.', 'geodir-converter' ),
				'placeholder' => __( 'Fax', 'geodir-converter' ),
				'icon'        => 'fas fa-fax',
				'required'    => 0,
			),
			array(
				'type'        => 'email',
				'field_key'   => 'email',
				'label'       => __( 'Email', 'geodir-converter' ),
				'description' => __( 'Store email address.', 'geodir-converter' ),
				'placeholder' => __( 'Email', 'geodir-converter' ),
				'icon'        => 'far fa-envelope',
				'required'    => 0,
			),
			array(
				'type'        => 'url',
				'field_key'   => 'website',
				'label'       => __( 'Website', 'geodir-converter' ),
				'description' => __( 'Store website URL.', 'geodir-converter' ),
				'placeholder' => __( 'Website', 'geodir-converter' ),
				'icon'        => 'fas fa-globe',
				'required'    => 0,
			),
			array(
				'type'        => 'business_hours',
				'field_key'   => 'business_hours',
				'label'       => __( 'Opening Hours', 'geodir-converter' ),
				'description' => __( 'Store opening hours.', 'geodir-converter' ),
				'icon'        => 'fas fa-clock',
				'required'    => 0,
			),
		);

		// The "Special" note under the opening hours, and free-text hours that can't be converted.
		if ( $this->source_has_special_hours() ) {
			$fields[] = array(
				'type'        => 'textarea',
				'field_key'   => self::FIELD_SPECIAL_HOURS,
				'label'       => __( 'Special Opening Hours', 'geodir-converter' ),
				'description' => __( 'Notes about the opening hours, such as holiday hours.', 'geodir-converter' ),
				'placeholder' => __( 'Special Opening Hours', 'geodir-converter' ),
				'icon'        => 'far fa-calendar-alt',
				'required'    => 0,
			);
		}

		// Temporarily / permanently closed stores and their reopening date.
		if ( $this->source_has_closed_stores() ) {
			$fields[] = array(
				'type'        => 'select',
				'field_key'   => self::FIELD_STORE_STATUS,
				'label'       => __( 'Store Status', 'geodir-converter' ),
				'description' => __( 'Whether the store is open, temporarily closed or permanently closed.', 'geodir-converter' ),
				'placeholder' => __( 'Store Status', 'geodir-converter' ),
				'icon'        => 'fas fa-store',
				'required'    => 0,
				'options'     => implode( ',', $this->get_location_status_options() ),
			);

			$fields[] = array(
				'type'        => 'datepicker',
				'field_key'   => self::FIELD_REOPEN_DATE,
				'label'       => __( 'Reopening Date', 'geodir-converter' ),
				'description' => __( 'The date a temporarily closed store reopens.', 'geodir-converter' ),
				'placeholder' => __( 'Reopening Date', 'geodir-converter' ),
				'icon'        => 'far fa-calendar-check',
				'required'    => 0,
			);
		}

		// Online-only stores (no physical location).
		if ( $this->source_has_online_stores() ) {
			$fields[] = array(
				'type'        => 'checkbox',
				'field_key'   => self::FIELD_ONLINE_ONLY,
				'label'       => __( 'Online Only', 'geodir-converter' ),
				'description' => __( 'The store only operates online.', 'geodir-converter' ),
				'placeholder' => __( 'Online Only', 'geodir-converter' ),
				'icon'        => 'fas fa-shopping-cart',
				'required'    => 0,
			);
		}

		$fields = array_merge( $fields, $this->get_field_manager_fields( wp_list_pluck( $fields, 'field_key' ) ) );

		$this->custom_fields_cache = $fields;

		return $fields;
	}

	/**
	 * Get the extra fields defined through the WP Store Locator field manager.
	 *
	 * The field manager is stored in the wpsl_editor option; each field's value
	 * is kept in the "wpsl_{name}" post meta of the store.
	 *
	 * @since 2.3.0
	 *
	 * @param array $reserved Field keys already claimed by the standard fields.
	 * @return array The custom fields.
	 */
	private function get_field_manager_fields( array $reserved = array() ) {
		$settings = get_option( self::OPTION_EDITOR, array() );

		if ( ! is_array( $settings ) || empty( $settings['field_manager']['groups'] ) || empty( $settings['field_manager']['fields'] ) || ! is_array( $settings['field_manager']['fields'] ) ) {
			return array();
		}

		$type_map = array(
			'text'     => 'text',
			'textarea' => 'textarea',
			'email'    => 'email',
			'tel'      => 'phone',
			'url'      => 'url',
			'checkbox' => 'checkbox',
			'dropdown' => 'select',
		);

		// Meta keys WP Store Locator writes itself; a field manager field can't own them.
		$core_meta = array( 'address', 'address2', 'city', 'state', 'zip', 'country', 'country_iso', 'lat', 'lng', 'hours', 'timezone', 'phone', 'fax', 'email', 'url', 'online', 'location_status', 'reopens', 'exclude_closed', 'location_marker', 'location_marker_active' );
		$fields    = array();

		foreach ( (array) $settings['field_manager']['groups'] as $group_id => $group_name ) {
			if ( empty( $settings['field_manager']['fields'][ $group_id ] ) || ! is_array( $settings['field_manager']['fields'][ $group_id ] ) ) {
				continue;
			}

			foreach ( $settings['field_manager']['fields'][ $group_id ] as $source_field ) {
				if ( empty( $source_field['name'] ) || in_array( $source_field['name'], $core_meta, true ) ) {
					continue;
				}

				$name      = (string) $source_field['name'];
				$field_key = trim( preg_replace( '/[^a-z0-9_]+/', '_', strtolower( $name ) ), '_' );

				if ( '' === $field_key ) {
					continue;
				}

				// A name that clashes with a GeoDirectory column or one of the standard fields
				// would overwrite that value, so keep it under a prefixed key instead.
				if ( in_array( $field_key, $reserved, true ) || in_array( $field_key, $this->get_reserved_field_keys(), true ) || $this->should_skip_field( $field_key ) ) {
					$field_key = self::META_PREFIX . $field_key;
				}

				if ( in_array( $field_key, $reserved, true ) || isset( $fields[ $field_key ] ) ) {
					continue;
				}

				$source_type = isset( $source_field['type'] ) ? (string) $source_field['type'] : 'text';
				$field_type  = isset( $type_map[ $source_type ] ) ? $type_map[ $source_type ] : 'text';
				$label       = ! empty( $source_field['label'] ) ? sanitize_text_field( $source_field['label'] ) : $name;
				$options     = array();

				if ( 'select' === $field_type && ! empty( $source_field['options'] ) ) {
					foreach ( explode( "\n", (string) $source_field['options'] ) as $option ) {
						// WP Store Locator stores the option key, derived from the raw option line
						// the same way as wpsl_alphanum_no_space().
						$option_key = strtolower( preg_replace( '/[^a-zA-Z0-9_-]/', '', str_replace( ' ', '-', $option ) ) );
						$option     = trim( str_replace( ',', ' ', sanitize_text_field( $option ) ) );

						if ( '' === $option || '' === $option_key ) {
							continue;
						}

						$options[ $option_key ] = $option;
					}
				}

				// The field manager default pre-fills new stores; carry it over for the plain field types.
				$default = '';
				if ( in_array( $field_type, array( 'text', 'textarea', 'email', 'phone', 'url' ), true ) && isset( $source_field['default'] ) && is_scalar( $source_field['default'] ) ) {
					$default = sanitize_text_field( $source_field['default'] );
				}

				$fields[ $field_key ] = array(
					'type'        => $field_type,
					'field_key'   => $field_key,
					'label'       => $label,
					'description' => '',
					'placeholder' => $label,
					'icon'        => 'fas fa-info-circle',
					'required'    => 0,
					'default'     => $default,
					'options'     => ! empty( $options ) ? implode( ',', $options ) : '',
					'_options'    => $options,
					'_meta_key'   => self::META_PREFIX . $name,
				);
			}
		}

		return array_values( $fields );
	}

	/**
	 * GeoDirectory detail table columns a field manager field must not write to.
	 *
	 * @since 2.3.0
	 *
	 * @return array Reserved field keys.
	 */
	private function get_reserved_field_keys() {
		return array(
			'post_id',
			'post_title',
			'post_content',
			'post_status',
			'post_tags',
			'post_category',
			'post_images',
			'default_category',
			'featured',
			'featured_image',
			'submit_ip',
			'overall_rating',
			'rating_count',
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
			'neighbourhood',
			'address',
			'package_id',
			'expire_date',
			'claimed',
			self::FIELD_SOURCE_ID,
			self::FIELD_STORE_STATUS,
			self::FIELD_REOPEN_DATE,
			self::FIELD_ONLINE_ONLY,
			self::FIELD_SPECIAL_HOURS,
		);
	}

	/**
	 * Run a cached existence check against the store meta.
	 *
	 * @since 2.3.0
	 *
	 * @param string $key   Cache key.
	 * @param string $where SQL condition on the postmeta row (alias pm), with placeholders.
	 * @param array  $args  Values for the placeholders in $where.
	 * @return bool True if at least one store matches.
	 */
	private function source_meta_exists( $key, $where, array $args = array() ) {
		global $wpdb;

		if ( ! isset( $this->source_checks[ $key ] ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $where only holds placeholders.
			$found = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT 1 FROM {$wpdb->postmeta} AS pm
					INNER JOIN {$wpdb->posts} AS p ON p.ID = pm.post_id
					WHERE p.post_type = %s AND {$where}
					LIMIT 1",
					array_merge( array( self::POST_TYPE_STORE ), $args )
				)
			);

			$this->source_checks[ $key ] = ! empty( $found );
		}

		return $this->source_checks[ $key ];
	}

	/**
	 * Whether any store is temporarily or permanently closed.
	 *
	 * @since 2.3.0
	 *
	 * @return bool
	 */
	private function source_has_closed_stores() {
		return $this->source_meta_exists(
			'closed',
			'pm.meta_key = %s AND pm.meta_value IN ( %s, %s )',
			array( self::META_STATUS, 'temporarily_closed', 'permanently_closed' )
		);
	}

	/**
	 * Whether online-only stores are enabled or used.
	 *
	 * @since 2.3.0
	 *
	 * @return bool
	 */
	private function source_has_online_stores() {
		$settings = get_option( self::OPTION_EDITOR, array() );

		if ( is_array( $settings ) && ! empty( $settings['enable_online_only'] ) ) {
			return true;
		}

		return $this->source_meta_exists(
			'online',
			'pm.meta_key = %s AND pm.meta_value NOT IN ( %s, %s )',
			array( self::META_ONLINE, '', '0' )
		);
	}

	/**
	 * Whether any store has a "special" hours note or free-text (1.x) opening hours.
	 *
	 * @since 2.3.0
	 *
	 * @return bool
	 */
	private function source_has_special_hours() {
		global $wpdb;

		return $this->source_meta_exists(
			'special',
			'pm.meta_key = %s AND pm.meta_value <> %s AND ( pm.meta_value LIKE %s OR pm.meta_value NOT LIKE %s )',
			array(
				self::META_HOURS,
				'',
				'%' . $wpdb->esc_like( 's:7:"special";s:' ) . '%',
				$wpdb->esc_like( 'a:' ) . '%',
			)
		);
	}

	/**
	 * Get the store status labels, keyed by the WP Store Locator status.
	 *
	 * @since 2.3.0
	 *
	 * @return array Status labels.
	 */
	private function get_location_status_options() {
		return array(
			'open'               => __( 'Open', 'geodir-converter' ),
			'temporarily_closed' => __( 'Temporarily closed', 'geodir-converter' ),
			'permanently_closed' => __( 'Permanently closed', 'geodir-converter' ),
		);
	}

	/**
	 * Get the database data type for a field type.
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
			'select'         => 'VARCHAR',
			'datepicker'     => 'DATE',
			'business_hours' => 'TEXT',
		);

		return isset( $type_map[ $field_type ] ) ? $type_map[ $field_type ] : 'VARCHAR';
	}

	/**
	 * Import custom fields to GeoDirectory.
	 *
	 * Fields that already exist on the post type are reused as they are, so an
	 * existing phone, email, website or business hours field is not duplicated
	 * or overwritten.
	 *
	 * @since 2.3.0
	 *
	 * @param array $task Task details.
	 * @return array|false The next task.
	 */
	public function task_import_fields( array $task ) {
		$this->log( __( 'Importing custom fields...', 'geodir-converter' ) );

		$post_type   = $this->get_import_post_type();
		$fields      = $this->get_custom_fields();
		$package_ids = $this->get_package_ids( $post_type );

		if ( empty( $fields ) ) {
			$this->log( __( 'No custom fields to import.', 'geodir-converter' ), 'warning' );
			return $this->next_task( $task );
		}

		$imported = $reused = $skipped = $failed = 0;

		foreach ( $fields as $field ) {
			if ( $this->should_skip_field( $field['field_key'] ) ) {
				++$skipped;
				/* translators: %s: field name */
				$this->log( sprintf( __( 'Skipped custom field: %s', 'geodir-converter' ), $field['label'] ), 'warning' );
				continue;
			}

			if ( $this->field_exists( $field['field_key'], $post_type ) ) {
				++$reused;
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

		$this->increase_succeed_imports( $imported + $reused );
		$this->increase_skipped_imports( $skipped );
		$this->increase_failed_imports( $failed );

		$this->log(
			sprintf(
				/* translators: %1$d: created count, %2$d: reused count, %3$d: skipped count, %4$d: failed count */
				__( 'Fields import completed: %1$d created, %2$d existing reused, %3$d skipped, %4$d failed.', 'geodir-converter' ),
				$imported,
				$reused,
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

		$gd_field = array(
			'post_type'         => $post_type,
			'data_type'         => isset( $field['data_type'] ) ? $field['data_type'] : $this->map_data_type( $field_type ),
			'field_type'        => $field_type,
			'htmlvar_name'      => $field['field_key'],
			'admin_title'       => $field['label'],
			'frontend_title'    => $field['label'],
			'frontend_desc'     => isset( $field['description'] ) ? $field['description'] : '',
			'placeholder_value' => isset( $field['placeholder'] ) ? $field['placeholder'] : '',
			'default_value'     => isset( $field['default'] ) ? $field['default'] : '',
			'is_active'         => '1',
			'for_admin_use'     => ! empty( $field['only_for_admin'] ) ? 1 : 0,
			'is_required'       => isset( $field['required'] ) && 1 === $field['required'] ? 1 : 0,
			'show_in'           => '[detail]',
			'show_on_pkg'       => $package_ids,
			'clabels'           => $field['label'],
			'option_values'     => isset( $field['options'] ) ? $field['options'] : '',
			'field_icon'        => isset( $field['icon'] ) ? $field['icon'] : 'fas fa-info-circle',
		);

		if ( 'business_hours' === $field_type ) {
			$gd_field['field_type_key'] = 'business_hours';
			$gd_field['show_in']        = '[detail],[owntab]';
		}

		return $gd_field;
	}

	/**
	 * Parse stores and queue them for background import.
	 *
	 * @since 2.3.0
	 *
	 * @param array $task The task to import.
	 * @return array|false The updated task, or the next task when done.
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
			$this->log( __( 'Starting stores parsing process...', 'geodir-converter' ) );
		}

		if ( 0 === $total_listings ) {
			$this->log( __( 'No stores found for parsing. Skipping process.', 'geodir-converter' ) );
			return $this->next_task( $task, true );
		}

		$listings = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_title, post_status
				FROM {$wpdb->posts}
				WHERE post_type = %s
				AND post_status IN (" . implode( ',', array_fill( 0, count( $this->post_statuses ), '%s' ) ) . ')
				ORDER BY ID ASC
				LIMIT %d OFFSET %d',
				array_merge(
					array( self::POST_TYPE_STORE ),
					$this->post_statuses,
					array( $batch_size, $offset )
				)
			)
		);

		if ( empty( $listings ) ) {
			$this->log( __( 'Import process completed. No more stores found.', 'geodir-converter' ) );
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
	 * Import a batch of stores (called by the background process).
	 *
	 * @since 2.3.0
	 *
	 * @param array $task The task to import.
	 * @return bool Always false, so the queue drops the completed task.
	 */
	public function task_import_listings( $task ) {
		$listings = isset( $task['listings'] ) && ! empty( $task['listings'] ) ? (array) $task['listings'] : array();

		// "Retry Failed" queues one task per store, carrying only its source ID.
		if ( empty( $listings ) && ! empty( $task['source_id'] ) ) {
			$listings = array(
				(object) array(
					'ID'         => absint( $task['source_id'] ),
					'post_title' => ! empty( $task['title'] ) ? $task['title'] : get_the_title( absint( $task['source_id'] ) ),
				),
			);
		}

		return $this->import_queued_items(
			$listings,
			function ( $listing ) {
				return $this->import_single_listing( $listing );
			},
			array(
				'item_type' => 'store',
			)
		);
	}

	/**
	 * Convert a single WP Store Locator store to a GeoDirectory listing.
	 *
	 * @since 2.3.0
	 *
	 * @param object $listing The queued store row.
	 * @return int Import status.
	 */
	private function import_single_listing( $listing ) {
		$post = get_post( $listing->ID );

		if ( ! $post ) {
			return self::IMPORT_STATUS_FAILED;
		}

		$post_type  = $this->get_import_post_type();
		$gd_post_id = ! $this->is_test_mode() ? $this->get_gd_listing_id( $post->ID, self::FIELD_SOURCE_ID, $post_type ) : false;
		$is_update  = ! empty( $gd_post_id );
		$post_meta  = $this->get_post_meta( $post->ID );
		$categories = $this->get_listings_terms( $post->ID, self::TAX_STORE_CATEGORY );
		$location   = $this->get_store_location( $post, $post_meta );
		$status     = $this->get_location_status( $post_meta );

		$listing_data = array(
			// Standard WP fields.
			'post_author'           => $post->post_author ? $post->post_author : $this->get_import_setting( 'wp_author_id', get_current_user_id() ),
			'post_title'            => $post->post_title,
			'post_content'          => $post->post_content ? $post->post_content : '',
			'post_content_filtered' => $post->post_content,
			'post_excerpt'          => $post->post_excerpt ? $post->post_excerpt : '',
			'post_status'           => $this->map_post_status( $post->post_status, $status['status'] ),
			'post_type'             => $post_type,
			'comment_status'        => $post->comment_status,
			'ping_status'           => $post->ping_status,
			'post_name'             => $post->post_name ? $post->post_name : 'store-' . $post->ID,
			'post_date_gmt'         => $post->post_date_gmt,
			'post_date'             => $post->post_date,
			'post_modified_gmt'     => $post->post_modified_gmt,
			'post_modified'         => $post->post_modified,
			'tax_input'             => array(
				"{$post_type}category" => $categories,
				"{$post_type}_tags"    => array(),
			),

			// GD fields.
			'default_category'      => ! empty( $categories ) ? $categories[0] : 0,
			'featured_image'        => $this->get_featured_image( $post->ID ),
			'submit_ip'             => '',
			'overall_rating'        => 0,
			'rating_count'          => 0,

			'street'                => $location['street'],
			'street2'               => $location['street2'],
			'city'                  => $location['city'],
			'region'                => $location['region'],
			'country'               => $location['country'],
			'zip'                   => $location['zip'],
			'latitude'              => $location['latitude'],
			'longitude'             => $location['longitude'],
			'mapview'               => '',
			'mapzoom'               => '',

			// WP Store Locator fields.
			self::FIELD_SOURCE_ID   => $post->ID,
			'phone'                 => $this->get_meta_text( $post_meta, self::META_PHONE ),
			'fax'                   => $this->get_meta_text( $post_meta, self::META_FAX ),
			'email'                 => sanitize_email( $this->get_meta_text( $post_meta, self::META_EMAIL ) ),
			'website'               => esc_url_raw( $this->get_meta_text( $post_meta, self::META_URL ) ),
		);

		$hours = $this->format_business_hours( $post, $post_meta, $location['country'] );

		// Always set, so a re-run clears hours that were removed from the store.
		$listing_data['business_hours']            = $hours['business_hours'];
		$listing_data[ self::FIELD_SPECIAL_HOURS ] = $hours['special'];

		$status_labels = $this->get_location_status_options();

		$listing_data[ self::FIELD_STORE_STATUS ] = $status_labels[ $status['status'] ];
		$listing_data[ self::FIELD_ONLINE_ONLY ]  = ! empty( $post_meta[ self::META_ONLINE ] ) ? 1 : 0;

		// An empty DATE is stored as 0000-00-00, so it is only written to clear an earlier import.
		if ( '' !== $status['reopens'] || $is_update ) {
			$listing_data[ self::FIELD_REOPEN_DATE ] = $status['reopens'];
		}

		foreach ( $this->process_field_manager_values( $post_meta ) as $key => $value ) {
			$listing_data[ $key ] = $value;
		}

		if ( $this->is_test_mode() ) {
			return self::IMPORT_STATUS_SUCCESS;
		}

		// Delete existing media if updating.
		if ( $is_update ) {
			GeoDir_Media::delete_files( (int) $gd_post_id, 'post_images' );
		}

		$listing_data['post_images'] = $this->get_post_images( $post->ID );

		if ( $is_update ) {
			$gd_post_id = wp_update_post( array_merge( array( 'ID' => $gd_post_id ), $listing_data ), true );
		} else {
			$gd_post_id = wp_insert_post( $listing_data, true );
		}

		if ( is_wp_error( $gd_post_id ) ) {
			$this->log(
				sprintf(
					/* translators: 1: store title, 2: store ID, 3: error message */
					__( 'Store "%1$s" (#%2$d) could not be saved: %3$s', 'geodir-converter' ),
					$post->post_title,
					$post->ID,
					$gd_post_id->get_error_message()
				),
				'error'
			);
			return self::IMPORT_STATUS_FAILED;
		}

		return $is_update ? self::IMPORT_STATUS_UPDATED : self::IMPORT_STATUS_SUCCESS;
	}

	/**
	 * Read a scalar meta value as trimmed text.
	 *
	 * @since 2.3.0
	 *
	 * @param array  $post_meta Post meta data.
	 * @param string $key       Meta key.
	 * @return string The meta value, or an empty string.
	 */
	private function get_meta_text( array $post_meta, $key ) {
		if ( ! isset( $post_meta[ $key ] ) || ! is_scalar( $post_meta[ $key ] ) ) {
			return '';
		}

		return trim( (string) $post_meta[ $key ] );
	}

	/**
	 * Resolve a store's location status and reopening date.
	 *
	 * A temporarily closed store whose reopening date has passed is open again,
	 * the same way WP Store Locator treats it.
	 *
	 * @since 2.3.0
	 *
	 * @param array $post_meta Post meta data.
	 * @return array {
	 *     @type string $status  One of open, temporarily_closed or permanently_closed.
	 *     @type string $reopens Reopening date (Y-m-d), or an empty string.
	 * }
	 */
	private function get_location_status( array $post_meta ) {
		$status  = $this->get_meta_text( $post_meta, self::META_STATUS );
		$reopens = $this->get_meta_text( $post_meta, self::META_REOPENS );
		$labels  = $this->get_location_status_options();

		if ( ! isset( $labels[ $status ] ) ) {
			$status = 'open';
		}

		$reopen_date = '';

		if ( 'temporarily_closed' === $status && is_numeric( $reopens ) && (int) $reopens > 0 ) {
			if ( (int) $reopens <= time() ) {
				$status = 'open';
			} else {
				// Stored as midnight UTC of the picked date.
				$reopen_date = gmdate( 'Y-m-d', (int) $reopens );
			}
		}

		return array(
			'status'  => $status,
			'reopens' => $reopen_date,
		);
	}

	/**
	 * Map a store's post status to a GeoDirectory post status.
	 *
	 * A published store that is permanently closed becomes a "Closed down"
	 * listing, which GeoDirectory keeps out of searches and archives.
	 *
	 * @since 2.3.0
	 *
	 * @param string $post_status     The store post status.
	 * @param string $location_status The WP Store Locator location status.
	 * @return string The GeoDirectory post status.
	 */
	private function map_post_status( $post_status, $location_status ) {
		if ( 'publish' === $post_status && 'permanently_closed' === $location_status && get_post_status_object( 'gd-closed' ) ) {
			return 'gd-closed';
		}

		return $post_status;
	}

	/**
	 * Build the GeoDirectory location for a store.
	 *
	 * WP Store Locator geocodes every store on save, so the stored address and
	 * coordinates are used as they are. A reverse geocode only runs when the
	 * store has coordinates but lacks the city or country GeoDirectory needs.
	 *
	 * @since 2.3.0
	 *
	 * @param \WP_Post $post      The store post.
	 * @param array    $post_meta Post meta data.
	 * @return array The location data.
	 */
	private function get_store_location( $post, array $post_meta ) {
		$default_location = $this->get_default_location();

		$latitude  = $this->get_meta_text( $post_meta, self::META_LAT );
		$longitude = $this->get_meta_text( $post_meta, self::META_LNG );

		$has_coordinates = is_numeric( $latitude ) && is_numeric( $longitude )
			&& ! ( 0.0 === (float) $latitude && 0.0 === (float) $longitude );

		$location = array(
			'street'    => $this->get_meta_text( $post_meta, self::META_ADDRESS ),
			'street2'   => $this->get_meta_text( $post_meta, self::META_ADDRESS2 ),
			'city'      => $this->get_meta_text( $post_meta, self::META_CITY ),
			'region'    => $this->get_meta_text( $post_meta, self::META_STATE ),
			'country'   => $this->get_country_name( $post_meta ),
			'zip'       => $this->get_meta_text( $post_meta, self::META_ZIP ),
			'latitude'  => $has_coordinates ? $latitude : $default_location['latitude'],
			'longitude' => $has_coordinates ? $longitude : $default_location['longitude'],
		);

		// Test mode writes nothing, including the geocoder's cache, so no lookup is made.
		if ( $has_coordinates && ! $this->is_test_mode() && ( '' === $location['city'] || '' === $location['country'] ) ) {
			$geocoded = $this->geocode_location( $latitude, $longitude, array(), $post->ID );

			foreach ( array( 'city', 'region', 'country', 'zip' ) as $key ) {
				if ( '' === $location[ $key ] && ! empty( $geocoded[ $key ] ) ) {
					$location[ $key ] = $geocoded[ $key ];
				}
			}

			if ( '' === $location['street'] && ! empty( $geocoded['address'] ) ) {
				$location['street'] = $geocoded['address'];
			}
		}

		if ( ! $has_coordinates ) {
			$this->log(
				sprintf(
					/* translators: 1: store title, 2: store ID */
					__( 'Store "%1$s" (#%2$d) has no coordinates; the default location coordinates were used.', 'geodir-converter' ),
					$post->post_title,
					$post->ID
				),
				'warning'
			);

			foreach ( array( 'city', 'region', 'country' ) as $key ) {
				if ( '' === $location[ $key ] ) {
					$location[ $key ] = $default_location[ $key ];
				}
			}
		}

		return $location;
	}

	/**
	 * Resolve the store country to the name GeoDirectory uses.
	 *
	 * The ISO code is preferred, since the stored country name is whatever the
	 * geocoder returned and may not match GeoDirectory's country list.
	 *
	 * @since 2.3.0
	 *
	 * @param array $post_meta Post meta data.
	 * @return string The country name.
	 */
	private function get_country_name( array $post_meta ) {
		$country = $this->get_meta_text( $post_meta, self::META_COUNTRY );
		$iso     = strtoupper( $this->get_meta_text( $post_meta, self::META_COUNTRY_ISO ) );

		// geodir_rest_country_by_iso2() passes a string 'where' that get_countries() ignores, so query directly.
		if ( preg_match( '/^[A-Z]{2}$/', $iso ) && function_exists( 'wp_country_database' ) ) {
			if ( ! isset( $this->country_cache[ $iso ] ) ) {
				$rows = wp_country_database()->get_countries(
					array(
						'fields' => array( 'name' ),
						'where'  => array( 'alpha2Code' => $iso ),
						'limit'  => 1,
					)
				);

				$this->country_cache[ $iso ] = ! empty( $rows[0]->name ) ? $rows[0]->name : '';
			}

			if ( '' !== $this->country_cache[ $iso ] ) {
				return $this->country_cache[ $iso ];
			}
		}

		return $country;
	}

	/**
	 * Convert WP Store Locator opening hours to the GeoDirectory business_hours format.
	 *
	 * WP Store Locator stores a serialized array keyed by week day, each holding
	 * a list of "open,close" periods ( "9:00 AM,5:00 PM" or "09:00,17:00" ); an
	 * empty list means closed. A free-text "special" note may also be present.
	 * Stores upgraded from 1.x can still hold a free-text string instead.
	 *
	 * GeoDirectory expects: ["Mo 09:00-17:00","Tu 09:00-12:00,13:00-17:00"],["UTC":"+01:00","Timezone":"Europe/Amsterdam"]
	 * A period that closes before it opens runs past midnight, which GeoDirectory
	 * reads the same way.
	 *
	 * @since 2.3.0
	 *
	 * @param \WP_Post $post      The store post.
	 * @param array    $post_meta Post meta data.
	 * @param string   $country   The store country, used to resolve the timezone.
	 * @return array {
	 *     @type string $business_hours GeoDirectory business_hours value, or an empty string.
	 *     @type string $special        The "special" hours note, or free text that couldn't be converted.
	 * }
	 */
	private function format_business_hours( $post, array $post_meta, $country = '' ) {
		$result = array(
			'business_hours' => '',
			'special'        => '',
		);

		$hours = isset( $post_meta[ self::META_HOURS ] ) ? $post_meta[ self::META_HOURS ] : '';

		if ( empty( $hours ) ) {
			return $result;
		}

		// Free-text hours from 1.x: reuse WP Store Locator's own parser while the plugin is active.
		if ( is_string( $hours ) ) {
			$parsed = false;

			if ( class_exists( 'WPSL\Core\Hours\Legacy_Converter' ) ) {
				$converter = new \WPSL\Core\Hours\Legacy_Converter();
				$parsed    = $converter->parse( $hours, '24' );
			}

			if ( ! is_array( $parsed ) ) {
				// Keep the text rather than lose it.
				$result['special'] = sanitize_textarea_field( $hours );

				$this->log(
					sprintf(
						/* translators: 1: store title, 2: store ID */
						__( 'Store "%1$s" (#%2$d): free-text opening hours could not be converted and were saved as Special Opening Hours.', 'geodir-converter' ),
						$post->post_title,
						$post->ID
					),
					'warning'
				);

				return $result;
			}

			$hours = $parsed;
		}

		if ( ! is_array( $hours ) ) {
			return $result;
		}

		if ( ! empty( $hours['special'] ) && is_string( $hours['special'] ) ) {
			$result['special'] = sanitize_textarea_field( $hours['special'] );
		}

		$day_abbr_map = array(
			'monday'    => 'Mo',
			'tuesday'   => 'Tu',
			'wednesday' => 'We',
			'thursday'  => 'Th',
			'friday'    => 'Fr',
			'saturday'  => 'Sa',
			'sunday'    => 'Su',
		);

		$days_parts = array();

		foreach ( $day_abbr_map as $day_key => $abbr ) {
			if ( empty( $hours[ $day_key ] ) ) {
				continue; // Closed.
			}

			// Hours saved through the REST API can hold a single period as a string.
			$day_periods = is_array( $hours[ $day_key ] ) ? $hours[ $day_key ] : array( $hours[ $day_key ] );
			$periods     = array();

			foreach ( $day_periods as $period ) {
				if ( ! is_string( $period ) || false === strpos( $period, ',' ) ) {
					continue;
				}

				list( $open, $close ) = array_map( 'trim', explode( ',', $period, 2 ) );

				$open  = $this->convert_to_24h( $open );
				$close = $this->convert_to_24h( $close );

				if ( '' === $open || '' === $close ) {
					continue;
				}

				// Opening and closing at the same time means open around the clock.
				if ( $open === $close ) {
					$open  = '00:00';
					$close = '00:00';
				}

				$periods[] = $open . '-' . $close;
			}

			if ( ! empty( $periods ) ) {
				$days_parts[] = $abbr . ' ' . implode( ',', array_unique( $periods ) );
			}
		}

		if ( empty( $days_parts ) ) {
			return $result;
		}

		$value = '["' . implode( '","', $days_parts ) . '"],' . $this->get_timezone_property( $post_meta );

		if ( function_exists( 'geodir_sanitize_business_hours' ) ) {
			$value = geodir_sanitize_business_hours( $value, $country );
		}

		$result['business_hours'] = $value;

		return $result;
	}

	/**
	 * Build the timezone part of the business_hours value.
	 *
	 * WP Store Locator keeps an optional per-store timezone, either a named
	 * zone ( "Europe/Amsterdam" ) or a manual offset ( "UTC+9.5" ). Without one
	 * the store follows the site timezone.
	 *
	 * @since 2.3.0
	 *
	 * @param array $post_meta Post meta data.
	 * @return string The timezone property, e.g. ["Timezone":"Europe/Amsterdam"].
	 */
	private function get_timezone_property( array $post_meta ) {
		$timezone = $this->get_meta_text( $post_meta, self::META_TIMEZONE );

		if ( '' !== $timezone && in_array( $timezone, DateTimeZone::listIdentifiers(), true ) ) {
			return '["Timezone":"' . $timezone . '"]';
		}

		if ( preg_match( '/^UTC([+-]\d{1,2}(?:\.\d+)?)$/i', $timezone, $match ) ) {
			return '["UTC":"' . $this->format_utc_offset( $match[1] ) . '"]';
		}

		$site_timezone = get_option( 'timezone_string' );

		if ( ! empty( $site_timezone ) && in_array( $site_timezone, DateTimeZone::listIdentifiers(), true ) ) {
			return '["Timezone":"' . $site_timezone . '"]';
		}

		return '["UTC":"' . $this->format_utc_offset( get_option( 'gmt_offset', 0 ) ) . '"]';
	}

	/**
	 * Format a decimal hour offset ( 5.5, -3, "+9.75" ) as "+05:30".
	 *
	 * GeoDirectory reads a "." in the offset as the hour/minute separator, so
	 * "+5.5" would become five hours and five minutes.
	 *
	 * @since 2.3.0
	 *
	 * @param mixed $offset The offset in hours.
	 * @return string The offset as +HH:MM.
	 */
	private function format_utc_offset( $offset ) {
		$offset  = (float) $offset;
		$sign    = $offset < 0 ? '-' : '+';
		$minutes = (int) round( abs( $offset ) * 60 );

		return sprintf( '%s%02d:%02d', $sign, floor( $minutes / 60 ), $minutes % 60 );
	}

	/**
	 * Convert a WP Store Locator time to 24-hour HH:MM.
	 *
	 * @since 2.3.0
	 *
	 * @param string $time Time such as "9:00 AM", "12:30 PM" or "17:00".
	 * @return string Time in 24-hour HH:MM format, or an empty string when unreadable.
	 */
	private function convert_to_24h( $time ) {
		$time = strtoupper( trim( (string) $time ) );

		if ( ! preg_match( '/^(\d{1,2})(?:[:.](\d{2}))?\s*(AM|PM)?$/', $time, $match ) ) {
			return '';
		}

		$hour     = (int) $match[1];
		$minute   = isset( $match[2] ) && '' !== $match[2] ? (int) $match[2] : 0;
		$meridiem = isset( $match[3] ) ? $match[3] : '';

		if ( $minute > 59 ) {
			return '';
		}

		if ( '' !== $meridiem ) {
			if ( $hour < 1 || $hour > 12 ) {
				return '';
			}

			if ( 'AM' === $meridiem ) {
				$hour = ( 12 === $hour ) ? 0 : $hour;
			} else {
				$hour = ( 12 === $hour ) ? 12 : $hour + 12;
			}
		} elseif ( $hour > 24 || ( 24 === $hour && 0 !== $minute ) ) {
			return '';
		}

		// A 24:00 close is midnight, which GeoDirectory reads as the end of the day.
		if ( 24 === $hour ) {
			$hour = 0;
		}

		return sprintf( '%02d:%02d', $hour, $minute );
	}

	/**
	 * Map field manager meta values onto their GeoDirectory fields.
	 *
	 * @since 2.3.0
	 *
	 * @param array $post_meta Post meta data.
	 * @return array Field values keyed by GeoDirectory field key.
	 */
	private function process_field_manager_values( array $post_meta ) {
		$values = array();

		foreach ( $this->get_custom_fields() as $field ) {
			if ( empty( $field['_meta_key'] ) ) {
				continue;
			}

			// WP Store Locator deletes the meta of an empty field (and an unchecked
			// checkbox), so a missing value is set empty to clear it on a re-run.
			if ( ! isset( $post_meta[ $field['_meta_key'] ] ) ) {
				$values[ $field['field_key'] ] = 'checkbox' === $field['type'] ? 0 : '';
				continue;
			}

			$value = $post_meta[ $field['_meta_key'] ];

			if ( is_array( $value ) ) {
				$value = implode( ',', array_map( 'trim', array_filter( $value, 'is_scalar' ) ) );
			}

			if ( ! is_scalar( $value ) ) {
				continue;
			}

			switch ( $field['type'] ) {
				case 'checkbox':
					$value = ! empty( $value ) ? 1 : 0;
					break;
				case 'select':
					// The stored value is the option key; GeoDirectory keeps the label.
					if ( isset( $field['_options'][ $value ] ) ) {
						$value = $field['_options'][ $value ];
					}
					break;
				case 'email':
					$value = sanitize_email( $value );
					break;
				case 'url':
					$value = esc_url_raw( $value );
					break;
			}

			$values[ $field['field_key'] ] = $value;
		}

		return $values;
	}

	/**
	 * Get the store images: WP Store Locator only uses the featured image.
	 *
	 * @since 2.3.0
	 *
	 * @param int $post_id The store post ID.
	 * @return string Formatted images string for GeoDirectory.
	 */
	private function get_post_images( $post_id ) {
		$thumbnail_id = (int) get_post_thumbnail_id( $post_id );

		if ( ! $thumbnail_id ) {
			return '';
		}

		return $this->format_images_data(
			array(
				array(
					'id'      => $thumbnail_id,
					'caption' => get_post_meta( $thumbnail_id, '_wp_attachment_image_alt', true ),
					'weight'  => 1,
				),
			)
		);
	}

	/**
	 * Get the featured image URL.
	 *
	 * @since 2.3.0
	 *
	 * @param int $post_id The post ID.
	 * @return string The featured image URL.
	 */
	private function get_featured_image( $post_id ) {
		$image = wp_get_attachment_image_src( get_post_thumbnail_id( $post_id ), 'full' );
		return isset( $image[0] ) ? esc_url( $image[0] ) : '';
	}

	/**
	 * Get the GeoDirectory term IDs matching a store's categories.
	 *
	 * @since 2.3.0
	 *
	 * @param int    $post_id  The post ID.
	 * @param string $taxonomy The source taxonomy.
	 * @return array Array of GD term IDs.
	 */
	private function get_listings_terms( $post_id, $taxonomy ) {
		global $wpdb;

		$candidate_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT tm.meta_value
				FROM {$wpdb->terms} t
				INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
				INNER JOIN {$wpdb->term_relationships} tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
				INNER JOIN {$wpdb->termmeta} tm ON t.term_id = tm.term_id AND tm.meta_key = 'gd_equivalent'
				WHERE tr.object_id = %d AND tt.taxonomy = %s",
				$post_id,
				$taxonomy
			)
		);

		$candidate_ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $candidate_ids ) ) ) );

		if ( empty( $candidate_ids ) ) {
			return array();
		}

		// Keep only GD terms that still exist.
		$valid_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT term_id FROM {$wpdb->terms} WHERE term_id IN (" . implode( ',', array_fill( 0, count( $candidate_ids ), '%d' ) ) . ')',
				$candidate_ids
			)
		);

		$valid_ids_map = array_flip( array_map( 'intval', $valid_ids ) );

		return array_values(
			array_filter(
				$candidate_ids,
				function ( $id ) use ( $valid_ids_map ) {
					return isset( $valid_ids_map[ $id ] );
				}
			)
		);
	}
}
