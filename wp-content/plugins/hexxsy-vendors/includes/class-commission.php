<?php
/**
 * Commission math and rate lookup.
 *
 * @package Hexxsy\Vendors
 */

namespace Hexxsy\Vendors;

/**
 * Splits sale amounts between the site and a vendor.
 *
 * Math works in minor units (cents) so rounding never loses or invents money:
 * the commission is rounded, and the vendor gets exactly what's left.
 */
class Commission {

	const OPTION       = 'hexxsy_commission_rate';
	const USER_META    = 'hexxsy_commission_rate';
	const DEFAULT_RATE = 20.0;

	/**
	 * Split an amount in minor units.
	 *
	 * @param int   $amount Amount in minor units, e.g. cents.
	 * @param float $rate   Commission percentage, 0 to 100.
	 * @return array{commission:int,vendor:int}
	 */
	public static function split( int $amount, float $rate ): array {
		$rate       = self::clamp_rate( $rate );
		$commission = (int) round( $amount * $rate / 100 );

		return array(
			'commission' => $commission,
			'vendor'     => $amount - $commission,
		);
	}

	/**
	 * Keep a rate within 0 to 100.
	 *
	 * @param float $rate Percentage.
	 */
	public static function clamp_rate( float $rate ): float {
		return max( 0.0, min( 100.0, $rate ) );
	}

	/**
	 * Site-wide default rate.
	 */
	public static function default_rate(): float {
		return self::clamp_rate( (float) get_option( self::OPTION, self::DEFAULT_RATE ) );
	}

	/**
	 * Rate for a vendor: their override if set, otherwise the site default.
	 *
	 * @param int $vendor_id User ID.
	 */
	public static function rate_for_vendor( int $vendor_id ): float {
		$override = get_user_meta( $vendor_id, self::USER_META, true );

		if ( '' !== $override && is_numeric( $override ) ) {
			return self::clamp_rate( (float) $override );
		}

		return self::default_rate();
	}
}
