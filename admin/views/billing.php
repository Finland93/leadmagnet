<?php
/**
 * Admin view: billing (per-partner billable leads).
 *
 * Shows leads that are assigned to a partner, not cancelled/refunded and not
 * yet marked billed. You can filter by partner and date range, see the value
 * per lead and the total per partner, and mark leads as billed once invoiced.
 *
 * @package LeadMagnet
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$partners = LMF93_Routing::all_partners();

// Filters (read-only display, no state change → nonce not required to view).
// phpcs:disable WordPress.Security.NonceVerification.Recommended
$f_partner = isset( $_GET['partner'] ) ? absint( $_GET['partner'] ) : 0;
$f_from    = isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '';
$f_to      = isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '';
$show_all  = ! empty( $_GET['include_billed'] );
// phpcs:enable WordPress.Security.NonceVerification.Recommended

$rows = LMF93_Leads::billable(
	array(
		'partner_id'     => $f_partner,
		'date_from'      => preg_match( '/^\d{4}-\d{2}-\d{2}$/', $f_from ) ? $f_from : '',
		'date_to'        => preg_match( '/^\d{4}-\d{2}-\d{2}$/', $f_to ) ? $f_to : '',
		'include_billed' => $show_all,
	)
);

// Group by partner.
$groups        = array();
$partner_names = array();
foreach ( $partners as $p ) {
	$partner_names[ (int) $p->id ] = $p->company_name;
}
foreach ( $rows as $r ) {
	$pid = (int) $r->partner_id;
	if ( ! isset( $groups[ $pid ] ) ) {
		$groups[ $pid ] = array();
	}
	$groups[ $pid ][] = $r;
}

$grand_total = 0.0;
$grand_count = 0;
?>
<div class="wrap lmf93-admin">
	<h1><?php esc_html_e( 'Billing', 'leadmagnet' ); ?></h1>
	<p class="description" style="max-width:720px;">
		<?php esc_html_e( 'Here you see the billable leads per partner. Only leads routed to a partner that are not cancelled or refunded are included. Mark leads as billed once you have sent the invoice – they leave this view.', 'leadmagnet' ); ?>
	</p>

	<?php LMF93_Admin::print_notice(); ?>

	<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="lmf93-billing-filters" style="margin:16px 0;">
		<input type="hidden" name="page" value="lmf93-billing">
		<label style="margin-right:12px;">
			<?php esc_html_e( 'Partner', 'leadmagnet' ); ?>
			<select name="partner">
				<option value="0"><?php esc_html_e( 'All partners', 'leadmagnet' ); ?></option>
				<?php foreach ( $partners as $p ) : ?>
					<option value="<?php echo esc_attr( $p->id ); ?>" <?php selected( $f_partner, (int) $p->id ); ?>><?php echo esc_html( $p->company_name ); ?></option>
				<?php endforeach; ?>
			</select>
		</label>
		<label style="margin-right:12px;"><?php esc_html_e( 'From', 'leadmagnet' ); ?> <input type="date" name="date_from" value="<?php echo esc_attr( $f_from ); ?>"></label>
		<label style="margin-right:12px;"><?php esc_html_e( 'To', 'leadmagnet' ); ?> <input type="date" name="date_to" value="<?php echo esc_attr( $f_to ); ?>"></label>
		<label style="margin-right:12px;"><input type="checkbox" name="include_billed" value="1" <?php checked( $show_all ); ?>> <?php esc_html_e( 'Also show already billed', 'leadmagnet' ); ?></label>
		<?php submit_button( __( 'Filter', 'leadmagnet' ), 'secondary', '', false ); ?>
	</form>

	<?php if ( empty( $groups ) ) : ?>
		<p><?php esc_html_e( 'No billable leads for the selected filters.', 'leadmagnet' ); ?></p>
	<?php else : ?>
		<?php foreach ( $groups as $pid => $leads ) : ?>
			<?php
			$p_total = 0.0;
			foreach ( $leads as $l ) {
				$p_total += (float) $l->lead_value;
			}
			$grand_total += $p_total;
			$grand_count += count( $leads );
			$p_name       = isset( $partner_names[ $pid ] ) ? $partner_names[ $pid ] : sprintf( __( 'Partner #%d', 'leadmagnet' ), $pid );
			?>
			<h2 style="margin-top:28px;"><?php echo esc_html( $p_name ); ?>
				<span style="font-weight:400;color:#646970;">
					— <?php echo esc_html( sprintf( _n( '%d lead', '%d leads', count( $leads ), 'leadmagnet' ), count( $leads ) ) ); ?>,
					<?php echo esc_html( LMF93_Helpers::format_price( $p_total ) ); ?><?php echo LMF93_Helpers::price_note() ? ' (' . esc_html( LMF93_Helpers::price_note() ) . ')' : ''; ?>
				</span>
			</h2>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
				<?php wp_nonce_field( 'lmf93_mark_billed' ); ?>
				<input type="hidden" name="lmf93_action" value="mark_billed">
				<input type="hidden" name="partner" value="<?php echo esc_attr( $pid ); ?>">

				<table class="widefat striped">
					<thead>
						<tr>
							<td class="check-column"><input type="checkbox" onclick="jQuery(this).closest('table').find('input[name=\'lead_ids[]\']').prop('checked', this.checked);"></td>
							<th><?php esc_html_e( 'Reference', 'leadmagnet' ); ?></th>
							<th><?php esc_html_e( 'Received', 'leadmagnet' ); ?></th>
							<th><?php esc_html_e( 'Customer', 'leadmagnet' ); ?></th>
							<th><?php esc_html_e( 'Postal', 'leadmagnet' ); ?></th>
							<th><?php esc_html_e( 'Job status', 'leadmagnet' ); ?></th>
							<th style="text-align:right;"><?php esc_html_e( 'Value', 'leadmagnet' ); ?></th>
							<th><?php esc_html_e( 'Billed', 'leadmagnet' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $leads as $l ) : ?>
							<tr>
								<th class="check-column">
									<?php if ( ! (int) $l->billed ) : ?>
										<input type="checkbox" name="lead_ids[]" value="<?php echo esc_attr( $l->id ); ?>">
									<?php endif; ?>
								</th>
								<td>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=lmf93-leads&lead=' . (int) $l->id ) ); ?>">
										<?php echo esc_html( LMF93_Helpers::lead_reference( (int) $l->id ) ); ?>
									</a>
								</td>
								<td><?php echo esc_html( mysql2date( get_option( 'date_format' ), $l->created_at ) ); ?></td>
								<td><?php echo esc_html( trim( $l->first_name . ' ' . $l->last_name ) ); ?></td>
								<td><?php echo esc_html( $l->postal_code ); ?></td>
								<td><?php echo esc_html( $l->job_status ); ?></td>
								<td style="text-align:right;"><?php echo esc_html( LMF93_Helpers::format_price( (float) $l->lead_value ) ); ?></td>
								<td><?php echo (int) $l->billed ? '✓ ' . esc_html( mysql2date( get_option( 'date_format' ), $l->billed_at ) ) : '—'; ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<p style="margin:10px 0 4px;">
					<?php submit_button( __( 'Mark selected as billed', 'leadmagnet' ), 'primary', '', false ); ?>
				</p>
			</form>
		<?php endforeach; ?>

		<hr style="margin:28px 0 12px;">
		<p style="font-size:15px;">
			<strong><?php esc_html_e( 'Total:', 'leadmagnet' ); ?></strong>
			<?php echo esc_html( sprintf( _n( '%d lead', '%d leads', $grand_count, 'leadmagnet' ), $grand_count ) ); ?>,
			<?php echo esc_html( LMF93_Helpers::format_price( $grand_total ) ); ?><?php echo LMF93_Helpers::price_note() ? ' (' . esc_html( LMF93_Helpers::price_note() ) . ')' : ''; ?>
		</p>
	<?php endif; ?>
</div>
