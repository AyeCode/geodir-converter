<?php 
/**
 * Prints the imports screen
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

//Use this hook to register new importers
$importers = GDCONVERTER_Loarder::get_importers();

?>
<div class="wrap geodir-converter">
	<h1><?php esc_html_e( "GeoDirectory Converter", "geodirectory-converter"); ?></h1>
	<div class="geodir-converter-inner">
		<div class="geodir-converter-form-wrapper">
			<div class="geodir-converter-errors"></div>
			<form method="post" action="" class="geodir-converter-form geodir-converter-form1">
			<p><?php esc_html_e( "If you have listings in another system, GeoDirectory can import those into this site. To get started, choose a system to import from below:", "geodirectory-converter"); ?></p>
				<?php 
					foreach( $importers as $id => $details ) {
						$value	 = esc_attr( $id );
						$label	 = esc_html( $details['title'] );
						$class	 = "geodir-converter-select geodir-converter-select-$value ";
						echo "<label class='$class'> 
								<input class='screen-reader-text' name='gd-converter' data-converter='$value' value='$value' type='radio'>
								$label
		  			  	 	 </label>";
				}
				wp_nonce_field( 'gdconverter_nonce_action', 'gdconverter_nonce_field' );
				?>
				<input type='hidden' name='action' value='gdconverter_handle_import'>
				<input type="hidden" name="step" value="1">
			</form>
			<div class="geodir-converter-progress">
				<div class="geodir-converter-meter">
  					<span class="gmw" style="width: 0"><span></span></span>
				</div>
			</div>
		</div>
		<div class="geodir-converter-loader"></div>
	</div>
</div>