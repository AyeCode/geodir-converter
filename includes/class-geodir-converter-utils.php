<?php
/**
 * Utility Class for Geodir Converter.
 *
 * @since      2.0.2
 * @package    GeoDir_Converter
 * @version    2.0.2
 */

namespace GeoDir_Converter;

use WP_Error;
use Exception;
use WpOrg\Requests\Requests;

defined( 'ABSPATH' ) || exit;

/**
 * Utility class for handling various utility functions.
 *
 * @since 2.0.2
 */
class GeoDir_Converter_Utils {
	const OPEN_STREET_MAP_API = 'https://nominatim.openstreetmap.org/reverse';

	/**
	 * Get multiple locations from an array of coordinates using Nominatim reverse geocoding.
	 *
	 * @param array $coords Array of arrays with 'lat' and 'lng' keys.
	 * @return array Array of location results keyed by original index. Values can be WP_Error or location data.
	 */
	public static function get_locations_from_coords_batch( $coords ) {
		$endpoint   = self::OPEN_STREET_MAP_API;
		$user_agent = sprintf( 'GeoDir_Converter/2.0.2 ( %s )', get_bloginfo( 'admin_email' ) );

		$requests = array();
		$indexes  = array();
		$results  = array();

		foreach ( $coords as $i => $coord ) {
			$lat = isset( $coord['lat'] ) ? $coord['lat'] : null;
			$lng = isset( $coord['lng'] ) ? $coord['lng'] : null;

			if ( ! is_numeric( $lat ) || ! is_numeric( $lng ) ) {
				$results[ $i ] = new WP_Error( 'invalid_location', __( 'Invalid latitude or longitude', 'geodir-converter' ) );
				continue;
			}

			$cache_key = 'geodir_converter_location_' . md5( $lat . ',' . $lng );
			$cached    = get_transient( $cache_key );
			if ( false !== $cached ) {
				// $results[ $i ] = $cached;
				// continue;
			}

			$url = add_query_arg(
				array(
					'lat'            => $lat,
					'lon'            => $lng,
					'format'         => 'json',
					'addressdetails' => 1,
				),
				$endpoint
			);

			$requests[] = array(
				'url'     => $url,
				'method'  => 'GET',
				'timeout' => 20,
				'headers' => array(
					'User-Agent' => $user_agent,
				),
			);

			$indexes[] = $i;
		}

		if ( empty( $requests ) ) {
			return $results;
		}

		$responses = Requests::request_multiple( $requests );

		foreach ( $responses as $key => $response ) {
			$i     = $indexes[ $key ];
			$coord = $coords[ $i ];
			$lat   = $coord['lat'];
			$lng   = $coord['lng'];

			if ( is_wp_error( $response ) || 200 !== $response->status_code ) {
				$results[ $i ] = new WP_Error( 'invalid_location', __( 'Failed to retrieve location data', 'geodir-converter' ) );
				continue;
			}

			$data = json_decode( $response->body, true );
			if ( ! isset( $data['address'] ) || empty( $data['address'] ) ) {
				$results[ $i ] = new WP_Error( 'invalid_location', __( 'Invalid location data', 'geodir-converter' ) );
				continue;
			}

			$location      = self::parse_location_data( $lat, $lng, $data );
			$results[ $i ] = $location;

			$cache_key = 'geodir_converter_location_' . md5( $lat . ',' . $lng );
			set_transient( $cache_key, $location, 60 * 60 );
		}

		return $results;
	}

