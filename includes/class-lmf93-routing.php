<?php
/**
 * Partner routing. Never exposes partner data to the front-end.
 *
 * @package LeadMagnet
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LMF93_Routing
 */
class LMF93_Routing {

	/**
	 * Route a lead to a partner. Returns partner id or 0.
	 *
	 * @param int   $lead_id Lead id.
	 * @param array $config  Form config.
	 * @param array $fields  Submitted values.
	 * @param array $mapped  Mapped values (postal_code, etc.).
	 * @return int
	 */
	public static function route( $lead_id, $config, $fields, $mapped ) {
		$partners = self::eligible_partners( $mapped, $fields );

		/**
		 * Allow custom routing strategies.
		 *
		 * @param int   $partner_id Chosen id (0 = none yet).
		 * @param array $partners   Eligible partner rows.
		 * @param int   $lead_id    Lead id.
		 */
		$chosen = apply_filters( 'lmf93_route_lead', 0, $partners, $lead_id, $mapped, $fields );
		if ( $chosen ) {
			return (int) $chosen;
		}

		if ( empty( $partners ) ) {
			return 0;
		}

		// Default strategy: highest priority (lowest number) with capacity left.
		foreach ( $partners as $p ) {
			if ( self::has_capacity( $p ) ) {
				return (int) $p->id;
			}
		}

		return 0;
	}

