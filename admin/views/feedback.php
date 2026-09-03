<?php
/**
 * Admin view: customer feedback / reviews.
 *
 * Lists star ratings tied to leads. Low ratings show the reason + comment the
 * customer left, so you can reach out and turn it around. You can mark each
 * row's handling status.
 *
 * @package LeadMagnet
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.Security.NonceVerification.Recommended
$only_low = ! empty( $_GET['low'] );
$f_status = isset( $_GET['fbstatus'] ) ? sanitize_key( wp_unslash( $_GET['fbstatus'] ) ) : '';
// phpcs:enable WordPress.Security.NonceVerification.Recommended

$stats = LMF93_Feedback::stats();
$rows  = LMF93_Feedback::query(
	array(
		'rating_max' => $only_low ? 2 : 0,
		'status'     => $f_status,
		'per_page'   => 100,
	)
);

$status_labels = array(
	'new'       => __( 'New', 'leadmagnet' ),
	'contacted' => __( 'Contacted', 'leadmagnet' ),
	'resolved'  => __( 'Resolved', 'leadmagnet' ),
	'ignored'   => __( 'Ignored', 'leadmagnet' ),
);

$reason_labels = array(
	'scheduling'   => __( 'Scheduling or delays', 'leadmagnet' ),
	'pricing'      => __( 'Pricing or billing', 'leadmagnet' ),
	'work_quality' => __( 'Quality of work', 'leadmagnet' ),
	'communication' => __( 'Communication or reachability', 'leadmagnet' ),
	'other'        => __( 'Other reason', 'leadmagnet' ),
);

/**
 * Render N filled stars out of 5 as text.
 *
 * @param int $n Rating.
 * @return string
 */
function lmf93_stars_text( $n ) {
	$n = max( 0, min( 5, (int) $n ) );
	return str_repeat( '★', $n ) . str_repeat( '☆', 5 - $n );
}
?>
<div class="wrap lmf93-admin">
	<h1><?php esc_html_e( 'Feedback', 'leadmagnet' ); ?></h1>

	<?php LMF93_Admin::print_notice(); ?>

	<p style="font-size:15px;margin:10px 0 18px;">
		<?php
		printf(
			/* translators: 1: count, 2: average, 3: low count */
			esc_html__( 'Total ratings: %1$s · average: %2$s / 5 · low (≤2): %3$s', 'leadmagnet' ),
			'<strong>' . esc_html( number_format_i18n( $stats['count'] ) ) . '</strong>',
			'<strong>' . esc_html( number_format_i18n( $stats['avg'], 1 ) ) . '</strong>',
			'<strong>' . esc_html( number_format_i18n( $stats['low'] ) ) . '</strong>'
		);
		?>
	</p>

	<ul class="subsubsub" style="margin-bottom:12px;">
		<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=lmf93-feedback' ) ); ?>" class="<?php echo $only_low ? '' : 'current'; ?>"><?php esc_html_e( 'All', 'leadmagnet' ); ?></a> | </li>
		<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=lmf93-feedback&low=1' ) ); ?>" class="<?php echo $only_low ? 'current' : ''; ?>"><?php esc_html_e( 'Low ratings only (≤2)', 'leadmagnet' ); ?></a></li>
	</ul>

	<?php if ( empty( $rows ) ) : ?>
		<p><?php esc_html_e( 'No feedback yet.', 'leadmagnet' ); ?></p>
	<?php else : ?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Date', 'leadmagnet' ); ?></th>
					<th><?php esc_html_e( 'Rating', 'leadmagnet' ); ?></th>
					<th><?php esc_html_e( 'Customer', 'leadmagnet' ); ?></th>
					<th><?php esc_html_e( 'Reason', 'leadmagnet' ); ?></th>
					<th><?php esc_html_e( 'Comment', 'leadmagnet' ); ?></th>
					<th><?php esc_html_e( 'Status', 'leadmagnet' ); ?></th>
					<th><?php esc_html_e( 'Action', 'leadmagnet' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $rows as $r ) : ?>
					<?php $is_low = (int) $r->rating <= 2; ?>
					<tr<?php echo $is_low ? ' style="background:#fff6f5;"' : ''; ?>>
						<td><?php echo esc_html( mysql2date( 'j.n.Y H:i', $r->created_at ) ); ?></td>
						<td style="font-size:16px;color:#f5b301;letter-spacing:1px;" title="<?php echo esc_attr( $r->rating . '/5' ); ?>"><?php echo esc_html( lmf93_stars_text( $r->rating ) ); ?></td>
						<td>
							<?php if ( $r->lead_id ) : ?>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=lmf93-leads&lead=' . (int) $r->lead_id ) ); ?>">
									<?php echo esc_html( trim( (string) $r->first_name . ' ' . (string) $r->last_name ) ?: __( '(anonymous)', 'leadmagnet' ) ); ?>
								</a><br>
								<span class="description"><?php echo esc_html( $r->phone ? $r->phone : $r->email ); ?></span>
							<?php else : ?>
								<?php esc_html_e( '(lead deleted)', 'leadmagnet' ); ?>
							<?php endif; ?>
						</td>
						<td><?php echo $r->reason ? esc_html( isset( $reason_labels[ $r->reason ] ) ? $reason_labels[ $r->reason ] : $r->reason ) : '—'; ?></td>
						<td><?php echo $r->comment ? esc_html( $r->comment ) : '—'; ?></td>
						<td><?php echo esc_html( isset( $status_labels[ $r->status ] ) ? $status_labels[ $r->status ] : $r->status ); ?></td>
						<td>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" style="display:flex;gap:6px;align-items:center;">
								<?php wp_nonce_field( 'lmf93_update_feedback' ); ?>
								<input type="hidden" name="lmf93_action" value="update_feedback">
								<input type="hidden" name="feedback_id" value="<?php echo esc_attr( $r->id ); ?>">
								<select name="fb_status">
									<?php foreach ( $status_labels as $key => $label ) : ?>
										<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $r->status, $key ); ?>><?php echo esc_html( $label ); ?></option>
									<?php endforeach; ?>
								</select>
								<?php submit_button( __( 'Save', 'leadmagnet' ), 'secondary small', '', false ); ?>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
