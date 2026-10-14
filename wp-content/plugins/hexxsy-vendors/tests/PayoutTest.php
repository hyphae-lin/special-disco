<?php
/**
 * Payout ledger tests. These run without WordPress.
 *
 * @package Hexxsy\Vendors
 */

use Hexxsy\Vendors\Payout;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-commission.php';
require_once dirname( __DIR__ ) . '/includes/class-payout.php';

final class PayoutTest extends TestCase {

	const NOW = 1_800_000_000;

	public function test_undelivered_reading_is_held(): void {
		$this->assertSame( Payout::HELD, Payout::state( 0, 0, 3600, true, self::NOW ) );
	}

	public function test_delivered_reading_is_held_until_release(): void {
		$this->assertSame( Payout::HELD, Payout::state( 0, self::NOW + 1, 3600, true, self::NOW ) );
		$this->assertSame( Payout::PAYABLE, Payout::state( 0, self::NOW, 3600, true, self::NOW ) );
	}

	public function test_paid_stays_paid_even_if_refunded_later(): void {
		$this->assertSame( Payout::PAID, Payout::state( self::NOW - 10, self::NOW - 100, 0, false, self::NOW ) );
	}

	public function test_refunded_or_cancelled_order_owes_nothing(): void {
		$this->assertSame( Payout::VOID, Payout::state( 0, self::NOW - 100, 3600, false, self::NOW ) );
		$this->assertSame( Payout::VOID, Payout::state( 0, self::NOW - 100, 0, true, self::NOW ) );
	}

	public function test_share_with_no_refund_matches_the_split(): void {
		// $45.00 at 20%: reader earns $36.00.
		$this->assertSame( 3600, Payout::share_after_refund( 4500, 0, 20 ) );
	}

	public function test_partial_refund_comes_off_before_the_split(): void {
		// $15.00 refunded on a $45.00 reading leaves $30.00; reader gets 80%.
		$this->assertSame( 2400, Payout::share_after_refund( 4500, 1500, 20 ) );
	}

	public function test_owed_back_after_payout_counts_reversals(): void {
		// Paid $36.00, then a full refund: all of it is owed back.
		$this->assertSame( 3600, Payout::owed_back( 3600, 0, 0 ) );
		// Partial refund leaves the line worth $24.00.
		$this->assertSame( 1200, Payout::owed_back( 3600, 0, 2400 ) );
		// Once reversed, nothing more is owed.
		$this->assertSame( 0, Payout::owed_back( 3600, 1200, 2400 ) );
		$this->assertSame( 0, Payout::owed_back( 3600, 0, 3600 ) );
	}

	public function test_full_or_excess_refund_leaves_nothing(): void {
		$this->assertSame( 0, Payout::share_after_refund( 4500, 4500, 20 ) );
		$this->assertSame( 0, Payout::share_after_refund( 4500, 9000, 20 ) );
	}
}
