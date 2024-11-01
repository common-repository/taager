<?php 

defined( 'ABSPATH' ) || exit;

function taager_import_zones () {
  taager_create_zones_table();
  
	global $wpdb;

	$api_provinces = taager_call_API( 'GET', taager_get_url('PROVINCES_ZONES_DISTRICTS') )->data;

	$zones_table_name = ZONES_TABLE_NAME;
	$db_zones = $wpdb->get_results( $wpdb->prepare("SELECT * from $zones_table_name"), ARRAY_A );
  foreach ( $api_provinces as $api_province ) {
    foreach ( $api_province->zones as $api_zone ) {
  
      $exist_zone_ids = array();
      foreach($db_zones as $id => $db_zone) {
        if($api_zone->zoneId === $db_zone["zone_id"]) {
          $exist_zone_ids[] = $id + 1;
        }
      }

      // Add new zone if don't exist already
      if ( count($exist_zone_ids) === 0 ) {
        $wpdb->insert(
          $zones_table_name,
          array(
            'province_id' => $api_province->provinceId,
            'zone_id' => $api_zone->zoneId,
            'zone_data' => json_encode($api_zone),
          ),
          array( '%s', '%s', '%s' )
        );
      } else {
        foreach($exist_zone_ids as $exist_zone_id) {
          $updated_zone_data = json_encode($api_zone);
          $wpdb->query(
            $wpdb->prepare(
              "UPDATE `".$zones_table_name."` SET `zone_data`=$updated_zone_data, where id=".$exist_zone_id
            )
          );
        }
      }
    }
  }
}

add_action('wp_ajax_taager_get_selected_province_zones', 'taager_get_selected_province_zones');
add_action('wp_ajax_nopriv_taager_get_selected_province_zones', 'taager_get_selected_province_zones');

function taager_get_selected_province_zones () {
	$selected_province_id = $_POST['selected_province_id'];

  global $wpdb;
  $zones = $wpdb->get_results( $wpdb->prepare("SELECT * from " . ZONES_TABLE_NAME . " WHERE province_id = '$selected_province_id'" ) , ARRAY_A);
  $language = get_bloginfo("language");
  if(is_array($zones) && count($zones)) {
    $zones_data = array_map(
      fn($value) => array(
        'zone_id' => $value['zone_id'],
        'zone_name' => str_starts_with($language, 'ar') ? json_decode($value['zone_data'])->zoneName->ar : json_decode($value['zone_data'])->zoneName->en,
      ),
      $zones
    );
    print_r(json_encode($zones_data));
  } else {
    print_r (json_encode( 
      array (
        array(
          "zone_id" => "",
          "zone_name" => __("No cities available", 'taager-plugin')
    ))));
  }
  wp_die();
}

add_action('wp_ajax_taager_get_selected_zone_districts', 'taager_get_selected_zone_districts');
add_action('wp_ajax_nopriv_taager_get_selected_zone_districts', 'taager_get_selected_zone_districts');

function taager_get_selected_zone_districts () {
	$selected_zone_id = $_POST['selected_zone_id'];

  global $wpdb;
  $zones = $wpdb->get_results( $wpdb->prepare("SELECT zone_data from " . ZONES_TABLE_NAME . " WHERE zone_id = '$selected_zone_id'" ) , ARRAY_A);
  $districts = json_decode($zones[0]['zone_data'])->districts;
  if(is_array($districts) && count($districts)) {
  $language = get_bloginfo("language");
  $districts_data = array_map(
    fn($value) => array(
      'district_id' => $value->districtId,
      'district_name' => str_starts_with($language, 'ar') ? $value->districtName->ar : $value->districtName->en,
    ),
    $districts
  );
    print_r(json_encode($districts_data));
  } else {
    print_r (json_encode( 
      array (
        array(
          "zone_id" => "",
          "zone_name" => __("No districts available", 'taager-plugin')
    ))));
  }
  wp_die();
}

add_action('wp_ajax_taager_get_zones_and_districts_label', 'taager_get_zones_and_districts_label');
add_action('wp_ajax_nopriv_taager_get_zones_and_districts_label', 'taager_get_zones_and_districts_label');

function taager_get_zones_and_districts_label () {
  print_r (json_encode(
    array('zone_label' => __('City', 'taager-plugin'), 'district_label' => __('District', 'taager-plugin')),
  ));
  wp_die();
}

