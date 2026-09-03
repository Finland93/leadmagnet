<?php
/**
 * Admin view: single lead.
 *
 * @package LeadMagnet
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$lead_id = isset( $_GET['lead'] ) ? absint( $_GET['lead'] ) : 0;
$lead    = LMF93_Leads::get( $lead_id );

if ( ! $lead ) {
	echo '<div class="wrap"><p>' . esc_html__( 'Lead not found.', 'leadmagnet' ) . '</p></div>';
	return;
}

$payload  = json_decode( $lead->payload, true );
$events   = LMF93_Leads::get_events( $lead_id );
$partners = LMF93_Routing::all_partners();
$consents = LMF93_Consent::get_for_lead( $lead_id );
$back_url = admin_url( 'admin.php?page=lmf93-leads' );

$job_statuses = array( 'new', 'scheduled', 'in_progress', 'completed', 'cancelled' );
$lead_statuses = array( 'new', 'assigned', 'contacted', 'won', 'lost' );
?>
<div class="wrap lmf93-admin lmf93-lead-single">
	<h1 class="wp-heading-inline">
		<?php echo esc_html( LMF93_Helpers::lead_reference( $lead->id ) ); ?>
	</h1>
	<a href="<?php echo esc_url( $back_url ); ?>" class="page-title-action"><?php esc_html_e( '← Back to leads', 'leadmagnet' ); ?></a>
	<hr class="wp-header-end">

	<?php LMF93_Admin::print_notice(); ?>

	<div class="lmf93-cols">
		<div class="lmf93-col-main">
			<div class="lmf93-card">
				<h2><?php esc_html_e( 'Customer', 'leadmagnet' ); ?></h2>
				<table class="form-table">
					<tr><th><?php esc_html_e( 'Name', 'leadmagnet' ); ?></th><td><?php echo esc_html( trim( $lead->first_name . ' ' . $lead->last_name ) ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Email', 'leadmagnet' ); ?></th><td><?php echo $lead->email ? '<a href="mailto:' . esc_attr( $lead->email ) . '">' . esc_html( $lead->email ) . '</a>' : '—'; ?></td></tr>
					<tr><th><?php esc_html_e( 'Phone', 'leadmagnet' ); ?></th><td><?php echo $lead->phone ? '<a href="tel:' . esc_attr( $lead->phone ) . '">' . esc_html( $lead->phone ) . '</a>' : '—'; ?></td></tr>
					<tr><th><?php esc_html_e( 'Area', 'leadmagnet' ); ?></th><td><?php echo esc_html( trim( $lead->postal_code . ' ' . $lead->city ) ); ?></td></tr>
				</table>
			</div>

			<?php if ( ! empty( $payload ) ) : ?>
				<div class="lmf93-card">
					<h2><?php esc_html_e( 'Submitted data', 'leadmagnet' ); ?></h2>
					<table class="form-table">
						<?php foreach ( $payload as $key => $value ) : ?>
							<tr>
								<th><?php echo esc_html( $key ); ?></th>
								<td><?php echo esc_html( is_array( $value ) ? implode( ', ', $value ) : $value ); ?></td>
							</tr>
						<?php endforeach; ?>
					</table>
				</div>
			<?php endif; ?>

			<div class="lmf93-card">
				<h2><?php esc_html_e( 'Timeline', 'leadmagnet' ); ?></h2>
				<ul class="lmf93-timeline">
					<?php foreach ( $events as $ev ) : ?>
						<li>
							<span class="lmf93-timeline-time"><?php echo esc_html( mysql2date( 'Y-m-d H:i', $ev->created_at ) ); ?></span>
							<span class="lmf93-timeline-msg"><?php echo esc_html( $ev->message ? $ev->message : $ev->event_type ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		</div>

		<div class="lmf93-col-side">
			<div class="lmf93-card">
				<h2><?php esc_html_e( 'Management', 'leadmagnet' ); ?></h2>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
					<?php wp_nonce_field( 'lmf93_update_lead' ); ?>
					<input type="hidden" name="lmf93_action" value="update_lead">
					<input type="hidden" name="lead_id" value="<?php echo (int) $lead->id; ?>">

					<p>
						<label for="lmf93_status"><strong><?php esc_html_e( 'Status', 'leadmagnet' ); ?></strong></label><br>
						<select name="status" id="lmf93_status">
							<?php foreach ( $lead_statuses as $s ) : ?>
								<option value="<?php echo esc_attr( $s ); ?>" <?php selected( $lead->status, $s ); ?>><?php echo esc_html( $s ); ?></option>
							<?php endforeach; ?>
						</select>
					</p>

					<p>
						<label for="lmf93_job_status"><strong><?php esc_html_e( 'Job status', 'leadmagnet' ); ?></strong></label><br>
						<select name="job_status" id="lmf93_job_status">
							<?php foreach ( $job_statuses as $s ) : ?>
								<option value="<?php echo esc_attr( $s ); ?>" <?php selected( $lead->job_status, $s ); ?>><?php echo esc_html( $s ); ?></option>
							<?php endforeach; ?>
						</select>
						<span class="description"><?php esc_html_e( 'Marking as completed schedules the follow-up messages (if enabled).', 'leadmagnet' ); ?></span>
					</p>

					<p>
						<label for="lmf93_partner"><strong><?php esc_html_e( 'Partner', 'leadmagnet' ); ?></strong></label><br>
						<select name="partner_id" id="lmf93_partner">
							<option value=""><?php esc_html_e( '— None —', 'leadmagnet' ); ?></option>
							<?php foreach ( $partners as $p ) : ?>
								<option value="<?php echo (int) $p->id; ?>" <?php selected( $lead->partner_id, $p->id ); ?>><?php echo esc_html( $p->company_name ); ?></option>
							<?php endforeach; ?>
						</select>
					</p>

					<button class="button button-primary"><?php esc_html_e( 'Save', 'leadmagnet' ); ?></button>
				</form>
			</div>

			<div class="lmf93-card">
				<h2><?php esc_html_e( 'Metadata', 'leadmagnet' ); ?></h2>
				<p><strong><?php esc_html_e( 'Score', 'leadmagnet' ); ?>:</strong> <?php echo (int) $lead->lead_score; ?></p>
				<p><strong><?php esc_html_e( 'Value', 'leadmagnet' ); ?>:</strong> <?php echo esc_html( LMF93_Helpers::format_price( $lead->lead_value ) ); ?></p>
				<p><strong><?php esc_html_e( 'Source', 'leadmagnet' ); ?>:</strong> <?php echo esc_html( $lead->utm_source ? $lead->utm_source : '—' ); ?></p>
				<?php if ( $lead->utm_campaign ) : ?>
					<p><strong><?php esc_html_e( 'Campaign', 'leadmagnet' ); ?>:</strong> <?php echo esc_html( $lead->utm_campaign ); ?></p>
				<?php endif; ?>
			</div>

			<div class="lmf93-card">
				<h2><?php esc_html_e( 'Consents', 'leadmagnet' ); ?></h2>
				<ul>
					<?php foreach ( $consents as $c ) : ?>
						<li>
							<?php echo esc_html( $c->purpose ); ?>:
							<?php echo $c->granted ? '✓' : '✗'; ?>
							<span class="description">(v<?php echo esc_html( $c->privacy_version ); ?>, <?php echo esc_html( mysql2date( 'Y-m-d', $c->created_at ) ); ?>)</span>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>

			<div class="lmf93-card lmf93-card-danger">
				<h2><?php esc_html_e( 'GDPR', 'leadmagnet' ); ?></h2>
				<p>
					<a class="button" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'lmf93_action' => 'export_lead', 'lead_id' => $lead->id ), admin_url( 'admin.php' ) ), 'lmf93_export_lead' ) ); ?>">
						<?php esc_html_e( 'Export data (JSON)', 'leadmagnet' ); ?>
					</a>
				</p>
				<p>
					<a class="button button-link-delete" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'lmf93_action' => 'delete_lead', 'lead_id' => $lead->id ), admin_url( 'admin.php' ) ), 'lmf93_delete_lead' ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Permanently delete this lead and all related data?', 'leadmagnet' ) ); ?>');">
						<?php esc_html_e( 'Delete permanently', 'leadmagnet' ); ?>
					</a>
				</p>
			</div>
		</div>
	</div>
</div>
