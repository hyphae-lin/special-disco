<?php
/**
 * Reading offerings: WooCommerce products with reading details.
 *
 * @package Hexxsy\Vendors
 */

namespace Hexxsy\Vendors;

/**
 * Each reading a vendor sells is a simple virtual product. The vendor is the
 * product's author. A reading is either written (delivered later, needs
 * intake answers) or live (a booked call).
 */
class Readings {

	const FORMAT_META     = '_hexxsy_reading_format';
	const TURNAROUND_META = '_hexxsy_turnaround_days';
	const DURATION_META   = '_hexxsy_duration_minutes';
	const INTAKE_META     = '_hexxsy_intake_fields';

	const WRITTEN = 'written';
	const LIVE    = 'live';

	/**
	 * Live calls stay off sale until booking exists. 'yes' puts them on.
	 */
	const LIVE_OPTION = 'hexxsy_live_calls';

	/**
	 * Optional intake fields a vendor can ask for on written readings. The
	 * question itself is always asked.
	 */
	const INTAKE_FIELDS = array(
		'birth_date'  => 'Birth date',
		'birth_time'  => 'Birth time',
		'birth_place' => 'Birth place',
		'focus_name'  => 'Name of the person the reading is about',
	);

	/**
	 * Hook in.
	 */
	public static function init(): void {
		add_action( 'woocommerce_product_options_general_product_data', array( __CLASS__, 'product_fields' ) );
		add_action( 'woocommerce_admin_process_product_object', array( __CLASS__, 'save_product_fields' ) );
		add_filter( 'woocommerce_product_supports', array( __CLASS__, 'disable_ajax_add_to_cart' ), 10, 3 );
		add_filter( 'woocommerce_product_add_to_cart_url', array( __CLASS__, 'loop_button_url' ), 10, 2 );
		add_filter( 'woocommerce_product_add_to_cart_text', array( __CLASS__, 'loop_button_text' ), 10, 2 );

		add_filter( 'woocommerce_is_purchasable', array( __CLASS__, 'purchasable' ), 10, 2 );
		add_filter( 'woocommerce_product_is_visible', array( __CLASS__, 'visible' ), 10, 2 );
		add_action( 'woocommerce_product_query', array( __CLASS__, 'hide_from_shop' ) );
		add_action( 'woocommerce_single_product_summary', array( __CLASS__, 'closed_note' ), 25 );
	}

	/**
	 * Whether live calls can be bought.
	 */
	public static function live_enabled(): bool {
		return 'yes' === get_option( self::LIVE_OPTION, 'no' );
	}

	/**
	 * Why a product can't be sold right now: 'live' for a live call while
	 * live calls are off, 'reader' when it belongs to someone who applied to
	 * read here and isn't an approved reader (pending, rejected or revoked),
	 * or '' when it can.
	 *
	 * @param \WC_Product $product Product.
	 */
	public static function off_sale( \WC_Product $product ): string {
		if ( self::LIVE === self::format( $product ) && ! self::live_enabled() ) {
			return 'live';
		}
		$author = (int) get_post_field( 'post_author', $product->get_id() );
		if ( $author && '' !== Vendor::status( $author ) && ! Vendor::is_approved( $author ) ) {
			return 'reader';
		}
		return '';
	}

	/**
	 * Nobody can buy an off-sale product, whatever page they find it on.
	 * WooCommerce also drops it from carts it's already in.
	 *
	 * @param bool        $purchasable Whether it can be bought.
	 * @param \WC_Product $product     Product.
	 */
	public static function purchasable( $purchasable, $product ) {
		return $purchasable && $product instanceof \WC_Product && '' === self::off_sale( $product );
	}

	/**
	 * Keep off-sale products out of product lists and related products.
	 *
	 * @param bool $visible    Whether it's listed.
	 * @param int  $product_id Product ID.
	 */
	public static function visible( $visible, $product_id ) {
		$product = $visible ? wc_get_product( $product_id ) : null;
		return $product ? '' === self::off_sale( $product ) : $visible;
	}

