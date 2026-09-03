<?php
/**
 * Public review / rating page.
 *
 * Renders a 1–5 star rating widget via the [lmf93_review] shortcode. The page
 * hosting the shortcode is forced to noindex so it never appears in search.
 *
 * Behaviour:
 *   - Customer clicks a star (1–5).
 *   - Rating at or above the configured threshold (default 4) -> the customer
 *     is sent to the public business review URL (Google/Trustpilot etc.).
 *   - Rating below the threshold -> an inline "what went wrong?" form appears
 *     so the business can capture the issue privately and follow up.
 *
 * The rating is tied to a lead through the per-lead token in the URL
 * (?lmf93_token=...), the same token used for message preferences.
 *
 * @package LeadMagnet
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LMF93_Review
 */
class LMF93_Review {

	/**
	 * A reserved token that lets the site owner preview the whole review flow
	 * (stars, redirect, low-rating form) without a real lead. Nothing is
	 * stored and no email is sent when this token is used.
	 */
	const TEST_TOKEN = 'LMF93-EXAMPLE-TOKEN';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_shortcode( 'lmf93_review', array( __CLASS__, 'render' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
		add_action( 'wp_head', array( __CLASS__, 'maybe_noindex' ), 1 );
	}

	/**
	 * Force noindex on the review page so it stays out of search engines.
	 *
	 * @return void
	 */
	public static function maybe_noindex() {
		if ( self::is_review_page() ) {
			echo '<meta name="robots" content="noindex,nofollow" />' . "\n";
		}
	}

	/**
	 * Is the current singular page the configured review page (or does it
	 * contain our shortcode)?
	 *
	 * @return bool
	 */
	protected static function is_review_page() {
		if ( ! is_singular() ) {
			return false;
		}
		$post = get_post();
		if ( $post && has_shortcode( (string) $post->post_content, 'lmf93_review' ) ) {
			return true;
		}
		$url = LMF93_Helpers::get_option( 'review_page_url', '' );
		if ( $url ) {
			$configured = untrailingslashit( wp_parse_url( $url, PHP_URL_PATH ) );
			$current    = untrailingslashit( wp_parse_url( home_url( add_query_arg( array() ) ), PHP_URL_PATH ) );
			if ( $configured && $configured === $current ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Register the REST route that stores a submitted rating.
	 *
	 * @return void
	 */
	public static function register_routes() {
		register_rest_route(
			'lmf93/v1',
			'/review',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'submit_review' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Store a submitted rating. Returns whether the customer should be
	 * redirected to the public business review URL (high rating) or shown
	 * the private "what went wrong" form (low rating).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function submit_review( WP_REST_Request $request ) {
		$token = sanitize_text_field( (string) $request->get_param( 'lmf93_token' ) );
		if ( empty( $token ) ) {
			// Return 200 with an error flag so the front-end can show a clean
			// message instead of a hard HTTP error in the console.
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'This review link is invalid.', 'leadmagnet' ),
				),
				200
			);
		}

		$rating  = (int) $request->get_param( 'rating' );
		$rating  = max( 1, min( 5, $rating ) );
		$reason  = sanitize_text_field( (string) $request->get_param( 'reason' ) );
		$comment = sanitize_textarea_field( (string) $request->get_param( 'comment' ) );

		$threshold = (int) LMF93_Helpers::get_option( 'review_threshold', 4 );
		$biz_url   = (string) LMF93_Helpers::get_option( 'business_review_url', '' );

		// Test token: lets the site owner verify the whole flow (stars,
		// redirect, low-rating form) without a real lead. Nothing is stored
		// and no email is sent.
		if ( self::TEST_TOKEN === $token ) {
			return new WP_REST_Response(
				array(
					'success'   => true,
					'test'      => true,
					'rating'    => $rating,
					'low'       => $rating < $threshold,
					'redirect'  => ( $rating >= $threshold && $biz_url ) ? $biz_url : '',
					'thank_you' => __( 'Test successful! (This rating was not saved.)', 'leadmagnet' ),
				),
				200
			);
		}

		$lead = LMF93_Leads::get_by_token( $token );
		if ( ! $lead ) {
			// Not a hard 404 — return 200 with an error flag so the page can
			// show a friendly message and the console stays clean.
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'This review link is no longer valid.', 'leadmagnet' ),
				),
				200
			);
		}

		LMF93_Feedback::record( $lead->id, $rating, $reason, $comment );

		// Low rating with a reason/comment: notify the business by email (if
		// enabled) so it can reach out and try to turn the experience around.
		if ( $rating < $threshold && ( '' !== $reason || '' !== $comment ) ) {
			if ( LMF93_Helpers::get_option( 'enable_low_review_notify', 1 ) ) {
				self::notify_low_review( $lead, $rating, $reason, $comment );
			}
		}

		$redirect = '';
		if ( $rating >= $threshold && $biz_url ) {
			$redirect = $biz_url;
		}

		return new WP_REST_Response(
			array(
				'success'   => true,
				'rating'    => $rating,
				'low'       => $rating < $threshold,
				'redirect'  => $redirect,
				'thank_you' => __( 'Thank you for your feedback!', 'leadmagnet' ),
			),
			200
		);
	}

