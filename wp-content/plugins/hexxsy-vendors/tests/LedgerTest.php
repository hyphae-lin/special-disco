<?php
/**
 * Ledger tests: seats and invite checks. These run without WordPress.
 *
 * @package Hexxsy\Vendors
 */

use Hexxsy\Vendors\Ledger;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-ledger.php';

final class LedgerTest extends TestCase {

	const NOW = 1_800_000_000;

	private function invite( array $extra = array() ): array {
		return array_merge(
			array(
				'email'   => 'Reader@Example.com',
				'created' => self::NOW - 100,
				'expires' => self::NOW + 1000,
			),
			$extra
		);
	}

	public function test_seats_count_readers_applications_and_open_invites(): void {
		$this->assertSame( 12, Ledger::seats_free( 12, 0, 0, 0 ) );
		$this->assertSame( 3, Ledger::seats_free( 12, 7, 1, 1 ) );
		$this->assertSame( 0, Ledger::seats_free( 12, 10, 1, 1 ) );
		// Lowering seats below what's taken never goes negative.
		$this->assertSame( 0, Ledger::seats_free( 5, 7, 0, 0 ) );
	}

	public function test_valid_invite_matches_email_ignoring_case(): void {
		$this->assertSame( Ledger::VALID, Ledger::invite_state( $this->invite(), ' reader@example.COM ', self::NOW ) );
	}

	public function test_invite_states(): void {
		$this->assertSame( Ledger::MISSING, Ledger::invite_state( null, 'reader@example.com', self::NOW ) );
		$this->assertSame( Ledger::WRONG_EMAIL, Ledger::invite_state( $this->invite(), 'someone@example.com', self::NOW ) );
		$this->assertSame( Ledger::EXPIRED, Ledger::invite_state( $this->invite( array( 'expires' => self::NOW ) ), 'reader@example.com', self::NOW ) );
		$this->assertSame( Ledger::USED, Ledger::invite_state( $this->invite( array( 'used_by' => 5 ) ), 'reader@example.com', self::NOW ) );
		$this->assertSame( Ledger::CANCELLED, Ledger::invite_state( $this->invite( array( 'cancelled' => self::NOW ) ), 'reader@example.com', self::NOW ) );
	}

	public function test_only_unused_unexpired_invites_hold_a_seat(): void {
		$this->assertTrue( Ledger::is_open( $this->invite(), self::NOW ) );
		$this->assertFalse( Ledger::is_open( $this->invite( array( 'expires' => self::NOW - 1 ) ), self::NOW ) );
		$this->assertFalse( Ledger::is_open( $this->invite( array( 'used_by' => 5 ) ), self::NOW ) );
		$this->assertFalse( Ledger::is_open( $this->invite( array( 'cancelled' => self::NOW ) ), self::NOW ) );
	}

	public function test_only_the_hash_of_a_token_is_stored(): void {
		$key = Ledger::key( 'abc123' );
		$this->assertSame( 64, strlen( $key ) );
		$this->assertStringNotContainsString( 'abc123', $key );
		$this->assertSame( $key, Ledger::key( 'abc123' ) );
	}
}
