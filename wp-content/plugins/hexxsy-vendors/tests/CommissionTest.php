<?php
/**
 * Commission math tests. These run without WordPress.
 *
 * @package Hexxsy\Vendors
 */

use Hexxsy\Vendors\Commission;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-commission.php';

final class CommissionTest extends TestCase {

	public function test_twenty_percent_of_round_amount(): void {
		$this->assertSame( array( 'commission' => 800, 'vendor' => 3200 ), Commission::split( 4000, 20 ) );
	}

	public function test_parts_always_add_up_to_the_total(): void {
		foreach ( array( 1, 7, 999, 1499, 3333, 100001 ) as $amount ) {
			foreach ( array( 0, 12.5, 20, 33.33, 100 ) as $rate ) {
				$split = Commission::split( $amount, $rate );
				$this->assertSame( $amount, $split['commission'] + $split['vendor'], "amount {$amount} at {$rate}%" );
			}
		}
	}

	public function test_commission_rounds_to_nearest_cent(): void {
		// 20% of $14.99 is 299.8 cents.
		$this->assertSame( array( 'commission' => 300, 'vendor' => 1199 ), Commission::split( 1499, 20 ) );
	}

	public function test_rate_is_clamped(): void {
		$this->assertSame( array( 'commission' => 1000, 'vendor' => 0 ), Commission::split( 1000, 150 ) );
		$this->assertSame( array( 'commission' => 0, 'vendor' => 1000 ), Commission::split( 1000, -5 ) );
	}
}