	/**
	 * Reason code -> human label map (shared by the widget and the email).
	 *
	 * @return array
	 */
	public static function reason_labels() {
		return array(
			'scheduling'   => __( 'Scheduling or delays', 'leadmagnet' ),
			'pricing'      => __( 'Pricing or billing', 'leadmagnet' ),
			'work_quality' => __( 'Quality of work', 'leadmagnet' ),
			'communication' => __( 'Communication or reachability', 'leadmagnet' ),
			'other'        => __( 'Other reason', 'leadmagnet' ),
		);
	}

	/**
	 * Email a low review to the configured address (falls back to admin email).
	 *
	 * @param object $lead    Lead row.
	 * @param int    $rating  Rating 1–5.
	 * @param string $reason  Reason code/label.
	 * @param string $comment Free-text comment.
	 * @return void
	 */
	protected static function notify_low_review( $lead, $rating, $reason, $comment ) {
		$settings = get_option( 'lmf93_settings', array() );
		$to       = ! empty( $settings['review_notify_email'] ) ? $settings['review_notify_email'] : '';
		if ( ! $to || ! is_email( $to ) ) {
			$to = ! empty( $settings['admin_email'] ) ? $settings['admin_email'] : get_option( 'admin_email' );
		}
		if ( ! is_email( $to ) ) {
			return;
		}

		$reason_labels = self::reason_labels();
		$reason_text   = isset( $reason_labels[ $reason ] ) ? $reason_labels[ $reason ] : ( $reason ? $reason : __( '(not specified)', 'leadmagnet' ) );

		$name = trim( (string) $lead->first_name . ' ' . (string) $lead->last_name );
		$ref  = LMF93_Helpers::lead_reference( (int) $lead->id );

		/* translators: %d: star rating */
		$subject = sprintf( __( 'Low customer rating (%d/5) – please follow up', 'leadmagnet' ), $rating );

		$body  = __( 'A customer left a low rating. Please reach out and try to turn the experience around.', 'leadmagnet' ) . "\n\n";
		$body .= __( 'Rating:', 'leadmagnet' ) . ' ' . $rating . "/5\n";
		$body .= __( 'Reason:', 'leadmagnet' ) . ' ' . $reason_text . "\n";
		if ( '' !== $comment ) {
			$body .= __( 'Comment:', 'leadmagnet' ) . ' ' . $comment . "\n";
		}
		$body .= "\n" . __( 'Customer details:', 'leadmagnet' ) . "\n";
		$body .= __( 'Name:', 'leadmagnet' ) . ' ' . ( $name ? $name : '-' ) . "\n";
		$body .= __( 'Phone:', 'leadmagnet' ) . ' ' . ( $lead->phone ? $lead->phone : '-' ) . "\n";
		$body .= __( 'Email:', 'leadmagnet' ) . ' ' . ( $lead->email ? $lead->email : '-' ) . "\n";
		$body .= __( 'Reference:', 'leadmagnet' ) . ' ' . $ref . "\n";

		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );
		if ( $lead->email && is_email( $lead->email ) ) {
			$headers[] = 'Reply-To: ' . $name . ' <' . $lead->email . '>';
		}

