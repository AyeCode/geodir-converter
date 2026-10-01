<?php
/**
 * PhpMyDirectory Converter Class.
 *
 * @since      2.0.2
 * @package    GeoDir_Converter
 * @version    2.0.2
 */

namespace GeoDir_Converter\Importers;

use WP_Error;
use WP_User;
use Exception;
use GeoDir_User;
use GeoDir_Media;
use WPInv_Invoice;
use WPInv_Discount;
use GeoDir_Comments;
use GetPaid_Form_Item;
use GeoDir_Pricing_Package;
use GeoDir_Converter\GeoDir_Converter_WPDB;
use GeoDir_Converter\Abstracts\GeoDir_Converter_Importer;

defined( 'ABSPATH' ) || exit;

/**
 * Main converter class for importing from PhpMyDirectory.
 *
 * @since 2.0.2
 */
class GeoDir_Converter_PMD extends GeoDir_Converter_Importer {
	/**
	 * Action identifier for importing users.
	 *
	 * @var string
	 */
	private const ACTION_IMPORT_USERS = 'import_users';

	/**
	 * Action identifier for importing blog categories.
	 *
	 * @var string
	 */
	private const ACTION_IMPORT_BLOG_CATEGORIES = 'import_blog_categories';

	/**
	 * Action identifier for importing event categories.
	 *
	 * @var string
	 */
	private const ACTION_IMPORT_EVENTS_CATEGORIES = 'import_events_categories';

	/**
	 * Action identifier for importing invoices.
	 *
	 * @var string
	 */
	private const ACTION_IMPORT_INVOICES = 'import_invoices';

	/**
	 * Action identifier for importing discounts.
	 *
	 * @var string
	 */
	private const ACTION_IMPORT_DISCOUNTS = 'import_discounts';

	/**
	 * Action identifier for importing products.
	 *
	 * @var string
	 */
	private const ACTION_IMPORT_PRODUCTS = 'import_products';

	/**
	 * Action identifier for importing reviews.
	 *
	 * @var string
	 */
	private const ACTION_IMPORT_REVIEWS = 'import_reviews';

	/**
	 * Action identifier for importing review replies.
	 *
	 * @since 2.3.0
	 * @var string
	 */
	private const ACTION_IMPORT_REVIEW_COMMENTS = 'import_review_comments';

	/**
	 * Action identifier for importing favorites.
	 *
	 * @since 2.3.0
	 * @var string
	 */
	private const ACTION_IMPORT_FAVORITES = 'import_favorites';

	/**
	 * Action identifier for importing events.
	 *
	 * @var string
	 */
	private const ACTION_IMPORT_EVENTS = 'import_events';

	/**
	 * Action identifier for importing comments.
	 *
	 * @var string
	 */
	private const ACTION_IMPORT_COMMENTS = 'import_comments';

	/**
	 * Action identifier for importing posts.
	 *
	 * @var string
	 */
	private const ACTION_IMPORT_POSTS = 'import_posts';

	/**
	 * Action identifier for importing pages.
	 *
	 * @var string
	 */
	private const ACTION_IMPORT_PAGES = 'import_pages';

	/**
	 * Post type identifier for events.
	 *
	 * @var string
	 */
	private const POST_TYPE_EVENTS = 'gd_event';

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
	protected $importer_id = 'pmd';

	/**
	 * Database connection instance.
	 *
	 * @var GeoDir_Converter_WPDB|null
	 */
	private $db_connection = null;

	/**
	 * Database table prefix.
	 *
	 * @var string
	 */
	private $db_prefix = '';

	/**
	 * PMD site URL.
	 *
	 * @var string
	 */
	private $url = '';

	/**
	 * PMD locations keyed by ID, loaded once per request.
	 *
	 * @since 2.3.0
	 * @var array|null
	 */
	private $locations_cache = null;

	/**
	 * Cached PMD table existence checks.
	 *
	 * @since 2.3.0
	 * @var array
	 */
	private $table_exists_cache = array();

	/**
	 * Cached PMD listing/event ID to GD post ID lookups, keyed by post type.
	 *
	 * @since 2.3.0
	 * @var array
	 */
	private $gd_ids_cache = array();

	/**
	 * Cached custom field mappings, keyed by post type.
	 *
	 * @since 2.3.0
	 * @var array
	 */
	private $fields_mapping_cache = array();

	/**
	 * Whether the PMD products tables are compatible with the products import.
	 *
	 * @since 2.3.0
	 * @var bool|null
	 */
	private $products_compatible = null;

	/**
	 * Cached PMD pricing ID to GD package ID mapping.
	 *
	 * @since 2.3.0
	 * @var array|null
	 */
	private $packages_mapping_cache = null;

	/**
	 * Cached PMD user ID to WP user ID mapping.
	 *
	 * @since 2.3.0
	 * @var array|null
	 */
	private $user_mapping_cache = null;

	/**
	 * Initialize hooks.
	 *
	 * @since 2.0.2
	 *
	 * @return void
	 */
	protected function init() {
		// The PMD site URL is read when needed (see get_pmd_url()): reading the import
		// settings here would cache them before import() saves the new ones.

		// Handle logins for imported users.
		add_filter( 'wp_authenticate_user', array( $this, 'handle_user_login' ), 10, 2 );

		// Forget the PMD password once a new WordPress password is set.
		add_action( 'wp_set_password', array( $this, 'clear_pmd_password_on_set' ), 10, 2 );
		add_action( 'profile_update', array( $this, 'clear_pmd_password_on_update' ), 10, 2 );
	}

	/**
	 * Get importer title.
	 *
	 * @since 2.0.2
	 *
	 * @return string The importer title.
	 */
	public function get_title() {
		return __( 'PhpMyDirectory', 'geodir-converter' );
	}

	/**
	 * Get importer description.
	 *
	 * @since 2.0.2
	 *
	 * @return string The importer description.
	 */
	public function get_description() {
		return __( 'Import listings, events, users and invoices from your PhpMyDirectory installation.', 'geodir-converter' );
	}

	/**
	 * Get importer icon URL.
	 *
	 * @since 2.0.2
	 *
	 * @return string The importer icon URL.
	 */
	public function get_icon() {
		return GEODIR_CONVERTER_PLUGIN_URL . 'assets/images/pmd.jpeg';
	}

	/**
	 * Get importer task action.
	 *
	 * @since 2.0.2
	 *
	 * @return string The first import action identifier.
	 */
	public function get_action() {
		return self::ACTION_IMPORT_USERS;
	}

