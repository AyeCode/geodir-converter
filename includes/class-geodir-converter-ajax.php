<?php
/**
 * Ajax Class for Geodir Converter.
 *
 * @since      2.0.2
 * @package    GeoDir_Converter
 * @version    2.0.2
 */

namespace GeoDir_Converter;

use WP_Error;
use GeoDir_Converter\Traits\GeoDir_Converter_Trait_Singleton;

defined( 'ABSPATH' ) || exit;

/**
 * Main ajax class for handling AJAX requests.
 *
 * @since 1.0.0
 */
class GeoDir_Converter_Ajax {
	use GeoDir_Converter_Trait_Singleton;

	/**
	 * Name of the nonce used for security verification.
	 *
	 * @var string
	 */
	protected $nonce_name = 'geodir_converter_nonce';

	/**
	 * Prefix used for AJAX action names.
	 *
	 * @var string
	 */
	protected $action_prefix = 'geodir_converter_';

	/**
	 * List of AJAX actions along with their details.
	 *
	 * @var array
	 */
	protected $ajax_actions = array(
		'progress' => array(
			'method' => 'GET',
		),
		'import'   => array(
			'method' => 'POST',
		),
		'abort'    => array(
			'method' => 'POST',
		),
	);

	/**
	 * Ajax constructor.
	 */
	public function __construct() {
		foreach ( $this->ajax_actions as $action => $details ) {
			$no_priv = isset( $details['no_priv'] ) ? $details['no_priv'] : false;
			$this->add_ajax_action( $action, $no_priv );
		}
	}

	/**
	 * Retrieves input data for processing AJAX requests.
	 *
	 * @param string $action The name of the AJAX action without the 'wp' prefix.
	 * @return array An array containing input data for the AJAX request.
	 */
	protected function get_request_input( $action ) {
		$method = isset( $this->ajax_actions[ $action ]['method'] ) ? $this->ajax_actions[ $action ]['method'] : '';

		switch ( $method ) {
			case 'GET':
				$input = $_GET;
				break;
			case 'POST':
				$input = $_POST;
				break;
			default:
				$input = $_REQUEST;
		}

		return $input;
	}

	/**
	 * Retrieve nonces for AJAX actions.
	 *
	 * @return array Nonces for AJAX actions.
	 */
	public function get_nonces() {
		$nonces = array();
		foreach ( $this->ajax_actions as $action_name => $details ) {
			$nonces[ $this->action_prefix . $action_name ] = wp_create_nonce( $this->action_prefix . $action_name );
		}

		return $nonces;
	}

	/**
	 * Add AJAX action hooks.
	 *
	 * @param string $action  AJAX action name.
	 * @param bool   $no_priv Whether the action is available for non-logged in users.
	 */
	public function add_ajax_action( $action, $no_priv = false ) {
		add_action( 'wp_ajax_' . $this->action_prefix . $action, array( $this, $action ) );

		if ( $no_priv ) {
			add_action( 'wp_ajax_nopriv_' . $this->action_prefix . $action, array( $this, $action ) );
		}
	}

	/**
	 * Check the validity of the nonce.
	 *
	 * @param string $action AJAX action name.
	 * @return bool True if the nonce is valid, otherwise false.
	 */
	protected function check_nonce( $action ) {
		if ( ! isset( $this->ajax_actions[ $action ] ) ) {
			return false;
		}

		$input = $this->get_request_input( $action );

		$nonce = isset( $input[ $this->nonce_name ] ) ? $input[ $this->nonce_name ] : '';

		return wp_verify_nonce( $nonce, $this->action_prefix . $action );
	}

	/**
	 * Verify the validity of the nonce.
	 *
	 * @param string $action AJAX action name.
	 */
	protected function verify_nonce( $action ) {
		if ( ! $this->check_nonce( $action ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Request does not pass security verification. Please refresh the page and try one more time.', 'geodir-converter' ),
				)
			);
		}
	}

	/**
	 * Get importer instance.
	 *
	 * @since 2.0.2
	 * @param string $importer_id The importer ID.
	 * @throws Exception If importer is not found.
	 * @return object
	 */
	private function get_importer( $importer_id ) {
		$importers = GeoDir_Converter::instance()->get_importers();

		if ( ! isset( $importers[ $importer_id ] ) ) {
			return new WP_Error( 'importer_not_found', __( 'Importer not found.', 'geodir-converter' ) );
		}

		return $importers[ $importer_id ];
	}

	/**
	 * Send JSON error response.
	 *
	 * @since 2.0.2
	 * @param string $message The error message.
	 * @return void
	 */
	private function send_json_error( $message ) {
		wp_send_json_error( array( 'message' => $message ) );
	}

	/**
	 * AJAX handler for starting the import process.
	 */
	public function import() {
		$this->verify_nonce( __FUNCTION__ );

		if ( ! current_user_can( 'manage_options' ) ) {
			$this->send_json_error( __( 'You do not have permission to perform this action.', 'geodir-converter' ) );
		}

		$importer_id = isset( $_POST['importerId'] ) ? sanitize_text_field( $_POST['importerId'] ) : '';
		$settings    = isset( $_POST['settings'] ) ? wp_unslash( $_POST['settings'] ) : array();

		$importer = $this->get_importer( $importer_id );

		if ( is_wp_error( $importer ) ) {
			$this->send_json_error( $importer->get_error_message() );
		}

		$result = $importer->import( $settings );

		if ( is_wp_error( $result ) ) {
			$this->send_json_error( $result->get_error_message() );
		}

		$progress = $importer->get_progress();

		wp_send_json_success(
			array(
				'progress' => (int) $progress,
				'complete' => $progress >= 100,
			)
		);
	}

	/**
	 * AJAX handler for getting import progress.
	 */
	public function progress() {
		$this->verify_nonce( __FUNCTION__ );

		if ( ! current_user_can( 'manage_options' ) ) {
			$this->send_json_error( __( 'You do not have permission to perform this action.', 'geodir-converter' ) );
		}

		$importer_id = isset( $_GET['importerId'] ) ? sanitize_text_field( $_GET['importerId'] ) : '';
		$logs_shown  = isset( $_GET['logsShown'] ) ? absint( $_GET['logsShown'] ) : '';

		$importer = $this->get_importer( $importer_id );

		if ( is_wp_error( $importer ) ) {
			$this->send_json_error( $importer->get_error_message() );
		}

		$progress    = $importer->get_progress();
		$in_progress = $importer->background_process->is_in_progress();
		$logs        = $importer->get_logs( $logs_shown );

		// Calculate new "logs_shown".
		$logs_shown += count( $logs );

		wp_send_json_success(
			array(
				'progress'   => $progress,
				'message'    => sprintf(
					__( 'Import progress: %d%%', 'geodir-converter' ),
					$progress
				),
				'logsShown'  => $logs_shown,
				'logs'       => $importer->logs_to_html( $logs ),
				'inProgress' => (bool) $in_progress,
			)
		);
	}

	/**
	 * AJAX handler for aborting the import process.
	 */
	public function abort() {
		$this->verify_nonce( __FUNCTION__ );

		if ( ! current_user_can( 'manage_options' ) ) {
			$this->send_json_error( __( 'You do not have permission to perform this action.', 'geodir-converter' ) );
		}

		$importer_id = isset( $_POST['importerId'] ) ? sanitize_text_field( $_POST['importerId'] ) : '';

		$importer = $this->get_importer( $importer_id );

		if ( is_wp_error( $importer ) ) {
			$this->send_json_error( $importer->get_error_message() );
		}

		// Abort the background process.
		$importer->background_process->abort();

		wp_send_json_success();
	}
}
