<?php
/**
 * Stripe Checkout payment gateway.
 *
 * @package Hexxsy\Vendors
 */

namespace Hexxsy\Vendors;

/**
 * Sends the customer to a Stripe-hosted payment page. Card details never
 * touch the site. The platform is the merchant; vendor shares are paid out
 * separately by Payouts once readings are delivered.
 *
 * An order is marked paid by whichever arrives first: the customer returning
 * from Stripe, or the checkout.session.completed webhook.
 */
class Gateway extends \WC_Payment_Gateway {

	const ID            = 'hexxsy_stripe';
	const SESSION_META  = '_hexxsy_stripe_session';
	const INTENT_META   = '_hexxsy_stripe_payment_intent';
	const CHARGE_META   = '_hexxsy_stripe_charge';
	const SESSION_QUERY = 'hexxsy_session';

	/**
	 * Set up the gateway.
	 */
	public function __construct() {
		$this->id                 = self::ID;
		$this->method_title       = __( 'Hexxsy Stripe', 'hexxsy-vendors' );
		$this->method_description = __( 'Card payments through Stripe Checkout, with vendor payouts through Stripe Connect. Keys are set in wp-config.php.', 'hexxsy-vendors' );
		$this->has_fields         = false;
		$this->supports           = array( 'products', 'refunds' );

		$this->init_form_fields();
		$this->init_settings();

		$this->title       = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
	}

	/**
	 * Hook the return handler. Called once from the plugin bootstrap.
	 */
	public static function init(): void {
		add_filter( 'woocommerce_payment_gateways', static fn( $gateways ) => array_merge( $gateways, array( __CLASS__ ) ) );
		add_action( 'template_redirect', array( __CLASS__, 'handle_return' ) );
	}

	/**
	 * Admin settings.
	 */
	public function init_form_fields(): void {
		$this->form_fields = array(
			'enabled'     => array(
				'title'   => __( 'Enable', 'hexxsy-vendors' ),
				'type'    => 'checkbox',
				'label'   => __( 'Take card payments through Stripe', 'hexxsy-vendors' ),
				'default' => 'yes',
			),
			'title'       => array(
				'title'   => __( 'Title', 'hexxsy-vendors' ),
				'type'    => 'text',
				'default' => __( 'Card', 'hexxsy-vendors' ),
			),
			'description' => array(
				'title'   => __( 'Description', 'hexxsy-vendors' ),
				'type'    => 'textarea',
				'default' => __( 'You will pay securely on Stripe\'s page, then come straight back here.', 'hexxsy-vendors' ),
			),
		);
	}

	/**
	 * Only offer the gateway once a key is configured.
	 */
	public function is_available() {
		return Stripe_Api::is_configured() && parent::is_available();
	}

