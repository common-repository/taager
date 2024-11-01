<?php
/**
* Plugin Name: Taager
* Description: A plugin to manage Woocommerce products, orders with Taager.
* Author: Taager
* Version: 1.16.0
* Text Domain: taager-plugin
* Domain Path: /languages
**/

defined( 'ABSPATH' ) || exit;

require_once plugin_dir_path( __FILE__ ) . 'includes/ta-admin-functions.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/ta-functions.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/ta-checkbox.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/ta-setup.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/ta-scheduled-tasks.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/ta-products-import.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/ta-orders.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/ta-mixpanel-tracking.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/ta-provinces.php';

require_once plugin_dir_path( __FILE__ ) . 'includes/pages/ta-account.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/pages/ta-country-selection.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/pages/ta-products-import-page.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/pages/ta-checkout.php';

require_once plugin_dir_path( __FILE__ ) . 'includes/utilities/ta-multitenancy.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/utilities/ta-color-variants-mapper.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/utilities/ta-utilities.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/utilities/ta-apis.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/utilities/ta-tables.php';

/**
 * Create a new table for Provinces when activate this plugin
 */
register_activation_hook( __FILE__, 'taager_on_plugin_activation' );
function taager_on_plugin_activation() {
    taager_create_provinces_table();
    taager_create_zones_table();

    add_option( 'ta_initial_status', '' );
    add_option( 'ta_product_import_status', '' );
    add_option( 'ta_last_category_filter', '' );
    add_option( 'ta_last_name_filter', '' );
    add_option( 'import_gallery_lists', [] );
	
	$plugin_data = get_plugin_data( __FILE__ );
	$plugin_version = $plugin_data['Version'];

    taager_on_register_prepare( $plugin_version );
}

function taager_create_provinces_table() {
    global $wpdb;

    $table_full_name = PROVINCES_TABLE_NAME;

    $charset_collate = $wpdb->get_charset_collate();

    // Check to see if the table exists already, if not, then create it
    if ($wpdb->get_var( "show tables like '$table_full_name'" ) != $table_full_name) {
        $sql = "CREATE TABLE $table_full_name (
            id int NOT NULL auto_increment PRIMARY KEY,
            province varchar(255) NOT NULL,
            province_name json,
            shipping_revenue int NOT NULL,
            active_status boolean NOT NULL
        ) $charset_collate;";

        require_once( ABSPATH . '/wp-admin/includes/upgrade.php' );
        dbDelta($sql);
    } else {
        // Alter table to add province_name if it didn't exist previously
        $row = $wpdb->get_results( "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE table_name = '$table_full_name' AND column_name = 'province_name'"  );
        
        if(empty($row)){
            $wpdb->query("ALTER TABLE $table_full_name ADD province_name json");
        }
    }
}

function taager_create_zones_table() {
    global $wpdb;

    $taager_zones_table_name = ZONES_TABLE_NAME;

    $charset_collate = $wpdb->get_charset_collate();

    if ($wpdb->get_var( "show tables like '$taager_zones_table_name'" ) != $taager_zones_table_name) {
        $sql = "CREATE TABLE $taager_zones_table_name (
            id int NOT NULL auto_increment PRIMARY KEY,
            province_id varchar(255) NOT NULL,
            zone_id varchar(255) NOT NULL,
            zone_data json
        ) $charset_collate;";

        require_once( ABSPATH . '/wp-admin/includes/upgrade.php' );
        dbDelta($sql);
    }
}

/**
 * Clear cron event when deactivate this plugin
 */
register_deactivation_hook( __FILE__, 'taager_deactivate_plugin' );
function taager_deactivate_plugin() {
    taager_clear_cron_events();

	$plugin_data = get_plugin_data( __FILE__ );
	$plugin_version = $plugin_data['Version'];

    taager_on_deactivation_prepare( $plugin_version );
}

// Load plugin textdomain.
add_action( 'init', 'taager_plugins_loaded' );

function taager_plugins_loaded() {
    load_plugin_textdomain( 'taager-plugin', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
