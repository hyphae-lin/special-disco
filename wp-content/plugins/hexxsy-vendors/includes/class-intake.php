<?php
/**
 * Intake questions for written readings.
 *
 * @package Hexxsy\Vendors
 */

namespace Hexxsy\Vendors;

/**
 * Shows the intake form on a written reading's product page, carries the
 * answers through the cart, and saves them on the order line for the reader.
 * Live calls skip this entirely.
 */
class Intake {

	const QUESTION_KEY = 'question';
	const CART_KEY     = 'hexxsy_intake';

	/**
	 * Hook in.
	 */
	public static function init(): void {
		add_action( 'woocommerce_before_add_to_cart_button', array( __CLASS__, 'render_fields' ) );
		add_filter( 'woocommerce_add_to_cart_validation', array( __CLASS__, 'validate' ), 10, 2 );
		add_filter( 'woocommerce_add_cart_item_data', array( __CLASS__, 'add_cart_item_data' ), 10, 2 );
		add_filter( 'woocommerce_get_item_data', array( __CLASS__, 'display_cart_item_data' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'save_order_item_meta' ), 10, 3 );
	}

	/**
	 * Labels for every field this product asks for, question first.
	 *
	 * @param \WC_Product $product Product.
	 * @return array<string,string>
	 */
	public static function fields_for( \WC_Product $product ): array {
		if ( Readings::WRITTEN !== Readings::format( $product ) ) {
			return array();
		}

		$fields = array( self::QUESTION_KEY => __( 'Your question', 'hexxsy-vendors' ) );
		foreach ( Readings::intake_fields( $product ) as $key ) {
			$fields[ $key ] = Readings::INTAKE_FIELDS[ $key ];
		}
		return $fields;
	}

	/**
	 * Render the intake form above the add-to-cart button.
	 */
	public static function render_fields(): void {
		global $product;
		if ( ! $product instanceof \WC_Product ) {
			return;
		}

		$fields = self::fields_for( $product );
		if ( ! $fields ) {
			return;
		}

		echo '<div class="hexxsy-intake">';
		foreach ( $fields as $key => $label ) {
			$id = 'hexxsy_intake_' . $key;
			echo '<p class="form-row"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . ' <abbr class="required">*</abbr></label>';

			if ( self::QUESTION_KEY === $key ) {
				echo '<textarea id="' . esc_attr( $id ) . '" name="' . esc_attr( self::CART_KEY . '[' . $key . ']' ) . '" rows="4" maxlength="2000" required></textarea>';
			} else {
				$type = 'birth_date' === $key ? 'date' : ( 'birth_time' === $key ? 'time' : 'text' );
				echo '<input type="' . esc_attr( $type ) . '" id="' . esc_attr( $id ) . '" name="' . esc_attr( self::CART_KEY . '[' . $key . ']' ) . '" maxlength="200" required>';
			}
			echo '</p>';
		}
		echo '</div>';
	}

	/**
	 * Read submitted answers for a product, sanitized.
	 *
	 * @param \WC_Product $product Product.
	 * @return array<string,string>
	 */
	private static function submitted( \WC_Product $product ): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- add-to-cart has no nonce; values are sanitized below.
		$raw     = isset( $_POST[ self::CART_KEY ] ) && is_array( $_POST[ self::CART_KEY ] ) ? wp_unslash( $_POST[ self::CART_KEY ] ) : array();
		$answers = array();

		foreach ( array_keys( self::fields_for( $product ) ) as $key ) {
			$value           = isset( $raw[ $key ] ) ? (string) $raw[ $key ] : '';
			$answers[ $key ] = self::QUESTION_KEY === $key ? sanitize_textarea_field( $value ) : sanitize_text_field( $value );
		}
		return $answers;
	}

	/**
	 * Block add-to-cart until every intake field is filled.
	 *
	 * @param bool $passed     Validation so far.
	 * @param int  $product_id Product.
	 */
	public static function validate( $passed, $product_id ) {
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return $passed;
		}

		$fields = self::fields_for( $product );
		foreach ( self::submitted( $product ) as $key => $value ) {
			if ( '' === trim( $value ) ) {
				/* translators: %s: field label */
				wc_add_notice( sprintf( __( 'Please fill in: %s', 'hexxsy-vendors' ), $fields[ $key ] ), 'error' );
				$passed = false;
			}
		}
		return $passed;
	}

	/**
	 * Attach answers to the cart item.
	 *
	 * @param array $cart_item_data Cart item data.
	 * @param int   $product_id     Product.
	 */
	public static function add_cart_item_data( $cart_item_data, $product_id ) {
		$product = wc_get_product( $product_id );
		if ( $product && self::fields_for( $product ) ) {
			$cart_item_data[ self::CART_KEY ] = self::submitted( $product );
		}
		return $cart_item_data;
	}

	/**
	 * Show answers in the cart and checkout.
	 *
	 * @param array $item_data Display rows.
	 * @param array $cart_item Cart item.
	 */
	public static function display_cart_item_data( $item_data, $cart_item ) {
		if ( empty( $cart_item[ self::CART_KEY ] ) || empty( $cart_item['data'] ) ) {
			return $item_data;
		}

		$fields = self::fields_for( $cart_item['data'] );
		foreach ( $cart_item[ self::CART_KEY ] as $key => $value ) {
			if ( isset( $fields[ $key ] ) ) {
				$item_data[] = array(
					'key'   => $fields[ $key ],
					'value' => $value,
				);
			}
		}
		return $item_data;
	}

	/**
	 * Save answers on the order line so the reader sees them.
	 *
	 * @param \WC_Order_Item_Product $item          Order line.
	 * @param string                 $cart_item_key Cart key.
	 * @param array                  $values        Cart item.
	 */
	public static function save_order_item_meta( $item, $cart_item_key, $values ): void {
		if ( empty( $values[ self::CART_KEY ] ) || empty( $values['data'] ) ) {
			return;
		}

		$fields = self::fields_for( $values['data'] );
		foreach ( $values[ self::CART_KEY ] as $key => $value ) {
			if ( isset( $fields[ $key ] ) ) {
				$item->add_meta_data( $fields[ $key ], $value, true );
			}
		}
	}
}
