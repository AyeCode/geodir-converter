<?php
/**
 * EDirectory Converter Class.
 *
 * @since      2.0.2
 * @package    GeoDir_Converter
 * @version    2.0.2
 */

namespace GeoDir_Converter\Importers;

use WP_User;
use WP_Error;
use WP_Query;
use Exception;
use DateTime;
use DateTimeZone;
use GeoDir_Media;
use WPInv_Invoice;
use GetPaid_Form_Item;
use GeoDir_Pricing_Package;
use GeoDir_Converter\Abstracts\GeoDir_Converter_Importer;

defined( 'ABSPATH' ) || exit;

/**
 * Main converter class for importing from eDirectory.
 *
 * @since 2.0.2
 */
class GeoDir_Converter_EDirectory extends GeoDir_Converter_Importer {

	/**
	 * Action identifier for importing comments.
	 *
	 * @var string
	 */
	private const ACTION_IMPORT_COMMENTS = 'comments';

	/**
	 * Action identifier for importing pages.
	 *
	 * @var string
	 */
	private const ACTION_IMPORT_PAGES = 'pages';

	/**
	 * Post type identifier for listings.
	 *
	 * @var string
	 */
	private const MODULE_TYPE_CLASSIFIED = 'classified';

	/**
	 * Post type identifier for listings.
	 *
	 * @var string
	 */
	private const MODULE_TYPE_LISTING = 'listing';

	/**
	 * Post type identifier for blogs.
	 *
	 * @var string
	 */
	private const MODULE_TYPE_BLOG = 'blog';

	/**
	 * Post type identifier for events.
	 *
	 * @var string
	 */
	private const MODULE_TYPE_EVENT = 'event';

	/**
	 * Post type identifier for events.
	 *
	 * @var string
	 */
	private const MODULE_TYPE_DEAL = 'deal';

	/**
	 * Post type identifier for events.
	 *
	 * @var string
	 */
	private const MODULE_TYPE_ARTICLE = 'article';

	/**
	 * Post type identifier for events.
	 *
	 * @var string
	 */
	private const POST_TYPE_EVENTS = 'gd_event';

	/**
	 * The modules to import.
	 *
	 * @var array
	 */
	private $modules = array(
		self::MODULE_TYPE_LISTING,
		self::MODULE_TYPE_EVENT,
		self::MODULE_TYPE_BLOG,
	);

	/**
	 * The single instance of the class.
	 *
	 * @var static
	 */
	protected static $instance;

	/**
	 * API base URL.
	 *
	 * @var string
	 */
	private $base_url;

	/**
	 * API token.
	 *
	 * @var string
	 */
	private $api_token;

	/**
	 * Default request timeout in seconds.
	 *
	 * @var int
	 */
	private $timeout = 15;

	/**
	 * The importer ID.
	 *
	 * @var string
	 */
	protected $importer_id = 'edirectory';

	/**
	 * Initialize hooks.
	 *
	 * @since 1.0.0
	 */
	protected function init() {
		$this->base_url  = rtrim( $this->get_import_setting( 'edirectory_site_url' ), '/' );
		$this->api_token = $this->get_import_setting( 'edirectory_api_key' );
	}

	/**
	 * Get class instance.
	 *
	 * @return static
	 */
	public static function instance() {
		if ( null === static::$instance ) {
			static::$instance = new static();
		}
		return static::$instance;
	}

	/**
	 * Get importer title.
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'eDirectory', 'geodir-converter' );
	}

	/**
	 * Get importer description.
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'Import listings, events, users and invoices from your eDirectory installation.', 'geodir-converter' );
	}

	/**
	 * Get importer icon URL.
	 *
	 * @return string
	 */
	public function get_icon() {
		return GEODIR_CONVERTER_PLUGIN_URL . 'assets/images/edirectory.png';
	}

	/**
	 * Get importer task action.
	 *
	 * @return string
	 */
	public function get_action() {
		return self::ACTION_IMPORT_CATEGORIES;
	}

	/**
	 * Render importer settings.
	 */
	public function render_settings() {
		$import_settings  = (array) $this->options_handler->get_option( 'import_settings', array() );
		$default_settings = array(
			'wp_author_id'        => '',
			'edirectory_site_url' => '',
			'edirectory_api_key'  => '',
			'edirectory_modules'  => array(
				self::MODULE_TYPE_LISTING,
			),
		);

		$settings = wp_parse_args( array_map( 'sanitize_text_field', $import_settings ), $default_settings );
		$modules  = array(
			self::MODULE_TYPE_LISTING => esc_html__( 'Listings', 'geodir-converter' ),
			self::MODULE_TYPE_EVENT   => esc_html__( 'Events', 'geodir-converter' ),
			self::MODULE_TYPE_BLOG    => esc_html__( 'Blogs', 'geodir-converter' ),
		);
		?> 
		<form class="geodir-converter-settings-form" method="post">
			<h6 class="fs-base"><?php esc_html_e( 'eDirectory Importer Settings', 'geodir-converter' ); ?></h6>
			
			<?php
			if ( ! defined( 'GEODIR_EVENT_VERSION' ) ) {
				$this->render_plugin_notice(
					esc_html__( 'Events Addon', 'geodir-converter' ),
					'events',
					esc_url( 'https://wpgeodirectory.com/downloads/events/' )
				);
			}

			aui()->input(
				array(
					'id'          => 'edirectory_site_url',
					'name'        => 'edirectory_site_url',
					'type'        => 'text',
					'placeholder' => __( 'e.g. https://site.com', 'geodir-converter' ),
					'label'       => __( 'eDirectory Site URL', 'geodir-converter' ),
					'label_class' => 'font-weight-bold fw-bold',
					'help_text'   => __( 'Enter the URL of your eDirectory site. Include the protocol (http or https).', 'geodir-converter' ),
					'label_type'  => 'top',
					'value'       => esc_attr( $settings['edirectory_site_url'] ),
					'required'    => true,
				),
				true
			);

			aui()->input(
				array(
					'id'          => 'edirectory_api_key',
					'name'        => 'edirectory_api_key',
					'type'        => 'text',
					'placeholder' => __( 'API Key', 'geodir-converter' ),
					'label'       => __( 'eDirectory API Key', 'geodir-converter' ),
					'label_class' => 'font-weight-bold fw-bold',
					'help_text'   => __( 'You can find your API key in the eDirectory settings. Please go to Settings > General Settings and copy the API key from there.', 'geodir-converter' ),
					'label_type'  => 'top',
					'value'       => esc_attr( $settings['edirectory_api_key'] ),
					'required'    => true,
				),
				true
			);

			aui()->select(
				array(
					'id'          => 'edirectory_modules',
					'name'        => 'edirectory_modules[]',
					'multiple'    => true,
					'select2'     => true,
					'label'       => esc_html__( 'eDirectory Content to Import', 'geodir-converter' ),
					'label_class' => 'font-weight-bold fw-bold',
					'label_type'  => 'top',
					'value'       => $settings['edirectory_modules'],
					'options'     => $modules,
					'help_text'   => wp_kses_post(
						sprintf(
							__( 'Select the content to import. You can select multiple content types.', 'geodir-converter' ),
						)
					),
				),
				true
			);

			$this->display_post_type_select();
			$this->display_author_select();
			$this->display_test_mode_checkbox();
			$this->display_progress();
			$this->display_logs( $this->get_logs() );
			$this->display_error_alert();
			?>
						
			<div class="geodir-converter-actions mt-3">
				<button type="button" class="btn btn-primary btn-sm geodir-converter-import me-2"><?php esc_html_e( 'Start Import', 'geodir-converter' ); ?></button>
				<button type="button" class="btn btn-outline-danger btn-sm geodir-converter-abort"><?php esc_html_e( 'Abort', 'geodir-converter' ); ?></button>
			</div>
		</form>
		<script type="text/javascript">
			(function ($) {
				$(function () {
					const GD_CONVERTER_MODULE_TYPE_LISTING = '<?php echo esc_js( self::MODULE_TYPE_LISTING ); ?>';
					const $edirectory_modules = $('[name="edirectory_modules"]');
					const $post_type_wrapper = $('.geodir-converter-post-type');

					$edirectory_modules.on('change', function () {
						const modules = $(this).val();

						if (!modules.includes(GD_CONVERTER_MODULE_TYPE_LISTING)) {
							$post_type_wrapper.hide();
						} else {
							$post_type_wrapper.show();
						}
					});

					$edirectory_modules.trigger('change');
				});
			})(jQuery);
		</script>
		<?php
	}