	/**
	 * Warn in the gateway settings when keys are missing.
	 */
	public function admin_options() {
		if ( ! Stripe_Api::is_configured() ) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'Add HEXXSY_STRIPE_SECRET_KEY, HEXXSY_STRIPE_WEBHOOK_SECRET and HEXXSY_STRIPE_CONNECT_WEBHOOK_SECRET to wp-config.php to switch this on.', 'hexxsy-vendors' ) . '</p></div>';
		}
		parent::admin_options();
	}

	/**
	 * Create a Checkout Session and send the customer to it.
	 *
	 * @param int $order_id Order ID.
	 */
	public function process_payment( $order_id ) {
		$order    = wc_get_order( $order_id );
		$decimals = wc_get_price_decimals();

		// Stripe fills in {CHECKOUT_SESSION_ID}; it must not be URL-encoded.
		$return = $order->get_checkout_order_received_url();
		$return .= ( str_contains( $return, '?' ) ? '&' : '?' ) . self::SESSION_QUERY . '={CHECKOUT_SESSION_ID}';

		try {
			$session = Stripe_Api::request(
				'POST',
				'checkout/sessions',
				array(
					'mode'                 => 'payment',
					// Cards only (Apple Pay and Google Pay included). Slower
					// methods like bank debits settle days later.
					'payment_method_types' => array( 'card' ),
					'customer_email'       => $order->get_billing_email(),
					'client_reference_id'  => (string) $order->get_id(),
					'line_items'           => array(
						array(
							'quantity'   => 1,
							'price_data' => array(
								'currency'     => strtolower( $order->get_currency() ),
								'unit_amount'  => Stripe_Api::to_minor( $order->get_total(), $decimals ),
								'product_data' => array(
									/* translators: %s: order number */
									'name' => sprintf( __( 'Hexxsy order #%s', 'hexxsy-vendors' ), $order->get_order_number() ),
								),
							),
						),
					),
					'payment_intent_data'  => array(
						'transfer_group' => 'order_' . $order->get_id(),
						'metadata'       => array( 'order_id' => $order->get_id() ),
					),
					'metadata'             => array( 'order_id' => $order->get_id() ),
					'success_url'          => $return,
					'cancel_url'           => $order->get_checkout_payment_url(),
				)
			);
		} catch ( \RuntimeException $e ) {
			wc_get_logger()->error( $e->getMessage(), array( 'source' => 'hexxsy-gateway' ) );
			wc_add_notice( __( 'We could not start the payment. Please try again in a moment.', 'hexxsy-vendors' ), 'error' );
			return array( 'result' => 'failure' );
		}

		$order->update_meta_data( self::SESSION_META, (string) $session['id'] );
		$order->save();

		return array(
			'result'   => 'success',
			'redirect' => (string) $session['url'],
		);
	}

	/**
	 * Customer is back from Stripe: confirm the payment straight away rather
	 * than waiting for the webhook.
	 */
	public static function handle_return(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the session is verified with Stripe below.
		$session_id = isset( $_GET[ self::SESSION_QUERY ] ) ? sanitize_text_field( wp_unslash( $_GET[ self::SESSION_QUERY ] ) ) : '';
		if ( '' === $session_id || ! is_order_received_page() ) {
			return;
		}
		self::complete_from_session( $session_id );
	}

	/**
	 * Mark an order paid from a Checkout Session, after checking it with
	 * Stripe. Does nothing if the order is already paid.
	 *
	 * @param string $session_id Checkout Session ID.
	 */
	public static function complete_from_session( string $session_id ): void {
		try {
			$session = Stripe_Api::request(
				'GET',
				'checkout/sessions/' . rawurlencode( $session_id ),
				array( 'expand' => array( 'payment_intent' ) )
			);
		} catch ( \RuntimeException $e ) {
			wc_get_logger()->error( $e->getMessage(), array( 'source' => 'hexxsy-gateway' ) );
			return;
		}

		$order = wc_get_order( (int) ( $session['metadata']['order_id'] ?? 0 ) );
		if ( ! $order || $order->is_paid() || 'paid' !== ( $session['payment_status'] ?? '' ) ) {
			return;
		}
		// The session must be the one this order started.
		if ( $order->get_meta( self::SESSION_META ) !== $session['id'] ) {
			return;
		}

		$intent = $session['payment_intent'] ?? array();
		$order->update_meta_data( self::INTENT_META, (string) ( $intent['id'] ?? '' ) );
		$order->update_meta_data( self::CHARGE_META, (string) ( $intent['latest_charge'] ?? '' ) );
		$order->save();
		$order->payment_complete( (string) ( $intent['id'] ?? '' ) );
	}

	/**
	 * Refund through Stripe. Vendor payouts already sent are not clawed back
	 * automatically; the order note says so.
	 *
	 * @param int        $order_id Order ID.
	 * @param float|null $amount   Amount to refund.
	 * @param string     $reason   Reason.
	 * @return bool|\WP_Error
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		$order  = wc_get_order( $order_id );
		$intent = $order ? (string) $order->get_meta( self::INTENT_META ) : '';
		if ( '' === $intent ) {
			return new \WP_Error( 'hexxsy_refund', __( 'No Stripe payment found for this order.', 'hexxsy-vendors' ) );
		}

		$params = array(
			'payment_intent' => $intent,
			'metadata'       => array( 'order_id' => $order->get_id() ),
		);
		if ( null !== $amount ) {
			$params['amount'] = Stripe_Api::to_minor( $amount, wc_get_price_decimals() );
		}

		try {
			$refund = Stripe_Api::request( 'POST', 'refunds', $params );
		} catch ( \RuntimeException $e ) {
			return new \WP_Error( 'hexxsy_refund', $e->getMessage() );
		}

		/* translators: 1: Stripe refund ID, 2: reason */
		$order->add_order_note( sprintf( __( 'Refunded through Stripe (%1$s). %2$s', 'hexxsy-vendors' ), $refund['id'], $reason ) );

		foreach ( $order->get_items() as $item ) {
			if ( Payouts::PAID === $item->get_meta( Payouts::TRANSFER_STATUS_META ) ) {
				$order->add_order_note(
					sprintf(
						/* translators: 1: reading name, 2: Stripe transfer ID */
						__( 'Note: the vendor was already paid for "%1$s" (transfer %2$s). Reverse that transfer in Stripe if they should give it back.', 'hexxsy-vendors' ),
						$item->get_name(),
						$item->get_meta( Payouts::TRANSFER_META )
					)
				);
			}
		}
		return true;
	}
}
