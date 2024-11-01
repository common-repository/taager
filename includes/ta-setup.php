<?php
defined( 'ABSPATH' ) || exit;

function taager_on_register_prepare( $plugin_version ) {
  delete_option( 'ta_log_token' );
  taager_plugin_upgrade_function();
}

function taager_on_deactivation_prepare( $file ) {
  
}

function taager_plugin_upgrade_function() {
	$plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/taager/taager.php' );
	$plugin_version = $plugin_data['Version'];

  if($plugin_version !== get_option('ta_plugin_version')) {
    update_option( 'ta_plugin_version', $plugin_version );
  }
}
