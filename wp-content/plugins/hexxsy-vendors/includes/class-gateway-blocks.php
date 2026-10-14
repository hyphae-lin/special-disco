<?php
/**
 * Block checkout support for the Hexxsy Stripe gateway.
 *
 * @package Hexxsy\Vendors
 */

namespace Hexxsy\Vendors;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * Without this, WooCommerce's block checkout (the default on new sites)
 * wouldn't list the gateway at all.
 */
class Gateway_Blocks extends AbstractPaymentMethodType {

	/**
	 * Payment method name; matches the gateway ID.
	 *
	 * @var string
	 */
	protected $name = Gateway::ID;

	/**
	 * Load the gateway's saved settings.
	 */
	public function initialize() {
		$this->settings = get_option( 'woocommerce_' . Gateway::ID . '_settings', array() );
	}

	/**
	 * Active when enabled and keys are configured.
	 */
	public function is_active() {
		return Stripe_Api::is_configured() && 'no' !== ( $this->settings['enabled'] ?? 'yes' );
	}

	/**
	 * Script that registers the method in the checkout.
	 */
	public function get_payment_method_script_handles() {
		wp_register_script(
			'hexxsy-checkout-blocks',
			plugins_url( 'assets/js/checkout-blocks.js', HEXXSY_VENDORS_FILE ),
			array( 'wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities' ),
			HEXXSY_VENDORS_VERSION,
			true
		);
		return array( 'hexxsy-checkout-blocks' );
	}

	/**
	 * Data passed to the script.
	 */
	public function get_payment_method_data() {
		return array(
			'title'       => $this->settings['title'] ?? __( 'Card', 'hexxsy-vendors' ),
			'description' => $this->settings['description'] ?? '',
			'supports'    => array( 'products' ),
		);
	}
}
