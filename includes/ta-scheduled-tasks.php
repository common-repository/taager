<?php 

defined( 'ABSPATH' ) || exit;

/* 
  Adding more options for wp_schedule_event parameter $recurrence
  The values supported by default are ‘hourly’, ‘twicedaily’, ‘daily’, and ‘weekly’
*/
add_filter( 'cron_schedules', 'taager_add_custom_recurrences' );
function taager_add_custom_recurrences( $schedules ) {
	$schedules['every_five_minutes'] = array(
		'interval' => 300,
		'display'  => __( 'Every 5 Minutes', 'taager-plugin' ),
	);
	$schedules['every_ten_minutes'] = array(
		'interval' => 600,
		'display'  => __( 'Every 10 Minutes', 'taager-plugin' ),
	);
	$schedules['every_half_an_hour']    = array(
		'interval' => 1800,
		'display'  => __( 'Every Half an Hour', 'taager-plugin' ),
	);
	$schedules['every_one_hour']    = array(
		'interval' => 3600,
		'display'  => __( 'Every One Hour', 'taager-plugin' ),
	);
	return $schedules;
}

add_action( 'wp_loaded', 'taager_hourly_action_scheduler');
function  taager_hourly_action_scheduler() {
	if ( is_admin() ) { 
		$lastDateOrdersUpdated = get_option('ta_orders_last_updated_date');
		$lastDateProductsUpdated = get_option('ta_products_last_updated_date');

		if(
			strtotime($lastDateOrdersUpdated) < strtotime($lastDateProductsUpdated) &&
			taager_get_option_time_diff_in_minutes($lastDateOrdersUpdated) >= 239 &&
			taager_get_option_time_diff_in_minutes($lastDateProductsUpdated) >= 59
		) {
			update_option('ta_orders_last_updated_date', date('Y-m-d H:i:s', time()));
			hourly_action_scheduler_taager_order_check();
		} else if (
			taager_get_option_time_diff_in_minutes($lastDateProductsUpdated) >= 239 &&
			taager_get_option_time_diff_in_minutes($lastDateOrdersUpdated) >= 59
		) {
			update_option('ta_products_last_updated_date', date('Y-m-d H:i:s', time()));
			hourly_action_scheduler_taager_product_check();
		}
	}
}

/**
 * scheduled function that syncs taager products order hourly.
 *
 * @return void
 */
function hourly_action_scheduler_taager_order_check() {
	$args        = [
		'post_type'      => 'shop_order',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'meta_query'     => [
			'relation' => 'AND',
			[
				'key'     => '_ta_order_num',
				'compare' => 'EXISTS',
			],
			[
				'key'     => 'taager_order_status',
				'compare' => 'EXISTS',
			],
			[
				'key' => 'taager_order_status',
				'compare' => '!=',
				'value' => 'delivered',
			]
		],
	];
	$orders_count = 0;
	$order_lists = new WP_Query( $args );
	if ( $order_lists->have_posts() ) :
		while ( $order_lists->have_posts() ) :
			$order_lists->the_post();
			$wp_order_id     = get_the_ID();
			$ta_order_id = get_post_meta( $wp_order_id, 'taager_order_id', true );
			$ta_order_no  = get_post_meta( $wp_order_id, '_ta_order_num', true );
			if ($ta_order_id) {
				$query_params = array(
					'order_id' => $ta_order_id,
				);
				$response     = taager_call_API( 'GET', taager_get_url('ORDERS'), $query_params );
			} else if ($ta_order_no) {
				$query_params = array(
					'order_num' => $ta_order_no,
				);
				$response     = taager_call_API( 'GET', taager_get_url('ORDERS'), $query_params );
			}

			if ($response && property_exists($response, 'data')) {
				$orders_count++;
				$ta_status                  = ( isset( $response->data->status ) ) ? $response->data->status : '';
				$ta_suspendedReason         = ( isset( $response->data->suspendedReason ) ) ? $response->data->suspendedReason : '';
				$ta_customerRejectedReason  = ( isset( $response->data->customerRejectedReason ) ) ? $response->data->customerRejectedReason : '';
				$ta_deliverySuspendedReason = ( isset( $response->data->deliverySuspendedReason ) ) ? $response->data->deliverySuspendedReason : '';
				
				if(isset( $response->data->orderID )) {
					update_post_meta( $wp_order_id, 'taager_order_id', $response->data->orderID );
				}
				update_post_meta( $wp_order_id, 'taager_order_status', $ta_status );
				update_post_meta( $wp_order_id, 'taager_suspended_reason', $ta_suspendedReason );
				update_post_meta( $wp_order_id, 'taager_customer_rejected_reason', $ta_customerRejectedReason );
				update_post_meta( $wp_order_id, 'taager_delivery_suspended_reason', $ta_deliverySuspendedReason );
			}
		endwhile;
	endif;
	ta_track_event('taager_hourly_orders_update', array('Orders Count' => $orders_count));
	wp_reset_postdata();
}

