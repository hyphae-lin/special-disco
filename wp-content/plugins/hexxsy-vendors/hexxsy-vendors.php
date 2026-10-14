<?php
/**
 * Plugin Name:       Hexxsy Vendors
 * Description:       Multi-vendor marketplace for readings on Hexxsy: vendor applications and approval, reading offerings, intake questions, written reading delivery, and Stripe Connect payments and payouts.
 * Version:           0.5.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Requires Plugins:  woocommerce
 * Author:            Hexxsy
 * Text Domain:       hexxsy-vendors
 *
 * @package Hexxsy\Vendors
 */

defined( 'ABSPATH' ) || exit;

define( 'HEXXSY_VENDORS_VERSION', '0.5.0' );
define( 'HEXXSY_VENDORS_FILE', __FILE__ );
define( 'HEXXSY_VENDORS_DIR', plugin_dir_path( __FILE__ ) );

require_once HEXXSY_VENDORS_DIR . 'includes/class-commission.php';
require_once HEXXSY_VENDORS_DIR . 'includes/class-vendor.php';
require_once HEXXSY_VENDORS_DIR . 'includes/class-roles.php';
require_once HEXXSY_VENDORS_DIR . 'includes/class-application.php';
require_once HEXXSY_VENDORS_DIR . 'includes/class-admin.php';
require_once HEXXSY_VENDORS_DIR . 'includes/class-readings.php';
require_once HEXXSY_VENDORS_DIR . 'includes/class-intake.php';
require_once HEXXSY_VENDORS_DIR . 'includes/class-order-split.php';
require_once HEXXSY_VENDORS_DIR . 'includes/class-dashboard.php';
require_once HEXXSY_VENDORS_DIR . 'includes/class-pdf.php';
require_once HEXXSY_VENDORS_DIR . 'includes/class-delivery.php';
require_once HEXXSY_VENDORS_DIR . 'includes/class-customer.php';
require_once HEXXSY_VENDORS_DIR . 'includes/class-stripe-api.php';
require_once HEXXSY_VENDORS_DIR . 'includes/class-payouts.php';
require_once HEXXSY_VENDORS_DIR . 'includes/class-webhook.php';

register_activation_hook( __FILE__, array( 'Hexxsy\Vendors\Roles', 'install' ) );
register_deactivation_hook( __FILE__, array( 'Hexxsy\Vendors\Roles', 'uninstall' ) );

add_action(
	'plugins_loaded',
	static function () {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action(
				'admin_notices',
				static function () {
					echo '<div class="notice notice-error"><p>' . esc_html__( 'Hexxsy Vendors needs WooCommerce to be installed and active.', 'hexxsy-vendors' ) . '</p></div>';
				}
			);
			return;
		}

		Hexxsy\Vendors\Application::init();
		Hexxsy\Vendors\Admin::init();
		Hexxsy\Vendors\Readings::init();
		Hexxsy\Vendors\Intake::init();
		Hexxsy\Vendors\Order_Split::init();
		Hexxsy\Vendors\Dashboard::init();
		Hexxsy\Vendors\Delivery::init();
		Hexxsy\Vendors\Customer::init();
		Hexxsy\Vendors\Payouts::init();
		Hexxsy\Vendors\Webhook::init();

		// These extend WooCommerce classes, so load them only once it's active.
		require_once HEXXSY_VENDORS_DIR . 'includes/class-gateway.php';
		Hexxsy\Vendors\Gateway::init();

		add_action(
			'woocommerce_blocks_payment_method_type_registration',
			static function ( $registry ) {
				require_once HEXXSY_VENDORS_DIR . 'includes/class-gateway-blocks.php';
				$registry->register( new Hexxsy\Vendors\Gateway_Blocks() );
			}
		);
	}
);
