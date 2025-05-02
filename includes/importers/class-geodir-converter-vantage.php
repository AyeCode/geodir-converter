<?php
/**
 * Vantage Converter Class.
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
use GeoDir_Media;
use WPInv_Invoice;
use GetPaid_Form_Item;
use GeoDir_Pricing_Package;
use GeoDir_Converter\Abstracts\GeoDir_Converter_Importer;

defined( 'ABSPATH' ) || exit;

/**
 * Main converter class for importing from Vantage.
 *
 * @since 2.0.2
 */
class GeoDir_Converter_Vantage extends GeoDir_Converter_Importer {
	/**
	 * Action identifier for importing payments.
	 *
	 * @var string
	 */
	const ACTION_IMPORT_PAYMENTS = 'import_payments';

	/**
	 * Post type identifier for listings.
	 *
	 * @var string
	 */
	private const POST_TYPE_LISTING = 'listing';

	/**
	 * Post type identifier for plans.
	 *
	 * @var string
	 */
	private const POST_TYPE_PLAN = 'listing-pricing-plan';

	/**
	 * Post type identifier for payments.
	 *
	 * @var string
	 */
	private const POST_TYPE_TRANSACTION = 'transaction';

	/**
	 * Taxonomy identifier for listing categories.
	 *
	 * @var string
	 */
	private const TAX_LISTING_CATEGORY = 'listing_category';

	/**
	 * Taxonomy identifier for listing tags.
	 *
	 * @var string
	 */
	private const TAX_LISTING_TAG = 'listing_tag';

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
	protected $importer_id = 'vantage';

	/**
	 * The import listing status ID.
	 *
	 * @var array
	 */
	protected $post_statuses = array( 'publish', 'expired', 'draft', 'deleted', 'pending' );

	/**
	 * Payment statuses.
	 *
	 * @var array
	 */
	protected $payment_statuses = array(
		'tr_pending'   => 'wpi-pending',
		'tr_failed'    => 'wpi-failed',
		'tr_completed' => 'publish',
		'tr_activated' => 'publish',
	);

	/**
	 * Initialize hooks.
	 *
	 * @since 1.0.0
	 */
	protected function init() {
		// Skip invoice emails for imported invoices.
		add_filter( 'getpaid_skip_invoice_email', array( $this, 'skip_invoice_email' ), 10, 3 );
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
		return __( 'Vantage', 'geodir-converter' );
	}

	/**
	 * Get importer description.
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'Import listings, events, users and invoices from your Vantage installation.', 'geodir-converter' );
	}

	/**
	 * Get importer icon URL.
	 *
	 * @return string
	 */
	public function get_icon() {
		return GEODIR_CONVERTER_PLUGIN_URL . 'assets/images/vantage.png';
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
		?>
		<form class="geodir-converter-settings-form" method="post">
			<h6 class="fs-base"><?php esc_html_e( 'Vantage Importer Settings', 'geodir-converter' ); ?></h6>
			
			<?php
			if ( ! defined( 'WPINV_VERSION' ) ) {
				$this->render_plugin_notice(
					esc_html__( 'Invoicing', 'geodir-converter' ),
					'payments',
					esc_url( 'https://wordpress.org/plugins/invoicing' )
				);
			}

			if ( ! defined( 'GEODIR_PRICING_VERSION' ) ) {
				$this->render_plugin_notice(
					esc_html__( 'GeoDirectory Pricing Manager', 'geodir-converter' ),
					'plans',
					esc_url( 'https://wpgeodirectory.com/downloads/pricing-manager/' )
				);
			}

			$this->display_post_type_select();
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

		$settings['test_mode']    = ( isset( $settings['test_mode'] ) && ! empty( $settings['test_mode'] ) && $settings['test_mode'] != 'no' ) ? 'yes' : 'no';
		$settings['gd_post_type'] = isset( $settings['gd_post_type'] ) && ! empty( $settings['gd_post_type'] ) ? sanitize_text_field( $settings['gd_post_type'] ) : 'gd_place';

		if ( ! in_array( $settings['gd_post_type'], $post_types, true ) ) {
			$errors[] = esc_html__( 'The selected post type is invalid. Please choose a valid post type.', 'geodir-converter' );
		}

		if ( ! empty( $errors ) ) {
			return new WP_Error( 'invalid_import_settings', implode( '<br>', $errors ) );
		}

		return $settings;
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
			self::ACTION_IMPORT_TAGS,
			self::ACTION_IMPORT_PACKAGES,
			self::ACTION_IMPORT_FIELDS,
			self::ACTION_PARSE_LISTINGS,
			self::ACTION_IMPORT_PAYMENTS,
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
	 */
	public function set_import_total() {
		global $wpdb;

		$total_items = 0;

		// Count categories.
		$categories   = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s", self::TAX_LISTING_CATEGORY ) );
		$total_items += is_wp_error( $categories ) ? 0 : $categories;

		// Count tags.
		$tags         = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s", self::TAX_LISTING_TAG ) );
		$total_items += is_wp_error( $tags ) ? 0 : $tags;

		// Count plans.
		$total_items += (int) $this->count_plans();

		// Count fields.
		$fields       = $this->get_fields();
		$total_items += (int) count( $fields );

		// Count listings.
		$total_items += (int) $this->count_listings();

		// Count payments.
		$total_items += (int) $this->count_payments();

		$this->increase_imports_total( $total_items );
	}

	/**
	 * Import categories from Listify to GeoDirectory.
	 *
	 * @since 2.0.2
	 * @param array $task Import task.
	 *
	 * @return array Result of the import operation.
	 */
	public function task_import_categories( $task ) {
		global $wpdb;

		// Set total number of items to import.
		$this->set_import_total();

		// Log import started.
		$this->log( esc_html__( 'Categories: Import started.', 'geodir-converter' ) );

		if ( 0 === (int) wp_count_terms( self::TAX_LISTING_CATEGORY ) ) {
			$this->log( esc_html__( 'Categories: No items to import.', 'geodir-converter' ), 'warning' );
			return $this->next_task( $task );
		}

		$post_type = $this->get_import_post_type();

		$categories = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT t.*, tt.*
                FROM {$wpdb->terms} AS t
                INNER JOIN {$wpdb->term_taxonomy} AS tt ON t.term_id = tt.term_id
                WHERE tt.taxonomy = %s",
				self::TAX_LISTING_CATEGORY
			)
		);

