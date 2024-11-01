<?php

defined( 'ABSPATH' ) || exit;

/**
 * Load plugin js, styles
 */
add_action( 'admin_enqueue_scripts', 'taager_admin_enqueue' );
function taager_admin_enqueue( $hook ) {
	wp_enqueue_script( 'ta_script', plugin_dir_url( __DIR__ ) . '/assets/js/admin.js', array( 'jquery' ), rand(), true );
	wp_enqueue_script( 'ta_country_selection_script', plugin_dir_url( __DIR__ ) . '/assets/js/country-selection.js', array( 'jquery' ), rand(), true );
	wp_enqueue_style( 'ta_style', plugin_dir_url( __DIR__ ) . '/assets/css/admin.css', array(), rand() );
	$localize_array = [
		'ajaxURL'        => admin_url( 'admin-ajax.php' ),
		'taager_product' => 0,
	];
	if ( isset( $_GET['post'] ) ) {
		$product_id = intval( $_GET['post'] );
		$ptype      = get_post_type( $product_id );
		if ( 'product' == $ptype ) {
			$is_taager_product = get_post_meta( $product_id, 'taager_product', true );
			$taager_price      = get_post_meta( $product_id, '_ta_product_price', true );

			$localize_array['taager_product'] = $is_taager_product ? $is_taager_product : 0;
			$localize_array['taager_price']   = $taager_price;
			$localize_array['currency']       = get_woocommerce_currency_symbol();
			//my new code  feb3

			global $wpdb;
			$ta_provinces_name   = PROVINCES_TABLE_NAME;
			$ta__select_max      = $wpdb->get_row( "SELECT * FROM $ta_provinces_name WHERE active_status=1 ORDER BY shipping_revenue DESC LIMIT 1" );
			$taager_max_shipping = $ta__select_max->shipping_revenue;
			$taager_profit       = get_post_meta( $product_id, '_ta_product_profit', true );
			if ( ! empty( $taager_profit ) && ! empty( $taager_max_shipping ) ) {
				$ta_new_min_price                   = $taager_price + $taager_max_shipping;
				$localize_array['ta_new_min_price'] = $ta_new_min_price;
			}
		}
	}
	wp_localize_script( 'ta_script', 'ta_admin', $localize_array );
}

/**
 * Get product profit when request Ajax post from frontend
 */
add_action( 'wp_ajax_get_product_profit', 'taager_get_product_profit' );
function taager_get_product_profit() {
	$product_id = intval( $_POST['productId'] );

	// Get product profit from meta data
	$product_profit = get_post_meta( $product_id, '_ta_product_profit', true );

	echo esc_html($product_profit);

	wp_die();
}

/**
 * Function is used to make HTTP requests and returns the response body
 */
function taager_call_API( $method, $url, $data = false ) {
	$ta_api_username = get_option('ta_api_username');
	$ta_api_password = get_option('ta_api_password');
	$ta_selected_country = taager_get_country_iso_code();

	$args['method'] = $method;

	if ($ta_api_username & $ta_api_password) {
		$args['headers']['Authorization'] = 'Basic ' . base64_encode("$ta_api_username:$ta_api_password");
	}

	if($ta_selected_country) {
		$args['headers']['country'] = $ta_selected_country;
	}

	if($method === 'POST') {
		if($data) {
			$args['headers']['Content-Type'] = 'application/json';
			$args['body'] = $data;
		}
	} else if ($method !== 'PUT' && $data) {
		$url = sprintf( '%s?%s', $url, http_build_query( $data ) );
	}
	$args['timeout'] = 60;
	$remote_result = wp_remote_get($url, $args);
	if(is_wp_error($remote_result)) {
		ta_track_event('taager_WP_Error', (array)$remote_result);
		$result = false;
	} else {
		$result = $remote_result['body'];
	}


	return json_decode( $result );
}

/**
 * Initialize Taager plugin
 *
 * Import Categories, Provinces from backend via the APIs and navigate to taager product setting page
 */
function taager_initialize() {
	update_option( 'ta_initial_status', 'running' );

	taager_import_categories();
	taager_import_provinces();
	taager_import_zones();

	update_option( 'ta_initial_status', 'done' );
	wp_redirect( admin_url( 'admin.php?page=taager_product_setting' ) );
}

