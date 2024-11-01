<?php

defined( 'ABSPATH' ) || exit;

function taager_country_selection_page() {
	$ta_available_countries = get_option('ta_available_countries');
	?>
	<h1 class="cs_pro_setting_heading"><?php _e('Country selection', 'taager-plugin') ?></h1>
	<h4 class="cs_pro_setting_subheading"><?php _e('Select the country to link your store to', 'taager-plugin') ?></h4>
	<div class="notice notice-warning">
		<p><?php _e('Kindly note that the selected country cannot be changed', 'taager-plugin') ?></p>
	</div>
	<form id="ta_country_selection_form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="ta_country_selection" />
		<table class="form-table">
			<tr>
				<th><?php _e('Country:', 'taager-plugin') ?></th>
				<td>
					<select name="country_selection" required id="country_selection_select_field">
						<option value=""><?php _e('Select country', 'taager-plugin') ?></option>
						<?php
						foreach ($ta_available_countries as $key=>$value) {
							echo '<option value="'. esc_attr( $value->countryIsoCode3 ) .'">'. esc_html( __($value->countryIsoCode3, 'taager-plugin') ) .'</option>';
						}
						?>
					</select>
				</td>
			</tr>
		</table>
		<?php submit_button( __('Submit', 'taager-plugin'), 'primary btn-country-selection' ) ?>
	</form>
	<?php
}

add_action( 'admin_post_ta_country_selection', 'taager_country_selection' );
function taager_country_selection() {
	$ta_initial_status = get_option('ta_initial_status');
	
	$ta_selected_country = sanitize_text_field($_POST['country_selection']);
	if ( $ta_selected_country ) {
		update_option('ta_selected_country', $ta_selected_country);
		taager_initialize();
	}
}
