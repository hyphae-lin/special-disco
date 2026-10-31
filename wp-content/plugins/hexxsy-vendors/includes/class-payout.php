<?php
/**
 * Payout ledger: Hexxsy holds the customer's payment and pays readers later.
 *
 * @package Hexxsy\Vendors
 */

namespace Hexxsy\Vendors;

/**
 * Tracks each order line's reader share through held, payable and paid.
 *
 * The customer pays Hexxsy. A written reading's share is held until the
 * reader delivers it, then for a further hold period so a complaint can be
 * refunded before the reader is paid. Live calls stay held until booking
 * exists. Payable shares go out as Stripe transfers to readers who have set
 * up payouts, or are marked paid by hand for anyone who hasn't.
 *
 * Refunds come off the line before the split, at the rate stamped at
 * purchase, so a partial refund shrinks both the commission and the share.
 * Welcome-offer lines are split on the undiscounted price (see Welcome_Offer).
 */
class Payout {

	const HOLD_OPTION       = 'hexxsy_payout_hold_days';
	const DEFAULT_HOLD_DAYS = 7;

	/**
	 * Line item meta. Release time is stamped at delivery, so changing the
	 * hold period later never moves an earlier reading's date.
	 */
	const RELEASE_META     = '_hexxsy_payout_release_at';
	const PAID_META        = '_hexxsy_payout_paid_at';
	const PAID_AMOUNT_META = '_hexxsy_payout_paid_amount';
	const TRANSFER_META    = '_hexxsy_payout_transfer_id';
	const REVERSED_META    = '_hexxsy_payout_reversed';

	const HELD    = 'held';
	const PAYABLE = 'payable';
	const PAID    = 'paid';
	const VOID    = 'void';

	/**
	 * Order statuses where Hexxsy has the customer's money.
	 */
	const PAID_STATUSES = array( 'processing', 'completed' );

	/**
	 * State of one reader share.
	 *
	 * @param int  $paid_at    When it was marked paid, or 0.
	 * @param int  $release_at When the hold ends, or 0 if not delivered.
	 * @param int  $share      Reader share in minor units, after refunds.
	 * @param bool $order_paid Whether the order still counts as paid.
	 * @param int  $now        Current Unix time.
	 */
	public static function state( int $paid_at, int $release_at, int $share, bool $order_paid, int $now ): string {
		if ( $paid_at ) {
			return self::PAID;
		}
		if ( ! $order_paid || $share <= 0 ) {
			return self::VOID;
		}
		if ( $release_at && $now >= $release_at ) {
			return self::PAYABLE;
		}
		return self::HELD;
	}

	/**
	 * Reader share in minor units once refunds come off the line.
	 *
	 * @param int   $line     Line total in minor units.
	 * @param int   $refunded Amount refunded on the line, in minor units.
	 * @param float $rate     Commission rate stamped at purchase.
	 */
	public static function share_after_refund( int $line, int $refunded, float $rate ): int {
		return Commission::split( max( 0, $line - $refunded ), $rate )['vendor'];
	}

	/**
	 * What the reader still owes back after a refund that came after their
	 * payout, in minor units.
	 *
	 * @param int $paid     Amount paid out.
	 * @param int $reversed Amount already taken back.
	 * @param int $current  Share the line is worth now.
	 */
	public static function owed_back( int $paid, int $reversed, int $current ): int {
		return max( 0, $paid - $reversed - $current );
	}

	/**
	 * Minor units from a stored decimal amount.
	 *
	 * @param mixed $amount Decimal string or number.
	 */
	public static function to_minor( $amount ): int {
		return (int) round( (float) $amount * ( 10 ** wc_get_price_decimals() ) );
	}

	/**
	 * Days a delivered reading's share is held before it's payable.
	 */
	public static function hold_days(): int {
		return max( 0, (int) get_option( self::HOLD_OPTION, self::DEFAULT_HOLD_DAYS ) );
	}