/**
 * Import Products from backend via the APIs
 */
// add_action( 'taager_import_products', 'taager_import_products_function', 10, 2 );
function taager_import_products_function( $product_category, $product_name ) {
	update_option( 'ta_product_import_status', 'running' );

	taager_import_products_from_db( $product_category, $product_name );

	update_option( 'ta_product_import_status', 'done' );
}

/**
 * Import categories from backend
 */
function taager_import_categories() {
	$categories = taager_call_API( 'GET', taager_get_url('CATEGORIES') );
	
	$taager_categories_name_lits = array();
	if (isset($categories->data)) {
		for ( $i = 0; $i < count( $categories->data ); $i++ ) {
			$category = $categories->data[ $i ];
			$taager_categories_name_lits[] = $category->text;
			
			// Add new category if don't exist alrady
			if ( ! term_exists( $category->text, 'product_cat' ) ) {
				$cat_id_data = wp_insert_term( $category->text, 'product_cat' );
				$cat_id = $cat_id_data['term_id'];
				add_term_meta( $cat_id, "category_type", "taager" );
			}
		}
	}
	
	if( !empty( $taager_categories_name_lits ) ) :
		update_option( 'taager_categories_name_lits', $taager_categories_name_lits );
	endif;
}

/**
 * Import provinces from backend
 */
function taager_import_provinces() {
	taager_create_provinces_table();
	global $wpdb;

	$api_provinces = taager_call_API( 'GET', taager_get_url('PROVINCES_ZONES_DISTRICTS') )->data;
	
	$table_full_name = PROVINCES_TABLE_NAME;
	$db_provinces = $wpdb->get_results( $wpdb->prepare("SELECT * from $table_full_name"), ARRAY_A );

	foreach ( $api_provinces as $api_province ) {

		$exist_province_ids = array();
		foreach($db_provinces as $id => $db_province) {
			if($api_province->provinceId === $db_province["province"]) {
				$exist_province_ids[] = $id + 1;
			}
		}

		// Add new province if don't exist alrady
		if ( count($exist_province_ids) === 0 ) {
			$wpdb->insert(
				$table_full_name,
				array(
					'province'         => $api_province->provinceId,
					'province_name'    => json_encode($api_province->provinceName),
					'shipping_revenue' => $api_province->shippingRevenue,
					'active_status'    => 1,
				),
				array( '%s', '%s', '%d' )
			);
		} else {
			foreach($exist_province_ids as $exist_province_id) {
				$query = $wpdb->update(
					$table_full_name,
					array(
						'province_name'    => json_encode($api_province->provinceName),
						'shipping_revenue' => $api_province->shippingRevenue,
						'active_status'    => 1,
					),
					array(
						"id" => $exist_province_id,
					),
				);
			}
		}
	}

	//set inactive if the state deleted from APIs
	foreach($db_provinces as $id => $db_province) {
		$found_province = taager_search_for_id($db_province["province"], $api_provinces);
		if(!isset($found_province)) {
			$wpdb->query($wpdb->prepare("UPDATE `".$table_full_name."` SET `active_status`=0 where id=".$db_province["id"]));
		}
	}
}

/**
 * Disable shipping functionality
 */
add_action( 'wp', 'taager_default_shipping' );
function taager_default_shipping() {
	if ( ! is_admin() && has_taager_product_in_cart() ) {
		add_filter( 'wc_shipping_enabled', '__return_false' );
	}
}

/**
 * Send created order to backend via POST order API
 */
