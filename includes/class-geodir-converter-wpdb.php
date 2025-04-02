<?php
/**
 * Custom database class for GeoDir Converter.
 *
 * @package GeoDir_Converter
 * @subpackage Database
 * @since 2.0.2
 */

namespace GeoDir_Converter;

use wpdb;

defined( 'ABSPATH' ) || exit;

/**
 * GeoDir_Converter_DB class.
 *
 * This class extends WordPress' wpdb class but doesn't connect
 * to the database in the constructor.
 */
class GeoDir_Converter_WPDB extends wpdb {

	/**
	 * Constructor.
	 *
	 * Unlike wpdb, this constructor doesn't connect to the database.
	 * It only sets up the class properties.
	 *
	 * @param string $dbuser     Database user.
	 * @param string $dbpassword Database password.
	 * @param string $dbname     Database name.
	 * @param string $dbhost     Database host.
	 */
	public function __construct(
		$dbuser,
		$dbpassword,
		$dbname,
		$dbhost
	) {
		$this->dbuser     = $dbuser;
		$this->dbpassword = $dbpassword;
		$this->dbname     = $dbname;
		$this->dbhost     = $dbhost;
	}
}
