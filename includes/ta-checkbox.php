<?php


add_action( 'woocommerce_product_options_general_product_data', 'taager_shipping_options');
function taager_shipping_options(){
    $product_id = intval( $_GET['post'] );
$is_taager_product = get_post_meta( $product_id, 'taager_product', true );
if($is_taager_product) {
	echo '<div class="options_group">';
 
	woocommerce_wp_checkbox( array(
		'id'      => 'ta__shipping_charge',
		//'value'   => get_post_meta( $product_id, 'ta__shipping_charge', true ),
		'label'   => __('Free Shipping', 'taager-plugin'),
		'desc_tip' => false,
	) );
 
	echo '</div>';
 
}
}

add_action( 'woocommerce_process_product_meta', 'taager_shipping_save', 10, 2 );
function taager_shipping_save( $id, $post ){
	$ta__shipping_charge = sanitize_text_field($_POST['ta__shipping_charge']);
	if($ta__shipping_charge === 'yes') {
		update_post_meta( $id, 'ta__shipping_charge', $ta__shipping_charge );
	} else {
		delete_post_meta( $id, 'ta__shipping_charge' );
	}
}