add_action( 'woocommerce_checkout_order_created', 'taager_send_order' );
function taager_send_order( $order ) {
	global $wpdb;

	$wp_order_id = $order->get_id(); // Get order ID

	if ( ! has_taager_product_in_order( $wp_order_id ) ) {
		return;
	}

	$is_external       = true;
	//$external_name   = 'WooCommerce';
	$external_name     = ucfirst(get_bloginfo( 'name' ));
	$order_received_by = 'WooCommerce_cart';
	//$order_received_by = ucfirst(get_bloginfo( 'name' ));
	$order_status      = 'order_received';

	$customer_name = $order->get_formatted_billing_full_name(); // Get customer's full name
	$full_address  = $order->get_billing_address_1(); // Get full address
	$province      = $order->get_billing_state(); // Get province
	$phone_number  = $order->get_billing_phone(); // Get phone number
	$order_note    = $order->get_customer_note(); // Get customer note
	
	$phone_number2 = get_post_meta( $wp_order_id, '_billing_phone2', true );
	
	$product_ids        = array();
	$product_prices     = array();
	$product_quantities = array();

	$ta__flatrate_shipping;
	$my__flatrateId = array();
	// Loop through order items to get products info
	foreach ( $order->get_items() as $item_id => $item ) {
		$product              = $item->get_product();
		$product_ids[]        = $product->get_sku(); // Get the product SKU
		$product_prices[]     = intval( $item->get_total() ); // Get the product price
		$product_quantities[] = $item->get_quantity(); // Get the product quantities

		$flatrate_product_id   = $item->get_product_id();
		$ta__flatrate_shipping = get_post_meta( $flatrate_product_id, 'ta__shipping_charge', true );
		if ( $ta__flatrate_shipping == 'yes' || ! empty( $ta__flatrate_shipping ) ) {
			if(empty($my__flatrateId)) {
				$my__flatrateId[] = $product->get_sku();
			}
		}
	}

	taager_plugin_upgrade_function();
	$plugin_version = get_option( 'ta_plugin_version' );

	$cash_on_delivery = intval( $order->get_total() );
	// Payload for POST order API
	$order_data = array(
		'isExternal'         => $is_external,
		'externalName'       => $external_name,
		'orderReceivedBy'    => $order_received_by,
		'status'             => $order_status,
		'customerName'       => $customer_name,
		'fullAddress'        => $full_address,
		'province'           => $province,
		'phoneNumber'        => $phone_number,
		'phoneNumberAlt'     => $phone_number2,
		'message'            => $order_note,
		'cashOnDelivery'     => $cash_on_delivery,
		'productIds'         => $product_ids,
		'productPrices'      => $product_prices,
		'productQuantities'  => $product_quantities,
		'flat_rate_products' => $my__flatrateId,
	);
	if($plugin_version) {
		$order_data['pluginVersion'] = $plugin_version;
	}

	// Send new order to server and get back the order info from the server
	$response  = taager_call_API( 'POST', taager_get_url('ORDERS'), json_encode( $order_data ) );
	if($response->data->orderID) {
		ta_track_event('taager_WP_order_placed', array(
			'Customer name'       => $customer_name,
			'Phone number'        => $phone_number,
			'Phone number 2'     	=> $phone_number2,
			'Province'           	=> $province,
			'Payment amount'     	=> $cash_on_delivery,
			'Order id'						=> $response->data->orderID,
			'Number of products'	=> count($product_ids),
		));
	} else {
		$order_rejection_reason = $response->message;
		ta_track_event('taager_WP_order_rejected', array(
			'Customer name'       => $customer_name,
			'Phone number'        => $phone_number,
			'Phone number 2'     	=> $phone_number2,
			'Province'           	=> $province,
			'Payment amount'     	=> $cash_on_delivery,
			'Rejection reason' => $order_rejection_reason,
		));
		update_post_meta( $wp_order_id, 'taager_order_rejection_reason', $order_rejection_reason );
	}

	$order_num = $response->data->orderNum;
	$ta_order_id = $response->data->orderID;
	$ta_order_status = $response->data->status;

	// Save meta data for order number
	update_post_meta( $wp_order_id, '_ta_order_num', $order_num );
	update_post_meta( $wp_order_id, 'taager_order_id', $ta_order_id );
	update_post_meta( $wp_order_id, 'taager_order_status', $ta_order_status );
}

/**
 * Update order status as 'pending' when created new order
 */
