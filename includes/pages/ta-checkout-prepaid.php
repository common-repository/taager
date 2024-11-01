<?php

defined( 'ABSPATH' ) || exit;

add_action( 'woocommerce_after_checkout_form', 'taager_handle_payment_complete', 10 );
function taager_handle_payment_complete(){
	$payment_id = taager_get_payment_id_from_url();
	
	if($payment_id) {
		taager_process_prepaid_order($payment_id);
	}
}

function taager_get_payment_id_from_url() {
	if (isset($_GET['id'])) {
    return $_GET['id'];
	} else {
		return 0;
	}
}

function taager_process_prepaid_order($payment_id) {
	?>
	<script>
		function placeWoocommcereOrder (taagerOrderData) {
			jQuery.ajax({
				type: 'POST',
				url: '<?php echo (admin_url( 'admin-ajax.php' )) ?>',
				data: { 
					metadata: {
						...metadata,
						taagerApiResponse: JSON.parse(taagerOrderData),
					},
					action: 'taager_place_prepaid_order_to_woocommerce',
				},
				success: function (response) {
					if (response !== 'failed') {
						window.location.href = response;
					} else {
						jQuery('.order-processing__loader').addClass('hidden');
						jQuery('.order-processing__text').addClass('hidden');
						jQuery('.order-processing__failed').removeClass('hidden');
					}
				}
			})
		}

		jQuery('.checkout.woocommerce-checkout').addClass('hidden');
		const metadata = JSON.parse(localStorage.getItem('taager_order_metadata'));
		localStorage.removeItem('taager_order_metadata');
		if (metadata) {
			jQuery.ajax({
				type: 'POST',
				url: '<?php echo (admin_url( 'admin-ajax.php' )) ?>',
				data: {
					paymentId: '<?php echo $payment_id ?>',
					action: 'taager_place_prepaid_order_to_taager'
				},
				success: placeWoocommcereOrder,
			});
		}
	</script>
	<div class="order-processing">
		<img class="order-processing__loader" src="<?php echo esc_url(plugin_dir_url(  __DIR__  ))?>../assets/images/spin.gif">
		<p class="order-processing__text"><?php _e('Order placement in progress', 'taager-plugin') ?></p>
		<p class="order-processing__failed hidden"><?php _e('Order placement failed', 'taager-plugin') ?></p>
	</div>

	<?php
}

add_action( 'wp_ajax_taager_place_prepaid_order_to_taager', 'taager_place_prepaid_order_to_taager' );
add_action( 'wp_ajax_nopriv_taager_place_prepaid_order_to_taager', 'taager_place_prepaid_order_to_taager' );
function taager_place_prepaid_order_to_taager() {
	$payment_id = $_POST['paymentId'];
	
	$response = taager_call_API(
		'POST', taager_get_url('PREPAID_ORDER'), json_encode(array("paymentId" => $payment_id))
	);

	echo (json_encode($response));
	wp_die();
}


add_action( 'wp_ajax_taager_place_prepaid_order_to_woocommerce', 'taager_place_prepaid_order_to_woocommerce' );
add_action( 'wp_ajax_nopriv_taager_place_prepaid_order_to_woocommerce', 'taager_place_prepaid_order_to_woocommerce' );
function taager_place_prepaid_order_to_woocommerce() {
	$order_data = $_POST['metadata'];

	$order = new WC_Order();

	$order->set_billing_first_name($order_data['customer']['fullName']);
	$order->set_billing_address_1($order_data['customer']['address1']);
	$order->set_billing_state($order_data['customer']['province']);
	$order->set_billing_phone($order_data['customer']['phoneNumber']);
	$order->set_billing_email($order_data['customer']['email']);

	foreach ($order_data['orderLines'] as $order_line) {
		$product_id = $order_line['product_id'];
		$quantity = $order_line['quantity'];
		$order->add_product(wc_get_product($product_id), $quantity);
	}

	$item_fee = new WC_Order_Item_Fee();

	$shipping_fee_name = __( "Shipping", "taager-plugin" );
	$item_fee->set_name( $shipping_fee_name );
	$item_fee->set_amount( $order_data['shippingCost'] );
	$item_fee->set_tax_class( '' );
	$item_fee->set_tax_status( 'none' );
	$item_fee->set_total( $order_data['shippingCost'] );

	$order->add_item( $item_fee );
	
	$order->calculate_totals();
	
	$order->update_status( 'processing' );

	$wp_order_id = $order->save();

	if ($wp_order_id) {
		if (isset ($order_data['taagerApiResponse']['data'])) {
			update_post_meta( $wp_order_id, '_ta_order_num', explode('/', $order_data['taagerApiResponse']['data']['orderID'])[1] );
			update_post_meta( $wp_order_id, 'taager_order_id', $order_data['taagerApiResponse']['data']['orderID'] );
			update_post_meta( $wp_order_id, 'taager_order_status', $order_data['taagerApiResponse']['data']['status'] );
		} else {
			update_post_meta( $wp_order_id, 'taager_order_rejection_reason', $order_data['taagerApiResponse']['message'] );
		}
	
		
		$order_key = $order->get_order_key();
		$redirect_url = wc_get_endpoint_url('order-received', $wp_order_id, wc_get_page_permalink('checkout')) . '/?key='.$order_key;
		echo $redirect_url;
	} else {
		echo "failed";
	}
	wp_die();
}