	/**
	 * When the hold ends for a reading delivered at $delivered_at.
	 *
	 * @param int $delivered_at Unix time.
	 */
	public static function release_time( int $delivered_at ): int {
		return $delivered_at + self::hold_days() * DAY_IN_SECONDS;
	}

	/**
	 * Reader share on a line, in minor units, after refunds.
	 *
	 * @param \WC_Order              $order Order.
	 * @param \WC_Order_Item_Product $item  Order line.
	 */
	public static function line_share( $order, $item ): int {
		$factor   = 10 ** wc_get_price_decimals();
		$line     = (int) round( (float) $item->get_total() * $factor );
		$refunded = (int) round( abs( (float) $order->get_total_refunded_for_item( $item->get_id() ) ) * $factor );
		$rate     = (float) $item->get_meta( Order_Split::RATE_META );

		if ( 'yes' === $item->get_meta( Welcome_Offer::LINE_META ) ) {
			// The house absorbed the discount: the reader's share comes from the full price.
			$undiscounted = (int) round( (float) $item->get_meta( Welcome_Offer::UNDISCOUNTED_META ) * $factor );
			return Welcome_Offer::share_after_refund( $undiscounted, $line, $refunded, $rate );
		}

		return self::share_after_refund( $line, $refunded, $rate );
	}

	/**
	 * State of a line's reader share.
	 *
	 * @param \WC_Order              $order Order.
	 * @param \WC_Order_Item_Product $item  Order line.
	 * @param int|null               $now   Unix time, defaults to now.
	 */
	public static function line_state( $order, $item, ?int $now = null ): string {
		$release = (int) $item->get_meta( self::RELEASE_META );
		if ( ! $release && Delivery::delivered_at( $item ) ) {
			// Delivered before the ledger existed.
			$release = self::release_time( Delivery::delivered_at( $item ) );
		}

		return self::state(
			(int) $item->get_meta( self::PAID_META ),
			$release,
			self::line_share( $order, $item ),
			in_array( $order->get_status(), self::PAID_STATUSES, true ),
			$now ?? time()
		);
	}

	/**
	 * Amount for display, from minor units.
	 *
	 * @param int $minor Minor units.
	 */
	public static function to_decimal( int $minor ): string {
		$decimals = wc_get_price_decimals();
		return wc_format_decimal( $minor / ( 10 ** $decimals ), $decimals );
	}

	/**
	 * Every vendor line on orders that ever held money, optionally for one
	 * vendor. Reads all records, not a recent slice.
	 *
	 * @param int $vendor_id Vendor, or 0 for all.
	 * @return array<int, array{0:\WC_Order,1:\WC_Order_Item_Product}>
	 */
	public static function lines( int $vendor_id = 0 ): array {
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
				'status'     => array( 'wc-processing', 'wc-completed', 'wc-refunded', 'wc-cancelled' ),
				'meta_query' => array( $meta ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			)
		);

