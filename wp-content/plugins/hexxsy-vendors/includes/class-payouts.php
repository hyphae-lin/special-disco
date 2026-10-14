<?php
/**
 * Vendor Stripe accounts and transfers.
 *
 * @package Hexxsy\Vendors
 */

namespace Hexxsy\Vendors;

/**
 * Each vendor gets a Stripe Express account through Stripe-hosted sign-up.
 * Customers pay the platform; a vendor's share is transferred per order line
 * once the reading is delivered, tied to the original charge so the money is
 * available as soon as that charge settles.
 */
class Payouts {

	const ACCOUNT_META = 'hexxsy_stripe_account';
	const READY_META   = 'hexxsy_stripe_ready';

	const TRANSFER_META        = '_hexxsy_transfer_id';
	const TRANSFER_STATUS_META = '_hexxsy_transfer_status';

	const PAID    = 'paid';
	const WAITING = 'waiting_for_vendor';
	const FAILED  = 'failed';

	/**
	 * Hook in.
	 */
	public static function init(): void {
		add_action( 'template_redirect', array( __CLASS__, 'handle_stripe_return' ) );
	}

	/**
	 * Vendor's connected account ID, or ''.
	 *
	 * @param int $vendor_id User ID.
	 */
	public static function account_id( int $vendor_id ): string {
		return (string) get_user_meta( $vendor_id, self::ACCOUNT_META, true );
	}

	/**
	 * Whether the vendor can receive transfers.
	 *
	 * @param int $vendor_id User ID.
	 */
	public static function is_ready( int $vendor_id ): bool {
		return '' !== self::account_id( $vendor_id ) && (bool) get_user_meta( $vendor_id, self::READY_META, true );
	}

	/**
	 * Send the vendor to Stripe's hosted sign-up, creating their account the
	 * first time. Returns the Stripe URL to redirect to, or '' on failure.
	 */
	public static function onboarding_url(): string {
		$vendor_id = get_current_user_id();
		$base      = wc_get_account_endpoint_url( Dashboard::PAYOUTS );

		try {
			$account_id = self::account_id( $vendor_id );
			if ( '' === $account_id ) {
				$user    = wp_get_current_user();
				$account = Stripe_Api::request(
					'POST',
					'accounts',
					array(
						'email'        => $user->user_email,
						'controller'   => array(
							'fees'                   => array( 'payer' => 'application' ),
							'losses'                 => array( 'payments' => 'application' ),
							'stripe_dashboard'       => array( 'type' => 'express' ),
							'requirement_collection' => 'stripe',
						),
						'capabilities' => array( 'transfers' => array( 'requested' => 'true' ) ),
						'metadata'     => array( 'wp_user_id' => $vendor_id ),
					),
					'hexxsy-account-' . $vendor_id
				);
				$account_id = (string) $account['id'];
				update_user_meta( $vendor_id, self::ACCOUNT_META, $account_id );
			}

			$link = Stripe_Api::request(
				'POST',
				'account_links',
				array(
					'account'     => $account_id,
					'type'        => 'account_onboarding',
					'refresh_url' => add_query_arg( 'stripe', 'refresh', $base ),
					'return_url'  => add_query_arg( 'stripe', 'return', $base ),
				)
			);
			return (string) $link['url'];
		} catch ( \RuntimeException $e ) {
			wc_add_notice( __( 'Stripe could not be reached. Please try again in a few minutes.', 'hexxsy-vendors' ), 'error' );
			wc_get_logger()->error( $e->getMessage(), array( 'source' => 'hexxsy-payouts' ) );
			return '';
		}
	}

	/**
	 * One-time link into the vendor's Stripe Express dashboard, or ''.
	 */
	public static function dashboard_url(): string {
		$account_id = self::account_id( get_current_user_id() );
		if ( '' === $account_id ) {
			return '';
		}
		try {
			$link = Stripe_Api::request( 'POST', 'accounts/' . rawurlencode( $account_id ) . '/login_links' );
			return (string) $link['url'];
		} catch ( \RuntimeException $e ) {
			wc_add_notice( __( 'Stripe could not be reached. Please try again in a few minutes.', 'hexxsy-vendors' ), 'error' );
			wc_get_logger()->error( $e->getMessage(), array( 'source' => 'hexxsy-payouts' ) );
			return '';
		}
	}