	/**
	 * Find active partners matching postal code / service.
	 *
	 * @param array $mapped Mapped lead values.
	 * @param array $fields Submitted values.
	 * @return array
	 */
	protected static function eligible_partners( $mapped, $fields ) {
		global $wpdb;
		$table = LMF93_Helpers::table( 'partners' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( "SELECT * FROM {$table} WHERE active = 1 ORDER BY priority ASC, id ASC" );
		if ( ! $rows ) {
			return array();
		}

		$postal  = isset( $mapped['postal_code'] ) ? preg_replace( '/\s+/', '', $mapped['postal_code'] ) : '';
		$service = isset( $fields['service_type'] ) ? $fields['service_type'] : '';

		$eligible = array();
		foreach ( $rows as $p ) {
			// Postal match: partner has a list; empty list = matches everywhere.
			$codes = self::to_list( $p->postal_codes );
			if ( ! empty( $codes ) && $postal ) {
				$match = false;
				foreach ( $codes as $code ) {
					$code = trim( $code );
					if ( '' === $code ) {
						continue;
					}
					// Prefix match ("20" matches "20100"), or exact.
					if ( 0 === strpos( $postal, $code ) ) {
						$match = true;
						break;
					}
				}
				if ( ! $match ) {
					continue;
				}
			}

			// Service match: partner has a list; empty = all services.
			$services = self::to_list( $p->services );
			if ( ! empty( $services ) && $service && ! in_array( $service, $services, true ) ) {
				continue;
			}

			$eligible[] = $p;
		}

		return $eligible;
	}

	/**
	 * Check the partner still has daily/monthly capacity.
	 *
	 * @param object $partner Partner row.
	 * @return bool
	 */
	protected static function has_capacity( $partner ) {
		global $wpdb;
		$leads = LMF93_Helpers::table( 'leads' );

		if ( (int) $partner->max_leads_per_day > 0 ) {
			$since = gmdate( 'Y-m-d 00:00:00' );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$today = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$leads} WHERE partner_id = %d AND created_at >= %s", (int) $partner->id, $since ) );
			if ( $today >= (int) $partner->max_leads_per_day ) {
				return false;
			}
		}

		if ( (int) $partner->max_leads_per_month > 0 ) {
			$since = gmdate( 'Y-m-01 00:00:00' );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$month = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$leads} WHERE partner_id = %d AND created_at >= %s", (int) $partner->id, $since ) );
			if ( $month >= (int) $partner->max_leads_per_month ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Normalize a stored list (JSON array or comma/newline separated) to array.
	 *
	 * @param string $stored Stored value.
	 * @return array
	 */
	protected static function to_list( $stored ) {
		if ( empty( $stored ) ) {
			return array();
		}
		$decoded = json_decode( $stored, true );
		if ( is_array( $decoded ) ) {
			return array_filter( array_map( 'trim', $decoded ) );
		}
		return array_filter( array_map( 'trim', preg_split( '/[,\n\r]+/', $stored ) ) );
	}

	/* ---------------------------------------------------------------------
	 * Partner CRUD (admin).
	 * ------------------------------------------------------------------- */

	/**
	 * Create a partner.
	 *
	 * @param array $data Partner fields.
	 * @return int|false
	 */
	public static function create_partner( $data ) {
		global $wpdb;
		$now = current_time( 'mysql' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$ok = $wpdb->insert(
			LMF93_Helpers::table( 'partners' ),
			array(
				'company_name'        => sanitize_text_field( $data['company_name'] ),
				'active'              => empty( $data['active'] ) ? 0 : 1,
				'priority'            => isset( $data['priority'] ) ? (int) $data['priority'] : 10,
				'email'               => isset( $data['email'] ) ? sanitize_email( $data['email'] ) : '',
				'phone'               => isset( $data['phone'] ) ? sanitize_text_field( $data['phone'] ) : '',
				'postal_codes'        => isset( $data['postal_codes'] ) ? sanitize_textarea_field( $data['postal_codes'] ) : '',
				'regions'             => isset( $data['regions'] ) ? sanitize_textarea_field( $data['regions'] ) : '',
				'services'            => isset( $data['services'] ) ? sanitize_textarea_field( $data['services'] ) : '',
				'max_leads_per_day'   => isset( $data['max_leads_per_day'] ) ? (int) $data['max_leads_per_day'] : 0,
				'max_leads_per_month' => isset( $data['max_leads_per_month'] ) ? (int) $data['max_leads_per_month'] : 0,
				'exclusive'           => empty( $data['exclusive'] ) ? 0 : 1,
				'created_at'          => $now,
				'updated_at'          => $now,
			)
		);
		return $ok ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Update a partner.
	 *
	 * @param int   $id   Partner id.
	 * @param array $data Fields.
	 * @return bool
	 */
	public static function update_partner( $id, $data ) {
		global $wpdb;
		$fields = array(
			'company_name'        => sanitize_text_field( $data['company_name'] ),
			'active'              => empty( $data['active'] ) ? 0 : 1,
			'priority'            => isset( $data['priority'] ) ? (int) $data['priority'] : 10,
			'email'               => isset( $data['email'] ) ? sanitize_email( $data['email'] ) : '',
			'phone'               => isset( $data['phone'] ) ? sanitize_text_field( $data['phone'] ) : '',
			'postal_codes'        => isset( $data['postal_codes'] ) ? sanitize_textarea_field( $data['postal_codes'] ) : '',
			'regions'             => isset( $data['regions'] ) ? sanitize_textarea_field( $data['regions'] ) : '',
			'services'            => isset( $data['services'] ) ? sanitize_textarea_field( $data['services'] ) : '',
			'max_leads_per_day'   => isset( $data['max_leads_per_day'] ) ? (int) $data['max_leads_per_day'] : 0,
			'max_leads_per_month' => isset( $data['max_leads_per_month'] ) ? (int) $data['max_leads_per_month'] : 0,
			'exclusive'           => empty( $data['exclusive'] ) ? 0 : 1,
			'updated_at'          => current_time( 'mysql' ),
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return (bool) $wpdb->update( LMF93_Helpers::table( 'partners' ), $fields, array( 'id' => (int) $id ), null, array( '%d' ) );
	}

	/**
	 * Get one partner.
	 *
	 * @param int $id Partner id.
	 * @return object|null
	 */
	public static function get_partner( $id ) {
		global $wpdb;
		$table = LMF93_Helpers::table( 'partners' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $id ) );
	}

	/**
	 * All partners.
	 *
	 * @return array
	 */
	public static function all_partners() {
		global $wpdb;
		$table = LMF93_Helpers::table( 'partners' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY priority ASC, id ASC" );
		return $rows ? $rows : array();
	}

	/**
	 * Delete a partner.
	 *
	 * @param int $id Partner id.
	 * @return bool
	 */
	public static function delete_partner( $id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return (bool) $wpdb->delete( LMF93_Helpers::table( 'partners' ), array( 'id' => (int) $id ), array( '%d' ) );
	}
}