	/**
	 * Get location data (city, state, zip, country) from latitude and longitude.
	 *
	 * Uses Nominatim (OpenStreetMap) reverse geocoding.
	 *
	 * @param float $lat Latitude.
	 * @param float $lng Longitude.
	 * @return WP_Error|array Location data with keys 'city', 'state', 'zip', 'country', or WP_Error on failure.
	 */
	public static function get_location_from_coords( $lat, $lng ) {
		if ( ! is_numeric( $lat ) || ! is_numeric( $lng ) ) {
			return new WP_Error( 'invalid_location', esc_html__( 'Invalid latitude or longitude', 'geodir-converter' ) );
		}

		// Check cache first.
		$cache_key = 'geodir_converter_location_' . md5( $lat . ',' . $lng );
		$location  = get_transient( $cache_key );

		if ( false !== $location ) {
			return $location;
		}

		$endpoint = self::OPEN_STREET_MAP_API;
		$args     = array(
			'headers' => array(
				'User-Agent' => sprintf( 'GeoDir_Converter/2.0.2 ( %s )', get_bloginfo( 'admin_email' ) ),
			),
			'timeout' => 10,
		);

		$url = add_query_arg(
			array(
				'lat'            => $lat,
				'lon'            => $lng,
				'format'         => 'json',
				'addressdetails' => 1,
			),
			$endpoint
		);

		$response = wp_remote_get( $url, $args );

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'invalid_location', esc_html__( 'Failed to retrieve location data', 'geodir-converter' ) );
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! isset( $data['address'] ) || empty( $data['address'] ) ) {
			return new WP_Error( 'invalid_location', esc_html__( 'Invalid location data', 'geodir-converter' ) );
		}

		$location = self::parse_location_data( $lat, $lng, $data );

		// Cache the location for 1 hour.
		set_transient( $cache_key, $location, 60 * 60 );

		return $location;
	}

	/**
	 * Parse address data from latitude and longitude.
	 *
	 * @param float $lat Latitude.
	 * @param float $lng Longitude.
	 * @param array $location Location data.
	 * @return array Location data with keys ['latitude', 'longitude', 'address', 'city', 'state', 'zip', 'country'], or WP_Error on failure.
	 */
	private static function parse_location_data( $lat, $lng, $location ) {
		$address = $location['address'];

		$city = '';
		if ( isset( $address['village'] ) ) {
			$city = $address['village'];
		} elseif ( isset( $address['town'] ) ) {
			$city = $address['town'];
		} elseif ( isset( $address['city'] ) ) {
			$city = $address['city'];
		}

		$state = '';
		if ( isset( $address['province'] ) ) {
			$state = $address['province'];
		} elseif ( isset( $address['state'] ) ) {
			$state = $address['state'];
		} elseif ( isset( $address['region'] ) ) {
			$state = $address['region'];
		}

		$data = array(
			'latitude'  => $lat,
			'longitude' => $lng,
			'address'   => isset( $location['display_name'] ) ? $location['display_name'] : '',
			'city'      => $city,
			'state'     => isset( $state ) ? $state : '',
			'zip'       => isset( $address['postcode'] ) ? $address['postcode'] : '',
			'country'   => isset( $address['country'] ) ? $address['country'] : '',
		);

		return $data;
	}

	/**
	 * Parse CSV file.
	 *
	 * @param string $file_path The path to the CSV file.
	 * @param array  $required_headers The required headers.
	 * @return array|WP_Error An array of parsed rows or a WP_Error object on failure.
	 */
	public static function parse_csv( $file_path, $required_headers = array() ) {
		if ( ! file_exists( $file_path ) ) {
			return new WP_Error( 'file_not_found', __( 'CSV file not found.', 'geodir-converter' ) );
		}

		if ( ! is_readable( $file_path ) ) {
			return new WP_Error( 'file_not_readable', __( 'CSV file is not readable. Please check file permissions.', 'geodir-converter' ) );
		}

		$data            = array();
		$line_number     = 0;
		$max_line_length = 0;

		try {
			if ( ( $handle = fopen( $file_path, 'r' ) ) !== false ) {
				// Get headers.
				$headers = fgetcsv( $handle, 0, ',' );
				++$line_number;

				if ( empty( $headers ) || ! is_array( $headers ) ) {
					return new WP_Error( 'invalid_headers', __( 'CSV file has invalid or missing headers.', 'geodir-converter' ) );
				}

				// Validate headers (no empty or duplicate headers).
				$headers = array_map( 'trim', $headers );
				if ( count( $headers ) !== count( array_filter( $headers ) ) ) {
					return new WP_Error( 'empty_headers', __( 'CSV headers contain empty values. Please ensure all columns have headers.', 'geodir-converter' ) );
				}

				if ( count( $headers ) !== count( array_unique( $headers ) ) ) {
					return new WP_Error( 'duplicate_headers', __( 'CSV headers contain duplicate values. Each column must have a unique header.', 'geodir-converter' ) );
				}

				// Remove spaces and convert to lowercase.
				$headers = array_map(
					function ( $header ) {
						return trim( str_replace( ' ', '', strtolower( $header ) ) );
					},
					$headers
				);

				// Required headers check.
				if ( ! empty( $required_headers ) ) {
					$missing_headers = array_diff( $required_headers, array_map( 'strtolower', $headers ) );

					if ( ! empty( $missing_headers ) ) {
						return new WP_Error(
							'missing_headers',
							sprintf(
								__( 'CSV is missing required headers: %s', 'geodir-converter' ),
								implode( ', ', $missing_headers )
							)
						);
					}
				}

				// Process rows.
				while ( ( $row = fgetcsv( $handle, 0, ',' ) ) !== false ) {
					++$line_number;

					// Skip empty rows.
					if ( count( array_filter( $row ) ) === 0 ) {
						continue;
					}

					// Check for row length mismatch.
					if ( count( $row ) !== count( $headers ) ) {
						return new WP_Error(
							'row_length_mismatch',
							sprintf(
								__( 'Row %1$d has %2$d columns while the header has %3$d columns. Please ensure all rows have the correct number of columns.', 'geodir-converter' ),
								$line_number,
								count( $row ),
								count( $headers )
							),
						);
					}

					// Track max line length for memory management.
					$line_length     = strlen( implode( '', $row ) );
					$max_line_length = max( $max_line_length, $line_length );

					// Memory limit check.
					if ( $max_line_length > 1048576 ) { // 1MB per line limit
						return new WP_Error( 'excessive_row_length', __( 'CSV contains excessively long rows. Please check your data format.', 'geodir-converter' ) );
					}

					// Sanitize and validate row data.
					$sanitized_row = array();
					foreach ( array_combine( $headers, $row ) as $key => $value ) {
						// Basic sanitization.
						$value = sanitize_text_field( $value );

						// Field-specific validation could be added here.
						$sanitized_row[ $key ] = $value;
					}

					$data[] = $sanitized_row;

					// Limit number of rows for memory protection.
					if ( count( $data ) >= 10000 ) {
						break;
					}
				}

				fclose( $handle );
			} else {
				return new WP_Error( 'file_open_failed', __( 'Failed to open CSV file for reading.', 'geodir-converter' ) );
			}
		} catch ( Exception $e ) {
			// Re-throw with line number information if it's a parsing error.
			if ( $line_number > 0 && $e->getCode() === 422 ) {
				return new WP_Error(
					'parsing_error',
					sprintf(
						__( 'Error at line %1$d: %2$s', 'geodir-converter' ),
						$line_number,
						$e->getMessage()
					),
				);
			}
			throw $e;
		}

		if ( empty( $data ) ) {
			return new WP_Error( 'no_data', __( 'CSV file contains no valid data rows.', 'geodir-converter' ) );
		}

		return $data;
	}
}
