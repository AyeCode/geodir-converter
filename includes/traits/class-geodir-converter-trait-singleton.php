<?php
/**
 * Singleton trait
 *
 * @since      2.0.1
 * @package    GeoDir_Converter
 * @version    2.0.1
 */

namespace GeoDir_Converter\Traits;

use RuntimeException;

defined( 'ABSPATH' ) || exit;

/**
 * Singleton trait.
 */
trait GeoDir_Converter_Trait_Singleton {

	/**
	 * The single instance of the class.
	 *
	 * @var static
	 */
	protected static $instance = null;

	/**
	 * Protected constructor to prevent creating a new instance of the
	 * class via the `new` operator from outside of this class.
	 */
	protected function __construct() {}

	/**
	 * Get class instance.
	 *
	 * @return static
	 */
	final public static function instance() {
		if ( null === static::$instance ) {
			static::$instance = new static();
		}
		return static::$instance;
	}

	/**
	 * Prevent cloning of the instance.
	 *
	 * @throws RuntimeException When attempting to clone the singleton instance.
	 */
	public function __clone() {
		throw new RuntimeException( 'Cloning is not allowed for singleton.' );
	}

	/**
	 * Prevent unserializing of the instance.
	 *
	 * @throws RuntimeException When attempting to unserialize the singleton instance.
	 */
	public function __wakeup() {
		throw new RuntimeException( 'Unserializing is not allowed for singleton.' );
	}
}
