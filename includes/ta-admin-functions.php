<?php

defined( 'ABSPATH' ) || exit;

// Create WordPress admin menu for Taager plugin Setting
add_action( 'admin_menu', 'taager_menu_setup' );
function taager_menu_setup() {
	taager_check_initial_status_validity();
	$ta_initial_status = get_option( 'ta_initial_status' );

	$page_title = 'Taager Account';
	$menu_title = 'Taager - منصة تاجر';
	$capability = 'manage_options';
	$menu_slug  = 'taager_account';
	$function   = 'taager_account_page';
	$icon_url   = 'dashicons-media-code';
	$position   = 85;

	add_menu_page( $page_title, $menu_title, $capability, $menu_slug, $function, $icon_url, $position );

	$account_page_title = __('Store linking', 'taager-plugin');
	
	add_submenu_page( $menu_slug, $account_page_title, $account_page_title, $capability, $menu_slug, $function, 1 );

	$parent_slug         = 'taager_account';
	$sub_menu_page_title = __('Products Import', 'taager-plugin');
	//$sub_menu_title      = 'Products Setting';
	$sub_menu_title      = __('Products Import', 'taager-plugin');
	$sub_menu_capability = 'manage_options';
	$sub_menu_slug       = 'taager_product_setting';
	$sub_menu_function   = 'taager_product_setting_page';
	
	$sub_menu_page_title2 = __('Shipping settings', 'taager-plugin');
	$sub_menu_title2      = __('Shipping settings', 'taager-plugin');
	$sub_menu_capability2 = 'manage_options';
	$sub_menu_slug2       = 'taager_shipping';
	$sub_menu_function2   = 'taager_shipping_page';
	
	$country_selection_page_title = __('Country selection', 'taager-plugin');
	$country_selection_title      = __('Country selection', 'taager-plugin');
	$country_selection_capability = 'manage_options';
	$country_selection_slug       = 'taager_country_selection';
	$country_selection_function   = 'taager_country_selection_page';

	if ( $ta_initial_status == 'done' ) {
		add_submenu_page( $parent_slug, $sub_menu_page_title, $sub_menu_title, $sub_menu_capability, $sub_menu_slug, $sub_menu_function, 0 );
		
		add_submenu_page( $parent_slug, $sub_menu_page_title2, $sub_menu_title2, $sub_menu_capability2, $sub_menu_slug2, $sub_menu_function2, 9 );
	} else if ( $ta_initial_status == 'country_selection') {
		add_submenu_page( $parent_slug, $country_selection_page_title, $country_selection_title, $country_selection_capability, $country_selection_slug, $country_selection_function, 0 );
	}
}

/**
 * Create cron job to import products with filters
 */
// add_action( 'admin_post_ta_products_filter', 'taager_products_filter' );
function taager_products_filter() {
	$product_category = sanitize_text_field($_POST['product_category']);
	$product_name     = sanitize_text_field($_POST['product_name']);
	if ( $product_category && $product_name ) {
	
		$ta_product_import_status = get_option( 'ta_product_import_status' );

		if ( ! $ta_product_import_status || $ta_product_import_status == 'done' ) {
			if ( sanitize_text_field($_POST['once_only']) ) {
				taager_import_products_function( $product_category, $product_name );
				wp_redirect( admin_url( 'edit.php?post_type=product' ) );
			} else {
				$last_category_filter = get_option( 'ta_last_category_filter' );
				$last_name_filter     = get_option( 'ta_last_name_filter' );

				$last_args = array( $last_category_filter, $last_name_filter );
				wp_clear_scheduled_hook( 'taager_import_products', $last_args );

				update_option( 'ta_last_category_filter', $product_category );
				update_option( 'ta_last_name_filter', $product_name );

				$args = array( $product_category, $product_name );
				// Delay the first run of `taager_import_products` by 1 mins
				wp_schedule_event( ( time() + MINUTE_IN_SECONDS ), 'twicedaily', 'taager_import_products', $args );

				wp_redirect( admin_url( 'edit.php?post_type=product' ) );
			}
		} elseif ( $ta_product_import_status == 'running' ) {
			wp_redirect( admin_url( 'admin.php?page=taager_product_setting' ) );
		}

		exit;
	}
}

function taager_shipping_page() {
	
	$taager_last_update_provinces = get_option('taager_last_update_provinces');
	
	?>
	<div class="ta_shipping_setting_section">
	
		<h1 class="ta_setting_heading"><?php _e('Shipping settings', 'taager-plugin') ?></h1>
		<form id="ta_shipping_setting_form" method="post" action="<?php //echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="taager_shipping_update" />
			<table class="form-table">
				<tr>
					<th><?php _e('Click here to update the shipping settings and fees:', 'taager-plugin') ?></th>
					<td><?php submit_button( __('Update', 'taager-plugin'), 'primary btn-shippig_update' ); ?></td>
				</tr>
				
				<?php 
				if($taager_last_update_provinces!='') { ?>
				<tr>
					<th><?php _e('Date of last update:', 'taager-plugin') ?></th>
					<td class="ta_last_updated_time"><?php echo esc_html(date('d/m/Y h:i:s A', strtotime($taager_last_update_provinces))); ?></td>
				</tr>
				<?php } ?>
			</table>
			<div class="ta_shipping_running"><img src="<?php echo esc_url(plugin_dir_url(  __DIR__  )); ?>assets/images/spin.gif"><?php _e('Updating', 'taager-plugin') ?></div>
			<div class="ta_shipping_response ta_shipping_response--hidden"><?php _e('Updated successfully', 'taager-plugin') ?></div>
		</form>
	</div>	
	<?php
}	