	/**
	 * Leave live calls out of the shop's own queries while they're off, so
	 * pages and counts come out right.
	 *
	 * @param \WP_Query $query Shop query.
	 */
	public static function hide_from_shop( $query ): void {
		if ( self::live_enabled() ) {
			return;
		}
		$meta   = (array) $query->get( 'meta_query' );
		$meta[] = array(
			'relation' => 'OR',
			array(
				'key'     => self::FORMAT_META,
				'compare' => 'NOT EXISTS',
			),
			array(
				'key'     => self::FORMAT_META,
				'value'   => self::LIVE,
				'compare' => '!=',
			),
		);
		$query->set( 'meta_query', $meta );
	}

	/**
	 * Say why there's no button on a live call's page.
	 */
	public static function closed_note(): void {
		global $product;
		if ( $product instanceof \WC_Product && 'live' === self::off_sale( $product ) ) {
			echo '<p class="hexxsy-line">' . Lines::html( 'live_closed' ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in Lines::html.
		}
	}

	/**
	 * Reading format for a product, or '' if it isn't a reading.
	 *
	 * @param \WC_Product $product Product.
	 */
	public static function format( \WC_Product $product ): string {
		return (string) $product->get_meta( self::FORMAT_META );
	}

	/**
	 * Intake field keys a written reading asks for.
	 *
	 * @param \WC_Product $product Product.
	 * @return string[]
	 */
	public static function intake_fields( \WC_Product $product ): array {
		$fields = $product->get_meta( self::INTAKE_META );
		return is_array( $fields ) ? array_values( array_intersect( $fields, array_keys( self::INTAKE_FIELDS ) ) ) : array();
	}

	/**
	 * Reading settings in the product editor. The front-end vendor dashboard
	 * will reuse the same meta keys.
	 */
	public static function product_fields(): void {
		global $product_object;

		echo '<div class="options_group hexxsy-reading">';

		woocommerce_wp_select(
			array(
				'id'      => self::FORMAT_META,
				'label'   => __( 'Reading format', 'hexxsy-vendors' ),
				'options' => array(
					''            => __( 'Not a reading', 'hexxsy-vendors' ),
					self::WRITTEN => __( 'Written reading', 'hexxsy-vendors' ),
					self::LIVE    => __( 'Live call', 'hexxsy-vendors' ),
				),
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'                => self::TURNAROUND_META,
				'label'             => __( 'Turnaround (days)', 'hexxsy-vendors' ),
				'description'       => __( 'Written readings: how long the reader has to deliver.', 'hexxsy-vendors' ),
				'desc_tip'          => true,
				'type'              => 'number',
				'custom_attributes' => array(
					'min'  => '1',
					'step' => '1',
				),
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'                => self::DURATION_META,
				'label'             => __( 'Call length (minutes)', 'hexxsy-vendors' ),
				'description'       => __( 'Live calls: length of the booked session.', 'hexxsy-vendors' ),
				'desc_tip'          => true,
				'type'              => 'number',
				'custom_attributes' => array(
					'min'  => '5',
					'step' => '5',
				),
			)
		);

		$selected = $product_object instanceof \WC_Product ? self::intake_fields( $product_object ) : array();
		echo '<p class="form-field"><label>' . esc_html__( 'Intake questions', 'hexxsy-vendors' ) . '</label>';
		foreach ( self::INTAKE_FIELDS as $key => $label ) {
			printf(
				'<label style="display:block;float:none;width:auto;margin:0 0 4px;"><input type="checkbox" name="%1$s[]" value="%2$s" %3$s> %4$s</label>',
				esc_attr( self::INTAKE_META ),
				esc_attr( $key ),
				checked( in_array( $key, $selected, true ), true, false ),
				esc_html( $label )
			);
		}
		echo '<span class="description">' . esc_html__( 'Written readings always ask for the customer\'s question. Tick anything else you need.', 'hexxsy-vendors' ) . '</span></p>';

		woocommerce_wp_textarea_input(
			array(
				'id'          => Offering::BEFORE_META,
				'label'       => __( 'Before you buy', 'hexxsy-vendors' ),
				'description' => __( 'Shown on the offering\'s page: how to prepare, what the reader won\'t read on, what to send.', 'hexxsy-vendors' ),
				'desc_tip'    => true,
				'rows'        => 4,
			)
		);

		echo '</div>';
	}

	/**
	 * Save reading settings from the product editor. Nonce and capability
	 * checks are done by WooCommerce before this hook runs.
	 *
	 * @param \WC_Product $product Product being saved.
	 */
	public static function save_product_fields( \WC_Product $product ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		self::apply_settings( $product, wp_unslash( $_POST ) );
	}

	/**
	 * Apply reading settings from submitted form data. Shared by the product
	 * editor and the vendor dashboard.
	 *
	 * @param \WC_Product $product Product to update. Not saved here.
	 * @param array       $input   Unslashed form data.
	 */
	public static function apply_settings( \WC_Product $product, array $input ): void {
		$format = isset( $input[ self::FORMAT_META ] ) ? sanitize_key( $input[ self::FORMAT_META ] ) : '';
		if ( ! in_array( $format, array( self::WRITTEN, self::LIVE ), true ) ) {
			$format = '';
		}
		$product->update_meta_data( self::FORMAT_META, $format );

		$product->update_meta_data( self::TURNAROUND_META, isset( $input[ self::TURNAROUND_META ] ) ? absint( $input[ self::TURNAROUND_META ] ) : 0 );
		$product->update_meta_data( self::DURATION_META, isset( $input[ self::DURATION_META ] ) ? absint( $input[ self::DURATION_META ] ) : 0 );

		$intake = isset( $input[ self::INTAKE_META ] ) ? array_map( 'sanitize_key', (array) $input[ self::INTAKE_META ] ) : array();
		$product->update_meta_data( self::INTAKE_META, array_values( array_intersect( $intake, array_keys( self::INTAKE_FIELDS ) ) ) );

		if ( isset( $input[ Offering::BEFORE_META ] ) ) {
			$product->update_meta_data( Offering::BEFORE_META, sanitize_textarea_field( (string) $input[ Offering::BEFORE_META ] ) );
		}

		// Readings are services: nothing ships, and each order needs its own reading.
		if ( '' !== $format ) {
			$product->set_virtual( true );
			$product->set_sold_individually( true );
		}
	}

	/**
	 * Shop-page buttons can't collect intake answers or (later) a call time,
	 * so readings link to their own page instead of adding to cart directly.
	 *
	 * @param bool        $supports Whether the feature is supported.
	 * @param string      $feature  Feature name.
	 * @param \WC_Product $product  Product.
	 */
	public static function disable_ajax_add_to_cart( $supports, $feature, $product ) {
		return 'ajax_add_to_cart' === $feature && '' !== self::format( $product ) ? false : $supports;
	}

	/**
	 * Point the shop-page button at the reading's page.
	 *
	 * @param string      $url     Add-to-cart URL.
	 * @param \WC_Product $product Product.
	 */
	public static function loop_button_url( $url, $product ) {
		return '' !== self::format( $product ) ? $product->get_permalink() : $url;
	}

	/**
	 * Label the shop-page button for a reading.
	 *
	 * @param string      $text    Button text.
	 * @param \WC_Product $product Product.
	 */
	public static function loop_button_text( $text, $product ) {
		$format = self::format( $product );
		$shelf  = '' !== $format ? Shelves::of( $product ) : null;
		if ( $shelf && '' !== $shelf['action'] ) {
			return $shelf['action'];
		}
		if ( self::WRITTEN === $format ) {
			return __( 'Request reading', 'hexxsy-vendors' );
		}
		if ( self::LIVE === $format ) {
			return __( 'Book a call', 'hexxsy-vendors' );
		}
		return $text;
	}
}
