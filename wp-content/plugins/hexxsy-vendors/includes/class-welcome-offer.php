<?php
/**
 * Welcome offer: a first-purchase discount the house absorbs.
 *
 * @package Hexxsy\Vendors
 */

namespace Hexxsy\Vendors;

/**
 * MailPoet sends each new subscriber a unique WooCommerce coupon. This class
 * recognizes those coupons, keeps their discount inside the site's
 * commission on every line, and pays readers as if no discount was used.
 *
 * Recognizing a welcome coupon, in order:
 * 1. A coupon marked "Welcome offer" (or explicitly unmarked) on its edit
 *    screen in WooCommerce > Coupons.
 * 2. A coupon MailPoet generated, judged by the description MailPoet gives
 *    it. If the admin lists MailPoet email IDs on the Vendors page, only
 *    coupons from those emails count. If the list is empty, any coupon
 *    MailPoet generates counts.
 *
 * WooCommerce splits a fixed cart discount evenly across items, not in
 * proportion to price, so a cheap item can get more discount than its
 * commission. The guard caps each line at its commission. WooCommerce then
 * moves what's left to lines with room, so the customer still gets the full
 * discount whenever the cart's total commission covers it.
 *
 * The pure functions here run without WordPress, for tests.
 */
class Welcome_Offer {

	/** Coupon meta: 'yes' or 'no' when set by the admin or on creation. */
	const COUPON_META = '_hexxsy_welcome_offer';

	/** Order line meta: 'yes' on a welcome-offer line. */
	const LINE_META = '_hexxsy_welcome_offer';

	/** Order line meta: line total before the welcome discount. */
	const UNDISCOUNTED_META = '_hexxsy_undiscounted_total';

	/** Comma-separated MailPoet email IDs whose coupons count. */
	const EMAILS_OPTION = 'hexxsy_welcome_email_ids';

	/**
	 * Descriptions MailPoet sets on coupons it generates (block email editor,
	 * then the classic editor). Translated versions are added at runtime.
	 */
	const MAILPOET_FORMATS = array(
		'Auto-generated coupon by MailPoet for email: %1$d',
		'Auto Generated coupon by MailPoet for email: %1$s: %2$s',
	);

	/**
	 * Discount already given per coupon and cart line during one cart
	 * calculation, in minor units.
	 *
	 * @var array<string,int>
	 */
	private static array $given = array();

	/**
	 * Hook in.
	 */
	public static function init(): void {
		add_action( 'woocommerce_before_calculate_totals', array( __CLASS__, 'reset' ) );
		add_filter( 'woocommerce_coupon_get_discount_amount', array( __CLASS__, 'guard' ), 20, 5 );
		add_action( 'woocommerce_checkout_create_order', array( __CLASS__, 'stamp_order' ), 30 );
		add_action( 'woocommerce_new_coupon', array( __CLASS__, 'mark_new_coupon' ), 10, 2 );
		add_action( 'woocommerce_coupon_options', array( __CLASS__, 'coupon_field' ), 10, 2 );
		add_action( 'woocommerce_coupon_options_save', array( __CLASS__, 'save_coupon_field' ), 10, 2 );
	}

	// ---------------------------------------------------------------------
	// Pure math.
	// ---------------------------------------------------------------------

	/**
	 * How much more welcome discount a line can take, in minor units.
	 *
	 * @param int   $line_price Line price before discount.
	 * @param float $rate       Commission rate.
	 * @param int   $given      Welcome discount already on the line.
	 */
	public static function room( int $line_price, float $rate, int $given ): int {
		return max( 0, Commission::split( max( 0, $line_price ), $rate )['commission'] - $given );
	}

	/**
	 * Split on a welcome-offer line. The reader earns their share of the
	 * undiscounted price; the site keeps what's left of what the customer
	 * paid. The reader's share never exceeds what was paid.
	 *
	 * @param int   $undiscounted Line total before the discount.
	 * @param int   $paid         Line total the customer paid.
	 * @param float $rate         Commission rate.
	 * @return array{commission:int,vendor:int}
	 */
	public static function split( int $undiscounted, int $paid, float $rate ): array {
		$paid   = max( 0, $paid );
		$vendor = min( $paid, Commission::split( max( $paid, $undiscounted ), $rate )['vendor'] );

		return array(
			'commission' => $paid - $vendor,
			'vendor'     => $vendor,
		);
	}

	/**
	 * Reader share on a welcome-offer line after refunds. A refund takes the
	 * same fraction off the undiscounted price as it took off what the
	 * customer paid.
	 *
	 * @param int   $undiscounted Line total before the discount.
	 * @param int   $paid         Line total the customer paid.
	 * @param int   $refunded     Amount refunded on the line.
	 * @param float $rate         Commission rate stamped at purchase.
	 */
	public static function share_after_refund( int $undiscounted, int $paid, int $refunded, float $rate ): int {
		$left = $paid - max( 0, $refunded );
		if ( $paid <= 0 || $left <= 0 ) {
			return 0;
		}
		$base = (int) round( max( $paid, $undiscounted ) * $left / $paid );

		return self::split( $base, $left, $rate )['vendor'];
	}

