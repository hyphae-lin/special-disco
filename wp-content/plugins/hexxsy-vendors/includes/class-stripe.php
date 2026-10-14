<?php
/**
 * Stripe Connect: reader onboarding and transfers.
 *
 * @package Hexxsy\Vendors
 */

namespace Hexxsy\Vendors;

/**
 * Talks to the Stripe API directly, so the plugin needs no SDK on the server.
 *
 * Customers pay Hexxsy through the WooCommerce Stripe gateway. Each reader
 * has an Express connected account, and once a share is payable Hexxsy sends
 * it as a transfer tied to the customer's charge (separate charges and
 * transfers). The secret key comes from HEXXSY_STRIPE_SECRET_KEY in
 * wp-config.php, or else from the Stripe gateway's settings, so test or live
 * mode always matches the mode customers were charged in.
 */
class Stripe {

	const API          = 'https://api.stripe.com/v1/';
	const KEY_CONSTANT = 'HEXXSY_STRIPE_SECRET_KEY';
	const RETURN_VAR   = 'hexxsy_stripe';

	/**
	 * User meta prefixes, suffixed with the mode, so test accounts are never
	 * sent live money.
	 */
	const ACCOUNT_META = 'hexxsy_stripe_account_';
	const READY_META   = 'hexxsy_stripe_ready_';

	/**
	 * Hook in.
	 */
	public static function init(): void {
		add_action( 'template_redirect', array( __CLASS__, 'handle_return' ) );
		add_filter( 'allowed_redirect_hosts', array( __CLASS__, 'allowed_hosts' ) );
	}

	/**
	 * Let wp_safe_redirect send readers to Stripe's hosted pages.
	 *
	 * @param array $hosts Allowed hosts.
	 */
	public static function allowed_hosts( $hosts ) {
		$hosts[] = 'connect.stripe.com';
		return $hosts;
	}

	/**
	 * Secret key, or '' if none is set.
	 */
	public static function secret_key(): string {
		if ( defined( self::KEY_CONSTANT ) ) {
			return (string) constant( self::KEY_CONSTANT );
		}
		$settings = (array) get_option( 'woocommerce_stripe_settings', array() );
		$test     = 'yes' === ( $settings['testmode'] ?? '' );
		return (string) ( $test ? ( $settings['test_secret_key'] ?? '' ) : ( $settings['secret_key'] ?? '' ) );
	}

	/**
	 * Whether payouts can go through Stripe.
	 */
	public static function is_configured(): bool {
		return '' !== self::mode();
	}

	/**
	 * test, live, or '' for a missing or unrecognised key.
	 *
	 * @param string $key Stripe secret or restricted key.
	 */
	public static function mode_for_key( string $key ): string {
		if ( preg_match( '/^(sk|rk)_test_/', $key ) ) {
			return 'test';
		}
		if ( preg_match( '/^(sk|rk)_live_/', $key ) ) {
			return 'live';
		}
		return '';
	}

	/**
	 * Current mode.
	 */
	public static function mode(): string {
		return self::mode_for_key( self::secret_key() );
	}

