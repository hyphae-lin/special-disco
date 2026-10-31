<?php
/**
 * Welcome offer tests. These run without WordPress.
 *
 * @package Hexxsy\Vendors
 */

use Hexxsy\Vendors\Welcome_Offer;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-commission.php';
require_once dirname( __DIR__ ) . '/includes/class-welcome-offer.php';

final class WelcomeOfferTest extends TestCase {

	public function test_reader_is_paid_on_the_undiscounted_price(): void {
		// Spec example: $40.00 reading, 15% off, customer pays $34.00.
		$this->assertSame( array( 'commission' => 200, 'vendor' => 3200 ), Welcome_Offer::split( 4000, 3400, 20 ) );
	}

	public function test_reader_share_never_exceeds_what_was_paid(): void {
		// 25% off $40.00: customer pays $30.00, reader would be owed $32.00.
		$this->assertSame( array( 'commission' => 0, 'vendor' => 3000 ), Welcome_Offer::split( 4000, 3000, 20 ) );
	}

	public function test_room_is_the_commission_less_discount_already_given(): void {
		$this->assertSame( 500, Welcome_Offer::room( 2500, 20, 0 ) );
		$this->assertSame( 200, Welcome_Offer::room( 2500, 20, 300 ) );
		$this->assertSame( 0, Welcome_Offer::room( 2500, 20, 900 ) );
		// A reader on 10% has half the room.
		$this->assertSame( 250, Welcome_Offer::room( 2500, 10, 0 ) );
	}

	public function test_refund_takes_the_same_fraction_off_the_undiscounted_price(): void {
		// $40.00 reading, paid $35.00 after $5 off. No refund: reader earns $32.00.
		$this->assertSame( 3200, Welcome_Offer::share_after_refund( 4000, 3500, 0, 20 ) );
		// Half refunded: reader earns half.
		$this->assertSame( 1600, Welcome_Offer::share_after_refund( 4000, 3500, 1750, 20 ) );
		// Full refund: nothing.
		$this->assertSame( 0, Welcome_Offer::share_after_refund( 4000, 3500, 3500, 20 ) );
		$this->assertSame( 0, Welcome_Offer::share_after_refund( 4000, 3500, 9999, 20 ) );
	}

	public function test_safe_minimum(): void {
		$this->assertSame( 2500, Welcome_Offer::safe_minimum( 500, 20 ) );
		$this->assertSame( 5000, Welcome_Offer::safe_minimum( 500, 10 ) );
		$this->assertSame( 3334, Welcome_Offer::safe_minimum( 500, 15 ) );
		$this->assertNull( Welcome_Offer::safe_minimum( 500, 0 ) );
	}

	public function test_recognizes_mailpoet_descriptions(): void {
		$formats = Welcome_Offer::MAILPOET_FORMATS;

		$this->assertTrue( Welcome_Offer::is_mailpoet_description( 'Auto-generated coupon by MailPoet for email: 12', $formats, array() ) );
		$this->assertTrue( Welcome_Offer::is_mailpoet_description( 'Auto Generated coupon by MailPoet for email: 7: Welcome', $formats, array() ) );
		$this->assertTrue( Welcome_Offer::is_mailpoet_description( 'Auto-generated coupon by MailPoet for email: 12', $formats, array( 3, 12 ) ) );
		$this->assertFalse( Welcome_Offer::is_mailpoet_description( 'Auto-generated coupon by MailPoet for email: 12', $formats, array( 3 ) ) );
		$this->assertFalse( Welcome_Offer::is_mailpoet_description( 'Holiday sale', $formats, array() ) );
		$this->assertFalse( Welcome_Offer::is_mailpoet_description( '', $formats, array() ) );
	}

	public function test_parse_ids(): void {
		$this->assertSame( array( 3, 12 ), Welcome_Offer::parse_ids( '3, 12, 3, x, -4' ) );
		$this->assertSame( array(), Welcome_Offer::parse_ids( '' ) );
	}

