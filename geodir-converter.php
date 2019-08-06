<?php
/**
 * This is the main GeoDirectory Converter plugin file, here we declare and call the important stuff
 *
 * @package           Geodir_Converter
 * @copyright         2016 AyeCode Ltd
 * @license           GPLv3
 * @since             1.0.0
 *
 * @geodir_converter
 * Plugin Name: GeoDirectory Converter
 * Plugin URI: https://ayecode.com
 * Description: A plugin to convert other directories to GeoDirectory
 * Version: 1.0.0
 * Author: AyeCode Ltd
 * Author URI: https://wpgeodirectory.com/
 * Requires at least: 4.7
 * Tested up to: 5.1
 * License: GPLv3
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: geodir-converter
 * Domain Path: /languages
 * Update URL: https://wpgeodirectory.com
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

if ( ! defined( 'GEODIR_CONVERTER_VERSION' ) ) {
	define( 'GEODIR_CONVERTER_VERSION', '1.0.0' );
}

if ( ! defined( 'GEODIR_CONVERTER_PLUGIN_FILE' ) ) {
	define( 'GEODIR_CONVERTER_PLUGIN_FILE', __FILE__ );
}

/**
 * Begins execution of the plugin.
 * 
 * Loads the plugin after GD has been loaded
 * 
 * @since    1.0.0
 */
function geodir_load_geodir_converter() {	
	require_once ( plugin_dir_path( GEODIR_CONVERTER_PLUGIN_FILE ) . 'includes/loader.php' );
	new GDCONVERTER_Loarder();
}
add_action( 'geodirectory_loaded', 'geodir_load_geodir_converter' );

/**
 * Tells the user to install GeoDirectory, if they haven't
 *
 * @since    1.0.0
 */
function geodir_converter_check_if_geodir_is_installed() {

	//If this is not an admin page or GD is activated, abort early
	if ( !is_admin() || did_action( 'geodirectory_loaded' ) ) {
		return;
	}

	include_once( ABSPATH . 'wp-admin/includes/plugin.php' );

	$class   = 'notice notice-warning is-dismissible';
	$action  = 'install-plugin';
	$slug	 = 'geodirectory';
	$basename= 'geodirectory/geodirectory.php';

	//Ask the user to activate GD in case they have installed it. Otherwise ask them to install it
	if( is_plugin_inactive($basename) ){

		$activation_url = esc_url( 
			wp_nonce_url( 
				admin_url("plugins.php?action=activate&plugin=$basename"), 
				"activate-plugin_$basename" ) 
			);

		printf( 
			esc_html__( '%s requires the %sGeodirectory%s plugin to be installed and active. %sClick here to activate it.%s', 'geodirectory-converter' ),
			"<div class='$class'><p><strong>GeoDirectory Converter", 
			'<a href="https://wpgeodirectory.com" target="_blank" title=" GeoDirectory">', 
			'</a>', 
			"<a href='$activation_url'  title='GeoDirectory'>", 
			'</a></strong></p></div>' );

	}else{

		$install_url = esc_url( wp_nonce_url(
			add_query_arg(
				array(
					'action' => $action,
					'plugin' => $slug
				),
				admin_url( 'update.php' )
			),
			$action.'_'.$slug
		) );
		
		printf( 
			esc_html__( '%s requires the %sGeodirectory%s plugin to be installed and active. %sClick here to install it.%s', 'geodirectory-converter' ),
			"<div class='$class'><p><strong>GeoDirectory Converter", 
			'<a href="https://wpgeodirectory.com" target="_blank" title=" GeoDirectory">', 
			'</a>', 
			"<a href='$install_url'  title='GeoDirectory'>",
			'</a></strong></p></div>' );

	}

}
add_action( 'admin_notices', 'geodir_converter_check_if_geodir_is_installed' );


/**
 * The code that runs during plugin activation.
 * 
 * @since 1.0.0
 */
