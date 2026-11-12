<?php
/**
 * Cancelling and refunding readings.
 *
 * @package Hexxsy\Vendors
 */

namespace Hexxsy\Vendors;

/**
 * One way out for every undelivered reading: the line is marked cancelled,
 * the customer's money goes back through the payment gateway, and both
 * sides are told. Used when a customer cancels (inside the cancellation
 * window, or once the reading is late), when a late reading runs out of
 * grace, and when a reader is revoked.
 *
 * A refunded line's reader share drops to nothing in the payout ledger, which
 * reads WooCommerce's refund records.
 *
 * Delivered readings are never refunded from here. Exceptions are made by
 * hand, with WooCommerce's own Refund button on the order.
 */
class Refunds {

	const CANCELLED_META = '_hexxsy_cancelled_at';
	const REASON_META    = '_hexxsy_cancel_reason';
	const FAILED_META    = '_hexxsy_refund_failed';

	const WINDOW_OPTION = 'hexxsy_cancel_window_hours';
	const GRACE_OPTION  = 'hexxsy_late_refund_days';
	const LEAD_OPTION   = 'hexxsy_reminder_days';
	const PAGE_OPTION   = 'hexxsy_refund_policy_page';

	const DEFAULT_WINDOW = 24;
	const DEFAULT_GRACE  = 7;
	const DEFAULT_LEAD   = 1;

	/**
	 * Why a line was cancelled.
	 */
	const WINDOW  = 'window';  // The customer changed their mind in time.
	const LATE    = 'late';    // The customer cancelled a late reading.
	const AUTO    = 'auto';    // Late past the grace period.
	const REVOKED = 'revoked'; // The reader was revoked.

	const ACTION = 'hexxsy_cancel_reading';

	/**
	 * Hook in.
	 */
	public static function init(): void {
		add_action( 'template_redirect', array( __CLASS__, 'handle_cancel' ) );
		add_action( 'woocommerce_before_add_to_cart_button', array( __CLASS__, 'product_note' ), 20 );
		add_shortcode( 'hexxsy_setting', array( __CLASS__, 'shortcode' ) );
	}

	/**
	 * Hours after paying in which a customer can cancel. 0 turns it off.
	 */
	public static function window_hours(): int {
		return max( 0, (int) get_option( self::WINDOW_OPTION, self::DEFAULT_WINDOW ) );
	}

	/**
	 * Days past due before a late reading is refunded without being asked.
	 */
	public static function grace_days(): int {
		return max( 0, (int) get_option( self::GRACE_OPTION, self::DEFAULT_GRACE ) );
	}

	/**
	 * Days before the due date the reader is reminded. 0 turns it off.
	 */
	public static function lead_days(): int {
		return max( 0, (int) get_option( self::LEAD_OPTION, self::DEFAULT_LEAD ) );
	}

	/**
	 * Whether a customer may cancel an undelivered reading now, and why:
	 * LATE once the due date has passed, WINDOW inside the cancellation
	 * window, or '' when they can't.
	 *
	 * @param int $paid         Unix time the order was paid, or 0.
	 * @param int $due          Unix time the reading is due, or 0.
	 * @param int $now          Current Unix time.
	 * @param int $window_hours Cancellation window in hours.
	 */
	public static function cancel_reason( int $paid, int $due, int $now, int $window_hours ): string {
		if ( ! $paid ) {
			return '';
		}
		if ( $due && $now > $due ) {
			return self::LATE;
		}
		if ( $window_hours > 0 && $now <= $paid + $window_hours * 3600 ) {
			return self::WINDOW;
		}
		return '';
	}

	/**
	 * Whether a line is cancelled: marked so here, or refunded in full by
	 * hand in WooCommerce.
	 *
	 * @param \WC_Order      $order Order.
	 * @param \WC_Order_Item $item  Order line.
	 */
	public static function is_cancelled( $order, $item ): bool {
		if ( $item->get_meta( self::CANCELLED_META ) ) {
			return true;
		}
		$total = (float) $item->get_total();
		return $total > 0 && (float) $order->get_total_refunded_for_item( $item->get_id() ) >= $total;
	}

	/**
	 * Why the customer could cancel this line right now, or ''.
	 *
	 * @param \WC_Order      $order Order.
	 * @param \WC_Order_Item $item  Order line.
	 */
	public static function customer_reason( $order, $item ): string {
		if ( 'processing' !== $order->get_status() || ! Delivery::is_written( $item ) || Delivery::delivered_at( $item ) || self::is_cancelled( $order, $item ) ) {
			return '';
		}
		$paid = $order->get_date_paid();
		$due  = Delivery::due_date( $order, $item );
		return self::cancel_reason( $paid ? $paid->getTimestamp() : 0, $due ? $due->getTimestamp() : 0, time(), self::window_hours() );
	}

