<?php
/**
 * Directories Pro Converter Class.
 *
 * @since     2.2.0
 * @package   GeoDir_Converter
 */

namespace GeoDir_Converter\Importers;

use WP_Error;
use GeoDir_Media;
use GeoDir_Comments;
use GeoDir_Converter\GeoDir_Converter_Utils;
use GeoDir_Converter\Abstracts\GeoDir_Converter_Importer;

defined( 'ABSPATH' ) || exit;

/**
 * Main converter class for importing from Directories Pro.
 *
 * @since 2.2.0
 */
class GeoDir_Converter_Directories_Pro extends GeoDir_Converter_Importer {

	/**
	 * Action identifier for import bundles.
	 *
	 * @var string
	 */
	const ACTION_IMPORT_BUNDLES = 'import_bundles';

	/**
	 * Action identifier for import reviews.
	 *
	 * @var string
	 */
	const ACTION_IMPORT_REVIEWS = 'import_reviews';

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
	protected $importer_id = 'directories_pro';

	/**
	 * The import listing status ID.
	 *
	 * @var array
	 */
	protected $post_statuses = array( 'publish', 'pending', 'draft', 'private' );

	/**
	 * Batch size for processing items.
	 *
	 * @var int
	 */
	private $batch_size = 50;

	/**
	 * Schema types for field storage.
	 *
	 * @var array
	 */
	private $schema_types = array( 'string', 'text', 'integer', 'decimal', 'boolean', 'user', 'video', 'file', 'date' );