add_action( 'woocommerce_thankyou', 'taager_update_order_status' );
function taager_update_order_status( $order_id ) {
	delete_option('taager_shipping_province');

	$order = wc_get_order( $order_id );

	// Get order number from meta data
	$order_num = get_post_meta( $order_id, '_ta_order_num', true );

	if($order_num) {
		// Query params for GET order API
		$query_params = array(
			'order_num' => $order_num,
		);

		// Get existing order from server
		$response     = taager_call_API( 'GET', taager_get_url('ORDERS'), $query_params );
		$order_status = ( $response->data->status );

		if ( $order_status == 'order_received' ) {
			// Update order status as 'processing'
			$order->update_status( 'processing' );
		}
	} else {
		$rejection_reason = taager_get_order_rejection_reason($order_id);
		if($rejection_reason) {
			// display failure reason except if reason is spammer behavior
			if(
				!str_contains($rejection_reason, 'spam behavior') &&
				!str_contains($rejection_reason, 'العميل له تاريخ من إساءة إستخدام النظام')
			) {
				$rejection_reason = __('Order placement failed', 'taager-plugin');
				echo "<div class='order-rejection-reason-text'>$rejection_reason</div>" ;
			}
			$order->update_status( 'failed' );
		}
	}

}

/**
 * Send order status cancelled to backend via PUT order API
 */
add_action( 'woocommerce_order_status_changed', 'taager_cancel_order' );
function taager_cancel_order( $order_id ) {
	if ( ! has_taager_product_in_order( $order_id ) ) {
		return;
	}
	$order = wc_get_order( $order_id );

	// Get updated order status
	$order_status = $order->get_status();

	if ( $order_status == 'cancelled' ) {
		// Get order number from meta data
		$order_num = get_post_meta( $order_id, '_ta_order_num', true );
		$order_id = get_post_meta( $order_id, 'taager_order_id', true );

		// Cancel order in the server
		taager_call_API( 'PUT', API_URL['CANCEL_ORDER'] . intval( $order_num ) );
		ta_track_event('taager_WP_order_cancelled', array(
			'Order id' => $order_id,
		));
	}
}

/**
 * restricts addition of taager product in cart
 * if already non taager prodect added and vice versa
 */
add_filter( 'woocommerce_add_to_cart_validation', 'taager_cart_validation', 10, 3 );
function taager_cart_validation( $passed, $product_id, $quantity ) {

	global $woocommerce;
	$items = $woocommerce->cart->get_cart();

	foreach ( $items as $item => $values ) {
		$cart_taager_product = get_post_meta( $values['data']->get_id(), 'taager_product', true ) ? get_post_meta( $values['data']->get_id(), 'taager_product', true ) : 0;
		break;
	}
	$current_taager_product = ( get_post_meta( $product_id, 'taager_product', true ) ) ? get_post_meta( $product_id, 'taager_product', true ) : 0;

	if ( $items && ( $cart_taager_product != $current_taager_product ) ) {
		//$message = ($current_taager_product) ? __( 'You added taager product.', 'woocommerce' ) : __( 'You added non-taager product.', 'woocommerce' );
		wc_add_notice( __( 'You can not add this product at the moment.', 'taager-plugin' ), 'error' );
		$passed = false;
	}
	return $passed;
}

/**
 * returns the respective shipping charge according to the taager province
 */
function taager_province_shipping( $province ) {
	global $wpdb;
	$table_full_name = PROVINCES_TABLE_NAME;
	$province_object = $wpdb->get_row( 'SELECT * FROM ' . $table_full_name . " WHERE province = '" . $province . "'" );
	return isset($province_object->shipping_revenue) ? intval( $province_object->shipping_revenue ) : 0;
}

/**
 * checks if cart has taager product or not
 * checks first cart item
 */
function has_taager_product_in_cart() {
	global $woocommerce;
	if(!is_null( $woocommerce ->cart)) {
		$items = $woocommerce->cart->get_cart();
		if ( ! $items ) {
			return false;
		}
		foreach ( $items as $item => $values ) {
			$cart_taager_product = get_post_meta( $values['data']->get_id(), 'taager_product', true ) ? true : false;
			break;
		}
		return $cart_taager_product;
	}
	return false;
}

function has_taager_product_in_order( $order_id ) {
	$order = wc_get_order( $order_id );
	foreach ( $order->get_items() as $item_id => $item ) {
		$p_id = $item->get_product_id();
		return get_post_meta( $p_id, 'taager_product', true ) ? true : false;
		exit;
	}
}

/**
 * adding shipping cost according to taager province
 */
