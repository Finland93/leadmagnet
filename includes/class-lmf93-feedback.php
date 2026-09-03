<?php
/**
 * Customer feedback / review loop.
 *
 * Stores 1–5 star ratings tied to a lead. Low ratings (below the configured
 * threshold) capture a reason + comment so the business can reach out and
 * turn the experience around. High ratings are directed to the public
 * business review URL (handled in the front-end review page).
 *
 * All feedback lives in a dedicated private table, never in wp_posts.
 *
 * @package LeadMagnet
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LMF93_Feedback
 */
class LMF93_Feedback {

	/**
	 * Record a rating for a lead.
	 *
	 * @param int    $lead_id Lead id.
	 * @param int    $rating  1–5.
	 * @param string $reason  Optional short reason (for low ratings).
	 * @param string $comment Optional free-text comment.
	 * @return int|false Inserted feedback id, or false on failure.
	 */
	public static function record( $lead_id, $rating, $reason = '', $comment = '' ) {
		global $wpdb;

		$lead_id = (int) $lead_id;
		$rating  = max( 1, min( 5, (int) $rating ) );

		if ( ! $lead_id ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$ok = $wpdb->insert(
			LMF93_Helpers::table( 'feedback' ),
			array(
				'lead_id'    => $lead_id,
				'rating'     => $rating,
				'reason'     => $reason ? sanitize_text_field( $reason ) : null,
				'comment'    => $comment ? sanitize_textarea_field( $comment ) : null,
				'status'     => 'new',
				'created_at' => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s' )
		);

		if ( ! $ok ) {
			return false;
		}

		$feedback_id = (int) $wpdb->insert_id;
		LMF93_Leads::add_event( $lead_id, 'feedback', 'Customer left a rating: ' . $rating . '/5' );

		return $feedback_id;
	}

	/**
	 * Update the handling status of a feedback row.
	 *
	 * @param int    $feedback_id Feedback id.
	 * @param string $status      new | contacted | resolved | ignored.
	 * @return void
	 */
	public static function set_status( $feedback_id, $status ) {
		global $wpdb;
		$allowed = array( 'new', 'contacted', 'resolved', 'ignored' );
		$status  = in_array( $status, $allowed, true ) ? $status : 'new';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update(
			LMF93_Helpers::table( 'feedback' ),
			array( 'status' => $status ),
			array( 'id' => (int) $feedback_id ),
			array( '%s' ),
			array( '%d' )
		);
	}

	/**
	 * List feedback rows joined with a little lead context.
	 *
	 * @param array $args rating_max, status, paged, per_page.
	 * @return array
	 */
	public static function query( $args = array() ) {
		global $wpdb;
		$defaults = array(
			'rating_max' => 0,   // 0 = all; e.g. 2 = only low ratings.
			'status'     => '',
			'paged'      => 1,
			'per_page'   => 50,
		);
		$args  = wp_parse_args( $args, $defaults );

		$fb    = LMF93_Helpers::table( 'feedback' );
		$leads = LMF93_Helpers::table( 'leads' );

		$where  = array( '1=1' );
		$params = array();

		if ( $args['rating_max'] ) {
			$where[]  = 'f.rating <= %d';
			$params[] = (int) $args['rating_max'];
		}
		if ( $args['status'] ) {
			$where[]  = 'f.status = %s';
			$params[] = sanitize_key( $args['status'] );
		}

		$per_page = max( 1, (int) $args['per_page'] );
		$offset   = ( max( 1, (int) $args['paged'] ) - 1 ) * $per_page;

		$sql = "SELECT f.*, l.first_name, l.last_name, l.email, l.phone, l.postal_code, l.partner_id
			FROM {$fb} f
			LEFT JOIN {$leads} l ON l.id = f.lead_id
			WHERE " . implode( ' AND ', $where ) . '
			ORDER BY f.created_at DESC
			LIMIT %d OFFSET %d';

		$params[] = $per_page;
		$params[] = $offset;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
	}

	/**
	 * Simple counts for the dashboard header (avg + totals).
	 *
	 * @return array{count:int,avg:float,low:int}
	 */
	public static function stats() {
		global $wpdb;
		$fb = LMF93_Helpers::table( 'feedback' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row(
			"SELECT COUNT(*) AS count, AVG(rating) AS avg,
			SUM(CASE WHEN rating <= 2 THEN 1 ELSE 0 END) AS low
			FROM {$fb}"
		);
		return array(
			'count' => $row ? (int) $row->count : 0,
			'avg'   => $row && $row->avg ? round( (float) $row->avg, 1 ) : 0.0,
			'low'   => $row ? (int) $row->low : 0,
		);
	}
}
