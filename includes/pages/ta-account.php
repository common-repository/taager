<?php
/**
 * Account Page
 */

defined( 'ABSPATH' ) || exit;

function taager_account_page() { 
	$ta_selected_country = taager_get_country_iso_code();
	$ta_user = get_option('ta_user');
	taager_plugin_upgrade_function();
	$plugin_version = get_option('ta_plugin_version');

	?>
	<h1><?php _e('Link your store with your Taager account', 'taager-plugin') ?></h1>

	<?php if ( !isset( $ta_user ) || !get_option( 'ta_api_username' ) || !get_option( 'ta_api_password' ) ) {
		echo "<h4>" . __('Please enter your Taager account login credentials', 'taager-plugin') . "</h4>";
	}

	if(isset($_GET['login']) && ('failed' == $_GET['login']))
		echo '<div class="error notice">
				<p>' . _e('Invalid login details. Please check your username/password and try again.', 'taager-plugin') . '</p>
			</div>';
	?>
	<form id="ta_login_form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="ta_account" />
		<?php
		if ( isset( $ta_user ) && get_option( 'ta_api_username' ) && get_option( 'ta_api_password' ) ) {
			?>
				<input type="hidden" name="taager_logout" value="logout" />
				<p><?php _e('Logged in as:', 'taager-plugin')?> <b><?php echo (esc_html($ta_user->username));?></b></p>
				<?php if($ta_selected_country) {
					echo "<p> " . __('Selected country:', 'taager-plugin') . " <b>" . esc_html( __($ta_selected_country, 'taager-plugin') ) . "</b></p>";
				} 
				if ($plugin_version) {
					echo "<p>" . __('Plugin version:', 'taager-plugin') . " <b>" . esc_html( $plugin_version ) . "</b></p>";
				}
			submit_button( __('Logout', 'taager-plugin') );
		} else {
			?>
				<table class="form-table">
			<tr>
				<th><?php _e('Username', 'taager-plugin') ?></th>
				<td>
					<input type="text" name="ta_api_username" required />
					<p><?php _e('Email or phone number', 'taager-plugin') ?></p>
				</td>
			</tr>
			<tr>
				<th><?php _e('Password', 'taager-plugin') ?></th>
				<td>
					<input type="password" name="ta_api_password" required />
				</td>
			</tr>
		</table>

			<?php submit_button( __('Login', 'taager-plugin') );
		}
		?>
	</form>
	<?php
}

/**
 * Import Category, Provinces after save Username and Password
 */
add_action( 'admin_post_ta_account', 'taager_account_authentication' );
function taager_account_authentication() {
	$ta_api_username = sanitize_text_field($_POST['ta_api_username']);
	if ( $ta_api_username && isset( $_POST['ta_api_password'] ) ) {
		$ta_api_password   = $_POST['ta_api_password'];
		$ta_initial_status = get_option( 'ta_initial_status' );
		$ta_selected_country = taager_get_country_iso_code();

		//authorize credentials
		$is_authorized = taager_login($ta_api_username, $ta_api_password);
		if( 'authorized' != $is_authorized ) {
			wp_redirect( admin_url( 'admin.php?page=taager_account&login=failed' ) );
			exit;
		}

		$ta_user = get_option('ta_user');
		$ta_user_features = $ta_user->features;

		if ( ! $ta_initial_status || $ta_initial_status == 'done' ) {
			if( ! $ta_selected_country ) {
				$ta_available_countries = taager_get_available_countries();
				update_option( 'ta_available_countries', $ta_available_countries);
				if( count($ta_available_countries) > 1 ) {
					$ta_initial_status = 'country_selection';
					update_option( 'ta_initial_status', $ta_initial_status );
					wp_redirect( admin_url( 'admin.php?page=taager_country_selection' ) );
				} else {
					$ta_selected_country = $ta_available_countries[0]->countryIsoCode3;
					update_option('ta_selected_country', $ta_selected_country);
					taager_initialize();
				}
			} else {
				taager_initialize();
			}
		} elseif ( $ta_initial_status == 'running' ) {
			wp_redirect( admin_url( 'admin.php?page=taager_account' ) );
		}

		exit;
	} else if( sanitize_text_field($_POST['taager_logout']) && ('logout' == sanitize_text_field($_POST['taager_logout'])) ) {
		taager_sign_out();
	}
}

function taager_sign_out() {
	ta_track_event('taager_WP_sign_out');
	delete_option('ta_user');
	delete_option('ta_api_username');
	delete_option('ta_api_password');
	delete_option('ta_initial_status');
	taager_clear_cron_events();
	wp_redirect( admin_url( 'admin.php?page=taager_account' ) );
}

/**
 * Attempt login
 */

function taager_login($ta_api_username, $ta_api_password) {
	$login_data = array(
		'username' => $ta_api_username,
		'password' => $ta_api_password
	);

	$login_response = taager_call_API( 'POST', taager_get_url('LOGIN'), json_encode( $login_data ) );
	if (isset( $login_response->data ) && isset( $login_response ->user )) {
		update_option('ta_api_username', $login_response->user->username);
		update_option('ta_api_password', $ta_api_password);
		update_option('ta_user', $login_response->user);
		ta_track_event('taager_WP_sign_in');
		return 'authorized';
	}
	return '';
}

function taager_get_available_countries() {
	$response = taager_call_API ( 'GET', taager_get_url('COUNTRIES') );
	$ta_available_countries = $response->data;
	return $ta_available_countries;
}

function taager_check_initial_status_validity() {
	$ta_initial_status = get_option( 'ta_initial_status' );

	/* If initial status is `running` this means the initialization was interrupted or an issue occurred during initialization */
	if($ta_initial_status == 'running') {
		taager_sign_out();
	}
}
