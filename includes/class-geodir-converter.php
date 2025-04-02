<?php
/**
 * Main Plugin class
 *
 * @since      2.0.2
 * @package    GeoDir_Converter
 * @version    2.0.2
 */

namespace GeoDir_Converter;

use GeoDir_Converter\Admin\GeoDir_Converter_Admin;
use GeoDir_Converter\Importers\GeoDir_Converter_PMD;
use GeoDir_Converter\Importers\GeoDir_Converter_Listify;
use GeoDir_Converter\Traits\GeoDir_Converter_Trait_Singleton;
use GeoDir_Converter\Importers\GeoDir_Converter_Business_Directory;

defined( 'ABSPATH' ) || exit;

/**
 * Class GeoDir_Converter
 *
 * Handles the core functionality of the Geodir Converter plugin.
 */
final class GeoDir_Converter {
	use GeoDir_Converter_Trait_Singleton;

	/**
	 * Ajax handler.
	 *
	 * @var GeoDir_Converter_Ajax
	 */
	public $ajax;

	/**
	 * Admin page.
	 *
	 * @var GeoDir_Converter_Admin
	 */
	public $admin;

	/**
	 * GeoDir_Converter constructor.
	 */
	private function __construct() {
		$this->init_hooks();
		$this->load_importers();

		$this->ajax = GeoDir_Converter_Ajax::instance();

		if ( is_admin() ) {
			$this->admin = GeoDir_Converter_Admin::instance();
		}
	}

	/**
	 * Initialize hooks.
	 *
	 * @since 1.0.0
	 */
	private function init_hooks() {
		add_action( 'plugins_loaded', array( $this, 'load_plugin_textdomain' ) );
		add_action( 'admin_init', array( $this, 'maybe_redirect_to_importer' ) );

		if ( is_admin() ) {
			add_filter( 'plugin_action_links', array( $this, 'plugin_action_links' ), 10, 2 );
		}
	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * @since  1.0.0
	 * @access private
	 */
	private function load_importers() {
		GeoDir_Converter_Listify::instance();
		GeoDir_Converter_PMD::instance();
		GeoDir_Converter_Business_Directory::instance();
	}

	/**
	 * Define constant if not already set.
	 *
	 * @param  string      $name
	 * @param  string|bool $value
	 */
	private function define( $name, $value ) {
		if ( ! defined( $name ) ) {
			define( $name, $value );
		}
	}

	/**
	 * Get script version for cache busting.
	 *
	 * @return string
	 */
	public function get_script_version() {
		return defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? time() : GEODIR_CONVERTER_VERSION;
	}

	/**
	 * Get script suffix based on debug mode.
	 *
	 * @return string
	 */
	public function get_script_suffix() {
		return defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
	}

	/**
	 * Load the plugin text domain for translation.
	 */
	public function load_plugin_textdomain() {
		load_plugin_textdomain(
			'geodir-converter',
			false,
			dirname( GEODIR_CONVERTER_PLUGIN_BASENAME ) . '/languages/'
		);
	}

	/**
	 * Adds a link to the plugin's admin page on the plugins overview screen
	 *
	 * @param array $links Array of plugin action links.
	 * @return array Modified array of plugin action links.
	 */
	public function plugin_action_links( $links, $file ) {
		if ( GEODIR_CONVERTER_PLUGIN_BASENAME === $file ) {
			$convert_link = sprintf(
				'<a href="%1$s" aria-label="%2$s">%3$s</a>',
				esc_url( $this->import_page_url() ),
				esc_attr__( 'Convert', 'geodir-converter' ),
				esc_html__( 'Convert', 'geodir-converter' )
			);

			$links['convert'] = $convert_link;
		}

		return $links;
	}

	/**
	 * Returns a url to the plugin's admin page
	 *
	 * @return string Admin page URL.
	 */
	public function import_page_url() {
		return add_query_arg(
			array(
				'page' => 'geodir-converter',
			),
			admin_url( 'tools.php' )
		);
	}

	/**
	 * Maybe redirect the user to the plugin's admin page
	 *
	 * @return void
	 */
	public function maybe_redirect_to_importer() {
		if ( '1' === get_transient( '_geodir_converter_installed' ) ) {
			delete_transient( '_geodir_converter_installed' );

			// Bail if activating from network, or bulk.
			if ( is_network_admin() || isset( $_GET['activate-multi'] ) ) {
				return;
			}

			// Redirect to the converter page.
			wp_redirect( esc_url( $this->import_page_url() ) );
			exit;
		}
	}

	/**
	 * Retrieves a list of all registered importers
	 *
	 * @return array List of registered importers.
	 */
	public function get_importers() {
		return apply_filters( 'geodir_converter_importers', array() );
	}
}
