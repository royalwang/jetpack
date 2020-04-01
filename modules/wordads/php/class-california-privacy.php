<?php

use Automattic\Jetpack\Assets;

/**
 * Class WordAds_California_Privacy
 *
 * Implementation of [California Consumer Privacy Act] (https://leginfo.legislature.ca.gov/faces/codes_displayText.xhtml?lawCode=CIV&division=3.&title=1.81.5.&part=4.&chapter=&article=) as applicable to WordAds.
 * Includes:
 * - Do Not Sell My Personal Information shortcode and widget.
 * - Modal notice to toggle opt-in/opt-out.
 * - Cookie handling. Implements IAB usprivacy cookie specifications.
 * - Client side geo-detection of California visitors by IP address. Avoids issues with page caching.
 *
 */
class WordAds_California_Privacy {

	/**
	 * Initializes required scripts and shortcode.
	 */
	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );

		self::init_shortcode();
	}

	/**
	 * Enqueue required CCPA JavaScript on the frontend.
	 */
	public static function enqueue_scripts() {
		wp_enqueue_script(
			'wordads_ccpa',
			Assets::get_file_url_for_environment(
				'_inc/build/wordads/js/wordads-ccpa.min.js',
				'modules/wordads/js/wordads-ccpa.js'
			),
			array(),
			'1.0.0',
			true
		);

		wp_localize_script(
			'wordads_ccpa',
			'ccpaSettings',
			array(
				'defaultOptinCookieString' => esc_js( self::get_optin_cookie_string() ),
				'cleanslateUrl'            => esc_url( WORDADS_URL ) . 'css/cleanslate.css',
				'ccpaCssUrl'               => esc_url( WORDADS_URL ) . 'css/wordads-ccpa.min.css',
				'ajaxUrl'                  => admin_url( 'admin-ajax.php' ),
				'ajaxNonce'                => wp_create_nonce( 'ccpa_optout' ),
			)
		);
	}

	/**
	 * Initializes handlers for admin AJAX.
	 */
	public static function init_ajax_actions() {
		add_action( 'wp_ajax_privacy_optout', array( __CLASS__, 'handle_optout_request' ) );
		add_action( 'wp_ajax_nopriv_privacy_optout', array( __CLASS__, 'handle_optout_request' ) );

		add_action( 'wp_ajax_privacy_optout_markup', array( __CLASS__, 'handle_optout_markup' ) );
		add_action( 'wp_ajax_nopriv_privacy_optout_markup', array( __CLASS__, 'handle_optout_markup' ) );
	}

	/**
	 * Initializes the [ccpa-do-not-sell-link] shortcode.
	 */
	private static function init_shortcode() {
		add_shortcode( 'ccpa-do-not-sell-link', array( __CLASS__, 'do_not_sell_link_shortcode' ) );
	}

	/**
	 * Outputs [ccpa-do-not-sell-link] shortcode markup.
	 *
	 * @param array  $attributes The shortcode attributes.
	 * @param string $content The shortcode content.
	 *
	 * @return string The generated shortcode markup.
	 */
	public static function do_not_sell_link_shortcode( $attributes, $content ) {
		return '<a href="#" class="ccpa-do-not-sell" style="display: none;">' . self::get_optout_link_text() . '</a>';
	}


	/**
	 * Gets the text used to link to the opt-out page. By law must read 'Do Not Sell My Personal Information'.
	 *
	 * @return mixed|string|void The text of the opt-out link.
	 */
	private static function get_optout_link_text() {
		return __( 'Do Not Sell My Personal Information', 'jetpack' );
	}

	/**
	 * Builds the value of the opt-out cookie.
	 * Format matches spec of [IAB U.S. Privacy String](https://iabtechlab.com/wp-content/uploads/2019/11/U.S.-Privacy-String-v1.0-IAB-Tech-Lab.pdf).
	 *
	 * @param bool $optout True if setting an opt-out cookie.
	 *
	 * @return string The value to be stored in the opt-out cookie.
	 */
	private static function build_iab_privacy_string( $optout ) {
		$values = array(
			'1', // Specification version
			'Y', // Explicit notice to opt-out provided
			$optout ? 'Y' : 'N', // Opt-out of data sale
			'N', // Signatory to IAB Limited Service Provider Agreement
		);

		return join( $values );
	}

	/**
	 * Gets the name to be used for the opt-out cookie.
	 * Name matches spec of [IAB U.S. Privacy String](https://iabtechlab.com/wp-content/uploads/2019/11/U.S.-Privacy-String-v1.0-IAB-Tech-Lab.pdf).
	 *
	 * @return string The name of the opt-out cookie.
	 */
	private static function get_cookie_name() {
		return 'usprivacy';
	}

	/**
	 * Gets the value to be used when an opt-out cookie is set.
	 *
	 * @return string The value to store in the opt-out cookie.
	 */
	private static function get_optout_cookie_string() {
		return self::build_iab_privacy_string( true );
	}

	/**
	 * Gets the value to be used when an opt-in cookie is set.
	 *
	 * @return string The value to store in the opt-in cookie.
	 */
	private static function get_optin_cookie_string() {
		return self::build_iab_privacy_string( false );
	}

	/**
	 * Sets a cookie in the HTTP response to opt-out visitors from data sales.
	 *
	 * @return bool True if the cookie could be set.
	 */
	private static function set_optout_cookie() {
		$cookie_domain = '.wordpress.com' === substr( $_SERVER['HTTP_HOST'], -strlen( '.wordpress.com' ) ) ? '.wordpress.com' : '.' . $_SERVER['HTTP_HOST'];

		return setcookie( self::get_cookie_name(), self::get_optout_cookie_string(), time() + ( 5 * YEAR_IN_SECONDS ), '/', $cookie_domain );
	}

	/**
	 * Sets a cookie in the HTTP response to opt-in visitors from data sales.
	 *
	 * @return bool True if the cookie could be set.
	 */
	private static function set_optin_cookie() {
		$cookie_domain = '.wordpress.com' === substr( $_SERVER['HTTP_HOST'], -strlen( '.wordpress.com' ) ) ? '.wordpress.com' : '.' . $_SERVER['HTTP_HOST'];

		return setcookie( self::get_cookie_name(), self::get_optin_cookie_string(), time() + YEAR_IN_SECONDS, '/', $cookie_domain );
	}

	/**
	 * Handler for opt-in/opt-out AJAX request.
	 */
	public static function handle_optout_request() {
		check_ajax_referer( 'ccpa_optout', 'security' );
		header( 'Content-Type: text/plain; charset=utf-8' );

		$optout = 'true' === $_POST['optout'];
		$optout ? self::set_optout_cookie() : self::set_optin_cookie();

		wp_send_json_success( $optout );
	}

	/**
	 * Handler for modal popup notice markup.
	 */
	public static function handle_optout_markup() {
		header( 'Content-Type: text/html; charset=utf-8' );
		$policy_url = get_option( 'wordads_ccpa_privacy_policy_url' );

		$default_disclosure = sprintf(
			'<p>%s</p>
			<p>%s</p>
			<p><strong>%s</strong></p>
			<p>%s</p>
			<p>%s</p>
			<p>%s</p>',
			esc_html__( 'If you are a California resident, you have the right to opt out of the "sale" of your "personal information" under the California Consumer Privacy Act ("CCPA")', 'jetpack' ),
			esc_html__( 'This site operates an ads program in partnership with third-party vendors who help place ads. Advertising cookies enable these ads partners to serve ads, to personalize those ads based on information like visits to this site and other sites on the internet, and to understand how users engage with those ads. Cookies collect certain information as part of the ads program, and we provide the following categories of information to third-party advertising partners: online identifiers and internet or other network or device activity (such as unique identifiers, cookie information, and IP address), and geolocation data (approximate location information from your IP address). This type of sharing with ads partners may be considered a "sale" of personal information under the CCPA.', 'jetpack' ),
			esc_html__( 'We never share information that identifies you personally, like your name or email address, as part of the advertising program.', 'jetpack' ),
			esc_html__( 'If you’d prefer not to see ads that are personalized based on information from your visits to this site, you can opt-out by toggling the "Do Not Sell My Personal Information" switch below to the On position.', 'jetpack' ),
			esc_html__( 'This opt-out is managed through cookies, so if you delete cookies, your browser is set to delete cookies automatically after a certain length of time, or if you visit this site with a different browser, you’ll need to make this selection again.', 'jetpack' ),
			esc_html__( 'After you opt-out you may still see ads, including personalized ones, on this site and other sites — they just won’t be personalized based on information from your visits to this site.', 'jetpack' )
		);

		/**
		 * Filter on the default CCPA disclosure text.
		 *
		 * @see https://jetpack.com/support/ads/
		 *
		 * @module wordads
		 *
		 * @since 8.4.0
		 *
		 * @param string Default CCPA disclosure for WordAds.
		 */
		$disclosure = apply_filters( 'wordads_ccpa_disclosure', $default_disclosure );
		?>
			<div id="ccpa-modal" class="cleanslate">
				<div class="components-modal__screen-overlay">
					<div tabindex="0"></div>
					<div role="dialog" aria-labelledby="dialog_label" aria-modal="true" class="components-modal__frame">
						<div class="components-modal__content ccpa-settings">
							<div class="components-modal__header">
								<div class="components-modal__header-heading-container">
									<h1 id="dialog_label" class="components-modal__header-heading"><?php esc_html_e( 'Do Not Sell My Personal Information', 'jetpack' ); ?></h1>
								</div>
								<button type="button" aria-label="Close dialog" class="components-button components-icon-button">
									<svg aria-hidden="true" role="img" focusable="false" class="dashicon dashicons-no-alt" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20">
										<path d="M14.95 6.46L11.41 10l3.54 3.54-1.41 1.41L10 11.42l-3.53 3.53-1.42-1.42L8.58 10 5.05 6.47l1.42-1.42L10 8.58l3.54-3.53z"></path>
									</svg>
								</button>
							</div>
							<div class="ccpa-settings__intro-txt"><?php echo wp_kses( $disclosure, wp_kses_allowed_html( 'post' ) ); ?></div>
							<div class="components-modal__footer">
								<div role="form" class="ccpa-setting">
									<label>
										<span class="ccpa-setting__header"><?php esc_html_e( 'Do Not Sell My Personal Information', 'jetpack' ); ?></span>
										<span class="ccpa-setting__toggle components-form-toggle">
											<input id="ccpa-opt-out" class="components-form-toggle__input opt-out" type="checkbox" value="false" autofocus />
											<span class="components-form-toggle__track"></span>
											<span class="components-form-toggle__thumb"></span>
											<svg class="components-form-toggle__on" width="2" height="6" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 2 6" role="img" aria-hidden="true" focusable="false"><path d="M0 0h2v6H0z"></path></svg>
											<svg class="components-form-toggle__off" width="6" height="6" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 6 6" role="img" aria-hidden="true" focusable="false"><path d="M3 1.5c.8 0 1.5.7 1.5 1.5S3.8 4.5 3 4.5 1.5 3.8 1.5 3 2.2 1.5 3 1.5M3 0C1.3 0 0 1.3 0 3s1.3 3 3 3 3-1.3 3-3-1.3-3-3-3z"></path></svg>
										</span>
										<span class="ccpa-setting__toggle-text ccpa-setting__toggle-text-off"><?php esc_html_e( 'Off', 'jetpack' ); ?></span>
										<span class="ccpa-setting__toggle-text ccpa-setting__toggle-text-on"><?php esc_html_e( 'On', 'jetpack' ); ?></span>
									</label>
								</div>
								<div class="components-modal__footer-bottom">
									<button class="components-button is-button is-primary"><?php esc_html_e( 'Close', 'jetpack' ); ?></button>
									<?php
									if ( $policy_url ) {
										printf(
											'<a href="%s" class="ccpa-privacy">%s</a>',
											esc_url( $policy_url ),
											esc_html__( 'View Privacy Policy', 'jetpack' )
										);
									}
									?>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		<?php

		wp_die();
	}
}