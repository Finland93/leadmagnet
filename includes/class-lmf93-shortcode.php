<?php
/**
 * Front-end shortcode: [leadmagnet id="1"].
 *
 * @package LeadMagnet
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LMF93_Shortcode
 */
class LMF93_Shortcode {

	/**
	 * Whether assets have been enqueued this request.
	 *
	 * @var bool
	 */
	protected static $enqueued = false;

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_shortcode( 'leadmagnet', array( __CLASS__, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
	}

	/**
	 * Register (but do not force-enqueue) assets.
	 *
	 * @return void
	 */
	public static function register_assets() {
		wp_register_style( 'lmf93-form', LMF93_URL . 'public/css/form.css', array(), LMF93_VERSION );
		wp_register_script( 'lmf93-form', LMF93_URL . 'public/js/form.js', array(), LMF93_VERSION, true );
		wp_register_script( 'lmf93-review', LMF93_URL . 'public/js/review.js', array(), LMF93_VERSION, true );

		$provider = LMF93_Helpers::get_option( 'captcha_provider', 'none' );
		$site_key = LMF93_Helpers::get_option( 'captcha_site_key', '' );

		// Postal / city localization. When auto-fill is on, use the configured
		// dataset URL, or fall back to the shipped Finnish example dataset.
		$autocity     = (bool) LMF93_Helpers::get_option( 'postal_autocity', 0 );
		$postcode_url = LMF93_Helpers::get_option( 'postal_data_url', '' );
		if ( $autocity && ! $postcode_url ) {
			$postcode_url = LMF93_URL . 'public/data/examples/fi-postal-codes.json';
		}

		wp_localize_script(
			'lmf93-form',
			'LMF93',
			array(
				'captchaProvider' => $provider,
				'captchaSiteKey'  => $site_key,
				'autocity'        => $autocity ? 1 : 0,
				'postcodeUrl'     => $autocity ? $postcode_url : '',
				'postalInputMode' => LMF93_Helpers::get_option( 'postal_input_mode', 'any' ),
				'postalMaxLength' => (int) LMF93_Helpers::get_option( 'postal_max_length', 12 ),
				'i18n'            => array(
					'unknownPostcode' => __( 'Unknown postal code', 'leadmagnet' ),
				),
			)
		);

		// CAPTCHA vendor scripts.
		if ( $site_key && 'none' !== $provider ) {
			if ( 'turnstile' === $provider ) {
				wp_register_script( 'lmf93-turnstile', 'https://challenges.cloudflare.com/turnstile/v0/api.js', array(), null, true ); // phpcs:ignore
			} elseif ( 'recaptcha_v2' === $provider ) {
				wp_register_script( 'lmf93-recaptcha', 'https://www.google.com/recaptcha/api.js', array(), null, true ); // phpcs:ignore
			} elseif ( 'recaptcha_v3' === $provider ) {
				wp_register_script( 'lmf93-recaptcha', 'https://www.google.com/recaptcha/api.js?render=' . rawurlencode( $site_key ), array(), null, true ); // phpcs:ignore
			}
		}
	}

	/**
	 * Render the shortcode.
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public static function render( $atts ) {
		$atts = shortcode_atts( array( 'id' => 0 ), $atts, 'leadmagnet' );

		$form_id = (int) $atts['id'];
		if ( ! $form_id ) {
			// If no id given, use the first active form.
			$all = LMF93_Forms::all();
			foreach ( $all as $f ) {
				if ( 'active' === $f->status ) {
					$form_id = (int) $f->id;
					break;
				}
			}
		}

		$form = LMF93_Forms::get( $form_id );
		if ( ! $form || 'active' !== $form->status ) {
			return '';
		}

		self::enqueue();

		return '<div class="lmf93-wrap">' . LMF93_Forms::render( $form ) . '</div>';
	}

	/**
	 * Enqueue assets when a form is actually rendered.
	 *
	 * @return void
	 */
	protected static function enqueue() {
		if ( self::$enqueued ) {
			return;
		}
		wp_enqueue_style( 'lmf93-form' );
		wp_enqueue_script( 'lmf93-form' );

		$provider = LMF93_Helpers::get_option( 'captcha_provider', 'none' );
		$site_key = LMF93_Helpers::get_option( 'captcha_site_key', '' );
		if ( $site_key && 'none' !== $provider ) {
			if ( 'turnstile' === $provider ) {
				wp_enqueue_script( 'lmf93-turnstile' );
			} else {
				wp_enqueue_script( 'lmf93-recaptcha' );
			}
		}

		self::$enqueued = true;
	}
}
