<?php
/**
 * Abstract Base Importer Class
 *
 * @package GeoDir_Converter
 * @subpackage Abstracts
 * @since 2.0.2
 */

namespace GeoDir_Converter\Abstracts;

use GeoDir_Converter\GeoDir_Converter_Options_Handler;
use GeoDir_Converter\Importers\GeoDir_Converter_Background_Process;

defined( 'ABSPATH' ) || exit;

/**
 * Abstract Base Importer Class
 *
 * This class serves as a foundation for all specific importer classes.
 */
abstract class GeoDir_Converter_Importer {
	/**
 * Number of records processed per batch.
 *
 * @var int
 */
	const BATCH_SIZE = 1000;

	/**
	 * Action identifier for importing categories.
	 *
	 * @var string
	 */
	const ACTION_IMPORT_CATEGORIES = 'categories';

	/**
	 * Action identifier for importing tags.
	 *
	 * @var string
	 */
	const ACTION_IMPORT_TAGS = 'tags';

	/**
	 * Action identifier for importing packages.
	 *
	 * @var string
	 */
	const ACTION_IMPORT_PACKAGES = 'packages';

	/**
	 * Action identifier for importing custom fields.
	 *
	 * @var string
	 */
	const ACTION_IMPORT_FIELDS = 'fields';

	/**
	 * Action identifier for importing listings.
	 *
	 * @var string
	 */
	const ACTION_IMPORT_LISTINGS = 'listings';

	/**
	 * Import status indicating failure.
	 *
	 * @var int
	 */
	const IMPORT_STATUS_FAILED = 0;

	/**
	 * Import status indicating success.
	 *
	 * @var int
	 */
	const IMPORT_STATUS_SUCCESS = 1;

	/**
	 * Import status indicating the item was skipped.
	 *
	 * @var int
	 */
	const IMPORT_STATUS_SKIPPED = 2;

	/**
	 * The importer ID.
	 *
	 * @var string
	 */
	protected $importer_id;

	/**
	 * Background process instance.
	 *
	 * @var GeoDir_Converter_Background_Process
	 */
	public $background_process;

	/**
	 * Options handler instance.
	 *
	 * @var GeoDir_Converter_Options_Handler
	 */
	public $options_handler;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->options_handler    = new GeoDir_Converter_Options_Handler( "geodir_converter_{$this->importer_id}" );
		$this->background_process = new GeoDir_Converter_Background_Process( $this );

		add_filter( 'geodir_converter_importers', array( $this, 'register' ) );