function activate_geodir_converter() {
	//Set a transient showing the plugin has been activated. Used to redirect users to the plugin page
    set_transient( '_geodir_converter_installed', '1', MINUTE_IN_SECONDS  );
}
register_activation_hook( __FILE__, 'activate_geodir_converter' );

/// fix images
//add_action('init',function(){
//
//
//	if(isset($_REQUEST['fix-images'])){
//		$db_config = array(
//			'user' => '',
//			'pass' => '',
//			'db_name' => '',
//			'host' => '',
//		);
//		$pmd_url = "";
//		$problem_post_ids = array('3470','4099');
//
//		//Try connecting to the db
//		$pmdb = new wpdb( $db_config['user'] ,$db_config['pass'] ,$db_config['db_name'] ,$db_config['host'] );
//
//		$posts_with_images = $pmdb->get_results("SELECT DISTINCT listing_id FROM pmd_images");
//
//		if(!empty($posts_with_images)){
//			global $wpdb;
//
//			require_once(ABSPATH . 'wp-admin/includes/media.php');
//			require_once(ABSPATH . 'wp-admin/includes/file.php');
//			require_once(ABSPATH . 'wp-admin/includes/image.php');
//			foreach($posts_with_images as $post_with_images){
//				$pmd_post_id = absint($post_with_images->listing_id);
//
//				// check for porblem posts
//				if(in_array($pmd_post_id,$problem_post_ids ) || !$pmd_post_id){
//					continue;
//				}
//
//				$gd_post_id = $wpdb->get_var("SELECT post_id FROM `wp_geodir_gd_place_detail` WHERE pmd_id = $pmd_post_id");
//				if($pmd_post_id && $gd_post_id){
//					$pmd_images = $pmdb->get_results("SELECT * FROM `pmd_images` where listing_id = $pmd_post_id");
//					$gd_images = $wpdb->get_results("SELECT * FROM `wp_geodir_attachments` where post_id = $gd_post_id AND type ='post_images' ");
//
//
//					if(count($pmd_images) > count($gd_images)){
//					echo $pmd_post_id.'###'.$gd_post_id." \n";//exit;
//						echo count($pmd_images).'###'.count($gd_images)." \n";//exit;
//
//
//
//						// build the new image import string
//						$image_string = '';
//						$image_array = array();
//						$allowed_extensions = array('jpg','jpeg','gif','png','svg');
//						$images = $pmd_images;
//						if($images){
//							foreach ( $images as $image ){
//								if( empty( $image->id ) ){
//									continue;
//								}
//
//								if(!in_array(strtolower($image->extension),$allowed_extensions)){
//									continue;
//								}
//
//								// create a random key prefixed with the ordering so that we try to keep the image original ordering via the array keys.
//								$key = (int) $image->ordering .wp_rand(100000,900000);
//
//								$image->title =  preg_replace("/[^A-Za-z0-9 ]/", '', $image->title);
//								$image->description =  preg_replace("/[^A-Za-z0-9 ]/", '',  $image->description);
//
//								$image_array[$key] = array(
//									"url"   => trailingslashit( $pmd_url )."files/images/$image->id.$image->extension", // this will end up in the current upload directory folder
//									"title" => wp_slash(esc_attr($image->title)),
//									"caption" => wp_slash(esc_attr($image->description))
//								);
//							}
//						}
//
//						if(!empty($image_array)){
//							foreach ($image_array as $img){
//								if(!$image_string){
//									$image_string .= $img['url']."||".$img['title']."|".$img['caption'];
//								}else{
//									$image_string .= "::".$img['url']."||".$img['title']."|".$img['caption'];
//								}
//							}
//						}
//
//						if($image_string){
//
//
//							// delete all images first so we don't duplicate
//							GeoDir_Media::delete_files( $gd_post_id, 'post_images');
//
//							// save the new images
//							GeoDir_Post_Data::save_files( $gd_post_id, $image_string, 'post_images', false, false );
//
//							echo $image_string;//exit;
//						}
//
//
//					}
////			print_r($pmd_img_count);
//				}
//
//
//			}
//			exit;
//		}
//
//
//	}
//
//
//});
//
