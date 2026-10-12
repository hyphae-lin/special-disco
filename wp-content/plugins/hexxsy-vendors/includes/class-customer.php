<?php
/**
 * Customer accounts and their reading library.
 *
 * @package Hexxsy\Vendors
 */

namespace Hexxsy\Vendors;

/**
 * Every purchase needs an account, so every reading has a home. Customers get
 * a My readings tab listing everything they've bought, with downloads for
 * delivered written readings.
 */
class Customer {

	const ENDPOINT = 'readings';

	/**
	 * Hook in.
	 */
	public static function init(): void {
		// Turn off guest checkout and allow sign-up at checkout, whatever the
		// WooCommerce settings say. Options cover the block checkout; the
		// filters cover the classic one.
		add_filter( 'pre_option_woocommerce_enable_guest_checkout', static fn() => 'no' );
		add_filter( 'pre_option_woocommerce_enable_signup_and_login_from_checkout', static fn() => 'yes' );
		add_filter( 'woocommerce_checkout_registration_required', '__return_true' );
		add_filter( 'woocommerce_checkout_registration_enabled', '__return_true' );

		add_filter( 'woocommerce_get_query_vars', array( __CLASS__, 'query_vars' ) );
		add_filter( 'woocommerce_account_menu_items', array( __CLASS__, 'menu_items' ), 20 );
		add_filter( 'woocommerce_endpoint_' . self::ENDPOINT . '_title', static fn() => __( 'My readings', 'hexxsy-vendors' ) );
		add_action( 'woocommerce_account_' . self::ENDPOINT . '_endpoint', array( __CLASS__, 'render' ) );
	}

	/**
	 * Register the endpoint.
	 *
	 * @param array $vars Query vars.
	 */
	public static function query_vars( $vars ) {
		$vars[ self::ENDPOINT ] = self::ENDPOINT;
		return $vars;
	}

	/**
	 * Put My readings right after Dashboard, before any vendor tabs.
	 *
	 * @param array $items Menu items.
	 */
	public static function menu_items( $items ) {
		$first = array_slice( $items, 0, 1, true );
		return $first + array( self::ENDPOINT => __( 'My readings', 'hexxsy-vendors' ) ) + array_slice( $items, 1, null, true );
	}

	/**
	 * List every reading the customer has bought, newest first.
	 */
	public static function render(): void {
		$orders = wc_get_orders(
			array(
				'customer_id' => get_current_user_id(),
				'limit'       => -1,
				'orderby'     => 'date',
				'order'       => 'DESC',
				'status'      => array( 'wc-processing', 'wc-completed', 'wc-on-hold' ),
			)
		);

		$rows = array();
		foreach ( $orders as $order ) {
			foreach ( $order->get_items() as $item ) {
				if ( '' !== (string) $item->get_meta( Order_Split::FORMAT_META ) ) {
					$rows[] = array( $order, $item );
				}
			}
		}

		if ( ! $rows ) {
			echo '<p>' . esc_html__( 'You have no readings yet.', 'hexxsy-vendors' ) . '</p>';
			return;
		}
		?>
		<table class="shop_table shop_table_responsive">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Reading', 'hexxsy-vendors' ); ?></th>
					<th><?php esc_html_e( 'Reader', 'hexxsy-vendors' ); ?></th>
					<th><?php esc_html_e( 'Ordered', 'hexxsy-vendors' ); ?></th>
					<th><?php esc_html_e( 'Status', 'hexxsy-vendors' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $rows as list( $order, $item ) ) : ?>
				<tr>
					<td data-title="<?php esc_attr_e( 'Reading', 'hexxsy-vendors' ); ?>"><a href="<?php echo esc_url( $order->get_view_order_url() ); ?>"><?php echo esc_html( $item->get_name() ); ?></a></td>
					<td data-title="<?php esc_attr_e( 'Reader', 'hexxsy-vendors' ); ?>"><?php echo esc_html( Delivery::shop_name( (int) $item->get_meta( Order_Split::VENDOR_META ) ) ); ?></td>
					<td data-title="<?php esc_attr_e( 'Ordered', 'hexxsy-vendors' ); ?>"><?php echo esc_html( wc_format_datetime( $order->get_date_created() ) ); ?></td>
					<td data-title="<?php esc_attr_e( 'Status', 'hexxsy-vendors' ); ?>"><?php self::render_status( $order, $item ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Status cell for one reading.
	 *
	 * @param \WC_Order      $order Order.
	 * @param \WC_Order_Item $item  Order line.
	 */
	private static function render_status( $order, $item ): void {
		if ( ! Delivery::is_written( $item ) ) {
			// Live calls: the booking flow will fill this in.
			esc_html_e( 'Live call', 'hexxsy-vendors' );
			return;
		}

		if ( Delivery::delivered_at( $item ) ) {
			echo '<a class="button" href="' . esc_url( Delivery::download_url( $item->get_id() ) ) . '">' . esc_html__( 'Download PDF', 'hexxsy-vendors' ) . '</a>';
			return;
		}

		$due = Delivery::due_date( $order, $item );
		echo esc_html(
			$due
				/* translators: %s: date */
				? sprintf( __( 'In progress, due %s', 'hexxsy-vendors' ), wc_format_datetime( $due ) )
				: __( 'Waiting for payment', 'hexxsy-vendors' )
		);
	}
}
