<?php
/**
 * Follow-up scheduling and processing (own automation, no external SaaS).
 *
 * @package LeadMagnet
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LMF93_Followup
 */
class LMF93_Followup {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'lmf93_lead_created', array( __CLASS__, 'on_lead_created' ), 20, 3 );
	}

	/**
	 * On lead creation: nothing time-based yet unless the site chooses to
	 * schedule from creation. Completion-based scheduling is preferred.
	 *
	 * @param int   $lead_id Lead id.
	 * @param array $mapped  Mapped values.
	 * @param array $config  Form config.
	 * @return void
	 */
	public static function on_lead_created( $lead_id, $mapped, $config ) {
		// Schedule any admin-defined follow-ups whose trigger is lead creation.
		self::schedule_custom( $lead_id, 'lead_created' );
	}

	/**
	 * Return the admin-defined follow-up messages.
	 *
	 * Each item:
	 *   [ 'id'=>'m1', 'enabled'=>1, 'trigger'=>'lead_created|job_completed',
	 *     'delay_days'=>30, 'subject'=>'...', 'body'=>'...' ]
	 *
	 * @return array
	 */
	public static function messages() {
		$msgs = get_option( 'lmf93_followups', array() );
		return is_array( $msgs ) ? $msgs : array();
	}

	/**
	 * Default content for the two fixed follow-ups (review + reminder).
	 *
	 * @return array
	 */
	public static function fixed_defaults() {
		return array(
			// Disabled by default: the site owner opts in on the Emails screen.
			'review_request'   => array(
				'enabled'    => 0,
				'delay_days' => 30,
				'subject'    => __( 'How did we do? Leave us a review', 'leadmagnet' ),
				'body'       => __( "Hi {first_name},\n\nThank you for choosing us. We would love to hear how everything went. Leaving a review takes only a moment and helps us improve.\n\nLeave your review here:\n{review_url}\n", 'leadmagnet' ),
			),
			'service_reminder' => array(
				'enabled'    => 0,
				'delay_days' => 365,
				'subject'    => __( 'A friendly reminder from {site_name}', 'leadmagnet' ),
				'body'       => __( "Hi {first_name},\n\nSome time has passed since we last helped you. If we can be of service again, you can send us a new request any time.\n", 'leadmagnet' ),
			),
		);
	}

	/**
	 * Get one fixed message (merged saved values over defaults).
	 *
	 * @param string $key review_request | service_reminder.
	 * @return array{enabled:int,delay_days:int,subject:string,body:string}
	 */
	public static function fixed_message( $key ) {
		$defaults = self::fixed_defaults();
		$saved    = get_option( 'lmf93_fixed_followups', array() );
		$def      = isset( $defaults[ $key ] ) ? $defaults[ $key ] : array( 'enabled' => 0, 'delay_days' => 0, 'subject' => '', 'body' => '' );
		if ( isset( $saved[ $key ] ) && is_array( $saved[ $key ] ) ) {
			return array_merge( $def, $saved[ $key ] );
		}
		return $def;
	}

	/**
	 * Whether a fixed follow-up is enabled.
	 *
	 * @param string $key review_request | service_reminder.
	 * @return bool
	 */
	public static function fixed_enabled( $key ) {
		$m = self::fixed_message( $key );
		return ! empty( $m['enabled'] );
	}

	/**
	 * Delay in days for a fixed follow-up.
	 *
	 * @param string $key review_request | service_reminder.
	 * @return int
	 */
	public static function fixed_delay( $key ) {
		$m = self::fixed_message( $key );
		return max( 0, (int) $m['delay_days'] );
	}

	/**
	 * Schedule all enabled admin-defined follow-ups for a given trigger.
	 *
	 * @param int    $lead_id Lead id.
	 * @param string $trigger lead_created | job_completed.
	 * @return void
	 */
	public static function schedule_custom( $lead_id, $trigger ) {
		$now = current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp
		foreach ( self::messages() as $msg ) {
			if ( empty( $msg['enabled'] ) ) {
				continue;
			}
			$mtrigger = isset( $msg['trigger'] ) ? $msg['trigger'] : 'job_completed';
			if ( $mtrigger !== $trigger ) {
				continue;
			}
			$delay = isset( $msg['delay_days'] ) ? max( 0, (int) $msg['delay_days'] ) : 0;
			$at    = gmdate( 'Y-m-d H:i:s', $now + $delay * DAY_IN_SECONDS );
			$kind  = isset( $msg['id'] ) ? 'custom:' . sanitize_key( $msg['id'] ) : 'custom';
			self::queue( $lead_id, $kind, $at );
		}
	}

	/**
	 * Schedule 30-day and 365-day follow-ups based on job completion.
	 *
	 * @param int $lead_id Lead id.
	 * @return void
	 */
	public static function schedule_completion_followups( $lead_id ) {
		$do_30  = self::fixed_enabled( 'review_request' );
		$do_365 = self::fixed_enabled( 'service_reminder' );

		global $wpdb;
		$now = current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp

		if ( $do_30 ) {
			$delay = self::fixed_delay( 'review_request' );
			$at    = gmdate( 'Y-m-d H:i:s', $now + $delay * DAY_IN_SECONDS );
			self::queue( $lead_id, 'review_request', $at );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->update(
				LMF93_Helpers::table( 'leads' ),
				array( 'followup_30_at' => $at ),
				array( 'id' => (int) $lead_id ),
				array( '%s' ),
				array( '%d' )
			);
		}

		if ( $do_365 ) {
			$delay = self::fixed_delay( 'service_reminder' );
			$at    = gmdate( 'Y-m-d H:i:s', $now + $delay * DAY_IN_SECONDS );
			self::queue( $lead_id, 'service_reminder', $at );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->update(
				LMF93_Helpers::table( 'leads' ),
				array( 'followup_365_at' => $at ),
				array( 'id' => (int) $lead_id ),
				array( '%s' ),
				array( '%d' )
			);
		}

		// Admin-defined follow-ups triggered by job completion.
		self::schedule_custom( $lead_id, 'job_completed' );

		LMF93_Leads::add_event( $lead_id, 'followup_scheduled', 'Follow-ups scheduled' );
	}

	/**
	 * Add a follow-up to the queue.
	 *
	 * @param int    $lead_id Lead id.
	 * @param string $kind    review_request | service_reminder | custom.
	 * @param string $at      MySQL datetime (UTC).
	 * @return void
	 */
	public static function queue( $lead_id, $kind, $at ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->insert(
			LMF93_Helpers::table( 'followups' ),
			array(
				'lead_id'      => (int) $lead_id,
				'kind'         => sanitize_key( $kind ),
				'scheduled_at' => $at,
				'sent'         => 0,
				'created_at'   => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%d', '%s' )
		);
	}

	/**
	 * Process due follow-ups (called by cron).
	 *
	 * @param int $limit Max to process per run.
	 * @return int Number sent.
	 */
	public static function process_due( $limit = 50 ) {
		global $wpdb;
		$table = LMF93_Helpers::table( 'followups' );
		$now   = gmdate( 'Y-m-d H:i:s' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$due = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE sent = 0 AND scheduled_at <= %s ORDER BY scheduled_at ASC LIMIT %d",
				$now,
				(int) $limit
			)
		);

		if ( ! $due ) {
			return 0;
		}

		$sent = 0;
		foreach ( $due as $item ) {
			$lead = LMF93_Leads::get( $item->lead_id );

			// Skip if lead gone, unsubscribed globally, or opted out of this kind.
			if ( ! $lead || (int) $lead->unsubscribe_all === 1 ) {
				self::mark_sent( $item->id, false );
				continue;
			}
			if ( 'review_request' === $item->kind && (int) $lead->pref_review_request !== 1 ) {
				self::mark_sent( $item->id, false );
				continue;
			}
			if ( 'service_reminder' === $item->kind && (int) $lead->pref_service_reminder !== 1 ) {
				self::mark_sent( $item->id, false );
				continue;
			}
			if ( empty( $lead->email ) || ! is_email( $lead->email ) ) {
				self::mark_sent( $item->id, false );
				continue;
			}

			$ok = self::send_followup( $lead, $item->kind );
			if ( $ok ) {
				$sent++;
			}
			self::mark_sent( $item->id, true );
		}

		return $sent;
	}

	/**
	 * Send a specific follow-up email.
	 *
	 * @param object $lead Lead row.
	 * @param string $kind Follow-up kind.
	 * @return bool
	 */
	protected static function send_followup( $lead, $kind ) {
		$unsub_url = self::unsubscribe_url( $lead->unsubscribe_token );
		$name      = $lead->first_name ? $lead->first_name : '';

		// Admin-defined custom follow-up (kind = "custom:xyz").
		if ( 0 === strpos( (string) $kind, 'custom:' ) ) {
			$msg_id = substr( $kind, strlen( 'custom:' ) );
			$msg    = null;
			foreach ( self::messages() as $m ) {
				if ( isset( $m['id'] ) && sanitize_key( $m['id'] ) === $msg_id ) {
					$msg = $m;
					break;
				}
			}
			if ( ! $msg || empty( $msg['enabled'] ) ) {
				return false;
			}
			$ph            = LMF93_Email::build_placeholders( $lead->id, null );
			$ph['{unsubscribe_url}'] = $unsub_url;
			$search        = array_keys( $ph );
			$replace       = array_values( $ph );
			$subject       = str_replace( $search, $replace, isset( $msg['subject'] ) ? $msg['subject'] : '' );
			$body          = str_replace( $search, $replace, isset( $msg['body'] ) ? $msg['body'] : '' );
			if ( false === strpos( $body, $unsub_url ) ) {
				$body .= "\n\n" . __( 'You can unsubscribe from these messages here:', 'leadmagnet' ) . "\n" . $unsub_url . "\n";
			}
			$ok = LMF93_Email::send( $lead->email, $subject, $body );
			LMF93_Leads::add_event( $lead->id, 'followup_sent', 'Follow-up sent: ' . $kind );
			return $ok;
		}

		if ( 'review_request' === $kind ) {
			$review_url = self::review_url( $lead->unsubscribe_token );
			$fixed      = self::fixed_message( 'review_request' );
			$ph            = LMF93_Email::build_placeholders( $lead->id, null );
			$ph['{review_url}']      = $review_url;
			$ph['{unsubscribe_url}'] = $unsub_url;
			$search  = array_keys( $ph );
			$replace = array_values( $ph );
			$subject = str_replace( $search, $replace, $fixed['subject'] );
			$body    = str_replace( $search, $replace, $fixed['body'] );
			// Safety net: always include the review link and unsubscribe.
			if ( $review_url && false === strpos( $body, $review_url ) ) {
				$body .= "\n\n" . __( 'Leave your review here:', 'leadmagnet' ) . "\n" . $review_url . "\n";
			}
			if ( false === strpos( $body, $unsub_url ) ) {
				$body .= "\n" . __( 'If you no longer wish to receive these messages:', 'leadmagnet' ) . "\n" . $unsub_url . "\n";
			}
		} elseif ( 'service_reminder' === $kind ) {
			$fixed   = self::fixed_message( 'service_reminder' );
			$ph            = LMF93_Email::build_placeholders( $lead->id, null );
			$ph['{unsubscribe_url}'] = $unsub_url;
			$search  = array_keys( $ph );
			$replace = array_values( $ph );
			$subject = str_replace( $search, $replace, $fixed['subject'] );
			$body    = str_replace( $search, $replace, $fixed['body'] );
			if ( false === strpos( $body, $unsub_url ) ) {
				$body .= "\n" . __( 'You can manage your message preferences here:', 'leadmagnet' ) . "\n" . $unsub_url . "\n";
			}
		} else {
			return false;
		}

		$subject = apply_filters( 'lmf93_followup_subject', $subject, $kind, $lead );
		$body    = apply_filters( 'lmf93_followup_body', $body, $kind, $lead );

		$ok = LMF93_Email::send( $lead->email, $subject, $body );
		LMF93_Leads::add_event( $lead->id, 'followup_sent', 'Follow-up sent: ' . $kind );
		return $ok;
	}

	/**
	 * Mark a queued follow-up as processed.
	 *
	 * @param int  $id   Queue id.
	 * @param bool $sent Whether it was actually sent.
	 * @return void
	 */
	protected static function mark_sent( $id, $sent ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update(
			LMF93_Helpers::table( 'followups' ),
			array(
				'sent'    => 1,
				'sent_at' => $sent ? current_time( 'mysql' ) : null,
			),
			array( 'id' => (int) $id ),
			array( '%d', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Build the unsubscribe/preferences URL for a token.
	 *
	 * @param string $token Token.
	 * @return string
	 */
	public static function unsubscribe_url( $token ) {
		$page = LMF93_Helpers::get_option( 'unsubscribe_page_url', '' );
		if ( $page ) {
			return add_query_arg( 'lmf93_token', rawurlencode( $token ), $page );
		}
		// Fallback to a REST-driven preferences endpoint.
		return add_query_arg( 'lmf93_token', rawurlencode( $token ), home_url( '/' ) );
	}

	/**
	 * Build the review-page URL for a token.
	 *
	 * @param string $token Token.
	 * @return string Empty string if no review page is configured.
	 */
	public static function review_url( $token ) {
		$page = LMF93_Helpers::get_option( 'review_page_url', '' );
		if ( ! $page ) {
			return '';
		}
		return add_query_arg( 'lmf93_token', rawurlencode( $token ), $page );
	}
}
