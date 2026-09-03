<?php
/**
 * Admin view: partners manager.
 *
 * @package LeadMagnet
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$partners = LMF93_Routing::all_partners();

// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$edit_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
$editing = $edit_id ? LMF93_Routing::get_partner( $edit_id ) : null;
$is_new  = isset( $_GET['new'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$new_url = add_query_arg( array( 'page' => 'lmf93-partners', 'new' => 1 ), admin_url( 'admin.php' ) );

$val = function ( $field, $default = '' ) use ( $editing ) {
	return $editing && isset( $editing->$field ) ? $editing->$field : $default;
};
?>
<div class="wrap lmf93-admin">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Partners', 'leadmagnet' ); ?></h1>
	<a href="<?php echo esc_url( $new_url ); ?>" class="page-title-action"><?php esc_html_e( 'Add new', 'leadmagnet' ); ?></a>
	<hr class="wp-header-end">

	<?php LMF93_Admin::print_notice(); ?>

	<?php if ( $editing || $is_new ) : ?>
		<div class="lmf93-card">
			<h2><?php echo $editing ? esc_html__( 'Edit partner', 'leadmagnet' ) : esc_html__( 'New partner', 'leadmagnet' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
				<?php wp_nonce_field( 'lmf93_save_partner' ); ?>
				<input type="hidden" name="lmf93_action" value="save_partner">
				<input type="hidden" name="partner_id" value="<?php echo (int) $edit_id; ?>">

				<table class="form-table">
					<tr>
						<th><label for="company_name"><?php esc_html_e( 'Company name', 'leadmagnet' ); ?></label></th>
						<td><input name="company_name" id="company_name" type="text" class="regular-text" value="<?php echo esc_attr( $val( 'company_name' ) ); ?>" required></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Active', 'leadmagnet' ); ?></th>
						<td><label><input type="checkbox" name="active" value="1" <?php checked( $val( 'active', 1 ), 1 ); ?>> <?php esc_html_e( 'This partner receives leads', 'leadmagnet' ); ?></label></td>
					</tr>
					<tr>
						<th><label for="email"><?php esc_html_e( 'Email', 'leadmagnet' ); ?></label></th>
						<td><input name="email" id="email" type="email" class="regular-text" value="<?php echo esc_attr( $val( 'email' ) ); ?>"></td>
					</tr>
					<tr>
						<th><label for="phone"><?php esc_html_e( 'Phone', 'leadmagnet' ); ?></label></th>
						<td><input name="phone" id="phone" type="text" class="regular-text" value="<?php echo esc_attr( $val( 'phone' ) ); ?>"></td>
					</tr>
					<tr>
						<th><label for="priority"><?php esc_html_e( 'Priority', 'leadmagnet' ); ?></label></th>
						<td>
							<input name="priority" id="priority" type="number" value="<?php echo esc_attr( $val( 'priority', 10 ) ); ?>" class="small-text">
							<span class="description"><?php esc_html_e( 'Lower number = higher priority.', 'leadmagnet' ); ?></span>
						</td>
					</tr>
					<tr>
						<th><label for="postal_codes"><?php esc_html_e( 'Postal codes', 'leadmagnet' ); ?></label></th>
						<td>
							<textarea name="postal_codes" id="postal_codes" rows="3" class="large-text code"><?php echo esc_textarea( $val( 'postal_codes' ) ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Separate with commas or line breaks. Prefixes are allowed (e.g. "20" matches all 20xxx). Empty = whole country.', 'leadmagnet' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="services"><?php esc_html_e( 'Services', 'leadmagnet' ); ?></label></th>
						<td>
							<textarea name="services" id="services" rows="2" class="large-text code"><?php echo esc_textarea( $val( 'services' ) ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Service values this partner accepts (matches the "service_type" field values). Empty = all.', 'leadmagnet' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="regions"><?php esc_html_e( 'Regions (notes)', 'leadmagnet' ); ?></label></th>
						<td><textarea name="regions" id="regions" rows="2" class="large-text code"><?php echo esc_textarea( $val( 'regions' ) ); ?></textarea></td>
					</tr>
					<tr>
						<th><label for="max_leads_per_day"><?php esc_html_e( 'Max leads / day', 'leadmagnet' ); ?></label></th>
						<td><input name="max_leads_per_day" id="max_leads_per_day" type="number" value="<?php echo esc_attr( $val( 'max_leads_per_day', 0 ) ); ?>" class="small-text"> <span class="description"><?php esc_html_e( '0 = unlimited', 'leadmagnet' ); ?></span></td>
					</tr>
					<tr>
						<th><label for="max_leads_per_month"><?php esc_html_e( 'Max leads / month', 'leadmagnet' ); ?></label></th>
						<td><input name="max_leads_per_month" id="max_leads_per_month" type="number" value="<?php echo esc_attr( $val( 'max_leads_per_month', 0 ) ); ?>" class="small-text"> <span class="description"><?php esc_html_e( '0 = unlimited', 'leadmagnet' ); ?></span></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Exclusive', 'leadmagnet' ); ?></th>
						<td><label><input type="checkbox" name="exclusive" value="1" <?php checked( $val( 'exclusive', 0 ), 1 ); ?>> <?php esc_html_e( 'Route this area\'s leads only to this partner', 'leadmagnet' ); ?></label></td>
					</tr>
				</table>

				<button class="button button-primary"><?php esc_html_e( 'Save partner', 'leadmagnet' ); ?></button>
			</form>
		</div>
	<?php endif; ?>

	<h2><?php esc_html_e( 'All partners', 'leadmagnet' ); ?></h2>
	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Company', 'leadmagnet' ); ?></th>
				<th><?php esc_html_e( 'Active', 'leadmagnet' ); ?></th>
				<th><?php esc_html_e( 'Priority', 'leadmagnet' ); ?></th>
				<th><?php esc_html_e( 'Regions', 'leadmagnet' ); ?></th>
				<th><?php esc_html_e( 'Limits (day/mo)', 'leadmagnet' ); ?></th>
				<th><?php esc_html_e( 'Actions', 'leadmagnet' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $partners ) ) : ?>
				<tr><td colspan="6"><?php esc_html_e( 'No partners yet.', 'leadmagnet' ); ?></td></tr>
			<?php else : ?>
				<?php foreach ( $partners as $p ) : ?>
					<?php
					$edit_url = add_query_arg( array( 'page' => 'lmf93-partners', 'id' => (int) $p->id ), admin_url( 'admin.php' ) );
					$del_url  = wp_nonce_url( add_query_arg( array( 'lmf93_action' => 'delete_partner', 'partner_id' => (int) $p->id ), admin_url( 'admin.php' ) ), 'lmf93_delete_partner' );
					?>
					<tr>
						<td><a href="<?php echo esc_url( $edit_url ); ?>"><strong><?php echo esc_html( $p->company_name ); ?></strong></a></td>
						<td><?php echo $p->active ? '✓' : '—'; ?></td>
						<td><?php echo (int) $p->priority; ?></td>
						<td><?php echo esc_html( wp_trim_words( $p->postal_codes ? $p->postal_codes : __( 'Everywhere', 'leadmagnet' ), 8 ) ); ?></td>
						<td><?php echo (int) $p->max_leads_per_day; ?> / <?php echo (int) $p->max_leads_per_month; ?></td>
						<td>
							<a href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Edit', 'leadmagnet' ); ?></a> |
							<a href="<?php echo esc_url( $del_url ); ?>" class="lmf93-delete" onclick="return confirm('<?php echo esc_js( __( 'Delete this partner?', 'leadmagnet' ) ); ?>');"><?php esc_html_e( 'Delete', 'leadmagnet' ); ?></a>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
</div>
