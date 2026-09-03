<?php
/**
 * Admin view: settings.
 *
 * @package LeadMagnet
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$s = get_option( 'lmf93_settings', array() );

$get = function ( $key, $default = '' ) use ( $s ) {
	return isset( $s[ $key ] ) ? $s[ $key ] : $default;
};

$providers = array(
	'none'         => __( 'None', 'leadmagnet' ),
	'turnstile'    => __( 'Cloudflare Turnstile', 'leadmagnet' ),
	'recaptcha_v2' => __( 'Google reCAPTCHA v2', 'leadmagnet' ),
	'recaptcha_v3' => __( 'Google reCAPTCHA v3', 'leadmagnet' ),
);

$emails_url = admin_url( 'admin.php?page=lmf93-emails' );
?>
<div class="wrap lmf93-admin">
	<h1><?php esc_html_e( 'LeadMagnet – Settings', 'leadmagnet' ); ?></h1>

	<?php LMF93_Admin::print_notice(); ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
		<?php wp_nonce_field( 'lmf93_save_settings' ); ?>
		<input type="hidden" name="lmf93_action" value="save_settings">

		<h2><?php esc_html_e( 'General', 'leadmagnet' ); ?></h2>
		<table class="form-table">
			<tr>
				<th><label for="lead_prefix"><?php esc_html_e( 'Lead reference prefix', 'leadmagnet' ); ?></label></th>
				<td><input name="lmf93[lead_prefix]" id="lead_prefix" type="text" value="<?php echo esc_attr( $get( 'lead_prefix', 'LM' ) ); ?>" class="small-text"> <span class="description"><?php esc_html_e( 'e.g. LM → LM-2026-000123', 'leadmagnet' ); ?></span></td>
			</tr>
			<tr>
				<th><label for="admin_email"><?php esc_html_e( 'Notification email', 'leadmagnet' ); ?></label></th>
				<td><input name="lmf93[admin_email]" id="admin_email" type="email" value="<?php echo esc_attr( $get( 'admin_email', get_option( 'admin_email' ) ) ); ?>" class="regular-text"></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Admin notifications', 'leadmagnet' ); ?></th>
				<td><label><input type="checkbox" name="lmf93[notify_admin]" value="1" <?php checked( $get( 'notify_admin', 1 ), 1 ); ?>> <?php esc_html_e( 'Email me when a new lead arrives', 'leadmagnet' ); ?></label></td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Localization', 'leadmagnet' ); ?></h2>
		<p class="description" style="max-width:720px;">
			<?php esc_html_e( 'These options make the form work in any country. By default the postal code accepts any format and the city field is a normal text field. Turn on auto-fill only if you provide a postal-code dataset for your country.', 'leadmagnet' ); ?>
		</p>
		<table class="form-table">
			<tr>
				<th><label for="postal_input_mode"><?php esc_html_e( 'Postal code format', 'leadmagnet' ); ?></label></th>
				<td>
					<select name="lmf93[postal_input_mode]" id="postal_input_mode">
						<option value="any" <?php selected( $get( 'postal_input_mode', 'any' ), 'any' ); ?>><?php esc_html_e( 'Any (letters and numbers) – works everywhere', 'leadmagnet' ); ?></option>
						<option value="numeric" <?php selected( $get( 'postal_input_mode', 'any' ), 'numeric' ); ?>><?php esc_html_e( 'Numeric only (e.g. Finland, Germany, USA)', 'leadmagnet' ); ?></option>
					</select>
					<p class="description"><?php esc_html_e( 'Choose "Numeric only" for countries with digit-only postal codes; otherwise leave "Any" so codes like SW1A 1AA or K1A 0B1 work.', 'leadmagnet' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="postal_max_length"><?php esc_html_e( 'Postal code max length', 'leadmagnet' ); ?></label></th>
				<td><input name="lmf93[postal_max_length]" id="postal_max_length" type="number" min="1" max="32" value="<?php echo esc_attr( $get( 'postal_max_length', 12 ) ); ?>" class="small-text"></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Auto-fill city from postal code', 'leadmagnet' ); ?></th>
				<td>
					<label><input type="checkbox" name="lmf93[postal_autocity]" value="1" <?php checked( $get( 'postal_autocity', 0 ), 1 ); ?>> <?php esc_html_e( 'Fill the city field automatically from a postal-code dataset', 'leadmagnet' ); ?></label>
					<p class="description"><?php esc_html_e( 'When off, the city field is a normal editable field (recommended for most countries). When on, provide a dataset URL below.', 'leadmagnet' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="postal_data_url"><?php esc_html_e( 'Postal dataset URL', 'leadmagnet' ); ?></label></th>
				<td>
					<input name="lmf93[postal_data_url]" id="postal_data_url" type="url" value="<?php echo esc_attr( $get( 'postal_data_url' ) ); ?>" class="regular-text">
					<p class="description">
						<?php esc_html_e( 'URL to a JSON file mapping postal codes to city names, e.g. { "00100": "Helsinki", "10001": "New York" }. Leave empty to use the bundled Finnish example dataset. See the Help page for how to add your own country.', 'leadmagnet' ); ?>
					</p>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Currency', 'leadmagnet' ); ?></h2>
		<table class="form-table">
			<tr>
				<th><label for="currency_symbol"><?php esc_html_e( 'Currency symbol', 'leadmagnet' ); ?></label></th>
				<td><input name="lmf93[currency_symbol]" id="currency_symbol" type="text" value="<?php echo esc_attr( $get( 'currency_symbol', '€' ) ); ?>" class="small-text"> <span class="description"><?php esc_html_e( 'e.g. €, $, £, kr', 'leadmagnet' ); ?></span></td>
			</tr>
			<tr>
				<th><label for="currency_position"><?php esc_html_e( 'Symbol position', 'leadmagnet' ); ?></label></th>
				<td>
					<select name="lmf93[currency_position]" id="currency_position">
						<option value="suffix" <?php selected( $get( 'currency_position', 'suffix' ), 'suffix' ); ?>><?php esc_html_e( 'After the amount (10 €)', 'leadmagnet' ); ?></option>
						<option value="prefix" <?php selected( $get( 'currency_position', 'suffix' ), 'prefix' ); ?>><?php esc_html_e( 'Before the amount ($10)', 'leadmagnet' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="price_note"><?php esc_html_e( 'Price note', 'leadmagnet' ); ?></label></th>
				<td><input name="lmf93[price_note]" id="price_note" type="text" value="<?php echo esc_attr( $get( 'price_note', 'excl. tax' ) ); ?>" class="regular-text"> <span class="description"><?php esc_html_e( 'Shown next to totals on the billing page, e.g. "excl. tax" or "excl. VAT".', 'leadmagnet' ); ?></span></td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Spam protection', 'leadmagnet' ); ?></h2>
		<table class="form-table">
			<tr>
				<th><label for="captcha_provider"><?php esc_html_e( 'CAPTCHA provider', 'leadmagnet' ); ?></label></th>
				<td>
					<select name="lmf93[captcha_provider]" id="captcha_provider">
						<?php foreach ( $providers as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $get( 'captcha_provider', 'none' ), $key ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php esc_html_e( 'Turnstile is free and privacy-friendly. Honeypot + timing checks are always on.', 'leadmagnet' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="captcha_site_key"><?php esc_html_e( 'Site key', 'leadmagnet' ); ?></label></th>
				<td><input name="lmf93[captcha_site_key]" id="captcha_site_key" type="text" value="<?php echo esc_attr( $get( 'captcha_site_key' ) ); ?>" class="regular-text"></td>
			</tr>
			<tr>
				<th><label for="captcha_secret_key"><?php esc_html_e( 'Secret key', 'leadmagnet' ); ?></label></th>
				<td><input name="lmf93[captcha_secret_key]" id="captcha_secret_key" type="password" value="<?php echo esc_attr( $get( 'captcha_secret_key' ) ); ?>" class="regular-text" autocomplete="off"></td>
			</tr>
			<tr>
				<th><label for="min_submit_seconds"><?php esc_html_e( 'Minimum submit time (s)', 'leadmagnet' ); ?></label></th>
				<td><input name="lmf93[min_submit_seconds]" id="min_submit_seconds" type="number" value="<?php echo esc_attr( $get( 'min_submit_seconds', 3 ) ); ?>" class="small-text"> <span class="description"><?php esc_html_e( 'Reject forms submitted faster than this (bot check).', 'leadmagnet' ); ?></span></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Submission limit', 'leadmagnet' ); ?></th>
				<td>
					<input name="lmf93[rate_limit_max]" type="number" value="<?php echo esc_attr( $get( 'rate_limit_max', 5 ) ); ?>" class="small-text">
					<?php esc_html_e( 'submissions per', 'leadmagnet' ); ?>
					<input name="lmf93[rate_limit_window]" type="number" value="<?php echo esc_attr( $get( 'rate_limit_window', 3600 ) ); ?>" class="small-text">
					<?php esc_html_e( 'seconds (per IP)', 'leadmagnet' ); ?>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Privacy & GDPR', 'leadmagnet' ); ?></h2>
		<table class="form-table">
			<tr>
				<th><label for="privacy_page_url"><?php esc_html_e( 'Privacy policy URL', 'leadmagnet' ); ?></label></th>
				<td><input name="lmf93[privacy_page_url]" id="privacy_page_url" type="url" value="<?php echo esc_attr( $get( 'privacy_page_url' ) ); ?>" class="regular-text"></td>
			</tr>
			<tr>
				<th><label for="privacy_version"><?php esc_html_e( 'Privacy policy version', 'leadmagnet' ); ?></label></th>
				<td><input name="lmf93[privacy_version]" id="privacy_version" type="text" value="<?php echo esc_attr( $get( 'privacy_version', '1.0' ) ); ?>" class="small-text"> <span class="description"><?php esc_html_e( 'Stored with each consent for audit purposes.', 'leadmagnet' ); ?></span></td>
			</tr>
			<tr>
				<th><label for="anonymize_after_days"><?php esc_html_e( 'Anonymize after (days)', 'leadmagnet' ); ?></label></th>
				<td><input name="lmf93[anonymize_after_days]" id="anonymize_after_days" type="number" value="<?php echo esc_attr( $get( 'anonymize_after_days', 730 ) ); ?>" class="small-text"> <span class="description"><?php esc_html_e( 'Personal data is automatically stripped from leads older than this. 0 = keep forever.', 'leadmagnet' ); ?></span></td>
			</tr>
			<tr>
				<th><label for="unsubscribe_page_url"><?php esc_html_e( 'Preferences page URL', 'leadmagnet' ); ?></label></th>
				<td><input name="lmf93[unsubscribe_page_url]" id="unsubscribe_page_url" type="url" value="<?php echo esc_attr( $get( 'unsubscribe_page_url' ) ); ?>" class="regular-text"> <span class="description"><?php esc_html_e( 'Where unsubscribe links point (optional).', 'leadmagnet' ); ?></span></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Uninstall', 'leadmagnet' ); ?></th>
				<td><label><input type="checkbox" name="lmf93[delete_data_on_uninstall]" value="1" <?php checked( $get( 'delete_data_on_uninstall', 0 ), 1 ); ?>> <?php esc_html_e( 'Delete all plugin data (leads, partners, settings) when the plugin is deleted', 'leadmagnet' ); ?></label>
				<p class="description"><?php esc_html_e( 'Off by default so you never lose data by accident.', 'leadmagnet' ); ?></p></td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Automated follow-up messages', 'leadmagnet' ); ?></h2>
		<p class="description" style="max-width:720px;">
			<?php
			printf(
				/* translators: %s: link to the Emails page */
				wp_kses( __( 'The review request and service reminder are configured on the <a href="%s">Emails</a> page, where you can turn each one on or off, edit its text and set its delay. They are off by default.', 'leadmagnet' ), array( 'a' => array( 'href' => array() ) ) ),
				esc_url( $emails_url )
			);
			?>
		</p>

		<h2><?php esc_html_e( 'Customer reviews', 'leadmagnet' ); ?></h2>
		<p class="description" style="max-width:640px;">
			<?php esc_html_e( 'The review request is sent as part of the follow-up messages. Create a hidden (noindex) review page with the shortcode [lmf93_review] and paste its address below. When a customer gives at least the chosen number of stars, they are sent to your public review service. Lower ratings ask for a reason so you can follow up personally.', 'leadmagnet' ); ?>
		</p>
		<table class="form-table">
			<tr>
				<th><label for="review_page_url"><?php esc_html_e( 'Review page URL (hidden)', 'leadmagnet' ); ?></label></th>
				<td>
					<input name="lmf93[review_page_url]" id="review_page_url" type="url" value="<?php echo esc_attr( $get( 'review_page_url' ) ); ?>" class="regular-text">
					<p class="description"><?php esc_html_e( 'A page containing the [lmf93_review] shortcode. This page is hidden from search engines automatically.', 'leadmagnet' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="review_threshold"><?php esc_html_e( 'Redirect threshold (stars)', 'leadmagnet' ); ?></label></th>
				<td>
					<select name="lmf93[review_threshold]" id="review_threshold">
						<?php
						$thr = (int) $get( 'review_threshold', 4 );
						for ( $t = 3; $t <= 5; $t++ ) {
							printf(
								'<option value="%1$d" %2$s>%3$s</option>',
								esc_attr( $t ),
								selected( $thr, $t, false ),
								esc_html( sprintf( /* translators: %d stars */ __( '%d stars or more', 'leadmagnet' ), $t ) )
							);
						}
						?>
					</select>
					<p class="description"><?php esc_html_e( 'This rating and above sends the customer to your public review service (e.g. Google Business or Trustpilot). Lower ratings ask for a reason internally. Example: with a threshold of 4, ratings 1–3 are handled internally and 4–5 are redirected.', 'leadmagnet' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="business_review_url"><?php esc_html_e( 'Public review URL', 'leadmagnet' ); ?></label></th>
				<td>
					<input name="lmf93[business_review_url]" id="business_review_url" type="url" value="<?php echo esc_attr( $get( 'business_review_url' ) ); ?>" class="regular-text">
					<p class="description"><?php esc_html_e( 'Where satisfied customers (ratings above the threshold) are sent. E.g. your Google Business review link, Trustpilot page or other review site.', 'leadmagnet' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Low-rating notification', 'leadmagnet' ); ?></th>
				<td><label><input type="checkbox" name="lmf93[enable_low_review_notify]" value="1" <?php checked( $get( 'enable_low_review_notify', 1 ), 1 ); ?>> <?php esc_html_e( 'Email me when a customer leaves a low rating with a reason', 'leadmagnet' ); ?></label></td>
			</tr>
			<tr>
				<th><label for="review_notify_email"><?php esc_html_e( 'Low-rating notification email', 'leadmagnet' ); ?></label></th>
				<td>
					<input name="lmf93[review_notify_email]" id="review_notify_email" type="email" value="<?php echo esc_attr( $get( 'review_notify_email' ) ); ?>" class="regular-text">
					<p class="description"><?php esc_html_e( 'When a customer gives a rating below the threshold and states a reason, it is emailed here so you can reach out. If empty, the admin email is used.', 'leadmagnet' ); ?></p>
				</td>
			</tr>
		</table>

		<?php submit_button( __( 'Save settings', 'leadmagnet' ) ); ?>
	</form>
</div>