		LMF93_Email::send( $to, $subject, $body, $headers );
		LMF93_Leads::add_event( (int) $lead->id, 'review_low_notified', 'Low rating notified by email' );
	}

	/**
	 * Render the review widget.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public static function render( $atts = array() ) {
		$atts = shortcode_atts(
			array(
				'title' => __( 'How did we do?', 'leadmagnet' ),
				'intro' => __( 'Rate us by clicking the stars. Your feedback helps us improve.', 'leadmagnet' ),
			),
			$atts,
			'lmf93_review'
		);

		// Token from the URL (?lmf93_token=...).
		$token = isset( $_GET['lmf93_token'] ) ? sanitize_text_field( wp_unslash( $_GET['lmf93_token'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification

		$rest_url  = esc_url_raw( rest_url( 'lmf93/v1/review' ) );
		$threshold = (int) LMF93_Helpers::get_option( 'review_threshold', 4 );

		wp_enqueue_style( 'lmf93-form' );
		wp_enqueue_script( 'lmf93-review' );

		$reason_labels = self::reason_labels();

		ob_start();
		?>
		<div class="lmf93-review" data-rest="<?php echo esc_attr( $rest_url ); ?>" data-token="<?php echo esc_attr( $token ); ?>" data-threshold="<?php echo esc_attr( $threshold ); ?>">
			<?php if ( empty( $token ) ) : ?>
				<p class="lmf93-review-error"><?php esc_html_e( 'This review link is invalid or has expired.', 'leadmagnet' ); ?></p>
			<?php else : ?>
				<h2 class="lmf93-review-title"><?php echo esc_html( $atts['title'] ); ?></h2>
				<p class="lmf93-review-intro"><?php echo esc_html( $atts['intro'] ); ?></p>

				<div class="lmf93-stars" role="radiogroup" aria-label="<?php esc_attr_e( 'Rating', 'leadmagnet' ); ?>">
					<?php for ( $i = 1; $i <= 5; $i++ ) : ?>
						<button type="button" class="lmf93-star" data-value="<?php echo esc_attr( $i ); ?>" role="radio" aria-checked="false" aria-label="<?php echo esc_attr( sprintf( /* translators: %d: number of stars */ _n( '%d star', '%d stars', $i, 'leadmagnet' ), $i ) ); ?>">
							<svg viewBox="0 0 24 24" width="44" height="44" aria-hidden="true"><path d="M12 2l2.9 6.3 6.9.8-5.1 4.7 1.4 6.8L12 17.8 5.9 21.4l1.4-6.8L2.2 9.9l6.9-.8L12 2z"/></svg>
						</button>
					<?php endfor; ?>
				</div>

				<div class="lmf93-review-low" hidden>
					<p class="lmf93-review-low-title"><?php esc_html_e( 'Sorry to hear that. What were you not happy with?', 'leadmagnet' ); ?></p>
					<div class="lmf93-field lmf93-field-select">
						<label for="lmf93_review_reason"><?php esc_html_e( 'Choose a topic', 'leadmagnet' ); ?></label>
						<select id="lmf93_review_reason" class="lmf93-review-reason">
							<option value=""><?php esc_html_e( '— Select —', 'leadmagnet' ); ?></option>
							<?php foreach ( $reason_labels as $rk => $rl ) : ?>
								<option value="<?php echo esc_attr( $rk ); ?>"><?php echo esc_html( $rl ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="lmf93-field lmf93-field-textarea">
						<label for="lmf93_review_comment"><?php esc_html_e( 'Tell us more (optional)', 'leadmagnet' ); ?></label>
						<textarea id="lmf93_review_comment" class="lmf93-review-comment" rows="4"></textarea>
					</div>
					<button type="button" class="lmf93-submit lmf93-review-send"><?php esc_html_e( 'Send feedback', 'leadmagnet' ); ?></button>
				</div>

				<div class="lmf93-review-thanks" hidden>
					<p><?php esc_html_e( 'Thank you for your feedback!', 'leadmagnet' ); ?></p>
				</div>

				<div class="lmf93-review-error-msg" role="alert" hidden></div>
			<?php endif; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}
}
