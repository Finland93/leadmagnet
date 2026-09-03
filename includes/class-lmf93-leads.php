<?php
/**
 * Lead persistence and lifecycle.
 *
 * @package LeadMagnet
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LMF93_Leads
 */
class LMF93_Leads {

	/**
	 * Create a lead from a validated submission.
	 *
	 * @param array  $data    {
	 *     @type int    form_id
	 *     @type array  fields       Clean field values.
	 *     @type array  consents     purpose => bool.
	 *     @type string source_url
	 *     @type string landing_page
	 *     @type array  utm
	 * }
	 * @param object $form    Form row (decoded config).
	 * @return array|WP_Error  [ 'id' => int, 'reference' => string ] or error.
	 */
	public static function create( $data, $form ) {
		global $wpdb;

		$fields  = isset( $data['fields'] ) ? $data['fields'] : array();
		$config  = $form->config;
		$now     = current_time( 'mysql' );
		$uuid    = wp_generate_uuid4();
		$unsub   = LMF93_Helpers::random_token( 32 );

		// Promote mapped fields to indexed columns.
		$mapped = self::extract_mapped( $config, $fields );

		// Deduplication: same email or phone within a short window (config-driven).
		$dupe_minutes = (int) apply_filters( 'lmf93_dedupe_minutes', 10 );
		if ( $dupe_minutes > 0 && ( ! empty( $mapped['email'] ) || ! empty( $mapped['phone'] ) ) ) {
			if ( self::is_recent_duplicate( $mapped, $dupe_minutes ) ) {
				return new WP_Error( 'lmf93_duplicate', __( 'We already received your request.', 'leadmagnet' ), array( 'status' => 409 ) );
			}
		}

		$ip_hash = LMF93_Helpers::hash_ip( LMF93_Helpers::get_ip() );
		$ua_hash = LMF93_Helpers::hash_user_agent();

		$marketing = ! empty( $data['consents']['marketing_email'] );

		$row = array(
			'lead_uuid'            => $uuid,
			'form_id'              => (int) $form->id,
			'created_at'           => $now,
			'updated_at'           => $now,
			'first_name'           => isset( $mapped['first_name'] ) ? $mapped['first_name'] : null,
			'last_name'            => isset( $mapped['last_name'] ) ? $mapped['last_name'] : null,
			'email'                => isset( $mapped['email'] ) ? $mapped['email'] : null,
			'phone'                => isset( $mapped['phone'] ) ? $mapped['phone'] : null,
			'postal_code'          => isset( $mapped['postal_code'] ) ? $mapped['postal_code'] : null,
			'city'                 => isset( $mapped['city'] ) ? $mapped['city'] : null,
			'payload'              => wp_json_encode( $fields ),
			'status'               => 'new',
			'job_status'           => 'new',
			'marketing_consent'    => $marketing ? 1 : 0,
			'marketing_consent_at' => $marketing ? $now : null,
			'unsubscribe_token'    => $unsub,
			'pref_marketing'       => $marketing ? 1 : 0,
			'source_url'           => isset( $data['source_url'] ) ? esc_url_raw( $data['source_url'] ) : null,
			'landing_page'         => isset( $data['landing_page'] ) ? esc_url_raw( $data['landing_page'] ) : null,
			'utm_source'           => isset( $data['utm']['utm_source'] ) ? sanitize_text_field( $data['utm']['utm_source'] ) : null,
			'utm_medium'           => isset( $data['utm']['utm_medium'] ) ? sanitize_text_field( $data['utm']['utm_medium'] ) : null,
			'utm_campaign'         => isset( $data['utm']['utm_campaign'] ) ? sanitize_text_field( $data['utm']['utm_campaign'] ) : null,
			'ip_hash'              => $ip_hash,
			'ua_hash'              => $ua_hash,
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$ok = $wpdb->insert( LMF93_Helpers::table( 'leads' ), $row );
		if ( ! $ok ) {
			LMF93_Helpers::log( 'Lead insert failed: ' . $wpdb->last_error );
			return new WP_Error( 'lmf93_db', __( 'Could not save your request. Please try again.', 'leadmagnet' ), array( 'status' => 500 ) );
		}

		$lead_id = (int) $wpdb->insert_id;

		self::add_event( $lead_id, 'created', 'Lead created' );

		// Record consents.
		if ( ! empty( $data['consents'] ) ) {
			LMF93_Consent::record_all( $lead_id, $config, $data['consents'], $data['source_url'] );
		}

		// Scoring + value.
		$score = LMF93_Scoring::score( $config, $fields );
		$value = LMF93_Scoring::value( $config, $fields );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update(
			LMF93_Helpers::table( 'leads' ),
			array(
				'lead_score' => (int) $score,
				'lead_value' => (float) $value,
			),
			array( 'id' => $lead_id ),
			array( '%d', '%f' ),
			array( '%d' )
		);

		// Routing.
		$partner_id = LMF93_Routing::route( $lead_id, $config, $fields, $mapped );
		if ( $partner_id ) {
			self::set_partner( $lead_id, $partner_id );
		}

		// Schedule follow-ups if enabled at plugin level (job-completion based ones
		// are scheduled when job_status becomes 'completed'; see set_job_status()).
		do_action( 'lmf93_lead_created', $lead_id, $mapped, $config );

		return array(
			'id'        => $lead_id,
			'reference' => LMF93_Helpers::lead_reference( $lead_id ),
		);
	}

	/**
	 * Pull mapped standard values (first_name, email, etc.) from the field set.
	 *
	 * @param array $config Form config.
	 * @param array $fields Submitted field values.
	 * @return array
	 */
	protected static function extract_mapped( $config, $fields ) {
		$out    = array();
		$fdefs  = isset( $config['fields'] ) ? $config['fields'] : array();
		foreach ( $fdefs as $fdef ) {
			if ( empty( $fdef['map'] ) || empty( $fdef['key'] ) ) {
				continue;
			}
			$key = $fdef['key'];
			if ( isset( $fields[ $key ] ) && '' !== $fields[ $key ] ) {
				$out[ $fdef['map'] ] = is_array( $fields[ $key ] ) ? implode( ', ', $fields[ $key ] ) : $fields[ $key ];
			}
		}
		return $out;
	}

	/**
	 * Check for a recent duplicate by email/phone.
	 *
	 * @param array $mapped  Mapped values.
	 * @param int   $minutes Window in minutes.
	 * @return bool
	 */
	protected static function is_recent_duplicate( $mapped, $minutes ) {
		global $wpdb;
		$table = LMF93_Helpers::table( 'leads' );
		$since = gmdate( 'Y-m-d H:i:s', time() - ( $minutes * MINUTE_IN_SECONDS ) );

		$email = isset( $mapped['email'] ) ? $mapped['email'] : '';
		$phone = isset( $mapped['phone'] ) ? $mapped['phone'] : '';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$found = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE created_at >= %s AND deleted_at IS NULL AND ( ( email <> '' AND email = %s ) OR ( phone <> '' AND phone = %s ) ) LIMIT 1",
				$since,
				$email,
				$phone
			)
		);
		return ! empty( $found );
	}

	/**
	 * Add an audit-trail event.
	 *
	 * @param int    $lead_id Lead id.
	 * @param string $type    Event type slug.
	 * @param string $message Human message (no personal data in message text).
	 * @param array  $meta    Optional meta.
	 * @return void
	 */
	public static function add_event( $lead_id, $type, $message = '', $meta = array() ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->insert(
			LMF93_Helpers::table( 'lead_events' ),
			array(
				'lead_id'    => (int) $lead_id,
				'event_type' => sanitize_key( $type ),
				'message'    => sanitize_text_field( $message ),
				'meta'       => $meta ? wp_json_encode( $meta ) : null,
				'created_at' => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Assign a partner.
	 *
	 * @param int $lead_id    Lead id.
	 * @param int $partner_id Partner id.
	 * @return void
	 */
	public static function set_partner( $lead_id, $partner_id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update(
			LMF93_Helpers::table( 'leads' ),
			array(
				'partner_id' => (int) $partner_id,
				'status'     => 'assigned',
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => (int) $lead_id ),
			array( '%d', '%s', '%s' ),
			array( '%d' )
		);
		self::add_event( $lead_id, 'assigned', 'Assigned to partner #' . (int) $partner_id );

		// Fire trigger for admin-defined follow-ups (e.g. "welcome" message).
		LMF93_Followup::schedule_custom( $lead_id, 'lead_assigned' );
	}

	/**
	 * Update lead status.
	 *
	 * @param int    $lead_id Lead id.
	 * @param string $status  New status.
	 * @return void
	 */
	public static function set_status( $lead_id, $status ) {
		global $wpdb;
		$status = sanitize_key( $status );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update(
			LMF93_Helpers::table( 'leads' ),
			array(
				'status'     => $status,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => (int) $lead_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
		self::add_event( $lead_id, 'status', 'Status changed to ' . $status );

		// Fire a status-specific trigger for admin-defined follow-ups.
		if ( 'won' === $status ) {
			LMF93_Followup::schedule_custom( $lead_id, 'status_won' );
		}
	}

	/**
	 * Update job status. When completed, records completion time and
	 * schedules completion-based follow-ups.
	 *
	 * @param int    $lead_id    Lead id.
	 * @param string $job_status New job status.
	 * @return void
	 */
	public static function set_job_status( $lead_id, $job_status ) {
		global $wpdb;
		$job_status = sanitize_key( $job_status );
		$update     = array(
			'job_status' => $job_status,
			'updated_at' => current_time( 'mysql' ),
		);
		$format     = array( '%s', '%s' );

		if ( 'completed' === $job_status ) {
			$now                    = current_time( 'mysql' );
			$update['completed_at'] = $now;
			$format[]               = '%s';
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update( LMF93_Helpers::table( 'leads' ), $update, array( 'id' => (int) $lead_id ), $format, array( '%d' ) );
		self::add_event( $lead_id, 'job_status', 'Job status: ' . $job_status );

		if ( 'completed' === $job_status ) {
			LMF93_Followup::schedule_completion_followups( $lead_id );
		}
	}

	/**
	 * Mark a lead as billed (invoiced) or clear the flag.
	 *
	 * @param int  $lead_id Lead id.
	 * @param bool $billed  Whether billed.
	 * @return void
	 */
	public static function set_billed( $lead_id, $billed = true ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update(
			LMF93_Helpers::table( 'leads' ),
			array(
				'billed'     => $billed ? 1 : 0,
				'billed_at'  => $billed ? current_time( 'mysql' ) : null,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => (int) $lead_id ),
			array( '%d', '%s', '%s' ),
			array( '%d' )
		);
		self::add_event( $lead_id, 'billed', $billed ? 'Marked as billed' : 'Billing mark removed' );
	}

	/**
	 * Billable leads grouped for the billing screen.
	 *
	 * A lead is billable when it is assigned to a partner, the job has NOT
	 * been cancelled or refunded, and it is not already billed. Optional
	 * date range filters on created_at.
	 *
	 * @param array $args partner_id, date_from, date_to, include_billed.
	 * @return array List of lead rows.
	 */
	public static function billable( $args = array() ) {
		global $wpdb;
		$defaults = array(
			'partner_id'     => 0,
			'date_from'      => '',
			'date_to'        => '',
			'include_billed' => false,
		);
		$args  = wp_parse_args( $args, $defaults );
		$table = LMF93_Helpers::table( 'leads' );

		$where  = array( 'partner_id IS NOT NULL', 'partner_id > 0', 'deleted_at IS NULL' );
		$params = array();

		// Exclude cancelled / refunded jobs and cancelled leads.
		$where[] = "job_status NOT IN ('cancelled','refunded')";
		$where[] = "status NOT IN ('cancelled','refunded','spam')";

		if ( ! $args['include_billed'] ) {
			$where[] = 'billed = 0';
		}
		if ( $args['partner_id'] ) {
			$where[]  = 'partner_id = %d';
			$params[] = (int) $args['partner_id'];
		}
		if ( $args['date_from'] ) {
			$where[]  = 'created_at >= %s';
			$params[] = $args['date_from'] . ' 00:00:00';
		}
		if ( $args['date_to'] ) {
			$where[]  = 'created_at <= %s';
			$params[] = $args['date_to'] . ' 23:59:59';
		}

		$sql = "SELECT * FROM {$table} WHERE " . implode( ' AND ', $where ) . ' ORDER BY partner_id ASC, created_at ASC';
		if ( $params ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
			$sql = $wpdb->prepare( $sql, $params );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		return $wpdb->get_results( $sql );
	}

	/**
	 * Fetch one lead.
	 *
	 * @param int $id Lead id.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = LMF93_Helpers::table( 'leads' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d AND deleted_at IS NULL", (int) $id ) );
	}

	/**
	 * Fetch a lead by its unsubscribe token.
	 *
	 * @param string $token Token.
	 * @return object|null
	 */
	public static function get_by_token( $token ) {
		global $wpdb;
		$table = LMF93_Helpers::table( 'leads' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE unsubscribe_token = %s AND deleted_at IS NULL", $token ) );
	}

	/**
	 * List leads with basic filtering + pagination for the admin CRM.
	 *
	 * @param array $args status, form_id, search, paged, per_page.
	 * @return array [ 'items' => [], 'total' => int ]
	 */
	public static function query( $args = array() ) {
		global $wpdb;
		$table = LMF93_Helpers::table( 'leads' );

		$defaults = array(
			'status'   => '',
			'form_id'  => 0,
			'search'   => '',
			'paged'    => 1,
			'per_page' => 25,
		);
		$args = wp_parse_args( $args, $defaults );

		$where  = array( 'deleted_at IS NULL' );
		$params = array();

		if ( $args['status'] ) {
			$where[]  = 'status = %s';
			$params[] = sanitize_key( $args['status'] );
		}
		if ( $args['form_id'] ) {
			$where[]  = 'form_id = %d';
			$params[] = (int) $args['form_id'];
		}
		if ( $args['search'] ) {
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[]  = '( first_name LIKE %s OR last_name LIKE %s OR email LIKE %s OR phone LIKE %s OR city LIKE %s )';
			$params   = array_merge( $params, array( $like, $like, $like, $like, $like ) );
		}

		$where_sql = implode( ' AND ', $where );
		$per_page  = max( 1, (int) $args['per_page'] );
		$offset    = ( max( 1, (int) $args['paged'] ) - 1 ) * $per_page;

		// Count.
		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$total = (int) $wpdb->get_var( $params ? $wpdb->prepare( $count_sql, $params ) : $count_sql );

		// Page.
		$list_sql       = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY id DESC LIMIT %d OFFSET %d";
		$list_params    = array_merge( $params, array( $per_page, $offset ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$items = $wpdb->get_results( $wpdb->prepare( $list_sql, $list_params ) );

		return array(
			'items' => $items ? $items : array(),
			'total' => $total,
		);
	}

	/**
	 * Get audit-trail events for a lead.
	 *
	 * @param int $lead_id Lead id.
	 * @return array
	 */
	public static function get_events( $lead_id ) {
		global $wpdb;
		$table = LMF93_Helpers::table( 'lead_events' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE lead_id = %d ORDER BY id ASC", (int) $lead_id ) );
		return $rows ? $rows : array();
	}

	/**
	 * GDPR: hard-delete a lead and all related rows.
	 *
	 * @param int $lead_id Lead id.
	 * @return void
	 */
	public static function hard_delete( $lead_id ) {
		global $wpdb;
		$lead_id = (int) $lead_id;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( LMF93_Helpers::table( 'leads' ), array( 'id' => $lead_id ), array( '%d' ) );
		$wpdb->delete( LMF93_Helpers::table( 'lead_events' ), array( 'lead_id' => $lead_id ), array( '%d' ) );
		$wpdb->delete( LMF93_Helpers::table( 'consents' ), array( 'lead_id' => $lead_id ), array( '%d' ) );
		$wpdb->delete( LMF93_Helpers::table( 'followups' ), array( 'lead_id' => $lead_id ), array( '%d' ) );
		$wpdb->delete( LMF93_Helpers::table( 'feedback' ), array( 'lead_id' => $lead_id ), array( '%d' ) );
		// phpcs:enable
	}

	/**
	 * GDPR: anonymize a lead (keep business/statistical columns, drop PII).
	 *
	 * @param int $lead_id Lead id.
	 * @return void
	 */
	public static function anonymize( $lead_id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update(
			LMF93_Helpers::table( 'leads' ),
			array(
				'first_name'        => null,
				'last_name'         => null,
				'email'             => null,
				'phone'             => null,
				'payload'           => null,
				'ip_hash'           => null,
				'ua_hash'           => null,
				'unsubscribe_token' => null,
				'updated_at'        => current_time( 'mysql' ),
			),
			array( 'id' => (int) $lead_id ),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);
		// Drop consent + event PII proofs too.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( LMF93_Helpers::table( 'consents' ), array( 'lead_id' => (int) $lead_id ), array( '%d' ) );
		// phpcs:enable
		self::add_event( $lead_id, 'anonymized', 'Lead anonymized (retention)' );
	}

	/**
	 * GDPR export: return a lead's personal data as an array.
	 *
	 * @param int $lead_id Lead id.
	 * @return array
	 */
	public static function export( $lead_id ) {
		$lead = self::get( $lead_id );
		if ( ! $lead ) {
			return array();
		}
		return array(
			'reference'   => LMF93_Helpers::lead_reference( $lead->id ),
			'created_at'  => $lead->created_at,
			'first_name'  => $lead->first_name,
			'last_name'   => $lead->last_name,
			'email'       => $lead->email,
			'phone'       => $lead->phone,
			'postal_code' => $lead->postal_code,
			'city'        => $lead->city,
			'fields'      => json_decode( $lead->payload, true ),
			'consents'    => LMF93_Consent::get_for_lead( $lead_id ),
		);
	}
}