	/**
	 * Validate importer settings.
	 *
	 * @param array $settings The settings to validate.
	 * @return array Validated and sanitized settings.
	 */
	public function validate_settings( array $settings ) {
		$post_types = geodir_get_posttypes();
		$errors     = array();

		$settings['gd_post_type']        = isset( $settings['gd_post_type'] ) && ! empty( $settings['gd_post_type'] ) ? sanitize_text_field( $settings['gd_post_type'] ) : 'gd_place';
		$settings['edirectory_site_url'] = isset( $settings['edirectory_site_url'] ) ? esc_url_raw( $settings['edirectory_site_url'] ) : '';
		$settings['edirectory_api_key']  = isset( $settings['edirectory_api_key'] ) ? sanitize_text_field( $settings['edirectory_api_key'] ) : '';
		$settings['test_mode']           = ( isset( $settings['test_mode'] ) && ! empty( $settings['test_mode'] ) && $settings['test_mode'] != 'no' ) ? 'yes' : 'no';
		$settings['wp_author_id']        = ( isset( $settings['wp_author_id'] ) && ! empty( $settings['wp_author_id'] ) ) ? absint( $settings['wp_author_id'] ) : get_current_user_id();

		// Validate and sanitize eDirectory modules.
		$edirectory_modules             = isset( $settings['edirectory_modules'] ) ? $settings['edirectory_modules'] : array();
		$edirectory_modules             = ! is_array( $edirectory_modules ) ? array( $edirectory_modules ) : $edirectory_modules;
		$settings['edirectory_modules'] = array_map( 'sanitize_text_field', $edirectory_modules );

		// Validate and sanitize post type.
		if ( ! in_array( $settings['gd_post_type'], $post_types, true ) ) {
			$errors[] = esc_html__( 'The selected post type is invalid. Please choose a valid post type.', 'geodir-converter' );
		}

		if ( empty( $settings['edirectory_modules'] ) || ! array_intersect( $settings['edirectory_modules'], $this->modules ) ) {
			$errors[] = esc_html__( 'Please select at least one eDirectory content to import.', 'geodir-converter' );
		}

		if ( empty( $settings['wp_author_id'] ) || ! get_userdata( (int) $settings['wp_author_id'] ) ) {
			$errors[] = esc_html__( 'The selected WordPress author is invalid. Please select a valid author to import listings to.', 'geodir-converter' );
		}

		// Validate and sanitize site URL.
		if ( empty( $settings['edirectory_site_url'] ) ) {
			$errors[] = esc_html__( 'eDirectory site URL is required.', 'geodir-converter' );
		}

		if ( ! wp_http_validate_url( $settings['edirectory_site_url'] ) ) {
			$errors[] = esc_html__( 'Invalid eDirectory site URL.', 'geodir-converter' );
		}

		// Validate and sanitize API key.
		if ( empty( $settings['edirectory_api_key'] ) ) {
			$errors[] = esc_html__( 'eDirectory API key is required.', 'geodir-converter' );
		}

		// If there are no errors, try to establish an API connection.
		if ( empty( $errors ) ) {
			$connection_result = $this->test_api_connection( $settings );
			if ( is_wp_error( $connection_result ) ) {
				$errors[] = $connection_result->get_error_message();
			}
		}

		if ( ! empty( $errors ) ) {
			return new WP_Error( 'invalid_import_settings', implode( '<br>', $errors ) );
		}

		return $settings;
	}