	/**
	 * A reader's lines on paid orders that are neither delivered nor
	 * cancelled, oldest first. With no reader, everyone's.
	 *
	 * @param int $vendor_id Reader, or 0 for all.
	 * @return array<int, array{0:\WC_Order,1:\WC_Order_Item_Product}>
	 */
	public static function open_lines( int $vendor_id = 0 ): array {
		$meta = array( 'key' => Order_Split::ORDER_VENDOR_META );
		if ( $vendor_id ) {
			$meta['value'] = $vendor_id;
		} else {
			$meta['compare'] = 'EXISTS';
		}

		$orders = wc_get_orders(
			array(
				'limit'      => -1,
				'orderby'    => 'date',
				'order'      => 'ASC',
				'status'     => array( 'wc-processing' ),
				'meta_query' => array( $meta ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			)
		);

		$lines = array();
		foreach ( $orders as $order ) {
			foreach ( $order->get_items() as $item ) {
				$owner = (int) $item->get_meta( Order_Split::VENDOR_META );
				if ( ! $owner || ( $vendor_id && $owner !== $vendor_id ) ) {
					continue;
				}
				if ( '' === (string) $item->get_meta( Order_Split::FORMAT_META ) || Delivery::delivered_at( $item ) || self::is_cancelled( $order, $item ) ) {
					continue;
				}
				$lines[] = array( $order, $item );
			}
		}
		return $lines;
	}

	/**
	 * Cancel an undelivered line and refund what the customer paid for it.
	 *
	 * The line is cancelled whether or not the gateway refund goes through,
	 * so the reader can't deliver something the customer was told is off. If
	 * the gateway refuses, the order is noted and the admin emailed to
	 * refund it by hand.
	 *
	 * @param \WC_Order              $order  Order.
	 * @param \WC_Order_Item_Product $item   Order line.
	 * @param string                 $reason One of the reason constants.
	 * @return bool Whether the line was cancelled by this call.
	 */
	public static function cancel_line( $order, $item, string $reason ): bool {
		if ( Delivery::delivered_at( $item ) || self::is_cancelled( $order, $item ) ) {
			return false;
		}

		$item_id   = $item->get_id();
		$vendor_id = (int) $item->get_meta( Order_Split::VENDOR_META );

		$item->update_meta_data( self::CANCELLED_META, time() );
		$item->update_meta_data( self::REASON_META, $reason );
		$item->save();

		// What's left on the line, with its tax.
		$total  = max( 0, (float) $item->get_total() - (float) $order->get_total_refunded_for_item( $item_id ) );
		$taxes  = $item->get_taxes();
		$tax    = array();
		$amount = $total;
		foreach ( (array) ( $taxes['total'] ?? array() ) as $tax_id => $tax_total ) {
			$left           = max( 0, (float) $tax_total - (float) $order->get_tax_refunded_for_item( $item_id, $tax_id ) );
			$tax[ $tax_id ] = wc_format_decimal( $left );
			$amount        += $left;
		}

		$error = '';
		if ( $amount > 0 ) {
			$refund = wc_create_refund(
				array(
					'amount'         => wc_format_decimal( $amount, wc_get_price_decimals() ),
					'reason'         => self::note_reason( $reason, $item->get_name() ),
					'order_id'       => $order->get_id(),
					'line_items'     => array(
						$item_id => array(
							'qty'          => max( 0, $item->get_quantity() - abs( (int) $order->get_qty_refunded_for_item( $item_id ) ) ),
							'refund_total' => wc_format_decimal( $total ),
							'refund_tax'   => $tax,
						),
					),
					'refund_payment' => true,
					'restock_items'  => false,
				)
			);
			if ( is_wp_error( $refund ) ) {
				$error = $refund->get_error_message();
			}
		}

		// The refund may have changed the order's status and totals.
		$order = wc_get_order( $order->get_id() );

		if ( '' !== $error ) {
			$item->update_meta_data( self::FAILED_META, $error );
			$item->save();
			$order->add_order_note(
				sprintf(
					/* translators: 1: reading name, 2: amount, 3: error from the payment gateway */
					__( '"%1$s" was cancelled, but the refund of %2$s did not go through: %3$s Refund it by hand.', 'hexxsy-vendors' ),
					$item->get_name(),
					self::money( $amount, $order ),
					$error
				)
			);
			wp_mail(
				get_option( 'admin_email' ),
				/* translators: %s: order number */
				sprintf( __( 'Hexxsy: refund order #%s by hand', 'hexxsy-vendors' ), $order->get_order_number() ),
				sprintf(
					/* translators: 1: reading name, 2: order number, 3: amount, 4: error, 5: order edit URL */
					__( "\"%1\$s\" on order #%2\$s was cancelled, and the customer was told %3\$s is on its way back. The payment gateway refused the refund:\n\n%4\$s\n\nRefund it by hand here:\n%5\$s", 'hexxsy-vendors' ),
					$item->get_name(),
					$order->get_order_number(),
					self::money( $amount, $order ),
					$error,
					$order->get_edit_order_url()
				)
			);
		} else {
			$order->add_order_note(
				sprintf(
					/* translators: 1: reading name, 2: amount, 3: why */
					__( '"%1$s" cancelled and %2$s refunded. %3$s', 'hexxsy-vendors' ),
					$item->get_name(),
					self::money( $amount, $order ),
					self::note_reason( $reason, $item->get_name() )
				)
			);
		}

		self::tell_customer( $order, $item, $reason, $amount );
		if ( self::REVOKED !== $reason ) {
			// A revoked reader gets one email about the whole thing instead.
			self::tell_reader( $order, $item, $reason );
		}

		Delivery::maybe_complete( $order );
		Vendor::settle_closing( $vendor_id );

		return true;
	}

	/**
	 * Admin-facing reason, for the refund record and order note.
	 *
	 * @param string $reason Reason constant.
	 * @param string $name   Reading name.
	 */
	private static function note_reason( string $reason, string $name ): string {
		switch ( $reason ) {
			case self::WINDOW:
				/* translators: %s: reading name */
				return sprintf( __( 'Customer cancelled "%s" inside the cancellation window.', 'hexxsy-vendors' ), $name );
			case self::LATE:
				/* translators: %s: reading name */
				return sprintf( __( 'Customer cancelled "%s" because it was late.', 'hexxsy-vendors' ), $name );
			case self::AUTO:
				/* translators: %s: reading name */
				return sprintf( __( '"%s" was late past the grace period.', 'hexxsy-vendors' ), $name );
			default:
				/* translators: %s: reading name */
				return sprintf( __( 'The reader of "%s" was revoked.', 'hexxsy-vendors' ), $name );
		}
	}

	/**
	 * An amount as plain text, for emails and notes.
	 *
	 * @param float     $amount Amount.
	 * @param \WC_Order $order  Order, for its currency.
	 */
	private static function money( float $amount, $order ): string {
		return html_entity_decode( wp_strip_all_tags( wc_price( $amount, array( 'currency' => $order->get_currency() ) ) ), ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * Placeholder values shared by the emails about one order line.
	 *
	 * @param \WC_Order      $order Order.
	 * @param \WC_Order_Item $item  Order line.
	 * @return array<string,string>
	 */
	public static function line_vars( $order, $item ): array {
		$first = $order->get_billing_first_name();
		$due   = Delivery::due_date( $order, $item );
		return array(
			'name'        => $first ? $first : __( 'Hello', 'hexxsy-vendors' ),
			'reading'     => $item->get_name(),
			'reader'      => Delivery::shop_name( (int) $item->get_meta( Order_Split::VENDOR_META ) ),
			'order'       => $order->get_order_number(),
			'due'         => $due ? wp_date( get_option( 'date_format' ), $due->getTimestamp() ) : '',
			'refund_date' => $due ? wp_date( get_option( 'date_format' ), $due->getTimestamp() + self::grace_days() * DAY_IN_SECONDS ) : '',
			'hours'       => (string) self::window_hours(),
			'days'        => (string) self::grace_days(),
		);
	}

	/**
	 * Email the customer that their reading is cancelled and refunded.
	 *
	 * @param \WC_Order      $order  Order.
	 * @param \WC_Order_Item $item   Order line.
	 * @param string         $reason Reason constant.
	 * @param float          $amount Amount refunded.
	 */
	private static function tell_customer( $order, $item, string $reason, float $amount ): void {
		$vars           = self::line_vars( $order, $item );
		$vars['reason'] = Emails::text( 'refund', 'reason_' . $reason, $vars );
		$vars['amount'] = self::money( $amount, $order );
		Emails::send( 'refund', $order->get_billing_email(), $vars );
	}

	/**
	 * Email the reader that an order of theirs is off.
	 *
	 * @param \WC_Order      $order  Order.
	 * @param \WC_Order_Item $item   Order line.
	 * @param string         $reason Reason constant.
	 */
	private static function tell_reader( $order, $item, string $reason ): void {
		$reader = get_userdata( (int) $item->get_meta( Order_Split::VENDOR_META ) );
		if ( ! $reader ) {
			return;
		}
		$vars           = self::line_vars( $order, $item );
		$vars['reason'] = Emails::text( 'reader_cancelled', 'reason_' . $reason, $vars );
		Emails::send( 'reader_cancelled', $reader->user_email, $vars );
	}

	/**
	 * A customer cancelling from My readings.
	 */
	public static function handle_cancel(): void {
		if ( empty( $_POST[ self::ACTION ] ) || ! is_user_logged_in() ) {
			return;
		}
		$item_id = absint( $_POST[ self::ACTION ] );
		$back    = wc_get_account_endpoint_url( Customer::ENDPOINT );

		if ( ! isset( $_POST['_hexxsy_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_hexxsy_nonce'] ), self::ACTION . '_' . $item_id ) ) {
			wc_add_notice( __( 'Your session expired. Please try again.', 'hexxsy-vendors' ), 'error' );
			wp_safe_redirect( $back );
			exit;
		}

		$item  = \WC_Order_Factory::get_order_item( $item_id );
		$order = $item ? wc_get_order( $item->get_order_id() ) : false;

		if ( ! $item || ! $order || $order->get_customer_id() !== get_current_user_id() ) {
			wc_add_notice( __( 'That reading could not be found.', 'hexxsy-vendors' ), 'error' );
		} else {
			$reason = self::customer_reason( $order, $item );
			if ( '' === $reason ) {
				wc_add_notice( __( 'This reading can no longer be cancelled.', 'hexxsy-vendors' ), 'error' );
			} else {
				self::cancel_line( $order, $item, $reason );
				wc_add_notice( __( 'Your reading is cancelled, and your refund is on its way. Banks take a few days over it.', 'hexxsy-vendors' ) );
			}
		}

		wp_safe_redirect( $back );
		exit;
	}

	/**
	 * The cancel button for one reading in My readings, when it applies.
	 *
	 * @param \WC_Order      $order Order.
	 * @param \WC_Order_Item $item  Order line.
	 */
	public static function render_cancel_button( $order, $item ): void {
		if ( '' === self::customer_reason( $order, $item ) ) {
			return;
		}
		?>
		<form method="post" style="margin-top:.5em">
			<?php wp_nonce_field( self::ACTION . '_' . $item->get_id(), '_hexxsy_nonce' ); ?>
			<button type="submit" class="button" name="<?php echo esc_attr( self::ACTION ); ?>" value="<?php echo esc_attr( (string) $item->get_id() ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Cancel this reading and get your money back?', 'hexxsy-vendors' ) ); ?>');"><?php esc_html_e( 'Cancel for a full refund', 'hexxsy-vendors' ); ?></button>
		</form>
		<?php
	}

	/**
	 * The short version of the policy, above the button on a written
	 * reading's page.
	 */
	public static function product_note(): void {
		global $product;
		if ( ! $product instanceof \WC_Product || Readings::WRITTEN !== Readings::format( $product ) ) {
			return;
		}
		$vars = array(
			'hours' => (string) self::window_hours(),
			'days'  => (string) self::grace_days(),
		);
		echo '<p class="hexxsy-line hexxsy-reading-policy">' . Lines::html( 'reading_policy', $vars, Ledger::page_url( self::PAGE_OPTION ) ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in Lines::html.
	}

	/**
	 * [hexxsy_setting key="…"] prints a policy number, so pages that quote
	 * one never disagree with the setting. Keys: cancel_hours,
	 * late_refund_days, hold_days.
	 *
	 * @param array|string $atts Attributes.
	 */
	public static function shortcode( $atts ): string {
		$atts   = shortcode_atts( array( 'key' => '' ), $atts, 'hexxsy_setting' );
		$values = array(
			'cancel_hours'     => self::window_hours(),
			'late_refund_days' => self::grace_days(),
			'hold_days'        => Payout::hold_days(),
		);
		$key    = sanitize_key( $atts['key'] );
		return isset( $values[ $key ] ) ? (string) $values[ $key ] : '';
	}
}
