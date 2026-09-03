<?php
/**
 * Admin view: email templates and follow-up messages.
 *
 * @package LeadMagnet
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$defaults  = LMF93_Email::default_templates();
$overrides = get_option( 'lmf93_email_templates', array() );
$followups = get_option( 'lmf93_followups', array() );
if ( ! is_array( $followups ) ) {
	$followups = array();
}

$tpl = function ( $key, $field ) use ( $defaults, $overrides ) {
	if ( isset( $overrides[ $key ][ $field ] ) ) {
		return $overrides[ $key ][ $field ];
	}
	return isset( $defaults[ $key ][ $field ] ) ? $defaults[ $key ][ $field ] : '';
};
$tpl_enabled = function ( $key ) use ( $defaults, $overrides ) {
	if ( isset( $overrides[ $key ]['enabled'] ) ) {
		return (int) $overrides[ $key ]['enabled'];
	}
	return isset( $defaults[ $key ]['enabled'] ) ? (int) $defaults[ $key ]['enabled'] : 1;
};

$placeholders = '{reference} {first_name} {last_name} {email} {phone} {postal_code} {city} {message} {partner_company} {partner_email} {partner_phone} {created_at} {site_name}';
?>
<div class="wrap lmf93-admin">
	<h1><?php esc_html_e( 'Emails', 'leadmagnet' ); ?></h1>

	<?php LMF93_Admin::print_notice(); ?>

	<p class="description">
		<?php esc_html_e( 'Available placeholders (you can use these in the subject or body):', 'leadmagnet' ); ?><br>
		<code><?php echo esc_html( $placeholders ); ?></code><br>
		<?php esc_html_e( 'Any form field is also available as {field_key} and, for choice fields, {field_key_label}.', 'leadmagnet' ); ?>
	</p>

	<div style="background:#f1f8f5;border:1px solid #cfe8dd;border-radius:10px;padding:16px 18px;margin:16px 0 24px;max-width:720px;">
		<h2 style="margin-top:0;"><?php esc_html_e( 'Send a test message', 'leadmagnet' ); ?></h2>
		<p class="description" style="margin-bottom:12px;">
			<?php esc_html_e( 'Send the selected message to the admin email using example data (a fake customer, Jane Doe), so you can see how it looks. The subject is prefixed with [TEST].', 'leadmagnet' ); ?>
		</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
			<?php wp_nonce_field( 'lmf93_send_test_email' ); ?>
			<input type="hidden" name="lmf93_action" value="send_test_email">
			<select name="test_type">
				<optgroup label="<?php esc_attr_e( 'Follow-ups and reviews', 'leadmagnet' ); ?>">
					<option value="review_request"><?php esc_html_e( 'Review request', 'leadmagnet' ); ?></option>
					<option value="service_reminder"><?php esc_html_e( 'Service reminder', 'leadmagnet' ); ?></option>
				</optgroup>
				<optgroup label="<?php esc_attr_e( 'Transactional messages', 'leadmagnet' ); ?>">
					<option value="partner_new_lead"><?php esc_html_e( 'Lead to partner', 'leadmagnet' ); ?></option>
					<option value="customer_confirmation"><?php esc_html_e( 'Customer confirmation (partner found)', 'leadmagnet' ); ?></option>
					<option value="customer_no_partner"><?php esc_html_e( 'Customer confirmation (searching for partner)', 'leadmagnet' ); ?></option>
				</optgroup>
				<?php
				$custom_msgs = LMF93_Followup::messages();
				if ( ! empty( $custom_msgs ) ) :
					?>
					<optgroup label="<?php esc_attr_e( 'Your follow-up messages', 'leadmagnet' ); ?>">
						<?php foreach ( $custom_msgs as $m ) : ?>
							<?php if ( ! empty( $m['id'] ) ) : ?>
								<option value="custom:<?php echo esc_attr( $m['id'] ); ?>">
									<?php echo esc_html( ! empty( $m['subject'] ) ? $m['subject'] : $m['id'] ); ?>
								</option>
							<?php endif; ?>
						<?php endforeach; ?>
					</optgroup>
				<?php endif; ?>
			</select>
			<?php submit_button( __( 'Send test to admin email', 'leadmagnet' ), 'secondary', '', false ); ?>
		</form>
	</div>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
		<?php wp_nonce_field( 'lmf93_save_emails' ); ?>
		<input type="hidden" name="lmf93_action" value="save_emails">

		<hr>
		<h2><?php esc_html_e( 'Lead to partner (full customer details)', 'leadmagnet' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Sent to the partner company for the area. It contains the full customer details so the partner can make contact directly. Reply-To is the customer. If you are not using partner routing, you can disable this message.', 'leadmagnet' ); ?></p>
		<table class="form-table">
			<tr>
				<th><?php esc_html_e( 'Enabled', 'leadmagnet' ); ?></th>
				<td><label><input type="checkbox" name="lmf93[tpl][partner_new_lead][enabled]" value="1" <?php checked( $tpl_enabled( 'partner_new_lead' ), 1 ); ?>> <?php esc_html_e( 'Send this message', 'leadmagnet' ); ?></label></td>
			</tr>
			<tr>
				<th><label><?php esc_html_e( 'Subject', 'leadmagnet' ); ?></label></th>
				<td><input type="text" class="large-text" name="lmf93[tpl][partner_new_lead][subject]" value="<?php echo esc_attr( $tpl( 'partner_new_lead', 'subject' ) ); ?>"></td>
			</tr>
			<tr>
				<th><label><?php esc_html_e( 'Message', 'leadmagnet' ); ?></label></th>
				<td><textarea class="large-text" rows="14" name="lmf93[tpl][partner_new_lead][body]"><?php echo esc_textarea( $tpl( 'partner_new_lead', 'body' ) ); ?></textarea></td>
			</tr>
		</table>

		<hr>
		<h2><?php esc_html_e( 'Customer: confirmation when a partner was found', 'leadmagnet' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Sent to the person who submitted the form when the lead was routed to a partner.', 'leadmagnet' ); ?></p>
		<table class="form-table">
			<tr>
				<th><?php esc_html_e( 'Enabled', 'leadmagnet' ); ?></th>
				<td><label><input type="checkbox" name="lmf93[tpl][customer_confirmation][enabled]" value="1" <?php checked( $tpl_enabled( 'customer_confirmation' ), 1 ); ?>> <?php esc_html_e( 'Send this message', 'leadmagnet' ); ?></label></td>
			</tr>
			<tr>
				<th><label><?php esc_html_e( 'Subject', 'leadmagnet' ); ?></label></th>
				<td><input type="text" class="large-text" name="lmf93[tpl][customer_confirmation][subject]" value="<?php echo esc_attr( $tpl( 'customer_confirmation', 'subject' ) ); ?>"></td>
			</tr>
			<tr>
				<th><label><?php esc_html_e( 'Message', 'leadmagnet' ); ?></label></th>
				<td><textarea class="large-text" rows="12" name="lmf93[tpl][customer_confirmation][body]"><?php echo esc_textarea( $tpl( 'customer_confirmation', 'body' ) ); ?></textarea></td>
			</tr>
		</table>

		<hr>
		<h2><?php esc_html_e( 'Customer: received, searching for a partner', 'leadmagnet' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Sent if no partner was found for the area yet. The customer still gets a confirmation so their request does not feel lost. If you are not using partner routing, this is your default confirmation message.', 'leadmagnet' ); ?></p>
		<table class="form-table">
			<tr>
				<th><?php esc_html_e( 'Enabled', 'leadmagnet' ); ?></th>
				<td><label><input type="checkbox" name="lmf93[tpl][customer_no_partner][enabled]" value="1" <?php checked( $tpl_enabled( 'customer_no_partner' ), 1 ); ?>> <?php esc_html_e( 'Send this message', 'leadmagnet' ); ?></label></td>
			</tr>
			<tr>
				<th><label><?php esc_html_e( 'Subject', 'leadmagnet' ); ?></label></th>
				<td><input type="text" class="large-text" name="lmf93[tpl][customer_no_partner][subject]" value="<?php echo esc_attr( $tpl( 'customer_no_partner', 'subject' ) ); ?>"></td>
			</tr>
			<tr>
				<th><label><?php esc_html_e( 'Message', 'leadmagnet' ); ?></label></th>
				<td><textarea class="large-text" rows="10" name="lmf93[tpl][customer_no_partner][body]"><?php echo esc_textarea( $tpl( 'customer_no_partner', 'body' ) ); ?></textarea></td>
			</tr>
		</table>

		<hr>
		<h2><?php esc_html_e( 'Built-in follow-up messages', 'leadmagnet' ); ?></h2>
		<p class="description" style="max-width:720px;">
			<?php esc_html_e( 'These two messages are built-in features. You can edit their text and delay, and turn them on or off. They are off by default. The review request uses the {review_url} placeholder. Both are sent when a lead\'s job status is set to "completed".', 'leadmagnet' ); ?>
		</p>

		<?php
		$fixed_defs = array(
			'review_request'   => __( 'Review request', 'leadmagnet' ),
			'service_reminder' => __( 'Service reminder', 'leadmagnet' ),
		);
		foreach ( $fixed_defs as $fkey => $flabel ) :
			$fm = LMF93_Followup::fixed_message( $fkey );
			?>
			<div style="border:1px solid #cfe8dd;border-radius:8px;padding:14px 18px;margin-bottom:16px;background:#f7fcfa;">
				<h3 style="margin-top:4px;"><?php echo esc_html( $flabel ); ?></h3>
				<p>
					<label><input type="checkbox" name="lmf93[fixed][<?php echo esc_attr( $fkey ); ?>][enabled]" value="1" <?php checked( ! empty( $fm['enabled'] ), true ); ?>> <?php esc_html_e( 'Enabled', 'leadmagnet' ); ?></label>
					&nbsp;&nbsp;
					<label><?php esc_html_e( 'Delay after job completion (days):', 'leadmagnet' ); ?>
						<input type="number" min="0" class="small-text" name="lmf93[fixed][<?php echo esc_attr( $fkey ); ?>][delay_days]" value="<?php echo esc_attr( (int) $fm['delay_days'] ); ?>">
					</label>
				</p>
				<p><input type="text" class="large-text" placeholder="<?php esc_attr_e( 'Subject', 'leadmagnet' ); ?>" name="lmf93[fixed][<?php echo esc_attr( $fkey ); ?>][subject]" value="<?php echo esc_attr( $fm['subject'] ); ?>"></p>
				<p><textarea class="large-text" rows="6" name="lmf93[fixed][<?php echo esc_attr( $fkey ); ?>][body]"><?php echo esc_textarea( $fm['body'] ); ?></textarea></p>
				<?php if ( 'review_request' === $fkey ) : ?>
					<p class="description"><?php esc_html_e( 'Tip: include {review_url} in the message – it is the customer\'s personal link to the review page. If you leave it out, the link is appended automatically.', 'leadmagnet' ); ?></p>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>

		<hr>
		<h2><?php esc_html_e( 'Scheduled follow-up messages (add / remove)', 'leadmagnet' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Define any messages you like, sent to the customer after a set time. Trigger: form submitted, lead routed to a partner, marked as won, or job completed. Delay is in days. Remove a message by clearing its subject and body, or press Remove.', 'leadmagnet' ); ?>
		</p>

		<div id="lmf93-followups">
			<?php
			if ( empty( $followups ) ) {
				// Render one empty starter row.
				$followups = array( array( 'enabled' => 1, 'trigger' => 'job_completed', 'delay_days' => 30, 'subject' => '', 'body' => '' ) );
			}
			$idx = 0;
			foreach ( $followups as $fu ) :
				$idx++;
				?>
				<div class="lmf93-fu-row" style="border:1px solid #dcdcde;padding:12px 16px;margin-bottom:12px;background:#fff;">
					<p>
						<label><input type="checkbox" name="lmf93[followup][<?php echo (int) $idx; ?>][enabled]" value="1" <?php checked( ! empty( $fu['enabled'] ), true ); ?>> <?php esc_html_e( 'Enabled', 'leadmagnet' ); ?></label>
						&nbsp;&nbsp;
						<label><?php esc_html_e( 'Trigger:', 'leadmagnet' ); ?>
							<select name="lmf93[followup][<?php echo (int) $idx; ?>][trigger]">
								<option value="lead_created" <?php selected( isset( $fu['trigger'] ) ? $fu['trigger'] : '', 'lead_created' ); ?>><?php esc_html_e( 'Form submitted', 'leadmagnet' ); ?></option>
								<option value="lead_assigned" <?php selected( isset( $fu['trigger'] ) ? $fu['trigger'] : '', 'lead_assigned' ); ?>><?php esc_html_e( 'Routed to partner', 'leadmagnet' ); ?></option>
								<option value="status_won" <?php selected( isset( $fu['trigger'] ) ? $fu['trigger'] : '', 'status_won' ); ?>><?php esc_html_e( 'Marked as won', 'leadmagnet' ); ?></option>
								<option value="job_completed" <?php selected( isset( $fu['trigger'] ) ? $fu['trigger'] : '', 'job_completed' ); ?>><?php esc_html_e( 'Job completed', 'leadmagnet' ); ?></option>
							</select>
						</label>
						&nbsp;&nbsp;
						<label><?php esc_html_e( 'Delay (days):', 'leadmagnet' ); ?>
							<input type="number" min="0" class="small-text" name="lmf93[followup][<?php echo (int) $idx; ?>][delay_days]" value="<?php echo esc_attr( isset( $fu['delay_days'] ) ? (int) $fu['delay_days'] : 0 ); ?>">
						</label>
						&nbsp;&nbsp;
						<button type="button" class="button lmf93-fu-remove"><?php esc_html_e( 'Remove', 'leadmagnet' ); ?></button>
					</p>
					<p><input type="text" class="large-text" placeholder="<?php esc_attr_e( 'Subject', 'leadmagnet' ); ?>" name="lmf93[followup][<?php echo (int) $idx; ?>][subject]" value="<?php echo esc_attr( isset( $fu['subject'] ) ? $fu['subject'] : '' ); ?>"></p>
					<p><textarea class="large-text" rows="6" placeholder="<?php esc_attr_e( 'Message', 'leadmagnet' ); ?>" name="lmf93[followup][<?php echo (int) $idx; ?>][body]"><?php echo esc_textarea( isset( $fu['body'] ) ? $fu['body'] : '' ); ?></textarea></p>
				</div>
			<?php endforeach; ?>
		</div>

		<p><button type="button" class="button" id="lmf93-fu-add"><?php esc_html_e( '+ Add follow-up message', 'leadmagnet' ); ?></button></p>

		<?php submit_button( __( 'Save emails', 'leadmagnet' ) ); ?>
	</form>
</div>

<script>
( function () {
	var wrap = document.getElementById( 'lmf93-followups' );
	var addBtn = document.getElementById( 'lmf93-fu-add' );
	var counter = <?php echo (int) $idx; ?>;

	function bindRemove( row ) {
		var btn = row.querySelector( '.lmf93-fu-remove' );
		if ( btn ) {
			btn.addEventListener( 'click', function () {
				row.parentNode.removeChild( row );
			} );
		}
	}

	Array.prototype.forEach.call( wrap.querySelectorAll( '.lmf93-fu-row' ), bindRemove );

	addBtn.addEventListener( 'click', function () {
		counter++;
		var i = counter;
		var div = document.createElement( 'div' );
		div.className = 'lmf93-fu-row';
		div.style.cssText = 'border:1px solid #dcdcde;padding:12px 16px;margin-bottom:12px;background:#fff;';
		div.innerHTML =
			'<p>' +
			'<label><input type="checkbox" name="lmf93[followup][' + i + '][enabled]" value="1" checked> <?php echo esc_js( __( 'Enabled', 'leadmagnet' ) ); ?></label>&nbsp;&nbsp;' +
			'<label><?php echo esc_js( __( 'Trigger:', 'leadmagnet' ) ); ?> <select name="lmf93[followup][' + i + '][trigger]">' +
			'<option value="lead_created"><?php echo esc_js( __( 'Form submitted', 'leadmagnet' ) ); ?></option>' +
			'<option value="lead_assigned"><?php echo esc_js( __( 'Routed to partner', 'leadmagnet' ) ); ?></option>' +
			'<option value="status_won"><?php echo esc_js( __( 'Marked as won', 'leadmagnet' ) ); ?></option>' +
			'<option value="job_completed"><?php echo esc_js( __( 'Job completed', 'leadmagnet' ) ); ?></option>' +
			'</select></label>&nbsp;&nbsp;' +
			'<label><?php echo esc_js( __( 'Delay (days):', 'leadmagnet' ) ); ?> <input type="number" min="0" class="small-text" name="lmf93[followup][' + i + '][delay_days]" value="0"></label>&nbsp;&nbsp;' +
			'<button type="button" class="button lmf93-fu-remove"><?php echo esc_js( __( 'Remove', 'leadmagnet' ) ); ?></button>' +
			'</p>' +
			'<p><input type="text" class="large-text" placeholder="<?php echo esc_js( __( 'Subject', 'leadmagnet' ) ); ?>" name="lmf93[followup][' + i + '][subject]" value=""></p>' +
			'<p><textarea class="large-text" rows="6" placeholder="<?php echo esc_js( __( 'Message', 'leadmagnet' ) ); ?>" name="lmf93[followup][' + i + '][body]"></textarea></p>';
		wrap.appendChild( div );
		bindRemove( div );
	} );
} )();
</script>