		if ( empty( $categories ) || is_wp_error( $categories ) ) {
			$this->log( esc_html__( 'Categories: No items to import.', 'geodir-converter' ), 'warning' );
			return $this->next_task( $task );
		}

		$result = $this->import_taxonomy_terms( $categories, $post_type . 'category', 'ct_cat_top_desc' );

		$this->increase_succeed_imports( (int) $result['imported'] );
		$this->increase_failed_imports( (int) $result['failed'] );

		$this->log(
			sprintf(
				/* translators: %1$d: number of imported terms, %2$d: number of failed imports */
				esc_html__( 'Categories: Import completed. %1$d imported, %2$d failed.', 'geodir-converter' ),
				$result['imported'],
				$result['failed']
			),
			'success'
		);

		return $this->next_task( $task );
	}

	/**
	 * Import tags.
	 *
	 * @param array $task Task details.
	 * @return array Updated task details.
	 */
	public function task_import_tags( array $task ) {
		global $wpdb;

		$this->log( esc_html__( 'Tags: Import started.', 'geodir-converter' ) );

		$post_type = $this->get_import_post_type();

		$tags = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT t.*, tt.*
                FROM {$wpdb->terms} AS t
                INNER JOIN {$wpdb->term_taxonomy} AS tt ON t.term_id = tt.term_id
                WHERE tt.taxonomy = %s",
				self::TAX_LISTING_TAG
			)
		);

		if ( empty( $tags ) || is_wp_error( $tags ) ) {
			$this->log( esc_html__( 'Tags: No items to import.', 'geodir-converter' ), 'notice' );
			return $this->next_task( $task );
		}

		$result = $this->import_taxonomy_terms( $tags, $post_type . '_tags', 'ct_cat_top_desc' );

		$this->increase_succeed_imports( (int) $result['imported'] );
		$this->increase_failed_imports( (int) $result['failed'] );

		$this->log(
			sprintf(
			/* translators: %1$d: number of imported terms, %2$d: number of failed imports */
				esc_html__( 'Tags: Import completed. %1$d imported, %2$d failed.', 'geodir-converter' ),
				$result['imported'],
				$result['failed']
			),
			'success'
		);

		return $this->next_task( $task );
	}

	/**
	 * Import fields from Listify to GeoDirectory.
	 *
	 * @since 2.0.2
	 * @param array $task Task details.
	 * @return array Result of the import operation.
	 */
	public function task_import_fields( array $task ) {
		global $plugin_prefix;

		$this->log( esc_html__( 'Importing fields...', 'geodir-converter' ) );

		$imported  = isset( $task['imported'] ) ? absint( $task['imported'] ) : 0;
		$failed    = isset( $task['failed'] ) ? absint( $task['failed'] ) : 0;
		$skipped   = isset( $task['skipped'] ) ? absint( $task['skipped'] ) : 0;
		$updated   = isset( $task['updated'] ) ? absint( $task['updated'] ) : 0;
		$fields    = $this->get_fields();
		$post_type = $this->get_import_post_type();

		if ( empty( $fields ) ) {
			$this->log( esc_html__( 'No fields found for import.', 'geodir-converter' ), 'warning' );
			return $this->next_task( $task );
		}

		$table       = $plugin_prefix . $post_type . '_detail';
		$package_ids = $this->get_package_ids( $post_type );

		// Fields to skip.
		$skip_keys = array( 'tax_input[listing_category]', 'tax_input[listing_tag]', '_app_media' );

		foreach ( $fields as $field ) {
			// Skip fields that shouldn't be imported.
			if ( in_array( $field['id'], $skip_keys, true ) ) {
				++$skipped;
				$this->log( sprintf( __( 'Skipped field: %s', 'geodir-converter' ), $field['props']['label'] ), 'warning' );
				continue;
			}

			$gd_field = $this->prepare_single_field( $field['id'], $field, $post_type, $package_ids );

			// Skip fields that shouldn't be imported.
			if ( $this->should_skip_field( $gd_field['htmlvar_name'] ) ) {
				++$skipped;
				$this->log( sprintf( __( 'Skipped field: %s', 'geodir-converter' ), $field['props']['label'] ), 'warning' );
				continue;
			}

			$column_exists = geodir_column_exist( $table, $gd_field['htmlvar_name'] );

			if ( $this->is_test_mode() ) {
				$column_exists ? $updated++ : $imported++;
				continue;
			}

			if ( $gd_field && geodir_custom_field_save( $gd_field ) ) {
				$column_exists ? ++$updated : ++$imported;
			} else {
				++$failed;
				$this->log( sprintf( __( 'Failed to import field: %s', 'geodir-converter' ), $field['props']['label'] ), 'error' );
			}
		}

		$this->increase_succeed_imports( $imported + $updated );
		$this->increase_skipped_imports( $skipped );
		$this->increase_failed_imports( $failed );

		$this->log(
			sprintf(
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
	 * Import packages.
	 *
	 * @param array $task Import task details.
	 * @return array Updated task with the next action.
	 */
	public function task_import_packages( array $task ) {
		global $wpdb;

		// Abort early if the payment manager plugin is not installed.
		if ( ! class_exists( 'GeoDir_Pricing_Package' ) ) {
			$this->log( __( 'Payment manager plugin is not active. Skipping plans...', 'geodir-converter' ) );
			return $this->next_task( $task );
		}

		// Set Pricing Manager cart option if WPINV is active.
		if ( defined( 'WPINV_VERSION' ) ) {
			geodir_update_option( 'pm_cart', 'invoicing' );
		}

		$offset      = isset( $task['offset'] ) ? (int) $task['offset'] : 0;
		$imported    = isset( $task['imported'] ) ? (int) $task['imported'] : 0;
		$failed      = isset( $task['failed'] ) ? (int) $task['failed'] : 0;
		$skipped     = isset( $task['skipped'] ) ? (int) $task['skipped'] : 0;
		$total_plans = isset( $task['total_plans'] ) ? (int) $task['total_plans'] : 0;
		$batch_size  = (int) $this->get_batch_size();
		$post_type   = $this->get_import_post_type();

		// Determine total listings count if not set.
		if ( ! isset( $task['total_plans'] ) ) {
			$total_plans         = $this->count_plans();
			$task['total_plans'] = $total_plans;
		}

		// Log the import start message only for the first batch.
		if ( 0 === $offset ) {
			$this->log( __( 'Starting plans import process...', 'geodir-converter' ) );
		}

		// Exit early if there are no plans to import.
		if ( 0 === $total_plans ) {
			$this->log( __( 'No plans found for import. Skipping process.', 'geodir-converter' ) );
			return $this->next_task( $task );
		}

		wp_suspend_cache_addition( true );

		$plans = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DISTINCT ID
                FROM {$wpdb->posts}
                WHERE post_type = %s
                LIMIT %d OFFSET %d",
				array( self::POST_TYPE_PLAN, $batch_size, $offset )
			)
		);

		if ( empty( $plans ) ) {
			$this->log( __( 'Finished importing plans.', 'geodir-converter' ) );
			return $this->next_task( $task );
		}

		foreach ( $plans as $plan ) {
			$plan = get_post( $plan->ID );
			if ( ! $plan ) {
				$this->log( sprintf( __( 'Failed to import plan: %s', 'geodir-converter' ), $plan->post_title ), 'error' );
				++$failed;
				continue;
			}

			$plan_id          = absint( $plan->ID );
			$plan_label       = $plan->post_title;
			$plan_description = $plan->post_content;

			// Retrieve all post meta data at once.
			$plan_meta = get_post_meta( $plan_id );
			$plan_meta = array_map(
				function ( $meta ) {
					return isset( $meta[0] ) ? $meta[0] : '';
				},
				$plan_meta
			);

			// Check if the plan already exists.
			$existing_package = $this->get_existing_package( $post_type, $plan_id, 0 === (float) $plan_meta['price'] );

			$package_data = array(
				'post_type'       => $post_type,
				'name'            => $plan_label,
				'title'           => $plan_label,
				'description'     => $plan_description,
				'fa_icon'         => '',
				'amount'          => (float) $plan_meta['price'],
				'time_interval'   => (int) $plan_meta['period'],
				'time_unit'       => $plan_meta['period_type'],
				'recurring'       => 'forced_recurring' === $plan_meta['recurring'] ? true : false,
				'recurring_limit' => 0,
				'trial'           => '',
				'trial_amount'    => '',
				'trial_interval'  => '',
				'trial_unit'      => '',
				'is_default'      => ( 'publish' === $plan->post_status && 1 === (int) $plan->menu_order ) ? 1 : 0,
				'display_order'   => (int) $plan->menu_order,
				'downgrade_pkg'   => 0,
				'post_status'     => 'pending',
				'status'          => 'publish' === $plan->post_status ? true : false,
			);

			// If existing package found, update ID before saving.
			if ( $existing_package ) {
				$package_data['id'] = absint( $existing_package->id );
			}

			// Prepare and insert/update package.
			$package_data = GeoDir_Pricing_Package::prepare_data_for_save( $package_data );
			$package_id   = GeoDir_Pricing_Package::insert_package( $package_data );

			if ( ! $package_id || is_wp_error( $package_id ) ) {
				$this->log( sprintf( __( 'Failed to import plan: %s', 'geodir-converter' ), $plan_label ), 'error' );
				++$failed;
			} else {
				$log_message = $existing_package
				? sprintf( __( 'Updated plan: %s', 'geodir-converter' ), $plan_label )
				: sprintf( __( 'Imported new plan: %s', 'geodir-converter' ), $plan_label );

				$this->log( $log_message );

				$existing_package ? ++$skipped : ++$imported;

				GeoDir_Pricing_Package::update_meta( $package_id, '_vantage_package_id', $plan_id );
			}
		}

		$this->increase_succeed_imports( (int) $imported );
		$this->increase_skipped_imports( (int) $skipped );
		$this->increase_failed_imports( (int) $failed );

		$this->log(
			sprintf(
				__( 'Plans import completed: %1$d imported, %2$d updated, %3$d failed.', 'geodir-converter' ),
				$imported,
				$skipped,
				$failed
			),
			$failed ? 'warning' : 'success'
		);

		return $this->next_task( $task );
	}

	/**
	 * Get existing package based on BDP package ID or find a suitable free package.
	 *
	 * @param string  $post_type     The post type associated with the package.
	 * @param int     $vantage_plan_id    The Vantage package ID.
	 * @param boolean $free_fallback Whether to fallback to a free package if no match is found.
	 * @return object|null The existing package object if found, or null otherwise.
	 */
	private function get_existing_package( $post_type, $vantage_plan_id, $free_fallback = true ) {
		global $wpdb;

		// Fetch the package by BDP ID.
		$query = $wpdb->prepare(
			'SELECT p.*, g.* 
            FROM ' . GEODIR_PRICING_PACKAGES_TABLE . ' AS p
            INNER JOIN ' . GEODIR_PRICING_PACKAGE_META_TABLE . ' AS g ON p.ID = g.package_id
            WHERE p.post_type = %s AND g.meta_key = %s AND g.meta_value = %d
            LIMIT 1',
			$post_type,
			'_vantage_package_id',
			(int) $vantage_plan_id
		);

		$existing_package = $wpdb->get_row( $query );

		// If not found, attempt to retrieve a free package.
		if ( ! $existing_package && $free_fallback ) {
			$query_free = $wpdb->prepare(
				'SELECT * FROM ' . GEODIR_PRICING_PACKAGES_TABLE . ' 
                WHERE post_type = %s AND amount = 0 AND status = 1
                ORDER BY display_order ASC, ID ASC
                LIMIT 1',
				$post_type
			);

			$existing_package = $wpdb->get_row( $query_free );
		}

		return $existing_package;
	}

	/**
	 * Get standard fields.
	 *
	 * @since 2.0.2
	 * @return array Array of standard fields.
	 */
	private function get_fields() {
		$standard_fields = array(
			array(
				'id'       => 'vantage_id',
				'type'     => 'int',
				'priority' => 1,
				'props'    => array(
					'label'       => __( 'Vantage ID', 'geodir-converter' ),
					'description' => __( 'Original Vantage Listing ID.', 'geodir-converter' ),
					'required'    => false,
					'placeholder' => __( 'Vantage ID', 'geodir-converter' ),
					'icon'        => 'far fa-id-card',
				),
			),
			array(
				'id'       => 'email',
				'type'     => 'text',
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
				'type'     => 'text',
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
				'type'     => 'text',
				'priority' => 3,
				'props'    => array(
					'label'       => __( 'Website', 'geodir-converter' ),
					'description' => __( 'The website of the listing.', 'geodir-converter' ),
					'required'    => false,
					'placeholder' => __( 'Website', 'geodir-converter' ),
					'icon'        => 'fa-solid fa-globe',
				),
			),
			array(
				'id'       => 'facebook',
				'type'     => 'text',
				'priority' => 3,
				'props'    => array(
					'label'       => __( 'Facebook', 'geodir-converter' ),
					'description' => __( 'The Facebook page of the listing.', 'geodir-converter' ),
					'required'    => false,
					'placeholder' => __( 'Facebook', 'geodir-converter' ),
					'icon'        => 'fa-brands fa-facebook',
				),
			),
			array(
				'id'       => 'twitter',
				'type'     => 'text',
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
				'type'     => 'text',
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
				'type'     => 'text',
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
				'type'     => 'text',
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
				'type'     => 'text',
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
				'id'       => 'featured',
				'type'     => 'checkbox',
				'priority' => 18,
				'props'    => array(
					'label'       => __( 'Is Featured?', 'geodir-converter' ),
					'frontend'    => __( 'Is Featured?', 'geodirectory' ),
					'description' => __( 'Mark listing as a featured.', 'geodir-converter' ),
					'required'    => false,
					'placeholder' => __( 'Is Featured?', 'geodir-converter' ),
					'icon'        => 'fas fa-certificate',
				),
			),
			array(
				'id'       => 'claimed',
				'type'     => 'checkbox',
				'priority' => 19,
				'props'    => array(
					'label'       => __( 'Is Claimed', 'geodir-converter' ),
					'frontend'    => __( 'Business Owner/Associate?', 'geodir-converter' ),
					'description' => __( 'Mark listing as a claimed.', 'geodir-converter' ),
					'required'    => false,
					'placeholder' => __( 'Is Claimed', 'geodir-converter' ),
					'icon'        => 'far fa-check',
				),
			),
		);

		$vantage_post_type = self::POST_TYPE_LISTING;
		$options           = (array) get_option( "app_{$vantage_post_type}_options", array() );
		$form_fields       = isset( $options['app_form'] ) ? (array) $options['app_form'] : array();
		$fields            = array_merge( $standard_fields, $form_fields );

		return $fields;
	}

	/**
	 * Convert BDP field to GD field.
	 *
	 * @since 2.0.2
	 * @param string $key       The field key.
	 * @param array  $field     The BDP field data.
	 * @param string $post_type The post type.
	 * @param array  $package_ids   The package data.
	 * @return array|false The GD field data or false if conversion fails.
	 */
	private function prepare_single_field( $key, $field, $post_type, $package_ids = array() ) {
		$field         = $this->normalize_vantage_field( $field );
		$gd_field_key  = $this->map_field_key( $key );
		$gd_field_type = $this->map_field_type( $field['type'] );
		$gd_data_type  = $this->map_data_type( $field['type'] );
		$gd_field      = geodir_get_field_infoby( 'htmlvar_name', $gd_field_key, $post_type );
		$props         = isset( $field['props'] ) ? (array) $field['props'] : array();

		if ( $gd_field ) {
			$gd_field['field_id'] = (int) $gd_field['id'];
			unset( $gd_field['id'] );
		} else {
			$gd_field = array(
				'post_type'     => $post_type,
				'data_type'     => $gd_data_type,
				'field_type'    => $gd_field_type,
				'htmlvar_name'  => $gd_field_key,
				'is_active'     => '1',
				'option_values' => '',
				'is_default'    => '0',
			);

			if ( 'checkbox' === $gd_field_type ) {
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
				'show_in'           => ( 'listing_title' === $key ) ? '[owntab],[detail],[mapbubble]' : '[owntab],[detail]',
				'show_on_pkg'       => $package_ids,
				'clabels'           => isset( $props['label'] ) ? $props['label'] : '',
				'field_icon'        => isset( $props['icon'] ) ? $props['icon'] : '',
			)
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

		return $gd_field;
	}

	/**
	 * Get the corresponding GD field key for a given shortname.
	 *
	 * @param string $shortname The field shortname.
	 * @return string The mapped field key or the original shortname if no match is found.
	 */
	private function map_field_key( $shortname ) {
		$fields_map = array(
			'listing_title'     => 'post_title',
			'short_description' => 'post_excerpt',
			'description'       => 'post_content',
			'listing_category'  => 'post_category',
			'listing_tags'      => 'post_tags',
			'zip_code'          => 'zip',
		);

		return isset( $fields_map[ $shortname ] ) ? $fields_map[ $shortname ] : $shortname;
	}

	/**
	 * Map PMD field type to GeoDirectory field type.
	 *
	 * @param string $field_type The PMD field type.
	 * @return string|false The GeoDirectory field type or false if not supported.
	 */
	private function map_field_type( $field_type ) {
		switch ( $field_type ) {
			case 'input_text':
			case 'email':
				return 'text';
			case 'textarea':
				return 'textarea';
			case 'file':
				return 'file';
			case 'select':
				return 'select';
			case 'url':
				return 'url';
			case 'radio':
				return 'radio';
			case 'checkbox':
				return 'checkbox';
			case 'number':
				return 'number';
			default:
				return 'text';
		}
	}

	/**
	 * Map PMD field type to GeoDirectory field type.
	 *
	 * @param string $field_type The PMD field type.
	 * @return string|false The GeoDirectory field type or false if not supported.
	 */
	private function map_data_type( $field_type ) {
		switch ( $field_type ) {
			case 'input_text':
			case 'textarea':
			case 'url':
			case 'select':
			case 'radio':
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
	 * Normalize and set default values for a given field.
	 *
	 * @param array $field Field values to normalize.
	 * @return array Normalized field values.
	 */
	private function normalize_vantage_field( $field ) {
		$defaults = array(
			'id'    => '',
			'type'  => 'input_text',
			'props' => array(
				'required'    => 0,
				'label'       => '',
				'tip'         => '',
				'disable'     => 0,
				'placeholder' => '',
				'tax'         => '',
				'options'     => array(),
				'extensions'  => '',
				'file_limit'  => 5,
				'embed_limit' => 0,
				'file_size'   => 0,
				'icon'        => '',
			),
		);

		return wp_parse_args( $field, $defaults );
	}

	/**
	 * Import listings from Listify to GeoDirectory.
	 *
	 * @since 2.0.2
	 * @param array $task The offset to start importing from.
	 * @return array Result of the import operation.
	 */
	public function task_parse_listings( array $task ) {
		global $wpdb;

		$offset         = isset( $task['offset'] ) ? (int) $task['offset'] : 0;
		$total_listings = isset( $task['total_listings'] ) ? (int) $task['total_listings'] : 0;
		$batch_size     = (int) $this->get_batch_size();

		// Determine total listings count if not set.
		if ( ! isset( $task['total_listings'] ) ) {
			$total_listings         = $this->count_listings();
			$task['total_listings'] = $total_listings;
		}

		// Log the import start message only for the first batch.
		if ( 0 === $offset ) {
			$this->log( __( 'Starting listings import process...', 'geodir-converter' ) );
		}

		// Exit early if there are no listings to import.
		if ( 0 === $total_listings ) {
			$this->log( __( 'No listings found for import. Skipping process.', 'geodir-converter' ) );
			return $this->next_task( $task );
		}

		wp_suspend_cache_addition( false );

		$listings = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT *
                FROM {$wpdb->posts}
                WHERE post_type = %s
                AND post_status IN (" . implode( ',', array_fill( 0, count( $this->post_statuses ), '%s' ) ) . ')
                LIMIT %d OFFSET %d',
				array_merge(
					array( self::POST_TYPE_LISTING ),
					$this->post_statuses,
					array( $batch_size, $offset )
				)
			)
		);

		if ( empty( $listings ) ) {
			$this->log( __( 'Import process completed. No more listings found.', 'geodir-converter' ) );
			return $this->next_task( $task );
		}

		$import_tasks = array();
		foreach ( $listings as $listing ) {
			$import_tasks[] = array(
				'post_id'    => (int) $listing->ID,
				'post_title' => $listing->post_title,
				'listing'    => $listing,
				'action'     => GeoDir_Converter_Importer::ACTION_IMPORT_LISTING,
			);
		}

		$this->background_process->add_import_tasks( $import_tasks );

		$complete = ( $offset + $batch_size >= $total_listings );

		if ( ! $complete ) {
			// Continue import with the next batch.
			$task['offset'] = $offset + $batch_size;
			return $task;
		}

		return $this->next_task( $task );
	}

	/**
	 * Import listings from Vantage to GeoDirectory.
	 *
	 * @since 2.0.2
	 * @param array $task The task to import.
	 * @return array Result of the import operation.
	 */
	public function task_import_listing( $task ) {
		$title         = $task['post_title'];
		$import_status = $this->import_single_listing( $task['listing'] );

		switch ( $import_status ) {
			case GeoDir_Converter_Importer::IMPORT_STATUS_SUCCESS:
				$this->log( sprintf( self::LOG_TEMPLATE_SUCCESS, 'listing', $title ), 'success' );
				$this->increase_succeed_imports( 1 );
				break;

			case GeoDir_Converter_Importer::IMPORT_STATUS_UPDATED:
				$this->log( sprintf( self::LOG_TEMPLATE_UPDATED, 'listing', $title ), 'warning' );
				$this->increase_succeed_imports( 1 );
				break;

			case GeoDir_Converter_Importer::IMPORT_STATUS_SKIPPED:
				$this->log( sprintf( self::LOG_TEMPLATE_SKIPPED, 'listing', $title ), 'warning' );
				$this->increase_skipped_imports( 1 );
				break;

			case GeoDir_Converter_Importer::IMPORT_STATUS_FAILED:
				$this->log( sprintf( self::LOG_TEMPLATE_FAILED, 'listing', $title ), 'warning' );
				$this->increase_failed_imports( 1 );
				break;
		}

		return false;
	}

	/**
	 * Convert a single Listify listing to GeoDirectory format.
	 *
	 * @since 2.0.2
	 * @param  int $post_id The post ID to convert.
	 * @return array|int Converted listing data or import status.
	 */
	private function import_single_listing( $post ) {
		// Check if the post has already been imported.
		$post_type        = $this->get_import_post_type();
		$listings_mapping = (array) $this->options_handler->get_option_no_cache( 'listings_mapping', array() );
		$gd_post_id       = ! $this->is_test_mode() ? (int) $this->get_gd_listing_id( $post->ID, 'vantage_id', $post_type ) : false;
		$is_update        = ! empty( $gd_post_id );

		// Retrieve all post meta data at once.
		$post_meta = get_post_meta( $post->ID );
		$post_meta = array_map(
			function ( $meta ) {
				return isset( $meta[0] ) ? $meta[0] : '';
			},
			$post_meta
		);

		// Retrieve default location and process fields.
		$default_location = $this->get_default_location();
		$fields           = $this->process_form_fields( $post, $post_meta );
		$categories       = $this->get_categories( $post->ID, self::TAX_LISTING_CATEGORY );
		$tags             = $this->get_categories( $post->ID, self::TAX_LISTING_TAG, 'names' );
		$coord            = $this->get_listing_coordinates( $post->ID );
		$address          = isset( $post_meta['address'] ) && ! empty( $post_meta['address'] ) ? $post_meta['address'] : '';

		// Convert post status to GD status.
		$gd_post_status = $post->post_status;
		if ( 'expired' === $gd_post_status ) {
			$gd_post_status = 'gd-expired';
		} elseif ( 'deleted' === $gd_post_status ) {
			$gd_post_status = 'gd-closed';
		}

		// Prepare the listing data.
		$listing = array(
			// Standard WP Fields.
			'post_author'           => $post->post_author ? $post->post_author : get_current_user_id(),
			'post_title'            => $post->post_title,
			'post_content'          => $post->post_content,
			'post_content_filtered' => $post->post_content_filtered,
			'post_excerpt'          => $post->post_excerpt,
			'post_status'           => $gd_post_status,
			'post_type'             => $post_type,
			'comment_status'        => $post->comment_status,
			'ping_status'           => $post->ping_status,
			'post_name'             => $post->post_name ? $post->post_name : 'listing-' . $post->ID,
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
			'featured_image'        => $this->get_featured_image( $post->ID ),
			'submit_ip'             => '',
			'overall_rating'        => 0,
			'rating_count'          => 0,

			'street'                => isset( $post_meta['geo_street'] ) && ! empty( $post_meta['geo_street'] ) ? $post_meta['geo_street'] : $address,
			'street2'               => '',
			'city'                  => isset( $post_meta['geo_city'] ) ? $post_meta['geo_city'] : $default_location['city'],
			'region'                => isset( $post_meta['geo_state_long'] ) ? $post_meta['geo_state_long'] : $default_location['region'],
			'country'               => isset( $post_meta['geo_country_long'] ) ? $post_meta['geo_country_long'] : $default_location['country'],
			'zip'                   => isset( $post_meta['geo_postal_code'] ) ? $post_meta['geo_postal_code'] : '',
			'latitude'              => ! empty( $coord->lat ) ? $coord->lat : $default_location['latitude'],
			'longitude'             => ! empty( $coord->lng ) ? $coord->lng : $default_location['longitude'],
			'mapview'               => '',
			'mapzoom'               => '',

			// Vantage standard fields.
			'vantage_id'            => $post->ID,
			'phone'                 => isset( $post_meta['phone'] ) ? $post_meta['phone'] : '',
			'website'               => isset( $post_meta['website'] ) ? $post_meta['website'] : '',
			'email'                 => isset( $post_meta['email'] ) ? $post_meta['email'] : '',
			'facebook'              => isset( $post_meta['facebook'] ) ? $post_meta['facebook'] : '',
			'twitter'               => isset( $post_meta['twitter'] ) ? $post_meta['twitter'] : '',
			'instagram'             => isset( $post_meta['instagram'] ) ? $post_meta['instagram'] : '',
			'youtube'               => isset( $post_meta['youtube'] ) ? $post_meta['youtube'] : '',
			'pinterest'             => isset( $post_meta['pinterest'] ) ? $post_meta['pinterest'] : '',
			'linkedin'              => isset( $post_meta['linkedin'] ) ? $post_meta['linkedin'] : '',

			'featured'              => isset( $post_meta['_listing-featured-home'] ) && 1 === (int) $post_meta['_listing-featured-home'] ? (bool) true : false,
		);

		// Process expiration date.
		$expiration_date = '';
		if ( isset( $post_meta['_listing_duration'] ) && (int) $post_meta['_listing_duration'] > 0 ) {
			$duration        = (int) $post_meta['_listing_duration'];
			$expiration_date = gmdate( 'Y-m-d H:i:s', strtotime( $post->post_date . ' + ' . $duration . 'days' ) );

			$listing['expire_date'] = $expiration_date;
		}

		// Process package.
		$gd_package_id = 0;
		if ( class_exists( 'GeoDir_Pricing_Package' ) && isset( $post_meta['_app_plan_id'] ) && ! empty( $post_meta['_app_plan_id'] ) ) {
			$plan_id = absint( (int) filter_var( $post_meta['_app_plan_id'], FILTER_SANITIZE_NUMBER_INT ) );
			$package = $this->get_existing_package( $post_type, $plan_id, false );

			if ( $package ) {
				$gd_package_id         = (int) $package->id;
				$listing['package_id'] = $gd_package_id;
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

		// Process gallery images.
		if ( isset( $post_meta['_app_media'] ) && ! empty( $post_meta['_app_media'] ) ) {
			$images = $this->get_gallery_images( $post_meta['_app_media'] );
			if ( ! empty( $images ) ) {
				$listing['post_images'] = $images;
			}
		}

		// Insert or update the post.
		if ( $is_update ) {
			$listing['ID'] = (int) $gd_post_id;
			$gd_post_id    = wp_update_post( $listing, true );
		} else {
			$gd_post_id = wp_insert_post( $listing, true );
		}

		// Handle errors during post insertion/update.
		if ( is_wp_error( $gd_post_id ) ) {
			$this->log( $gd_post_id->get_error_message() );
			return GeoDir_Converter_Importer::IMPORT_STATUS_FAILED;
		}

		// Update custom fields.
		$gd_post = geodir_get_post_info( (int) $gd_post_id );
		if ( ! empty( $gd_post ) && ! empty( $fields ) ) {
			foreach ( $fields as $field_key => $field_value ) {
				if ( property_exists( $gd_post, $field_key ) ) {
					$gd_post->{$field_key} = $field_value;
				}
			}

			$updated = wp_update_post( (array) $gd_post, true );
			if ( is_wp_error( $updated ) ) {
				$this->log( $updated->get_error_message() );
			}
		}

		// Update listings mapping.
		$listings_mapping[ (int) $post->ID ] = array(
			'gd_post_id'    => (int) $gd_post_id,
			'gd_package_id' => (int) $gd_package_id,
		);

		$this->options_handler->update_option( 'listings_mapping', $listings_mapping );

		return $is_update ? GeoDir_Converter_Importer::IMPORT_STATUS_UPDATED : GeoDir_Converter_Importer::IMPORT_STATUS_SUCCESS;
	}

	/**
	 * Import payments.
	 *
	 * @param array $task Import task details.
	 * @return array Updated task with the next action.
	 */
	public function task_import_payments( array $task ) {
		global $wpdb;

		// Abort early if the invoices plugin is not installed.
		if ( ! class_exists( 'WPInv_Plugin' ) ) {
			$this->log( __( 'Invoices plugin is not active. Skipping invoices...', 'geodir-converter' ) );
			return $this->next_task( $task );
		}

		$offset           = isset( $task['offset'] ) ? (int) $task['offset'] : 0;
		$imported         = isset( $task['imported'] ) ? (int) $task['imported'] : 0;
		$failed           = isset( $task['failed'] ) ? (int) $task['failed'] : 0;
		$skipped          = isset( $task['skipped'] ) ? (int) $task['skipped'] : 0;
		$total_payments   = isset( $task['total_payments'] ) ? (int) $task['total_payments'] : 0;
		$batch_size       = (int) $this->get_batch_size();
		$vantage_options  = (array) get_option( 'va_options', array() );
		$listings_mapping = (array) $this->options_handler->get_option_no_cache( 'listings_mapping', array() );

		// Determine total payments count if not set.
		if ( ! isset( $task['total_payments'] ) ) {
			$total_payments         = $this->count_payments();
			$task['total_payments'] = $total_payments;
		}

		// Log the import start message only for the first batch.
		if ( 0 === $offset ) {
			$this->log( __( 'Starting payments import process...', 'geodir-converter' ) );
		}

		// Exit early if there are no payments to import.
		if ( 0 === $total_payments ) {
			$this->log( __( 'No payments found for import. Skipping process.', 'geodir-converter' ) );
			return $this->next_task( $task );
		}

		wp_suspend_cache_addition( true );

		$payments = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DISTINCT ID
                FROM {$wpdb->posts}
                WHERE post_type = %s
                LIMIT %d OFFSET %d",
				array( self::POST_TYPE_TRANSACTION, $batch_size, $offset )
			)
		);

		if ( empty( $payments ) ) {
			$this->log( __( 'Finished importing payments.', 'geodir-converter' ) );
			return $this->next_task( $task );
		}

		foreach ( $payments as $payment ) {
			$payment = get_post( $payment->ID );
			if ( ! $payment ) {
				$this->log( sprintf( __( 'Failed to import payment: %s', 'geodir-converter' ), $payment->post_title ), 'error' );
				++$failed;
				$this->increase_failed_imports( 1 );
				continue;
			}

			// Retrieve all post meta data at once.
			$payment_meta = get_post_meta( $payment->ID );
			$payment_meta = array_map(
				function ( $meta ) {
					return isset( $meta[0] ) ? $meta[0] : '';
				},
				$payment_meta
			);

			$invoice_id     = ! $this->is_test_mode() ? $this->get_gd_post_id( $payment->ID, 'vantage_invoice_id' ) : false;
			$is_update      = ! empty( $invoice_id );
			$invoice_status = isset( $this->payment_statuses[ $payment->post_status ] ) ? $this->payment_statuses[ $payment->post_status ] : 'wpi-pending';
			$total_price    = isset( $payment_meta['total_price'] ) ? (float) $payment_meta['total_price'] : 0;
			$charged_tax    = 0;
			$gateway        = isset( $payment_meta['gateway'] ) ? strtolower( $payment_meta['gateway'] ) : '';

			// Get transaction ID.
			$transaction_id = '';
			if ( 'paypal' === $gateway ) {
				$transaction_id = isset( $payment_meta['paypal_subscription_id'] ) ? $payment_meta['paypal_subscription_id'] : '';
			}

			// Get tax.
			$taxes = array();
			if ( isset( $vantage_options['tax_charge'] ) && (float) $vantage_options['tax_charge'] > 0 ) {
				$charged_tax = (float) $total_price * ( (float) $vantage_options['tax_charge'] / 100 );

				$taxes[ __( 'Tax', 'geodir-converter' ) ] = array( 'initial_tax' => (float) $charged_tax );
			}

			$wpi_invoice = new WPInv_Invoice();
			$wpi_invoice->set_props(
				array(
					// Basic info.
					'post_type'      => 'wpi_invoice',
					'description'    => $payment->post_content,
					'status'         => $invoice_status,
					'created_via'    => 'geodir-converter',
					'date_created'   => $payment->post_date,
					'due_date'       => $payment->post_date,
					'date_completed' => $payment->post_date,

					// Payment info.
					'gateway'        => $gateway,
					'total'          => (float) $total_price,
					'subtotal'       => $total_price > 0 ? (float) $total_price - (float) $charged_tax : 0,
					'taxes'          => $taxes,

					// Billing details.
					'user_id'        => $payment->post_author,
					'user_ip'        => $payment_meta['ip_address'],
					'currency'       => $payment_meta['currency'],
					'transaction_id' => $transaction_id,
				)
			);

			$order_items = new WP_Query(
				array(
					'connected_type'  => 'order-connection',
					'connected_from'  => $payment->ID,
					'connected_query' => array( 'post_status' => 'any' ),
					'post_status'     => 'any',
					'nopaging'        => true,
				)
			);

			// Get the package ID.
			$gd_post_info = array();
			foreach ( $order_items->posts as $order_item ) {
				if ( self::POST_TYPE_LISTING === $order_item->post_type && isset( $listings_mapping[ $order_item->ID ]['gd_post_id'] ) ) {
					$gd_post_info = $listings_mapping[ $order_item->ID ];
					break;
				}
			}

			if ( isset( $gd_post_info['gd_package_id'] ) && 0 !== (int) $gd_post_info['gd_package_id'] ) {
				$wpinv_item = wpinv_get_item_by( 'custom_id', (int) $gd_post_info['gd_package_id'], 'package' );
				if ( $wpinv_item ) {
					$item = new GetPaid_Form_Item( $wpinv_item->get_id() );
					$item->set_name( $wpinv_item->get_name() );
					$item->set_description( $wpinv_item->get_description() );
					$item->set_price( $wpinv_item->get_price() );
					$item->set_quantity( 1 );
					$wpi_invoice->add_item( $item );
				} else {
					$package = GeoDir_Pricing_Package::get_package( (int) $gd_post_info['gd_package_id'] );
					if ( $package ) {
						$item = new GetPaid_Form_Item( $package['id'] );
						$item->set_name( $package['title'] );
						$item->set_description( $package['description'] );
						$item->set_price( (float) $package['amount'] );
						$item->set_quantity( 1 );
						$wpi_invoice->add_item( $item );
					}
				}
			}

			// Insert or update the post.
			if ( $is_update ) {
				$wpi_invoice->ID = absint( $invoice_id );
			}

			// Handle test mode.
			if ( $this->is_test_mode() ) {
				if ( $is_update ) {
					++$skipped;
					$this->increase_skipped_imports( 1 );
				} else {
					++$imported;
					$this->increase_succeed_imports( 1 );
				}
				continue;
			}

			$wpi_invoice_id = $wpi_invoice->save();

			if ( is_wp_error( $wpi_invoice_id ) ) {
				$this->log( sprintf( __( 'Failed to import payment %s.', 'geodir-converter' ), $payment->title ) );
				++$failed;
				continue;
			}

			// Update post meta.
			update_post_meta( $wpi_invoice_id, 'vantage_invoice_id', $payment->ID );

			if ( $is_update ) {
				++$skipped;
				$this->increase_skipped_imports( 1 );
			} else {
				++$imported;
				$this->increase_succeed_imports( 1 );
			}
		}

		// Update task progress.
		$task['imported'] = (int) $imported;
		$task['failed']   = (int) $failed;
		$task['skipped']  = (int) $skipped;

		$complete = ( $offset + $batch_size >= $total_payments );

		if ( ! $complete ) {
			// Continue import with the next batch.
			$task['offset'] = $offset + $batch_size;
			return $task;
		}

		$this->log(
			sprintf(
				__( 'Payments import completed: %1$d imported, %2$d updated, %3$d failed.', 'geodir-converter' ),
				$imported,
				$skipped,
				$failed
			),
			$failed ? 'warning' : 'success'
		);

		return $this->next_task( $task );
	}

	/**
	 * Get the featured image URL.
	 *
	 * @since 2.0.2
	 * @param int $post_id The post ID.
	 * @return string The featured image URL.
	 */
	private function get_featured_image( $post_id ) {
		$image = wp_get_attachment_image_src( get_post_thumbnail_id( $post_id ), 'full' );
		return isset( $image[0] ) ? esc_url( $image[0] ) : '';
	}

	/**
	 * Get gallery images from Vantage format.
	 *
	 * @since 2.0.2
	 * @param string|array $media The Vantage media format.
	 * @return array The images in the Geodirectory format.
	 */
	private function get_gallery_images( $media ) {
		$image_ids = maybe_unserialize( $media );

		if ( ! is_array( $image_ids ) || empty( $image_ids ) ) {
			return '';
		}

		$images = array_map(
			function ( $id ) {
				$id = absint( $id );

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
	 * Get the listing coordinates.
	 *
	 * @param int  $post_id The post ID.
	 * @param bool $fallback_to_zero Whether to fallback to zero coordinates if none found.
	 * @return object The coordinates.
	 */
	private function get_listing_coordinates( $post_id, $fallback_to_zero = true ) {
		global $wpdb;

		if ( ! isset( $wpdb->app_geodata ) ) {
			return (object) array(
				'lat' => 0,
				'lng' => 0,
			);
		}

		$coord = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $wpdb->app_geodata WHERE post_id = %d", $post_id ) );

		if ( ! $coord && $fallback_to_zero ) {
			return (object) array(
				'lat' => 0,
				'lng' => 0,
			);
		}

		return $coord;
	}

	/**
	 * Process form fields and extract values from post meta.
	 *
	 * @param object $post The post object.
	 * @param array  $post_meta The post meta data.
	 * @return array The processed fields.
	 */
	private function process_form_fields( $post, $post_meta ) {
		$form_fields = $this->get_fields();
		$fields      = array();

		foreach ( $form_fields as $field ) {
			if ( isset( $post_meta[ $field['id'] ] ) ) {
				$gd_key = $this->map_field_key( $field['id'] );
				$value  = $post_meta[ $field['id'] ];

				if ( $this->should_skip_field( $gd_key ) ) {
					continue;
				}

				// Unserialize a value if it's serialized.
				if ( is_string( $value ) && is_serialized( $value ) ) {
					$value = maybe_unserialize( $value );
				}

				$fields[ $gd_key ] = $value;
			}
		}

		return $fields;
	}

	/**
	 * Retrieves the current post's categories.
	 *
	 * @since 2.0.2
	 * @param int    $post_id The post ID.
	 * @param string $taxonomy The taxonomy to query for.
	 * @param string $return_type Determines whether to return IDs or names.
	 * @return array An array of category IDs or names based on the $return_type.
	 */
	private function get_categories( $post_id, $taxonomy = self::TAX_LISTING_CATEGORY, $return_type = 'ids' ) {
		global $wpdb;

		$terms = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT t.term_id, t.name, tm.meta_value as gd_equivalent
				FROM {$wpdb->terms} t
				INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
				INNER JOIN {$wpdb->term_relationships} tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
				LEFT JOIN {$wpdb->termmeta} tm ON t.term_id = tm.term_id and tm.meta_key = 'gd_equivalent'
				WHERE tr.object_id = %d and tt.taxonomy = %s",
				$post_id,
				$taxonomy
			)
		);

		$categories = array();

		foreach ( $terms as $term ) {
			$gd_term_id = (int) $term->gd_equivalent;
			if ( $gd_term_id ) {
				$gd_term = $wpdb->get_row( $wpdb->prepare( "SELECT name, term_id FROM {$wpdb->terms} WHERE term_id = %d", $gd_term_id ) );

				if ( $gd_term ) {
					$categories[] = ( 'names' === $return_type ) ? $gd_term->name : $gd_term->term_id;
				}
			}
		}

		return $categories;
	}

	/**
	 * Counts the number of listings.
	 *
	 * @since 2.0.2
	 * @return int The number of listings.
	 */
	private function count_listings() {
		global $wpdb;

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} 
                WHERE post_type = %s 
                AND post_status IN (" . implode( ',', array_fill( 0, count( $this->post_statuses ), '%s' ) ) . ')',
				array_merge( array( self::POST_TYPE_LISTING ), $this->post_statuses )
			)
		);

		return $count;
	}

	/**
	 * Counts the number of plans.
	 *
	 * @since 2.0.2
	 * @return int The number of plans.
	 */
	private function count_plans() {
		global $wpdb;

		$count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s", self::POST_TYPE_PLAN ) );

		return $count;
	}

	/**
	 * Counts the number of payments.
	 *
	 * @since 2.0.2
	 * @return int The number of payments.
	 */
	private function count_payments() {
		global $wpdb;

		$count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s", self::POST_TYPE_TRANSACTION ) );

		return $count;
	}

	/**
	 * Filter to skip sending completed invoice emails for invoices created by GeoDir Converter.
	 *
	 * @param bool   $skip     Whether to skip sending the email.
	 * @param string $type     The email type.
	 * @param object $invoice  The invoice object.
	 * @return bool
	 */
	public function skip_invoice_email( $skip, $type, $invoice ) {
		if ( in_array( $type, array( 'completed_invoice', 'refunded_invoice', 'cancelled_invoice' ), true ) && $invoice->get_created_via() === 'geodir-converter' ) {
			return true;
		}

		return $skip;
	}
}
