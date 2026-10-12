<?php
/**
 * Records who earns what on each order line.
 *
 * @package Hexxsy\Vendors
 */

namespace Hexxsy\Vendors;

/**
 * Stamps every order line with its vendor, the commission rate in force at
 * purchase, and the resulting split. Payouts (Stripe Connect, once approved)
 * will read these instead of recalculating, so a later rate change never
 * alters past orders.
 *
 * Commission is taken on the line total after discounts, excluding tax.
 */
class Order_Split {

	const VENDOR_META     = '_hexxsy_vendor_id';
	const RATE_META       = '_hexxsy_commission_rate';
	const COMMISSION_META = '_hexxsy_commission';
	const EARNINGS_META   = '_hexxsy_vendor_earnings';

	/**
	 * Hook in.
	 */
	public static function init(): void {
		add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'stamp_line_item' ), 20, 4 );
	}

	/**
	 * Record the split on a new order line.
	 *
	 * @param \WC_Order_Item_Product $item          Order line. Totals are already set.
	 * @param string                 $cart_item_key Cart key.
	 * @param array                  $values        Cart item.
	 * @param \WC_Order              $order         Order.
	 */
	public static function stamp_line_item( $item, $cart_item_key, $values, $order ): void {
		$vendor_id = (int) get_post_field( 'post_author', $item->get_product_id() );
		if ( ! $vendor_id || ! Vendor::is_approved( $vendor_id ) ) {
			// Products the site sells itself, with no vendor share.
			return;
		}

		$decimals = wc_get_price_decimals();
		$factor   = 10 ** $decimals;
		$rate     = Commission::rate_for_vendor( $vendor_id );
		$split    = Commission::split( (int) round( (float) $item->get_total() * $factor ), $rate );

		$item->add_meta_data( self::VENDOR_META, $vendor_id, true );
		$item->add_meta_data( self::RATE_META, $rate, true );
		$item->add_meta_data( self::COMMISSION_META, wc_format_decimal( $split['commission'] / $factor, $decimals ), true );
		$item->add_meta_data( self::EARNINGS_META, wc_format_decimal( $split['vendor'] / $factor, $decimals ), true );
	}
}
