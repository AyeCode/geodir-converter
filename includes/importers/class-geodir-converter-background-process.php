<?php
/**
 * Background Process for GeoDir Converter
 *
 * @package GeoDir_Converter
 * @subpackage Importers
 * @since 2.0.2
 */

namespace GeoDir_Converter\Importers;

use Exception;
use InvalidArgumentException;
use GeoDir_Background_Process;
use GeoDir_Converter\Abstracts\GeoDir_Converter_Importer;
use GeoDir_Converter\Exceptions\GeoDir_Converter_Execution_Time_Exception;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'GeoDir_Background_Process', false ) ) {
	require_once GEODIRECTORY_PLUGIN_DIR . '/includes/abstracts/class-geodir-background-process.php';
}

/**
 * GeoDir Converter Background Process class.
 *
 * @since 2.0.2
 */
class GeoDir_Converter_Background_Process extends GeoDir_Background_Process {
	/**
	 * Maximum execution time (in seconds) for a single request.
	 *
	 * Prevents PHP timeouts during large imports.
	 *
	 * @var int
	 */
	public const MAX_REQUEST_TIMEOUT = 30;

	/**
	 * The importer instance.
	 *
	 * @var GeoDir_Converter_Importer
	 */
	protected $importer;

	/**
	 * Maximum execution time for the background process.
	 *
	 * @var int
	 */
	protected $max_execution_time = 0;

	/**
	 * Constructor.
	 *
	 * @since 2.0.2
	 * @param GeoDir_Converter_Importer $importer The importer instance.
	 */
	public function __construct( $importer ) {
		$this->action   = 'geodir_converter_import_' . $importer->get_id();
		$this->importer = $importer;

		parent::__construct();

		$this->max_execution_time = intval( ini_get( 'max_execution_time' ) );
	}

	/**
	 * Calculates time left for the background process.
	 *
	 * @return int
	 */
	protected function time_left() {
		if ( $this->max_execution_time > 0 ) {
			return $this->start_time + $this->max_execution_time - time();
		} else {
			return self::MAX_REQUEST_TIMEOUT;
		}
	}

	/**
	 * Checks if the background process is in progress.
	 *
	 * @since 2.0.2
	 * @return bool True if the process is running, false otherwise.
	 */
	public function is_in_progress() {
		return $this->is_process_running() || ! $this->is_queue_empty();
	}

	/**
	 * Checks if the background process is aborting.
	 *
	 * @return bool
	 */
	public function is_aborting() {
		return $this->importer->options_handler->get_option_no_cache( 'abort_current', false );
	}

	/**
	 * Touches the background process to restart if needed.
	 *
	 * @since 2.0.2
	 */
	public function touch() {
		if ( ! $this->is_process_running() && ! $this->is_queue_empty() ) {
			// Background process down, but was not finished. Restart it.
			$this->dispatch();
		}
	}

	/**
	 * Aborts the background process if it's in progress.
	 */
	public function abort() {
		if ( $this->is_in_progress() ) {
			$this->importer->options_handler->update_option( 'abort_current', true );
		}
	}

	/**
	 * Clears options on start and finish.
	 */
	public function clear_options() {
		$this->importer->options_handler->delete_option( 'abort_current' );
	}

	/**
	 * Complete the background process.
	 *
	 * @since 2.0.2
	 */
	protected function complete() {
		parent::complete();

		$this->clear_options();

		do_action( $this->identifier . '_complete' );
	}