	/**
	 * Initialize hooks.
	 *
	 * @since 2.2.0
	 */
	protected function init() {
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
	 * @since 2.2.0
	 * @return string
	 */
	public function get_title() {
		return __( 'Directories Pro', 'geodir-converter' );
	}

	/**
	 * Get importer description.
	 *
	 * @since 2.2.0
	 * @return string
	 */
	public function get_description() {
		return __( 'Import listings, categories, custom fields, and reviews from your Directories Pro installation.', 'geodir-converter' );
	}

	/**
	 * Get importer icon URL.
	 *
	 * @since 2.2.0
	 * @return string
	 */
	public function get_icon() {
		return GEODIR_CONVERTER_PLUGIN_URL . 'assets/images/directories-pro.png';
	}

	/**
	 * Get importer task action.
	 *
	 * @since 2.2.0
	 * @return string
	 */
	public function get_action() {
		return self::ACTION_IMPORT_BUNDLES;
	}

	/**
	 * Render importer settings.
	 *
	 * @since 2.2.0
	 */
	public function render_settings() {
		$drts_bundles = $this->get_available_bundles();

		if ( empty( $drts_bundles ) ) {
			?>
			<div class="notice notice-error">
				<p><?php esc_html_e( 'No Directories Pro bundles found. Please ensure Directories Pro is installed and has created content.', 'geodir-converter' ); ?></p>
			</div>
			<?php
			return;
		}
		?>
		<form class="geodir-converter-settings-form" method="post">
			<h6 class="fs-base"><?php esc_html_e( 'Directories Pro Importer Settings', 'geodir-converter' ); ?></h6>
			<?php
			if ( ! class_exists( 'GeoDir_CP' ) ) {
				$this->render_plugin_notice( esc_html__( 'GeoDirectory Custom Post Types', 'geodir-converter' ), 'posttypes', esc_url( 'https://wpgeodirectory.com/downloads/custom-post-types/' ) );
			}

			$this->render_setting_gd_post_type();
			$this->render_setting_drts_bundle( $drts_bundles );
			$this->render_setting_default_author();
			$this->render_setting_default_category();
			$this->render_actions();
			?>
		</form>
		<?php
	}

	/**
	 * Validate importer settings.
	 *
	 * @since 2.2.0
	 *
	 * @param array $settings Settings to validate.
	 * @return array Array of errors, empty if valid.
	 */
	public function validate_settings( $settings ) {
		$errors = array();

		if ( empty( $settings['gd_post_type'] ) ) {
			$errors[] = __( 'Please select a GeoDirectory post type.', 'geodir-converter' );
		}

		if ( empty( $settings['drts_bundle'] ) ) {
			$errors[] = __( 'Please select a Directories Pro bundle to import from.', 'geodir-converter' );
		}

		if ( empty( $settings['wp_author_id'] ) ) {
			$errors[] = __( 'Please select a default author.', 'geodir-converter' );
		}

		return $errors;
	}

	/**
	 * Get next task to process.
	 *
	 * @since 2.2.0
	 *
	 * @param string $current_action Current action being processed.
	 * @return string Next action to process.
	 */
	public function next_task( $current_action = '' ) {
		switch ( $current_action ) {
			case '':
				return self::ACTION_IMPORT_BUNDLES;
			case self::ACTION_IMPORT_BUNDLES:
				return self::ACTION_IMPORT_CATEGORIES;
			case self::ACTION_IMPORT_CATEGORIES:
				return self::ACTION_IMPORT_TAGS;
			case self::ACTION_IMPORT_TAGS:
				return self::ACTION_IMPORT_FIELDS;
			case self::ACTION_IMPORT_FIELDS:
				return self::ACTION_PARSE_LISTINGS;
			case self::ACTION_PARSE_LISTINGS:
				return self::ACTION_IMPORT_LISTINGS;
			case self::ACTION_IMPORT_LISTINGS:
				return self::ACTION_IMPORT_REVIEWS;
			case self::ACTION_IMPORT_REVIEWS:
			default:
				return '';
		}
	}

	/**
	 * Set total import count.
	 *
	 * @since 2.2.0
	 * @return int Total items to import.
	 */
	public function set_import_total() {
		$settings    = $this->get_settings();
		$bundle_name = ! empty( $settings['drts_bundle'] ) ? $settings['drts_bundle'] : '';

		if ( empty( $bundle_name ) ) {
			return 0;
		}

		$total = 0;
		$total += $this->count_categories( $bundle_name );
		$total += $this->count_tags( $bundle_name );
		$total += $this->count_custom_fields( $bundle_name );
		$total += $this->count_listings( $bundle_name );

		return $total;
	}

	/**
	 * Task: Import bundle information.
	 *
	 * @since 2.2.0
	 * @return bool
	 */
	public function task_import_bundles() {
		$this->log( sprintf( self::LOG_TEMPLATE_STARTED, 'Bundle Information' ) );

		$settings    = $this->get_settings();
		$bundle_name = ! empty( $settings['drts_bundle'] ) ? $settings['drts_bundle'] : '';

		if ( empty( $bundle_name ) ) {
			$this->log( 'ERROR: No bundle selected.' );
			return false;
		}

		$bundle_info = $this->get_bundle_info( $bundle_name );
		if ( $bundle_info ) {
			$this->log( 'Bundle: ' . $bundle_info['name'] );
			$this->log( 'Type: ' . $bundle_info['type'] );
			$this->log( 'Post Type: ' . $bundle_info['post_type'] );

			$this->update_option( 'bundle_info', $bundle_info );
		}

		$this->log( 'Bundle information collected successfully.' );
		return true;
	}

	/**
	 * Task: Import categories.
	 *
	 * @since 2.2.0
	 * @return bool
	 */
	public function task_import_categories() {
		$this->log( sprintf( self::LOG_TEMPLATE_STARTED, 'Categories' ) );

		$settings     = $this->get_settings();
		$bundle_name  = ! empty( $settings['drts_bundle'] ) ? $settings['drts_bundle'] : '';
		$gd_post_type = ! empty( $settings['gd_post_type'] ) ? $settings['gd_post_type'] : '';

		if ( empty( $bundle_name ) || empty( $gd_post_type ) ) {
			return false;
		}

		$bundle_info       = $this->get_option( 'bundle_info', array() );
		$category_taxonomy = $this->get_category_taxonomy( $bundle_name, $bundle_info );

		if ( empty( $category_taxonomy ) ) {
			$this->log( 'No category taxonomy found.' );
			return true;
		}

		$gd_taxonomy = $gd_post_type . 'category';
		$terms       = get_terms(
			array(
				'taxonomy'   => $category_taxonomy,
				'hide_empty' => false,
				'orderby'    => 'term_id',
			)
		);

		if ( is_wp_error( $terms ) ) {
			$this->log( 'ERROR: ' . $terms->get_error_message() );
			return false;
		}

		$imported = $this->import_taxonomy_terms( $terms, $gd_taxonomy, $gd_post_type );
		$this->log( sprintf( self::LOG_TEMPLATE_FINISHED, 'Categories', count( $terms ), $imported, 0, count( $terms ) - $imported, 0 ) );

		return true;
	}

	/**
	 * Task: Import tags.
	 *
	 * @since 2.2.0
	 * @return bool
	 */
	public function task_import_tags() {
		$this->log( sprintf( self::LOG_TEMPLATE_STARTED, 'Tags' ) );

		$settings     = $this->get_settings();
		$bundle_name  = ! empty( $settings['drts_bundle'] ) ? $settings['drts_bundle'] : '';
		$gd_post_type = ! empty( $settings['gd_post_type'] ) ? $settings['gd_post_type'] : '';

		if ( empty( $bundle_name ) || empty( $gd_post_type ) ) {
			return false;
		}

		$bundle_info   = $this->get_option( 'bundle_info', array() );
		$tag_taxonomy = $this->get_tag_taxonomy( $bundle_name, $bundle_info );

		if ( empty( $tag_taxonomy ) ) {
			$this->log( 'No tag taxonomy found.' );
			return true;
		}

		$gd_taxonomy = $gd_post_type . '_tags';
		$terms       = get_terms(
			array(
				'taxonomy'   => $tag_taxonomy,
				'hide_empty' => false,
				'orderby'    => 'term_id',
			)
		);

		if ( is_wp_error( $terms ) ) {
			$this->log( 'ERROR: ' . $terms->get_error_message() );
			return false;
		}

		$imported = $this->import_taxonomy_terms( $terms, $gd_taxonomy, $gd_post_type );
		$this->log( sprintf( self::LOG_TEMPLATE_FINISHED, 'Tags', count( $terms ), $imported, 0, count( $terms ) - $imported, 0 ) );

		return true;
	}

	/**
	 * Task: Import custom fields.
	 *
	 * @since 2.2.0
	 * @return bool
	 */
	public function task_import_fields() {
		$this->log( sprintf( self::LOG_TEMPLATE_STARTED, 'Custom Fields' ) );

		$settings     = $this->get_settings();
		$bundle_name  = ! empty( $settings['drts_bundle'] ) ? $settings['drts_bundle'] : '';
		$gd_post_type = ! empty( $settings['gd_post_type'] ) ? $settings['gd_post_type'] : '';

		if ( empty( $bundle_name ) || empty( $gd_post_type ) ) {
			return false;
		}

		$custom_fields = $this->get_custom_fields( $bundle_name, $gd_post_type );

		if ( empty( $custom_fields ) ) {
			$this->log( 'No custom fields found.' );
			return true;
		}

		$created = 0;
		foreach ( $custom_fields as $field_name => $field_data ) {
			if ( $this->field_exists( $gd_post_type, $field_name ) ) {
				continue;
			}

			$result = geodir_custom_field_save( $field_data );
			if ( $result ) {
				$created++;
				$this->increase_imports_total();
			}
		}

		$this->log( sprintf( self::LOG_TEMPLATE_FINISHED, 'Custom Fields', count( $custom_fields ), $created, 0, count( $custom_fields ) - $created, 0 ) );

		return true;
	}

	/**
	 * Task: Parse listings for import.
	 *
	 * @since 2.2.0
	 * @return bool
	 */
	public function task_parse_listings() {
		global $wpdb;

		$this->log( sprintf( self::LOG_TEMPLATE_STARTED, 'Listings Parse' ) );

		$settings    = $this->get_settings();
		$bundle_name = ! empty( $settings['drts_bundle'] ) ? $settings['drts_bundle'] : '';

		if ( empty( $bundle_name ) ) {
			return false;
		}

		$bundle_info = $this->get_option( 'bundle_info', array() );
		$post_type   = ! empty( $bundle_info['post_type'] ) ? $bundle_info['post_type'] : '';

		if ( empty( $post_type ) ) {
			return false;
		}

		$offset = 0;

		$total_listings = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s", $post_type ) );

		$this->log( 'Found ' . $total_listings . ' listings to parse.' );

		while ( $offset < $total_listings ) {
			$listings = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s ORDER BY ID ASC LIMIT %d OFFSET %d",
					$post_type,
					$this->batch_size,
					$offset
				)
			);

			if ( empty( $listings ) ) {
				break;
			}

			foreach ( $listings as $listing ) {
				$this->add_task( self::ACTION_IMPORT_LISTINGS, array( 'drts_listing_id' => $listing->ID ) );
			}

			$offset += $this->batch_size;
		}