	/**
	 * Handle the vendor coming back from Stripe's sign-up.
	 */
	public static function handle_stripe_return(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Stripe redirect; only refreshes the current user's own status.
		$state = isset( $_GET['stripe'] ) ? sanitize_key( $_GET['stripe'] ) : '';
		if ( '' === $state || ! is_user_logged_in() || ! is_wc_endpoint_url( Dashboard::PAYOUTS ) ) {
			return;
		}

		$vendor_id = get_current_user_id();
		if ( ! Vendor::is_approved( $vendor_id ) ) {
			return;
		}

		if ( 'refresh' === $state ) {
			// The sign-up link expired; make a fresh one.
			$url = self::onboarding_url();
			if ( '' !== $url ) {
				wp_redirect( $url ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- Stripe-hosted page.
				exit;
			}
			return;
		}

		if ( 'return' === $state ) {
			try {
				$account = Stripe_Api::request( 'GET', 'accounts/' . rawurlencode( self::account_id( $vendor_id ) ) );
				self::update_from_account( $account );
			} catch ( \RuntimeException $e ) {
				wc_get_logger()->error( $e->getMessage(), array( 'source' => 'hexxsy-payouts' ) );
			}
			wp_safe_redirect( wc_get_account_endpoint_url( Dashboard::PAYOUTS ) );
			exit;
		}
	}

	/**
	 * Store a vendor's readiness from a Stripe account object, from the
	 * return redirect or an account.updated webhook. Sends any payouts that
	 * were waiting on them.
	 *
	 * @param array $account Stripe account object.
	 */
	public static function update_from_account( array $account ): void {
		$users = get_users(
			array(
				'meta_key'   => self::ACCOUNT_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value' => (string) ( $account['id'] ?? '' ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'number'     => 1,
				'fields'     => 'ID',
			)
		);
		if ( ! $users ) {
			return;
		}

		$vendor_id = (int) $users[0];
		$was_ready = self::is_ready( $vendor_id );
		$ready     = ! empty( $account['payouts_enabled'] ) && 'active' === ( $account['capabilities']['transfers'] ?? '' );

		update_user_meta( $vendor_id, self::READY_META, $ready ? 1 : 0 );

		if ( $ready && ! $was_ready ) {
			self::retry_waiting( $vendor_id );
		}
	}

	/**
	 * Transfer the vendor's share of a delivered order line. Safe to call
	 * more than once: Stripe's idempotency key and the stored transfer ID
	 * both stop a second payment.
	 *
	 * @param \WC_Order      $order Order.
	 * @param \WC_Order_Item $item  Order line.
	 */
	public static function transfer_item( $order, $item ): void {
		if ( self::PAID === $item->get_meta( self::TRANSFER_STATUS_META ) ) {
			return;
		}

		$vendor_id = (int) $item->get_meta( Order_Split::VENDOR_META );
		$amount    = Stripe_Api::to_minor( $item->get_meta( Order_Split::EARNINGS_META ), wc_get_price_decimals() );
		$charge_id = (string) $order->get_meta( Gateway::CHARGE_META );

		if ( ! $vendor_id || $amount <= 0 || '' === $charge_id ) {
			return;
		}

		if ( ! self::is_ready( $vendor_id ) ) {
			$item->update_meta_data( self::TRANSFER_STATUS_META, self::WAITING );
			$item->save();
			$order->add_order_note(
				/* translators: %s: reading name */
				sprintf( __( 'Vendor payout for "%s" is waiting: the vendor has not finished Stripe sign-up.', 'hexxsy-vendors' ), $item->get_name() )
			);
			return;
		}

		try {
			$transfer = Stripe_Api::request(
				'POST',
				'transfers',
				array(
					'amount'             => $amount,
					'currency'           => strtolower( $order->get_currency() ),
					'destination'        => self::account_id( $vendor_id ),
					'source_transaction' => $charge_id,
					'transfer_group'     => 'order_' . $order->get_id(),
					'description'        => sprintf( 'Hexxsy order #%s: %s', $order->get_order_number(), $item->get_name() ),
					'metadata'           => array(
						'order_id' => $order->get_id(),
						'item_id'  => $item->get_id(),
					),
				),
				'hexxsy-transfer-' . $item->get_id()
			);

			$item->update_meta_data( self::TRANSFER_META, (string) $transfer['id'] );
			$item->update_meta_data( self::TRANSFER_STATUS_META, self::PAID );
			$item->save();
			$order->add_order_note(
				/* translators: 1: amount, 2: reading name, 3: Stripe transfer ID */
				sprintf( __( 'Paid vendor %1$s for "%2$s" (Stripe transfer %3$s).', 'hexxsy-vendors' ), wc_price( $amount / ( 10 ** wc_get_price_decimals() ) ), $item->get_name(), $transfer['id'] )
			);
		} catch ( \RuntimeException $e ) {
			$item->update_meta_data( self::TRANSFER_STATUS_META, self::FAILED );
			$item->save();
			$order->add_order_note(
				/* translators: 1: reading name, 2: error */
				sprintf( __( 'Vendor payout for "%1$s" failed: %2$s', 'hexxsy-vendors' ), $item->get_name(), $e->getMessage() )
			);
		}
	}

	/**
	 * Send payouts that were waiting for this vendor to finish sign-up.
	 *
	 * @param int $vendor_id User ID.
	 */
	public static function retry_waiting( int $vendor_id ): void {
		$orders = wc_get_orders(
			array(
				'limit'      => -1,
				'status'     => array( 'wc-processing', 'wc-completed' ),
				'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => Order_Split::ORDER_VENDOR_META,
						'value' => $vendor_id,
					),
				),
			)
		);
		foreach ( $orders as $order ) {
			foreach ( $order->get_items() as $item ) {
				if ( (int) $item->get_meta( Order_Split::VENDOR_META ) === $vendor_id
					&& in_array( $item->get_meta( self::TRANSFER_STATUS_META ), array( self::WAITING, self::FAILED ), true ) ) {
					self::transfer_item( $order, $item );
				}
			}
		}
	}

	/**
	 * Short payout status for the vendor's order list.
	 *
	 * @param \WC_Order_Item $item Order line.
	 */
	public static function status_label( $item ): string {
		switch ( $item->get_meta( self::TRANSFER_STATUS_META ) ) {
			case self::PAID:
				return __( 'Paid out to your Stripe account', 'hexxsy-vendors' );
			case self::WAITING:
				return __( 'Payout waiting: finish Stripe sign-up in Payouts', 'hexxsy-vendors' );
			case self::FAILED:
				return __( 'Payout failed; Hexxsy has been notified', 'hexxsy-vendors' );
			default:
				return __( 'Paid out once delivered', 'hexxsy-vendors' );
		}
	}

	/**
	 * The vendor's Payouts tab.
	 */
	public static function render(): void {
		$vendor_id = get_current_user_id();

		if ( ! Stripe_Api::is_configured() ) {
			echo '<p>' . esc_html__( 'Payouts are not switched on yet. Hexxsy will let you know when you can connect your bank account.', 'hexxsy-vendors' ) . '</p>';
			return;
		}

		if ( Stripe_Api::is_test_mode() ) {
			echo '<p><em>' . esc_html__( 'Test mode: no real money moves.', 'hexxsy-vendors' ) . '</em></p>';
		}

		$has_account = '' !== self::account_id( $vendor_id );
		?>
		<?php if ( self::is_ready( $vendor_id ) ) : ?>
			<p><?php esc_html_e( 'Your payouts are set up. Your share of each reading is sent to your Stripe account when you deliver it, and Stripe pays it out to your bank.', 'hexxsy-vendors' ); ?></p>
			<form method="post">
				<?php wp_nonce_field( Dashboard::NONCE, '_hexxsy_nonce' ); ?>
				<input type="hidden" name="hexxsy_dashboard_action" value="stripe_dashboard">
				<button type="submit" class="button"><?php esc_html_e( 'Open my Stripe dashboard', 'hexxsy-vendors' ); ?></button>
			</form>
		<?php else : ?>
			<p>
				<?php
				echo esc_html(
					$has_account
						? __( 'Your Stripe sign-up isn\'t finished, or Stripe needs more information. Payouts wait until it\'s done.', 'hexxsy-vendors' )
						: __( 'To get paid, connect a bank account through Stripe. It takes a few minutes, and Stripe handles your identity check and tax details.', 'hexxsy-vendors' )
				);
				?>
			</p>
			<form method="post">
				<?php wp_nonce_field( Dashboard::NONCE, '_hexxsy_nonce' ); ?>
				<input type="hidden" name="hexxsy_dashboard_action" value="stripe_onboard">
				<button type="submit" class="button"><?php echo esc_html( $has_account ? __( 'Continue Stripe sign-up', 'hexxsy-vendors' ) : __( 'Set up payouts with Stripe', 'hexxsy-vendors' ) ); ?></button>
			</form>
		<?php endif; ?>
		<?php
	}
}