/**
 * scheduled function that syncs imported taager products hourly
 *
 * @return void
 */
function hourly_action_scheduler_taager_product_check() {
	global $wpdb;
	$args            = [
		'post_type'      => array('product', 'product_variation'),
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'meta_query'     => [
			'relation' => 'AND',
			array(
				'key'     => 'taager_product',
				'compare' => '=',
				'value'   => '1',
			),
			array(
				'key'     => '_sku',
				'compare' => 'EXISTS',
			)
		],
	];
	$taager_products = new WP_Query( $args );
	$products_count = 0;
	if ( $taager_products->have_posts() ) :
		while ( $taager_products->have_posts() ) :
			$taager_products->the_post();
			$p_id = get_the_ID();

			$is_variation_product = get_post_type($p_id) === 'product_variation';

			$parent_post = get_post_parent($p_id);

			if($is_variation_product && (!$parent_post || $parent_post->post_status !== 'publish')) {
				continue;
			}

			$ta_shipping = get_post_meta( $p_id, 'ta__shipping_charge', true );
			$ta_id       = get_post_meta( $p_id, '_sku', true );

			if( $ta_id ) {
				$pid_param = array('prod_ids' => $ta_id );
				$response = taager_call_API( 'GET', taager_get_url('PRODUCT', $pid_param) );
				$products_count++;
			}

			if ( isset( $response->data ) ) {
				$all_ta_product     = $response->data;
				
				foreach($all_ta_product as $ta_product) {
					
					if ( $ta_product->isProductAvailableToSell ) {
						$stock_status = 'instock';
					} else {
						$stock_status = 'outofstock';
					}
					update_post_meta( $p_id, '_stock_status', $stock_status );
					$current_product_price = get_post_meta( $p_id, '_price', true );
					if ( 'yes' == $ta_shipping ) {
						$ta_provinces_name   = PROVINCES_TABLE_NAME;
						$ta__select_max      = $wpdb->get_row( "SELECT * FROM $ta_provinces_name WHERE active_status=1 ORDER BY shipping_revenue DESC LIMIT 1" );
						$taager_max_shipping = $ta__select_max->shipping_revenue;
						$taager_profit       = get_post_meta( $p_id, '_ta_product_profit', true );
						$taager_price        = get_post_meta( $p_id, '_ta_product_price', true );

						$ta_new_min_price = $taager_price + $taager_max_shipping;

						if ( intval( $current_product_price ) < intval( $ta_new_min_price ) ) {
							update_post_meta( $p_id, '_price', $ta_new_min_price );
						}
					} else {
						if ( intval( $current_product_price ) < intval( $ta_product->productPrice ) ) {
							update_post_meta( $p_id, '_regular_price', $ta_product->productPrice );
							update_post_meta( $p_id, '_price', $ta_product->productPrice );
							update_post_meta( $p_id, '_ta_product_profit', $ta_product->productProfit );
							$update_product_wc_status = array(
								'ID' => $p_id,
								'post_title'  => $ta_product->productName,
								'post_status' => 'draft',
							);
							wp_update_post( $update_product_wc_status );
						}
					}
					update_post_meta( $p_id, '_ta_product_price', $ta_product->productPrice );
				}
			}
		endwhile;
		wp_reset_postdata();
	endif;
	ta_track_event('taager_hourly_product_update', array('ProductsCount' => $products_count));
}

function taager_clear_cron_events() {
	$last_category_filter = get_option( 'ta_last_category_filter' );
	$last_name_filter = get_option( 'ta_last_name_filter' );
	$args = array( $last_category_filter, $last_name_filter );

	wp_clear_scheduled_hook( 'taager_import_products', $args );
	wp_clear_scheduled_hook( 'ta_hourly_update_hook' );
	wp_clear_scheduled_hook( 'ta_alternating_update_hook' );
}