	/**
	 * Smallest minimum spend that lets a fixed discount always fit inside
	 * the commission, in minor units.
	 *
	 * @param int   $discount Fixed discount.
	 * @param float $rate     Lowest commission rate in force.
	 * @return int|null Null when no minimum works (rate of 0).
	 */
	public static function safe_minimum( int $discount, float $rate ): ?int {
		$rate = Commission::clamp_rate( $rate );
		if ( $rate <= 0 ) {
			return null;
		}

		return (int) ceil( $discount * 100 / $rate );
	}

	/**
	 * Whether a coupon description is one MailPoet writes, and if email IDs
	 * are listed, from one of those emails.
	 *
	 * @param string   $description Coupon description.
	 * @param string[] $formats     sprintf formats MailPoet uses.
	 * @param int[]    $email_ids   Allowed MailPoet email IDs; empty for any.
	 */
	public static function is_mailpoet_description( string $description, array $formats, array $email_ids ): bool {
		foreach ( array_unique( $formats ) as $format ) {
			$pattern = preg_quote( $format, '/' );
			$pattern = str_replace( array( '%1\$d', '%1\$s' ), '(\d+)', $pattern );
			$pattern = str_replace( '%2\$s', '.*', $pattern );

			if ( preg_match( '/^' . $pattern . '$/su', trim( $description ), $m ) ) {
				return empty( $email_ids ) || in_array( (int) $m[1], $email_ids, true );
			}
		}

		return false;
	}

	/**
	 * Parse the email ID setting.
	 *
	 * @param string $value Comma or space separated IDs.
	 * @return int[]
	 */
	public static function parse_ids( string $value ): array {
		$ids = array_filter( array_map( 'intval', preg_split( '/[\s,]+/', $value ) ?: array() ) );

		return array_values( array_unique( array_filter( $ids, static fn( $id ) => $id > 0 ) ) );
	}

	// ---------------------------------------------------------------------
	// WordPress glue.
	// ---------------------------------------------------------------------

	/**
	 * MailPoet email IDs from settings.
	 *
	 * @return int[]
	 */
	public static function email_ids(): array {
		return self::parse_ids( (string) get_option( self::EMAILS_OPTION, '' ) );
	}

	/**
	 * Description formats, including the site's translation of MailPoet's.
	 *
	 * @return string[]
	 */
	private static function formats(): array {
		$formats = self::MAILPOET_FORMATS;
		foreach ( self::MAILPOET_FORMATS as $format ) {
			// phpcs:ignore WordPress.WP.I18n -- matching MailPoet's own string.
			$formats[] = _x( $format, 'Coupon block code generation', 'mailpoet' );
		}

		return $formats;
	}

	/**
	 * Whether a coupon is a welcome coupon.
	 *
	 * @param \WC_Coupon $coupon Coupon.
	 */
	public static function is_welcome_coupon( $coupon ): bool {
		if ( ! $coupon instanceof \WC_Coupon ) {
			return false;
		}

		$flag = $coupon->get_id() ? (string) get_post_meta( $coupon->get_id(), self::COUPON_META, true ) : '';
		if ( 'yes' === $flag || 'no' === $flag ) {
			return 'yes' === $flag;
		}

		return self::is_mailpoet_description( (string) $coupon->get_description(), self::formats(), self::email_ids() );
	}

	/**
	 * Start each cart calculation fresh.
	 */
	public static function reset(): void {
		self::$given = array();
	}

	/**
	 * Cap a welcome coupon's discount on a cart line at the line's commission.
	 *
	 * @param float      $discount   Discount WooCommerce wants to give the line.
	 * @param float      $line_price Price being discounted.
	 * @param array      $cart_item  Cart item.
	 * @param bool       $single     Unused.
	 * @param \WC_Coupon $coupon     Coupon.
	 * @return float
	 */
	public static function guard( $discount, $line_price, $cart_item, $single, $coupon ) {
		if ( ! is_array( $cart_item ) || empty( $cart_item['product_id'] ) || ! self::is_welcome_coupon( $coupon ) ) {
			return $discount;
		}

		$vendor_id = (int) get_post_field( 'post_author', (int) $cart_item['product_id'] );
		if ( ! $vendor_id || ! Vendor::is_approved( $vendor_id ) ) {
			// The site's own products: no reader share to protect.
			return $discount;
		}

		$factor = 10 ** wc_get_price_decimals();
		$key    = $coupon->get_code() . '|' . ( $cart_item['key'] ?? $cart_item['product_id'] );
		$given  = self::$given[ $key ] ?? 0;
		$room   = self::room( (int) round( (float) $line_price * $factor ), Commission::rate_for_vendor( $vendor_id ), $given );
		$wanted = (int) round( (float) $discount * $factor );
		$allow  = min( max( 0, $wanted ), $room );

		self::$given[ $key ] = $given + $allow;

		return $allow / $factor;
	}

