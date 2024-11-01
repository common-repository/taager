<?php

defined( 'ABSPATH' ) || exit;

define ('TAAGER_URL', 'https://woocommerce-api-gw.api.taager.com');

function taager_get_url($api_name, $options = null) {
  $taager_url = TAAGER_URL;
  switch ($api_name) {
    case 'LOGIN':
      return "$taager_url/auth/login";
    case 'COUNTRIES':
      return "$taager_url/countries";
    case 'CATEGORIES':
      return "$taager_url/category/";
    case 'ORDERS':
      return "$taager_url/orders";
    case 'CANCEL_ORDER':
      return "$taager_url/orders/cancel/";
    case 'PREPAID_ORDER':
      return "$taager_url/orders/prepaid";
    case 'PROVINCES':
      return "$taager_url/province/";
    case 'PROVINCES_ZONES_DISTRICTS':
      $country_code = taager_get_country_iso_code();
      return "$taager_url/countries/$country_code/provinces-with-zones-and-districts";
    case 'PRODUCT':
      $query_params = http_build_query($options);
      return "$taager_url/product/?$query_params";
    case 'IMPORT_PRODUCTS':
      $query_params = http_build_query (array_merge($options, array('taager_import' => '1')));
      return "$taager_url/product/?$query_params";
  }
}