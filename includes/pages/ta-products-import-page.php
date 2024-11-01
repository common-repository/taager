<?php

defined( 'ABSPATH' ) || exit;

/**
 * Product setting page
 */
function taager_product_setting_page() {
	
	//import categoies first
	taager_import_categories();
	
	$taager_categories_names = get_option('taager_categories_name_lits');
	
	$args               = array(
		'taxonomy'   => 'product_cat',
		'hide_empty' => false,
	);
	$product_categories = get_terms( $args );
	?>
	<h1 class="cs_pro_setting_heading"><?php _e('Products Import', 'taager-plugin') ?></h1>
	<h4 class="cs_pro_setting_subheading"><?php _e('You can import a whole category or a single product', 'taager-plugin') ?></h4>
	<form id="ta_product_setting_form" method="post" action="<?php //echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="taager_products_import" />
		<table class="form-table">
			<tr>
				<th><?php _e('Import category', 'taager-plugin') ?></th>
				<td>
					<select name="product_category">
					<option value=""><?php _e('Choose a category', 'taager-plugin') ?></option>
					<?php
					foreach ( $product_categories as $value ) {
						if ( $value->name == 'Uncategorized' ) {
							continue;
						}
						if(in_array($value->name, $taager_categories_names)) {
						?>
						<option value="<?php echo esc_attr($value->name); ?>">
							<?php echo esc_html($value->name); ?>
						</option>
					<?php } 
					} ?>
					</select>
				</td>
			</tr>
			<tr><td colspan="3" class="cs_pro_setting_info_td"><?php _e('To import a category, select the intended category, and then click on the import button', 'taager-plugin') ?></td></tr>
			<tr>
				<th><?php _e('Import product (Product name):', 'taager-plugin') ?></th>
				<td>
					<input type="text" name="product_name" />
				</td>
			</tr>
			<tr><td colspan="3" class="cs_pro_setting_info_td"><?php _e('Enter the product name (Product name has to match the name on taager\'s website), then click on the import button', 'taager-plugin') ?></td></tr>
			<tr>
			<th><?php _e('Import product (Product SKU):', 'taager-plugin') ?></th>
				<td>
					<input type="text" name="product_ids" />
				</td>
			</tr>
			<tr><td colspan="3" class="cs_pro_setting_info_td" style="padding-bottom: 0px !important;">
					<?php _e('Enter the product SKU (Can be found on taager\'s website on the product\'s details page), then click on the import button', 'taager-plugin') ?>
				</td>
			</tr>
		</table>
		<?php submit_button( __('Import'), 'primary btn-import_products' ); ?>
		<div class="import_running"><img src="<?php echo esc_url(plugin_dir_url(  __DIR__  ))?>../assets/images/spin.gif"><?php _e('Products import in progress', 'taager-plugin') ?></div>
		<div class="import_response"></div>
	</form>
	<?php
}