	/**
	 * Lines are [price in cents, commission rate]. Returns the discount on
	 * each line after WooCommerce's fixed cart logic runs with the guard.
	 */
	private function fixed_cart( int $amount, array $lines ): array {
		$given = array_fill( 0, count( $lines ), 0 );
		$this->apply_fixed_cart( $amount, $lines, $given );
		return $given;
	}

	/**
	 * Mirrors WC_Discounts::apply_coupon_fixed_cart, apply_coupon_fixed_product
	 * and apply_coupon_remainder for quantity-1 lines, with the guard on the
	 * woocommerce_coupon_get_discount_amount filter.
	 */
	private function apply_fixed_cart( int $amount, array $lines, array &$given ): int {
		$total = 0;
		$per   = intdiv( $amount, count( $lines ) );

		if ( $per > 0 ) {
			foreach ( $lines as $i => [ $price, $rate ] ) {
				$allow       = min( $per, Welcome_Offer::room( $price, $rate, $given[ $i ] ) );
				$allow       = min( $price - $given[ $i ], $allow );
				$given[ $i ] += $allow;
				$total       += $allow;
			}
			if ( $total > 0 && $total < $amount ) {
				$total += $this->apply_fixed_cart( $amount - $total, $lines, $given );
			}
		} elseif ( $amount > 0 ) {
			// Remainder: a cent at a time, no filter.
			foreach ( $lines as $i => [ $price ] ) {
				if ( $total >= $amount ) {
					break;
				}
				if ( $price - $given[ $i ] > 0 ) {
					++$given[ $i ];
					++$total;
				}
			}
		}

		return $total;
	}

	public function test_guard_moves_excess_to_lines_with_room(): void {
		// $5 off a $20 reading and a $5 ware at 20%. WooCommerce wants $2.50
		// on each; the ware's commission is $1.00, so $1.50 moves to the reading.
		$given = $this->fixed_cart( 500, array( array( 2000, 20 ), array( 500, 20 ) ) );
		$this->assertSame( 500, array_sum( $given ) );
		$this->assertSame( array( 400, 100 ), $given );
	}

	public function test_full_discount_whenever_minimum_spend_is_safe(): void {
		$carts = array(
			array( array( 2500, 20 ) ),
			array( array( 1500, 20 ), array( 1000, 20 ) ),
			array( array( 2200, 20 ), array( 200, 20 ), array( 100, 20 ) ),
			array( array( 900, 20 ), array( 800, 20 ), array( 800, 20 ) ),
			array( array( 4000, 20 ), array( 199, 20 ) ),
		);
		foreach ( $carts as $n => $cart ) {
			$given = $this->fixed_cart( 500, $cart );
			$this->assertSame( 500, array_sum( $given ), "cart {$n}" );
		}
	}

	public function test_no_line_goes_much_past_its_commission(): void {
		// The 1-cent remainder pass skips the filter, so allow a cent per line.
		$carts = array(
			array( array( 2500, 10 ) ),
			array( array( 1000, 20 ), array( 1000, 10 ), array( 501, 20 ) ),
			array( array( 333, 20 ), array( 333, 20 ), array( 334, 20 ) ),
		);
		foreach ( $carts as $n => $cart ) {
			$given = $this->fixed_cart( 500, $cart );
			foreach ( $cart as $i => [ $price, $rate ] ) {
				$commission = intdiv( $price * (int) ( $rate * 100 ), 10000 ) + 1;
				$this->assertLessThanOrEqual( $commission + 1, $given[ $i ], "cart {$n} line {$i}" );
			}
		}
	}

	public function test_low_rate_reader_gets_a_smaller_discount_not_a_loss(): void {
		// One $25 reading from a reader on 10%: only $2.50 fits.
		$this->assertSame( array( 250 ), $this->fixed_cart( 500, array( array( 2500, 10 ) ) ) );
	}
}
