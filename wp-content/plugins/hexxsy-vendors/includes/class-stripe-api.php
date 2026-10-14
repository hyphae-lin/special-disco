<?php
/**
 * Small Stripe API client.
 *
 * @package Hexxsy\Vendors
 */

namespace Hexxsy\Vendors;

/**
 * Talks to Stripe's REST API with wp_remote_request, so the plugin needs no
 * SDK. Keys come from constants in wp-config.php and are never stored in the
 * database:
 *
 *     define( 'HEXXSY_STRIPE_SECRET_KEY', 'sk_test_...' );
 *     define( 'HEXXSY_STRIPE_WEBHOOK_SECRET', 'whsec_...' );
 */
class Stripe_Api {

	const BASE    = 'https://api.stripe.com/v1/';
	const VERSION = '2024-06-20';

	/**
	 * How old a webhook signature may be, in seconds.
	 */
	const TOLERANCE = 300;

	/**
	 * Whether a secret key is configured.
	 */
	public static function is_configured(): bool {
		return defined( 'HEXXSY_STRIPE_SECRET_KEY' ) && '' !== (string) HEXXSY_STRIPE_SECRET_KEY;
	}

	/**
	 * Whether the configured key is a test-mode key.
	 */
	public static function is_test_mode(): bool {
		return self::is_configured() && str_starts_with( (string) HEXXSY_STRIPE_SECRET_KEY, 'sk_test_' );
	}

	/**
	 * Call the API.
	 *
	 * @param string $method          GET, POST or DELETE.
	 * @param string $path            Path after /v1/, e.g. "accounts".
	 * @param array  $params          Parameters; nested arrays become Stripe's bracket syntax.
	 * @param string $idempotency_key Makes a POST safe to retry.
	 * @return array Decoded response.
	 * @throws \RuntimeException On network or API errors.
	 */
	public static function request( string $method, string $path, array $params = array(), string $idempotency_key = '' ): array {
		if ( ! self::is_configured() ) {
			throw new \RuntimeException( 'Stripe is not configured. Add HEXXSY_STRIPE_SECRET_KEY to wp-config.php.' );
		}

		$url  = self::BASE . ltrim( $path, '/' );
		$args = array(
			'method'  => $method,
			'timeout' => 30,
			'headers' => array(
				'Authorization'  => 'Bearer ' . HEXXSY_STRIPE_SECRET_KEY,
				'Stripe-Version' => self::VERSION,
			),
		);

		if ( 'GET' === $method ) {
			$url = $params ? $url . '?' . http_build_query( $params ) : $url;
		} else {
			$args['body']                    = http_build_query( $params );
			$args['headers']['Content-Type'] = 'application/x-www-form-urlencoded';
			if ( '' !== $idempotency_key ) {
				$args['headers']['Idempotency-Key'] = $idempotency_key;
			}
		}

		$response = wp_remote_request( $url, $args );
		if ( is_wp_error( $response ) ) {
			throw new \RuntimeException( 'Could not reach Stripe: ' . $response->get_error_message() );
		}

		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( $code >= 400 || ! is_array( $body ) ) {
			$message = is_array( $body ) && isset( $body['error']['message'] ) ? $body['error']['message'] : 'HTTP ' . $code;
			throw new \RuntimeException( 'Stripe error: ' . $message );
		}
		return $body;
	}

	/**
	 * Convert a decimal amount to Stripe's minor units.
	 *
	 * @param float|string $amount   Amount, e.g. 12.50.
	 * @param int          $decimals Currency decimals.
	 */
	public static function to_minor( $amount, int $decimals = 2 ): int {
		return (int) round( (float) $amount * ( 10 ** $decimals ) );
	}

	/**
	 * Check a webhook's Stripe-Signature header. Pure, so it's unit tested.
	 *
	 * @param string $payload Raw request body.
	 * @param string $header  Stripe-Signature header.
	 * @param string $secret  Webhook signing secret.
	 * @param int    $now     Current Unix time.
	 */
	public static function verify_signature( string $payload, string $header, string $secret, int $now ): bool {
		if ( '' === $secret || '' === $header ) {
			return false;
		}

		$timestamp  = 0;
		$signatures = array();
		foreach ( explode( ',', $header ) as $part ) {
			$pair = explode( '=', trim( $part ), 2 );
			if ( 2 !== count( $pair ) ) {
				continue;
			}
			if ( 't' === $pair[0] ) {
				$timestamp = (int) $pair[1];
			} elseif ( 'v1' === $pair[0] ) {
				$signatures[] = $pair[1];
			}
		}

		if ( ! $timestamp || ! $signatures || abs( $now - $timestamp ) > self::TOLERANCE ) {
			return false;
		}

		$expected = hash_hmac( 'sha256', $timestamp . '.' . $payload, $secret );
		foreach ( $signatures as $signature ) {
			if ( hash_equals( $expected, $signature ) ) {
				return true;
			}
		}
		return false;
	}
}