	/**
	 * On a welcome-offer order, pay readers on the undiscounted price.
	 * Coupon lines exist by now; product lines already carry the normal split.
	 *
	 * @param \WC_Order $order Order.
	 */
	public static function stamp_order( $order ): void {
		$coupons = $order->get_coupons();
		if ( 1 !== count( $coupons ) ) {
			// Welcome codes are individual use, so they never share an order.
			return;
		}

		$coupon = new \WC_Coupon( reset( $coupons )->get_code() );
		if ( ! self::is_welcome_coupon( $coupon ) ) {
			return;
		}

		$decimals = wc_get_price_decimals();
		$factor   = 10 ** $decimals;

		foreach ( $order->get_items() as $item ) {
			if ( ! $item->get_meta( Order_Split::VENDOR_META ) ) {
				continue;
			}
			$undiscounted = (int) round( (float) $item->get_subtotal() * $factor );
			$paid         = (int) round( (float) $item->get_total() * $factor );
			if ( $undiscounted <= $paid ) {
				continue;
			}

			$split = self::split( $undiscounted, $paid, (float) $item->get_meta( Order_Split::RATE_META ) );

			$item->update_meta_data( self::LINE_META, 'yes' );
			$item->update_meta_data( self::UNDISCOUNTED_META, wc_format_decimal( $undiscounted / $factor, $decimals ) );
			$item->update_meta_data( Order_Split::COMMISSION_META, wc_format_decimal( $split['commission'] / $factor, $decimals ) );
			$item->update_meta_data( Order_Split::EARNINGS_META, wc_format_decimal( $split['vendor'] / $factor, $decimals ) );
		}
	}

	/**
	 * Mark coupons MailPoet generates from a welcome email as they're made,
	 * so the Vendors page can find the latest one.
	 *
	 * @param int        $coupon_id Coupon ID.
	 * @param \WC_Coupon $coupon    Coupon.
	 */
	public static function mark_new_coupon( $coupon_id, $coupon = null ): void {
		if ( ! $coupon instanceof \WC_Coupon ) {
			$coupon = new \WC_Coupon( (int) $coupon_id );
		}
		if ( self::is_mailpoet_description( (string) $coupon->get_description(), self::formats(), self::email_ids() ) ) {
			update_post_meta( (int) $coupon_id, self::COUPON_META, 'yes' );
		}
	}

	/**
	 * Checkbox on the coupon edit screen.
	 *
	 * @param int        $coupon_id Coupon ID.
	 * @param \WC_Coupon $coupon    Coupon.
	 */
	public static function coupon_field( $coupon_id, $coupon ): void {
		woocommerce_wp_checkbox(
			array(
				'id'          => self::COUPON_META,
				'label'       => __( 'Welcome offer', 'hexxsy-vendors' ),
				'description' => __( 'The house absorbs this discount: readers are paid on the full price, and the discount on each item is capped at the site\'s commission.', 'hexxsy-vendors' ),
				'value'       => self::is_welcome_coupon( $coupon ) ? 'yes' : 'no',
				'cbvalue'     => 'yes',
			)
		);
	}

	/**
	 * Save the checkbox. WooCommerce has checked the nonce and capability.
	 *
	 * @param int        $post_id Coupon ID.
	 * @param \WC_Coupon $coupon  Coupon.
	 */
	public static function save_coupon_field( $post_id, $coupon ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified by WooCommerce's meta box save.
		update_post_meta( (int) $post_id, self::COUPON_META, isset( $_POST[ self::COUPON_META ] ) ? 'yes' : 'no' );
	}

	/**
	 * Lowest commission rate in force: the site default or any approved
	 * reader's override.
	 */
	public static function lowest_rate(): float {
		$lowest = Commission::default_rate();
		$ids    = get_users(
			array(
				'meta_key'   => Vendor::STATUS_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value' => Vendor::APPROVED, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'fields'     => 'ID',
			)
		);
		foreach ( $ids as $id ) {
			$lowest = min( $lowest, Commission::rate_for_vendor( (int) $id ) );
		}

		return $lowest;
	}

	/**
	 * The most recently created welcome coupon, if any.
	 */
	public static function latest_coupon(): ?\WC_Coupon {
		$ids = get_posts(
			array(
				'post_type'   => 'shop_coupon',
				'post_status' => 'publish',
				'numberposts' => 1,
				'orderby'     => 'date',
				'order'       => 'DESC',
				'fields'      => 'ids',
				'meta_key'    => self::COUPON_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'  => 'yes', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);

		return $ids ? new \WC_Coupon( (int) $ids[0] ) : null;
	}
}