add_action( 'woocommerce_cart_calculate_fees', 'ta_add_shipping_fee_by_taager' );
function ta_add_shipping_fee_by_taager() {
	if ( ( is_admin() && ! defined( 'DOING_AJAX' ) ) || ( ! has_taager_product_in_cart() || is_cart() ) ) {
		return;
	}
	$ta__check_shipping;
	foreach ( WC()->cart->get_cart() as $cart_item ) {
		$product_id         = $cart_item['product_id'];
		$ta__check_shipping = get_post_meta( $product_id, 'ta__shipping_charge', true );
		$ta__cs[]           = $ta__check_shipping;
	}
	if ( in_array( 'yes', $ta__cs ) ) {
		$ta__check_shipping = 'yes';
	} else {
		$ta__check_shipping = 'no';
	}
	//echo $ta__check_shipping;

	if ( $ta__check_shipping == 'no' ) {
		$shipping_address = '';
		if ( isset($_POST['city']) && sanitize_text_field($_POST['city']) ) {
			$shipping_address = sanitize_text_field($_POST['city']);
		}

		if ( isset($_POST['state']) && sanitize_text_field($_POST['state']) ) {
			$shipping_address = sanitize_text_field($_POST['state']);
		}

		$shipping_fee_name = __("Shipping", "taager-plugin");
	
		if ( $shipping_address ) {
			//ta__shipping_charge
			$shipping_charge                      = taager_province_shipping( $shipping_address );
			add_option('taager_shipping_province', $shipping_address);
			WC()->cart->add_fee( $shipping_fee_name, $shipping_charge );
		} elseif ( get_option('taager_shipping_province') ) {
			$shipping_charge = taager_province_shipping( get_option('taager_shipping_province') );
			WC()->cart->add_fee( $shipping_fee_name, $shipping_charge );
		}
	}
}

/**
 * validation for product price in compared to taager product price
 */
add_action( 'save_post', 'taager_product_save_post', 10, 2 );
function taager_product_save_post( $prod_id, $wp_product_post ) {

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE || $wp_product_post->post_type != 'product' ) {
		return;
	}

	$errors = array();
	$ta_post_id = $wp_product_post->ID;
	if ( $ta_post_id ) {
		$ta__check_shipping = get_post_meta( $ta_post_id, 'ta__shipping_charge', true );
		if(get_post_meta( $ta_post_id, '_product_attributes', true )) {
			$errors = taager_check_product_variants_prices($ta_post_id, $ta__check_shipping);
		} else {
			$errors = taager_check_product_price($ta_post_id, $ta__check_shipping);
		}
	}

	if ( ! empty( $errors ) ) {
		remove_action( 'save_post', 'taager_product_save_post' );

		add_option( 'my_admin_notices', $errors);
		$wp_product_post->post_status            = 'draft';

		wp_update_post( $wp_product_post );

		add_action( 'save_post', 'taager_product_save_post' );

		add_filter( 'redirect_post_location', 'taager_product_redirect_filter' );
	}
}

function taager_check_product_price ($ta_post_id, $ta__check_shipping) {
	$errors = array();
	$taager_product_name = get_post( $ta_post_id ) -> post_title;
	$taager_product_sku = get_post_meta( $ta_post_id, '_sku', true );

	$taager_price      = get_post_meta( $ta_post_id, '_ta_product_price', true );
	$regular_price        = get_post_meta( $ta_post_id, '_regular_price', true );

	$is_taager_product = get_post_meta( $ta_post_id, 'taager_product', true );

	if ( $ta__check_shipping == 'yes' ) {
		global $wpdb;
		$ta_provinces_name   = PROVINCES_TABLE_NAME;
		$ta__select_max      = $wpdb->get_row( "SELECT * FROM $ta_provinces_name WHERE active_status=1 ORDER BY shipping_revenue DESC LIMIT 1" );
		$taager_max_shipping = $ta__select_max->shipping_revenue;
		$taager_profit       = get_post_meta( $ta_post_id, '_ta_product_profit', true );

		$ta_new_min_price  = $taager_price + $taager_max_shipping;
		$ta_currency       = get_woocommerce_currency_symbol();
		if ( $is_taager_product && ( $regular_price  < $ta_new_min_price ) ) {
			$error_name = 'ta-price' . $taager_product_sku;
			$errors[$error_name] = 'Product price of ' . $taager_product_name . ' [[' . $taager_product_sku . ']]' . ' should be atleast ' . strip_tags(wc_price( $ta_new_min_price )) . ' instead of ' . strip_tags(wc_price( $regular_price ));
			update_post_meta( $ta_post_id, '_regular_price', $regular_price );
		}
	} else {
		if ( $is_taager_product && ( $regular_price  < $taager_price ) ) {
			$error_name = 'ta-price' . $taager_product_sku;
			$errors[$error_name] = 'Product price of ' . $taager_product_name . ' [[' . $taager_product_sku . ']]' . ' should be atleast ' . strip_tags(wc_price( get_post_meta( $ta_post_id, '_ta_product_price', true ))) . ' instead of ' . strip_tags(wc_price( $regular_price ));
			update_post_meta( $ta_post_id, '_regular_price', $regular_price );
		}
	}
	return $errors;
}