	/**
	 * Test the API connection.
	 *
	 * @param array $settings The settings to validate.
	 * @return array|WP_Error Validated and sanitized settings or WP_Error on failure.
	 */
	public function test_api_connection( array $settings ) {
		$this->base_url  = rtrim( $settings['edirectory_site_url'], '/' );
		$this->api_token = $settings['edirectory_api_key'];

		$response = $this->get( '/api/v1/home.json', array(), array( 'timeout' => 30 ) );

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'api_connection_failed', esc_html__( 'Failed to connect to eDirectory API.', 'geodir-converter' ) );
		}

		return true;
	}

	/**
	 * Get next task.
	 *
	 * @param array $task The current task.
	 *
	 * @return array|false The next task or false if all tasks are completed.
	 */
	public function next_task( $task ) {
		$task['imported'] = 0;
		$task['failed']   = 0;
		$task['skipped']  = 0;
		$task['updated']  = 0;

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
	 * Import categories from eDirectory to GeoDirectory.
	 *
	 * @param array $task Import task.
	 *
	 * @return array Result of the import operation.
	 */
	public function task_import_categories( $task ) {
		$offset   = isset( $task['offset'] ) ? absint( $task['offset'] ) : 0;
		$imported = isset( $task['imported'] ) ? absint( $task['imported'] ) : 0;
		$failed   = isset( $task['failed'] ) ? absint( $task['failed'] ) : 0;
		$skipped  = isset( $task['skipped'] ) ? absint( $task['skipped'] ) : 0;
		$updated  = isset( $task['updated'] ) ? absint( $task['updated'] ) : 0;
		$modules  = (array) $this->get_import_setting( 'edirectory_modules', array() );

		// Log the import start message only for the first batch.
		if ( 0 === $offset ) {
			$this->log( sprintf( self::LOG_TEMPLATE_STARTED, 'Categories' ), 'info' );
		}

		// Sets total categories.
		$module_categories = array();
		foreach ( $modules as $module ) {
			$response = $this->get( "/api/v1/{$module}/categories.json", array(), array( 'timeout' => 30 ) );

			if ( is_wp_error( $response ) ) {
				$this->log( sprintf( self::LOG_TEMPLATE_SKIPPED, 'category', $response->get_error_message() ), 'warning' );
				continue;
			}

			$post_type  = ( self::MODULE_TYPE_EVENT === $module ) ? self::POST_TYPE_EVENTS : $this->get_import_post_type();
			$taxonomy   = $post_type . 'category';
			$categories = isset( $response['data'] ) ? (array) $response['data'] : array();

			if ( empty( $categories ) ) {
				$this->log( sprintf( self::LOG_TEMPLATE_SKIPPED, 'category', 'No categories to import.' ), 'warning' );
				continue;
			}

			$total_categories = count( $categories );
			$this->log( sprintf( esc_html__( 'Importing %1$d %2$s categories.', 'geodir-converter' ), $total_categories, $module ), 'info' );

			// Merge categories from all modules.
			$module_categories = array_merge( $module_categories, $categories );
		}

		if ( $this->is_test_mode() ) {
			$this->log(
				sprintf(
				/* translators: %1$d: number of imported terms, %2$d: number of failed imports */
					esc_html__( 'Categories: Import completed. %1$d imported, %2$d failed.', 'geodir-converter' ),
					count( $module_categories ),
					0
				),
				'success'
			);
			return $this->next_task( $task );
		}

		// Sets total categories.
		$total_categories = count( $module_categories );
		$this->increase_imports_total( $total_categories );

		// Import categories.
		foreach ( $module_categories as $category ) {
			$result = $this->import_single_category( $category, 0, $taxonomy );

			if ( GeoDir_Converter_Importer::IMPORT_STATUS_SUCCESS === $result ) {
				++$imported;
				$this->increase_succeed_imports( 1 );
			} elseif ( GeoDir_Converter_Importer::IMPORT_STATUS_UPDATED === $result ) {
				++$updated;
				$this->increase_succeed_imports( 1 );
			} elseif ( GeoDir_Converter_Importer::IMPORT_STATUS_SKIPPED === $result ) {
				++$skipped;
				$this->increase_skipped_imports( 1 );
			} elseif ( GeoDir_Converter_Importer::IMPORT_STATUS_FAILED === $result ) {
				++$failed;
				$this->increase_failed_imports( 1 );
			}
		}

		$this->log( sprintf( self::LOG_TEMPLATE_FINISHED, 'Categories', $total_categories, $imported, $updated, $skipped, $failed ), 'success' );

		return $this->next_task( $task );
	}

	/**
	 * Recursively import a category and its children.
	 *
	 * @param array  $category          Category data from API.
	 * @param int    $parent_term_id    WP parent term ID (0 for top-level).
	 * @param string $taxonomy          The taxonomy to import into.
	 *
	 * @return int Status of import: imported|updated|skipped|failed
	 */
	protected function import_single_category( $category, $parent_term_id, $taxonomy ) {
		$external_id = isset( $category['id'] ) ? absint( $category['id'] ) : 0;
		$name        = isset( $category['title'] ) ? sanitize_text_field( $category['title'] ) : '';
		$slug        = isset( $category['friendly_url'] ) ? sanitize_title( $category['friendly_url'] ) : '';

		if ( ! $external_id || ! $name ) {
			return GeoDir_Converter_Importer::IMPORT_STATUS_SKIPPED;
		}

		// Get existing category mapping.
		$category_mapping = (array) $this->options_handler->get_option_no_cache( 'category_mapping', array() );

		$args = array(
			'slug'   => $slug,
			'parent' => $parent_term_id,
		);

		// Check if already mapped.
		if ( isset( $category_mapping[ $external_id ] ) ) {
			$term_id = $category_mapping[ $external_id ];
			$term    = get_term( $term_id, $taxonomy );

			if ( $term && ! is_wp_error( $term ) ) {
				return GeoDir_Converter_Importer::IMPORT_STATUS_SKIPPED;
			}
		}

		// Insert or update term.
		$term = term_exists( $name, $taxonomy, $parent_term_id );

		if ( $term && ! is_wp_error( $term ) ) {
			$term_id = absint( $term['term_id'] );
			wp_update_term( $term_id, $taxonomy, $args );
			$category_mapping[ $external_id ] = $term_id;
		} else {
			$new_term = wp_insert_term( $name, $taxonomy, $args );

			if ( is_wp_error( $new_term ) ) {
				return GeoDir_Converter_Importer::IMPORT_STATUS_FAILED;
			}

			$term_id = intval( $new_term['term_id'] );

			$category_mapping[ $external_id ] = $term_id;
		}

		// Update category mapping.
		$this->options_handler->update_option( 'category_mapping', $category_mapping );

		// Import children recursively.
		if ( ! empty( $category['children'] ) && is_array( $category['children'] ) ) {
			foreach ( $category['children'] as $child_category ) {
				$this->import_single_category( $child_category, $term_id, $taxonomy );
			}
		}

		return isset( $term ) ? GeoDir_Converter_Importer::IMPORT_STATUS_UPDATED : GeoDir_Converter_Importer::IMPORT_STATUS_SUCCESS;
	}

	/**
	 * Import fields from eDirectory to GeoDirectory.
	 *
	 * @since 2.0.2
	 * @param array $task Task details.
	 * @return array Result of the import operation.
	 */
	public function task_import_fields( array $task ) {
		$imported    = isset( $task['imported'] ) ? absint( $task['imported'] ) : 0;
		$failed      = isset( $task['failed'] ) ? absint( $task['failed'] ) : 0;
		$skipped     = isset( $task['skipped'] ) ? absint( $task['skipped'] ) : 0;
		$updated     = isset( $task['updated'] ) ? absint( $task['updated'] ) : 0;
		$fields_cpts = array( $this->get_import_post_type(), self::POST_TYPE_EVENTS );

		$this->log( sprintf( self::LOG_TEMPLATE_STARTED, 'Fields' ), 'info' );

		$started = false;
		foreach ( $fields_cpts as $post_type ) {
			$package_ids = $this->get_package_ids( $post_type );
			$fields      = $this->get_fields( $post_type );

			if ( empty( $fields ) ) {
				$this->log( sprintf( self::LOG_TEMPLATE_SKIPPED, 'fields', 'No fields found for import.' ), 'warning' );
				continue;
			}

			if ( ! $started ) {
				$this->increase_imports_total( count( $fields ) );
				$this->log( sprintf( self::LOG_TEMPLATE_STARTED, "Fields {$post_type}" ), 'info' );
				$started = true;
			}

			foreach ( $fields as $field ) {
				$result = $this->import_single_field( $field, $post_type, $package_ids );

				// Skip fields that shouldn't be imported.
				if ( GeoDir_Converter_Importer::IMPORT_STATUS_SKIPPED === $result ) {
					++$skipped;
				} elseif ( GeoDir_Converter_Importer::IMPORT_STATUS_UPDATED === $result ) {
					++$updated;
				} elseif ( GeoDir_Converter_Importer::IMPORT_STATUS_SUCCESS === $result ) {
					++$imported;
				} elseif ( GeoDir_Converter_Importer::IMPORT_STATUS_FAILED === $result ) {
					++$failed;
				}
			}

			// Reset started flag for next iteration.
			$started = false;

			$this->log(
				sprintf( self::LOG_TEMPLATE_SUCCESS, "{$post_type} fields", count( $fields ) ),
				'success'
			);
		}

		$this->increase_succeed_imports( $imported + $updated );
		$this->increase_skipped_imports( $skipped );
		$this->increase_failed_imports( $failed );

		$this->log(
			sprintf( self::LOG_TEMPLATE_FINISHED, 'Fields', count( $fields ), $imported, $updated, $skipped, $failed ),
			'success'
		);

		return $this->next_task( $task );
	}

	/**
	 * Import a single field.
	 *
	 * @param array  $field The field to import.
	 * @param string $post_type The post type to import the field for.
	 * @param array  $package_ids The package IDs to import the field for.
	 * @return string The import status.
	 */
	private function import_single_field( $field, $post_type, $package_ids = array() ) {
		global $plugin_prefix;

		$table        = $plugin_prefix . $post_type . '_detail';
		$gd_field     = geodir_get_field_infoby( 'htmlvar_name', $field['id'], $post_type );
		$gd_data_type = $this->map_data_type( $field['type'] );
		$props        = isset( $field['props'] ) ? (array) $field['props'] : array();

		if ( $gd_field ) {
			$gd_field['field_id'] = (int) $gd_field['id'];
			unset( $gd_field['id'] );
		} else {
			$gd_field = array(
				'post_type'     => $post_type,
				'data_type'     => $gd_data_type,
				'field_type'    => $field['type'],
				'htmlvar_name'  => $field['id'],
				'is_active'     => '1',
				'option_values' => '',
				'is_default'    => '0',
			);

			if ( 'checkbox' === $field['type'] ) {
				$gd_field['data_type'] = 'TINYINT';
			}
		}

		$gd_field = array_merge(
			$gd_field,
			array(
				'admin_title'       => isset( $props['label'] ) ? $props['label'] : '',
				'frontend_desc'     => isset( $props['tip'] ) ? $props['tip'] : '',
				'placeholder_value' => isset( $props['placeholder'] ) ? $props['placeholder'] : '',
				'frontend_title'    => isset( $props['label'] ) ? $props['label'] : '',
				'default_value'     => '',
				'for_admin_use'     => 0,
				'is_required'       => isset( $props['required'] ) && 1 === (int) $props['required'] ? 1 : 0,
				'show_in'           => '[owntab],[detail]',
				'show_on_pkg'       => $package_ids,
				'clabels'           => isset( $props['label'] ) ? $props['label'] : '',
				'field_icon'        => isset( $props['icon'] ) ? $props['icon'] : '',
				'class'             => isset( $props['class'] ) ? $props['class'] : '',
			),
			isset( $props['field_type_key'] ) ? array(
				'field_type_key' => $props['field_type_key'],
			) : array()
		);

		// Add file field extra data if available.
		if ( 'file' === $field['type'] ) {
			$gd_field['extra'] = array(
				'gd_file_types' => geodir_image_extensions(),
				'file_limit'    => 1,
			);
		}

		// Add options if available.
		if ( isset( $props['options'] ) && ! empty( $props['options'] ) && is_array( $props['options'] ) ) {
			$option_values = array();
			foreach ( $props['options'] as $option ) {
				$option_values[] = $option['value'];
			}

			$gd_field['option_values'] = implode( ',', $option_values );
		}

		// Skip fields that shouldn't be imported.
		if ( $this->should_skip_field( $gd_field['htmlvar_name'] ) ) {
			return GeoDir_Converter_Importer::IMPORT_STATUS_SKIPPED;
		}

		$column_exists = geodir_column_exist( $table, $gd_field['htmlvar_name'] );

		if ( $this->is_test_mode() ) {
			return $column_exists ? GeoDir_Converter_Importer::IMPORT_STATUS_UPDATED : GeoDir_Converter_Importer::IMPORT_STATUS_SUCCESS;
		}

		if ( $gd_field && geodir_custom_field_save( $gd_field ) ) {
			return $column_exists ? GeoDir_Converter_Importer::IMPORT_STATUS_UPDATED : GeoDir_Converter_Importer::IMPORT_STATUS_SUCCESS;
		}

		return GeoDir_Converter_Importer::IMPORT_STATUS_FAILED;
	}

	/**
	 * Get standard fields.
	 *
	 * @since 2.0.2
	 * @param string $post_type The post type to get the fields for.
	 * @return array Array of standard fields.
	 */
	private function get_fields( $post_type ) {
		$standard_fields = array(
			array(
				'id'       => 'edirectory_id',
				'type'     => 'int',
				'priority' => 1,
				'props'    => array(
					'label'       => __( 'eDirectory ID', 'geodir-converter' ),
					'description' => __( 'Original eDirectory Listing ID.', 'geodir-converter' ),
					'required'    => false,
					'placeholder' => __( 'eDirectory ID', 'geodir-converter' ),
					'icon'        => 'far fa-id-card',
				),
			),
			array(
				'id'       => 'email',
				'type'     => 'email',
				'priority' => 4,
				'props'    => array(
					'label'       => __( 'Email', 'geodir-converter' ),
					'description' => __( 'The email of the listing.', 'geodir-converter' ),
					'required'    => false,
					'placeholder' => __( 'Email', 'geodir-converter' ),
					'icon'        => 'far fa-envelope',
				),
			),
			array(
				'id'       => 'phone',
				'type'     => 'phone',
				'priority' => 2,
				'props'    => array(
					'label'       => __( 'Phone', 'geodir-converter' ),
					'description' => __( 'The phone number of the listing.', 'geodir-converter' ),
					'required'    => false,
					'placeholder' => __( 'Phone', 'geodir-converter' ),
					'icon'        => 'fa-solid fa-phone',
				),
			),
			array(
				'id'       => 'website',
				'type'     => 'url',
				'priority' => 3,
				'props'    => array(
					'label'       => __( 'Website', 'geodir-converter' ),
					'description' => __( 'The website of the listing.', 'geodir-converter' ),
					'required'    => false,
					'placeholder' => __( 'Website', 'geodir-converter' ),
					'icon'        => 'fa-solid fa-globe',
				),
			),
		);

		$social_fields = array(
			array(
				'id'       => 'whatsapp',
				'type'     => 'url',
				'priority' => 3,
				'props'    => array(
					'label'       => __( 'WhatsApp', 'geodir-converter' ),
					'description' => __( 'The WhatsApp page of the listing.', 'geodir-converter' ),
					'required'    => false,
					'placeholder' => __( 'WhatsApp', 'geodir-converter' ),
					'icon'        => 'fa-brands fa-whatsapp',
				),
			),
			array(
				'id'       => 'facebook',
				'type'     => 'url',
				'priority' => 3,
				'props'    => array(
					'label'       => __( 'Facebook', 'geodir-converter' ),
					'description' => __( 'The Facebook page of the listing.', 'geodir-converter' ),
					'required'    => false,
					'placeholder' => __( 'Facebook', 'geodir-converter' ),
					'icon'        => 'fa-brands fa-facebook',
				),
			),
		);

		if ( self::POST_TYPE_EVENTS === $post_type ) {
			$standard_fields = array_merge(
				$standard_fields,
				array(
					array(
						'id'       => 'venue',
						'type'     => 'text',
						'priority' => 3,
						'props'    => array(
							'label'       => __( 'Venue', 'geodir-converter' ),
							'description' => __( 'The venue that will host this event.', 'geodir-converter' ),
							'required'    => false,
							'placeholder' => __( 'Venue', 'geodir-converter' ),
							'icon'        => 'far fa-map-marker-alt',
						),
					),
					array(
						'id'       => 'contact_name',
						'type'     => 'text',
						'priority' => 3,
						'props'    => array(
							'label'       => __( 'Contact Name', 'geodir-converter' ),
							'description' => __( 'The contact person.', 'geodir-converter' ),
							'required'    => false,
							'placeholder' => __( 'Contact Name', 'geodir-converter' ),
							'icon'        => 'far fa-user',
						),
					),
				)
			);
		} else {
			$standard_fields = array_merge(
				$standard_fields,
				$social_fields,
				array(
					array(
						'id'       => 'logo',
						'type'     => 'file',
						'priority' => 3,
						'props'    => array(
							'label'       => __( 'Company Logo', 'geodir-converter' ),
							'frontend'    => __( 'You can upload your company logo.', 'geodir-converter' ),
							'description' => __( 'Adds a logo input. This can be used in conjunction with the `GD > Post Images` widget, there is a setting to allow it to use the logo if available. This can also be used by other plugins if the field key remains `logo`.', 'geodir-converter' ),
							'required'    => false,
							'placeholder' => __( 'Logo', 'geodir-converter' ),
							'icon'        => 'fas fa-image',
							'class'       => 'gd-logo',
						),
					),
					array(
						'id'       => 'twitter',
						'type'     => 'url',
						'priority' => 3,
						'props'    => array(
							'label'       => __( 'Twitter', 'geodir-converter' ),
							'description' => __( 'The Twitter page of the listing.', 'geodir-converter' ),
							'required'    => false,
							'placeholder' => __( 'Twitter', 'geodir-converter' ),
							'icon'        => 'fa-brands fa-twitter',
						),
					),
					array(
						'id'       => 'instagram',
						'type'     => 'url',
						'priority' => 3,
						'props'    => array(
							'label'       => __( 'Instagram', 'geodir-converter' ),
							'description' => __( 'The Instagram page of the listing.', 'geodir-converter' ),
							'required'    => false,
							'placeholder' => __( 'Instagram', 'geodir-converter' ),
							'icon'        => 'fa-brands fa-instagram',
						),
					),
					array(
						'id'       => 'youtube',
						'type'     => 'url',
						'priority' => 3,
						'props'    => array(
							'label'       => __( 'YouTube', 'geodir-converter' ),
							'description' => __( 'The YouTube page of the listing.', 'geodir-converter' ),
							'required'    => false,
							'placeholder' => __( 'YouTube', 'geodir-converter' ),
							'icon'        => 'fa-brands fa-youtube',
						),
					),
					array(
						'id'       => 'pinterest',
						'type'     => 'url',
						'priority' => 3,
						'props'    => array(
							'label'       => __( 'Pinterest', 'geodir-converter' ),
							'description' => __( 'The Pinterest page of the listing.', 'geodir-converter' ),
							'required'    => false,
							'placeholder' => __( 'Pinterest', 'geodir-converter' ),
							'icon'        => 'fa-brands fa-pinterest',
						),
					),
					array(
						'id'       => 'linkedin',
						'type'     => 'url',
						'priority' => 3,
						'props'    => array(
							'label'       => __( 'LinkedIn', 'geodir-converter' ),
							'description' => __( 'The LinkedIn page of the listing.', 'geodir-converter' ),
							'required'    => false,
							'placeholder' => __( 'LinkedIn', 'geodir-converter' ),
							'icon'        => 'fa-brands fa-linkedin',
						),
					),
					array(
						'id'       => 'tiktok',
						'type'     => 'url',
						'priority' => 3,
						'props'    => array(
							'label'       => __( 'TikTok', 'geodir-converter' ),
							'description' => __( 'The TikTok page of the listing.', 'geodir-converter' ),
							'required'    => false,
							'placeholder' => __( 'TikTok', 'geodir-converter' ),
							'icon'        => 'fa-brands fa-tiktok',
						),
					),
					array(
						'id'       => 'business_hours',
						'type'     => 'business_hours',
						'priority' => 3,
						'props'    => array(
							'label'       => __( 'Business Hours', 'geodir-converter' ),
							'description' => __( 'Select your business opening/operating hours.', 'geodir-converter' ),
							'required'    => false,
							'placeholder' => __( 'Business Hours', 'geodir-converter' ),
							'class'       => 'gd-business-hours',
							'icon'        => 'far fa-clock',
						),
					),
					array(
						'id'       => 'features',
						'type'     => 'multiselect',
						'priority' => 3,
						'props'    => array(
							'label'       => __( 'Features', 'geodir-converter' ),
							'description' => __( 'Select the features of the listing. e.g. Wi-Fi, Parking, etc.', 'geodir-converter' ),
							'required'    => false,
							'placeholder' => __( 'Features', 'geodir-converter' ),
							'icon'        => 'far fa-list',
						),
					),
					array(
						'id'       => 'video',
						'type'     => 'textarea',
						'priority' => 3,
						'props'    => array(
							'label'       => __( 'Video URL', 'geodir-converter' ),
							'description' => __( 'Enter the URL of the video you want to associate with this listing. This can be a YouTube or Vimeo URL.', 'geodir-converter' ),
							'required'    => false,
							'placeholder' => __( 'Video URL', 'geodir-converter' ),
							'icon'        => 'far fa-video',
						),
					),
					array(
						'id'       => 'featured',
						'type'     => 'checkbox',
						'priority' => 18,
						'props'    => array(
							'field_type_key' => 'featured',
							'label'          => __( 'Is Featured?', 'geodir-converter' ),
							'frontend'       => __( 'Is Featured?', 'geodirectory' ),
							'description'    => __( 'Mark listing as a featured.', 'geodir-converter' ),
							'required'       => false,
							'placeholder'    => __( 'Is Featured?', 'geodir-converter' ),
							'icon'           => 'fas fa-certificate',
						),
					),
				)
			);
		}

		return $standard_fields;
	}

	/**
	 * Import listings from eDirectory to GeoDirectory.
	 *
	 * @since 2.0.2
	 * @param array $task The offset to start importing from.
	 * @return array Result of the import operation.
	 */
	public function task_parse_listings( array $task ) {
		$offset         = isset( $task['offset'], $task['total_listings'] ) ? (int) $task['offset'] : 1;
		$total_listings = isset( $task['total_listings'] ) ? (int) $task['total_listings'] : 0;
		$total_pages    = isset( $task['total_pages'] ) ? (int) $task['total_pages'] : 0;
		$modules        = (array) $this->get_import_setting( 'edirectory_modules', array() );

		// Log the import start message only for the first batch.
		if ( 1 === $offset ) {
			$this->log( sprintf( self::LOG_TEMPLATE_STARTED, 'Listings' ), 'info' );
		}

		$response = $this->get(
			'/api/v1/results.json',
			array(
				'module' => implode( ',', $modules ),
				'page'   => $offset,
			),
			array( 'timeout' => 30 )
		);

		if ( is_wp_error( $response ) ) {
			$this->log( sprintf( self::LOG_TEMPLATE_FAILED, 'Listings', $response->get_error_message() ), 'error' );
			return $this->next_task( $task );
		}

		// Determine total listings count if not set.
		if ( ! isset( $task['total_listings'] ) ) {
			$total_listings = isset( $response['paging']['total'] ) ? (int) $response['paging']['total'] : 0;
			$total_pages    = isset( $response['paging']['pages'] ) ? (int) $response['paging']['pages'] : 0;

			$task['total_listings'] = $total_listings;
			$task['total_pages']    = $total_pages;
			$this->increase_imports_total( $total_listings );
		}

		// Exit early if there are no listings to import.
		if ( 0 === $total_listings ) {
			$this->log( sprintf( self::LOG_TEMPLATE_FAILED, 'Listings', 'No listings found for import.' ), 'error' );
			return $this->next_task( $task );
		}

		$listings = isset( $response['data'] ) ? (array) $response['data'] : array();

		if ( empty( $listings ) ) {
			$this->log( sprintf( self::LOG_TEMPLATE_FAILED, 'Listings', 'No more listings found for import.' ), 'info' );
			return $this->next_task( $task );
		}

		$import_tasks = array();
		foreach ( $listings as $listing ) {
			if ( isset( $listing['id'], $listing['title'], $listing['type'] ) ) {
				$import_tasks[] = array_merge(
					array(
						'id'     => (int) $listing['id'],
						'title'  => $listing['title'],
						'type'   => $listing['type'],
						'action' => GeoDir_Converter_Importer::ACTION_IMPORT_LISTING,
					),
					isset( $listing['image_url'] ) ? array( 'image_url' => $listing['image_url'] ) : array()
				);
			}
		}

		$this->background_process->add_import_tasks( $import_tasks );

		$complete = ( $offset >= $total_pages );
		if ( ! $complete ) {
			// Continue import with the next batch.
			$task['offset'] = $offset + 1;
			return $task;
		}

		return $this->next_task( $task );
	}

	/**
	 * Import listings from eDirectory to GeoDirectory.
	 *
	 * @since 2.0.2
	 * @param array $task The task to import.
	 * @return array Result of the import operation.
	 */
	public function task_import_listing( $task ) {
		// Get the id, title and module type.
		$id     = absint( $task['id'] );
		$title  = $task['title'];
		$module = $task['type'];

		// Get the endpoint for the module type.
		$endpoints = array(
			self::MODULE_TYPE_LISTING => "/api/v1/listings/{$id}.json",
			self::MODULE_TYPE_EVENT   => "/api/v1/events/{$id}.json",
			self::MODULE_TYPE_BLOG    => "/api/v1/blogs/{$id}.json",
		);

		// Remove event endpoint if events addon is not installed.
		if ( ! class_exists( 'GeoDir_Event_Manager' ) ) {
			unset( $endpoints[ self::MODULE_TYPE_EVENT ] );
		}

		// Get the response for the module type.
		if ( ! isset( $endpoints[ $module ] ) ) {
			$response = new WP_Error( 'invalid_module', __( 'Invalid module type.', 'geodir-converter' ) );
		} else {
			$response = $this->get( $endpoints[ $module ], array(), array( 'timeout' => 30 ) );
		}

		// Get the import method for the module type.
		$import_method = "import_single_{$module}";

		// Check if the response is a WP_Error or if the import method does not exist.
		if ( is_wp_error( $response ) || ! method_exists( $this, $import_method ) ) {
			$this->increase_failed_imports( 1 );
			return false;
		}

		// Get the data.
		$data = isset( $response['data'] ) ? $response['data'] : array();

		// Set image_url to image_url if it exists.
		if ( ! isset( $data['image_url'] ) && isset( $task['image_url'] ) && ! empty( $task['image_url'] ) ) {
			$data['image_url'] = $task['image_url'];
		}

		$import_status = $this->$import_method( $data );

		switch ( $import_status ) {
			case GeoDir_Converter_Importer::IMPORT_STATUS_SUCCESS:
				$this->log( sprintf( self::LOG_TEMPLATE_SUCCESS, $module, $title ), 'success' );
				$this->increase_succeed_imports( 1 );
				break;

			case GeoDir_Converter_Importer::IMPORT_STATUS_UPDATED:
				$this->log( sprintf( self::LOG_TEMPLATE_UPDATED, $module, $title ), 'warning' );
				$this->increase_succeed_imports( 1 );
				break;

			case GeoDir_Converter_Importer::IMPORT_STATUS_SKIPPED:
				$this->log( sprintf( self::LOG_TEMPLATE_SKIPPED, $module, $title ), 'warning' );
				$this->increase_skipped_imports( 1 );
				break;

			case GeoDir_Converter_Importer::IMPORT_STATUS_FAILED:
				$this->log( sprintf( self::LOG_TEMPLATE_FAILED, $module, $title ), 'warning' );
				$this->increase_failed_imports( 1 );
				break;
		}

		return false;
	}

	/**
	 * Import events from eDirectory to GeoDirectory.
	 *
	 * @since 2.0.2
	 * @param array $listing The offset to start importing from.
	 * @return array Result of the import operation.
	 */
	public function import_single_listing( array $listing ) {
		$post_type    = $this->get_import_post_type();
		$wp_author_id = (int) $this->get_import_setting( 'wp_author_id', 1 );
		$gd_post_id   = ! $this->is_test_mode() ? (int) $this->get_gd_listing_id( $listing['id'], 'edirectory_id', $post_type ) : false;
		$is_update    = ! empty( $gd_post_id );

		// Get the gallery.
		$gallery = isset( $listing['gallery'] ) && is_array( $listing['gallery'] ) ? (array) $listing['gallery'] : array();

		// Get the current time.
		$current_time     = current_time( 'mysql' );
		$current_time_gmt = current_time( 'mysql', 1 );
		$social_network   = isset( $listing['social_network'] ) ? (array) $listing['social_network'] : array();

		// Get the location.
		$location = $this->get_default_location();
		$address  = isset( $listing['address'] ) ? $listing['address'] : array();
		if ( isset( $listing['geo']['lat'], $listing['geo']['lng'] ) ) {
			$location = $this->get_location_from_coords( $listing['geo']['lat'], $listing['geo']['lng'] );
			$address  = isset( $location['address'] ) && ! empty( $location['address'] ) ? $location['address'] : $address;
		}

		// Get the category mapping.
		$category_mapping = (array) $this->options_handler->get_option_no_cache( 'category_mapping', array() );
		$categories       = isset( $listing['categories'] ) && is_array( $listing['categories'] ) ? (array) $listing['categories'] : array();

		// Map the categories.
		$gd_categories = array();
		foreach ( $categories as $category ) {
			$gd_categories[] = isset( $category_mapping[ $category['id'] ] ) ? (int) $category_mapping[ $category['id'] ] : 0;
		}

		$post_content = isset( $listing['long_description'] ) ? $listing['long_description'] : '';

		// Prepare the listing data.
		$post_data = array(
			// Standard WP Fields.
			'post_author'           => $wp_author_id,
			'post_title'            => isset( $listing['title'] ) ? $listing['title'] : '',
			'post_content'          => $post_content,
			'post_content_filtered' => $post_content,
			'post_excerpt'          => isset( $listing['description'] ) ? $listing['description'] : '',
			'post_status'           => 'publish',
			'post_type'             => $post_type,
			'comment_status'        => 'open',
			'ping_status'           => 'closed',
			'post_name'             => $listing['friendly_url'],
			'post_date_gmt'         => $current_time_gmt,
			'post_date'             => $current_time,
			'post_modified_gmt'     => $current_time_gmt,
			'post_modified'         => $current_time,
			'tax_input'             => array(
				"{$post_type}category" => $gd_categories,
			),

			// GD fields.
			'default_category'      => ! empty( $gd_categories ) ? $gd_categories[0] : 0,
			'submit_ip'             => '',
			'overall_rating'        => 0,
			'rating_count'          => 0,

			'street'                => $address,
			'street2'               => '',
			'city'                  => isset( $location['city'] ) ? $location['city'] : '',
			'region'                => isset( $location['state'] ) ? $location['state'] : '',
			'country'               => isset( $location['country'] ) ? $location['country'] : '',
			'zip'                   => isset( $location['zip'] ) ? $location['zip'] : '',
			'latitude'              => isset( $location['latitude'] ) ? $location['latitude'] : '',
			'longitude'             => isset( $location['longitude'] ) ? $location['longitude'] : '',
			'mapview'               => '',
			'mapzoom'               => '',

			// eDirectory standard fields.
			'edirectory_id'         => $listing['id'],
			'phone'                 => isset( $listing['phone'] ) ? $listing['phone'] : '',
			'website'               => isset( $listing['url'] ) ? $listing['url'] : '',
			'email'                 => isset( $listing['email'] ) ? $listing['email'] : '',
			'facebook'              => isset( $social_network['facebook'] ) ? $social_network['facebook'] : '',
			'twitter'               => isset( $social_network['twitter'] ) ? $social_network['twitter'] : '',
			'instagram'             => isset( $social_network['instagram'] ) ? $social_network['instagram'] : '',
			'youtube'               => isset( $social_network['youtube'] ) ? $social_network['youtube'] : '',
			'pinterest'             => isset( $social_network['pinterest'] ) ? $social_network['pinterest'] : '',
			'linkedin'              => isset( $social_network['linkedin'] ) ? $social_network['linkedin'] : '',

			'featured'              => isset( $listing['featured'] ) && 1 === (int) $listing['featured'] ? (bool) true : false,
		);

		// Import featured image.
		if ( isset( $listing['image_url'] ) && ! empty( $listing['image_url'] ) ) {
			$logo = $this->import_attachment( $listing['image_url'] );
			if ( isset( $logo['id'], $logo['src'] ) ) {
				$post_data['logo'] = $logo['src'] . '|||';
			}
		}

		// Get the business hours.
		if ( isset( $listing['hours_work'] ) && ! empty( $listing['hours_work'] ) ) {
			$timezone       = isset( $listing['time_zone_hours_work'] ) ? $listing['time_zone_hours_work'] : 'UTC';
			$business_hours = $this->parse_business_hours( $listing['hours_work'], $timezone );

			if ( ! is_wp_error( $business_hours ) ) {
				$post_data['business_hours'] = $business_hours;
			}
		}

		// Handle test mode.
		if ( $this->is_test_mode() ) {
			return GeoDir_Converter_Importer::IMPORT_STATUS_SUCCESS;
		}

		// Delete existing media if updating.
		if ( $is_update ) {
			GeoDir_Media::delete_files( (int) $gd_post_id, 'post_images' );
		}

		// Import gallery.
		$images = $this->import_gallery_image( $gallery );
		if ( ! empty( $images ) ) {
			$post_data['post_images'] = $images;
		}

		// Import video.
		if ( isset( $listing['video_url'] ) && ! empty( $listing['video_url'] ) ) {
			$post_data['video'] = $listing['video_url'];
		}

		// Import features.
		if ( isset( $listing['features'] ) && ! empty( $listing['features'] ) ) {
			$features = preg_split( '/\r\n|\r|\n/', trim( $listing['features'] ) );
			$gd_field = geodir_get_field_infoby( 'htmlvar_name', 'features', $post_type );

			if ( $gd_field ) {
				$gd_field['field_id'] = (int) $gd_field['id'];

				$option_values = $gd_field['option_values'];
				$option_values = (array) explode( ',', $option_values );
				$options       = array_merge( $option_values, $features );
				$options       = array_filter( $options );
				$options       = array_values( $options );
				$options       = array_unique( $options );

				if ( $features !== $option_values ) {
					$gd_field['option_values'] = implode( ',', $options );
					geodir_custom_field_save( $gd_field );
				}
			}

			if ( is_array( $features ) && ! empty( $features ) ) {
				$post_data['features'] = implode( ',', $features );
			}
		}

		// Insert or update the post.
		if ( $is_update ) {
			$post_data['ID'] = (int) $gd_post_id;
			$gd_post_id      = wp_update_post( $post_data, true );
		} else {
			$gd_post_id = wp_insert_post( $post_data, true );
		}

		// Handle errors during post insertion/update.
		if ( is_wp_error( $gd_post_id ) ) {
			$this->log( $gd_post_id->get_error_message() );
			return GeoDir_Converter_Importer::IMPORT_STATUS_FAILED;
		}

		return $is_update ? GeoDir_Converter_Importer::IMPORT_STATUS_SKIPPED : GeoDir_Converter_Importer::IMPORT_STATUS_SUCCESS;
	}

	/**
	 * Import events from eDirectory to GeoDirectory.
	 *
	 * @since 2.0.2
	 * @param array $event The offset to start importing from.
	 * @return array Result of the import operation.
	 */
	public function import_single_event( array $event ) {
		// Abort early if events addon is not installed.
		if ( ! class_exists( 'GeoDir_Event_Manager' ) ) {
			$this->log( sprintf( __( 'Events addon is not active. Skipping event: %s', 'geodir-converter' ), $event['title'] ), 'error' );
			return GeoDir_Converter_Importer::IMPORT_STATUS_SKIPPED;
		}

		// Get the post type and ID.
		$gd_event_post_type = self::POST_TYPE_EVENTS;
		$gd_event_id        = ! $this->is_test_mode() ? $this->get_gd_listing_id( $event['id'], 'edirectory_id', $gd_event_post_type ) : false;
		$is_update          = ! empty( $gd_event_id );
		$wp_author_id       = (int) $this->get_import_setting( 'wp_author_id', 1 );
		$current_time       = current_time( 'mysql' );
		$current_time_gmt   = current_time( 'mysql', 1 );

		// Get the categories.
		$categories = isset( $event['categories'] ) && is_array( $event['categories'] ) ? (array) $event['categories'] : array();
		$gallery    = isset( $event['gallery'] ) && is_array( $event['gallery'] ) ? (array) $event['gallery'] : array();

		// Get the start and end dates.
		$start_date       = isset( $event['start_date'] ) && ! empty( $event['start_date'] ) ? $event['start_date'] : '';
		$end_date         = isset( $event['end_date'] ) && ! empty( $event['end_date'] ) ? $event['end_date'] : '';
		$start_time       = isset( $event['start_time'] ) && ! empty( $event['start_time'] ) ? $event['start_time'] : '';
		$end_time         = isset( $event['end_time'] ) && ! empty( $event['end_time'] ) ? $event['end_time'] : '';
		$recurring_phrase = isset( $event['recurring_phrase'] ) && ! empty( $event['recurring_phrase'] ) ? strtolower( $event['recurring_phrase'] ) : '';

		// Get the friendly URL.
		$friendly_url = $this->extract_slug_from_url( $event['detail_url'] );

		// Get the repeat type and days.
		$repeat_type  = '';
		$repeat_days  = array();
		$repeat_month = '';
		$repeat_weeks = array();

		// todo: handle yearly recurring events.
		if ( ! empty( $recurring_phrase ) ) {
			if ( 'daily' === $recurring_phrase ) {
				$repeat_type = 'day';
			} else {
				$recurring = $this->parse_recurring_phrase( $recurring_phrase );

				$repeat_type  = isset( $recurring['type'] ) ? $recurring['type'] : '';
				$repeat_days  = isset( $recurring['days'] ) ? $recurring['days'] : array();
				$repeat_month = isset( $recurring['month'] ) ? $recurring['month'] : '';
				$repeat_weeks = isset( $recurring['ordinals'] ) ? $recurring['ordinals'] : array();
			}
		}

		// If end date is not set, use recurring until date.
		if ( ! isset( $event['end_date'] ) && isset( $event['recurring_until'] ) ) {
			$end_date = $event['recurring_until'];
		}

		// Get the category mapping.
		$category_mapping = (array) $this->options_handler->get_option_no_cache( 'category_mapping', array() );

		// Map the categories.
		$gd_categories = array();
		foreach ( $categories as $category ) {
			$gd_categories[] = isset( $category_mapping[ $category['id'] ] ) ? $category_mapping[ $category['id'] ] : 0;
		}

		// Get the location.
		$location = array();
		$address  = isset( $event['address'] ) ? $event['address'] : array();
		if ( isset( $event['geo']['lat'], $event['geo']['lng'] ) ) {
			$location = $this->get_location_from_coords( $event['geo']['lat'], $event['geo']['lng'] );
			$address  = isset( $location['address'] ) && ! empty( $location['address'] ) ? $location['address'] : $address;
		}

		// Prepare the listing data.
		$gd_event = array(
			// Standard WP Fields.
			'post_author'           => $wp_author_id,
			'post_title'            => isset( $event['title'] ) ? $event['title'] : '&mdash;',
			'post_content'          => isset( $event['long_description'] ) ? $event['long_description'] : '',
			'post_content_filtered' => isset( $event['long_description'] ) ? $event['long_description'] : '',
			'post_excerpt'          => isset( $event['description'] ) ? $event['description'] : '',
			'post_status'           => 'publish',
			'post_type'             => $gd_event_post_type,
			'comment_status'        => 'open',
			'ping_status'           => 'closed',
			'post_name'             => $friendly_url,
			'post_date_gmt'         => $current_time_gmt,
			'post_date'             => $current_time,
			'post_modified_gmt'     => $current_time_gmt,
			'post_modified'         => $current_time,
			'tax_input'             => array(
				"{$gd_event_post_type}category" => $gd_categories,
			),

			// GD fields.
			'default_category'      => ! empty( $gd_categories ) ? $gd_categories[0] : 0,

			// location.
			'street'                => $address,
			'street2'               => '',
			'city'                  => isset( $location['city'] ) ? $location['city'] : '',
			'region'                => isset( $location['state'] ) ? $location['state'] : '',
			'country'               => isset( $location['country'] ) ? $location['country'] : '',
			'zip'                   => isset( $location['zip'] ) ? $location['zip'] : '',
			'latitude'              => isset( $location['latitude'] ) ? $location['latitude'] : '',
			'longitude'             => isset( $location['longitude'] ) ? $location['longitude'] : '',
			'mapview'               => '',
			'mapzoom'               => '',

			// eDirectory standard fields.
			'edirectory_id'         => isset( $event['id'] ) ? absint( $event['id'] ) : 0,
			'website'               => isset( $event['url'] ) ? $event['url'] : '',
			'phone'                 => isset( $event['phone'] ) ? $event['phone'] : '',
			'email'                 => isset( $event['email'] ) ? $event['email'] : '',
			'facebook'              => isset( $event['facebook_page'] ) ? $event['facebook_page'] : '',
			'twitter'               => isset( $event['twitter_feed'] ) ? $event['twitter_feed'] : '',
			'venue'                 => isset( $event['location_name'] ) ? $event['location_name'] : '',
			'contact_name'          => isset( $event['contact_name'] ) ? $event['contact_name'] : '',

			// Event dates.
			'recurring'             => isset( $event['recurring'] ) && ( 'Y' === $event['recurring'] ) ? true : false,
			'event_dates'           => array(
				'recurring'       => isset( $event['repeat_event'] ) && ( 'Y' === $event['repeat_event'] ) ? true : false,
				'start_date'      => $start_date ? gmdate( 'Y-m-d', strtotime( $start_date ) ) : '',
				'end_date'        => $end_date ? gmdate( 'Y-m-d', strtotime( $end_date ) ) : '',
				'all_day'         => 0,
				'start_time'      => $start_time ? gmdate( 'g:i a', strtotime( $start_time ) ) : '',
				'end_time'        => $end_time ? gmdate( 'g:i a', strtotime( $end_time ) ) : '',
				'duration_x'      => '',
				'repeat_type'     => $repeat_type,
				'repeat_x'        => '',
				'repeat_end_type' => '',
				'max_repeat'      => '',
				'recurring_dates' => '',
				'different_times' => '',
				'start_times'     => '',
				'end_times'       => '',
				'repeat_days'     => $repeat_days,
				'repeat_weeks'    => $repeat_weeks,
			),
		);

		// Handle test mode.
		if ( $this->is_test_mode() ) {
			return $is_update ? self::IMPORT_STATUS_SKIPPED : self::IMPORT_STATUS_SUCCESS;
		}

		// Delete existing media if updating.
		if ( $is_update ) {
			GeoDir_Media::delete_files( (int) $gd_event_id, 'post_images' );
		}

		$images = $this->import_gallery_image( $gallery );
		if ( ! empty( $images ) ) {
			$gd_event['post_images'] = $images;
		}

		// Insert or update the post.
		if ( $is_update ) {
			$gd_event['ID'] = absint( $gd_event_id );
			$gd_event_id    = wp_update_post( $gd_event, true );
		} else {
			$gd_event_id = wp_insert_post( $gd_event, true );
		}

		if ( is_wp_error( $gd_event_id ) ) {
			return self::IMPORT_STATUS_FAILED;
		}

		return $is_update ? self::IMPORT_STATUS_UPDATED : self::IMPORT_STATUS_SUCCESS;
	}

	/**
	 * Import blogs from eDirectory to GeoDirectory.
	 *
	 * @since 2.0.2
	 * @param array $blog The offset to start importing from.
	 * @return array Result of the import operation.
	 */
	public function import_single_blog( array $blog ) {
		$wp_author_id = (int) $this->get_import_setting( 'wp_author_id', 1 );
		$blog_id      = ! $this->is_test_mode() ? (int) $this->get_gd_post_id( $blog['id'], 'edirectory_blog_id' ) : false;
		$is_update    = ! empty( $blog_id );

		// Get the category mapping.
		$category_mapping = (array) $this->options_handler->get_option_no_cache( 'category_mapping', array() );
		$categories       = isset( $blog['categories'] ) && is_array( $blog['categories'] ) ? (array) $blog['categories'] : array();

		// Map the categories.
		$gd_categories = array();
		foreach ( $categories as $category ) {
			$gd_categories[] = isset( $category_mapping[ $category['id'] ] ) ? (int) $category_mapping[ $category['id'] ] : 0;
		}

		$friendly_url = $this->extract_slug_from_url( $blog['detail_url'] );

		// Import images from content.
		$post_content = isset( $blog['content'] ) ? $blog['content'] : '';
		$post_content = $this->import_images_from_content( $post_content, $this->base_url );
		$post_content = $this->replace_links( $post_content, $this->base_url );

		// Convert publication date to GMT.
		$post_date     = isset( $blog['entered'] ) ? $blog['entered'] : current_time( 'mysql' ); // 2024-11-14T23:27:46-0600.
		$post_date     = new DateTime( $post_date );
		$post_date_gmt = clone $post_date;
		$post_date_gmt->setTimezone( new DateTimeZone( 'GMT' ) );

		$post_updated     = current_time( 'mysql' );
		$post_updated_gmt = current_time( 'mysql', 1 );

		$post_data = array(
			'post_author'       => $wp_author_id,
			'post_content'      => $post_content,
			'post_title'        => $blog['title'],
			'post_name'         => $friendly_url,
			'post_status'       => 'publish',
			'post_date_gmt'     => $post_date_gmt->format( 'Y-m-d H:i:s' ),
			'post_date'         => $post_date->format( 'Y-m-d H:i:s' ),
			'post_modified_gmt' => $post_updated_gmt,
			'post_modified'     => $post_updated,
		);

		// Handle test mode.
		if ( $this->is_test_mode() ) {
			return GeoDir_Converter_Importer::IMPORT_STATUS_SUCCESS;
		}

		// Insert or update the post.
		if ( $is_update ) {
			$post_data['ID'] = (int) $blog_id;
			$blog_id         = wp_update_post( $post_data, true );
		} else {
			$blog_id = wp_insert_post( $post_data, true );
		}

		// Handle errors during post insertion/update.
		if ( is_wp_error( $blog_id ) ) {
			$this->log( $blog_id->get_error_message() );
			return GeoDir_Converter_Importer::IMPORT_STATUS_FAILED;
		}

		// Import featured image.
		if ( isset( $blog['image_url'] ) && ! empty( $blog['image_url'] ) ) {
			$attachment = $this->import_attachment( $blog['image_url'] );
			if ( isset( $attachment['id'] ) ) {
				set_post_thumbnail( $blog_id, $attachment['id'] );
			}
		}

		update_post_meta( $blog_id, 'edirectory_blog_id', $blog['id'] );

		return $is_update ? GeoDir_Converter_Importer::IMPORT_STATUS_SKIPPED : GeoDir_Converter_Importer::IMPORT_STATUS_SUCCESS;
	}

	/**
	 * Parse weekly hours and convert to JSON-compatible business hour format.
	 *
	 * @param string $hours_string Raw weekly hours string.
	 * @param string $timezone     Timezone identifier (e.g., 'Pacific/Fiji').
	 * @return string JSON-formatted weekly hours with UTC offset.
	 */
	private function parse_business_hours( $hours_string, $timezone = 'UTC' ) {
		if ( empty( $hours_string ) || ! is_string( $hours_string ) ) {
			return new WP_Error( 'invalid_hours_string', __( 'Invalid hours string.', 'geodir-converter' ) );
		}

		$day_map = array(
			'sunday'    => 'Su',
			'monday'    => 'Mo',
			'tuesday'   => 'Tu',
			'wednesday' => 'We',
			'thursday'  => 'Th',
			'friday'    => 'Fr',
			'saturday'  => 'Sa',
		);

		$lines  = preg_split( '/\r\n|\r|\n/', trim( $hours_string ) );
		$output = array();

		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( ! $line || strpos( $line, ':' ) === false ) {
				continue;
			}

			[ $day_raw, $times_raw ] = explode( ':', $line, 2 );

			$day  = strtolower( trim( $day_raw ) );
			$abbr = $day_map[ $day ] ?? null;
			if ( ! $abbr ) {
				continue;
			}

			$time_ranges = array_filter( array_map( 'trim', explode( '/', $times_raw ) ) );
			$ranges_24h  = array();

			foreach ( $time_ranges as $range ) {
				$range = str_replace( ' - ', '-', $range );
				$times = explode( '-', $range );

				if ( count( $times ) === 2 ) {
					$start_24     = date( 'H:i', strtotime( $times[0] ) );
					$end_24       = date( 'H:i', strtotime( $times[1] ) );
					$ranges_24h[] = $start_24 . '-' . $end_24;
				}
			}

			if ( $ranges_24h ) {
				$output[] = $abbr . ' ' . implode( ',', $ranges_24h );
			}
		}

		// Convert timezone to UTC offset.
		try {
			$tz         = new DateTimeZone( $timezone );
			$dt         = new DateTime( 'now', $tz );
			$utc_offset = $dt->format( 'P' ); // e.g., +12:00.
		} catch ( Exception $e ) {
			$utc_offset = '+00:00';
		}

		// Final JSON format.
		$json  = '["' . implode( '","', $output ) . '"]';
		$json .= ',["UTC":"' . $utc_offset . '"]';

		return $json;
	}

	/**
	 * Parse a recurring phrase into an array of weekday indexes (0 = Sunday, ..., 6 = Saturday).
	 *
	 * Supports phrases like "Every Monday, Tuesday and Wednesday".
	 *
	 * @param string $phrase Recurring phrase describing weekdays.
	 * @return int[] Array of numeric weekdays in 0-6 format.
	 */
	private function parse_recurring_phrase( string $phrase ) {
		$phrase = strtolower( trim( $phrase ) );

		// Define known constants.
		$day_map = array(
			'sunday'    => 0,
			'monday'    => 1,
			'tuesday'   => 2,
			'wednesday' => 3,
			'thursday'  => 4,
			'friday'    => 5,
			'saturday'  => 6,
		);

		$ordinals_map = array(
			'1st' => 1,
			'2nd' => 2,
			'3rd' => 3,
			'4th' => 4,
			'5th' => 5,
		);

		$months = array_map(
			'strtolower',
			array(
				'January',
				'February',
				'March',
				'April',
				'May',
				'June',
				'July',
				'August',
				'September',
				'October',
				'November',
				'December',
			)
		);

		// Normalize string.
		$phrase   = str_replace( array( ' and ', ', ' ), array( ', ', ', ' ), $phrase );
		$type     = 'week';
		$ordinals = array();
		$days     = array();
		$month    = null;

		// Determine type.
		if ( str_starts_with( $phrase, 'every year' ) ) {
			$type = 'year';
		} elseif ( str_contains( $phrase, 'of the month' ) ) {
			$type = 'month';
		}

		// Extract ordinals.
		preg_match_all( '/\b(1st|2nd|3rd|4th|5th)\b/', $phrase, $ordinal_matches );
		foreach ( $ordinal_matches[0] as $ordinal ) {
			if ( isset( $ordinals_map[ $ordinal ] ) ) {
				$ordinals[] = $ordinals_map[ $ordinal ];
			}
		}

		// Extract days.
		foreach ( $day_map as $day => $index ) {
			if ( str_contains( $phrase, $day ) ) {
				$days[] = $index;
			}
		}

		// Extract month (for yearly only).
		if ( 'year' === $type ) {
			foreach ( $months as $m ) {
				if ( str_contains( $phrase, $m ) ) {
					$month = $m;
					break;
				}
			}
		}

		return array(
			'type'     => $type,
			'ordinals' => $ordinals,
			'days'     => array_values( $days ),
			'month'    => $month,
		);
	}

	/**
	 * Get gallery images.
	 *
	 * @since 2.0.2
	 * @param array $gallery The gallery to import.
	 * @return string Gallery images string.
	 */
	private function import_gallery_image( $gallery ) {
		$image_ids = array();
		foreach ( $gallery as $image ) {
			$imported_image = $this->import_attachment( $image['image_url'] );
			if ( isset( $imported_image['id'] ) ) {
				$image_ids[] = absint( $imported_image['id'] );
			}
		}

		$images = array_map(
			function ( $id ) {
				return array(
					'id'      => (int) $id,
					'caption' => '',
					'weight'  => 1,
				);
			},
			$image_ids
		);

		return $this->format_images_data( $images );
	}

	/**
	 * Extracts a slug from a detail URL.
	 *
	 * Example:
	 * Input:  https://edirectory.com/event/test-event.html
	 * Output: test-event
	 *
	 * @param string $url The full detail URL.
	 * @return string|null The extracted slug or null on failure.
	 */
	private function extract_slug_from_url( $url ) {
		$parsed_url = parse_url( $url );

		if ( ! isset( $parsed_url['path'] ) || empty( $parsed_url['path'] ) ) {
			return null;
		}

		$path     = $parsed_url['path'];                   // e.g., /event/test-event.html
		$filename = pathinfo( $path, PATHINFO_FILENAME );  // e.g., test-event

		if ( empty( $filename ) ) {
			return null;
		}

		// Sanitize to ensure it's safe for use in slugs or titles.
		return sanitize_title( $filename );
	}

	/**
	 * Downloads and replaces image URLs from a specific base URL in HTML content.
	 *
	 * @param string $content  HTML content to process.
	 * @param string $base_url Base URL to match (e.g., https://example.com/).
	 * @return string Updated content with local image URLs.
	 */
	protected function import_images_from_content( $content, $base_url ) {
		if ( empty( $content ) || empty( $base_url ) ) {
			return $content;
		}

		$image_urls = $this->extract_image_urls( $content, $base_url );

		foreach ( $image_urls as $image_url ) {
			$attachment = $this->import_attachment( $image_url );

			if ( isset( $attachment['url'] ) ) {
				$content = str_replace( $image_url, esc_url( $attachment['url'] ), $content );
			}
		}

		return $content;
	}

	/**
	 * Extract image URLs from content that match a given base URL.
	 *
	 * @param string $content  The HTML content.
	 * @param string $base_url The base URL to match.
	 * @return array Array of matched image URLs.
	 */
	protected function extract_image_urls( $content, $base_url ) {
		$clean_base = preg_replace( '#^https?://#', '', rtrim( $base_url, '/' ) );
		$pattern    = '#<img[^>]+src=["\'](https?://' . preg_quote( $clean_base, '#' ) . '/[^"\']+)["\']#i';

		preg_match_all( $pattern, $content, $matches );

		return isset( $matches[1] ) ? array_unique( $matches[1] ) : array();
	}

	/**
	 * Replaces links in content with local links.
	 *
	 * @param string $content  The HTML content.
	 * @param string $base_url The base URL to match.
	 * @return string Updated content with local links.
	 */
	protected function replace_links( $content, $base_url ) {
		$home_url = home_url();
		$host     = preg_quote( wp_parse_url( $base_url, PHP_URL_HOST ), '#' );

		// Clean <a> tags with .html at end from base domain.
		$content = preg_replace_callback(
			'#<a([^>]+?)href=["\']https?://' . $host . '([^"\']+?)\.html(["\'])#i',
			function ( $matches ) use ( $home_url ) {
				$new_href = $home_url . $matches[2]; // remove .html.
				return '<a' . $matches[1] . 'href="' . esc_url( $new_href ) . $matches[3];
			},
			$content
		);

		// Clean raw URLs in text ending in .html (e.g. https://site.com/page.html).
		$content = preg_replace_callback(
			'#(?<!["\'])https?://' . $host . '([^"\s<]+?)\.html(?!\w)#i',
			function ( $matches ) use ( $home_url ) {
				return esc_url( $home_url . $matches[1] );
			},
			$content
		);

		$content = preg_replace_callback(
			'#<a([^>]+?)href=["\']https?://' . $host . '([^"\']+)["\']#i',
			function ( $matches ) use ( $home_url ) {
				$new_href = $home_url . $matches[2];
				return '<a' . $matches[1] . 'href="' . esc_url( $new_href ) . '"';
			},
			$content
		);

		return $content;
	}

	/**
	 * Map eDirectory field type to GeoDirectory field type.
	 *
	 * @param string $field_type The eDirectory field type.
	 * @return string|false The GeoDirectory field type or false if not supported.
	 */
	private function map_data_type( $field_type ) {
		switch ( $field_type ) {
			case 'input_text':
			case 'textarea':
			case 'url':
			case 'select':
			case 'multiselect':
			case 'radio':
			case 'file':
			case 'business_hours':
				return 'TEXT';
			case 'checkbox':
				return 'TINYINT';
			case 'number':
				return 'INT';
			default:
				return 'VARCHAR';
		}
	}

	/**
	 * Make an API GET request.
	 *
	 * @param string $endpoint  API endpoint path.
	 * @param array  $args      Query parameters.
	 * @param array  $options   Request options.
	 *
	 * @return array|WP_Error Response data or error.
	 */
	public function get( $endpoint, $args = array(), $options = array() ) {
		return $this->request( 'GET', $endpoint, $args, null, $options );
	}

	/**
	 * Make a POST request.
	 *
	 * @param string $endpoint API endpoint.
	 * @param array  $data     Post body.
	 * @param array  $args     Query parameters.
	 * @param array  $options  Request options.
	 *
	 * @return array|WP_Error Response data or error.
	 */
	public function post( $endpoint, $data = array(), $args = array(), $options = array() ) {
		return $this->request( 'POST', $endpoint, $args, $data, $options );
	}

	/**
	 * Make an API request.
	 *
	 * @param string $method   HTTP method (GET, POST, etc).
	 * @param string $endpoint API endpoint path.
	 * @param array  $args     Query parameters.
	 * @param mixed  $data     Request body for POST/PUT/PATCH.
	 * @param array  $options  Request options.
	 *
	 * @return array|WP_Error Response data or error.
	 */
	public function request( $method, $endpoint, $args = array(), $data = null, $options = array() ) {
		$method = strtoupper( $method );

		$args['token'] = $this->api_token;
		$url           = $this->build_url( $endpoint, $args );

		// Prepare request arguments.
		$timeout = isset( $options['timeout'] ) ? absint( $options['timeout'] ) : $this->timeout;

		if ( in_array( $method, array( 'POST', 'PUT', 'PATCH' ), true ) && null !== $data ) {
			$headers['Content-Type'] = 'application/json';
		}

		$request_args = array(
			'method'  => $method,
			'timeout' => $timeout,
		);

		// Add body data for methods that support it.
		if ( in_array( $method, array( 'POST', 'PUT', 'PATCH' ), true ) && null !== $data ) {
			$request_args['body'] = wp_json_encode( $data );
		}

		// Add custom user agent.
		$request_args['user-agent'] = 'EDirectory_API_Client/2.0.0 WordPress/' . get_bloginfo( 'version' );

		// Apply filters to allow modification of request args.
		$request_args = apply_filters( 'edirectory_api_request_args', $request_args, $method, $endpoint, $args, $data, $options );

		// Make the request.
		$response = wp_remote_request( $url, $request_args );

		// Handle response.
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		// Try to decode JSON response.
		$data = json_decode( $body, true );
		if ( JSON_ERROR_NONE !== json_last_error() && ! empty( $body ) ) {
			$data = $body;
		}

		// Handle successful response.
		if ( $code >= 200 && $code < 300 ) {
			return $data;
		}

		// Handle error response.
		$error_message = isset( $data['message'] ) ? $data['message'] : 'eDirectory API error';
		$error_code    = isset( $data['code'] ) ? $data['code'] : 'ed_api_error';

		$error = new WP_Error(
			$error_code,
			$error_message,
			array(
				'status'   => $code,
				'response' => $data,
				'request'  => array(
					'method'   => $method,
					'endpoint' => $endpoint,
					'args'     => $args,
					'data'     => $data,
				),
			)
		);

		// Allow error handling through filters.
		return apply_filters( 'edirectory_api_response_error', $error, $response, $method, $endpoint, $args, $data, $options );
	}

	/**
	 * Build the full URL for an API request.
	 *
	 * @param string $endpoint API endpoint path.
	 * @param array  $args     Query parameters.
	 *
	 * @return string Full URL.
	 */
	private function build_url( $endpoint, $args = array() ) {
		$endpoint = ltrim( $endpoint, '/' );
		$base_url = $this->base_url;

		// Allow filtering of base URL.
		$base_url = apply_filters( 'edirectory_api_base_url', $base_url, $endpoint, $args );

		// Build the URL with query args.
		$url = "{$base_url}/{$endpoint}";
		if ( ! empty( $args ) ) {
			$url = add_query_arg( $args, $url );
		}

		return $url;
	}
}
