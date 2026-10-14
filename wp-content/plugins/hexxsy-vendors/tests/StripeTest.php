<?php
/**
 * Stripe client tests. These run without WordPress: the few WordPress
 * functions the client calls are stubbed in its namespace below.
 *
 * @package Hexxsy\Vendors
 */

namespace Hexxsy\Vendors {

	/**
	 * Captured request and canned response for the stubs.
	 */
	final class Http_Stub {
		public static array $last     = array();
		public static array $response = array();
	}

	function wp_remote_request( $url, $args ) {
		Http_Stub::$last = array( 'url' => $url ) + $args;
		return Http_Stub::$response;
	}
	function wp_remote_retrieve_body( $response ) {
		return $response['body'];
	}
	function wp_remote_retrieve_response_code( $response ) {
		return $response['code'];
	}
	function is_wp_error( $thing ) {
		return $thing instanceof \WP_Error;
	}
	function __( $text ) {
		return $text;
	}
}

namespace {

	use Hexxsy\Vendors\Http_Stub;
	use Hexxsy\Vendors\Stripe;
	use PHPUnit\Framework\TestCase;

	if ( ! class_exists( 'WP_Error' ) ) {
		class WP_Error {
			public function __construct( public string $code = '', public string $message = '' ) {}
			public function get_error_message(): string {
				return $this->message;
			}
		}
	}

	define( 'HEXXSY_STRIPE_SECRET_KEY', 'sk_test_abc123' );

	require_once dirname( __DIR__ ) . '/includes/class-stripe.php';

	final class StripeTest extends TestCase {

		public function test_mode_comes_from_the_key_prefix(): void {
			$this->assertSame( 'test', Stripe::mode_for_key( 'sk_test_abc' ) );
			$this->assertSame( 'live', Stripe::mode_for_key( 'sk_live_abc' ) );
			$this->assertSame( 'live', Stripe::mode_for_key( 'rk_live_abc' ) );
			$this->assertSame( '', Stripe::mode_for_key( 'pk_live_abc' ) );
			$this->assertSame( '', Stripe::mode_for_key( '' ) );
		}

		public function test_transaction_ids_resolve_to_a_charge_or_intent(): void {
			$this->assertSame( array( 'charge' => 'ch_123' ), Stripe::parse_transaction( 'ch_123' ) );
			$this->assertSame( array( 'charge' => 'py_123' ), Stripe::parse_transaction( 'py_123' ) );
			$this->assertSame( array( 'intent' => 'pi_123' ), Stripe::parse_transaction( 'pi_123' ) );
			$this->assertSame( array(), Stripe::parse_transaction( '' ) );
			$this->assertSame( array(), Stripe::parse_transaction( 'PAYPAL-123' ) );
		}

		public function test_post_sends_bracketed_params_key_and_idempotency(): void {
			Http_Stub::$response = array(
				'code' => 200,
				'body' => '{"id":"tr_1"}',
			);
			$result = Stripe::request(
				'POST',
				'transfers',
				array(
					'amount'   => 3600,
					'metadata' => array( 'order_id' => 42 ),
				),
				'hexxsy-transfer-7-3600'
			);

			$this->assertSame( array( 'id' => 'tr_1' ), $result );
			$this->assertSame( 'https://api.stripe.com/v1/transfers', Http_Stub::$last['url'] );
			$this->assertSame( 'POST', Http_Stub::$last['method'] );
			$this->assertSame( 'amount=3600&metadata%5Border_id%5D=42', Http_Stub::$last['body'] );
			$this->assertSame( 'Bearer sk_test_abc123', Http_Stub::$last['headers']['Authorization'] );
			$this->assertSame( 'hexxsy-transfer-7-3600', Http_Stub::$last['headers']['Idempotency-Key'] );
		}

		public function test_get_puts_params_in_the_query_string(): void {
			Http_Stub::$response = array(
				'code' => 200,
				'body' => '{}',
			);
			Stripe::request( 'GET', 'accounts/acct_1', array( 'expand' => array( 'capabilities' ) ) );

			$this->assertSame( 'https://api.stripe.com/v1/accounts/acct_1?expand%5B0%5D=capabilities', Http_Stub::$last['url'] );
			$this->assertArrayNotHasKey( 'body', Http_Stub::$last );
			$this->assertArrayNotHasKey( 'Idempotency-Key', Http_Stub::$last['headers'] );
		}

		public function test_stripe_errors_come_back_as_wp_errors(): void {
			Http_Stub::$response = array(
				'code' => 400,
				'body' => '{"error":{"message":"Insufficient funds in Stripe account."}}',
			);
			$result = Stripe::request( 'POST', 'transfers', array( 'amount' => 1 ) );

			$this->assertInstanceOf( \WP_Error::class, $result );
			$this->assertSame( 'Insufficient funds in Stripe account.', $result->get_error_message() );
		}

		public function test_unreadable_response_is_an_error(): void {
			Http_Stub::$response = array(
				'code' => 502,
				'body' => '<html>Bad gateway</html>',
			);
			$this->assertInstanceOf( \WP_Error::class, Stripe::request( 'GET', 'balance' ) );
		}
	}
}
