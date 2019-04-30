<?php 
/**
 * Prints the imports screen
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

//Use this hook to register new importers
$importers = apply_filters( 'geodir_converter_importers', array());

?>
<div class="wrap">
<h1>GeoDirectory Converter</h1>
<p>If you have listings in another system, GeoDirectory can import those into this site. To get started, choose a system to import from below:</p>
<?php 
foreach( $importers as $id => $details ) {
	$value	 = esc_attr( $id );
	$label	 = esc_html( $details['title'] );
	$class	 = "geodir-converter-select geodir-converter-select-$value ";
	echo "<label class='$class'> 
			<input name='converter' data-converter='$value' value='$value' type='radio'>
			$label
		  </label>";
}?>
</div>