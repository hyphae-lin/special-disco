<?php
/**
 * Stripe webhook receiver.
 *
 * @package Hexxsy\Vendors
 */

namespace Hexxsy\Vendors;

/**
 * Receives events at /wp-json/hexxsy/v1/stripe-webhook. In Stripe, add this
 * URL twice: once for events on your account (checkout.session.completed,
 * charge.dispute.created), with its signing secret in
 * HEXXSY_STRIPE_WEBHOOK_SECRET; and once for events on connected accounts
 * (account.updated), with its secret in HEXXSY_STRIPE_CONNECT_WEBHOOK_SECRET.
 */
class Webhook {

	/**
	 * Hook in.
	 */
	public static function init(): void {
		add_action( 'rest_api_init', array( __CLASS__, 'register_route' ) );
	}

	/**
	 * Register the endpoint. Stripe can't log in, so access is controlled by
	 * the signature check instead of a permission callback.
	 */
	public static function register_route(): void {
		register_rest_route(
			'hexxsy/v1',
			'/stripe-webhook',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Verify and handle an event.
	 *
	 * @param \WP_REST_Request $request Request.
	 */
	public static function handle( \WP_REST_Request $request ): \WP_REST_Response {
		$body   = $request->get_body();
		$header = (string) $request->get_header( 'stripe_signature' );
		$valid  = false;

		foreach ( array( 'HEXXSY_STRIPE_WEBHOOK_SECRET', 'HEXXSY_STRIPE_CONNECT_WEBHOOK_SECRET' ) as $constant ) {
			if ( defined( $constant ) && Stripe_Api::verify_signature( $body, $header, (string) constant( $constant ), time() ) ) {
				$valid = true;
				break;
			}
		}
		if ( ! $valid ) {
			return new \WP_REST_Response( array( 'error' => 'bad signature' ), 400 );
		}

		$event  = json_decode( $body, true );
		$object = $event['data']['object'] ?? array();

		switch ( $event['type'] ?? '' ) {
			case 'checkout.session.completed':
				Gateway::complete_from_session( (string) ( $object['id'] ?? '' ) );
				break;

			case 'account.updated':
				Payouts::update_from_account( $object );
				break;

			case 'charge.dispute.created':
				self::flag_dispute( $object );
				break;
		}

		return new \WP_REST_Response( array( 'received' => true ), 200 );
	}

	/**
	 * Put a disputed order on hold and tell the site owner, so any vendor
	 * payouts still waiting aren't sent while it's argued.
	 *
	 * @param array $dispute Stripe dispute object.
	 */
	private static function flag_dispute( array $dispute ): void {
		$orders = wc_get_orders(
			array(
				'limit'      => 1,
				'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => Gateway::INTENT_META,
						'value' => (string) ( $dispute['payment_intent'] ?? '' ),
					),
				),
			)
		);
		if ( ! $orders ) {
			return;
		}

		$order = $orders[0];
		$order->update_status(
			'on-hold',
			/* translators: %s: dispute reason */
			sprintf( __( 'The customer disputed this payment with their bank (reason: %s). Respond in your Stripe dashboard.', 'hexxsy-vendors' ), (string) ( $dispute['reason'] ?? 'unknown' ) )
		);

		wp_mail(
			get_option( 'admin_email' ),
			/* translators: %s: order number */
			sprintf( __( 'Payment disputed on Hexxsy order #%s', 'hexxsy-vendors' ), $order->get_order_number() ),
			__( 'A customer disputed a payment. Stripe gives you a deadline to respond with evidence, so check your Stripe dashboard soon.', 'hexxsy-vendors' )
		);
	}
}