		$this->init();
	}

	/**
	 * Initialize the importer.
	 */
	abstract protected function init();

	/**
	 * Register the importer.
	 *
	 * @param array $importers Existing importers.
	 * @return array Modified importers array.
	 */
	public function register( array $importers ) {
		$importers[ $this->importer_id ] = $this;

		return $importers;
	}

	/**
	 * Get the importer ID.
	 *
	 * @return string
	 */
	public function get_id() {
		return $this->importer_id;
	}

	/**
	 * Get importer title.
	 *
	 * @return string
	 */
	abstract public function get_title();

	/**
	 * Get importer description.
	 *
	 * @return string
	 */
	abstract public function get_description();

	/**
	 * Get importer icon URL.
	 *
	 * @return string
	 */
	abstract public function get_icon();

	/**
	 * Retrieves the action identifier for the importer task.
	 *
	 * This action is the first step executed by the WordPress background process
	 * and determines how the import job will be processed.
	 *
	 * @return string The action identifier associated with the importer.
	 */
	abstract public function get_action();

	/**
	 * Import categories.
	 *
	 * @param array $task The offset to start importing from.
	 * @return array Result of the import operation.
	 */
	abstract public function import_categories( array $task );

	/**
	 * Validate importer settings.
	 *
	 * @param array $settings The settings to validate.
	 * @return array Validated and sanitized settings.
	 */
	abstract public function validate_settings( array $settings );

	/**
	 * Render importer settings.
	 *
	 * This method should be overridden by child classes to display custom settings.
	 */
	public function render_settings() {
		echo '<p>' . esc_html__( 'This importer does not have any custom settings.', 'geodir-converter' ) . '</p>';
	}

	/**
	 * Displays the logs associated with the process.
	 *
	 * @param array $logs An array containing log entries.
	 */
	public function display_logs( array $logs = array() ) {
		echo '<ul class="geodir-converter-logs ps-0 pe-0">';
		foreach ( $logs as $log ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $this->log_to_html( $log );
		}
		echo '</ul>';
	}

	/**
	 * Displays the progress of the process.
	 */
	public function display_progress() {
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<div class="geodir-converter-progress mt-3 mb-3 d-none">';
		echo '<div class="progress">';
		echo '<div class="progress-bar" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div>';
		echo '</div>';
		echo '</div>';
	}

	/**
	 * Display the post type selection dropdown.
	 *
	 * @since 2.0.2
	 */
	public function display_post_type_select() {
		$post_type_options = geodir_get_posttypes( 'options' );
		$gd_post_type      = $this->get_import_setting( 'gd_post_type' );
		$new_cpt_url       = add_query_arg(
			array(
				'page' => 'gd-settings',
				'tab'  => 'cpts',
			),
			admin_url( 'admin.php' )
		);

		aui()->select(
			array(
				'id'         => 'gd_post_type',
				'name'       => 'gd_post_type',
				'label'      => esc_html__( 'GD Post Type', 'geodirectory' ),
				'label_type' => 'top',
				'value'      => $gd_post_type,
				'options'    => $post_type_options,
				'help_text'  => wp_kses_post(
					sprintf(
					/* translators: %s is the link to create a new post type */
						__( 'Choose the post type to assign imported listings to. <a href="%s" target="_blank">Create a new post type</a>.', 'geodir-converter' ),
						esc_url( $new_cpt_url )
					)
				),
			),
			true
		);
	}

	/**
	 * Display the test mode toggle checkbox.
	 *
	 * @since 2.0.2
	 */
	public function display_test_mode_checkbox() {
		$is_test_mode = (bool) $this->is_test_mode();

		aui()->input(
			array(
				'id'         => 'test_mode',
				'type'       => 'checkbox',
				'name'       => 'test_mode',
				'label_type' => 'top',
				'label'      => esc_html__( 'Test Mode', 'geodirectory' ),
				'checked'    => $is_test_mode,
				'value'      => 'yes',
				'switch'     => 'md',
				'help_text'  => esc_html__( 'Run a test import without importing any data.', 'geodirectory' ),
			),
			true
		);
	}

	/**
	 * Displays an error alert.
	 *
	 * @since 2.0.2
	 *
	 * @param string $message Optional. Error message to display. Defaults to empty.
	 */
	public function display_error_alert( $message = '' ) {
		$message = ! empty( $message ) ? esc_html( $message ) : '';
		?>
		<div class="alert alert-danger geodir-converter-error d-none">
			<?php
			echo $message; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
			?>
		</div>
		<?php
	}

	/**
	 * Get the batch size for importing listings.
	 *
	 * @return int The batch size.
	 */
	public function get_batch_size() {
		return (int) apply_filters( "geodir_converter_{$this->importer_id}_batch_size", self::BATCH_SIZE );
	}

	/**
	 * Check if the importer is in test mode.
	 *
	 * @return bool True if in test mode, false otherwise.
	 */
	protected function is_test_mode() {
		return $this->get_import_setting( 'test_mode', 'no' ) === 'yes';
	}

	/**
	 * Get a saved setting from import_settings option.
	 *
	 * @param string $key     The setting key to retrieve.
	 * @param mixed  $default Optional. Default value to return if the setting does not exist.
	 * @return mixed The setting value or default if not found.
	 */
	protected function get_import_setting( $key, $default = null ) {
		$settings = (array) $this->options_handler->get_option_no_cache( 'import_settings', array() );
		if ( isset( $settings[ $key ] ) ) {
			return $settings[ $key ];
		}

		return $default;
	}

	/**
	 * Get a saved setting from import_settings option.
	 *
	 * @param mixed $default Optional. Default value to return if the setting does not exist.
	 * @return mixed The setting value or default if not found.
	 */
	protected function get_import_post_type( $default = 'gd_place' ) {
		$post_type = $this->get_import_setting( 'gd_post_type', $default );

		return $post_type;
	}

	/**
	 * Check if a field should be skipped during import.
	 *
	 * @param string $field_name The field name to check.
	 * @return bool True if the field should be skipped, false otherwise.
	 */
	protected function should_skip_field( $field_name ) {
		$preserved_keys = array(
			'ID',
			'post_author',
			'post_date',
			'post_date_gmt',
			'post_excerpt',
			'post_status',
			'comment_status',
			'ping_status',
			'post_password',
			'post_name',
			'to_ping',
			'pinged',
			'post_modified',
			'post_modified_gmt',
			'post_content_filtered',
			'post_parent',
			'guid',
			'menu_order',
			'post_type',
			'post_mime_type',
			'comment_count',
			'geodir_search',
			'type',
			'near',
			'geo_lat',
			'geo_lon',
			'action',
			'security',
			'preview',
			'post_images',
			'featured_image',
			'address',
			'city',
			'region',
			'country',
			'neighbourhood',
			'zip',
			'latitude',
			'longitude',
			'mapview',
			'mapzoom',
			'street'
		);

		if ( in_array( $field_name, $preserved_keys, true ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Get the default location data.
	 *
	 * @return array The default location data.
	 */
	protected function get_default_location() {
		global $geodirectory;

		$default_location = $geodirectory->location->get_default_location();

		return array(
			'city'      => isset( $default_location->city ) ? $default_location->city : '',
			'region'    => isset( $default_location->region ) ? $default_location->region : '',
			'country'   => isset( $default_location->country ) ? $default_location->country : '',
			'latitude'  => isset( $default_location->latitude ) ? $default_location->latitude : '',
			'longitude' => isset( $default_location->longitude ) ? $default_location->longitude : '',
		);
	}

	/**
	 * Sorts an array by priority value.
	 *
	 * @param array $a First array to compare.
	 * @param array $b Second array to compare.
	 * @return int Sorting order: -1, 0, or 1.
	 */
	protected function sort_by_priority( $a, $b ) {
		$a_priority = isset( $a['priority'] ) ? (float) $a['priority'] : 100000;
		$b_priority = isset( $b['priority'] ) ? (float) $b['priority'] : 100000;

		if ( $a_priority === $b_priority ) {
			return 0;
		}

		return ( $a_priority < $b_priority ) ? -1 : 1;
	}

	/**
	 * Get package IDs for a given post type.
	 *
	 * @param string $post_type The post type.
	 * @return array|string Array of package IDs or empty string if no packages.
	 */
	protected function get_package_ids( $post_type ) {
		$package_ids = array();

		if ( function_exists( 'geodir_pricing_get_packages' ) ) {
			$packages = geodir_pricing_get_packages( array( 'post_type' => $post_type ) );

			if ( ! empty( $packages ) && is_array( $packages ) ) {
				$package_ids = wp_list_pluck( $packages, 'id' );
			}
		}

		return $package_ids;
	}

	/**
	 * Format image data for GeoDirectory.
	 *
	 * @param array $images Images (single or multiple).
	 * @return string Formatted image data.
	 */
	protected function format_images_data( $images ) {
		$attachments = array();

		foreach ( $images as $index => $attachment ) {
			$attachment_id = isset( $attachment['id'] ) ? absint( $attachment['id'] ) : 0;

			// Skip invalid or non-image attachments.
			if ( ! $attachment_id || ! wp_attachment_is_image( $attachment_id ) ) {
				continue;
			}

			$attachments[] = array(
				'url'     => wp_get_attachment_url( $attachment_id ),
				'title'   => get_the_title( $attachment_id ),
				'caption' => isset( $attachment['caption'] ) ? sanitize_text_field( $attachment['caption'] ) : '',
				'weight'  => isset( $attachment['weight'] ) ? $attachment['weight'] : $index,
			);
		}

		if ( empty( $attachments ) ) {
			return '';
		}

		// Sort attachments by weight.
		usort(
			$attachments,
			function ( $a, $b ) {
				return $a['weight'] - $b['weight'];
			}
		);

		$formatted_images = array();

		foreach ( $attachments as $attachment ) {
			$formatted_images[] = sprintf(
				'%s||%s|%s',
				esc_url( $attachment['url'] ),
				esc_html( $attachment['title'] ),
				esc_html( $attachment['caption'] )
			);
		}

		return implode( '::', $formatted_images );
	}

	/**
	 * Import taxonomy terms.
	 *
	 * @param array  $terms     Array of terms to import.
	 * @param string $taxonomy  The taxonomy to import terms into.
	 * @param string $desc_meta_key  The meta key for storing term description.
	 * @return array Result of the import operation.
	 */
	protected function import_taxonomy_terms( $terms, $taxonomy, $desc_meta_key = 'ct_cat_top_desc' ) {
		$imported = 0;
		$failed   = 0;

		if ( empty( $terms ) ) {
			return compact( 'imported', 'failed' );
		}

		foreach ( $terms as $term ) {
			$args = array(
				'description' => $term->description,
				'slug'        => $term->slug,
			);

			// Handle parent terms.
			if ( ! empty( $term->parent ) ) {
				$parent = get_term_meta( $term->parent, 'gd_equivalent', true );
				if ( $parent ) {
					$args['parent'] = $parent;
				}
			}

			if ( ! $this->is_test_mode() ) {
				$id = term_exists( $term->slug, $taxonomy );
				if ( ! $id ) {
					$id = wp_insert_term( $term->name, $taxonomy, $args );
				}

				if ( is_wp_error( $id ) ) {
					++$failed;
					$this->log(
						sprintf(
							/* translators: %1$s: term name, %2$s: error message */
							esc_html__( 'Taxonomy error with "%1$s": %2$s', 'geodir-converter' ),
							esc_html( $term->name ),
							esc_html( $id->get_error_message() )
						),
						'error'
					);
					continue;
				}

				$term_id = is_array( $id ) ? $id['term_id'] : $id;

				if ( ! empty( $term->description ) ) {
					update_term_meta( $term_id, $desc_meta_key, $term->description );
				}

				update_term_meta( $term->term_id, 'gd_equivalent', $term_id );
			}

			++$imported;
		}

		return compact( 'imported', 'failed' );
	}

	/**
	 * Start the import process.
	 *
	 * @param array $settings The import settings.
	 * @return array The result of the import process.
	 */
	public function import( array $settings ) {
		// Validate and sanitize settings.
		$settings = $this->validate_settings( $settings );

		if ( is_wp_error( $settings ) ) {
			return $settings;
		}

		// reset all importer options.
		$this->clear_import_options();

		$this->options_handler->update_option( 'import_settings', $settings );

		if ( $this->is_test_mode() ) {
			$this->log( esc_html__( 'Test mode is enabled. No data will be imported.', 'geodir-converter' ), 'error' );
		}

		// Start the background process.
		$this->background_process->add_import_tasks(
			array(
				'importer_id' => $this->importer_id,
				'settings'    => $settings,
			)
		);
	}

	/**
	 * Increases the total count of imports by the specified increment.
	 *
	 * @param int $increment The amount by which to increase the total imports count.
	 */
	public function increase_imports_total( $increment ) {
		$this->increase_field( 'total', $increment );
	}

	/**
	 * Increases the count of successful imports by the specified increment.
	 *
	 * @param int $increment The amount by which to increase the successful imports count.
	 */
	public function increase_succeed_imports( $increment ) {
		$this->increase_field( 'succeed', $increment );
	}

	/**
	 * Increases the count of skipped imports by the specified increment.
	 *
	 * @param int $increment The amount by which to increase the skipped imports count.
	 */
	public function increase_skipped_imports( $increment ) {
		$this->increase_field( 'skipped', $increment );
	}

	/**
	 * Increases the count of failed imports by the specified increment.
	 *
	 * @param int $increment The amount by which to increase the failed imports count.
	 */
	public function increase_failed_imports( $increment ) {
		$this->increase_field( 'failed', $increment );
	}

	/**
	 * Increases the value of a specific field by the specified increment in the database.
	 *
	 * @param string $field The name of the field to increase.
	 * @param int    $increment The amount by which to increase the field's value.
	 */
	protected function increase_field( $field, $increment ) {
		$stats       = (array) $this->options_handler->get_option_no_cache( 'stats' );
		$empty_stats = self::empty_stats();
		$stats       = wp_parse_args( $stats, $empty_stats );

		$stats[ $field ] = (int) $stats[ $field ] + $increment;

		$this->options_handler->update_option( 'stats', (array) $stats );
	}

	/**
	 * Retrieves the statistics for the current queue ID.
	 *
	 * @return array An array containing statistics (total, succeed, skipped, failed, removed).
	 */
	public function get_stats() {
		$stats       = (array) $this->options_handler->get_option_no_cache( 'stats' );
		$empty_stats = self::empty_stats();
		$stats       = wp_parse_args( $stats, $empty_stats );

		return array(
			'total'   => (int) $stats['total'],
			'succeed' => (int) $stats['succeed'],
			'skipped' => (int) $stats['skipped'],
			'failed'  => (int) $stats['failed'],
		);
	}

	/**
	 * Returns an array representing empty statistics, with all counts initialized to 0.
	 *
	 * @return array An array containing empty statistics.
	 */
	public function empty_stats() {
		return array(
			'total'   => 0,
			'succeed' => 0,
			'skipped' => 0,
			'failed'  => 0,
		);
	}

	/**
	 * Get the import progress as a percentage.
	 *
	 * @return float
	 */
	public function get_progress() {
		$stats = $this->get_stats();

		$total     = (int) $stats['total'];
		$processed = $stats['succeed'] + $stats['skipped'] + $stats['failed'];

		if ( $total == 0 ) {
			return $this->background_process->is_in_progress() ? 0 : 100;
		} else {
			return $this->background_process->is_in_progress() ? min( round( $processed / $total * 100 ), 100 ) : 100;
		}
	}

	/**
	 * Clear all import-related options.
	 */
	protected function clear_import_options() {
		$this->options_handler->delete_option( 'stats' );
		$this->options_handler->delete_option( 'import_log' );
		$this->options_handler->delete_option( 'import_settings' );
	}

	/**
	 * Check if a listing has already been imported.
	 *
	 * @since 2.0.2
	 *
	 * @param int    $listing_id    The original listing ID.
	 * @param string $meta_key    The meta key to search for.
	 * @param string $post_type   The post type to search within. Default 'gd_place'.
	 * @return int|false The existing GD post ID if found, false otherwise.
	 */
	public function get_gd_listing_id( $listing_id, $meta_key, $post_type = 'gd_place' ) {
		global $wpdb, $plugin_prefix;

		$details_table = $plugin_prefix . $post_type . '_detail';

		$gd_post_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT pd.post_id
                FROM {$details_table} pd
                INNER JOIN {$wpdb->posts} p ON p.ID = pd.post_id
                WHERE pd.{$meta_key} = %s
                AND p.post_type = %s
                LIMIT 1",
				$listing_id,
				$post_type
			)
		);

		return $gd_post_id ? (int) $gd_post_id : false;
	}

	/**
	 * Check if a post has already been imported.
	 *
	 * @since 2.0.2
	 *
	 * @param int    $post_id    The original post ID.
	 * @param string $meta_key   The meta key to search for.
	 * @return int|false The existing GD post ID if found, false otherwise.
	 */
	public function get_gd_post_id( $post_id, $meta_key ) {
		global $wpdb;

		$gd_post_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT pm.post_id
                FROM {$wpdb->postmeta} pm
                INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                WHERE pm.meta_key = %s
                AND pm.meta_value = %d
                LIMIT 1",
				$meta_key,
				$post_id
			)
		);

		return $gd_post_id ? (int) $gd_post_id : false;
	}

	/**
	 * Retrieves the import log with optional skipping.
	 *
	 * @param int $skip_logs Number of logs to skip. Defaults to 0.
	 * @return array The filtered import log.
	 */
	public function get_logs( $skip_logs = 0 ) {
		$logs = $this->options_handler->get_option_no_cache( 'import_log', array() );

		if ( ! is_array( $logs ) ) {
			return array();
		}

		$skip_logs = max( 0, (int) $skip_logs );

		return array_slice( $logs, $skip_logs );
	}

	/**
	 * Log a message.
	 *
	 * @param string $message The message to log.
	 * @param string $status The status of log message (info, warning, error).
	 */
	public function log( $message, $status = 'info' ) {
		$logs   = $this->options_handler->get_option_no_cache( 'import_log', array() );
		$logs[] = array(
			'message' => $message,
			'status'  => $status,
		);

		$this->options_handler->update_option( 'import_log', $logs );
	}

	/**
	 * Converts a log entry into HTML format.
	 *
	 * @param array $log Log entry ["status", "message"].
	 * @param bool  $inline Indicates whether the log should be displayed inline.
	 * @return string HTML representation of the log entry.
	 */
	public function log_to_html( array $log, bool $inline = false ) {
		$log += array(
			'status'  => 'info',
			'message' => '',
		);

		$html = '';

		if ( ! empty( $log['message'] ) && ! $inline ) {
			$html .= '<li>';
			$html .= '<p class="notice notice-' . esc_attr( $log['status'] ) . ' ms-0 me-0 mb-2">';
			$html .= esc_html( $log['message'] );
			$html .= '</p>';
			$html .= '</li>';
		} else {
			$html .= esc_html( $log['message'] );
		}

		return $html;
	}

	/**
	 * Converts an array of logs into HTML format.
	 *
	 * @param array $logs An array of log entries.
	 * @param bool  $inline Indicates whether the logs should be displayed inline.
	 * @return array HTML representations of the log entries.
	 */
	public function logs_to_html( array $logs, bool $inline = false ) {
		$logs_html = array();
		foreach ( $logs as $log ) {
			$logs_html[] = $this->log_to_html( $log, $inline );
		}
		return $logs_html;
	}
}