function taager_check_product_variants_prices ($ta_post_id, $ta__check_shipping) {
	$parent_product = wc_get_product( $ta_post_id );
	$variants_ids = $parent_product->get_children();
	$errors = array();
	foreach ($variants_ids as $variant_id) {
		$errors = array_merge($errors,taager_check_product_price($variant_id, $ta__check_shipping));
	}
	return $errors;
}

function taager_product_redirect_filter( $location ) {
	$location = remove_query_arg( 'message', $location );

	$location = add_query_arg( 'ta-price', 'error', $location );

	return $location;
}

// Add new admin message
add_action( 'admin_notices', 'taager_error_admin_message', 99 );

function taager_error_admin_message() {
	if ( isset( $_GET['ta-price'] ) && $_GET['ta-price'] == 'error' ) {
		// lets get the errors from the option product_errors
		$errors = get_option('my_admin_notices');

		delete_option('my_admin_notices'); ?>

		<div id="notice" class="error">
			<ul>
			
				<?php
				// Because we are storing as an array we should loop through them
				foreach ( $errors as $error ) { 
				?>

				<li> <?php echo esc_html($error); ?> </li>

				<?php } ?> 
		
			</ul>
		</div> 
		
		<?php

		// add some jQuery
		?>
		<script>
		jQuery(function($) {
			$("#_regular_price").css({"border": "1px solid red"})
		});
		</script>
		<?php
	}
}

//add three extra fields in order dashboard
add_filter( 'manage_edit-shop_order_columns', 'taager_order_admin_dashboard_column' );
function taager_order_admin_dashboard_column( $columns ) {
	$new_columns = ( is_array( $columns ) ) ? $columns : array();
	// unset( $new_columns[ 'order_actions' ] );

	//edit this for your column(s)
	//all of your columns will be added before the actions column
	$new_columns['order_id_']                 = 'Order ID';
	$new_columns['order_status_']             = 'Order Status';
	$new_columns['order_rejection_reason']    = 'Rejection Reason';
	//$new_columns['suspended_reason']          = 'Suspended Reason';
	//$new_columns['customer_rejected_reason']  = 'Customer Rejected Reason';
	//$new_columns['delivery_suspended_reason'] = 'Delivery Suspended Reason';

	//stop editing
	$new_columns['order_actions'] = $columns['order_actions'];
	return $new_columns;
}
add_action( 'manage_shop_order_posts_custom_column', 'taager_order_admin_dashboard_column_values' );
function taager_order_admin_dashboard_column_values( $column ) {
	global $post;

	switch ( $column ) {
		case 'order_id_':
			echo esc_html( get_post_meta( $post->ID, 'taager_order_id', true ) );
			break;
		case 'order_status_':
			echo esc_html( get_post_meta( $post->ID, 'taager_order_status', true ) );
			break;
		case 'order_rejection_reason':
			echo esc_html( taager_get_order_rejection_reason($post->ID) );
			break;
	}
}

//delete taxonomy hook
//add_action('delete_term_taxonomy', 'taager_delete_term_taxonomy');
function taager_delete_term_taxonomy()  {
	
	if(!is_network_admin() && ($_POST['taxonomy'] == 'product_cat')) {
		$term_id = intval($_POST['tag_ID']);
		$term_name = get_term( $term_id )->name;	
		
		$categories = taager_call_API( 'GET', taager_get_url('CATEGORIES') );
		//echo "<pre>"; print_r($categories); exit();
		
		if($categories) {
			for ( $i = 0; $i < count( $categories->data ); $i++ ) {
				$category = $categories->data[ $i ];
				
				if($category->text == $term_name) {
					echo "You can not delete this category";
					break;
					exit();
				} 
			}
		} else {
			exit();
		}
	}
}

