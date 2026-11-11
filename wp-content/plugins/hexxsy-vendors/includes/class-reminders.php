<?php
/**
 * Due-date reminders and the late-reading clock.
 *
 * @package Hexxsy\Vendors
 */

namespace Hexxsy\Vendors;

/**
 * Once an hour, looks at every paid, undelivered written reading:
 *
 * - Shortly before it's due, the reader is reminded.
 * - Once it's late, the reader and the customer are both told, and the
 *   customer can cancel for a refund from My readings.
 * - If it's still undelivered when the grace period ends, it's cancelled and
 *   refunded.
 *
 * Each email goes once; the line is stamped when it's sent.
 */
class Reminders {

	const HOOK = 'hexxsy_reminders';

	const SOON_META = '_hexxsy_reminded_soon';
	const LATE_META = '_hexxsy_reminded_late';

	const NONE   = '';
	const SOON   = 'soon';
	const LATE   = 'late';
	const REFUND = 'refund';

	const DAY = 86400;

	/**
	 * Hook in.
	 */
	public static function init(): void {
		add_action( self::HOOK, array( __CLASS__, 'run' ) );
		add_action( 'init', array( __CLASS__, 'schedule' ) );
	}

	/**
	 * Keep the hourly check scheduled.
	 */
	public static function schedule(): void {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + 300, 'hourly', self::HOOK );
		}
	}

	/**
	 * Where an undelivered reading stands against its due date.
	 *
	 * @param int $due        Unix time it's due, or 0.
	 * @param int $now        Current Unix time.
	 * @param int $lead_days  Days before due to remind the reader. 0 is never.
	 * @param int $grace_days Days past due before it's refunded.
	 * @param int $turnaround Days the reader was given. A reminder is skipped
	 *                        when it would land on the day of the order.
	 */
	public static function stage( int $due, int $now, int $lead_days, int $grace_days, int $turnaround ): string {
		if ( ! $due ) {
			return self::NONE;
		}
		if ( $now > $due ) {
			return $now >= $due + $grace_days * self::DAY ? self::REFUND : self::LATE;
		}
		if ( $lead_days > 0 && $turnaround > $lead_days && $now >= $due - $lead_days * self::DAY ) {
			return self::SOON;
		}
		return self::NONE;
	}

	/**
	 * The hourly check.
	 */
	public static function run(): void {
		$now = time();
		foreach ( Refunds::open_lines() as list( $order, $item ) ) {
			if ( ! Delivery::is_written( $item ) ) {
				continue;
			}
			$due   = Delivery::due_date( $order, $item );
			$stage = self::stage(
				$due ? $due->getTimestamp() : 0,
				$now,
				Refunds::lead_days(),
				Refunds::grace_days(),
				(int) $item->get_meta( Order_Split::TURNAROUND_META )
			);

			if ( self::REFUND === $stage ) {
				Refunds::cancel_line( $order, $item, Refunds::AUTO );
			} elseif ( self::LATE === $stage && ! $item->get_meta( self::LATE_META ) ) {
				self::stamp( $item, self::LATE_META );
				self::send( $order, $item, true, 'reader_late' );
				self::send( $order, $item, false, 'customer_late' );
			} elseif ( self::SOON === $stage && ! $item->get_meta( self::SOON_META ) ) {
				self::stamp( $item, self::SOON_META );
				self::send( $order, $item, true, 'due_soon' );
			}
		}
	}

	/**
	 * Record that an email went, before sending, so a failure can't repeat it.
	 *
	 * @param \WC_Order_Item $item Order line.
	 * @param string         $meta Meta key.
	 */
	private static function stamp( $item, string $meta ): void {
		$item->update_meta_data( $meta, time() );
		$item->save();
	}

	/**
	 * Send one of Hexx's emails about an order line.
	 *
	 * @param \WC_Order      $order     Order.
	 * @param \WC_Order_Item $item      Order line.
	 * @param bool           $to_reader To the reader, rather than the customer.
	 * @param string         $line      Line key, without _subject or _body.
	 */
	private static function send( $order, $item, bool $to_reader, string $line ): void {
		$vars = Refunds::line_vars( $order, $item );
		if ( $to_reader ) {
			$reader = get_userdata( (int) $item->get_meta( Order_Split::VENDOR_META ) );
			if ( ! $reader ) {
				return;
			}
			$to               = $reader->user_email;
			$vars['link_url'] = wc_get_account_endpoint_url( Dashboard::ORDERS );
		} else {
			$to               = $order->get_billing_email();
			$vars['link_url'] = wc_get_account_endpoint_url( Customer::ENDPOINT );
		}
		wp_mail( $to, Lines::text( $line . '_subject', $vars ), Lines::text( $line . '_body', $vars ) );
	}
}