	/**
	 * Render importer settings.
	 *
	 * @since 2.0.2
	 *
	 * @return void
	 */
	public function render_settings() {
		?>
		<form class="geodir-converter-settings-form" method="post">
			<h6 class="fs-base"><?php esc_html_e( 'PhpMyDirectory Connection Settings', 'geodir-converter' ); ?></h6>

			<?php
			if ( ! defined( 'WPINV_VERSION' ) ) {
				$this->render_plugin_notice(
					esc_html__( 'Invoicing', 'geodir-converter' ),
					'invoices',
					esc_url( 'https://wordpress.org/plugins/invoicing' )
				);
			}

			if ( ! defined( 'GEODIR_EVENT_VERSION' ) ) {
				$this->render_plugin_notice(
					esc_html__( 'Events Addon', 'geodir-converter' ),
					'events',
					esc_url( 'https://wpgeodirectory.com/downloads/events/' )
				);
			}

			$this->display_post_type_select();
			$this->display_form_fields();
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
	 * Render form fields for the importer settings.
	 *
	 * @since 2.0.2
	 *
	 * @return void
	 */
	private function display_form_fields() {
		$import_settings  = (array) $this->options_handler->get_option( 'import_settings', array() );
		$default_settings = array(
			'site_url'          => '',
			'database_host'     => 'localhost',
			'database_name'     => 'pmd',
			'database_user'     => 'root',
			'database_password' => '',
			'database_prefix'   => 'pmd_',
		);

		$settings = wp_parse_args( array_map( 'sanitize_text_field', array_filter( $import_settings, 'is_scalar' ) ), $default_settings );

		// Define fields.
		$fields = array(
			'site_url'          => array(
				'label'       => __( 'PMD Root URL', 'geodir-converter' ),
				'type'        => 'url',
				'placeholder' => 'https://mysite.com/',
				'value'       => $settings['site_url'],
				'required'    => true,
			),
			'database_host'     => array(
				'label'    => __( 'Database Host Name', 'geodir-converter' ),
				'type'     => 'text',
				'value'    => $settings['database_host'],
				'required' => true,
			),
			'database_name'     => array(
				'label'    => __( 'Database Name', 'geodir-converter' ),
				'type'     => 'text',
				'value'    => $settings['database_name'],
				'required' => true,
			),
			'database_user'     => array(
				'label'    => __( 'Database Username', 'geodir-converter' ),
				'type'     => 'text',
				'value'    => $settings['database_user'],
				'required' => true,
			),
			'database_password' => array(
				'label' => __( 'Database Password', 'geodir-converter' ),
				'type'  => 'password',
				'value' => $settings['database_password'],
			),
			'database_prefix'   => array(
				'label' => __( 'Table Prefix', 'geodir-converter' ),
				'type'  => 'text',
				'value' => $settings['database_prefix'],
			),
		);

		echo '<div class="row">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		foreach ( $fields as $id => $field ) {
			aui()->input(
				array(
					'id'          => esc_attr( $id ),
					'name'        => esc_attr( $id ),
					'type'        => esc_attr( $field['type'] ),
					'placeholder' => isset( $field['placeholder'] ) ? esc_attr( $field['placeholder'] ) : '',
					'label'       => esc_html( $field['label'] ),
					'label_type'  => 'top',
					'value'       => esc_attr( $field['value'] ),
					'required'    => isset( $field['required'] ) ? $field['required'] : false,
					'wrap_class'  => 'col-md-6',
				),
				true
			);
		}
		echo '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Validate importer settings.
	 *
	 * @since 2.0.2
	 *
	 * @param array $settings The settings to validate.
	 * @param array $files    The files to validate.
	 * @return array|WP_Error Validated and sanitized settings or WP_Error on failure.
	 */
	public function validate_settings( array $settings, array $files = array() ) {
		$settings['gd_post_type']      = ! empty( $settings['gd_post_type'] ) ? sanitize_text_field( $settings['gd_post_type'] ) : 'gd_place';
		$settings['test_mode']         = ( isset( $settings['test_mode'] ) && ! empty( $settings['test_mode'] ) && 'no' !== $settings['test_mode'] ) ? 'yes' : 'no';
		$settings['site_url']          = isset( $settings['site_url'] ) ? esc_url_raw( $settings['site_url'] ) : '';
		$settings['database_host']     = isset( $settings['database_host'] ) ? sanitize_text_field( $settings['database_host'] ) : '';
		$settings['database_name']     = isset( $settings['database_name'] ) ? sanitize_text_field( $settings['database_name'] ) : '';
		$settings['database_user']     = isset( $settings['database_user'] ) ? sanitize_text_field( $settings['database_user'] ) : '';
		$settings['database_password'] = isset( $settings['database_password'] ) ? (string) $settings['database_password'] : '';
		$settings['database_prefix']   = isset( $settings['database_prefix'] ) ? preg_replace( '/[^A-Za-z0-9_]/', '', (string) $settings['database_prefix'] ) : '';

		$errors = array();

		if ( ! geodir_is_gd_post_type( $settings['gd_post_type'] ) ) {
			$errors[] = esc_html__( 'The selected post type is invalid. Please choose a valid post type.', 'geodir-converter' );
		}

		// Validate and sanitize site URL.
		if ( empty( $settings['site_url'] ) ) {
			$errors[] = esc_html__( 'PMD root URL is required.', 'geodir-converter' );
		} elseif ( ! wp_http_validate_url( $settings['site_url'] ) ) {
			$errors[] = esc_html__( 'Invalid PMD root URL.', 'geodir-converter' );
		}

		// Validate and sanitize database host.
		if ( empty( $settings['database_host'] ) ) {
			$errors[] = esc_html__( 'Database host is required.', 'geodir-converter' );
		}

		// Validate and sanitize database name.
		if ( empty( $settings['database_name'] ) ) {
			$errors[] = esc_html__( 'Database name is required.', 'geodir-converter' );
		}

		// Validate and sanitize database user.
		if ( empty( $settings['database_user'] ) ) {
			$errors[] = esc_html__( 'Database username is required.', 'geodir-converter' );
		}

		// If there are no errors, try to establish a database connection.
		if ( empty( $errors ) ) {
			$connection_result = $this->test_database_connection( $settings );
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
	 * Test the database connection and required tables.
	 *
	 * @since 2.0.2
	 *
	 * @param array $settings Validated settings.
	 * @return true|WP_Error True on success, WP_Error on failure.
	 */
	private function test_database_connection( $settings ) {
		$this->options_handler->update_option( 'import_settings', $settings );

		// Reset the connection to force a new connection with new settings.
		$this->db_connection      = null;
		$this->table_exists_cache = array();

		// Get database connection.
		$db_connection = $this->get_db_connection();

		// Check if we got an error instead of a connection.
		if ( is_wp_error( $db_connection ) ) {
			if ( $db_connection->get_error_code() === 'db_connect_fail' ) {
				return new WP_Error(
					'db_connect_fail',
					sprintf(
					/* translators: %s is the database error message */
						'<h6 class="mb-2">%s</h6>
                    <ul class="ps-0">
                        <li>%s</li>
                        <li>%s</li>
                        <li>%s</li>
                    </ul>
                    <strong class="mb-0">%s</strong>',
						esc_html__( 'Database connection failed', 'geodir-converter' ),
						esc_html__( 'Are you sure you have the correct username and password?', 'geodir-converter' ),
						esc_html__( 'Are you sure you have typed the correct hostname?', 'geodir-converter' ),
						esc_html__( 'Are you sure the database server is running?', 'geodir-converter' ),
						esc_html( mysqli_connect_error() ) // Display actual DB error.
					)
				);
			}

			return $db_connection;
		}

		// Required tables to check.
		$required_tables = array(
			'users',
			'users_groups_lookup',
			'categories',
			'images',
			'listings',
			'listings_categories',
			'fields',
			'fields_groups',
			'invoices',
			'discount_codes',
			'products',
			'products_pricing',
			'events',
			'events_categories',
			'blog',
			'blog_comments',
			'pages',
			'locations',
			'orders',
			'reviews',
			'ratings',
		);

		// Check if the required tables exist.
		$missing_tables = array();
		foreach ( $required_tables as $table ) {
			$table_name = $settings['database_prefix'] . $table;
			$query      = $db_connection->prepare( 'SHOW TABLES LIKE %s', $db_connection->esc_like( $table_name ) );

			if ( ! $db_connection->get_var( $query ) ) {
				$missing_tables[] = $table_name;
			}
		}

		if ( ! empty( $missing_tables ) ) {
			return new WP_Error(
				'tables_not_found',
				sprintf(
					'<h6 class="mb-2">%s</h6> <p class="mb-0">%s</p>',
					esc_html__( 'Required tables are missing', 'geodir-converter' ),
					esc_html( implode( ', ', $missing_tables ) )
				)
			);
		}

		return true;
	}

	/**
	 * Get or create the database connection.
	 *
	 * @since 2.0.2
	 *
	 * @return GeoDir_Converter_WPDB|WP_Error The database connection instance or WP_Error on failure.
	 */
	protected function get_db_connection() {
		try {
			if ( $this->db_connection === null ) {
				$settings = (array) $this->options_handler->get_option( 'import_settings', array() );

				// An empty password is valid (e.g. local MySQL installs), so it is not required here.
				if ( empty( $settings['database_user'] ) || empty( $settings['database_name'] ) || empty( $settings['database_host'] ) ) {
					return new WP_Error( 'invalid_db_settings', __( 'Invalid database settings', 'geodir-converter' ) );
				}

				$this->db_connection = new GeoDir_Converter_WPDB(
					$settings['database_user'],
					isset( $settings['database_password'] ) ? $settings['database_password'] : '',
					$settings['database_name'],
					$settings['database_host']
				);

				$this->db_prefix = isset( $settings['database_prefix'] ) ? preg_replace( '/[^A-Za-z0-9_]/', '', (string) $settings['database_prefix'] ) : '';

				$this->db_connection->hide_errors();
				$this->db_connection->db_connect();

				if ( is_wp_error( $this->db_connection->error ) ) {
					$error               = $this->db_connection->error;
					$this->db_connection = null;

					return $error;
				}
			}
		} catch ( Exception $e ) {
			$this->db_connection = null;

			return new WP_Error( 'db_connect_fail', $e->getMessage() );
		}

		return $this->db_connection;
	}

	/**
	 * Get the connected PMD database or throw.
	 *
	 * @since 2.3.0
	 *
	 * @return GeoDir_Converter_WPDB The database connection.
	 * @throws Exception If the database connection fails.
	 */
	private function require_db() {
		$db = $this->get_db_connection();

		if ( is_wp_error( $db ) ) {
			throw new Exception( $db->get_error_message() );
		}

		return $db;
	}

	/**
	 * Get next task.
	 *
	 * Each task paginates from its own offset, so the offset and counters are
	 * reset before handing over to the next action.
	 *
	 * @since 2.0.2
	 *
	 * @param array $task The current task.
	 * @return array|false The next task or false if all tasks are completed.
	 */
	public function next_task( $task ) {
		$task['imported'] = 0;
		$task['failed']   = 0;
		$task['skipped']  = 0;
		$task['updated']  = 0;
		$task['offset']   = 0;

		$tasks = array(
			self::ACTION_IMPORT_USERS,
			self::ACTION_IMPORT_PRODUCTS,
			self::ACTION_IMPORT_FIELDS,
			self::ACTION_IMPORT_CATEGORIES,
			self::ACTION_IMPORT_BLOG_CATEGORIES,
			self::ACTION_IMPORT_EVENTS_CATEGORIES,
			self::ACTION_IMPORT_LISTINGS,
			self::ACTION_IMPORT_EVENTS,
			self::ACTION_IMPORT_REVIEWS,
			self::ACTION_IMPORT_REVIEW_COMMENTS,
			self::ACTION_IMPORT_FAVORITES,
			self::ACTION_IMPORT_PAGES,
			self::ACTION_IMPORT_POSTS,
			self::ACTION_IMPORT_COMMENTS,
			self::ACTION_IMPORT_DISCOUNTS,
			self::ACTION_IMPORT_INVOICES,
		);

		$key = array_search( $task['action'], $tasks, true );
		if ( false !== $key && $key + 1 < count( $tasks ) ) {
			$task['action'] = $tasks[ $key + 1 ];
			return $task;
		}

		return false;
	}

	/**
	 * Calculate the total number of items the import will process.
	 *
	 * Uses the same counts and the same plugin conditions as the tasks, so the
	 * processed stats add up to this total and progress reaches 100%.
	 *
	 * @since 2.3.0
	 *
	 * @return void
	 */
	private function set_import_total() {
		$post_type = $this->get_import_post_type();
		$total     = 0;

		$total += $this->count_table_rows( 'users' );

		if ( $this->products_enabled() ) {
			$total += $this->count_products_rows();
		}

		$total += count( $this->get_standard_fields( $post_type ) );

		if ( $this->events_enabled() ) {
			$total += count( $this->get_standard_fields( self::POST_TYPE_EVENTS ) );
		}

		$total += $this->count_custom_fields();
		$total += $this->count_table_rows( 'categories' );
		$total += $this->count_table_rows( 'blog_categories' );

		if ( $this->events_enabled() ) {
			$total += $this->count_table_rows( 'events_categories' );
		}

		$total += $this->count_table_rows( 'listings' );

		if ( $this->events_enabled() ) {
			$total += $this->count_table_rows( 'events' );
		}

		$total += $this->count_table_rows( 'reviews' );
		$total += $this->count_table_rows( 'reviews_comments' );
		$total += $this->count_table_rows( 'favorites' );
		$total += $this->count_table_rows( 'pages' );
		$total += $this->count_table_rows( 'blog' );
		$total += $this->count_table_rows( 'blog_comments' );

		if ( class_exists( 'WPInv_Discount' ) ) {
			$total += $this->count_table_rows( 'discount_codes' );
		}

		if ( class_exists( 'WPInv_Invoice' ) ) {
			$total += $this->count_table_rows( 'invoices' );
		}

		$this->increase_imports_total( $total );
	}

	/**
	 * Check whether a PMD table exists.
	 *
	 * @since 2.3.0
	 *
	 * @param string $table Table name without prefix.
	 * @return bool True if the table exists.
	 */
	private function table_exists( $table ) {
		if ( isset( $this->table_exists_cache[ $table ] ) ) {
			return $this->table_exists_cache[ $table ];
		}

		$db = $this->get_db_connection();

		if ( is_wp_error( $db ) ) {
			return false;
		}

		$exists = (bool) $db->get_var( $db->prepare( 'SHOW TABLES LIKE %s', $db->esc_like( $this->db_prefix . $table ) ) );

		$this->table_exists_cache[ $table ] = $exists;

		return $exists;
	}

	/**
	 * Count the rows of a PMD table.
	 *
	 * @since 2.3.0
	 *
	 * @param string $table Table name without prefix.
	 * @return int Number of rows, 0 when the table does not exist.
	 */
	private function count_table_rows( $table ) {
		if ( ! $this->table_exists( $table ) ) {
			return 0;
		}

		$db = $this->get_db_connection();

		return (int) $db->get_var( "SELECT COUNT(*) FROM `{$this->db_prefix}{$table}`" );
	}

	/**
	 * Count the product pricing rows processed by the products task.
	 *
	 * @since 2.3.0
	 *
	 * @return int Number of rows.
	 */
	private function count_products_rows() {
		$db = $this->get_db_connection();

		if ( is_wp_error( $db ) ) {
			return 0;
		}

		return (int) $db->get_var( "SELECT COUNT(*) FROM `{$this->db_prefix}products` p LEFT JOIN `{$this->db_prefix}products_pricing` pp ON p.`id` = pp.`product_id`" );
	}

	/**
	 * Get the PMD field group types imported as custom fields.
	 *
	 * @since 2.3.0
	 *
	 * @return array Field group types.
	 */
	private function get_custom_field_group_types() {
		$types = array( 'listings' );

		if ( $this->events_enabled() ) {
			$types[] = 'events';
		}

		return $types;
	}

	/**
	 * Count the PMD custom fields imported by the fields task.
	 *
	 * @since 2.3.0
	 *
	 * @return int Number of fields.
	 */
	private function count_custom_fields() {
		$db = $this->get_db_connection();

		if ( is_wp_error( $db ) || ! $this->table_exists( 'fields_groups' ) ) {
			return 0;
		}

		$types        = $this->get_custom_field_group_types();
		$placeholders = implode( ',', array_fill( 0, count( $types ), '%s' ) );

		return (int) $db->get_var(
			$db->prepare(
				"SELECT COUNT(*) FROM `{$this->db_prefix}fields` f INNER JOIN `{$this->db_prefix}fields_groups` fg ON f.`group_id` = fg.`id` WHERE fg.`type` IN ({$placeholders})",
				$types
			)
		);
	}

	/**
	 * Whether events can be imported.
	 *
	 * @since 2.3.0
	 *
	 * @return bool True if the GeoDirectory Events addon is active.
	 */
	private function events_enabled() {
		return class_exists( 'GeoDir_Event_Manager' ) && post_type_exists( self::POST_TYPE_EVENTS );
	}

	/**
	 * Whether PMD products can be imported as GD price packages.
	 *
	 * @since 2.3.0
	 *
	 * @return bool True if the pricing manager is active and the PMD pricing table is compatible.
	 */
	private function products_enabled() {
		if ( null !== $this->products_compatible ) {
			return $this->products_compatible;
		}

		$this->products_compatible = false;

		if ( ! class_exists( 'GeoDir_Pricing_Package' ) || ! $this->table_exists( 'products_pricing' ) ) {
			return false;
		}

		$db      = $this->get_db_connection();
		$columns = $db->get_col( "SHOW COLUMNS FROM `{$this->db_prefix}products_pricing`" );

		// Older pricing table is different from this.
		$this->products_compatible = is_array( $columns ) && in_array( 'overdue_pricing_id', $columns, true );

		return $this->products_compatible;
	}

	/**
	 * Read the running counters carried by a paginated task.
	 *
	 * @since 2.3.0
	 *
	 * @param array $task The task.
	 * @return array Counters keyed imported, updated, skipped and failed.
	 */
	private function get_task_counts( array $task ) {
		return array(
			'imported' => isset( $task['imported'] ) ? absint( $task['imported'] ) : 0,
			'updated'  => isset( $task['updated'] ) ? absint( $task['updated'] ) : 0,
			'skipped'  => isset( $task['skipped'] ) ? absint( $task['skipped'] ) : 0,
			'failed'   => isset( $task['failed'] ) ? absint( $task['failed'] ) : 0,
		);
	}

	/**
	 * Add an import status to the running counters.
	 *
	 * @since 2.3.0
	 *
	 * @param array $counts Counters, updated in place.
	 * @param int   $status Import status constant.
	 * @return void
	 */
	private function count_status( array &$counts, $status ) {
		switch ( $status ) {
			case self::IMPORT_STATUS_SUCCESS:
				++$counts['imported'];
				break;
			case self::IMPORT_STATUS_UPDATED:
				++$counts['updated'];
				break;
			case self::IMPORT_STATUS_SKIPPED:
				++$counts['skipped'];
				break;
			default:
				++$counts['failed'];
				break;
		}
	}

	/**
	 * Report a finished batch and move on to the next batch or task.
	 *
	 * @since 2.3.0
	 *
	 * @param array  $task       The task.
	 * @param array  $counts     Cumulative counters for the task.
	 * @param int    $offset     Offset of the batch that just ran.
	 * @param int    $batch_size Batch size.
	 * @param int    $total      Total rows for the task.
	 * @param string $label      Human-readable name of what is imported.
	 * @return array|false The task to run next, or false if all tasks are completed.
	 */
	private function finish_batch( array $task, array $counts, $offset, $batch_size, $total, $label ) {
		$this->sync_task_counters( $task, $counts['imported'], $counts['failed'], $counts['skipped'], $counts['updated'] );
		$this->flush_failed_items();

		$processed = $counts['imported'] + $counts['updated'] + $counts['skipped'] + $counts['failed'];

		if ( $offset + $batch_size < $total ) {
			$this->log(
				sprintf(
					/* translators: %1$s: item type, %2$d: processed count, %3$d: total count */
					__( '%1$s: batch complete. Progress: %2$d/%3$d processed.', 'geodir-converter' ),
					$label,
					$processed,
					$total
				)
			);

			$task['offset'] = $offset + $batch_size;

			return $task;
		}

		$this->log(
			sprintf(
				/* translators: %1$s: item type, %2$d: processed count, %3$d: total count, %4$d: imported, %5$d: updated, %6$d: skipped, %7$d: failed */
				__( '%1$s: import completed. Processed: %2$d/%3$d, Imported: %4$d, Updated: %5$d, Skipped: %6$d, Failed: %7$d.', 'geodir-converter' ),
				$label,
				$processed,
				$total,
				$counts['imported'],
				$counts['updated'],
				$counts['skipped'],
				$counts['failed']
			),
			'success'
		);

		return $this->next_task( $task );
	}

	/**
	 * Run one checkpointed batch of per-item imports from a PMD table.
	 *
	 * Each row is claimed before processing so a row that crashes the worker is
	 * recorded as failed and skipped, and the offset is checkpointed so an
	 * interrupted batch resumes where it stopped.
	 *
	 * @since 2.3.0
	 *
	 * @param array    $task      The task.
	 * @param string   $action    Task action.
	 * @param string   $table     PMD table name without prefix.
	 * @param string   $item_type Human-readable item type.
	 * @param string   $total_key Task key holding the total.
	 * @param callable $handler   Receives a row and returns an IMPORT_STATUS_* code.
	 * @return array|false The task to run next, or false if all tasks are completed.
	 */
	private function process_item_batch( array $task, $action, $table, $item_type, $total_key, callable $handler ) {
		$db         = $this->require_db();
		$table_name = $this->db_prefix . $table;
		$offset     = $this->resume_offset( $action, $task );
		$batch_size = absint( $this->get_batch_size() );

		if ( ! isset( $task[ $total_key ] ) ) {
			$task[ $total_key ] = $this->count_table_rows( $table );
		}

		$total = absint( $task[ $total_key ] );

		if ( 0 === $offset ) {
			/* translators: %1$s: item type, %2$d: number of items */
			$this->log( sprintf( __( 'Starting %1$s import: %2$d found.', 'geodir-converter' ), $item_type, $total ) );
		}

		if ( 0 === $total ) {
			/* translators: %s: item type */
			$this->log( sprintf( __( 'No %s available for import. Skipping...', 'geodir-converter' ), $item_type ) );
			return $this->next_task( $task );
		}

		$rows = $db->get_results(
			$db->prepare(
				"SELECT t.* FROM `{$table_name}` t ORDER BY t.`id` ASC LIMIT %d, %d",
				$offset,
				$batch_size
			)
		);

		if ( empty( $rows ) ) {
			$this->clear_checkpoint();
			/* translators: %s: item type */
			$this->log( sprintf( __( 'No more %s to import. Process completed.', 'geodir-converter' ), $item_type ), 'success' );
			return $this->next_task( $task );
		}

		$processed = 0;
		$remaining = count( $rows );

		try {
			foreach ( $rows as $row ) {
				++$processed;
				--$remaining;

				$label = $this->get_row_label( $row );

				if ( $this->claim_item( $action, $row->id, $item_type, $label ) ) {
					$this->process_import_result( call_user_func( $handler, $row ), $item_type, $label, $row->id, $action );
				}

				$this->set_checkpoint( $action, $offset + $processed );

				if ( $remaining > 0 && $this->should_yield() ) {
					break;
				}
			}
		} finally {
			$this->clear_in_flight();
			$this->flush_progress();
		}

		$next_offset = $offset + $processed;

		if ( $next_offset < $total ) {
			$task['offset'] = $next_offset;
			return $task;
		}

		$this->clear_checkpoint();

		/* translators: %s: item type */
		$this->log( sprintf( __( 'Finished importing %s.', 'geodir-converter' ), $item_type ), 'success' );

		return $this->next_task( $task );
	}

	/**
	 * Re-run a single item queued by "Retry Failed".
	 *
	 * A retried task carries only the failed item's source ID, so the row is
	 * loaded again and imported on its own instead of re-running the whole task
	 * chain.
	 *
	 * @since 2.3.0
	 *
	 * @param array    $task      The retry task.
	 * @param string   $action    Task action.
	 * @param string   $table     PMD table name without prefix.
	 * @param string   $item_type Human-readable item type.
	 * @param callable $handler   Receives a row and returns an IMPORT_STATUS_* code.
	 * @return false Always false, so the queue drops the task.
	 */
	private function retry_single_item( array $task, $action, $table, $item_type, callable $handler ) {
		$db        = $this->require_db();
		$source_id = absint( $task['source_id'] );
		$row       = $db->get_row( $db->prepare( "SELECT * FROM `{$this->db_prefix}{$table}` WHERE `id` = %d", $source_id ) );

		if ( ! $row ) {
			$label = ! empty( $task['title'] ) ? $task['title'] : '#' . $source_id;
			$error = sprintf(
				/* translators: %1$s: item type, %2$s: item label */
				__( 'Retry failed: %1$s %2$s no longer exists in the PhpMyDirectory database.', 'geodir-converter' ),
				$item_type,
				$label
			);

			$this->log( $error, 'error' );
			$this->increase_failed_imports( 1 );
			$this->record_failed_item( $source_id, $action, $item_type, $label, $error );
			$this->flush_progress();

			return false;
		}

		return $this->import_queued_items(
			array( $row ),
			$handler,
			array(
				'item_type'      => $item_type,
				'action'         => $action,
				'id_key'         => 'id',
				'label_callback' => function ( $item ) {
					return $this->get_row_label( $item );
				},
			)
		);
	}

	/**
	 * Build the display label for a PMD row.
	 *
	 * @since 2.3.0
	 *
	 * @param object $row The PMD row.
	 * @return string The label.
	 */
	private function get_row_label( $row ) {
		$title = isset( $row->title ) ? (string) $row->title : '';

		return trim( $title . ' (#' . (int) $row->id . ')' );
	}

	/**
	 * Whether the task is a "Retry Failed" task for a single item.
	 *
	 * @since 2.3.0
	 *
	 * @param array $task The task.
	 * @return bool True for a retry task.
	 */
	private function is_retry_task( array $task ) {
		return ! empty( $task['retry'] ) && ! empty( $task['source_id'] );
	}

	/**
	 * Import users from PMD to GeoDirectory.
	 *
	 * @since 2.0.2
	 *
	 * @param array $task Import task details.
	 * @return array|false Result of the import operation or false if import is complete.
	 * @throws Exception If database connection fails.
	 */
	public function task_import_users( array $task ) {
		$db = $this->require_db();

		// The first task sets the overall total so progress is meaningful from the start.
		if ( empty( $task['import_total_set'] ) ) {
			$this->set_import_total();
			$task['import_total_set'] = true;
		}

		wp_suspend_cache_addition( true );

		$offset      = isset( $task['offset'] ) ? absint( $task['offset'] ) : 0;
		$batch_size  = absint( $this->get_batch_size() );
		$counts      = $this->get_task_counts( $task );
		$users_table = $this->db_prefix . 'users';

		if ( ! isset( $task['total_users'] ) ) {
			$task['total_users'] = $this->count_table_rows( 'users' );
		}

		$total_users = absint( $task['total_users'] );

		// Log the import start message only for the first batch.
		if ( 0 === $offset ) {
			/* translators: %d: number of users */
			$this->log( sprintf( __( 'Starting user import: %d users found.', 'geodir-converter' ), $total_users ) );
		}

		// Exit early if there are no users to import.
		if ( 0 === $total_users ) {
			$this->log( __( 'No users available for import. Skipping...', 'geodir-converter' ) );
			return $this->next_task( $task );
		}

		$users = $db->get_results(
			$db->prepare( "SELECT * FROM `{$users_table}` ORDER BY `id` ASC LIMIT %d, %d", $offset, $batch_size )
		);

		if ( empty( $users ) ) {
			$this->log( __( 'No more users to import. Process completed.', 'geodir-converter' ) );
			return $this->next_task( $task );
		}

		$user_groups  = $this->get_user_groups( wp_list_pluck( $users, 'id' ) );
		$user_mapping = (array) $this->options_handler->get_option_no_cache( 'user_mapping', array() );

		foreach ( $users as $user ) {
			$this->count_status( $counts, $this->import_single_user( $user, $user_groups, $user_mapping ) );
		}

		if ( ! $this->is_test_mode() ) {
			$this->options_handler->update_option( 'user_mapping', $user_mapping );
			$this->user_mapping_cache = null;
		}

		return $this->finish_batch( $task, $counts, $offset, $batch_size, $total_users, __( 'Users', 'geodir-converter' ) );
	}

	/**
	 * Import a single PMD user.
	 *
	 * New accounts get a random WordPress password; the PMD hash is kept in user
	 * meta so the user can log in with their old password (see handle_user_login()).
	 * A WordPress account that already uses the email address is linked but never
	 * modified, so an existing administrator cannot be renamed or demoted.
	 *
	 * @since 2.3.0
	 *
	 * @param object $user         The PMD user row.
	 * @param array  $user_groups  PMD group ID keyed by PMD user ID.
	 * @param array  $user_mapping PMD user ID to WP user ID mapping, updated in place.
	 * @return int Import status constant.
	 */
	private function import_single_user( $user, array $user_groups, array &$user_mapping ) {
		$pmd_id = (int) $user->id;
		$email  = sanitize_email( (string) $user->user_email );

		if ( empty( $email ) || ! is_email( $email ) ) {
			/* translators: %d: PMD user ID */
			$this->log( sprintf( __( 'Failed to import user #%d: missing or invalid email address.', 'geodir-converter' ), $pmd_id ), 'error' );
			return self::IMPORT_STATUS_FAILED;
		}

		$first_name   = trim( (string) $user->user_first_name );
		$last_name    = trim( (string) $user->user_last_name );
		$display_name = trim( $first_name . ' ' . $last_name );

		if ( '' === $display_name ) {
			$display_name = trim( (string) $user->display_name );
		}

		$existing_user = get_user_by( 'email', $email );
		$is_ours       = $existing_user && (int) get_user_meta( $existing_user->ID, 'pmd_user_id', true ) === $pmd_id;

		if ( $this->is_test_mode() ) {
			if ( ! $existing_user ) {
				return self::IMPORT_STATUS_SUCCESS;
			}

			return $is_ours ? self::IMPORT_STATUS_UPDATED : self::IMPORT_STATUS_SKIPPED;
		}

		// An account that was not created by this importer is only linked.
		if ( $existing_user && ! $is_ours ) {
			$user_mapping[ $pmd_id ] = (int) $existing_user->ID;

			/* translators: %s: email address */
			$this->log( sprintf( __( 'User %s already exists in WordPress; linked to the existing account without changing it.', 'geodir-converter' ), $email ), 'warning' );

			return self::IMPORT_STATUS_SKIPPED;
		}

		$role = $this->map_user_role( isset( $user_groups[ $pmd_id ] ) ? (int) $user_groups[ $pmd_id ] : 0 );

		if ( $existing_user ) {
			$user_data = array(
				'ID'         => $existing_user->ID,
				'first_name' => $first_name,
				'last_name'  => $last_name,
				'role'       => $role,
			);

			if ( '' !== $display_name ) {
				$user_data['display_name'] = $display_name;
			}

			$wp_user_id = wp_update_user( $user_data );
		} else {
			$login = sanitize_user( (string) $user->login, true );

			if ( '' === $login || username_exists( $login ) ) {
				$login = sanitize_user( $email, true );
			}

			$created   = $this->normalize_date( $user->created );
			$user_data = array(
				'user_login'      => $login,
				'user_email'      => $email,
				'user_pass'       => wp_generate_password( 24, true, true ),
				'first_name'      => $first_name,
				'last_name'       => $last_name,
				'role'            => $role,
				'user_registered' => $created ? get_gmt_from_date( $created ) : current_time( 'mysql', true ),
			);

			if ( '' !== $display_name ) {
				$user_data['display_name'] = $display_name;
			}

			$wp_user_id = wp_insert_user( $user_data );
		}

		if ( is_wp_error( $wp_user_id ) ) {
			/* translators: %1$s: email address, %2$s: error message */
			$this->log( sprintf( __( 'Failed to import user %1$s: %2$s', 'geodir-converter' ), $email, $wp_user_id->get_error_message() ), 'error' );
			return self::IMPORT_STATUS_FAILED;
		}

		$this->update_user_meta( (int) $wp_user_id, $user, ! $existing_user );

		$user_mapping[ $pmd_id ] = (int) $wp_user_id;

		return $existing_user ? self::IMPORT_STATUS_UPDATED : self::IMPORT_STATUS_SUCCESS;
	}

	/**
	 * Get the PMD group of each user.
	 *
	 * A user may belong to several groups; the lowest group ID is used, which is
	 * the most privileged of PMD's default groups (1 = Administrator).
	 *
	 * @since 2.3.0
	 *
	 * @param array $user_ids PMD user IDs.
	 * @return array PMD group ID keyed by PMD user ID.
	 */
	private function get_user_groups( array $user_ids ) {
		$user_ids = array_values( array_filter( array_map( 'absint', $user_ids ) ) );

		if ( empty( $user_ids ) ) {
			return array();
		}

		$db           = $this->get_db_connection();
		$placeholders = implode( ',', array_fill( 0, count( $user_ids ), '%d' ) );
		$rows         = $db->get_results(
			$db->prepare(
				"SELECT `user_id`, MIN(`group_id`) AS `group_id` FROM `{$this->db_prefix}users_groups_lookup` WHERE `user_id` IN ({$placeholders}) GROUP BY `user_id`",
				$user_ids
			)
		);

		$groups = array();
		foreach ( (array) $rows as $row ) {
			$groups[ (int) $row->user_id ] = (int) $row->group_id;
		}

		return $groups;
	}

	/**
	 * Map PMD user group to WordPress role.
	 *
	 * @since 2.0.2
	 *
	 * @param int $group_id The PMD group ID.
	 * @return string The corresponding WordPress role.
	 */
	private function map_user_role( $group_id ) {
		switch ( $group_id ) {
			case 1:
				return 'administrator';
			case 2:
				return 'editor';
			case 3:
				return 'author';
			default:
				return 'subscriber';
		}
	}

	/**
	 * Update user meta data.
	 *
	 * @since 2.0.2
	 *
	 * @param int    $user_id        The WordPress user ID.
	 * @param object $user           The original user data from PMD.
	 * @param bool   $store_password Whether to store the PMD password hash for login.
	 * @return void
	 */
	private function update_user_meta( $user_id, $user, $store_password = false ) {
		$meta_fields = array(
			'user_organization' => 'user_organization',
			'user_address1'     => 'user_address1',
			'user_address2'     => 'user_address2',
			'user_city'         => 'user_city',
			'user_state'        => 'user_state',
			'user_country'      => 'user_country',
			'user_zip'          => 'user_zip',
			'user_phone'        => 'user_phone',
			'user_fax'          => 'user_fax',
		);

		foreach ( $meta_fields as $wp_key => $pmd_key ) {
			if ( isset( $user->$pmd_key ) ) {
				update_user_meta( $user_id, $wp_key, sanitize_text_field( $user->$pmd_key ) );
			}
		}

		update_user_meta( $user_id, 'pmd_user_id', (int) $user->id );

		// Only accounts created by this import get the PMD password; see handle_user_login().
		if ( $store_password && ! empty( $user->pass ) ) {
			update_user_meta( $user_id, 'pmd_password', (string) $user->pass );
			update_user_meta( $user_id, 'pmd_password_hash', isset( $user->password_hash ) ? (string) $user->password_hash : '' );
			update_user_meta( $user_id, 'pmd_password_salt', isset( $user->password_salt ) ? (string) $user->password_salt : '' );
		}
	}

	/**
	 * Import products from PMD to GeoDirectory.
	 *
	 * Each PMD pricing option (products_pricing row) becomes one GD price package,
	 * since a GD package carries a single price and period.
	 *
	 * @since 2.0.2
	 *
	 * @param array $task Import task details.
	 * @return array|false Result of the import operation.
	 * @throws Exception If database connection fails.
	 */
	public function task_import_products( array $task ) {
		// Abort early if the payment manager plugin is not installed.
		if ( ! class_exists( 'GeoDir_Pricing_Package' ) ) {
			$this->log( __( 'Payment manager plugin is not active. Skipping products...', 'geodir-converter' ) );
			return $this->next_task( $task );
		}

		$db = $this->require_db();

		if ( ! $this->products_enabled() ) {
			$this->log( __( 'Skipping products as you are using an incompatible version of PMD', 'geodir-converter' ) );
			return $this->next_task( $task );
		}

		wp_suspend_cache_addition( true );

		$offset         = isset( $task['offset'] ) ? absint( $task['offset'] ) : 0;
		$batch_size     = absint( $this->get_batch_size() );
		$counts         = $this->get_task_counts( $task );
		$products_table = $this->db_prefix . 'products';
		$pricing_table  = $this->db_prefix . 'products_pricing';
		$post_type      = $this->get_import_post_type();

		if ( ! isset( $task['total_products'] ) ) {
			$task['total_products'] = $this->count_products_rows();
		}

		$total_products = absint( $task['total_products'] );

		// Log the import start message only for the first batch.
		if ( 0 === $offset ) {
			/* translators: %d: number of products */
			$this->log( sprintf( __( 'Starting products import: %d product pricing options found.', 'geodir-converter' ), $total_products ) );
		}

		// Exit early if there are no products to import.
		if ( 0 === $total_products ) {
			$this->log( __( 'No products available for import. Skipping...', 'geodir-converter' ) );
			return $this->next_task( $task );
		}

		$has_memberships = $this->table_exists( 'memberships' );
		$membership_cols = $has_memberships ? ', m.`images_limit`, m.`category_limit`' : '';
		$membership_join = $has_memberships ? "LEFT JOIN `{$this->db_prefix}memberships` m ON p.`type_id` = m.`id`" : '';

		// Fetch products for current batch, ordered by product and pricing ID.
		$products = $db->get_results(
			$db->prepare(
				"SELECT
					p.`id` AS product_id,
					p.`name`,
					p.`active`,
					p.`description`,
					pp.`id` AS pricing_id,
					pp.`active` AS pricing_active,
					pp.`label`,
					pp.`period`,
					pp.`period_count`,
					pp.`price`,
					pp.`renewable`,
					pp.`overdue_pricing_id`,
					pp.`ordering`
					{$membership_cols}
				FROM `{$products_table}` p
				LEFT JOIN `{$pricing_table}` pp ON p.`id` = pp.`product_id`
				{$membership_join}
				ORDER BY p.`id` ASC, pp.`id` ASC
				LIMIT %d, %d",
				$offset,
				$batch_size
			)
		);

		if ( empty( $products ) ) {
			$this->log( __( 'No more products to import. Process completed.', 'geodir-converter' ) );
			return $this->next_task( $task );
		}

		// Get packages mapping (PMD pricing ID => GD package ID).
		$packages_mapping = (array) $this->options_handler->get_option_no_cache( 'packages_mapping', array() );

		foreach ( $products as $product ) {
			$product_id = absint( $product->product_id );
			$pricing_id = absint( $product->pricing_id );
			$plan_label = (string) $product->name;

			// A product without a pricing option has no price or period to import.
			if ( ! $pricing_id ) {
				/* translators: %s: product name */
				$this->log( sprintf( __( 'Skipped product %s: it has no pricing options.', 'geodir-converter' ), $plan_label ), 'warning' );
				++$counts['skipped'];
				continue;
			}

			if ( '' !== trim( (string) $product->label ) ) {
				$plan_label .= ' - ' . trim( (string) $product->label );
			}

			$existing_package_id = $this->get_existing_package( $post_type, $pricing_id, $product_id );
			$period_count        = absint( $product->period_count );

			$package_data = array(
				'post_type'       => $post_type,
				'name'            => $plan_label,
				'title'           => $plan_label,
				'description'     => (string) $product->description,
				'fa_icon'         => '',
				'amount'          => (float) $product->price,
				'time_interval'   => $period_count,
				'time_unit'       => $period_count && $product->period ? $this->get_time_unit( $product->period ) : '',
				'recurring'       => ! empty( $product->renewable ) ? 1 : 0,
				'recurring_limit' => 0,
				'trial'           => '',
				'trial_amount'    => '',
				'trial_interval'  => '',
				'trial_unit'      => '',
				'is_default'      => 0,
				'display_order'   => (int) $product->ordering,
				'downgrade_pkg'   => ! empty( $product->overdue_pricing_id ) && isset( $packages_mapping[ (int) $product->overdue_pricing_id ] ) ? (int) $packages_mapping[ (int) $product->overdue_pricing_id ] : 0,
				'post_status'     => 'default',
				'status'          => ! empty( $product->active ) && ! empty( $product->pricing_active ) ? 1 : 0,
			);

			if ( $has_memberships ) {
				$package_data['image_limit']    = absint( $product->images_limit );
				$package_data['category_limit'] = absint( $product->category_limit );
			}

			// If existing package found, update ID before saving.
			if ( $existing_package_id ) {
				$package_data['id'] = $existing_package_id;
			}

			// Handle test mode.
			if ( $this->is_test_mode() ) {
				$existing_package_id ? ++$counts['updated'] : ++$counts['imported'];
				continue;
			}

			// Prepare and insert/update package.
			$package_data = GeoDir_Pricing_Package::prepare_data_for_save( $package_data );
			$package_id   = GeoDir_Pricing_Package::insert_package( $package_data, true );

			if ( ! $package_id || is_wp_error( $package_id ) ) {
				/* translators: %s: plan name */
				$this->log( sprintf( __( 'Failed to import plan: %s', 'geodir-converter' ), $plan_label ), 'error' );
				++$counts['failed'];
				continue;
			}

			$log_message = $existing_package_id
				/* translators: %s: plan name */
				? sprintf( __( 'Updated plan: %s', 'geodir-converter' ), $plan_label )
				/* translators: %s: plan name */
				: sprintf( __( 'Imported new plan: %s', 'geodir-converter' ), $plan_label );

			$this->log( $log_message );

			$existing_package_id ? ++$counts['updated'] : ++$counts['imported'];

			// Store package mapping.
			$packages_mapping[ $pricing_id ] = (int) $package_id;

			GeoDir_Pricing_Package::update_meta( $package_id, '_pmd_pricing_id', $pricing_id );
			GeoDir_Pricing_Package::update_meta( $package_id, '_pmd_package_id', $product_id );
		}

		// Save packages mapping.
		if ( ! $this->is_test_mode() ) {
			$this->options_handler->update_option( 'packages_mapping', $packages_mapping );
			$this->packages_mapping_cache = null;
		}

		return $this->finish_batch( $task, $counts, $offset, $batch_size, $total_products, __( 'Products', 'geodir-converter' ) );
	}

	/**
	 * Get the time unit based on the period.
	 *
	 * @since 2.0.2
	 *
	 * @param string $period The period to convert.
	 * @return string The time unit.
	 */
	public function get_time_unit( $period ) {
		$periods = array(
			'days'   => 'D',
			'weeks'  => 'W',
			'months' => 'M',
			'years'  => 'Y',
		);

		return isset( $periods[ $period ] ) ? $periods[ $period ] : 'M';
	}

	/**
	 * Get the GD package previously imported for a PMD pricing option.
	 *
	 * Packages imported before 2.3.0 were keyed by the PMD product ID only; such a
	 * package is reused for the product's first pricing option.
	 *
	 * @since 2.0.2
	 * @since 2.3.0 Looks packages up by PMD pricing ID.
	 *
	 * @param string $post_type  The post type associated with the package.
	 * @param int    $pricing_id The PMD pricing ID.
	 * @param int    $product_id The PMD product ID.
	 * @return int The existing package ID, or 0.
	 */
	private function get_existing_package( $post_type, $pricing_id, $product_id ) {
		global $wpdb;

		$package_id = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT p.id
				FROM ' . GEODIR_PRICING_PACKAGES_TABLE . ' AS p
				INNER JOIN ' . GEODIR_PRICING_PACKAGE_META_TABLE . ' AS g ON p.id = g.package_id
				WHERE p.post_type = %s AND g.meta_key = %s AND g.meta_value = %s
				LIMIT 1',
				$post_type,
				'_pmd_pricing_id',
				(string) $pricing_id
			)
		);

		if ( ! $package_id ) {
			$package_id = $wpdb->get_var(
				$wpdb->prepare(
					'SELECT p.id
					FROM ' . GEODIR_PRICING_PACKAGES_TABLE . ' AS p
					INNER JOIN ' . GEODIR_PRICING_PACKAGE_META_TABLE . ' AS g ON p.id = g.package_id
					WHERE p.post_type = %s AND g.meta_key = %s AND g.meta_value = %s
					AND NOT EXISTS ( SELECT 1 FROM ' . GEODIR_PRICING_PACKAGE_META_TABLE . ' AS g2 WHERE g2.package_id = p.id AND g2.meta_key = %s )
					LIMIT 1',
					$post_type,
					'_pmd_package_id',
					(string) $product_id,
					'_pmd_pricing_id'
				)
			);
		}

		return $package_id ? (int) $package_id : 0;
	}

	/**
	 * Import custom fields from PMD to GeoDirectory.
	 *
	 * PMD stores listing and event custom field values in `custom_{field ID}`
	 * columns of the listings/events tables. The GD key chosen for each field is
	 * saved so the listing and event imports can copy the values across.
	 *
	 * @since 2.0.2
	 *
	 * @param array $task Import task details.
	 * @return array|false Result of the import operation or false if import is complete.
	 * @throws Exception If database connection fails.
	 */
	public function task_import_fields( array $task ) {
		$db = $this->require_db();

		$offset     = isset( $task['offset'] ) ? absint( $task['offset'] ) : 0;
		$batch_size = absint( $this->get_batch_size() );
		$counts     = $this->get_task_counts( $task );
		$post_type  = $this->get_import_post_type();

		// Import standard fields if not already done.
		if ( ! isset( $task['standard_fields_imported'] ) ) {
			$this->import_standard_fields( $post_type );

			if ( $this->events_enabled() ) {
				$this->import_standard_fields( self::POST_TYPE_EVENTS );
			}

			$task['standard_fields_imported'] = true;
		}

		wp_suspend_cache_addition( true );

		if ( ! isset( $task['total_fields'] ) ) {
			$task['total_fields'] = $this->count_custom_fields();
		}

		$total_fields = absint( $task['total_fields'] );

		// Log the import start message only for the first batch.
		if ( 0 === $offset ) {
			/* translators: %d: number of fields */
			$this->log( sprintf( __( 'Starting custom fields import: %d fields found.', 'geodir-converter' ), $total_fields ) );
		}

		// Exit early if there are no fields to import.
		if ( 0 === $total_fields ) {
			$this->log( __( 'No custom fields available for import. Skipping...', 'geodir-converter' ) );
			return $this->next_task( $task );
		}

		$types        = $this->get_custom_field_group_types();
		$placeholders = implode( ',', array_fill( 0, count( $types ), '%s' ) );

		// Get fields for current batch.
		$fields = $db->get_results(
			$db->prepare(
				"SELECT f.*, fg.`type` AS group_type
				FROM `{$this->db_prefix}fields` f
				INNER JOIN `{$this->db_prefix}fields_groups` fg ON f.`group_id` = fg.`id`
				WHERE fg.`type` IN ({$placeholders})
				ORDER BY fg.`type` ASC, fg.`ordering` ASC, f.`ordering` ASC, f.`id` ASC
				LIMIT %d, %d",
				array_merge( $types, array( $offset, $batch_size ) )
			)
		);

		if ( empty( $fields ) ) {
			$this->log( __( 'No more custom fields to import. Process completed.', 'geodir-converter' ) );
			return $this->next_task( $task );
		}

		$fields_mapping = (array) $this->options_handler->get_option_no_cache( 'fields_mapping', array() );

		foreach ( $fields as $field ) {
			$field_post_type = 'events' === $field->group_type ? self::POST_TYPE_EVENTS : $post_type;
			$definition      = $this->get_custom_field_definition( $field );

			if ( null === $definition ) {
				++$counts['skipped'];
				/* translators: %1$s: field name, %2$s: PMD field type */
				$this->log( sprintf( __( 'Skipped custom field %1$s: PMD field type "%2$s" has no GeoDirectory equivalent.', 'geodir-converter' ), $field->name, $field->type ), 'warning' );
				continue;
			}

			$gd_field_key = $this->build_field_key( $field, $field_post_type );

			// Skip fields that shouldn't be imported.
			if ( $this->should_skip_field( $gd_field_key ) ) {
				++$counts['skipped'];
				/* translators: %s: field name */
				$this->log( sprintf( __( 'Skipped custom field: %s', 'geodir-converter' ), $field->name ), 'warning' );
				continue;
			}

			$existing = geodir_get_field_infoby( 'htmlvar_name', $gd_field_key, $field_post_type );

			if ( $existing ) {
				$existing['field_id'] = (int) $existing['id'];
				unset( $existing['id'] );
			} else {
				$existing = array();
			}

			$options       = $this->get_field_options( $field->options );
			$default_value = in_array( $definition['field_type'], array( 'text', 'select', 'radio' ), true ) ? (string) $field->selected : '';

			$gd_field = array_merge(
				$existing,
				array(
					'post_type'          => $field_post_type,
					'data_type'          => $definition['data_type'],
					'field_type'         => $definition['field_type'],
					'field_type_key'     => $definition['field_type'],
					'admin_title'        => $field->name,
					'htmlvar_name'       => $gd_field_key,
					'frontend_title'     => $field->name,
					'frontend_desc'      => (string) $field->description,
					'clabels'            => $field->name,
					'default_value'      => $default_value,
					'is_active'          => 1,
					'is_required'        => ! empty( $field->required ) ? 1 : 0,
					'validation_pattern' => $this->convert_regex_pattern( $field->regex ),
					'validation_msg'     => (string) $field->regex_error,
					'option_values'      => $options,
					'show_in'            => ! empty( $field->hidden ) ? '' : '[detail]',
					'show_on_pkg'        => $this->get_package_ids( $field_post_type ),
					'for_admin_use'      => ! empty( $field->admin_only ) ? 1 : 0,
					'sort_order'         => (int) $field->ordering,
				)
			);

			if ( isset( $definition['decimal_point'] ) ) {
				$gd_field['decimal_point'] = $definition['decimal_point'];
			}

			if ( ! empty( $definition['extra'] ) ) {
				$gd_field['extra'] = $definition['extra'];
			}

			$is_update = ! empty( $existing );

			if ( $this->is_test_mode() ) {
				$is_update ? ++$counts['updated'] : ++$counts['imported'];
				continue;
			}

			$result = geodir_custom_field_save( $gd_field );

			if ( is_wp_error( $result ) || ! $result ) {
				++$counts['failed'];
				$this->log(
					sprintf(
						/* translators: %1$s: field name, %2$s: error message */
						__( 'Failed to import field %1$s: %2$s', 'geodir-converter' ),
						$field->name,
						is_wp_error( $result ) ? $result->get_error_message() : __( 'Unknown error', 'geodir-converter' )
					),
					'error'
				);
				continue;
			}

			$fields_mapping[ $field_post_type ][ 'custom_' . (int) $field->id ] = array(
				'key'       => $gd_field_key,
				'type'      => $definition['field_type'],
				'data_type' => $definition['data_type'],
				'pmd_type'  => (string) $field->type,
			);

			$is_update ? ++$counts['updated'] : ++$counts['imported'];
		}

		if ( ! $this->is_test_mode() ) {
			$this->options_handler->update_option( 'fields_mapping', $fields_mapping );
			$this->fields_mapping_cache = array();
		}

		return $this->finish_batch( $task, $counts, $offset, $batch_size, $total_fields, __( 'Custom fields', 'geodir-converter' ) );
	}

	/**
	 * Map a PMD custom field to a GeoDirectory field type.
	 *
	 * PMD field types are listed in cp/admin_fields.php; their storage is set in
	 * Fields::getDatabaseFieldType().
	 *
	 * @since 2.3.0
	 *
	 * @param object $field The PMD field row.
	 * @return array|null Field type, data type and extra settings, or null if unsupported.
	 */
	private function get_custom_field_definition( $field ) {
		$type = (string) $field->type;

		switch ( $type ) {
			case 'textarea':
				return array(
					'field_type' => 'textarea',
					'data_type'  => 'TEXT',
				);
			case 'htmleditor':
				return array(
					'field_type' => 'html',
					'data_type'  => 'TEXT',
				);
			case 'select':
				return array(
					'field_type' => 'select',
					'data_type'  => 'VARCHAR',
				);
			case 'radio':
				return array(
					'field_type' => 'radio',
					'data_type'  => 'VARCHAR',
				);
			case 'text_select':
				// Free-text entries, one per line. A GD multiselect only displays values that
				// are among its options, so the entries are kept in a textarea.
				return array(
					'field_type' => 'textarea',
					'data_type'  => 'TEXT',
				);
			case 'select_multiple':
				return array(
					'field_type' => 'multiselect',
					'data_type'  => 'VARCHAR',
				);
			case 'checkbox':
				// A checkbox with options holds several newline-separated values in PMD.
				if ( '' !== $this->get_field_options( $field->options ) ) {
					return array(
						'field_type' => 'multiselect',
						'data_type'  => 'VARCHAR',
						'extra'      => array( 'multi_display_type' => 'checkbox' ),
					);
				}

				return array(
					'field_type' => 'checkbox',
					'data_type'  => 'TINYINT',
				);
			case 'date':
				return array(
					'field_type' => 'datepicker',
					'data_type'  => 'DATE',
				);
			case 'number':
			case 'rating':
				return array(
					'field_type' => 'text',
					'data_type'  => 'INT',
				);
			case 'decimal':
			case 'currency':
				return array(
					'field_type'    => 'text',
					'data_type'     => 'DECIMAL',
					'decimal_point' => 2,
				);
			case 'url_title':
				return array(
					'field_type' => 'url',
					'data_type'  => 'TEXT',
				);
			case 'hours':
				// GD allows a single business_hours field per post type, used for the listing hours.
				return null;
			case 'text':
			case 'hidden':
			case 'color':
			default:
				// A GD text field is a VARCHAR(254) column; longer PMD text fields become textareas.
				if ( absint( $field->character_limit ) > 254 ) {
					return array(
						'field_type' => 'textarea',
						'data_type'  => 'TEXT',
					);
				}

				return array(
					'field_type' => 'text',
					'data_type'  => 'VARCHAR',
				);
		}
	}

	/**
	 * Build the GD key (htmlvar_name) for a PMD custom field.
	 *
	 * Mirrors GD's own key sanitization so the key used for the listing values
	 * matches the saved field. Keys that would collide with GD or importer fields
	 * are prefixed.
	 *
	 * @since 2.3.0
	 *
	 * @param object $field     The PMD field row.
	 * @param string $post_type The GD post type.
	 * @return string The field key.
	 */
	private function build_field_key( $field, $post_type ) {
		$key = str_replace( array( '-', ' ', '"', "'" ), array( '_', '', '', '' ), sanitize_title_with_dashes( (string) $field->name ) );
		$key = substr( $key, 0, 50 );

		// Non-latin names produce encoded keys that are not valid column names.
		if ( '' === $key || ! preg_match( '/^[a-z0-9_]+$/', $key ) ) {
			return 'pmd_field_' . (int) $field->id;
		}

		$reserved = array_merge(
			array_keys( $this->get_standard_fields( $post_type ) ),
			array(
				'post_title',
				'post_content',
				'post_category',
				'post_tags',
				'default_category',
				'package_id',
				'expire_date',
				'logo',
				'event_dates',
				'recurring',
				'overall_rating',
				'rating_count',
			)
		);

		if ( in_array( $key, $reserved, true ) || $this->should_skip_field( $key ) ) {
			$key = substr( 'pmd_' . $key, 0, 50 );
		}

		return $key;
	}

	/**
	 * Convert a PMD validation regex to a GD validation pattern.
	 *
	 * PMD runs the regex through preg_match(), so it includes delimiters. GD
	 * outputs the pattern as an HTML pattern attribute, which has none. Patterns
	 * with modifiers cannot be expressed and are dropped.
	 *
	 * @since 2.3.0
	 *
	 * @param string $regex The PMD regex.
	 * @return string The GD validation pattern.
	 */
	private function convert_regex_pattern( $regex ) {
		$regex = trim( (string) $regex );

		if ( '' === $regex ) {
			return '';
		}

		if ( preg_match( '/^([^a-zA-Z0-9\s\\\\])(.*)\1([a-zA-Z]*)$/s', $regex, $matches ) && '' === $matches[3] ) {
			return $matches[2];
		}

		return '';
	}

	/**
	 * Get the GD field definitions the importer adds for PMD's built-in columns.
	 *
	 * @since 2.3.0
	 *
	 * @param string $post_type The post type.
	 * @return array Field definitions keyed by field key.
	 */
	private function get_standard_fields( $post_type ) {
		$fields = array(
			'pmd_id'   => array(
				'field_type'    => 'text',
				'data_type'     => 'INT',
				'label'         => __( 'PMD ID', 'geodir-converter' ),
				'description'   => __( 'Original PMD Listing ID.', 'geodir-converter' ),
				'icon'          => 'far fa-id-card',
				'show_in'       => '',
				'for_admin_use' => 1,
			),
			'phone'    => array(
				'field_type'  => 'phone',
				'data_type'   => 'VARCHAR',
				'label'       => __( 'Phone', 'geodir-converter' ),
				'description' => __( 'The phone number of the listing.', 'geodir-converter' ),
				'icon'        => 'fas fa-phone',
			),
			'website'  => array(
				'field_type'  => 'url',
				'data_type'   => 'TEXT',
				'label'       => __( 'Website', 'geodir-converter' ),
				'description' => __( 'The website of the listing.', 'geodir-converter' ),
				'icon'        => 'fas fa-globe',
			),
			'email'    => array(
				'field_type'  => 'email',
				'data_type'   => 'VARCHAR',
				'label'       => __( 'Email', 'geodir-converter' ),
				'description' => __( 'The email of the listing.', 'geodir-converter' ),
				'icon'        => 'far fa-envelope',
			),
			'featured' => array(
				'field_type'  => 'checkbox',
				'data_type'   => 'TINYINT',
				'label'       => __( 'Featured', 'geodir-converter' ),
				'frontend'    => __( 'Is Featured?', 'geodir-converter' ),
				'description' => __( 'Mark listing as a featured.', 'geodir-converter' ),
				'icon'        => 'far fa-star',
			),
		);

		if ( self::POST_TYPE_EVENTS === $post_type ) {
			return array_merge(
				$fields,
				array(
					'venue'          => array(
						'field_type'  => 'text',
						'data_type'   => 'VARCHAR',
						'label'       => __( 'Venue', 'geodir-converter' ),
						'description' => __( 'The venue that will host this event.', 'geodir-converter' ),
						'icon'        => 'fas fa-map-marker-alt',
					),
					'location'       => array(
						'field_type'  => 'textarea',
						'data_type'   => 'TEXT',
						'label'       => __( 'Location', 'geodir-converter' ),
						'description' => __( 'The actual location of this event.', 'geodir-converter' ),
						'icon'        => 'fas fa-map-marker-alt',
					),
					'contact_name'   => array(
						'field_type'  => 'text',
						'data_type'   => 'VARCHAR',
						'label'       => __( 'Contact Name', 'geodir-converter' ),
						'description' => __( 'The contact person.', 'geodir-converter' ),
						'icon'        => 'far fa-user',
					),
					// GD Events predefined "Event Registration Info" field.
					'event_reg_desc' => array(
						'field_type'  => 'html',
						'data_type'   => 'TEXT',
						'label'       => __( 'Event Registration Info', 'geodir-converter' ),
						'description' => __( 'Enter details to register to this event.', 'geodir-converter' ),
						'icon'        => 'fas fa-ticket-alt',
						'show_in'     => '[moreinfo]',
					),
				)
			);
		}

		$social = array(
			'twitter'    => __( 'Twitter', 'geodir-converter' ),
			'facebook'   => __( 'Facebook', 'geodir-converter' ),
			'google'     => __( 'Google+', 'geodir-converter' ),
			'linkedin'   => __( 'LinkedIn', 'geodir-converter' ),
			'pinterest'  => __( 'Pinterest', 'geodir-converter' ),
			'youtube'    => __( 'YouTube', 'geodir-converter' ),
			'foursquare' => __( 'Foursquare', 'geodir-converter' ),
			'instagram'  => __( 'Instagram', 'geodir-converter' ),
		);

		$fields['fax'] = array(
			'field_type'  => 'phone',
			'data_type'   => 'VARCHAR',
			'label'       => __( 'Fax', 'geodir-converter' ),
			'description' => __( 'The fax number of the listing.', 'geodir-converter' ),
			'icon'        => 'fas fa-fax',
		);

		$fields['company_logo'] = array(
			'field_type'  => 'file',
			'data_type'   => 'TEXT',
			'label'       => __( 'Company Logo', 'geodir-converter' ),
			'description' => __( 'You can upload your company logo.', 'geodir-converter' ),
			'icon'        => 'far fa-image',
			'extra'       => array(
				'gd_file_types' => geodir_image_extensions(),
				'file_limit'    => 1,
			),
		);

		foreach ( $social as $key => $label ) {
			$fields[ $key ] = array(
				'field_type'  => 'url',
				'data_type'   => 'TEXT',
				'label'       => $label,
				/* translators: %s: social network name */
				'description' => sprintf( __( 'You can enter your business or listing %s url.', 'geodir-converter' ), $label ),
				'icon'        => 'fab fa-' . ( 'google' === $key ? 'google' : $key ),
			);
		}

		$fields['claimed'] = array(
			'field_type'  => 'checkbox',
			'data_type'   => 'TINYINT',
			'label'       => __( 'Is Claimed', 'geodir-converter' ),
			'frontend'    => __( 'Business Owner/Associate?', 'geodir-converter' ),
			'description' => __( 'Mark listing as a claimed.', 'geodir-converter' ),
			'icon'        => 'far fa-check',
		);

		// GD predefined business hours field (single use per post type).
		$fields['business_hours'] = array(
			'field_type'  => 'business_hours',
			'data_type'   => 'TEXT',
			'label'       => __( 'Business Hours', 'geodir-converter' ),
			'description' => __( 'Select your business opening/operating hours.', 'geodir-converter' ),
			'icon'        => 'fas fa-clock',
		);

		return $fields;
	}

	/**
	 * Create the GD fields for PMD's built-in columns.
	 *
	 * A field that already exists is left untouched, so GD's own default fields
	 * (phone, email, website...) keep their type and settings.
	 *
	 * @since 2.0.2
	 *
	 * @param string $post_type The post type to import standard fields for.
	 * @return void
	 */
	private function import_standard_fields( $post_type ) {
		$package_ids = $this->get_package_ids( $post_type );
		$imported    = 0;
		$skipped     = 0;
		$failed      = 0;
		$priority    = 0;

		foreach ( $this->get_standard_fields( $post_type ) as $field_key => $field ) {
			++$priority;

			if ( $this->should_skip_field( $field_key ) || geodir_get_field_infoby( 'htmlvar_name', $field_key, $post_type ) ) {
				++$skipped;
				continue;
			}

			$gd_field = array(
				'post_type'         => $post_type,
				'data_type'         => $field['data_type'],
				'field_type'        => $field['field_type'],
				'field_type_key'    => $field['field_type'],
				'htmlvar_name'      => $field_key,
				'admin_title'       => $field['label'],
				'frontend_desc'     => $field['description'],
				'placeholder_value' => '',
				'frontend_title'    => isset( $field['frontend'] ) ? $field['frontend'] : $field['label'],
				'clabels'           => $field['label'],
				'is_required'       => 0,
				'show_in'           => isset( $field['show_in'] ) ? $field['show_in'] : '[detail]',
				'show_on_pkg'       => $package_ids,
				'field_icon'        => isset( $field['icon'] ) ? $field['icon'] : '',
				'is_active'         => 1,
				'is_default'        => 0,
				'for_admin_use'     => ! empty( $field['for_admin_use'] ) ? 1 : 0,
				'sort_order'        => $priority,
			);

			if ( ! empty( $field['extra'] ) ) {
				$gd_field['extra'] = $field['extra'];
			}

			if ( $this->is_test_mode() ) {
				++$imported;
				continue;
			}

			$result = geodir_custom_field_save( $gd_field );

			if ( $result && ! is_wp_error( $result ) ) {
				++$imported;
			} else {
				++$failed;
				/* translators: %1$s: field name, %2$s: error message */
				$this->log( sprintf( __( 'Failed to import standard field %1$s: %2$s', 'geodir-converter' ), $field['label'], is_wp_error( $result ) ? $result->get_error_message() : __( 'Unknown error', 'geodir-converter' ) ), 'error' );
			}
		}

		$this->increase_succeed_imports( $imported );
		$this->increase_skipped_imports( $skipped );
		$this->increase_failed_imports( $failed );

		$this->log(
			sprintf(
				/* translators: %1$s: post type, %2$d: imported count, %3$d: already existing count, %4$d: failed count */
				__( 'Standard fields for %1$s: %2$d created, %3$d already existed, %4$d failed.', 'geodir-converter' ),
				$post_type,
				$imported,
				$skipped,
				$failed
			),
			'success'
		);
	}

	/**
	 * Convert PMD field options to GD option values.
	 *
	 * PMD stores one option per line (Fields::addToForm()); GD reads one option
	 * per line as well.
	 *
	 * @since 2.0.2
	 *
	 * @param string $field_options The field options.
	 * @return string The formatted options string.
	 */
	private function get_field_options( $field_options ) {
		if ( empty( $field_options ) ) {
			return '';
		}

		$options = array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', (string) $field_options ) ), 'strlen' );

		return implode( "\n", $options );
	}

	/**
	 * Get the saved PMD column to GD field mapping for a post type.
	 *
	 * @since 2.3.0
	 *
	 * @param string $post_type The post type.
	 * @return array Mapping keyed by PMD column (custom_{id}).
	 */
	private function get_fields_mapping( $post_type ) {
		if ( ! isset( $this->fields_mapping_cache[ $post_type ] ) ) {
			$mapping = (array) $this->options_handler->get_option_no_cache( 'fields_mapping', array() );

			$this->fields_mapping_cache[ $post_type ] = isset( $mapping[ $post_type ] ) ? (array) $mapping[ $post_type ] : array();
		}

		return $this->fields_mapping_cache[ $post_type ];
	}

	/**
	 * Add PMD custom field values to the GD post data.
	 *
	 * @since 2.3.0
	 *
	 * @param object $row       The PMD listing/event row.
	 * @param string $post_type The GD post type.
	 * @param array  $data      GD post data, updated in place.
	 * @return void
	 */
	private function add_custom_field_values( $row, $post_type, array &$data ) {
		foreach ( $this->get_fields_mapping( $post_type ) as $column => $info ) {
			if ( ! property_exists( $row, $column ) || empty( $info['key'] ) ) {
				continue;
			}

			$data[ $info['key'] ] = $this->format_custom_field_value( $row->{$column}, $info );
		}
	}

	/**
	 * Convert a PMD custom field value to the format GD saves.
	 *
	 * @since 2.3.0
	 *
	 * @param mixed $value The PMD value.
	 * @param array $info  Field mapping info.
	 * @return mixed The GD value.
	 */
	private function format_custom_field_value( $value, array $info ) {
		$value     = null === $value ? '' : (string) $value;
		$data_type = isset( $info['data_type'] ) ? $info['data_type'] : '';
		$pmd_type  = isset( $info['pmd_type'] ) ? $info['pmd_type'] : '';

		switch ( $info['type'] ) {
			case 'multiselect':
				// PMD joins multiple values with newlines; GD implodes an array with commas.
				return array_values( array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', $value ) ), 'strlen' ) );
			case 'checkbox':
				return ( '' !== trim( $value ) && '0' !== trim( $value ) ) ? 1 : 0;
			case 'datepicker':
				return $this->normalize_date( $value, 'Y-m-d' );
			case 'url':
				// PMD stores "title|url"; GD reads "url|label".
				if ( 'url_title' === $pmd_type && false !== strpos( $value, '|' ) ) {
					$parts = explode( '|', $value, 2 );
					$url   = trim( $parts[1] );

					return '' !== $url ? $url . '|' . trim( $parts[0] ) : '';
				}

				return $value;
			case 'text':
				if ( in_array( $data_type, array( 'INT', 'DECIMAL', 'FLOAT' ), true ) && ! is_numeric( $value ) ) {
					return '';
				}

				return $value;
			default:
				return $value;
		}
	}

	/**
	 * Import categories from PMD to GeoDirectory.
	 *
	 * @since 2.0.2
	 *
	 * @param array $task Import task details.
	 * @return array|false Result of the import operation or false if import is complete.
	 * @throws Exception If database connection fails.
	 */
	public function task_import_categories( array $task ) {
		$db = $this->require_db();

		wp_suspend_cache_addition( true );

		$offset           = isset( $task['offset'] ) ? absint( $task['offset'] ) : 0;
		$batch_size       = absint( $this->get_batch_size() );
		$counts           = $this->get_task_counts( $task );
		$categories_table = $this->db_prefix . 'categories';
		$post_type        = $this->get_import_post_type();
		$taxonomy         = $post_type . 'category';

		if ( ! isset( $task['total_categories'] ) ) {
			$task['total_categories'] = $this->count_table_rows( 'categories' );
		}

		$total_categories = absint( $task['total_categories'] );

		// Log the import start message only for the first batch.
		if ( 0 === $offset ) {
			/* translators: %d: number of categories */
			$this->log( sprintf( __( 'Starting category import: %d categories found.', 'geodir-converter' ), $total_categories ) );
		}

		// Exit early if there are no categories to import.
		if ( 0 === $total_categories ) {
			$this->log( __( 'No categories available for import. Skipping...', 'geodir-converter' ) );
			return $this->next_task( $task );
		}

		// Parents come before children: order by tree level.
		$categories = $db->get_results(
			$db->prepare(
				"SELECT c.* FROM `{$categories_table}` c ORDER BY c.`level` ASC, c.`id` ASC LIMIT %d, %d",
				$offset,
				$batch_size
			)
		);

		if ( empty( $categories ) ) {
			$this->log( __( 'No more categories to import. Process completed.', 'geodir-converter' ) );
			return $this->next_task( $task );
		}

		// Get existing category mapping.
		$category_mapping = (array) $this->options_handler->get_option_no_cache( 'category_mapping', array() );

		foreach ( $categories as $category ) {
			// Skip the nested set ROOT node.
			if ( 'ROOT' === $category->title || 0 === (int) $category->level ) {
				++$counts['skipped'];
				continue;
			}

			$parent_term_id = 0;
			if ( ! empty( $category->parent_id ) && isset( $category_mapping[ $category->parent_id ] ) ) {
				$parent_term_id = (int) $category_mapping[ $category->parent_id ];
			}

			/* translators: %d: category ID */
			$name        = ! empty( $category->title ) ? $category->title : sprintf( __( 'Category %d', 'geodir-converter' ), $category->id );
			$description = ! empty( $category->description ) ? $category->description : '';
			$term_slug   = sanitize_title( ! empty( $category->friendly_url ) ? $category->friendly_url : $name );
			$term_id     = $this->find_term_by_source( $taxonomy, 'pmd_category_id', $category->id );

			if ( ! $term_id ) {
				$existing = term_exists( $term_slug, $taxonomy, $parent_term_id );
				$term_id  = $existing ? absint( is_array( $existing ) ? $existing['term_id'] : $existing ) : 0;
			}

			// Handle test mode.
			if ( $this->is_test_mode() ) {
				$term_id ? ++$counts['updated'] : ++$counts['imported'];
				continue;
			}

			$is_update = (bool) $term_id;

			if ( ! $term_id ) {
				$term = wp_insert_term(
					$name,
					$taxonomy,
					array(
						'description' => $description,
						'parent'      => $parent_term_id,
						'slug'        => $term_slug,
					)
				);

				if ( is_wp_error( $term ) ) {
					$this->log(
						sprintf(
							/* translators: %1$s: category name, %2$s: error message */
							__( 'Failed to import category %1$s: %2$s', 'geodir-converter' ),
							$name,
							$term->get_error_message()
						),
						'error'
					);
					++$counts['failed'];
					continue;
				}

				$term_id = absint( $term['term_id'] );
			}

			update_term_meta( $term_id, 'pmd_category_id', (int) $category->id );

			// Store category mapping.
			$category_mapping[ $category->id ] = $term_id;

			// Import category description.
			if ( ! empty( $description ) ) {
				update_term_meta( $term_id, 'ct_cat_top_desc', $description );
			}

			$this->import_category_images( $term_id, $category );

			$is_update ? ++$counts['updated'] : ++$counts['imported'];
		}

		// Update category mapping.
		if ( ! $this->is_test_mode() ) {
			$this->options_handler->update_option( 'category_mapping', $category_mapping );
		}

		return $this->finish_batch( $task, $counts, $offset, $batch_size, $total_categories, __( 'Categories', 'geodir-converter' ) );
	}

	/**
	 * Import a PMD category's map icon and default image.
	 *
	 * PMD saves uploaded category images as files/categories/{id}.{ext} (large)
	 * and {id}-map.{ext} (map marker), or uses admin-entered image URLs.
	 * Images already set on the term are kept.
	 *
	 * @since 2.3.0
	 *
	 * @param int    $term_id  The GD term ID.
	 * @param object $category The PMD category row.
	 * @return void
	 */
	private function import_category_images( $term_id, $category ) {
		$image_extensions = array( 'jpg', 'jpeg', 'png', 'gif' );
		$images           = array(
			'ct_cat_icon'        => array(
				'url'  => ! empty( $category->small_image_url ) ? $category->small_image_url : '',
				'file' => (int) $category->id . '-map',
			),
			'ct_cat_default_img' => array(
				'url'  => ! empty( $category->large_image_url ) ? $category->large_image_url : '',
				'file' => (int) $category->id,
			),
		);

		foreach ( $images as $meta_key => $image ) {
			$current = get_term_meta( $term_id, $meta_key, true );

			if ( ! empty( $current['id'] ) ) {
				continue;
			}

			if ( '' !== $image['url'] ) {
				if ( ! $this->import_category_image( $term_id, $image['url'], $meta_key ) ) {
					/* translators: %s: image URL */
					$this->log( sprintf( __( 'Failed to import category image %s', 'geodir-converter' ), $image['url'] ), 'warning' );
				}
				continue;
			}

			foreach ( $image_extensions as $extension ) {
				if ( $this->import_category_image( $term_id, $this->get_pmd_url( 'files/categories/' . $image['file'] . '.' . $extension ), $meta_key ) ) {
					break;
				}
			}
		}
	}

	/**
	 * Find a term previously imported from a PMD row.
	 *
	 * @since 2.3.0
	 *
	 * @param string $taxonomy  The taxonomy.
	 * @param string $meta_key  Term meta key holding the PMD ID.
	 * @param int    $source_id The PMD ID.
	 * @return int The term ID, or 0.
	 */
	private function find_term_by_source( $taxonomy, $meta_key, $source_id ) {
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'fields'     => 'ids',
				'number'     => 1,
				'meta_key'   => $meta_key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value' => (string) (int) $source_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);

		return ! is_wp_error( $terms ) && ! empty( $terms ) ? (int) $terms[0] : 0;
	}

	/**
	 * Import blog categories from PMD to WordPress.
	 *
	 * @since 2.0.2
	 *
	 * @param array $task Import task details.
	 * @return array|false Result of the import operation or false if import is complete.
	 * @throws Exception If database connection fails.
	 */
	public function task_import_blog_categories( array $task ) {
		return $this->import_flat_categories(
			$task,
			array(
				'table'           => 'blog_categories',
				'taxonomy'        => 'category',
				'mapping_option'  => 'blog_category_mapping',
				'total_key'       => 'total_blog_categories',
				'term_meta_key'   => 'pmd_blog_category_id',
				'label'           => __( 'Blog categories', 'geodir-converter' ),
				'require_events'  => false,
			)
		);
	}

	/**
	 * Import event categories from PMD to GeoDirectory.
	 *
	 * @since 2.0.2
	 *
	 * @param array $task Import task details.
	 * @return array|false Result of the import operation or false if import is complete.
	 * @throws Exception If database connection fails.
	 */
	public function task_import_events_categories( array $task ) {
		return $this->import_flat_categories(
			$task,
			array(
				'table'           => 'events_categories',
				'taxonomy'        => self::POST_TYPE_EVENTS . 'category',
				'mapping_option'  => 'events_category_mapping',
				'total_key'       => 'total_events_categories',
				'term_meta_key'   => 'pmd_event_category_id',
				'label'           => __( 'Event categories', 'geodir-converter' ),
				'require_events'  => true,
			)
		);
	}

	/**
	 * Import a flat (non-hierarchical) PMD category table.
	 *
	 * @since 2.3.0
	 *
	 * @param array $task Import task details.
	 * @param array $args Table, taxonomy, mapping option, total key, term meta key and label.
	 * @return array|false Result of the import operation or false if import is complete.
	 * @throws Exception If database connection fails.
	 */
	private function import_flat_categories( array $task, array $args ) {
		if ( $args['require_events'] && ! $this->events_enabled() ) {
			$this->log( __( 'Events addon is not active. Skipping event categories...', 'geodir-converter' ) );
			return $this->next_task( $task );
		}

		$db = $this->require_db();

		wp_suspend_cache_addition( true );

		$offset     = isset( $task['offset'] ) ? absint( $task['offset'] ) : 0;
		$batch_size = absint( $this->get_batch_size() );
		$counts     = $this->get_task_counts( $task );
		$table      = $this->db_prefix . $args['table'];
		$total_key  = $args['total_key'];

		if ( ! isset( $task[ $total_key ] ) ) {
			$task[ $total_key ] = $this->count_table_rows( $args['table'] );
		}

		$total = absint( $task[ $total_key ] );

		if ( 0 === $offset ) {
			/* translators: %1$s: item type, %2$d: number of categories */
			$this->log( sprintf( __( 'Starting %1$s import: %2$d found.', 'geodir-converter' ), $args['label'], $total ) );
		}

		if ( 0 === $total ) {
			/* translators: %s: item type */
			$this->log( sprintf( __( 'No %s available for import. Skipping...', 'geodir-converter' ), $args['label'] ) );
			return $this->next_task( $task );
		}

		$categories = $db->get_results(
			$db->prepare(
				"SELECT c.* FROM `{$table}` c ORDER BY c.`id` ASC LIMIT %d, %d",
				$offset,
				$batch_size
			)
		);

		if ( empty( $categories ) ) {
			return $this->next_task( $task );
		}

		$category_mapping = (array) $this->options_handler->get_option_no_cache( $args['mapping_option'], array() );

		foreach ( $categories as $category ) {
			/* translators: %d: category ID */
			$name      = ! empty( $category->title ) ? $category->title : sprintf( __( 'Category %d', 'geodir-converter' ), $category->id );
			$term_slug = sanitize_title( ! empty( $category->friendly_url ) ? $category->friendly_url : $name );
			$term_id   = $this->find_term_by_source( $args['taxonomy'], $args['term_meta_key'], $category->id );

			if ( ! $term_id ) {
				$existing = term_exists( $term_slug, $args['taxonomy'] );
				$term_id  = $existing ? absint( is_array( $existing ) ? $existing['term_id'] : $existing ) : 0;
			}

			// Handle test mode.
			if ( $this->is_test_mode() ) {
				$term_id ? ++$counts['updated'] : ++$counts['imported'];
				continue;
			}

			$is_update = (bool) $term_id;

			if ( ! $term_id ) {
				$term = wp_insert_term( $name, $args['taxonomy'], array( 'slug' => $term_slug ) );

				if ( is_wp_error( $term ) ) {
					$this->log(
						sprintf(
							/* translators: %1$s: category name, %2$s: error message */
							__( 'Failed to import category %1$s: %2$s', 'geodir-converter' ),
							$name,
							$term->get_error_message()
						),
						'error'
					);
					++$counts['failed'];
					continue;
				}

				$term_id = absint( $term['term_id'] );
			}

			update_term_meta( $term_id, $args['term_meta_key'], (int) $category->id );

			$category_mapping[ $category->id ] = $term_id;

			$is_update ? ++$counts['updated'] : ++$counts['imported'];
		}

		if ( ! $this->is_test_mode() ) {
			$this->options_handler->update_option( $args['mapping_option'], $category_mapping );
		}

		return $this->finish_batch( $task, $counts, $offset, $batch_size, $total, $args['label'] );
	}

	/**
	 * Import category image.
	 *
	 * @since 2.0.2
	 *
	 * @param int    $term_id   Term ID.
	 * @param string $image_url Image URL.
	 * @param string $meta_key  Meta key.
	 * @return bool True on success, false on failure.
	 */
	public function import_category_image( $term_id, $image_url, $meta_key ) {
		$this->load_media_includes();

		$attachment_data = $this->import_attachment( $image_url );

		if ( ! isset( $attachment_data['id'], $attachment_data['src'] ) ) {
			return false;
		}

		update_term_meta(
			$term_id,
			$meta_key,
			array(
				'id'  => absint( $attachment_data['id'] ),
				'src' => $attachment_data['src'],
			)
		);

		return true;
	}

	/**
	 * Import listings from PMD to GeoDirectory.
	 *
	 * @since 2.0.2
	 *
	 * @param array $task Import task details.
	 * @return array|false Result of the import operation or false if import is complete.
	 * @throws Exception If database connection fails.
	 */
	public function task_import_listings( array $task ) {
		$this->require_db();

		wp_suspend_cache_addition( true );

		$handler = function ( $listing ) {
			return $this->import_single_listing( $listing );
		};

		if ( $this->is_retry_task( $task ) ) {
			return $this->retry_single_item( $task, self::ACTION_IMPORT_LISTINGS, 'listings', 'listing', $handler );
		}

		return $this->process_item_batch( $task, self::ACTION_IMPORT_LISTINGS, 'listings', 'listing', 'total_listings', $handler );
	}

	/**
	 * Import a single listing from PMD to GeoDirectory.
	 *
	 * @since 2.0.2
	 *
	 * @param object $listing The listing to import.
	 * @return int Import status constant.
	 */
	public function import_single_listing( $listing ) {
		$post_type  = $this->get_import_post_type();
		$gd_post_id = ! $this->is_test_mode() ? $this->get_gd_listing_id( $listing->id, 'pmd_id', $post_type ) : false;
		$is_update  = ! empty( $gd_post_id );
		$categories = $this->get_listing_categories( $listing );
		$tags       = ! empty( $listing->keywords ) ? array_filter( array_map( 'trim', explode( ',', $listing->keywords ) ) ) : array();
		$location   = $this->get_listing_location( (int) $listing->location_id );
		$has_coords = $this->has_coordinates( $listing->latitude, $listing->longitude );
		$date       = $this->normalize_date( $listing->date );
		$modified   = $this->normalize_date( $listing->date_update );

		// PMD listing statuses: active, pending, suspended.
		$status_map = array(
			'active'    => 'publish',
			'pending'   => 'pending',
			'suspended' => 'draft',
		);
		$status     = isset( $status_map[ $listing->status ] ) ? $status_map[ $listing->status ] : 'draft';

		// Prepare the listing data.
		$wp_listing = array(
			// Standard WP Fields.
			'post_author'           => $this->get_mapped_user_id( $listing->user_id, 1 ),
			'post_title'            => ( $listing->title ) ? $listing->title : 'NO TITLE',
			'post_content'          => $listing->description ? $listing->description : '',
			'post_content_filtered' => $listing->description ? $listing->description : '',
			'post_excerpt'          => $listing->description_short ? $listing->description_short : '',
			'post_status'           => $status,
			'post_type'             => $post_type,
			'comment_status'        => 'open',
			'ping_status'           => 'closed',
			'post_name'             => ( $listing->friendly_url ) ? $listing->friendly_url : 'listing-' . $listing->id,
			'post_date_gmt'         => $date ? get_gmt_from_date( $date ) : current_time( 'mysql', 1 ),
			'post_date'             => $date ? $date : current_time( 'mysql' ),
			'post_modified_gmt'     => $modified ? get_gmt_from_date( $modified ) : current_time( 'mysql', 1 ),
			'post_modified'         => $modified ? $modified : current_time( 'mysql' ),
			'tax_input'             => array(
				$post_type . 'category' => $categories,
				$post_type . '_tags'    => $tags,
			),

			// GD fields.
			'default_category'      => ! empty( $categories ) ? $categories[0] : 0,

			// Location.
			'street'                => ! empty( $listing->listing_address1 ) ? $listing->listing_address1 : '',
			'street2'               => ! empty( $listing->listing_address2 ) ? $listing->listing_address2 : '',
			'city'                  => ! empty( $listing->location_text_3 ) ? $listing->location_text_3 : $location['city'],
			'region'                => ! empty( $listing->location_text_2 ) ? $listing->location_text_2 : $location['region'],
			'country'               => ! empty( $listing->location_text_1 ) ? $listing->location_text_1 : $location['country'],
			'zip'                   => (string) $listing->listing_zip,
			'latitude'              => $has_coords ? $listing->latitude : $location['latitude'],
			'longitude'             => $has_coords ? $listing->longitude : $location['longitude'],
			'mapview'               => '',
			'mapzoom'               => '',

			// PMD standard fields.
			'pmd_id'                => (int) $listing->id,
			'phone'                 => ! empty( $listing->phone ) ? $listing->phone : '',
			'fax'                   => ! empty( $listing->fax ) ? $listing->fax : '',
			'business_hours'        => $this->get_business_hours( $listing->hours ),
			'website'               => ! empty( $listing->www ) ? $listing->www : '',
			'email'                 => ! empty( $listing->mail ) ? $listing->mail : '',
			'facebook'              => $this->build_social_url( $listing->facebook_page_id, 'https://facebook.com/' ),
			'google'                => $this->build_social_url( $listing->google_page_id, 'https://plus.google.com/' ),
			'linkedin'              => $this->build_social_url( $listing->linkedin_company_id, 'https://linkedin.com/company/' ),
			'twitter'               => $this->build_social_url( $listing->twitter_id, 'https://twitter.com/' ),
			'pinterest'             => $this->build_social_url( $listing->pinterest_id, 'https://pinterest.com/' ),
			'youtube'               => $this->build_social_url( $listing->youtube_id, 'https://youtube.com/user/' ),
			'foursquare'            => $this->build_social_url( $listing->foursquare_id, 'https://foursquare.com/' ),
			'instagram'             => $this->build_social_url( $listing->instagram_id, 'https://instagram.com/' ),
			'featured'              => ! empty( $listing->featured ) ? 1 : 0,
			'claimed'               => ! empty( $listing->claimed ) ? 1 : 0,

			// Media.
			'post_images'           => $this->get_listing_images( $listing->id ),
			'company_logo'          => ! empty( $listing->logo_extension ) ? $this->get_pmd_url( 'files/logo/' . (int) $listing->id . '.' . $listing->logo_extension ) . '|||' : '',
		);

		// PMD saves missing coordinates as 0.0000000000. Such a listing gets the default
		// location's coordinates (GD requires them) and is kept as a draft so the
		// map pin can be fixed before it goes live.
		if ( 'publish' === $status && ! $has_coords ) {
			$wp_listing['post_status'] = 'draft';

			/* translators: %s: listing title */
			$this->log( sprintf( __( 'Listing %s has no coordinates in PhpMyDirectory; imported as a draft with the default location coordinates.', 'geodir-converter' ), $wp_listing['post_title'] ), 'warning' );
		}

		// Membership package and expiry from the listing's PMD order.
		$wp_listing = array_merge( $wp_listing, $this->get_listing_package( $listing->id ) );

		// PMD custom field values (custom_{id} columns).
		$this->add_custom_field_values( $listing, $post_type, $wp_listing );

		if ( $this->is_test_mode() ) {
			return self::IMPORT_STATUS_SUCCESS;
		}

		// Delete existing media if updating.
		if ( $is_update ) {
			GeoDir_Media::delete_files( (int) $gd_post_id, 'post_images' );
			GeoDir_Media::delete_files( (int) $gd_post_id, 'company_logo' );
		}

		// Insert or update the post.
		if ( $is_update ) {
			$wp_listing['ID'] = absint( $gd_post_id );
			$gd_post_id       = wp_update_post( $wp_listing, true );
		} else {
			$gd_post_id = wp_insert_post( $wp_listing, true );
		}

		// Handle errors during post insertion/update.
		if ( is_wp_error( $gd_post_id ) ) {
			$this->log( $gd_post_id->get_error_message(), 'error' );
			return self::IMPORT_STATUS_FAILED;
		}

		$this->gd_ids_cache[ $post_type ][ (int) $listing->id ] = (int) $gd_post_id;

		return $is_update ? self::IMPORT_STATUS_UPDATED : self::IMPORT_STATUS_SUCCESS;
	}

	/**
	 * Build a social profile URL from a PMD profile ID.
	 *
	 * @since 2.3.0
	 *
	 * @param string $value The PMD value (profile ID, handle or full URL).
	 * @param string $base  The profile base URL.
	 * @return string The profile URL.
	 */
	private function build_social_url( $value, $base ) {
		$value = trim( (string) $value );

		if ( '' === $value ) {
			return '';
		}

		if ( preg_match( '#^https?://#i', $value ) ) {
			return $value;
		}

		return $base . ltrim( $value, '@/' );
	}

	/**
	 * Get the GD package and expiry date for a PMD listing.
	 *
	 * PMD keeps a listing's membership in an order of type listing_membership
	 * (orders.type_id is the listing ID, orders.pricing_id the pricing option).
	 *
	 * @since 2.3.0
	 *
	 * @param int $listing_id The PMD listing ID.
	 * @return array GD package_id and expire_date, or an empty array.
	 */
	private function get_listing_package( $listing_id ) {
		if ( ! class_exists( 'GeoDir_Pricing_Package' ) || ! $this->table_exists( 'orders' ) ) {
			return array();
		}

		$db    = $this->get_db_connection();
		$order = $db->get_row(
			$db->prepare(
				"SELECT `pricing_id`, `next_due_date` FROM `{$this->db_prefix}orders` WHERE `type` = %s AND `type_id` = %d ORDER BY `id` DESC LIMIT 1",
				'listing_membership',
				(int) $listing_id
			)
		);

		if ( ! $order ) {
			return array();
		}

		$packages_mapping = $this->get_packages_mapping();
		$data             = array();

		if ( ! empty( $order->pricing_id ) && isset( $packages_mapping[ (int) $order->pricing_id ] ) ) {
			$data['package_id'] = (int) $packages_mapping[ (int) $order->pricing_id ];
		}

		// A blank expire date means "never" in GD.
		$expire_date = $this->normalize_date( $order->next_due_date, 'Y-m-d' );
		if ( $expire_date ) {
			$data['expire_date'] = $expire_date;
		}

		return $data;
	}

	/**
	 * Get the PMD pricing ID to GD package ID mapping.
	 *
	 * @since 2.3.0
	 *
	 * @return array The mapping.
	 */
	private function get_packages_mapping() {
		if ( null === $this->packages_mapping_cache ) {
			$this->packages_mapping_cache = (array) $this->options_handler->get_option_no_cache( 'packages_mapping', array() );
		}

		return $this->packages_mapping_cache;
	}

	/**
	 * Get gallery images.
	 *
	 * PMD saves listing images as files/images/{image ID}.{extension}.
	 *
	 * @since 2.0.2
	 *
	 * @param int $listing_id The listing ID.
	 * @return string The formatted images string.
	 */
	private function get_listing_images( $listing_id ) {
		$db         = $this->get_db_connection();
		$table      = $this->db_prefix . 'images';
		$extensions = array( 'jpg', 'jpeg', 'gif', 'png', 'svg', 'webp' );
		$images     = $db->get_results( $db->prepare( "SELECT * FROM `{$table}` WHERE `listing_id` = %d ORDER BY `ordering` ASC, `id` ASC", $listing_id ) );

		if ( empty( $images ) ) {
			return '';
		}

		$formatted_images = array();

		foreach ( $images as $image ) {
			$extension = strtolower( (string) $image->extension );

			if ( ! in_array( $extension, $extensions, true ) ) {
				continue;
			}

			// "|" and "::" separate values in GD's file string.
			$title   = trim( str_replace( array( '|', '::' ), ' ', sanitize_text_field( (string) $image->title ) ) );
			$caption = trim( str_replace( array( '|', '::' ), ' ', sanitize_text_field( (string) $image->description ) ) );

			$formatted_images[] = sprintf(
				'%s||%s|%s',
				esc_url_raw( $this->get_pmd_url( 'files/images/' . absint( $image->id ) . '.' . $extension ) ),
				$title,
				$caption
			);
		}

		return implode( '::', $formatted_images );
	}

	/**
	 * Retrieves the location data for a given location ID.
	 *
	 * PMD stores locations as a nested set under a ROOT node (level 0). As
	 * before, level 2 is used as the region and level 3 as the city. Level 1
	 * is used as the country when it is a country GD knows; otherwise the GD
	 * default location's country is kept.
	 *
	 * @since 2.0.2
	 *
	 * @param int $location_id The ID of the location to retrieve.
	 * @return array The location data.
	 */
	public function get_listing_location( $location_id ) {
		$default_location = $this->get_default_location();
		$location         = array(
			'country'   => $default_location['country'],
			'region'    => $default_location['region'],
			'city'      => $default_location['city'],
			'latitude'  => $default_location['latitude'],
			'longitude' => $default_location['longitude'],
		);

		if ( ! $location_id ) {
			return $location;
		}

		$locations = $this->get_locations();
		$chain     = array();
		$current   = isset( $locations[ $location_id ] ) ? $locations[ $location_id ] : null;
		$guard     = 0;

		while ( $current && (int) $current->level > 0 && $guard < 20 ) {
			array_unshift( $chain, $current );
			$current = ! empty( $current->parent_id ) && isset( $locations[ (int) $current->parent_id ] ) ? $locations[ (int) $current->parent_id ] : null;
			++$guard;
		}

		if ( ! empty( $chain ) && function_exists( 'geodir_get_countries' ) ) {
			foreach ( array_keys( geodir_get_countries() ) as $country ) {
				if ( 0 === strcasecmp( $country, trim( $chain[0]->title ) ) ) {
					$location['country'] = $country;
					break;
				}
			}
		}

		if ( count( $chain ) > 1 ) {
			$location['region'] = $chain[1]->title;
			$location['city']   = isset( $chain[2] ) ? $chain[2]->title : $chain[1]->title;
		}

		return $location;
	}

	/**
	 * Load the PMD locations tree once per request.
	 *
	 * @since 2.3.0
	 *
	 * @return array Location rows keyed by ID.
	 */
	private function get_locations() {
		if ( null !== $this->locations_cache ) {
			return $this->locations_cache;
		}

		$this->locations_cache = array();

		$db   = $this->get_db_connection();
		$rows = $db->get_results( "SELECT `id`, `title`, `parent_id`, `level` FROM `{$this->db_prefix}locations`" );

		foreach ( (array) $rows as $row ) {
			$this->locations_cache[ (int) $row->id ] = $row;
		}

		return $this->locations_cache;
	}

	/**
	 * Retrieves the categories for a given listing.
	 *
	 * The primary category comes first so it becomes the GD default category.
	 *
	 * @since 2.0.2
	 *
	 * @param object $listing The listing object.
	 * @return array The GD category term IDs.
	 */
	public function get_listing_categories( $listing ) {
		$db    = $this->get_db_connection();
		$table = $this->db_prefix . 'listings_categories';

		$category_mapping = (array) $this->options_handler->get_option_no_cache( 'category_mapping', array() );
		$category_ids     = array_map(
			'intval',
			(array) $db->get_col(
				$db->prepare(
					"SELECT `cat_id` FROM `{$table}` WHERE `list_id` = %d ORDER BY `cat_id`",
					$listing->id
				)
			)
		);

		$primary = (int) $listing->primary_category_id;
		if ( $primary ) {
			$category_ids = array_merge( array( $primary ), array_diff( $category_ids, array( $primary ) ) );
		}

		$categories = array();
		foreach ( $category_ids as $category_id ) {
			if ( isset( $category_mapping[ $category_id ] ) ) {
				$categories[] = (int) $category_mapping[ $category_id ];
			}
		}

		return array_values( array_unique( $categories ) );
	}

	/**
	 * Get business hours.
	 *
	 * PMD stores hours as a serialized list of "{day} {G:i} {G:i}" strings, where
	 * the day is 0 (Sunday) to 6 (Saturday) (Form::getFormElement 'hours' and
	 * Dates::getWeekDays()), or as '24' when the listing is open 24 hours.
	 *
	 * GD compares times as "H:i" strings and treats 00:00-00:00 as open 24 hours
	 * (geodir_get_business_hours()), so times are zero-padded.
	 *
	 * @since 2.0.2
	 *
	 * @param string $hours  The serialized PMD hours.
	 * @param int    $offset Unused, kept for backwards compatibility.
	 * @return string The GD business hours schema.
	 */
	public function get_business_hours( $hours, $offset = 0 ) {
		if ( empty( $hours ) ) {
			return '';
		}

		if ( ! function_exists( 'geodir_array_to_schema' ) ) {
			return '';
		}

		// Open 24 hours, every day.
		if ( '24' === trim( (string) $hours ) ) {
			$hours = array();

			for ( $day = 0; $day < 7; $day++ ) {
				$hours[] = $day . ' 00:00 00:00';
			}
		}

		// Data comes from an external database, so never instantiate objects.
		if ( is_string( $hours ) && is_serialized( $hours ) ) {
			$hours = @unserialize( trim( $hours ), array( 'allowed_classes' => false ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize, WordPress.PHP.NoSilencedErrors.Discouraged
		}

		if ( empty( $hours ) || ! is_array( $hours ) ) {
			return '';
		}

		$day_map = array(
			'0' => 'Su',
			'1' => 'Mo',
			'2' => 'Tu',
			'3' => 'We',
			'4' => 'Th',
			'5' => 'Fr',
			'6' => 'Sa',
			'7' => 'Su',
		);

		$schema_hours = array();

		foreach ( $hours as $times ) {
			$time_parts = explode( ' ', trim( (string) $times ) );

			if ( count( $time_parts ) < 3 || ! isset( $day_map[ $time_parts[0] ] ) ) {
				continue;
			}

			$opens  = $this->format_hours_time( $time_parts[1] );
			$closes = $this->format_hours_time( $time_parts[2] );

			if ( '' === $opens || '' === $closes ) {
				continue;
			}

			$schema_hours[ $day_map[ $time_parts[0] ] ][] = array(
				'opens'  => $opens,
				'closes' => $closes,
			);
		}

		if ( empty( $schema_hours ) ) {
			return '';
		}

		return geodir_array_to_schema( array( 'hours' => $schema_hours ) );
	}

	/**
	 * Format a PMD hours time ("9:00", "17:30") as GD's "H:i".
	 *
	 * @since 2.3.0
	 *
	 * @param string $time The PMD time.
	 * @return string The time as H:i, or an empty string if invalid.
	 */
	private function format_hours_time( $time ) {
		if ( ! preg_match( '/^(\d{1,2}):(\d{2})$/', trim( (string) $time ), $matches ) || (int) $matches[1] > 24 || (int) $matches[2] > 59 ) {
			return '';
		}

		return sprintf( '%02d:%02d', (int) $matches[1] % 24, (int) $matches[2] );
	}

	/**
	 * Import events from PMD to GeoDirectory.
	 *
	 * @since 2.0.2
	 *
	 * @param array $task Import task details.
	 * @return array|false Result of the import operation or false if import is complete.
	 * @throws Exception If database connection fails.
	 */
	public function task_import_events( array $task ) {
		// Abort early if events addon is not installed.
		if ( ! $this->events_enabled() ) {
			$this->log( __( 'Events addon is not active. Skipping events...', 'geodir-converter' ) );

			if ( $this->is_retry_task( $task ) ) {
				// Keep the retried item counted so progress still adds up.
				$this->increase_failed_imports( 1 );
				return false;
			}

			return $this->next_task( $task );
		}

		$this->require_db();

		wp_suspend_cache_addition( true );

		$handler = function ( $event ) {
			return $this->import_single_event( $event );
		};

		if ( $this->is_retry_task( $task ) ) {
			return $this->retry_single_item( $task, self::ACTION_IMPORT_EVENTS, 'events', 'event', $handler );
		}

		return $this->process_item_batch( $task, self::ACTION_IMPORT_EVENTS, 'events', 'event', 'total_events', $handler );
	}

	/**
	 * Import a single PMD event.
	 *
	 * @since 2.3.0
	 *
	 * @param object $event The PMD event row.
	 * @return int Import status constant.
	 */
	private function import_single_event( $event ) {
		$post_type   = self::POST_TYPE_EVENTS;
		$gd_event_id = ! $this->is_test_mode() ? $this->get_gd_listing_id( $event->id, 'pmd_id', $post_type ) : false;
		$is_update   = ! empty( $gd_event_id );
		$categories  = $this->get_post_categories( $event->id, 'events', 'event_id' );
		$tags        = ! empty( $event->keywords ) ? array_filter( array_map( 'trim', explode( ',', $event->keywords ) ) ) : array();
		$location    = $this->get_event_location( $event );
		$event_dates = $this->get_event_dates( $event );
		$date        = $this->normalize_date( $event->date );
		$modified    = $this->normalize_date( $event->date_update );

		// PMD event statuses: active, pending.
		$status_map = array(
			'active'  => 'publish',
			'pending' => 'pending',
		);
		$status     = isset( $status_map[ $event->status ] ) ? $status_map[ $event->status ] : 'draft';

		$gd_event = array(
			// Standard WP Fields.
			'post_author'           => $this->get_mapped_user_id( $event->user_id, 1 ),
			'post_title'            => ( $event->title ) ? $event->title : '&mdash;',
			'post_content'          => $event->description ? $event->description : '',
			'post_content_filtered' => $event->description ? $event->description : '',
			'post_excerpt'          => $event->description_short ? $event->description_short : '',
			'post_status'           => $status,
			'post_type'             => $post_type,
			'comment_status'        => 'open',
			'ping_status'           => 'closed',
			'post_name'             => ( $event->friendly_url ) ? $event->friendly_url : 'event-' . $event->id,
			'post_date_gmt'         => $date ? get_gmt_from_date( $date ) : current_time( 'mysql', 1 ),
			'post_date'             => $date ? $date : current_time( 'mysql' ),
			'post_modified_gmt'     => $modified ? get_gmt_from_date( $modified ) : current_time( 'mysql', 1 ),
			'post_modified'         => $modified ? $modified : current_time( 'mysql' ),
			'tax_input'             => array(
				$post_type . 'category' => $categories,
				$post_type . '_tags'    => $tags,
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
			'mapview'               => '',
			'mapzoom'               => '',

			// Event dates.
			'recurring'             => $event_dates['recurring'],
			'event_dates'           => $event_dates,

			// PMD standard fields.
			'pmd_id'                => (int) $event->id,
			'phone'                 => ! empty( $event->phone ) ? $event->phone : '',
			'website'               => ! empty( $event->website ) ? $event->website : '',
			'email'                 => ! empty( $event->email ) ? $event->email : '',
			'venue'                 => ! empty( $event->venue ) ? $event->venue : '',
			'location'              => ! empty( $event->location ) ? $event->location : '',
			'event_reg_desc'        => ! empty( $event->admission ) ? $event->admission : '',
			'contact_name'          => ! empty( $event->contact_name ) ? $event->contact_name : '',

			// PMD events have a single image: files/events/images/{id}.{extension}.
			'post_images'           => ! empty( $event->image_extension ) ? esc_url_raw( $this->get_pmd_url( 'files/events/images/' . (int) $event->id . '.' . $event->image_extension ) ) . '|||' : '',
		);

		// PMD custom field values (custom_{id} columns).
		$this->add_custom_field_values( $event, $post_type, $gd_event );

		// Handle test mode.
		if ( $this->is_test_mode() ) {
			return self::IMPORT_STATUS_SUCCESS;
		}

		// Delete existing media if updating.
		if ( $is_update ) {
			GeoDir_Media::delete_files( (int) $gd_event_id, 'post_images' );
		}

		// Insert or update the post.
		if ( $is_update ) {
			$gd_event['ID'] = absint( $gd_event_id );
			$gd_event_id    = wp_update_post( $gd_event, true );
		} else {
			$gd_event_id = wp_insert_post( $gd_event, true );
		}

		if ( is_wp_error( $gd_event_id ) ) {
			$this->log( $gd_event_id->get_error_message(), 'error' );
			return self::IMPORT_STATUS_FAILED;
		}

		return $is_update ? self::IMPORT_STATUS_UPDATED : self::IMPORT_STATUS_SUCCESS;
	}

	/**
	 * Build the GD event_dates data for a PMD event.
	 *
	 * PMD pre-computes every occurrence of a recurring event in the events_dates
	 * table (Events::updateRecurring()), so recurring events are imported as GD
	 * "custom" recurring dates.
	 *
	 * @since 2.3.0
	 *
	 * @param object $event The PMD event row.
	 * @return array GD event dates.
	 */
	private function get_event_dates( $event ) {
		$start = $this->normalize_date( $event->date_start );
		$end   = $this->normalize_date( $event->date_end );

		$start_date = $start ? gmdate( 'Y-m-d', strtotime( $start ) ) : '';
		$end_date   = $end ? gmdate( 'Y-m-d', strtotime( $end ) ) : $start_date;

		$event_dates = array(
			'recurring'       => 0,
			'start_date'      => $start_date,
			'end_date'        => $end_date,
			'all_day'         => 0,
			'start_time'      => $start ? gmdate( 'H:i', strtotime( $start ) ) : '',
			'end_time'        => $end ? gmdate( 'H:i', strtotime( $end ) ) : '',
			'duration_x'      => '',
			'repeat_type'     => '',
			'repeat_x'        => '',
			'repeat_end_type' => '',
			'max_repeat'      => '',
			'repeat_end'      => '',
			'recurring_dates' => '',
			'different_times' => '',
			'start_times'     => '',
			'end_times'       => '',
			'repeat_days'     => '',
			'repeat_weeks'    => '',
		);

		if ( empty( $event->recurring ) || ! $this->table_exists( 'events_dates' ) ) {
			return $event_dates;
		}

		$db          = $this->get_db_connection();
		$occurrences = $db->get_col(
			$db->prepare(
				"SELECT `date_start` FROM `{$this->db_prefix}events_dates` WHERE `event_id` = %d ORDER BY `date_start` ASC",
				(int) $event->id
			)
		);

		$recurring_dates = array();
		foreach ( (array) $occurrences as $occurrence ) {
			$occurrence = $this->normalize_date( $occurrence, 'Y-m-d' );

			if ( $occurrence ) {
				$recurring_dates[ $occurrence ] = $occurrence;
			}
		}

		if ( empty( $recurring_dates ) ) {
			return $event_dates;
		}

		$duration = 1;
		if ( $start_date && $end_date ) {
			$duration = max( 1, (int) floor( ( strtotime( $end_date ) - strtotime( $start_date ) ) / DAY_IN_SECONDS ) + 1 );
		}

		$event_dates['recurring']       = 1;
		$event_dates['repeat_type']     = 'custom';
		$event_dates['duration_x']      = $duration;
		$event_dates['recurring_dates'] = array_values( $recurring_dates );

		return $event_dates;
	}

	/**
	 * Get the location of a PMD event.
	 *
	 * An event linked to an imported listing uses that listing's address;
	 * otherwise the GD default location is used. Event coordinates win when set.
	 *
	 * @since 2.3.0
	 *
	 * @param object $event The PMD event row.
	 * @return array Location data.
	 */
	private function get_event_location( $event ) {
		$default  = $this->get_default_location();
		$location = array(
			'street'    => '',
			'city'      => $default['city'],
			'region'    => $default['region'],
			'country'   => $default['country'],
			'zip'       => '',
			'latitude'  => $default['latitude'],
			'longitude' => $default['longitude'],
		);

		if ( ! empty( $event->listing_id ) ) {
			$gd_listing_id = $this->get_gd_id_by_pmd_id( (int) $event->listing_id, $this->get_import_post_type() );
			$gd_listing    = $gd_listing_id ? geodir_get_post_info( $gd_listing_id ) : false;

			if ( $gd_listing && ! empty( $gd_listing->city ) ) {
				foreach ( array( 'street', 'city', 'region', 'country', 'zip', 'latitude', 'longitude' ) as $key ) {
					$location[ $key ] = isset( $gd_listing->{$key} ) ? (string) $gd_listing->{$key} : $location[ $key ];
				}
			}
		}

		if ( $this->has_coordinates( $event->latitude, $event->longitude ) ) {
			$location['latitude']  = $event->latitude;
			$location['longitude'] = $event->longitude;
		}

		return $location;
	}

	/**
	 * Import reviews from PMD to GeoDirectory.
	 *
	 * @since 2.0.2
	 *
	 * @param array $task Import task details.
	 * @return array|false Result of the import operation or false if import is complete.
	 * @throws Exception If database connection fails.
	 */
	public function task_import_reviews( array $task ) {
		$db = $this->require_db();

		wp_suspend_cache_addition( true );

		$offset        = isset( $task['offset'] ) ? absint( $task['offset'] ) : 0;
		$batch_size    = absint( $this->get_batch_size() );
		$counts        = $this->get_task_counts( $task );
		$post_type     = $this->get_import_post_type();
		$reviews_table = $this->db_prefix . 'reviews';
		$ratings_table = $this->db_prefix . 'ratings';
		$users_table   = $this->db_prefix . 'users';

		if ( ! isset( $task['total_reviews'] ) ) {
			$task['total_reviews'] = $this->count_table_rows( 'reviews' );
		}

		$total_reviews = absint( $task['total_reviews'] );

		// Log the import start message only for the first batch.
		if ( 0 === $offset ) {
			/* translators: %d: number of reviews */
			$this->log( sprintf( __( 'Starting reviews import: %d reviews found.', 'geodir-converter' ), $total_reviews ) );
		}

		// Exit early if there are no reviews to import.
		if ( 0 === $total_reviews ) {
			$this->log( __( 'No reviews available for import. Skipping...', 'geodir-converter' ) );
			return $this->next_task( $task );
		}

		$reviews = $db->get_results(
			$db->prepare(
				"SELECT
					r.`id` AS review_id,
					r.`status`,
					r.`listing_id`,
					r.`user_id`,
					r.`date`,
					r.`name`,
					r.`review`,
					u.`user_first_name`,
					u.`user_last_name`,
					u.`user_email`,
					rt.`rating`
				FROM `{$reviews_table}` AS r
				LEFT JOIN `{$users_table}` AS u ON r.`user_id` = u.`id`
				LEFT JOIN `{$ratings_table}` AS rt ON r.`rating_id` = rt.`id`
				ORDER BY r.`id` ASC
				LIMIT %d, %d",
				$offset,
				$batch_size
			)
		);

		if ( empty( $reviews ) ) {
			$this->log( __( 'No more reviews to import. Process completed.', 'geodir-converter' ) );
			return $this->next_task( $task );
		}

		$listing_ids = $this->get_gd_ids_by_pmd_ids( wp_list_pluck( $reviews, 'listing_id' ), $post_type );

		foreach ( $reviews as $review ) {
			$listing_id = isset( $listing_ids[ (int) $review->listing_id ] ) ? (int) $listing_ids[ (int) $review->listing_id ] : 0;

			if ( ! $listing_id ) {
				/* translators: %1$d: review ID, %2$d: listing ID */
				$this->log( sprintf( __( 'Skipped review %1$d: listing #%2$d was not imported.', 'geodir-converter' ), $review->review_id, $review->listing_id ), 'warning' );
				++$counts['skipped'];
				continue;
			}

			$legacy_agent = 'geodir-converter' . md5( (string) $review->user_email ) . $review->review_id;
			$existing_id  = $this->find_imported_comment( 'pmd_review_id', $review->review_id, $legacy_agent, $listing_id );
			$author       = trim( $review->user_first_name . ' ' . $review->user_last_name );
			$date         = $this->normalize_date( $review->date );

			$comment_data = array(
				'comment_post_ID'      => $listing_id,
				'user_id'              => $this->get_mapped_user_id( $review->user_id, 0 ),
				'comment_date'         => $date ? $date : current_time( 'mysql' ),
				'comment_date_gmt'     => $date ? get_gmt_from_date( $date ) : current_time( 'mysql', 1 ),
				'comment_content'      => (string) $review->review,
				'comment_author'       => '' !== $author ? $author : (string) $review->name,
				'comment_author_email' => (string) $review->user_email,
				'comment_agent'        => $legacy_agent,
				'comment_approved'     => 'active' === $review->status ? 1 : 0,
			);

			// Handle test mode.
			if ( $this->is_test_mode() ) {
				$existing_id ? ++$counts['updated'] : ++$counts['imported'];
				continue;
			}

			$comment_id = $this->save_imported_comment( $comment_data, $existing_id, 'pmd_review_id', $review->review_id );

			if ( ! $comment_id ) {
				/* translators: %d: review ID */
				$this->log( sprintf( __( 'Failed to import review %d.', 'geodir-converter' ), $review->review_id ), 'error' );
				++$counts['failed'];
				continue;
			}

			if ( ! empty( $review->rating ) ) {
				$this->save_pmd_rating( $comment_id, (int) $review->rating, (bool) $existing_id );
			}

			$existing_id ? ++$counts['updated'] : ++$counts['imported'];
		}

		return $this->finish_batch( $task, $counts, $offset, $batch_size, $total_reviews, __( 'Reviews', 'geodir-converter' ) );
	}

	/**
	 * Save a GD rating for an imported review.
	 *
	 * GeoDir_Comments::save_rating() inserts a rating for the global $user_ID;
	 * GeoDir_Comments::edit_comment() updates an existing rating (or saves one if
	 * missing) and recalculates the listing rating.
	 *
	 * @since 2.3.0
	 *
	 * @param int  $comment_id The comment ID.
	 * @param int  $rating     The rating.
	 * @param bool $is_update  Whether the review existed already.
	 * @return void
	 */
	private function save_pmd_rating( $comment_id, $rating, $is_update ) {
		global $user_ID;

		$comment = get_comment( $comment_id );

		if ( ! $comment || $rating < 1 || ! class_exists( 'GeoDir_Comments' ) ) {
			return;
		}

		$current_user_id = $user_ID;
		$user_ID         = (int) $comment->user_id; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		$_REQUEST['geodir_overallrating'] = absint( $rating );

		if ( $is_update ) {
			GeoDir_Comments::edit_comment( (int) $comment->comment_ID );
		} else {
			GeoDir_Comments::save_rating( (int) $comment->comment_ID );
		}

		unset( $_REQUEST['geodir_overallrating'] );

		$user_ID = $current_user_id; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	}

	/**
	 * Import review replies from PMD to GeoDirectory.
	 *
	 * PMD review comments become WordPress replies to the imported review.
	 *
	 * @since 2.3.0
	 *
	 * @param array $task Import task details.
	 * @return array|false Result of the import operation or false if import is complete.
	 * @throws Exception If database connection fails.
	 */
	public function task_import_review_comments( array $task ) {
		$db = $this->require_db();

		wp_suspend_cache_addition( true );

		$offset     = isset( $task['offset'] ) ? absint( $task['offset'] ) : 0;
		$batch_size = absint( $this->get_batch_size() );
		$counts     = $this->get_task_counts( $task );
		$label      = __( 'Review replies', 'geodir-converter' );

		if ( ! isset( $task['total_review_comments'] ) ) {
			$task['total_review_comments'] = $this->count_table_rows( 'reviews_comments' );
		}

		$total = absint( $task['total_review_comments'] );

		if ( 0 === $total ) {
			$this->log( __( 'No review replies available for import. Skipping...', 'geodir-converter' ) );
			return $this->next_task( $task );
		}

		$rows = $db->get_results(
			$db->prepare(
				"SELECT c.*, u.`user_first_name`, u.`user_last_name`, u.`user_email`
				FROM `{$this->db_prefix}reviews_comments` c
				LEFT JOIN `{$this->db_prefix}users` u ON c.`user_id` = u.`id`
				ORDER BY c.`id` ASC
				LIMIT %d, %d",
				$offset,
				$batch_size
			)
		);

		if ( empty( $rows ) ) {
			return $this->next_task( $task );
		}

		foreach ( $rows as $row ) {
			$parent_id = $this->find_imported_comment( 'pmd_review_id', $row->review_id );
			$parent    = $parent_id ? get_comment( $parent_id ) : null;

			if ( ! $parent ) {
				/* translators: %1$d: reply ID, %2$d: review ID */
				$this->log( sprintf( __( 'Skipped review reply %1$d: review #%2$d was not imported.', 'geodir-converter' ), $row->id, $row->review_id ), 'warning' );
				++$counts['skipped'];
				continue;
			}

			$existing_id = $this->find_imported_comment( 'pmd_review_comment_id', $row->id );
			$date        = $this->normalize_date( $row->date );

			if ( $this->is_test_mode() ) {
				$existing_id ? ++$counts['updated'] : ++$counts['imported'];
				continue;
			}

			$comment_id = $this->save_imported_comment(
				array(
					'comment_post_ID'      => (int) $parent->comment_post_ID,
					'comment_parent'       => (int) $parent->comment_ID,
					'user_id'              => $this->get_mapped_user_id( $row->user_id, 0 ),
					'comment_date'         => $date ? $date : current_time( 'mysql' ),
					'comment_date_gmt'     => $date ? get_gmt_from_date( $date ) : current_time( 'mysql', 1 ),
					'comment_content'      => (string) $row->comment,
					'comment_author'       => trim( $row->user_first_name . ' ' . $row->user_last_name ),
					'comment_author_email' => (string) $row->user_email,
					'comment_agent'        => 'geodir-converter',
					'comment_approved'     => 'active' === $row->status ? 1 : 0,
				),
				$existing_id,
				'pmd_review_comment_id',
				$row->id
			);

			if ( ! $comment_id ) {
				/* translators: %d: reply ID */
				$this->log( sprintf( __( 'Failed to import review reply %d.', 'geodir-converter' ), $row->id ), 'error' );
				++$counts['failed'];
				continue;
			}

			$existing_id ? ++$counts['updated'] : ++$counts['imported'];
		}

		return $this->finish_batch( $task, $counts, $offset, $batch_size, $total, $label );
	}

	/**
	 * Import favorites from PMD to GeoDirectory.
	 *
	 * @since 2.3.0
	 *
	 * @param array $task Import task details.
	 * @return array|false Result of the import operation or false if import is complete.
	 * @throws Exception If database connection fails.
	 */
	public function task_import_favorites( array $task ) {
		$db = $this->require_db();

		$offset     = isset( $task['offset'] ) ? absint( $task['offset'] ) : 0;
		$batch_size = absint( $this->get_batch_size() );
		$counts     = $this->get_task_counts( $task );
		$post_type  = $this->get_import_post_type();

		if ( ! isset( $task['total_favorites'] ) ) {
			$task['total_favorites'] = $this->count_table_rows( 'favorites' );
		}

		$total = absint( $task['total_favorites'] );

		if ( 0 === $total ) {
			$this->log( __( 'No favorites available for import. Skipping...', 'geodir-converter' ) );
			return $this->next_task( $task );
		}

		$rows = $db->get_results(
			$db->prepare(
				"SELECT * FROM `{$this->db_prefix}favorites` ORDER BY `id` ASC LIMIT %d, %d",
				$offset,
				$batch_size
			)
		);

		if ( empty( $rows ) ) {
			return $this->next_task( $task );
		}

		$listing_ids = $this->get_gd_ids_by_pmd_ids( wp_list_pluck( $rows, 'listing_id' ), $post_type );

		foreach ( $rows as $row ) {
			$user_id    = $this->get_mapped_user_id( $row->user_id, 0 );
			$listing_id = isset( $listing_ids[ (int) $row->listing_id ] ) ? (int) $listing_ids[ (int) $row->listing_id ] : 0;

			if ( ! $user_id || ! $listing_id ) {
				++$counts['skipped'];
				continue;
			}

			$favorites = (array) GeoDir_User::get_user_favs( $user_id );

			if ( in_array( $listing_id, array_map( 'intval', $favorites ), true ) ) {
				++$counts['skipped'];
				continue;
			}

			if ( $this->is_test_mode() ) {
				++$counts['imported'];
				continue;
			}

			GeoDir_User::add_fav( $listing_id, $user_id ) ? ++$counts['imported'] : ++$counts['failed'];
		}

		return $this->finish_batch( $task, $counts, $offset, $batch_size, $total, __( 'Favorites', 'geodir-converter' ) );
	}

	/**
	 * Import posts from PMD to WordPress.
	 *
	 * @since 2.0.2
	 *
	 * @param array $task Import task details.
	 * @return array|false Result of the import operation or false if import is complete.
	 * @throws Exception If database connection fails.
	 */
	public function task_import_posts( array $task ) {
		$this->require_db();

		wp_suspend_cache_addition( true );

		$handler = function ( $post ) {
			return $this->import_single_post( $post );
		};

		if ( $this->is_retry_task( $task ) ) {
			return $this->retry_single_item( $task, self::ACTION_IMPORT_POSTS, 'blog', 'post', $handler );
		}

		return $this->process_item_batch( $task, self::ACTION_IMPORT_POSTS, 'blog', 'post', 'total_posts', $handler );
	}

	/**
	 * Import a single PMD blog post.
	 *
	 * @since 2.3.0
	 *
	 * @param object $post The PMD blog row.
	 * @return int Import status constant.
	 */
	private function import_single_post( $post ) {
		$post_id    = ! $this->is_test_mode() ? $this->get_gd_post_id( $post->id, 'pmd_blog_id' ) : false;
		$is_update  = ! empty( $post_id );
		$status_map = array(
			'active'  => 'publish',
			'pending' => 'pending',
		);
		$status     = isset( $status_map[ $post->status ] ) ? $status_map[ $post->status ] : 'draft';
		$published  = $this->normalize_date( $post->date_publish );
		$date       = $published ? $published : $this->normalize_date( $post->date );
		$modified   = $this->normalize_date( $post->date_updated );

		$post_data = array(
			'post_author'       => $this->get_mapped_user_id( $post->user_id, 1 ),
			'post_content'      => ( $post->content ) ? $post->content : '',
			'post_title'        => ( $post->title ) ? $post->title : '',
			'post_name'         => ( $post->friendly_url ) ? $post->friendly_url : '',
			'post_excerpt'      => ( $post->content_short ) ? $post->content_short : '',
			'post_status'       => $status,
			'post_type'         => 'post',
			'post_date'         => $date ? $date : '',
			'post_date_gmt'     => $date ? get_gmt_from_date( $date ) : '',
			'post_modified'     => $modified ? $modified : '',
			'post_modified_gmt' => $modified ? get_gmt_from_date( $modified ) : '',
			'tags_input'        => ! empty( $post->keywords ) ? array_filter( array_map( 'trim', explode( ',', $post->keywords ) ) ) : array(),
		);

		// Handle test mode.
		if ( $this->is_test_mode() ) {
			return self::IMPORT_STATUS_SUCCESS;
		}

		// Insert or update the post.
		if ( $is_update ) {
			$post_data['ID'] = absint( $post_id );
			$post_id         = wp_update_post( $post_data, true );
		} else {
			$post_id = wp_insert_post( $post_data, true );
		}

		if ( is_wp_error( $post_id ) ) {
			$this->log( $post_id->get_error_message(), 'error' );
			return self::IMPORT_STATUS_FAILED;
		}

		// PMD ID meta.
		update_post_meta( $post_id, 'pmd_blog_id', (int) $post->id );

		// Featured image: files/blog/{id}.{extension}.
		if ( ! empty( $post->image_extension ) && ! has_post_thumbnail( $post_id ) ) {
			$attachment_id = $this->sideload_image( $this->get_pmd_url( 'files/blog/' . absint( $post->id ) . '.' . $post->image_extension ), $post_id );

			if ( $attachment_id ) {
				set_post_thumbnail( $post_id, $attachment_id );
			}
		}

		// Set categories.
		$categories = $this->get_post_categories( $post->id, 'blog', 'blog_id' );
		if ( ! empty( $categories ) ) {
			wp_set_post_categories( $post_id, $categories );
		}

		return $is_update ? self::IMPORT_STATUS_UPDATED : self::IMPORT_STATUS_SUCCESS;
	}

	/**
	 * Retrieves the categories for a given post.
	 *
	 * @since 2.0.2
	 *
	 * @param int    $post_id   The post ID.
	 * @param string $table     The PMD table name prefix for categories lookup.
	 * @param string $column_id The column name for the post ID in the lookup table.
	 * @return array The categories.
	 */
	public function get_post_categories( $post_id, $table = 'blog', $column_id = 'blog_id' ) {
		$db         = $this->get_db_connection();
		$cats_table = $this->db_prefix . $table . '_categories_lookup';
		$column_id  = preg_replace( '/[^a-z_]/', '', $column_id );

		$category_mapping = (array) $this->options_handler->get_option_no_cache( $table . '_category_mapping', array() );

		$categories_lookup = $db->get_col(
			$db->prepare(
				"SELECT `category_id` FROM `{$cats_table}` WHERE `{$column_id}` = %d ORDER BY `category_id`",
				(int) $post_id
			)
		);

		$categories = array();
		foreach ( (array) $categories_lookup as $category_id ) {
			if ( isset( $category_mapping[ (int) $category_id ] ) ) {
				$categories[] = (int) $category_mapping[ (int) $category_id ];
			}
		}

		return $categories;
	}

	/**
	 * Import pages from PMD to WordPress.
	 *
	 * @since 2.0.2
	 *
	 * @param array $task Import task details.
	 * @return array|false Result of the import operation or false if import is complete.
	 * @throws Exception If database connection fails.
	 */
	public function task_import_pages( array $task ) {
		$db = $this->require_db();

		wp_suspend_cache_addition( true );

		$offset      = isset( $task['offset'] ) ? absint( $task['offset'] ) : 0;
		$batch_size  = absint( $this->get_batch_size() );
		$counts      = $this->get_task_counts( $task );
		$pages_table = $this->db_prefix . 'pages';

		if ( ! isset( $task['total_pages'] ) ) {
			$task['total_pages'] = $this->count_table_rows( 'pages' );
		}

		$total_pages = absint( $task['total_pages'] );

		// Log the import start message only for the first batch.
		if ( 0 === $offset ) {
			/* translators: %d: number of pages */
			$this->log( sprintf( __( 'Starting pages import: %d pages found.', 'geodir-converter' ), $total_pages ) );
		}

		// Exit early if there are no pages to import.
		if ( 0 === $total_pages ) {
			$this->log( __( 'No pages available for import. Skipping...', 'geodir-converter' ) );
			return $this->next_task( $task );
		}

		$pages = $db->get_results(
			$db->prepare(
				"SELECT p.* FROM `{$pages_table}` p ORDER BY p.`id` ASC LIMIT %d, %d",
				$offset,
				$batch_size
			)
		);

		if ( empty( $pages ) ) {
			$this->log( __( 'No more pages to import. Process completed.', 'geodir-converter' ) );
			return $this->next_task( $task );
		}

		// PMD pages have no author; use the account linked to the PMD administrator (user #1).
		$author_id = $this->get_mapped_user_id( 1, 1 );

		foreach ( $pages as $page ) {
			$page_id   = ! $this->is_test_mode() ? $this->get_gd_post_id( $page->id, 'pmd_page_id' ) : false;
			$is_update = ! empty( $page_id );

			$page_data = array(
				'post_author'  => $author_id,
				'post_content' => ( $page->content ) ? $page->content : '',
				'post_title'   => ( $page->title ) ? $page->title : '',
				'post_name'    => ( $page->friendly_url ) ? $page->friendly_url : '',
				'post_type'    => 'page',
				'post_status'  => ! empty( $page->active ) ? 'publish' : 'draft',
			);

			// Handle test mode.
			if ( $this->is_test_mode() ) {
				$is_update ? ++$counts['updated'] : ++$counts['imported'];
				continue;
			}

			// Insert or update the post.
			if ( $is_update ) {
				$page_data['ID'] = absint( $page_id );
				$page_id         = wp_update_post( $page_data, true );
			} else {
				$page_id = wp_insert_post( $page_data, true );
			}

			if ( is_wp_error( $page_id ) ) {
				/* translators: %1$s: page title, %2$s: error message */
				$this->log( sprintf( __( 'Failed to import page %1$s: %2$s', 'geodir-converter' ), $page->title, $page_id->get_error_message() ), 'error' );
				++$counts['failed'];
				continue;
			}

			// PMD ID meta.
			update_post_meta( $page_id, 'pmd_page_id', (int) $page->id );

			$is_update ? ++$counts['updated'] : ++$counts['imported'];
		}

		return $this->finish_batch( $task, $counts, $offset, $batch_size, $total_pages, __( 'Pages', 'geodir-converter' ) );
	}

	/**
	 * Import comments from PMD to WordPress.
	 *
	 * @since 2.0.2
	 *
	 * @param array $task Import task details.
	 * @return array|false Result of the import operation or false if import is complete.
	 * @throws Exception If database connection fails.
	 */
	public function task_import_comments( array $task ) {
		$db = $this->require_db();

		wp_suspend_cache_addition( true );

		$offset         = isset( $task['offset'] ) ? absint( $task['offset'] ) : 0;
		$batch_size     = absint( $this->get_batch_size() );
		$counts         = $this->get_task_counts( $task );
		$comments_table = $this->db_prefix . 'blog_comments';
		$users_table    = $this->db_prefix . 'users';

		if ( ! isset( $task['total_comments'] ) ) {
			$task['total_comments'] = $this->count_table_rows( 'blog_comments' );
		}

		$total_comments = absint( $task['total_comments'] );

		// Log the import start message only for the first batch.
		if ( 0 === $offset ) {
			/* translators: %d: number of comments */
			$this->log( sprintf( __( 'Starting comments import: %d comments found.', 'geodir-converter' ), $total_comments ) );
		}

		// Exit early if there are no comments to import.
		if ( 0 === $total_comments ) {
			$this->log( __( 'No comments available for import. Skipping...', 'geodir-converter' ) );
			return $this->next_task( $task );
		}

		$comments = $db->get_results(
			$db->prepare(
				"SELECT
					c.`id` AS comment_id,
					c.`status`,
					c.`blog_id`,
					c.`user_id`,
					c.`date`,
					c.`comment`,
					c.`name` AS user_comment_name,
					c.`email` AS user_comment_email,
					c.`website`,
					u.`user_first_name`,
					u.`user_last_name`,
					u.`user_email`
				FROM `{$comments_table}` c
				LEFT JOIN `{$users_table}` u ON c.`user_id` = u.`id`
				ORDER BY c.`id` ASC
				LIMIT %d, %d",
				$offset,
				$batch_size
			)
		);

		if ( empty( $comments ) ) {
			$this->log( __( 'No more comments to import. Process completed.', 'geodir-converter' ) );
			return $this->next_task( $task );
		}

		foreach ( $comments as $comment ) {
			$post_id = (int) $this->get_gd_post_id( $comment->blog_id, 'pmd_blog_id' );

			if ( ! $post_id ) {
				/* translators: %1$d: comment ID, %2$d: blog post ID */
				$this->log( sprintf( __( 'Skipped comment %1$d: blog post #%2$d was not imported.', 'geodir-converter' ), $comment->comment_id, $comment->blog_id ), 'warning' );
				++$counts['skipped'];
				continue;
			}

			$legacy_agent   = 'geodir-converter' . md5( (string) $comment->user_email ) . $comment->comment_id;
			$existing_id    = $this->find_imported_comment( 'pmd_blog_comment_id', $comment->comment_id, $legacy_agent, $post_id );
			$comment_author = trim( $comment->user_first_name . ' ' . $comment->user_last_name );
			$date           = $this->normalize_date( $comment->date );

			$comment_data = array(
				'comment_post_ID'      => $post_id,
				'user_id'              => $this->get_mapped_user_id( $comment->user_id, 0 ),
				'comment_date'         => $date ? $date : current_time( 'mysql' ),
				'comment_date_gmt'     => $date ? get_gmt_from_date( $date ) : current_time( 'mysql', 1 ),
				'comment_content'      => (string) $comment->comment,
				'comment_author'       => '' !== $comment_author ? $comment_author : (string) $comment->user_comment_name,
				'comment_author_email' => ! empty( $comment->user_email ) ? $comment->user_email : (string) $comment->user_comment_email,
				'comment_author_url'   => (string) $comment->website,
				'comment_agent'        => $legacy_agent,
				'comment_approved'     => 'active' === $comment->status ? 1 : 0,
			);

			// Handle test mode.
			if ( $this->is_test_mode() ) {
				$existing_id ? ++$counts['updated'] : ++$counts['imported'];
				continue;
			}

			$comment_id = $this->save_imported_comment( $comment_data, $existing_id, 'pmd_blog_comment_id', $comment->comment_id );

			if ( ! $comment_id ) {
				/* translators: %d: comment ID */
				$this->log( sprintf( __( 'Failed to import comment %d.', 'geodir-converter' ), $comment->comment_id ), 'error' );
				++$counts['failed'];
				continue;
			}

			$existing_id ? ++$counts['updated'] : ++$counts['imported'];
		}

		return $this->finish_batch( $task, $counts, $offset, $batch_size, $total_comments, __( 'Comments', 'geodir-converter' ) );
	}

	/**
	 * Find a comment previously imported from a PMD row.
	 *
	 * Comments imported before 2.3.0 are matched by their comment agent on the
	 * same post. Reviews and blog comments used the same agent format, so the
	 * post keeps a review from matching a blog comment with the same ID.
	 *
	 * @since 2.3.0
	 *
	 * @param string $meta_key     Comment meta key holding the PMD ID.
	 * @param int    $source_id    The PMD ID.
	 * @param string $legacy_agent Optional. Comment agent used by earlier imports.
	 * @param int    $post_id      Optional. Post the legacy comment belongs to.
	 * @return int The comment ID, or 0.
	 */
	private function find_imported_comment( $meta_key, $source_id, $legacy_agent = '', $post_id = 0 ) {
		global $wpdb;

		$comment_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT comment_id FROM {$wpdb->commentmeta} WHERE meta_key = %s AND meta_value = %s LIMIT 1",
				$meta_key,
				(string) (int) $source_id
			)
		);

		if ( ! $comment_id && '' !== $legacy_agent && $post_id ) {
			$comment_id = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT comment_ID FROM {$wpdb->comments} WHERE comment_agent = %s AND comment_post_ID = %d LIMIT 1",
					$legacy_agent,
					(int) $post_id
				)
			);
		}

		return $comment_id ? (int) $comment_id : 0;
	}

	/**
	 * Insert or update an imported comment.
	 *
	 * @since 2.3.0
	 *
	 * @param array  $comment_data Comment data.
	 * @param int    $existing_id  Existing comment ID, or 0.
	 * @param string $meta_key     Comment meta key holding the PMD ID.
	 * @param int    $source_id    The PMD ID.
	 * @return int The comment ID, or 0 on failure.
	 */
	private function save_imported_comment( array $comment_data, $existing_id, $meta_key, $source_id ) {
		if ( $existing_id ) {
			$comment_data['comment_ID'] = (int) $existing_id;

			$result = wp_update_comment( $comment_data, true );

			if ( false === $result || is_wp_error( $result ) ) {
				return 0;
			}

			$comment_id = (int) $existing_id;
		} else {
			$comment_id = (int) wp_insert_comment( $comment_data );

			if ( ! $comment_id ) {
				return 0;
			}
		}

		update_comment_meta( $comment_id, $meta_key, (int) $source_id );

		return $comment_id;
	}

	/**
	 * Import discounts from PMD to GeoDirectory.
	 *
	 * @since 2.0.2
	 *
	 * @param array $task Import task details.
	 * @return array|false Result of the import operation or false if import is complete.
	 * @throws Exception If database connection fails.
	 */
	public function task_import_discounts( array $task ) {
		if ( ! class_exists( 'WPInv_Discount' ) ) {
			$this->log( __( 'Invoicing plugin is not active. Skipping discounts...', 'geodir-converter' ) );
			return $this->next_task( $task );
		}

		$db = $this->require_db();

		wp_suspend_cache_addition( true );

		$offset          = isset( $task['offset'] ) ? absint( $task['offset'] ) : 0;
		$batch_size      = absint( $this->get_batch_size() );
		$counts          = $this->get_task_counts( $task );
		$discounts_table = $this->db_prefix . 'discount_codes';

		if ( ! isset( $task['total_discounts'] ) ) {
			$task['total_discounts'] = $this->count_table_rows( 'discount_codes' );
		}

		$total_discounts = absint( $task['total_discounts'] );

		// Log the import start message only for the first batch.
		if ( 0 === $offset ) {
			/* translators: %d: number of discounts */
			$this->log( sprintf( __( 'Starting discounts import: %d discounts found.', 'geodir-converter' ), $total_discounts ) );
		}

		// Exit early if there are no discounts to import.
		if ( 0 === $total_discounts ) {
			$this->log( __( 'No discounts available for import. Skipping...', 'geodir-converter' ) );
			return $this->next_task( $task );
		}

		$discounts = $db->get_results(
			$db->prepare(
				"SELECT d.* FROM `{$discounts_table}` d ORDER BY d.`id` ASC LIMIT %d, %d",
				$offset,
				$batch_size
			)
		);

		if ( empty( $discounts ) ) {
			$this->log( __( 'No more discounts to import. Process completed.', 'geodir-converter' ) );
			return $this->next_task( $task );
		}

		foreach ( $discounts as $discount ) {
			$wpinv_discount = new WPInv_Discount( $discount->code );
			$is_update      = $wpinv_discount->exists();

			$wpinv_discount->set_props(
				array(
					'name'           => $discount->title,
					'code'           => $discount->code,
					'amount'         => $discount->value,
					'status'         => 'publish',
					'start'          => $this->normalize_date( $discount->date_start ),
					'expiration'     => $this->normalize_date( $discount->date_expire ),
					'is_single_use'  => 1 === (int) $discount->user_used_limit,
					'type'           => ( 'percentage' === $discount->discount_type ) ? 'percent' : 'flat',
					'is_recurring'   => 'recurring' === $discount->type,
					'items'          => $this->map_pricing_ids_to_items( $discount->pricing_ids ),
					'required_items' => $this->map_pricing_ids_to_items( $discount->pricing_ids_required ),
					'max_uses'       => (int) $discount->used_limit,
					'uses'           => (int) $discount->used,
				)
			);

			// Handle test mode.
			if ( $this->is_test_mode() ) {
				$is_update ? ++$counts['updated'] : ++$counts['imported'];
				continue;
			}

			$discount_id = $wpinv_discount->save();

			if ( ! $discount_id || is_wp_error( $discount_id ) ) {
				/* translators: %s: discount title */
				$this->log( sprintf( __( 'Failed to import discount %s.', 'geodir-converter' ), $discount->title ), 'error' );
				++$counts['failed'];
				continue;
			}

			$is_update ? ++$counts['updated'] : ++$counts['imported'];
		}

		return $this->finish_batch( $task, $counts, $offset, $batch_size, $total_discounts, __( 'Discounts', 'geodir-converter' ) );
	}

	/**
	 * Map a comma-separated list of PMD pricing IDs to GetPaid item IDs.
	 *
	 * @since 2.3.0
	 *
	 * @param string $pricing_ids Comma-separated PMD pricing IDs.
	 * @return array GetPaid item IDs.
	 */
	private function map_pricing_ids_to_items( $pricing_ids ) {
		$packages_mapping = $this->get_packages_mapping();
		$items            = array();

		if ( ! function_exists( 'wpinv_get_item_by' ) ) {
			return $items;
		}

		foreach ( wp_parse_id_list( (string) $pricing_ids ) as $pricing_id ) {
			if ( empty( $packages_mapping[ $pricing_id ] ) ) {
				continue;
			}

			$wpinv_item = wpinv_get_item_by( 'custom_id', (int) $packages_mapping[ $pricing_id ], 'package' );

			if ( $wpinv_item && $wpinv_item->exists() ) {
				$items[] = (int) $wpinv_item->get_id();
			}
		}

		return array_values( array_unique( $items ) );
	}

	/**
	 * Import invoices from PMD to GeoDirectory.
	 *
	 * @since 2.0.2
	 *
	 * @param array $task Import task details.
	 * @return array|false Result of the import operation or false if import is complete.
	 * @throws Exception If database connection fails.
	 */
	public function task_import_invoices( array $task ) {
		if ( ! class_exists( 'WPInv_Invoice' ) ) {
			$this->log( __( 'Invoices plugin is not active. Skipping invoices...', 'geodir-converter' ) );
			return $this->next_task( $task );
		}

		$db = $this->require_db();

		wp_suspend_cache_addition( true );

		$offset         = isset( $task['offset'] ) ? absint( $task['offset'] ) : 0;
		$batch_size     = absint( $this->get_batch_size() );
		$counts         = $this->get_task_counts( $task );
		$invoices_table = $this->db_prefix . 'invoices';

		if ( ! isset( $task['total_invoices'] ) ) {
			$task['total_invoices'] = $this->count_table_rows( 'invoices' );
		}

		$total_invoices = absint( $task['total_invoices'] );

		// Log the import start message only for the first batch.
		if ( 0 === $offset ) {
			/* translators: %d: number of invoices */
			$this->log( sprintf( __( 'Starting invoices import: %d invoices found.', 'geodir-converter' ), $total_invoices ) );
		}

		// Exit early if there are no invoices to import.
		if ( 0 === $total_invoices ) {
			$this->log( __( 'No invoices available for import. Skipping...', 'geodir-converter' ) );
			return $this->next_task( $task );
		}

		$invoices = $db->get_results(
			$db->prepare(
				"SELECT i.*, o.`pricing_id` AS order_pricing_id
				FROM `{$invoices_table}` i
				LEFT JOIN `{$this->db_prefix}orders` o ON i.`order_id` = o.`id`
				ORDER BY i.`id` ASC
				LIMIT %d, %d",
				$offset,
				$batch_size
			)
		);

		if ( empty( $invoices ) ) {
			$this->log( __( 'No more invoices to import. Process completed.', 'geodir-converter' ) );
			return $this->next_task( $task );
		}

		$packages_mapping = $this->get_packages_mapping();

		// Map PMD status to GetPaid status.
		$status_map = array(
			'unpaid'   => 'wpi-pending',
			'canceled' => 'wpi-cancelled',
			'paid'     => 'publish',
		);

		foreach ( $invoices as $invoice ) {
			$invoice_id = ! $this->is_test_mode() ? $this->get_gd_post_id( $invoice->id, 'pmd_invoice_id' ) : false;
			$is_update  = ! empty( $invoice_id );
			$user_id    = $this->get_mapped_user_id( $invoice->user_id, 0 );
			$wp_user    = $user_id ? get_userdata( $user_id ) : false;
			$user_meta  = $user_id ? get_user_meta( $user_id ) : array();
			$status     = isset( $status_map[ $invoice->status ] ) ? $status_map[ $invoice->status ] : 'wpi-pending';

			$user_address = isset( $user_meta['user_address1'][0] ) ? $user_meta['user_address1'][0] : '';
			if ( ! empty( $user_meta['user_address2'][0] ) ) {
				$user_address .= ' ' . $user_meta['user_address2'][0];
			}

			$taxes = array();
			if ( (float) $invoice->tax > 0 ) {
				$tax_name           = __( 'Tax', 'geodir-converter' );
				$taxes[ $tax_name ] = array(
					'name'          => $tax_name,
					'initial_tax'   => (float) $invoice->tax,
					'recurring_tax' => 0,
				);
			}

			if ( (float) $invoice->tax2 > 0 ) {
				$tax_name           = __( 'Tax 2', 'geodir-converter' );
				$taxes[ $tax_name ] = array(
					'name'          => $tax_name,
					'initial_tax'   => (float) $invoice->tax2,
					'recurring_tax' => 0,
				);
			}

			$discounts = array();
			if ( (float) $invoice->discount_code_value > 0 ) {
				$discounts['discount_code'] = array(
					'name'               => 'discount_code',
					'discount_code'      => (string) $invoice->discount_code,
					'initial_discount'   => (float) $invoice->discount_code_value,
					'recurring_discount' => 0,
				);
			}

			$wpi_invoice = new WPInv_Invoice( $is_update ? (int) $invoice_id : 0 );
			$wpi_invoice->set_props(
				array(
					// Basic info.
					'description'    => (string) $invoice->description,
					'status'         => $status,
					'created_via'    => 'geodir-converter',
					'date_created'   => $this->normalize_date( $invoice->date ),

					// Payment info.
					'gateway'        => strtolower( (string) $invoice->gateway_id ),
					'discount_code'  => (string) $invoice->discount_code,
					'discounts'      => $discounts,
					'taxes'          => $taxes,
					'total'          => (float) $invoice->total,
					'subtotal'       => (float) $invoice->subtotal,
					'due_date'       => $this->normalize_date( $invoice->date_due ),
					'date_completed' => $this->normalize_date( $invoice->date_paid ),

					// Billing details.
					'user_id'        => $user_id,
					'email'          => $wp_user ? $wp_user->user_email : '',
					'first_name'     => isset( $user_meta['first_name'][0] ) ? $user_meta['first_name'][0] : '',
					'last_name'      => isset( $user_meta['last_name'][0] ) ? $user_meta['last_name'][0] : '',
					'address'        => $user_address,
					'company'        => isset( $user_meta['user_organization'][0] ) ? $user_meta['user_organization'][0] : '',
					'zip'            => isset( $user_meta['user_zip'][0] ) ? $user_meta['user_zip'][0] : '',
					'state'          => isset( $user_meta['user_state'][0] ) ? $user_meta['user_state'][0] : '',
					'city'           => isset( $user_meta['user_city'][0] ) ? $user_meta['user_city'][0] : '',
					'country'        => isset( $user_meta['user_country'][0] ) ? $this->get_invoice_country_code( $user_meta['user_country'][0] ) : '',
					'phone'          => isset( $user_meta['user_phone'][0] ) ? $user_meta['user_phone'][0] : '',
				)
			);

			// The invoiced package comes from the invoice's order (orders.pricing_id).
			$pricing_id = (int) $invoice->order_pricing_id;
			$wpinv_item = ( $pricing_id && ! empty( $packages_mapping[ $pricing_id ] ) && function_exists( 'wpinv_get_item_by' ) )
				? wpinv_get_item_by( 'custom_id', (int) $packages_mapping[ $pricing_id ], 'package' )
				: false;

			if ( $wpinv_item && class_exists( 'GetPaid_Form_Item' ) ) {
				$item = new GetPaid_Form_Item( $wpinv_item->get_id() );
				$item->set_name( $wpinv_item->get_name() );
				$item->set_description( $wpinv_item->get_description() );
				$item->set_price( (float) $invoice->subtotal );
				$item->set_quantity( 1 );
				$wpi_invoice->add_item( $item );
			}

			// Handle test mode.
			if ( $this->is_test_mode() ) {
				$is_update ? ++$counts['updated'] : ++$counts['imported'];
				continue;
			}

			$wpi_invoice_id = $wpi_invoice->save();

			if ( ! $wpi_invoice_id || is_wp_error( $wpi_invoice_id ) ) {
				/* translators: %d: invoice ID */
				$this->log( sprintf( __( 'Failed to import invoice %d.', 'geodir-converter' ), $invoice->id ), 'error' );
				++$counts['failed'];
				continue;
			}

			// Update post meta.
			update_post_meta( $wpi_invoice_id, 'pmd_invoice_id', (int) $invoice->id );

			$is_update ? ++$counts['updated'] : ++$counts['imported'];
		}

		return $this->finish_batch( $task, $counts, $offset, $batch_size, $total_invoices, __( 'Invoices', 'geodir-converter' ) );
	}

	/**
	 * Convert a PMD country name to the ISO code GetPaid stores.
	 *
	 * GetPaid customers store a 2-letter country code. A full name makes the customer
	 * insert fail, and GetPaid's getpaid_save_invoice_user_address() then re-saves the
	 * invoice without end until PHP crashes.
	 *
	 * @since 2.3.0
	 *
	 * @param string $country Country name or code.
	 * @return string ISO 3166-1 alpha-2 code, or '' to let GetPaid use its default country.
	 */
	private function get_invoice_country_code( $country ) {
		$country = trim( (string) $country );

		if ( '' === $country || ! function_exists( 'wpinv_get_country_list' ) ) {
			return '';
		}

		$countries = wpinv_get_country_list();

		if ( 2 === strlen( $country ) && isset( $countries[ strtoupper( $country ) ] ) ) {
			return strtoupper( $country );
		}

		foreach ( $countries as $code => $name ) {
			if ( 0 === strcasecmp( $name, $country ) ) {
				return $code;
			}
		}

		return '';
	}

	/**
	 * Build a URL to a file on the PMD site.
	 *
	 * @since 2.3.0
	 *
	 * @param string $path Path relative to the PMD root.
	 * @return string The URL.
	 */
	private function get_pmd_url( $path ) {
		if ( '' === $this->url ) {
			$this->url = (string) $this->get_import_setting( 'site_url', '' );
		}

		return trailingslashit( $this->url ) . ltrim( $path, '/' );
	}

	/**
	 * Normalize a PMD date, treating MySQL zero dates as empty.
	 *
	 * @since 2.3.0
	 *
	 * @param string $value  The date.
	 * @param string $format Output format.
	 * @return string The formatted date, or an empty string.
	 */
	private function normalize_date( $value, $format = 'Y-m-d H:i:s' ) {
		$value = trim( (string) $value );

		if ( '' === $value || 0 === strpos( $value, '0000-00-00' ) ) {
			return '';
		}

		$timestamp = strtotime( $value );

		if ( false === $timestamp || $timestamp <= 0 ) {
			return '';
		}

		return gmdate( $format, $timestamp );
	}

	/**
	 * Whether a latitude/longitude pair holds real coordinates.
	 *
	 * PMD stores missing coordinates as 0.0000000000.
	 *
	 * @since 2.3.0
	 *
	 * @param mixed $latitude  Latitude.
	 * @param mixed $longitude Longitude.
	 * @return bool True if set.
	 */
	private function has_coordinates( $latitude, $longitude ) {
		if ( ! is_numeric( $latitude ) || ! is_numeric( $longitude ) ) {
			return false;
		}

		return 0.0 !== (float) $latitude || 0.0 !== (float) $longitude;
	}

	/**
	 * Get the WP user mapped to a PMD user.
	 *
	 * @since 2.3.0
	 *
	 * @param int $pmd_user_id The PMD user ID.
	 * @param int $default     Value returned when the user is not mapped.
	 * @return int The WP user ID.
	 */
	private function get_mapped_user_id( $pmd_user_id, $default = 0 ) {
		if ( null === $this->user_mapping_cache ) {
			$this->user_mapping_cache = (array) $this->options_handler->get_option_no_cache( 'user_mapping', array() );
		}

		$pmd_user_id = (int) $pmd_user_id;

		return ( $pmd_user_id && isset( $this->user_mapping_cache[ $pmd_user_id ] ) ) ? (int) $this->user_mapping_cache[ $pmd_user_id ] : (int) $default;
	}

	/**
	 * Get the GD posts imported from PMD IDs (listings or events).
	 *
	 * @since 2.3.0
	 *
	 * @param array  $pmd_ids   PMD IDs.
	 * @param string $post_type The GD post type.
	 * @return array GD post ID keyed by PMD ID.
	 */
	private function get_gd_ids_by_pmd_ids( array $pmd_ids, $post_type ) {
		global $wpdb, $plugin_prefix;

		$pmd_ids = array_values( array_unique( array_filter( array_map( 'absint', $pmd_ids ) ) ) );

		if ( empty( $pmd_ids ) ) {
			return array();
		}

		if ( ! isset( $this->gd_ids_cache[ $post_type ] ) ) {
			$this->gd_ids_cache[ $post_type ] = array();
		}

		$result  = array();
		$missing = array();

		foreach ( $pmd_ids as $pmd_id ) {
			if ( isset( $this->gd_ids_cache[ $post_type ][ $pmd_id ] ) ) {
				$result[ $pmd_id ] = $this->gd_ids_cache[ $post_type ][ $pmd_id ];
			} else {
				$missing[] = $pmd_id;
			}
		}

		$table = $plugin_prefix . $post_type . '_detail';

		if ( empty( $missing ) || ! geodir_column_exist( $table, 'pmd_id' ) ) {
			return $result;
		}

		$placeholders = implode( ',', array_fill( 0, count( $missing ), '%d' ) );
		$rows         = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT pd.post_id, pd.pmd_id FROM {$table} pd INNER JOIN {$wpdb->posts} p ON p.ID = pd.post_id WHERE p.post_type = %s AND pd.pmd_id IN ({$placeholders})", // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
				array_merge( array( $post_type ), $missing )
			)
		);

