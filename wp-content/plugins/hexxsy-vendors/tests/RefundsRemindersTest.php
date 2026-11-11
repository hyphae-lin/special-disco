<?php
/**
 * Cancellation rules and the late-reading clock. These run without WordPress.
 *
 * @package Hexxsy\Vendors
 */

use Hexxsy\Vendors\Lines;
use Hexxsy\Vendors\Refunds;
use Hexxsy\Vendors\Reminders;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-lines.php';
require_once dirname( __DIR__ ) . '/includes/class-refunds.php';
require_once dirname( __DIR__ ) . '/includes/class-reminders.php';

final class RefundsRemindersTest extends TestCase {

	const PAID = 1_800_000_000;
	const HOUR = 3600;
	const DAY  = 86400;

	public function test_customer_can_cancel_inside_the_window(): void {
		$due = self::PAID + 3 * self::DAY;
		$this->assertSame( Refunds::WINDOW, Refunds::cancel_reason( self::PAID, $due, self::PAID + 60, 24 ) );
		$this->assertSame( Refunds::WINDOW, Refunds::cancel_reason( self::PAID, $due, self::PAID + 24 * self::HOUR, 24 ) );
	}

	public function test_customer_cannot_cancel_between_the_window_and_the_due_date(): void {
		$due = self::PAID + 3 * self::DAY;
		$this->assertSame( '', Refunds::cancel_reason( self::PAID, $due, self::PAID + 24 * self::HOUR + 1, 24 ) );
		$this->assertSame( '', Refunds::cancel_reason( self::PAID, $due, $due, 24 ) );
	}

	public function test_customer_can_cancel_once_late(): void {
		$due = self::PAID + 3 * self::DAY;
		$this->assertSame( Refunds::LATE, Refunds::cancel_reason( self::PAID, $due, $due + 1, 24 ) );
	}

	public function test_late_wins_over_the_window(): void {
		// A one-day turnaround that's already late, still inside a 48-hour window.
		$due = self::PAID + self::DAY;
		$this->assertSame( Refunds::LATE, Refunds::cancel_reason( self::PAID, $due, $due + 60, 48 ) );
	}

	public function test_window_of_zero_turns_cancelling_off_until_late(): void {
		$due = self::PAID + 3 * self::DAY;
		$this->assertSame( '', Refunds::cancel_reason( self::PAID, $due, self::PAID + 60, 0 ) );
		$this->assertSame( Refunds::LATE, Refunds::cancel_reason( self::PAID, $due, $due + 1, 0 ) );
	}

	public function test_unpaid_order_cannot_be_cancelled_here(): void {
		$this->assertSame( '', Refunds::cancel_reason( 0, 0, self::PAID, 24 ) );
	}

	public function test_stage_runs_none_soon_late_refund(): void {
		$due = self::PAID + 5 * self::DAY;
		$this->assertSame( Reminders::NONE, Reminders::stage( $due, self::PAID + self::DAY, 1, 7, 5 ) );
		$this->assertSame( Reminders::SOON, Reminders::stage( $due, $due - self::DAY, 1, 7, 5 ) );
		$this->assertSame( Reminders::SOON, Reminders::stage( $due, $due, 1, 7, 5 ) );
		$this->assertSame( Reminders::LATE, Reminders::stage( $due, $due + 1, 1, 7, 5 ) );
		$this->assertSame( Reminders::LATE, Reminders::stage( $due, $due + 7 * self::DAY - 1, 1, 7, 5 ) );
		$this->assertSame( Reminders::REFUND, Reminders::stage( $due, $due + 7 * self::DAY, 1, 7, 5 ) );
	}

	public function test_no_reminder_when_it_would_land_on_the_order(): void {
		// One day to deliver, reminder one day ahead: that's the moment of purchase.
		$due = self::PAID + self::DAY;
		$this->assertSame( Reminders::NONE, Reminders::stage( $due, self::PAID + 60, 1, 7, 1 ) );
	}

	public function test_reminders_can_be_turned_off(): void {
		$due = self::PAID + 5 * self::DAY;
		$this->assertSame( Reminders::NONE, Reminders::stage( $due, $due - 60, 0, 7, 5 ) );
	}

	public function test_no_grace_refunds_as_soon_as_it_is_late(): void {
		$due = self::PAID + 5 * self::DAY;
		$this->assertSame( Reminders::REFUND, Reminders::stage( $due, $due + 1, 1, 0, 5 ) );
	}

	public function test_no_due_date_means_nothing_happens(): void {
		$this->assertSame( Reminders::NONE, Reminders::stage( 0, self::PAID, 1, 7, 5 ) );
	}

	public function test_every_reason_has_its_lines(): void {
		$lines = Lines::defaults();
		foreach ( array( Refunds::WINDOW, Refunds::LATE, Refunds::AUTO, Refunds::REVOKED ) as $reason ) {
			$this->assertArrayHasKey( 'refund_reason_' . $reason, $lines );
		}
		// A revoked reader gets the revoke email instead of a per-order one.
		foreach ( array( Refunds::WINDOW, Refunds::LATE, Refunds::AUTO ) as $reason ) {
			$this->assertArrayHasKey( 'reader_reason_' . $reason, $lines );
		}
		foreach ( array( 'due_soon', 'reader_late', 'customer_late', 'refund', 'reader_cancelled' ) as $email ) {
			$this->assertArrayHasKey( $email . '_subject', $lines );
			$this->assertArrayHasKey( $email . '_body', $lines );
		}
	}
}