		$lines = array();
		foreach ( $orders as $order ) {
			foreach ( $order->get_items() as $item ) {
				$owner = (int) $item->get_meta( Order_Split::VENDOR_META );
				if ( $owner && ( ! $vendor_id || $owner === $vendor_id ) ) {
					$lines[] = array( $order, $item );
				}
			}
		}
		return $lines;
	}

	/**
	 * Pay a vendor's payable lines, by Stripe transfer or by hand. Only lines
	 * whose hold ended by $as_of are included, so the total matches what the
	 * admin was shown. A Stripe error stops the run; lines already sent stay
	 * recorded, and running it again picks up the rest.
	 *
	 * @param int  $vendor_id  Vendor.
	 * @param int  $as_of      Unix time the admin's page was rendered.
	 * @param bool $via_stripe Send transfers, rather than record a payment made by hand.
	 * @return array{paid:int,error:string} Minor units paid, and any error.
	 */
	public static function pay( int $vendor_id, int $as_of, bool $via_stripe ): array {
		$total = 0;
		foreach ( self::lines( $vendor_id ) as list( $order, $item ) ) {
			if ( self::PAYABLE !== self::line_state( $order, $item, $as_of ) ) {
				continue;
			}
			$share       = self::line_share( $order, $item );
			$transfer_id = '';
			if ( $via_stripe ) {
				$transfer_id = Stripe::transfer( $order, $item, $share );
				if ( is_wp_error( $transfer_id ) ) {
					return array(
						'paid'  => $total,
						'error' => $transfer_id->get_error_message(),
					);
				}
			}
			self::record_paid( $order, $item, $share, $transfer_id );
			$total += $share;
		}
		return array(
			'paid'  => $total,
			'error' => '',
		);
	}

	/**
	 * Stamp a line paid and note it on the order.
	 *
	 * @param \WC_Order              $order       Order.
	 * @param \WC_Order_Item_Product $item        Order line.
	 * @param int                    $share       Minor units paid.
	 * @param string                 $transfer_id Stripe transfer, or '' if paid by hand.
	 */
	private static function record_paid( $order, $item, int $share, string $transfer_id ): void {
		$item->update_meta_data( self::PAID_META, time() );
		$item->update_meta_data( self::PAID_AMOUNT_META, self::to_decimal( $share ) );
		if ( '' !== $transfer_id ) {
			$item->update_meta_data( self::TRANSFER_META, $transfer_id );
		}
		$item->save();

		$amount = wp_strip_all_tags( wc_price( self::to_decimal( $share ), array( 'currency' => $order->get_currency() ) ) );
		$order->add_order_note(
			'' !== $transfer_id
				/* translators: 1: amount, 2: reading name, 3: Stripe transfer ID */
				? sprintf( __( 'Reader share of %1$s for "%2$s" sent by Stripe transfer %3$s.', 'hexxsy-vendors' ), $amount, $item->get_name(), $transfer_id )
				/* translators: 1: amount, 2: reading name */
				: sprintf( __( 'Reader share of %1$s for "%2$s" marked paid by hand.', 'hexxsy-vendors' ), $amount, $item->get_name() )
		);
	}

	/**
	 * Take an overpaid amount back from the reader's Stripe account.
	 *
	 * @param \WC_Order              $order Order.
	 * @param \WC_Order_Item_Product $item  Order line.
	 * @return true|\WP_Error
	 */
	public static function reverse_overpaid( $order, $item ) {
		$transfer_id = (string) $item->get_meta( self::TRANSFER_META );
		$amount      = self::overpaid( $order, $item );
		if ( '' === $transfer_id || $amount <= 0 ) {
			return new \WP_Error( 'hexxsy_payout', __( 'Nothing to reverse on this line.', 'hexxsy-vendors' ) );
		}
		$reversed = self::to_minor( $item->get_meta( self::REVERSED_META ) );
		$result   = Stripe::reverse( $transfer_id, $amount, $reversed );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$item->update_meta_data( self::REVERSED_META, self::to_decimal( $reversed + $amount ) );
		$item->save();
		$order->add_order_note(
			sprintf(
				/* translators: 1: amount, 2: reading name */
				__( 'Reversed %1$s of the reader share for "%2$s" after a refund.', 'hexxsy-vendors' ),
				wp_strip_all_tags( wc_price( self::to_decimal( $amount ), array( 'currency' => $order->get_currency() ) ) ),
				$item->get_name()
			)
		);
		return true;
	}

	/**
	 * Amount refunded after the share was paid out, less anything already
	 * reversed, in minor units: what Hexxsy still needs back from the reader.
	 *
	 * @param \WC_Order              $order Order.
	 * @param \WC_Order_Item_Product $item  Order line.
	 */
	public static function overpaid( $order, $item ): int {
		if ( ! $item->get_meta( self::PAID_META ) ) {
			return 0;
		}
		$current = in_array( $order->get_status(), self::PAID_STATUSES, true ) ? self::line_share( $order, $item ) : 0;
		return self::owed_back(
			self::to_minor( $item->get_meta( self::PAID_AMOUNT_META ) ),
			self::to_minor( $item->get_meta( self::REVERSED_META ) ),
			$current
		);
	}
}
