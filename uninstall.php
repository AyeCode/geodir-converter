<?php
/**
 * GeoDirectory Directory Converter Uninstall
 *
 * @package    GeoDir_Converter
 * @author     AyeCode Ltd
 * @version    1.0.0
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Remove the data stored by the converter for the current site.
 *
 * @since 2.2.2
 *
 * @return void
 */
function geodir_converter_uninstall_data() {
	global $wpdb;

	// Import settings, logs, stats, mappings and background process batches.
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
			$wpdb->esc_like( 'geodir_converter_' ) . '%',
			'%' . $wpdb->esc_like( '_geodir_converter_import_' ) . '%',
			$wpdb->esc_like( '_transient_geodir_converter_' ) . '%',
			$wpdb->esc_like( '_transient_timeout_geodir_converter_' ) . '%'
		)
	);

	delete_transient( '_geodir_converter_installed' );

	// Legacy PMD password hashes are only used by the converter's login handler.
	delete_metadata( 'user', 0, 'pmd_password', '', true );
	delete_metadata( 'user', 0, 'pmd_password_hash', '', true );
	delete_metadata( 'user', 0, 'pmd_password_salt', '', true );
}

if ( is_multisite() ) {
	$site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $site_ids as $site_id ) {
		switch_to_blog( $site_id );

		geodir_converter_uninstall_data();

		restore_current_blog();
	}
} else {
	geodir_converter_uninstall_data();
}
