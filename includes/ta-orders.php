<?php 

defined( 'ABSPATH' ) || exit;

add_action( 'woocommerce_checkout_create_order', 'taager_create_order', 10, 2 );
function taager_create_order( $order, $data ) {
  $cod = 0;
  $ta_is_flatrate = false;
  
	foreach ( $order->get_items() as $item_id => $item ) {
		$cod = $cod + intval( $item->get_total() );

    $product_id   = $item->get_product_id();
		$ta__flatrate_shipping = get_post_meta( $product_id, 'ta__shipping_charge', true );
		if ( $ta__flatrate_shipping == 'yes' ) {
      $ta_is_flatrate = true;
		}
	}

	if(!$ta_is_flatrate) {
		$shipping_fees = taager_province_shipping($data['billing_state']);
    $cod = $shipping_fees + $cod;

    $shipping_fee_name = __( "Shipping", "taager-plugin");
  
    foreach( $order->get_items( 'fee' ) as $item_id => $item ) {
      if( $shipping_fee_name === $item['name'] ) {
          $item->set_amount( $shipping_fees );
          $item->set_total( $shipping_fees );
      }       
    }
    $order->set_total($cod);
	}

}

function taager_get_order_rejection_reason($wc_order_id) {
  $order_rejection_reason = get_post_meta( $wc_order_id, 'taager_order_rejection_reason', true );
  $language=get_bloginfo("language");
  if($language == 'ar') {
    if(str_contains($order_rejection_reason, 'spam behavior')) {
      $order_rejection_reason = 'عزيزي التاجر لايمكن إستلام هذا الطلب لأن هذا العميل له تاريخ من إساءة إستخدام النظام و عدم إستلام الاوردر من المندوب';
    } else if(str_contains($order_rejection_reason, 'lower than defined product price')) {
      $order_rejection_reason = 'لا يمكن اتمام الطلب لأن سعر المنتج المحدد اقل من السعر الصحيح للمنتج';
    } else if(str_contains($order_rejection_reason, 'product IDs, product prices and product quantities, differ')) {
      $order_rejection_reason = 'لا يمكن اتمام الطلب لوجود مشكلة في بيانات الطلب';
    } else if (str_contains($order_rejection_reason, 'expired or unavailable')) {
      $order_rejection_reason = 'لا يمكن اتمام الطلب لأن المنتج المطلوب غير متوفر حاليا';
    }
  }
  return $order_rejection_reason;
}
