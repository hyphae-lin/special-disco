<?php
/**
 * Admin screen for paying readers.
 *
 * @package Hexxsy\Vendors
 */

namespace Hexxsy\Vendors;

/**
 * Adds WooCommerce > Payouts: the hold period, what each reader is owed,
 * and buttons to send it by Stripe transfer or record a payment made by
 * hand, plus reversals when a customer is refunded after a payout.
 */
class Payouts_Admin {

	const PAGE    = 'hexxsy-payouts';
	const ACTION  = 'hexxsy_pay_reader';
	const REVERSE = 'hexxsy_reverse_payout';

	/**
	 * Hook in.
	 */
	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 61 );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle_pay' ) );
		add_action( 'admin_post_' . self::REVERSE, array( __CLASS__, 'handle_reverse' ) );
	}

	/**
	 * Register the submenu.
	 */
	public static function menu(): void {
		add_submenu_page(
			'woocommerce',
			__( 'Payouts', 'hexxsy-vendors' ),
			__( 'Payouts', 'hexxsy-vendors' ),
			Admin::CAPABILITY,
			self::PAGE,
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Register the hold period setting.
	 */
	public static function register_settings(): void {
		register_setting(
			self::PAGE,
			Payout::HOLD_OPTION,
			array(
				'type'              => 'integer',
				'default'           => Payout::DEFAULT_HOLD_DAYS,
				'sanitize_callback' => static fn( $value ) => max( 0, (int) $value ),
			)
		);
	}

	/**
	 * Render the Payouts page.
	 */
	public static function render_page(): void {
		if ( ! current_user_can( Admin::CAPABILITY ) ) {
			return;
		}

		$as_of    = time();
		$vendors  = array();
		$overpaid = array();
		foreach ( Payout::lines() as list( $order, $item ) ) {
			$vendor_id = (int) $item->get_meta( Order_Split::VENDOR_META );
			$vendors[ $vendor_id ] ??= array(
				Payout::HELD    => 0,
				Payout::PAYABLE => 0,
			);
			$state = Payout::line_state( $order, $item, $as_of );
			if ( isset( $vendors[ $vendor_id ][ $state ] ) ) {
				$vendors[ $vendor_id ][ $state ] += Payout::line_share( $order, $item );
			}
			$owed_back = Payout::overpaid( $order, $item );
			if ( $owed_back ) {
				$overpaid[] = array( $order, $item, $vendor_id, $owed_back );
			}
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Payouts', 'hexxsy-vendors' ); ?></h1>
			<?php self::render_notice(); ?>
			<p><?php esc_html_e( 'Hexxsy holds each payment. A written reading becomes payable once it is delivered and the hold period has passed. Live calls stay on hold until booking exists. Readers who have set up payouts are paid by Stripe transfer; for anyone else, send the money yourself and click Mark paid.', 'hexxsy-vendors' ); ?></p>
			<p><strong><?php esc_html_e( 'Stripe:', 'hexxsy-vendors' ); ?></strong>
				<?php
				if ( 'live' === Stripe::mode() ) {
					esc_html_e( 'live. Transfers send real money.', 'hexxsy-vendors' );
				} elseif ( 'test' === Stripe::mode() ) {
					esc_html_e( 'test mode. Transfers use sandbox money.', 'hexxsy-vendors' );
				} else {
					printf(
						/* translators: %s: constant name */
						esc_html__( 'not connected. Set up the WooCommerce Stripe gateway, or define %s in wp-config.php.', 'hexxsy-vendors' ),
						'<code>' . esc_html( Stripe::KEY_CONSTANT ) . '</code>'
					);
				}
				?>
			</p>

			<form method="post" action="options.php">
				<?php settings_fields( self::PAGE ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="hexxsy_payout_hold_days"><?php esc_html_e( 'Hold after delivery (days)', 'hexxsy-vendors' ); ?></label></th>
						<td>
							<input type="number" id="hexxsy_payout_hold_days" name="<?php echo esc_attr( Payout::HOLD_OPTION ); ?>" value="<?php echo esc_attr( (string) Payout::hold_days() ); ?>" min="0" step="1" class="small-text">
							<p class="description"><?php esc_html_e( 'Applies to readings delivered from now on. Earlier readings keep the date they were given.', 'hexxsy-vendors' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Save hold period', 'hexxsy-vendors' ) ); ?>
			</form>

			<h2><?php esc_html_e( 'Owed to readers', 'hexxsy-vendors' ); ?></h2>
			<?php if ( ! $vendors ) : ?>
				<p><?php esc_html_e( 'Nothing yet.', 'hexxsy-vendors' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Reader', 'hexxsy-vendors' ); ?></th>
							<th><?php esc_html_e( 'Stripe payouts', 'hexxsy-vendors' ); ?></th>
							<th><?php esc_html_e( 'On hold', 'hexxsy-vendors' ); ?></th>
							<th><?php esc_html_e( 'Ready to pay', 'hexxsy-vendors' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'hexxsy-vendors' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $vendors as $vendor_id => $totals ) : ?>
						<?php
						$payable = Payout::to_decimal( $totals[ Payout::PAYABLE ] );
						$stripe  = Stripe::is_configured() && Stripe::is_ready( $vendor_id );
						$name    = self::reader_name( $vendor_id );
						?>
						<tr>
							<td><?php echo esc_html( $name ); ?></td>
							<td>
								<?php
								if ( ! Stripe::is_configured() ) {
									esc_html_e( 'n/a', 'hexxsy-vendors' );
								} elseif ( $stripe ) {
									esc_html_e( 'Ready', 'hexxsy-vendors' );
								} elseif ( Stripe::account_id( $vendor_id ) ) {
									esc_html_e( 'Setup not finished', 'hexxsy-vendors' );
								} else {
									esc_html_e( 'Not set up', 'hexxsy-vendors' );
								}
								?>
							</td>
							<td><?php echo wp_kses_post( wc_price( Payout::to_decimal( $totals[ Payout::HELD ] ) ) ); ?></td>
							<td><strong><?php echo wp_kses_post( wc_price( $payable ) ); ?></strong></td>
							<td>
								<?php if ( $totals[ Payout::PAYABLE ] > 0 && $stripe ) : ?>
									<a class="button button-primary" href="<?php echo esc_url( self::pay_url( $vendor_id, $as_of, true ) ); ?>" onclick="return confirm('<?php echo esc_js( sprintf( /* translators: 1: amount, 2: reader */ __( 'Send %1$s to %2$s through Stripe?', 'hexxsy-vendors' ), wp_strip_all_tags( wc_price( $payable ) ), $name ) ); ?>');"><?php esc_html_e( 'Send via Stripe', 'hexxsy-vendors' ); ?></a>
								<?php elseif ( $totals[ Payout::PAYABLE ] > 0 ) : ?>
									<a class="button" href="<?php echo esc_url( self::pay_url( $vendor_id, $as_of, false ) ); ?>" onclick="return confirm('<?php echo esc_js( sprintf( /* translators: 1: amount, 2: reader */ __( 'Record %1$s as paid to %2$s by hand? Only do this once the money has been sent.', 'hexxsy-vendors' ), wp_strip_all_tags( wc_price( $payable ) ), $name ) ); ?>');"><?php esc_html_e( 'Mark paid', 'hexxsy-vendors' ); ?></a>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<?php if ( $overpaid ) : ?>
				<h2><?php esc_html_e( 'Refunded after payout', 'hexxsy-vendors' ); ?></h2>
				<p><?php esc_html_e( 'These customers were refunded after the reader had been paid, so Hexxsy covered the refund. Reverse a Stripe transfer to take it back, or recover it from the reader by hand.', 'hexxsy-vendors' ); ?></p>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Order', 'hexxsy-vendors' ); ?></th>
							<th><?php esc_html_e( 'Reading', 'hexxsy-vendors' ); ?></th>
							<th><?php esc_html_e( 'Reader', 'hexxsy-vendors' ); ?></th>
							<th><?php esc_html_e( 'Overpaid', 'hexxsy-vendors' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'hexxsy-vendors' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $overpaid as list( $order, $item, $vendor_id, $amount ) ) : ?>
						<tr>
							<td><a href="<?php echo esc_url( $order->get_edit_order_url() ); ?>">#<?php echo esc_html( $order->get_order_number() ); ?></a></td>
							<td><?php echo esc_html( $item->get_name() ); ?></td>
							<td><?php echo esc_html( self::reader_name( $vendor_id ) ); ?></td>
							<td><?php echo wp_kses_post( wc_price( Payout::to_decimal( $amount ) ) ); ?></td>
							<td>
								<?php if ( $item->get_meta( Payout::TRANSFER_META ) && Stripe::is_configured() ) : ?>
									<a class="button" href="<?php echo esc_url( self::reverse_url( $item->get_id() ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Take this amount back from the reader\'s Stripe account?', 'hexxsy-vendors' ) ); ?>');"><?php esc_html_e( 'Reverse transfer', 'hexxsy-vendors' ); ?></a>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Result of the last action, passed back in the redirect.
	 */
	private static function render_notice(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display only.
		if ( isset( $_GET['hexxsy_error'] ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html( sanitize_text_field( wp_unslash( $_GET['hexxsy_error'] ) ) ) . '</p></div>';
		}
		if ( isset( $_GET['hexxsy_paid'] ) ) {
			printf(
				'<div class="notice notice-success"><p>%s</p></div>',
				/* translators: %s: amount */
				esc_html( sprintf( __( '%s recorded as paid.', 'hexxsy-vendors' ), wp_strip_all_tags( wc_price( Payout::to_decimal( absint( $_GET['hexxsy_paid'] ) ) ) ) ) )
			);
		}
		if ( isset( $_GET['hexxsy_reversed'] ) ) {
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Transfer reversed.', 'hexxsy-vendors' ) . '</p></div>';
		}
		// phpcs:enable
	}

	/**
	 * Shop name, falling back to the account email for readers without one.
	 *
	 * @param int $vendor_id Vendor.
	 */
	private static function reader_name( int $vendor_id ): string {
		$shop = (string) get_user_meta( $vendor_id, 'hexxsy_shop_name', true );
		if ( '' !== $shop ) {
			return $shop;
		}
		$user = get_userdata( $vendor_id );
		return $user ? $user->user_email : '#' . $vendor_id;
	}

	/**
	 * Signed URL for paying a reader.
	 *
	 * @param int  $vendor_id  Vendor.
	 * @param int  $as_of      Time the page was rendered.
	 * @param bool $via_stripe Stripe transfer, or a payment made by hand.
	 */
	private static function pay_url( int $vendor_id, int $as_of, bool $via_stripe ): string {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action'  => self::ACTION,
					'user_id' => $vendor_id,
					'as_of'   => $as_of,
					'via'     => $via_stripe ? 'stripe' : 'hand',
				),
				admin_url( 'admin-post.php' )
			),
			self::ACTION . '_' . $vendor_id
		);
	}

	/**
	 * Signed URL for reversing an overpaid transfer.
	 *
	 * @param int $item_id Order line.
	 */
	private static function reverse_url( int $item_id ): string {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action'  => self::REVERSE,
					'item_id' => $item_id,
				),
				admin_url( 'admin-post.php' )
			),
			self::REVERSE . '_' . $item_id
		);
	}

	/**
	 * Back to the Payouts page with a result.
	 *
	 * @param array $args Query args.
	 */
	private static function back( array $args ): void {
		wp_safe_redirect( add_query_arg( array_map( 'rawurlencode', $args ), admin_url( 'admin.php?page=' . self::PAGE ) ) );
		exit;
	}

	/**
	 * Pay a reader.
	 */
	public static function handle_pay(): void {
		$vendor_id = isset( $_GET['user_id'] ) ? absint( $_GET['user_id'] ) : 0;

		if ( ! current_user_can( Admin::CAPABILITY ) ) {
			wp_die( esc_html__( 'You are not allowed to pay readers.', 'hexxsy-vendors' ), 403 );
		}
		check_admin_referer( self::ACTION . '_' . $vendor_id );

		$as_of = isset( $_GET['as_of'] ) ? min( time(), absint( $_GET['as_of'] ) ) : 0;
		$via   = isset( $_GET['via'] ) && 'stripe' === sanitize_key( $_GET['via'] );
		if ( ! $vendor_id || ! $as_of ) {
			self::back( array() );
		}
		if ( ! $via && Stripe::is_ready( $vendor_id ) ) {
			// Their account is connected now; don't let a stale page record a hand payment.
			self::back( array( 'hexxsy_error' => __( 'This reader is set up for Stripe payouts now. Reload and use Send via Stripe.', 'hexxsy-vendors' ) ) );
		}

		$result = Payout::pay( $vendor_id, $as_of, $via );
		$args   = array( 'hexxsy_paid' => $result['paid'] );
		if ( '' !== $result['error'] ) {
			$args['hexxsy_error'] = sprintf(
				/* translators: %s: error from Stripe */
				__( 'Stripe stopped the payout: %s Anything already sent is recorded; try again to send the rest.', 'hexxsy-vendors' ),
				$result['error']
			);
		}
		self::back( $args );
	}

	/**
	 * Reverse an overpaid transfer.
	 */
	public static function handle_reverse(): void {
		$item_id = isset( $_GET['item_id'] ) ? absint( $_GET['item_id'] ) : 0;

		if ( ! current_user_can( Admin::CAPABILITY ) ) {
			wp_die( esc_html__( 'You are not allowed to reverse payouts.', 'hexxsy-vendors' ), 403 );
		}
		check_admin_referer( self::REVERSE . '_' . $item_id );

		$item  = $item_id ? \WC_Order_Factory::get_order_item( $item_id ) : false;
		$order = $item ? wc_get_order( $item->get_order_id() ) : false;
		if ( ! $item || ! $order ) {
			self::back( array( 'hexxsy_error' => __( 'That order line could not be found.', 'hexxsy-vendors' ) ) );
		}

		$result = Payout::reverse_overpaid( $order, $item );
		self::back( is_wp_error( $result ) ? array( 'hexxsy_error' => $result->get_error_message() ) : array( 'hexxsy_reversed' => 1 ) );
	}
}