function taager_is_prepaid_allowed() {
	$ta_selected_country = taager_get_country_iso_code();
	switch ( $ta_selected_country ) {
		case SAUDI_ISO_CODE_3:
			return true;
		case EGYPT_ISO_CODE_3:
		case EMIRATES_ISO_CODE_3:
		default:
			return false;
	}
}

function taager_get_moyasar_key() {
	return "'pk_live_SHJoo8rrqY3hg7Mj4EjBAcj6ZVGe6wTknasVZV2R'";
}

function taager_map_cart_items($cart_item) {
	$post_id = $cart_item['variation_id'] ? $cart_item['variation_id'] : $cart_item['product_id'];
	return array(
		"sku" => get_post_meta($post_id, '_sku', true),
		"quantity" => $cart_item['quantity'],
		"totalPrice" => $cart_item['line_total'],
		"product_id" => $post_id
	);
}

function taager_get_order_items() {
	$cart_content = array_map('taager_map_cart_items', WC()->cart->get_cart());
	$encoded_cart_content = json_encode (array_values($cart_content));
	return $encoded_cart_content;
}

function taager_get_order_received_by() {
	$version = "Taager_plugin_v" . get_option( 'ta_plugin_version' );
	return "'$version'";
}

add_filter( 'woocommerce_review_order_before_payment', 'taager_moyasar_form' );
function taager_moyasar_form() {
	if( taager_is_prepaid_allowed() ) {
	?>

	<div class="checkout__payment-info-details">
			<h3><?php _e('Payment info', 'taager-plugin')?></h3>

			<label> <?php _e('Payment method', 'taager-plugin') ?> </label><br>
			<div class="cash-on-delivery__wrapper">
				<input type="radio" id="cod" name="payment_method" value="COD">
				<label for="cod"> <?php _e('Cash on delivery', 'taager-plugin') ?> </label><br>
			</div>
			<div class="pay-with-moyasar__wrapper">
				<input type="radio" id="card" name="payment_method" value="creditcard">
				<label for="card"> <?php _e('Pay with Moyasar', 'taager-plugin') ?> </label><br>
				<div class="pay-with-moyasar__images-wrapper">
					<img src="<?php echo esc_url(plugin_dir_url(  __DIR__  ))?>../assets/images/moyasar.svg" alt="Moyasar">
					<div class= "pay-with-moyasar__supported-networks-images">
						<img src="<?php echo esc_url(plugin_dir_url(  __DIR__  ))?>../assets/images/visa.svg" alt="Visa">
						<img src="<?php echo esc_url(plugin_dir_url(  __DIR__  ))?>../assets/images/masterCard.svg" alt="Master Card">
						<img src="<?php echo esc_url(plugin_dir_url(  __DIR__  ))?>../assets/images/mada.svg" alt="Mada">
					</div>
				</div>
			</div>
			

			<br>
			<br>
			
			<div id="payment-details" class="hidden">
				<!-- Moyasar Styles -->
				<link rel="stylesheet" href="https://cdn.moyasar.com/mpf/1.10.0/moyasar.css" />

				<!-- Moyasar Scripts -->
				<script src="https://polyfill.io/v3/polyfill.min.js?features=fetch"></script>
				<script src="https://cdn.moyasar.com/mpf/1.10.0/moyasar.js"></script>

				<div class="mysr-form"></div>
			</div>
		</div>

	<?php
	}
}