add_filter( 'product_cat_row_actions', 'taager_product_cat_row_actions' , 10, 2 );
function taager_product_cat_row_actions($actions, $term) {
	
	if(!is_network_admin()) {
		$taager_categories_names = get_option('taager_categories_name_lits');
		if(in_array($term->name, $taager_categories_names)) {
			unset($actions['edit']);
			unset($actions['inline hide-if-no-js']);
			unset($actions['delete']);
		} 
	} 
	
	return $actions;
}

//add meta for taager category 
//add_action('admin_init', 'taager_update_prod_cat_term_meta');
function taager_update_prod_cat_term_meta() {
	
	global $wpdb;
	
	$tagger_category = array();
	$categories = taager_call_API( 'GET', taager_get_url('CATEGORIES') );
	if($categories) {
		for ( $i = 0; $i < count( $categories->data ); $i++ ) {
			$tagger_category[] = $categories->data[ $i ]->text;
		}
		
		$db_prefix = $wpdb->prefix;	
			
		$cat_list = $wpdb->get_results(
			"SELECT * FROM
					" . $db_prefix . "terms
				LEFT JOIN
					" . $db_prefix . "term_taxonomy ON
						" . $db_prefix . "terms.term_id = " . $db_prefix . "term_taxonomy.term_id
				WHERE
					" . $db_prefix . "term_taxonomy.taxonomy = 'product_cat'");
			
		foreach($cat_list as $cat) {
			if ( in_array($cat->name,$tagger_category) &&  $cat->slug != 'uncategorized') {
				
				$check_termmeta = $wpdb->get_col(
					"SELECT meta_id FROM `" . $db_prefix . "termmeta`
						WHERE `term_id` = $cat->term_id
						AND `meta_key` = 'category_type' 
						AND `meta_value` = 'taager'");
				
				if(!$check_termmeta) {	
					$term_id = $cat->term_id;
					$meta_key = 'category_type';
					$meta_value = 'taager';
					
					$term_sql = $wpdb->prepare("INSERT INTO `" . $db_prefix . "termmeta` (`term_id`, `meta_key`, `meta_value`) values (%d, %s, %s)", $term_id, $meta_key, $meta_value);
					$wpdb->query($term_sql);
				}
			}
		}
	}  
}

// Add How to use Meta box to admin products pages
add_action( 'add_meta_boxes', 'taager_create_product_technical_specs_meta_box', 20 );
function taager_create_product_technical_specs_meta_box() {
    add_meta_box(
        'how_to_use_product_meta_box',
        __( 'How to use', 'taager-plugin' ),
        'taager_how_to_use_content_meta_box',
        'product',
        'normal',
        'default'
    );
}

// How to use metabox content in admin product pages
function taager_how_to_use_content_meta_box( $post ){
    $product = wc_get_product($post->ID);
    $content = $product->get_meta( '_ta_how_to_use' );

    echo '<div class="product_ta_how_to_use">';

    wp_editor( $content, '_ta_how_to_use', ['textarea_rows' => 10]);

    echo '</div>';
}

// Save How to use field value from product admin pages
add_action( 'woocommerce_admin_process_product_object', 'save_product_taager_how_to_use_field', 10, 1 );
function save_product_taager_how_to_use_field( $product ) {
	$ta_how_to_use_field = sanitize_textarea_field($_POST['_ta_how_to_use']);
	if ( $ta_how_to_use_field )
		$product->update_meta_data( '_ta_how_to_use', wp_kses_post( $ta_how_to_use_field ) );
}

// Add "How to use" product tab
add_filter( 'woocommerce_product_tabs', 'taager_add_how_to_use_product_tab', 10, 1 );
function taager_add_how_to_use_product_tab( $tabs ) {
	
	global $product;
	if( $product->get_meta( '_ta_how_to_use' ) ) {
		$tabs['test_tab'] = array(
			'title'         => __( 'How to use', 'taager-plugin' ),
			'priority'      => 20,
			'callback'      => 'taager_display_how_to_use_product_tab_content'

		);
	}
    return $tabs;
}

// Display "How to use" content tab
function taager_display_how_to_use_product_tab_content() {
    global $product;
    echo '<div class="wrapper-how_to_use">' . esc_html($product->get_meta( '_ta_how_to_use' )) . '</div>';
}

//shipping update
function taager_shipping_update() {
	
	$taager_last_update_provinces = get_option('taager_last_update_provinces');
	ta_track_event('taager_WP_update_shipping_fees', array(
		'Last update date' => $taager_last_update_provinces,
	));

	taager_import_provinces();
	taager_import_zones();
	$update_date = date("Y-m-d H:i:s");
	update_option( 'taager_last_update_provinces', $update_date );
	
	echo $shipping_update_time = esc_html(date('d/m/Y h:i:s A', strtotime($update_date)));
	die;
}
add_action( 'wp_ajax_taager_shipping_update', 'taager_shipping_update' );

add_action( 'woocommerce_admin_order_data_after_billing_address', 'taager_custom_checkout_field_display_admin_order_meta', 10, 1 );
function taager_custom_checkout_field_display_admin_order_meta($order){
	$order_id = $order->get_id();
	if ( get_post_meta( $order_id, '_billing_phone2', true ) ) 
		echo '<p><strong>'.__('Phone 2', 'taager-plugin').': </strong></br>' . esc_html( get_post_meta( $order_id, '_billing_phone2', true ) ) . '</p>';
}

add_action( 'woocommerce_email_after_order_table', 'taager_show_new_checkout_field_emails', 20, 4 );
function taager_show_new_checkout_field_emails( $order, $sent_to_admin, $plain_text, $email ) {
    if ( get_post_meta( $order->get_id(), '_billing_phone2', true ) ) 
			echo '<p><strong>'.__('alternate phone number', 'taager-plugin').': </strong></br>' .esc_html( get_post_meta( $order->get_id(), '_billing_phone2', true ) ) . '</p>';
}

//display taager price in single product edit page
add_action( 'woocommerce_product_options_general_product_data', 'ta_display_taager_price');
function ta_display_taager_price(){
	$product_id = intval( $_GET['post'] );
	if(!get_post_meta( $product_id, '_product_attributes', true )) {
		$is_taager_product = get_post_meta( $product_id, 'taager_product', true );
		if($is_taager_product) {
		
			$product_id = get_post_meta( $product_id, '_sku', true );
			if( $product_id ) {
				$pid_param = array('prod_ids' =>  $product_id );
				$product_data = taager_call_API( 'GET', taager_get_url('IMPORT_PRODUCTS', $pid_param)  );
				if(!empty($product_data->data)) {
					$taager_price = intval( $product_data->data[0]->productPrice );
				} else {
					$taager_price = 0;
				}
				if(!empty($product_data->data)) {
					woocommerce_wp_text_input( 
						array(
							'id'          => '_cs_taager_price',
							'label' 	  => __('Taager product price', 'taager-plugin'),
							'desc_tip'    => false,
							'custom_attributes' => array('readonly' => 'readonly'),
							'value'		  => $taager_price,
						) 
					);
				}
			}
		}
	}
}

add_action( 'woocommerce_variation_options_pricing', 'taager_add_taager_variant_price', 10, 3 );

//display taager price in variants product edit page
function taager_add_taager_variant_price( $loop, $variation_data, $variation ) {
	$product_id = $variation_data['_sku'][0];
	if($product_id) {
		$pid_param = array('prod_ids' => $product_id );
		$product_data = taager_call_API( 'GET', taager_get_url('IMPORT_PRODUCTS', $pid_param));
		if(!empty($product_data->data)) {
			woocommerce_wp_text_input( array(
				'id' => 'custom_field[' . $loop . ']',
				'label' => __( 'Taager product price', 'taager-plugin' ),
				'desc_tip'    => false,
				'custom_attributes' => array('readonly' => 'readonly'),
				'value' => $product_data->data[0]->productPrice,
			) );
		}
	}
}

function taager_search_for_id($id, $array) {
	foreach ($array as $key => $val) {
		if ($val->provinceId === $id) {
			return $key;
		} 
   }
   return null;
}
