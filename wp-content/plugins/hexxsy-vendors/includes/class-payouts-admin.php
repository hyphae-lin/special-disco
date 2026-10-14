<?php
/**
 * Admin screen for paying readers.
 *
 * @package Hexxsy\Vendors
 */

namespace Hexxsy\Vendors;

/**
 * Adds WooCommerce > Payouts: the hold period, what each reader is owed,
 * and a button to record a payout once it's been sent by hand.
 */
class Payouts_Admin {

	const PAGE   = 'hexxsy-payouts';
	const ACTION = 'hexxsy_mark_paid';

	/**
	 * Hook in.
	 */
	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 61 );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle_mark_paid' ) );
	}

	/**
	 * Register the submenu.
	 */
	public static function menu(): void {
		add_submenu_page(
			'woocommerce',
			__( 'Payouts', 'hexxsy-vendors' ),
			__( 'Payouts', 'hexxsy-vendors' ),
			Admin::CAPABILITY,
			self::PAGE,
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Register the hold period setting.
	 */
	public static function register_settings(): void {
		register_setting(
			self::PAGE,
			Payout::HOLD_OPTION,
			array(
				'type'              => 'integer',
				'default'           => Payout::DEFAULT_HOLD_DAYS,
				'sanitize_callback' => static fn( $value ) => max( 0, (int) $value ),
			)
		);
	}

	/**
	 * Render the Payouts page.
	 */
	public static function render_page(): void {
		if ( ! current_user_can( Admin::CAPABILITY ) ) {
			return;
		}

		$as_of    = time();
		$vendors  = array();
		$overpaid = array();
		foreach ( Payout::lines() as list( $order, $item ) ) {
			$vendor_id = (int) $item->get_meta( Order_Split::VENDOR_META );
			$vendors[ $vendor_id ] ??= array(
				Payout::HELD    => 0,
				Payout::PAYABLE => 0,
			);
			$state = Payout::line_state( $order, $item, $as_of );
			if ( isset( $vendors[ $vendor_id ][ $state ] ) ) {
				$vendors[ $vendor_id ][ $state ] += Payout::line_share( $order, $item );
			}
			$owed_back = Payout::overpaid( $order, $item );
			if ( $owed_back ) {
				$overpaid[] = array( $order, $item, $vendor_id, $owed_back );
			}
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Payouts', 'hexxsy-vendors' ); ?></h1>
			<p><?php esc_html_e( 'Hexxsy holds each payment. A written reading becomes payable once it is delivered and the hold period has passed. Live calls stay on hold until booking exists. Send the money yourself, then click Mark paid to record it.', 'hexxsy-vendors' ); ?></p>

			<form method="post" action="options.php">
				<?php settings_fields( self::PAGE ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="hexxsy_payout_hold_days"><?php esc_html_e( 'Hold after delivery (days)', 'hexxsy-vendors' ); ?></label></th>
						<td>
							<input type="number" id="hexxsy_payout_hold_days" name="<?php echo esc_attr( Payout::HOLD_OPTION ); ?>" value="<?php echo esc_attr( (string) Payout::hold_days() ); ?>" min="0" step="1" class="small-text">
							<p class="description"><?php esc_html_e( 'Applies to readings delivered from now on. Earlier readings keep the date they were given.', 'hexxsy-vendors' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Save hold period', 'hexxsy-vendors' ) ); ?>
			</form>

			<h2><?php esc_html_e( 'Owed to readers', 'hexxsy-vendors' ); ?></h2>
			<?php if ( ! $vendors ) : ?>
				<p><?php esc_html_e( 'Nothing yet.', 'hexxsy-vendors' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Reader', 'hexxsy-vendors' ); ?></th>
							<th><?php esc_html_e( 'On hold', 'hexxsy-vendors' ); ?></th>
							<th><?php esc_html_e( 'Ready to pay', 'hexxsy-vendors' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'hexxsy-vendors' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $vendors as $vendor_id => $totals ) : ?>
						<?php $payable = Payout::to_decimal( $totals[ Payout::PAYABLE ] ); ?>
						<tr>
							<td><?php echo esc_html( self::reader_name( $vendor_id ) ); ?></td>
							<td><?php echo wp_kses_post( wc_price( Payout::to_decimal( $totals[ Payout::HELD ] ) ) ); ?></td>
							<td><strong><?php echo wp_kses_post( wc_price( $payable ) ); ?></strong></td>
							<td>
								<?php if ( $totals[ Payout::PAYABLE ] > 0 ) : ?>
									<a class="button button-primary" href="<?php echo esc_url( self::mark_paid_url( $vendor_id, $as_of ) ); ?>" onclick="return confirm('<?php echo esc_js( sprintf( /* translators: 1: amount, 2: reader */ __( 'Record %1$s as paid to %2$s? Only do this once the money has been sent.', 'hexxsy-vendors' ), wp_strip_all_tags( wc_price( $payable ) ), self::reader_name( $vendor_id ) ) ); ?>');"><?php esc_html_e( 'Mark paid', 'hexxsy-vendors' ); ?></a>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<?php if ( $overpaid ) : ?>
				<h2><?php esc_html_e( 'Refunded after payout', 'hexxsy-vendors' ); ?></h2>
				<p><?php esc_html_e( 'These customers were refunded after the reader had been paid. Hexxsy covered the refund; recover it from the reader by hand.', 'hexxsy-vendors' ); ?></p>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Order', 'hexxsy-vendors' ); ?></th>
							<th><?php esc_html_e( 'Reading', 'hexxsy-vendors' ); ?></th>
							<th><?php esc_html_e( 'Reader', 'hexxsy-vendors' ); ?></th>
							<th><?php esc_html_e( 'Overpaid', 'hexxsy-vendors' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $overpaid as list( $order, $item, $vendor_id, $amount ) ) : ?>
						<tr>
							<td><a href="<?php echo esc_url( $order->get_edit_order_url() ); ?>">#<?php echo esc_html( $order->get_order_number() ); ?></a></td>
							<td><?php echo esc_html( $item->get_name() ); ?></td>
							<td><?php echo esc_html( self::reader_name( $vendor_id ) ); ?></td>
							<td><?php echo wp_kses_post( wc_price( Payout::to_decimal( $amount ) ) ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Shop name, falling back to the account email for readers without one.
	 *
	 * @param int $vendor_id Vendor.
	 */
	private static function reader_name( int $vendor_id ): string {
		$shop = (string) get_user_meta( $vendor_id, 'hexxsy_shop_name', true );
		if ( '' !== $shop ) {
			return $shop;
		}
		$user = get_userdata( $vendor_id );
		return $user ? $user->user_email : '#' . $vendor_id;
	}

	/**
	 * Signed URL for recording a payout.
	 *
	 * @param int $vendor_id Vendor.
	 * @param int $as_of     Time the page was rendered.
	 */
	private static function mark_paid_url( int $vendor_id, int $as_of ): string {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action'  => self::ACTION,
					'user_id' => $vendor_id,
					'as_of'   => $as_of,
				),
				admin_url( 'admin-post.php' )
			),
			self::ACTION . '_' . $vendor_id
		);
	}

	/**
	 * Record a payout.
	 */
	public static function handle_mark_paid(): void {
		$vendor_id = isset( $_GET['user_id'] ) ? absint( $_GET['user_id'] ) : 0;

		if ( ! current_user_can( Admin::CAPABILITY ) ) {
			wp_die( esc_html__( 'You are not allowed to record payouts.', 'hexxsy-vendors' ), 403 );
		}
		check_admin_referer( self::ACTION . '_' . $vendor_id );

		$as_of = isset( $_GET['as_of'] ) ? min( time(), absint( $_GET['as_of'] ) ) : 0;
		if ( $vendor_id && $as_of ) {
			Payout::mark_paid( $vendor_id, $as_of );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE ) );
		exit;
	}
}
