<?php

defined( 'ABSPATH' ) || exit;

require_once plugin_dir_path( __FILE__ ) . 'ta-checkout-prepaid.php';

add_action( 'woocommerce_after_checkout_form', 'taager_after_checkout_form', 10 );
function taager_after_checkout_form(){
	ta_track_event('taager_WP_open_checkout_page');
}

/**
 * wc default hook to disable payment functionality on checkout.
 */
// Include plugin.php
require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
// Create the plugins folder and file variables
$plugin_folder = get_plugins( '/' . 'woocommerce' );
$plugin_file   = 'woocommerce.php';

// If the plugin version number is set, return it
if ( isset( $plugin_folder[ $plugin_file ]['Version'] ) ) {
	$wpowp_wc_version = $plugin_folder[ $plugin_file ]['Version'];

	if ( version_compare( $wpowp_wc_version, '4.7.0', '<' ) ) {
		// Disable payment method in cart & checcout
		add_filter( 'woocommerce_cart_needs_payment', '__return_false' );
	} else {
		// Disable payment method in cart & checcout
		add_filter( 'woocommerce_cart_needs_payment', '__return_false' );
		add_filter( 'woocommerce_order_needs_payment', '__return_false' );
	}
}

/**
 * Remove available payment gateways
 */
add_filter( 'woocommerce_available_payment_gateways', 'taager_all_payment_gateway_disable' );
function taager_all_payment_gateway_disable( $available_gateways ) {
	global $woocommerce;
	return [];
}

add_filter( 'woocommerce_checkout_fields', 'taager_billing_fields_modify' );
function taager_billing_fields_modify( $address_fields ) {
	$address_fields['billing']['billing_email']['required'] = false;
	if ( ! has_taager_product_in_cart() ) {
		return $address_fields;
	}
	if ( isset( $address_fields['billing']['billing_state'] ) && ! empty( $address_fields['billing']['billing_state'] ) ) {
		unset( $address_fields['billing']['billing_state']['validate'] );

		global $wpdb;
		$table_full_name = PROVINCES_TABLE_NAME;
		$provinces       = $wpdb->get_results( 'SELECT * FROM ' . $table_full_name . ' WHERE active_status = 1' );

		$province_array = array();

		foreach ( $provinces as $key => $value ) {
		  $language = get_bloginfo("language");
			if(json_decode($value->province_name)) {
				$province_array[ $value->province ] = str_starts_with($language, 'ar') ? json_decode($value->province_name)->ar : json_decode($value->province_name)->en;
			} else {
				$province_array[ $value->province ] = $value->province;
			}
		}

		// Customize city field
		$address_fields['billing']['billing_state']['type']    = 'select';
		$address_fields['billing']['billing_state']['options'] = $province_array;
		$address_fields['billing']['billing_state']['required'] = true;
	}

	return $address_fields;
}

/**
 * Customize address fields in checkout
 */
add_filter( 'woocommerce_default_address_fields', 'taager_customized_address_fields' );
function taager_customized_address_fields( $address_fields ) {
	global $wpdb;
	global $pagenow;
		
	if (!is_admin()) {
		if ( ! has_taager_product_in_cart() ) {
			return $address_fields;
		}
		// Remove company, country, address_2, state, postcode fields
		
		unset( $address_fields['company'] );
		unset( $address_fields['country'] );
		unset( $address_fields['address_2'] );
		unset( $address_fields['city'] );
		unset( $address_fields['postcode'] );
		unset( $address_fields['last_name'] );
		
		//first name in full width
		$address_fields['first_name']['class'] = array('form-row-wide'); 
		
		$address_fields['first_name']['label'] = __('Full name', 'taager-plugin'); 
	}
	return $address_fields;
}

/* Add phone number field on checkout page */
add_filter( 'woocommerce_checkout_fields', 'taager_custom_override_checkout_fields', 99 );
function taager_custom_override_checkout_fields( $fields ) { 
	
	$fields['billing']['billing_phone2'] = array(
		'type' => 'tel',
        'label'     => __('alternate phone', 'taager-plugin'),
		'required'  => false,
		'class'     => array('form-row-wide'),
		'clear'     => true
    );
	 
	return $fields;
}

add_filter( 'woocommerce_checkout_fields', 'taager_checkout_fields_custom_attributes', 999 );
function taager_checkout_fields_custom_attributes( $fields ) {

	$phone_number_digits_count = taager_get_country_phone_number_length();
	$phone_number_hint = taager_get_country_phone_number_hint();

	$fields['billing']['billing_phone']['custom_attributes']['minlength'] = $phone_number_digits_count;
	$fields['billing']['billing_phone']['custom_attributes']['errorMessage'] = __( "Phone number shall be " ) . $phone_number_digits_count . __( " digits" );
	$fields['billing']['billing_phone']['maxlength'] = $phone_number_digits_count;
	$fields['billing']['billing_phone']['placeholder'] = $phone_number_hint;

	$fields['billing']['billing_phone2']['custom_attributes']['minlength'] = $phone_number_digits_count;
	$fields['billing']['billing_phone2']['custom_attributes']['errorMessage'] = __( "Phone number shall be " ) . $phone_number_digits_count . __( " digits" );
	$fields['billing']['billing_phone2']['maxlength'] = $phone_number_digits_count;
	$fields['billing']['billing_phone2']['placeholder'] = $phone_number_hint;
	
	$fields['billing']['billing_phone2']['priority'] = 110;
	$fields['billing']['billing_email']['priority'] = 120;
	
	return $fields;
}

/* Show error on submitting the checkout form with wrong number of digits for phonenumber */
add_action( 'woocommerce_checkout_process', 'taager_checkout_fields_custom_validation' );
function taager_checkout_fields_custom_validation() {
	
	$phone_number_digits_count = taager_get_country_phone_number_length();

	if ( isset( $_POST['billing_phone'] )) {
		if ( ! (preg_match('/^[0-9]{'. $phone_number_digits_count .'}$/D', $_POST['billing_phone'] ))){
			wc_add_notice(
				__('Phone number shall be ', 'taager-plugin') . $phone_number_digits_count . __(' digits', 'taager-plugin')
				, 'error' );
		}
	}
	if ( isset( $_POST['billing_phone2'] ) && $_POST['billing_phone2'] != "") {
		if ( ! (preg_match('/^[0-9]{'. $phone_number_digits_count . '}$/D', $_POST['billing_phone2'] ))){
			wc_add_notice(
				__('Alternate phone number shall be ', 'taager-plugin') . $phone_number_digits_count . __(' digits', 'taager-plugin')
				, 'error' );
		}
	}	
}

add_action( 'wp_enqueue_scripts', 'taager_frontend_css_js_equeue', 15 );
function taager_frontend_css_js_equeue() {
	wp_enqueue_script( 'taager_custom_script', plugin_dir_url( dirname(__DIR__) ) . '/assets/js/taager_custom_script.js', array( 'jquery' ), rand(), true );
	
	wp_enqueue_style( 'taager_custom_script', plugin_dir_url( dirname(__DIR__) ) . '/assets/css/checkout_page.css', array(), rand() );
}