		$this->log( 'Parsed ' . $total_listings . ' listings for import.' );

		return true;
	}

	/**
	 * Task: Import single listing.
	 *
	 * @since 2.2.0
	 *
	 * @param array $task Task data.
	 * @return bool
	 */
	public function task_import_listings( $task ) {
		if ( empty( $task['drts_listing_id'] ) ) {
			$this->increase_failed_imports();
			return false;
		}

		$drts_listing_id = absint( $task['drts_listing_id'] );
		$settings        = $this->get_settings();
		$gd_post_type    = ! empty( $settings['gd_post_type'] ) ? $settings['gd_post_type'] : '';

		if ( empty( $gd_post_type ) ) {
			$this->increase_failed_imports();
			return false;
		}

		$gd_post_id = $this->get_gd_listing_id( $drts_listing_id );
		if ( $gd_post_id ) {
			$this->increase_skipped_imports();
			return true;
		}

		$post = get_post( $drts_listing_id );
		if ( ! $post ) {
			$this->increase_failed_imports();
			return false;
		}

		$post_data = array(
			'post_title'   => $post->post_title,
			'post_content' => $post->post_content,
			'post_excerpt' => $post->post_excerpt,
			'post_status'  => $post->post_status,
			'post_author'  => $post->post_author ? $post->post_author : $settings['wp_author_id'],
			'post_date'    => $post->post_date,
			'post_type'    => $gd_post_type,
		);

		wp_suspend_cache_addition( true );
		$new_post_id = wp_insert_post( $post_data );
		wp_suspend_cache_addition( false );

		if ( is_wp_error( $new_post_id ) || ! $new_post_id ) {
			$this->log( sprintf( self::LOG_TEMPLATE_FAILED, 'listing', $drts_listing_id . ' - ' . ( is_wp_error( $new_post_id ) ? $new_post_id->get_error_message() : 'Unknown error' ) ) );
			$this->increase_failed_imports();
			return false;
		}

		update_post_meta( $new_post_id, '_drts_listing_id', $drts_listing_id );

		$this->process_custom_fields( $drts_listing_id, $new_post_id, $gd_post_type );
		$this->process_images( $drts_listing_id, $new_post_id );
		$this->process_taxonomies( $drts_listing_id, $new_post_id, $gd_post_type );
		$this->add_comments_import_tasks( $drts_listing_id, $new_post_id );

		$this->increase_succeed_imports();

		return true;
	}

	/**
	 * Task: Import reviews for a listing.
	 *
	 * @since 2.2.0
	 *
	 * @param array $task Task data.
	 * @return bool
	 */
	public function task_import_reviews( $task ) {
		if ( empty( $task['reviews'] ) || empty( $task['gd_post_id'] ) ) {
			return true;
		}

		$reviews    = $task['reviews'];
		$gd_post_id = absint( $task['gd_post_id'] );

		$imported = 0;
		foreach ( $reviews as $review_data ) {
			$comment_data = array(
				'comment_post_ID'      => $gd_post_id,
				'comment_author'       => $review_data['comment_author'],
				'comment_author_email' => $review_data['comment_author_email'],
				'comment_author_url'   => $review_data['comment_author_url'],
				'comment_content'      => $review_data['comment_content'],
				'comment_date'         => $review_data['comment_date'],
				'comment_approved'     => $review_data['comment_approved'],
				'comment_parent'       => $review_data['comment_parent'],
				'user_id'              => $review_data['user_id'],
			);

			$comment_id = wp_insert_comment( $comment_data );
			if ( $comment_id && ! empty( $review_data['rating'] ) ) {
				update_comment_meta( $comment_id, 'geodir_overallrating', $review_data['rating'] );
				GeoDir_Comments::save_rating( $comment_id );
				$imported++;
			}
		}

		if ( $imported > 0 ) {
			wp_update_comment_count( $gd_post_id );
		}

		return true;
	}

	/**
	 * Render bundle selection setting.
	 *
	 * @since 2.2.0
	 *
	 * @param array $bundles Available bundles.
	 */
	private function render_setting_drts_bundle( $bundles ) {
		$settings = $this->get_settings();
		?>
		<div class="gdc-setting-row">
			<label for="drts_bundle" class="gdc-setting-label">
				<?php esc_html_e( 'Directories Pro Bundle', 'geodir-converter' ); ?>
			</label>
			<div class="gdc-setting-field">
				<select name="drts_bundle" id="drts_bundle" required>
					<option value=""><?php esc_html_e( '-- Select Bundle --', 'geodir-converter' ); ?></option>
					<?php foreach ( $bundles as $bundle_name => $bundle_info ) : ?>
						<option value="<?php echo esc_attr( $bundle_name ); ?>" <?php selected( ! empty( $settings['drts_bundle'] ) ? $settings['drts_bundle'] : '', $bundle_name ); ?>>
							<?php echo esc_html( $bundle_info['label'] . ' (' . $bundle_name . ') - ' . $bundle_info['count'] . ' listings' ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<p class="description"><?php esc_html_e( 'Select the Directories Pro bundle to import from.', 'geodir-converter' ); ?></p>
			</div>
		</div>
		<?php
	}

	/**
	 * Get available Directories Pro bundles.
	 *
	 * @since 2.2.0
	 * @return array
	 */
	private function get_available_bundles() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'drts_entity_bundle';

		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) !== $table_name ) {
			return array();
		}

		$results = $wpdb->get_results(
			"SELECT bundle_name, bundle_type, bundle_info, bundle_entitytype_name 
			FROM {$table_name} 
			WHERE bundle_entitytype_name = 'post' 
			ORDER BY bundle_name ASC"
		);

		if ( empty( $results ) ) {
			return array();
		}

		$bundles = array();
		foreach ( $results as $row ) {
			$bundle_info = maybe_unserialize( $row->bundle_info );
			$label       = ! empty( $bundle_info['label'] ) ? $bundle_info['label'] : $row->bundle_name;
			$post_type   = $row->bundle_name;
			$count       = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s", $post_type ) );

			$bundles[ $row->bundle_name ] = array(
				'name'      => $row->bundle_name,
				'type'      => $row->bundle_type,
				'label'     => $label,
				'count'     => $count,
				'info'      => $bundle_info,
				'post_type' => $post_type,
			);
		}

		return $bundles;
	}

	/**
	 * Get bundle information.
	 *
	 * @since 2.2.0
	 *
	 * @param string $bundle_name Bundle name.
	 * @return array|null
	 */
	private function get_bundle_info( $bundle_name ) {
		$bundles = $this->get_available_bundles();
		return ! empty( $bundles[ $bundle_name ] ) ? $bundles[ $bundle_name ] : null;
	}

	/**
	 * Get category taxonomy for bundle.
	 *
	 * @since 2.2.0
	 *
	 * @param string $bundle_name Bundle name.
	 * @param array  $bundle_info Bundle information.
	 * @return string
	 */
	private function get_category_taxonomy( $bundle_name, $bundle_info = array() ) {
		if ( empty( $bundle_info ) ) {
			$bundle_info = $this->get_bundle_info( $bundle_name );
		}

		if ( ! empty( $bundle_info['info']['taxonomies']['directory_cat_type'] ) ) {
			return $bundle_info['info']['taxonomies']['directory_cat_type'];
		}

		$possible_taxonomies = array(
			$bundle_name . '_cat',
			str_replace( '_listing', '_cat', $bundle_name ),
			'directory_cat',
		);

		foreach ( $possible_taxonomies as $tax ) {
			if ( taxonomy_exists( $tax ) ) {
				return $tax;
			}
		}

		return '';
	}

	/**
	 * Get tag taxonomy for bundle.
	 *
	 * @since 2.2.0
	 *
	 * @param string $bundle_name Bundle name.
	 * @param array  $bundle_info Bundle information.
	 * @return string
	 */
	private function get_tag_taxonomy( $bundle_name, $bundle_info = array() ) {
		if ( empty( $bundle_info ) ) {
			$bundle_info = $this->get_bundle_info( $bundle_name );
		}

		if ( ! empty( $bundle_info['info']['taxonomies']['directory_tag_type'] ) ) {
			return $bundle_info['info']['taxonomies']['directory_tag_type'];
		}

		$possible_taxonomies = array(
			$bundle_name . '_tag',
			str_replace( '_listing', '_tag', $bundle_name ),
			'directory_tag',
		);

		foreach ( $possible_taxonomies as $tax ) {
			if ( taxonomy_exists( $tax ) ) {
				return $tax;
			}
		}

		return '';
	}

	/**
	 * Count categories for bundle.
	 *
	 * @since 2.2.0
	 *
	 * @param string $bundle_name Bundle name.
	 * @return int
	 */
	private function count_categories( $bundle_name ) {
		$bundle_info       = $this->get_bundle_info( $bundle_name );
		$category_taxonomy = $this->get_category_taxonomy( $bundle_name, $bundle_info );

		if ( empty( $category_taxonomy ) ) {
			return 0;
		}

		return wp_count_terms( $category_taxonomy, array( 'hide_empty' => false ) );
	}

	/**
	 * Count tags for bundle.
	 *
	 * @since 2.2.0
	 *
	 * @param string $bundle_name Bundle name.
	 * @return int
	 */
	private function count_tags( $bundle_name ) {
		$bundle_info  = $this->get_bundle_info( $bundle_name );
		$tag_taxonomy = $this->get_tag_taxonomy( $bundle_name, $bundle_info );

		if ( empty( $tag_taxonomy ) ) {
			return 0;
		}

		return wp_count_terms( $tag_taxonomy, array( 'hide_empty' => false ) );
	}

	/**
	 * Count custom fields for bundle.
	 *
	 * @since 2.2.0
	 *
	 * @param string $bundle_name Bundle name.
	 * @return int
	 */
	private function count_custom_fields( $bundle_name ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'drts_entity_field';

		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) !== $table_name ) {
			return 0;
		}

		return absint( $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table_name} WHERE field_bundle_name = %s", $bundle_name ) ) );
	}

	/**
	 * Count listings for bundle.
	 *
	 * @since 2.2.0
	 *
	 * @param string $bundle_name Bundle name.
	 * @return int
	 */
	private function count_listings( $bundle_name ) {
		global $wpdb;

		$bundle_info = $this->get_bundle_info( $bundle_name );
		$post_type   = ! empty( $bundle_info['post_type'] ) ? $bundle_info['post_type'] : $bundle_name;

		return absint( $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s", $post_type ) ) );
	}

	/**
	 * Get custom fields for bundle.
	 *
	 * @since 2.2.0
	 *
	 * @param string $bundle_name  Bundle name.
	 * @param string $gd_post_type GeoDirectory post type.
	 * @return array
	 */
	private function get_custom_fields( $bundle_name, $gd_post_type ) {
		global $wpdb;

		$table_name   = $wpdb->prefix . 'drts_entity_field';
		$config_table = $wpdb->prefix . 'drts_entity_fieldconfig';

		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) !== $table_name ) {
			return array();
		}

		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $config_table ) ) !== $config_table ) {
			return array();
		}

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT f.*, fc.fieldconfig_type, fc.fieldconfig_settings, fc.fieldconfig_schema 
				FROM {$table_name} f 
				LEFT JOIN {$config_table} fc ON f.field_fieldconfig_name = fc.fieldconfig_name 
				WHERE f.field_bundle_name = %s",
				$bundle_name
			)
		);

		if ( empty( $results ) ) {
			return array();
		}

		$fields = array();

		foreach ( $results as $row ) {
			$field_data_raw        = maybe_unserialize( $row->field_data );
			$field_config_settings = maybe_unserialize( $row->fieldconfig_settings );
			$field_schema          = maybe_unserialize( $row->fieldconfig_schema );

			$field_name  = ! empty( $field_data_raw['name'] ) ? $field_data_raw['name'] : 'field_' . $row->field_id;
			$field_label = ! empty( $field_data_raw['label'] ) ? $field_data_raw['label'] : $field_name;
			$field_type  = $row->fieldconfig_type;

			$geodir_field_type = $this->map_field_type( $field_type, $field_schema );
			$data_type         = $this->map_data_type( $geodir_field_type );

			$fields[ $field_name ] = array(
				'post_type'          => $gd_post_type,
				'admin_title'        => $field_label,
				'frontend_title'     => $field_label,
				'field_type'         => $geodir_field_type,
				'data_type'          => $data_type,
				'htmlvar_name'       => $field_name,
				'is_active'          => 1,
				'default_value'      => '',
				'is_required'        => 0,
				'validation_pattern' => '',
				'validation_msg'     => '',
				'required_msg'       => '',
				'field_icon'         => '',
				'css_class'          => '',
				'cat_sort'           => 0,
				'for_admin_use'      => 0,
				'is_default'         => 0,
				'option_values'      => '',
				'show_in'            => '[detail],[more_info]',
				'packages'           => '',
				'field_type_key'     => '',
				'extra_fields'       => '',
			);
		}

		return $fields;
	}

	/**
	 * Map Directories Pro field type to GeoDirectory field type.
	 *
	 * @since 2.2.0
	 *
	 * @param string $drts_type   Directories Pro field type.
	 * @param array  $schema      Field schema.
	 * @return string
	 */
	private function map_field_type( $drts_type, $schema = array() ) {
		$type_map = array(
			'string'           => 'text',
			'text'             => 'textarea',
			'email'            => 'email',
			'url'              => 'url',
			'phone'            => 'phone',
			'number'           => 'text',
			'choice'           => 'select',
			'date'             => 'datepicker',
			'time'             => 'time',
			'boolean'          => 'checkbox',
			'wp_image'         => 'file',
			'video'            => 'url',
			'social_accounts'  => 'text',
			'range'            => 'text',
			'color'            => 'text',
			'location_address' => 'address',
		);

		return ! empty( $type_map[ $drts_type ] ) ? $type_map[ $drts_type ] : 'text';
	}

	/**
	 * Map GeoDirectory field type to data type.
	 *
	 * @since 2.2.0
	 *
	 * @param string $field_type GeoDirectory field type.
	 * @return string
	 */
	private function map_data_type( $field_type ) {
		$data_type_map = array(
			'textarea'   => 'TEXT',
			'html'       => 'TEXT',
			'url'        => 'VARCHAR',
			'email'      => 'VARCHAR',
			'phone'      => 'VARCHAR',
			'checkbox'   => 'TINYINT',
			'datepicker' => 'DATE',
			'time'       => 'TIME',
			'file'       => 'TEXT',
			'address'    => 'VARCHAR',
		);

		return ! empty( $data_type_map[ $field_type ] ) ? $data_type_map[ $field_type ] : 'VARCHAR';
	}

	/**
	 * Process custom fields for listing.
	 *
	 * @since 2.2.0
	 *
	 * @param int    $drts_listing_id Directories Pro listing ID.
	 * @param int    $gd_post_id      GeoDirectory post ID.
	 * @param string $gd_post_type    GeoDirectory post type.
	 */
	private function process_custom_fields( $drts_listing_id, $gd_post_id, $gd_post_type ) {
		global $wpdb;

		$bundle_name = $this->get_settings( 'drts_bundle' );

		foreach ( $this->schema_types as $schema_type ) {
			$table_name = $wpdb->prefix . 'drts_entity_field_' . $schema_type;

			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) !== $table_name ) {
				continue;
			}

			$results = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT field_name, value FROM {$table_name} 
					WHERE entity_type = 'post' 
					AND entity_id = %d 
					AND bundle_name = %s 
					ORDER BY field_name, weight ASC",
					$drts_listing_id,
					$bundle_name
				)
			);

			if ( empty( $results ) ) {
				continue;
			}

			$grouped_values = array();
			foreach ( $results as $row ) {
				if ( ! isset( $grouped_values[ $row->field_name ] ) ) {
					$grouped_values[ $row->field_name ] = array();
				}
				$grouped_values[ $row->field_name ][] = $row->value;
			}

			foreach ( $grouped_values as $field_name => $values ) {
				if ( empty( $values ) ) {
					continue;
				}

				$final_value = count( $values ) === 1 ? $values[0] : $values;
				update_post_meta( $gd_post_id, $field_name, $final_value );
			}
		}

		$this->process_location_data( $drts_listing_id, $gd_post_id );
	}

	/**
	 * Process location data for listing.
	 *
	 * @since 2.2.0
	 *
	 * @param int $drts_listing_id Directories Pro listing ID.
	 * @param int $gd_post_id      GeoDirectory post ID.
	 */
	private function process_location_data( $drts_listing_id, $gd_post_id ) {
		$location_fields = array(
			'location_address'   => 'post_address',
			'location_city'      => 'post_city',
			'location_state'     => 'post_region',
			'location_zip'       => 'post_zip',
			'location_country'   => 'post_country',
			'location_latitude'  => 'post_latitude',
			'location_longitude' => 'post_longitude',
		);

		foreach ( $location_fields as $drts_field => $gd_field ) {
			$value = get_post_meta( $drts_listing_id, $drts_field, true );
			if ( $value ) {
				update_post_meta( $gd_post_id, $gd_field, $value );
			}
		}
	}

	/**
	 * Process images for listing.
	 *
	 * @since 2.2.0
	 *
	 * @param int $drts_listing_id Directories Pro listing ID.
	 * @param int $gd_post_id      GeoDirectory post ID.
	 */
	private function process_images( $drts_listing_id, $gd_post_id ) {
		if ( has_post_thumbnail( $drts_listing_id ) ) {
			$thumbnail_id = get_post_thumbnail_id( $drts_listing_id );
			set_post_thumbnail( $gd_post_id, $thumbnail_id );
		}

		$attachments = get_attached_media( 'image', $drts_listing_id );
		if ( ! empty( $attachments ) ) {
			foreach ( $attachments as $attachment ) {
				wp_update_post(
					array(
						'ID'          => $attachment->ID,
						'post_parent' => $gd_post_id,
					)
				);
			}
		}
	}

	/**
	 * Process taxonomies for listing.
	 *
	 * @since 2.2.0
	 *
	 * @param int    $drts_listing_id Directories Pro listing ID.
	 * @param int    $gd_post_id      GeoDirectory post ID.
	 * @param string $gd_post_type    GeoDirectory post type.
	 */
	private function process_taxonomies( $drts_listing_id, $gd_post_id, $gd_post_type ) {
		$settings    = $this->get_settings();
		$bundle_name = ! empty( $settings['drts_bundle'] ) ? $settings['drts_bundle'] : '';
		$bundle_info = $this->get_option( 'bundle_info', array() );

		$category_taxonomy = $this->get_category_taxonomy( $bundle_name, $bundle_info );
		if ( ! empty( $category_taxonomy ) ) {
			$categories = wp_get_post_terms( $drts_listing_id, $category_taxonomy, array( 'fields' => 'ids' ) );
			if ( ! is_wp_error( $categories ) && ! empty( $categories ) ) {
				wp_set_object_terms( $gd_post_id, $categories, $gd_post_type . 'category' );
			}
		}

		$tag_taxonomy = $this->get_tag_taxonomy( $bundle_name, $bundle_info );
		if ( ! empty( $tag_taxonomy ) ) {
			$tags = wp_get_post_terms( $drts_listing_id, $tag_taxonomy, array( 'fields' => 'ids' ) );
			if ( ! is_wp_error( $tags ) && ! empty( $tags ) ) {
				wp_set_object_terms( $gd_post_id, $tags, $gd_post_type . '_tags' );
			}
		}
	}

	/**
	 * Add comments import tasks for listing.
	 *
	 * @since 2.2.0
	 *
	 * @param int $drts_listing_id Directories Pro listing ID.
	 * @param int $gd_post_id      GeoDirectory post ID.
	 */
	private function add_comments_import_tasks( $drts_listing_id, $gd_post_id ) {
		$comments = get_comments(
			array(
				'post_id' => $drts_listing_id,
				'status'  => 'all',
			)
		);

		if ( empty( $comments ) ) {
			return;
		}

		$reviews_data = array();
		foreach ( $comments as $comment ) {
			$rating = get_comment_meta( $comment->comment_ID, 'rating', true );
			if ( empty( $rating ) ) {
				$rating = get_comment_meta( $comment->comment_ID, '_drts_voting_rating', true );
			}

			$reviews_data[] = array(
				'comment_author'       => $comment->comment_author,
				'comment_author_email' => $comment->comment_author_email,
				'comment_author_url'   => $comment->comment_author_url,
				'comment_content'      => $comment->comment_content,
				'comment_date'         => $comment->comment_date,
				'comment_approved'     => $comment->comment_approved,
				'comment_parent'       => $comment->comment_parent,
				'user_id'              => $comment->user_id,
				'rating'               => $rating,
			);
		}

		if ( ! empty( $reviews_data ) ) {
			$this->add_task(
				self::ACTION_IMPORT_REVIEWS,
				array(
					'gd_post_id' => $gd_post_id,
					'reviews'    => $reviews_data,
				)
			);
		}
	}

	/**
	 * Get GeoDirectory listing ID from Directories Pro listing ID.
	 *
	 * @since 2.2.0
	 *
	 * @param int $drts_listing_id Directories Pro listing ID.
	 * @return int
	 */
	private function get_gd_listing_id( $drts_listing_id ) {
		global $wpdb;

		$gd_post_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_drts_listing_id' AND meta_value = %s LIMIT 1",
				$drts_listing_id
			)
		);

		return $gd_post_id ? absint( $gd_post_id ) : 0;
	}
}
