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

		echo '</div>';
	}

	/**
	 * Save reading settings. Nonce and capability checks are done by
	 * WooCommerce before this hook runs.
	 *
	 * @param \WC_Product $product Product being saved.
	 */
	public static function save_product_fields( \WC_Product $product ): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$format = isset( $_POST[ self::FORMAT_META ] ) ? sanitize_key( $_POST[ self::FORMAT_META ] ) : '';
		if ( ! in_array( $format, array( self::WRITTEN, self::LIVE ), true ) ) {
			$format = '';
		}
		$product->update_meta_data( self::FORMAT_META, $format );

		$product->update_meta_data( self::TURNAROUND_META, isset( $_POST[ self::TURNAROUND_META ] ) ? absint( $_POST[ self::TURNAROUND_META ] ) : 0 );
		$product->update_meta_data( self::DURATION_META, isset( $_POST[ self::DURATION_META ] ) ? absint( $_POST[ self::DURATION_META ] ) : 0 );

		$intake = isset( $_POST[ self::INTAKE_META ] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST[ self::INTAKE_META ] ) ) : array();
		$product->update_meta_data( self::INTAKE_META, array_values( array_intersect( $intake, array_keys( self::INTAKE_FIELDS ) ) ) );
		// phpcs:enable

		// Readings are services: nothing ships, and each order needs its own reading.
		if ( '' !== $format ) {
			$product->set_virtual( true );
			$product->set_sold_individually( true );
		}
	}
}
