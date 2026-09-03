<?php
/**
 * Admin view: leads list (CRM).
 *
 * @package LeadMagnet
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.Security.NonceVerification.Recommended
$status   = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
$search   = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
$paged    = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
$form_id  = isset( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : 0;
// phpcs:enable

$per_page = 25;
$result   = LMF93_Leads::query(
	array(
		'status'   => $status,
		'search'   => $search,
		'paged'    => $paged,
		'form_id'  => $form_id,
		'per_page' => $per_page,
	)
);

$items = $result['items'];
$total = $result['total'];
$pages = (int) ceil( $total / $per_page );

$statuses = array(
	''          => __( 'All statuses', 'leadmagnet' ),
	'new'       => __( 'New', 'leadmagnet' ),
	'assigned'  => __( 'Assigned', 'leadmagnet' ),
	'contacted' => __( 'Contacted', 'leadmagnet' ),
	'won'       => __( 'Won', 'leadmagnet' ),
	'lost'      => __( 'Lost', 'leadmagnet' ),
);
?>
<div class="wrap lmf93-admin">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Leads', 'leadmagnet' ); ?></h1>
	<hr class="wp-header-end">

	<?php LMF93_Admin::print_notice(); ?>

	<form method="get" class="lmf93-filters">
		<input type="hidden" name="page" value="lmf93-leads">
		<select name="status">
			<?php foreach ( $statuses as $key => $label ) : ?>
				<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $status, $key ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
		<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search name, email, phone…', 'leadmagnet' ); ?>">
		<button class="button"><?php esc_html_e( 'Filter', 'leadmagnet' ); ?></button>
	</form>

	<p class="lmf93-count">
		<?php
		/* translators: %d: number of leads */
		printf( esc_html__( '%d leads', 'leadmagnet' ), (int) $total );
		?>
	</p>

	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Reference', 'leadmagnet' ); ?></th>
				<th><?php esc_html_e( 'Name', 'leadmagnet' ); ?></th>
				<th><?php esc_html_e( 'Contact', 'leadmagnet' ); ?></th>
				<th><?php esc_html_e( 'Area', 'leadmagnet' ); ?></th>
				<th><?php esc_html_e( 'Value', 'leadmagnet' ); ?></th>
				<th><?php esc_html_e( 'Status', 'leadmagnet' ); ?></th>
				<th><?php esc_html_e( 'Partner', 'leadmagnet' ); ?></th>
				<th><?php esc_html_e( 'Received', 'leadmagnet' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $items ) ) : ?>
				<tr><td colspan="8"><?php esc_html_e( 'No leads.', 'leadmagnet' ); ?></td></tr>
			<?php else : ?>
				<?php foreach ( $items as $lead ) : ?>
					<?php
					$view_url = add_query_arg(
						array(
							'page' => 'lmf93-leads',
							'lead' => (int) $lead->id,
						),
						admin_url( 'admin.php' )
					);
					$partner  = $lead->partner_id ? LMF93_Routing::get_partner( $lead->partner_id ) : null;
					$name     = trim( $lead->first_name . ' ' . $lead->last_name );
					?>
					<tr>
						<td><a href="<?php echo esc_url( $view_url ); ?>"><strong><?php echo esc_html( LMF93_Helpers::lead_reference( $lead->id ) ); ?></strong></a></td>
						<td><a href="<?php echo esc_url( $view_url ); ?>"><?php echo esc_html( $name ? $name : '—' ); ?></a></td>
						<td>
							<?php if ( $lead->email ) : ?>
								<div><?php echo esc_html( $lead->email ); ?></div>
							<?php endif; ?>
							<?php if ( $lead->phone ) : ?>
								<div><?php echo esc_html( $lead->phone ); ?></div>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( trim( $lead->postal_code . ' ' . $lead->city ) ); ?></td>
						<td><?php echo $lead->lead_value > 0 ? esc_html( LMF93_Helpers::format_price( $lead->lead_value ) ) : '—'; ?></td>
						<td><span class="lmf93-badge lmf93-badge-<?php echo esc_attr( $lead->status ); ?>"><?php echo esc_html( $lead->status ); ?></span></td>
						<td><?php echo $partner ? esc_html( $partner->company_name ) : '—'; ?></td>
						<td><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' H:i', $lead->created_at ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>

	<?php if ( $pages > 1 ) : ?>
		<div class="tablenav">
			<div class="tablenav-pages">
				<?php
				$base = add_query_arg(
					array(
						'page'   => 'lmf93-leads',
						'status' => $status,
						's'      => $search,
						'paged'  => '%#%',
					),
					admin_url( 'admin.php' )
				);
				echo wp_kses_post(
					paginate_links(
						array(
							'base'      => $base,
							'format'    => '',
							'current'   => $paged,
							'total'     => $pages,
							'prev_text' => '‹',
							'next_text' => '›',
						)
					)
				);
				?>
			</div>
		</div>
	<?php endif; ?>
</div>