	/**
	 * Call the Stripe API.
	 *
	 * @param string $method          GET or POST.
	 * @param string $path            Path after /v1/.
	 * @param array  $params          Parameters; nested arrays become Stripe's bracket syntax.
	 * @param string $idempotency_key Makes a retried POST safe.
	 * @return array|\WP_Error Decoded response.
	 */
	public static function request( string $method, string $path, array $params = array(), string $idempotency_key = '' ) {
		$key = self::secret_key();
		if ( ! self::mode_for_key( $key ) ) {
			return new \WP_Error( 'hexxsy_stripe', __( 'Stripe is not set up.', 'hexxsy-vendors' ) );
		}

		$headers = array( 'Authorization' => 'Bearer ' . $key );
		if ( '' !== $idempotency_key ) {
			$headers['Idempotency-Key'] = $idempotency_key;
		}
		$url  = self::API . $path;
		$args = array(
			'method'  => $method,
			'timeout' => 30,
			'headers' => $headers,
		);
		if ( 'GET' === $method ) {
			$url = $params ? $url . '?' . http_build_query( $params, '', '&' ) : $url;
		} else {
			$args['body'] = http_build_query( $params, '', '&' );
		}

		$response = wp_remote_request( $url, $args );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) ) {
			return new \WP_Error( 'hexxsy_stripe', __( 'Stripe sent an unreadable response.', 'hexxsy-vendors' ) );
		}
		if ( isset( $body['error'] ) || wp_remote_retrieve_response_code( $response ) >= 400 ) {
			return new \WP_Error( 'hexxsy_stripe', (string) ( $body['error']['message'] ?? __( 'Stripe request failed.', 'hexxsy-vendors' ) ) );
		}
		return $body;
	}

	/**
	 * A reader's connected account in the current mode, or ''.
	 *
	 * @param int $user_id Reader.
	 */
	public static function account_id( int $user_id ): string {
		return (string) get_user_meta( $user_id, self::ACCOUNT_META . self::mode(), true );
	}

	/**
	 * Whether the reader's account can receive transfers, as last checked.
	 *
	 * @param int $user_id Reader.
	 */
	public static function is_ready( int $user_id ): bool {
		return '' !== self::account_id( $user_id ) && (bool) get_user_meta( $user_id, self::READY_META . self::mode(), true );
	}

	/**
	 * Re-check the reader's account with Stripe and store the result.
	 *
	 * @param int $user_id Reader.
	 */
	public static function refresh( int $user_id ): bool {
		$account_id = self::account_id( $user_id );
		if ( '' === $account_id ) {
			return false;
		}
		$account = self::request( 'GET', 'accounts/' . rawurlencode( $account_id ) );
		if ( is_wp_error( $account ) ) {
			return self::is_ready( $user_id );
		}
		$ready = ! empty( $account['payouts_enabled'] ) && 'active' === ( $account['capabilities']['transfers'] ?? '' );
		update_user_meta( $user_id, self::READY_META . self::mode(), $ready ? 1 : 0 );
		return $ready;
	}

	/**
	 * Link to Stripe's hosted onboarding, creating the account first if
	 * the reader doesn't have one yet.
	 *
	 * @param int $user_id Reader.
	 * @return string|\WP_Error
	 */
	public static function onboarding_url( int $user_id ) {
		$account_id = self::account_id( $user_id );
		if ( '' === $account_id ) {
			$user    = get_userdata( $user_id );
			$account = self::request(
				'POST',
				'accounts',
				array(
					'type'          => 'express',
					'email'         => $user ? $user->user_email : '',
					'business_type' => 'individual',
					'capabilities'  => array( 'transfers' => array( 'requested' => 'true' ) ),
					'metadata'      => array( 'hexxsy_user_id' => $user_id ),
				),
				'hexxsy-account-' . self::mode() . '-' . $user_id
			);
			if ( is_wp_error( $account ) ) {
				return $account;
			}
			$account_id = (string) $account['id'];
			update_user_meta( $user_id, self::ACCOUNT_META . self::mode(), $account_id );
		}

		$back = wc_get_account_endpoint_url( Dashboard::ORDERS );
		$link = self::request(
			'POST',
			'account_links',
			array(
				'account'     => $account_id,
				'type'        => 'account_onboarding',
				'return_url'  => add_query_arg( self::RETURN_VAR, 'return', $back ),
				'refresh_url' => add_query_arg( self::RETURN_VAR, 'refresh', $back ),
			)
		);
		return is_wp_error( $link ) ? $link : (string) $link['url'];
	}

	/**
	 * One-time link into the reader's Stripe Express dashboard.
	 *
	 * @param int $user_id Reader.
	 * @return string|\WP_Error
	 */
	public static function dashboard_url( int $user_id ) {
		$account_id = self::account_id( $user_id );
		if ( '' === $account_id ) {
			return new \WP_Error( 'hexxsy_stripe', __( 'Payouts are not set up yet.', 'hexxsy-vendors' ) );
		}
		$link = self::request( 'POST', 'accounts/' . rawurlencode( $account_id ) . '/login_links' );
		return is_wp_error( $link ) ? $link : (string) $link['url'];
	}

	/**
	 * Readers come back here from Stripe's onboarding.
	 */
	public static function handle_return(): void {
		if ( ! isset( $_GET[ self::RETURN_VAR ] ) || ! is_user_logged_in() || ! Vendor::is_approved( get_current_user_id() ) ) {
			return;
		}
		$user_id = get_current_user_id();
		$back    = wc_get_account_endpoint_url( Dashboard::ORDERS );

		if ( 'refresh' === sanitize_key( $_GET[ self::RETURN_VAR ] ) ) {
			// The onboarding link expired; Stripe wants a fresh one.
			$url = self::onboarding_url( $user_id );
			wp_safe_redirect( is_wp_error( $url ) ? $back : $url );
			exit;
		}

		if ( self::refresh( $user_id ) ) {
			wc_add_notice( __( 'Payouts are set up. Your earnings will be sent to your Stripe account.', 'hexxsy-vendors' ) );
		} else {
			wc_add_notice( __( 'Stripe still needs a few details before it can pay you. Use "Finish setting up payouts" to carry on.', 'hexxsy-vendors' ), 'notice' );
		}
		wp_safe_redirect( $back );
		exit;
	}

	/**
	 * Which charge a transfer should draw from, given an order's
	 * transaction ID: a charge ID as is, or a PaymentIntent to look up.
	 *
	 * @param string $transaction_id Order transaction ID.
	 * @return array{charge?:string,intent?:string}
	 */
	public static function parse_transaction( string $transaction_id ): array {
		if ( preg_match( '/^(ch|py)_[A-Za-z0-9]+$/', $transaction_id ) ) {
			return array( 'charge' => $transaction_id );
		}
		if ( preg_match( '/^pi_[A-Za-z0-9]+$/', $transaction_id ) ) {
			return array( 'intent' => $transaction_id );
		}
		return array();
	}

	/**
	 * The Stripe charge behind an order, or '' if it wasn't paid by Stripe.
	 *
	 * @param \WC_Order $order Order.
	 */
	public static function source_charge( $order ): string {
		if ( 0 !== strpos( (string) $order->get_payment_method(), 'stripe' ) ) {
			return '';
		}
		$parsed = self::parse_transaction( (string) $order->get_transaction_id() );
		if ( isset( $parsed['charge'] ) ) {
			return $parsed['charge'];
		}
		if ( isset( $parsed['intent'] ) ) {
			$intent = self::request( 'GET', 'payment_intents/' . rawurlencode( $parsed['intent'] ) );
			if ( ! is_wp_error( $intent ) && is_string( $intent['latest_charge'] ?? null ) ) {
				return $intent['latest_charge'];
			}
		}
		return '';
	}

	/**
	 * Send a reader their share of one order line.
	 *
	 * Tying the transfer to the customer's charge lets it go out before
	 * that money has settled in Hexxsy's balance. Orders paid some other way
	 * draw on the available balance instead.
	 *
	 * @param \WC_Order              $order Order.
	 * @param \WC_Order_Item_Product $item  Order line.
	 * @param int                    $share Amount in minor units.
	 * @return string|\WP_Error Transfer ID.
	 */
	public static function transfer( $order, $item, int $share ) {
		$vendor_id = (int) $item->get_meta( Order_Split::VENDOR_META );
		if ( ! self::is_ready( $vendor_id ) ) {
			return new \WP_Error( 'hexxsy_stripe', __( 'This reader has not finished setting up payouts.', 'hexxsy-vendors' ) );
		}

		$params = array(
			'amount'         => $share,
			'currency'       => strtolower( $order->get_currency() ),
			'destination'    => self::account_id( $vendor_id ),
			'transfer_group' => 'hexxsy-order-' . $order->get_id(),
			'description'    => sprintf( 'Order #%s: %s', $order->get_order_number(), $item->get_name() ),
			'metadata'       => array(
				'order_id' => $order->get_id(),
				'item_id'  => $item->get_id(),
			),
		);
		$charge = self::source_charge( $order );
		if ( '' !== $charge ) {
			$params['source_transaction'] = $charge;
		}

		$transfer = self::request( 'POST', 'transfers', $params, 'hexxsy-transfer-' . $item->get_id() . '-' . $share );
		return is_wp_error( $transfer ) ? $transfer : (string) $transfer['id'];
	}

	/**
	 * Take back part of a transfer after a customer refund.
	 *
	 * @param string $transfer_id     Transfer.
	 * @param int    $amount          Minor units to reverse.
	 * @param int    $already_reversed Minor units reversed before, to keep retries idempotent.
	 * @return true|\WP_Error
	 */
	public static function reverse( string $transfer_id, int $amount, int $already_reversed ) {
		$reversal = self::request(
			'POST',
			'transfers/' . rawurlencode( $transfer_id ) . '/reversals',
			array( 'amount' => $amount ),
			'hexxsy-reversal-' . $transfer_id . '-' . $already_reversed . '-' . $amount
		);
		return is_wp_error( $reversal ) ? $reversal : true;
	}
}
