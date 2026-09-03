<?php
/**
 * Admin view: forms manager.
 *
 * @package LeadMagnet
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$forms = LMF93_Forms::all();

// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$edit_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
$editing = $edit_id ? LMF93_Forms::get( $edit_id ) : null;

// New form scaffold.
$is_new       = ( isset( $_GET['new'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$editor_name  = $editing ? $editing->name : '';
$editor_stat  = $editing ? $editing->status : 'active';
$editor_conf  = $editing ? $editing->config : ( $is_new ? LMF93_Forms::default_config() : null );
$editor_json  = $editor_conf ? wp_json_encode( $editor_conf, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) : '';
$new_url      = add_query_arg( array( 'page' => 'lmf93-forms', 'new' => 1 ), admin_url( 'admin.php' ) );
?>
<div class="wrap lmf93-admin">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Forms', 'leadmagnet' ); ?></h1>
	<a href="<?php echo esc_url( $new_url ); ?>" class="page-title-action"><?php esc_html_e( 'Add new', 'leadmagnet' ); ?></a>
	<hr class="wp-header-end">

	<?php LMF93_Admin::print_notice(); ?>

	<?php if ( $editing || $is_new ) : ?>
		<div class="lmf93-card">
			<h2><?php echo $editing ? esc_html__( 'Edit form', 'leadmagnet' ) : esc_html__( 'New form', 'leadmagnet' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
				<?php wp_nonce_field( 'lmf93_save_form' ); ?>
				<input type="hidden" name="lmf93_action" value="save_form">
				<input type="hidden" name="form_id" value="<?php echo (int) $edit_id; ?>">

				<table class="form-table">
					<tr>
						<th><label for="form_name"><?php esc_html_e( 'Name', 'leadmagnet' ); ?></label></th>
						<td><input name="form_name" id="form_name" type="text" class="regular-text" value="<?php echo esc_attr( $editor_name ); ?>" required></td>
					</tr>
					<tr>
						<th><label for="form_status"><?php esc_html_e( 'Status', 'leadmagnet' ); ?></label></th>
						<td>
							<select name="form_status" id="form_status">
								<option value="active" <?php selected( $editor_stat, 'active' ); ?>><?php esc_html_e( 'Active', 'leadmagnet' ); ?></option>
								<option value="inactive" <?php selected( $editor_stat, 'inactive' ); ?>><?php esc_html_e( 'Inactive', 'leadmagnet' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th><label for="form_config"><?php esc_html_e( 'Settings (JSON)', 'leadmagnet' ); ?></label></th>
						<td>
							<textarea name="form_config" id="form_config" rows="24" class="large-text code" spellcheck="false"><?php echo esc_textarea( $editor_json ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Define fields, consents, scoring and pricing (value_rules) here. Field "map" values (first_name, last_name, email, phone, postal_code, city) promote a field to its own column. See the Help page for details.', 'leadmagnet' ); ?></p>
						</td>
					</tr>
				</table>

				<?php if ( $editing ) : ?>
					<p class="lmf93-shortcode-hint">
						<?php esc_html_e( 'Shortcode:', 'leadmagnet' ); ?>
						<code>[leadmagnet id="<?php echo (int) $editing->id; ?>"]</code>
					</p>
				<?php endif; ?>

				<button class="button button-primary"><?php esc_html_e( 'Save form', 'leadmagnet' ); ?></button>
			</form>
		</div>
	<?php endif; ?>

	<h2><?php esc_html_e( 'All forms', 'leadmagnet' ); ?></h2>
	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Name', 'leadmagnet' ); ?></th>
				<th><?php esc_html_e( 'Status', 'leadmagnet' ); ?></th>
				<th><?php esc_html_e( 'Shortcode', 'leadmagnet' ); ?></th>
				<th><?php esc_html_e( 'Actions', 'leadmagnet' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $forms ) ) : ?>
				<tr><td colspan="4"><?php esc_html_e( 'No forms yet.', 'leadmagnet' ); ?></td></tr>
			<?php else : ?>
				<?php foreach ( $forms as $f ) : ?>
					<?php
					$edit_url = add_query_arg( array( 'page' => 'lmf93-forms', 'id' => (int) $f->id ), admin_url( 'admin.php' ) );
					$del_url  = wp_nonce_url( add_query_arg( array( 'lmf93_action' => 'delete_form', 'form_id' => (int) $f->id ), admin_url( 'admin.php' ) ), 'lmf93_delete_form' );
					?>
					<tr>
						<td><a href="<?php echo esc_url( $edit_url ); ?>"><strong><?php echo esc_html( $f->name ); ?></strong></a></td>
						<td><?php echo esc_html( $f->status ); ?></td>
						<td><code>[leadmagnet id="<?php echo (int) $f->id; ?>"]</code></td>
						<td>
							<a href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Edit', 'leadmagnet' ); ?></a> |
							<a href="<?php echo esc_url( $del_url ); ?>" class="lmf93-delete" onclick="return confirm('<?php echo esc_js( __( 'Delete this form?', 'leadmagnet' ) ); ?>');"><?php esc_html_e( 'Delete', 'leadmagnet' ); ?></a>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
</div>
