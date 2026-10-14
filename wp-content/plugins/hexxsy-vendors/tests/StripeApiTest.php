<?php
/**
 * Stripe helper tests. These run without WordPress.
 *
 * @package Hexxsy\Vendors
 */

use Hexxsy\Vendors\Stripe_Api;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-stripe-api.php';

final class StripeApiTest extends TestCase {

	const SECRET  = 'whsec_test';
	const PAYLOAD = '{"type":"checkout.session.completed"}';
	const NOW     = 1760000000;

	private function header( int $time, string $secret = self::SECRET ): string {
		return 't=' . $time . ',v1=' . hash_hmac( 'sha256', $time . '.' . self::PAYLOAD, $secret );
	}

	public function test_valid_signature_passes(): void {
		$this->assertSame( true, Stripe_Api::verify_signature( self::PAYLOAD, $this->header( self::NOW ), self::SECRET, self::NOW ) );
	}

	public function test_wrong_secret_fails(): void {
		$this->assertSame( false, Stripe_Api::verify_signature( self::PAYLOAD, $this->header( self::NOW, 'whsec_other' ), self::SECRET, self::NOW ) );
	}

	public function test_tampered_body_fails(): void {
		$this->assertSame( false, Stripe_Api::verify_signature( '{"type":"tampered"}', $this->header( self::NOW ), self::SECRET, self::NOW ) );
	}

	public function test_old_signature_fails(): void {
		$this->assertSame( false, Stripe_Api::verify_signature( self::PAYLOAD, $this->header( self::NOW - 301 ), self::SECRET, self::NOW ) );
	}

	public function test_missing_secret_or_header_fails(): void {
		$this->assertSame( false, Stripe_Api::verify_signature( self::PAYLOAD, $this->header( self::NOW ), '', self::NOW ) );
		$this->assertSame( false, Stripe_Api::verify_signature( self::PAYLOAD, '', self::SECRET, self::NOW ) );
	}

	public function test_any_matching_v1_signature_passes(): void {
		// Stripe sends two v1 signatures while a secret is being rolled.
		$header = 't=' . self::NOW . ',v1=deadbeef,' . explode( ',', $this->header( self::NOW ) )[1];
		$this->assertSame( true, Stripe_Api::verify_signature( self::PAYLOAD, $header, self::SECRET, self::NOW ) );
	}

	public function test_to_minor_rounds_to_cents(): void {
		$this->assertSame( 1499, Stripe_Api::to_minor( '14.99' ) );
		$this->assertSame( 1200, Stripe_Api::to_minor( 11.999 ) );
		$this->assertSame( 3200, Stripe_Api::to_minor( 32 ) );
	}
}