	/**
	 * Process a single task in the queue.
	 *
	 * @since 2.0.2
	 * @param mixed $task Queue item to iterate over.
	 * @return mixed Modified task for further processing or false to remove the item from the queue.
	 * @throws GeoDir_Converter_Execution_Time_Exception When the execution time limit is reached.
	 * @throws InvalidArgumentException When an invalid action is provided.
	 */
	protected function task( $task ) {
		if ( $this->is_aborting() ) {
			$this->cancel_process();
			return false;
		}

		if ( ! isset( $task['action'] ) ) {
			return false;
		}

		$task['offset'] = isset( $task['offset'] ) ? (int) $task['offset'] : 0;

		try {
			// Time left until script termination.
			$time_left = $this->time_left();

			// Leave 5 seconds for importing/batching/logging.
			$timeout = min( $time_left - 5, self::MAX_REQUEST_TIMEOUT );

			if ( $timeout <= 0 ) {
				throw new GeoDir_Converter_Execution_Time_Exception(
					sprintf(
						/* translators: %d: Maximum execution time in seconds */
						esc_html__( 'Maximum execution time is set to %d seconds.', 'geodir-booking' ),
						$timeout
					)
				);
			}

			$action        = $task['action'];
			$import_method = "task_{$action}";

			if ( method_exists( $this->importer, $import_method ) ) {
				return $this->importer->$import_method( $task );
			}

			throw new InvalidArgumentException(
				sprintf(
					/* translators: %s: Invalid action name */
					esc_html__( 'Invalid action: %s', 'geodir-converter' ),
					$import_method
				)
			);
		} catch ( GeoDir_Converter_Execution_Time_Exception $e ) {
			// Restart the process if execution time exceeded.
			add_filter( $this->identifier . '_time_exceeded', '__return_true' );

			$this->importer->log( $e->getMessage(), 'warning' );

			/**
			 * Edge case: Hosts with low `max_execution_time` settings.
			 * WP Background Processing does not check execution time and defaults to 20s per cycle.
			 * If the process times out, it relies on WP-Cron (5 min interval).
			 * This can cause an infinite loop if timeout is negative.
			 */
			return $task;
		} catch ( Exception $e ) {
			$this->importer->log( 'Import error: ' . $e->getMessage(), 'error' );
		}

		return false;
	}

	/**
	 * Adds converter tasks to the background process.
	 *
	 * @since 2.0.2
	 * @param array $workload The workload to process.
	 */
	public function add_converter_tasks( $workload ) {
		$tasks = array(
			array_merge(
				$workload,
				array(
					'action' => $this->importer->get_action(),
				)
			),
		);

		$this->add_tasks( $tasks );
	}

	/**
	 * Adds import tasks to the background process.
	 *
	 * @param array $workloads [[id, title, type], ...]
	 */
	public function add_import_tasks( $workloads ) {
		$tasks = array_map(
			function ( $workload ) {
				$workload['action'] = isset( $workload['action'] ) ? $workload['action'] : GeoDir_Converter_Importer::ACTION_IMPORT_LISTING;
				return $workload;
			},
			$workloads
		);

		$this->add_tasks( $tasks );
	}

	/**
	 * Adds pull addresses tasks to the background process.
	 *
	 * @param array $workloads [[lat, lng], ...]
	 *
	 * @return void
	 */
	public function add_pull_addresses_tasks( $workloads ) {
		$workloads = array_chunk( $workloads, 15, true );

		$tasks = array_map(
			function ( $workload ) {
				$workload['coords'] = $workload;
				$workload['action'] = GeoDir_Converter_Importer::ACTION_IMPORT_ADDRESSES;
				return $workload;
			},
			$workloads
		);

		$this->add_tasks( $tasks );
	}

	/**
	 * Adds import images tasks to the background process.
	 *
	 * @param array $workloads [[image_url, post_id], ...]
	 *
	 * @return void
	 */
	public function add_import_images_tasks( $workloads ) {
		$workloads = array_chunk( $workloads, 10, true );

		$tasks = array_map(
			function ( $workload ) {
				$workload['images'] = $workload;
				$workload['action'] = GeoDir_Converter_Importer::ACTION_IMPORT_IMAGES;
				return $workload;
			},
			$workloads
		);

		$this->add_tasks( $tasks );
	}

	/**
	 * Adds tasks to the background process.
	 *
	 * @since 2.0.2
	 * @param array $tasks The tasks to add.
	 */
	protected function add_tasks( $tasks ) {
		$batch_size = $this->importer->get_batch_size();
		$batches    = array_chunk( $tasks, $batch_size );

		foreach ( $batches as $batch ) {
			$this->data( $batch )->save();
		}

		$this->touch();
	}
}