		foreach ( (array) $rows as $row ) {
			$this->gd_ids_cache[ $post_type ][ (int) $row->pmd_id ] = (int) $row->post_id;
			$result[ (int) $row->pmd_id ]                           = (int) $row->post_id;
		}

		return $result;
	}

	/**
	 * Get the GD post imported from a single PMD ID.
	 *
	 * @since 2.3.0
	 *
	 * @param int    $pmd_id    The PMD ID.
	 * @param string $post_type The GD post type.
	 * @return int The GD post ID, or 0.
	 */
	private function get_gd_id_by_pmd_id( $pmd_id, $post_type ) {
		$ids = $this->get_gd_ids_by_pmd_ids( array( $pmd_id ), $post_type );

		return isset( $ids[ (int) $pmd_id ] ) ? (int) $ids[ (int) $pmd_id ] : 0;
	}

	/**
	 * Load the WordPress admin media functions used to sideload files.
	 *
	 * The import can run from WP-Cron, where these are not loaded.
	 *
	 * @since 2.3.0
	 *
	 * @return void
	 */
	private function load_media_includes() {
		if ( ! function_exists( 'wp_handle_sideload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}
	}

	/**
	 * Download an image into the media library.
	 *
	 * @since 2.3.0
	 *
	 * @param string $url       The image URL.
	 * @param int    $parent_id Post to attach the image to.
	 * @return int The attachment ID, or 0 on failure.
	 */
	private function sideload_image( $url, $parent_id = 0 ) {
		$this->load_media_includes();

		$attachment = $this->import_attachment( $url );

		if ( empty( $attachment['id'] ) ) {
			return 0;
		}

		if ( $parent_id ) {
			wp_update_post(
				array(
					'ID'          => (int) $attachment['id'],
					'post_parent' => (int) $parent_id,
				)
			);
		}

		return (int) $attachment['id'];
	}

	/**
	 * Handle authentication for imported PMD users.
	 *
	 * PMD hashes passwords as hash( $algorithm, $password . $salt ), where the
	 * algorithm is stored per user and falls back to md5
	 * (Authentication::encryptPassword()). On a successful match the password is
	 * upgraded to a WordPress hash.
	 *
	 * @since 2.0.2
	 *
	 * @param WP_User|WP_Error|null $user     WP_User object if the user is authenticated.
	 * @param string                $password The password in plain text.
	 * @return WP_User|WP_Error|null The authenticated user or the original response.
	 */
	public function handle_user_login( $user, $password ) {
		// Return early if no valid user object.
		if ( ! $user instanceof WP_User ) {
			return $user;
		}

		$stored_hash = (string) get_user_meta( $user->ID, 'pmd_password', true );
		$hash_type   = (string) get_user_meta( $user->ID, 'pmd_password_hash', true );

		// Return early if not a PMD user.
		if ( '' === $stored_hash && '' === $hash_type ) {
			return $user;
		}

		// Imports before 2.3.0 stored the PMD hash as the user password.
		if ( '' === $stored_hash ) {
			$stored_hash = (string) $user->user_pass;
		}

		$salt      = (string) get_user_meta( $user->ID, 'pmd_password_salt', true );
		$algorithm = in_array( $hash_type, array( 'md5', 'sha1', 'sha256' ), true ) ? $hash_type : 'md5';

		// If password is valid, upgrade to WordPress password system.
		if ( hash_equals( $stored_hash, hash( $algorithm, $password . $salt ) ) ) {
			wp_set_password( $password, $user->ID );
			$this->delete_pmd_password( $user->ID );

			// WordPress checks the password against this object after the filter.
			$refreshed = get_user_by( 'id', $user->ID );

			return $refreshed ? $refreshed : $user;
		}

		return $user;
	}

	/**
	 * Forget the PMD password when a new WordPress password is set.
	 *
	 * @since 2.3.0
	 *
	 * @param string $password The new password.
	 * @param int    $user_id  The user ID.
	 * @return void
	 */
	public function clear_pmd_password_on_set( $password, $user_id ) {
		$this->delete_pmd_password( $user_id );
	}

	/**
	 * Forget the PMD password when a profile update changes the password.
	 *
	 * @since 2.3.0
	 *
	 * @param int     $user_id       The user ID.
	 * @param WP_User $old_user_data The user before the update.
	 * @return void
	 */
	public function clear_pmd_password_on_update( $user_id, $old_user_data ) {
		$user = get_userdata( $user_id );

		if ( $user && $old_user_data instanceof WP_User && $user->user_pass !== $old_user_data->user_pass ) {
			$this->delete_pmd_password( $user_id );
		}
	}

	/**
	 * Delete the stored PMD password of a user.
	 *
	 * @since 2.3.0
	 *
	 * @param int $user_id The user ID.
	 * @return void
	 */
	private function delete_pmd_password( $user_id ) {
		delete_user_meta( $user_id, 'pmd_password' );
		delete_user_meta( $user_id, 'pmd_password_hash' );
		delete_user_meta( $user_id, 'pmd_password_salt' );
	}
}