add_filter('woocommerce_review_order_after_order_total', 'updateMoyasarFormOnProvinceChange');
function updateMoyasarFormOnProvinceChange() {
	if( taager_is_prepaid_allowed() ) {
	?>
	<script>
		function isCustomerFieldsFilled (customerFormData) {
			const keysToCheck = [ 'fullName', 'province', 'provinceId', 'zoneId', 'phoneNumber', 'address1' ];
			if (<?php echo (taager_get_country_iso_code() === SAUDI_ISO_CODE_3)?> === 1) {
				keysToCheck.push('districtId');
			}
			return Object.entries(customerFormData).every(([key, value]) =>
				!(keysToCheck.includes(key) && !value)
			) && keysToCheck.every((keyToCheck) => customerFormData.hasOwnProperty(keyToCheck));
		}

		function isPhoneNumberValid (phoneNumber) {
			return /^\d{<?php echo taager_get_country_phone_number_length() ?>}$/.test(phoneNumber);
		}

		function getAmountFromElement(elementSelector) {
			return jQuery(elementSelector)[0].innerText.replace(/\,/g, '').match(/\d+/)[0];
		}

		Moyasar.init({
			element: '.mysr-form',
			amount: getAmountFromElement('.order-total .woocommerce-Price-amount.amount') * 100 ,
			currency: '<?php echo taager_get_currency() ?>',
			description: 'Order from ' + window.location.href.split('.com')[0] + '.com',
			publishable_api_key: <?php echo taager_get_moyasar_key() ?>,
			callback_url: window.location.href,
			supported_networks: [ 'visa', 'mastercard', 'mada'],
			methods: ['creditcard','applepay'],
			apple_pay: {
            country: 'SA',
            label: 'Order from ' + window.location.href.split('.com')[0] + '.com',
            validate_merchant_url: 'https://api.moyasar.com/v1/applepay/initiate',
			},
			on_initiating: function() {
				const metadata = {
						orderLines: <?php echo taager_get_order_items() ?>,
						shippingCost: 
							getAmountFromElement('.order-total .woocommerce-Price-amount.amount') -
								getAmountFromElement('.cart-subtotal .woocommerce-Price-amount.amount') ,
						customer: {
							fullName: jQuery('#billing_first_name')[0].value,
							province: jQuery('#billing_state')[0].value,
							provinceId: jQuery('#billing_state')[0].value,
							zoneId: jQuery('#billing_zone')[0].value,
							...(jQuery('#billing_district')[0].value? {districtId: jQuery('#billing_district')[0].value} : {}),
							phoneNumber: jQuery('#billing_phone')[0].value,
							...(jQuery('#billing_phone2')[0].value? {phoneNumber2: jQuery('#billing_phone2')[0].value} : {}),
							address1: jQuery('#billing_address_1')[0].value,
							email: jQuery('#billing_email')[0].value,
							notes: jQuery('#order_comments')[0].value
						},
						orderSource: {
							pageUrl: window.location.href.split('.com')[0] + '.com',
						},
						orderReceivedBy: <?php echo taager_get_order_received_by() ?>,
					};
				if ( !isCustomerFieldsFilled(metadata.customer) ) {
					alert('<?php _e('Please make sure all required fields are filled', 'taager-plugin') ?>');
					return false;
				} else if (
					!isPhoneNumberValid(metadata.customer.phoneNumber) ||
					(metadata.customer.phoneNumber2 && !isPhoneNumberValid(metadata.customer.phoneNumber2))
				) {
					alert('<?php 
						_e('Phone number shall be ', 'taager-plugin');
						echo taager_get_country_phone_number_length();
						_e(' digits', 'taager-plugin'); 
					?>');
					return false;
				}
				localStorage.setItem('taager_order_metadata', JSON.stringify(metadata));
				return { metadata } 
			},
		});
	</script>
	<?php
	}
}
