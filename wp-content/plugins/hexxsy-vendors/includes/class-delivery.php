<?php
/**
 * Written reading delivery as a PDF.
 *
 * @package Hexxsy\Vendors
 */

namespace Hexxsy\Vendors;

/**
 * The vendor writes a reading in their dashboard (saving drafts as they go),
 * then delivers it. Delivery locks the text, emails the customer a PDF, and
 * lets them download it again from their account. The PDF is built from the
 * saved text on demand, so nothing extra sits on the server.
 */
class Delivery {

	const TEXT_META      = '_hexxsy_reading_text';
	const DELIVERED_META = '_hexxsy_delivered_at';
	const DOWNLOAD_VAR   = 'hexxsy_reading_pdf';

	/**
	 * Hook in.
	 */
	public static function init(): void {
		add_action( 'template_redirect', array( __CLASS__, 'handle_download' ) );
		add_action( 'woocommerce_order_item_meta_end', array( __CLASS__, 'customer_link' ), 10, 4 );
	}

	/**
	 * Whether an order line is a written reading.
	 *
	 * @param \WC_Order_Item $item Order line.
	 */
	public static function is_written( $item ): bool {
		return Readings::WRITTEN === $item->get_meta( Order_Split::FORMAT_META );
	}

	/**
	 * Unix time the reading was delivered, or 0.
	 *
	 * @param \WC_Order_Item $item Order line.
	 */
	public static function delivered_at( $item ): int {
		return (int) $item->get_meta( self::DELIVERED_META );
	}

	/**
	 * When the reading is due: payment date plus the turnaround promised at
	 * purchase. Null until the order is paid.
	 *
	 * @param \WC_Order      $order Order.
	 * @param \WC_Order_Item $item  Order line.
	 */
	public static function due_date( $order, $item ): ?\WC_DateTime {
		$paid = $order->get_date_paid();
		$days = (int) $item->get_meta( Order_Split::TURNAROUND_META );
		if ( ! $paid || ! $days ) {
			return null;
		}
		$due = clone $paid;
		return $due->modify( '+' . $days . ' days' );
	}

	/**
	 * Download URL for a delivered reading.
	 *
	 * @param int $item_id Order item ID.
	 */
	public static function download_url( int $item_id ): string {
		return add_query_arg( self::DOWNLOAD_VAR, $item_id, home_url( '/' ) );
	}

	/**
	 * Save a draft or deliver, from the vendor dashboard. Returns the URL to
	 * redirect to.
	 *
	 * @param array $input Unslashed form data, nonce already checked.
	 */
	public static function save( array $input ): string {
		$back    = wc_get_account_endpoint_url( Dashboard::ORDERS );
		$item_id = absint( $input['item_id'] ?? 0 );
		$item    = $item_id ? \WC_Order_Factory::get_order_item( $item_id ) : false;
		$order   = $item ? wc_get_order( $item->get_order_id() ) : false;

		if ( ! $item || ! $order || (int) $item->get_meta( Order_Split::VENDOR_META ) !== get_current_user_id() || ! self::is_written( $item ) ) {
			wc_add_notice( __( 'That order could not be found.', 'hexxsy-vendors' ), 'error' );
			return $back;
		}
		if ( self::delivered_at( $item ) ) {
			wc_add_notice( __( 'This reading was already delivered.', 'hexxsy-vendors' ), 'error' );
			return $back;
		}
		if ( 'processing' !== $order->get_status() ) {
			wc_add_notice( __( 'This order is not paid, or was cancelled, so it cannot be delivered.', 'hexxsy-vendors' ), 'error' );
			return $back;
		}

		$text    = sanitize_textarea_field( (string) ( $input['reading_text'] ?? '' ) );
		$deliver = 'deliver' === ( $input['delivery_mode'] ?? '' );

		$item->update_meta_data( self::TEXT_META, $text );

		if ( ! $deliver ) {
			$item->save();
			wc_add_notice( __( 'Draft saved. The customer cannot see it yet.', 'hexxsy-vendors' ) );
			return $back . '#reading-' . $item_id;
		}

		if ( '' === trim( $text ) ) {
			$item->save();
			wc_add_notice( __( 'Write the reading before delivering it.', 'hexxsy-vendors' ), 'error' );
			return $back . '#reading-' . $item_id;
		}

		$delivered = time();
		$item->update_meta_data( self::DELIVERED_META, $delivered );
		$item->update_meta_data( Payout::RELEASE_META, Payout::release_time( $delivered ) );
		$item->save();

		$order->add_order_note(
			sprintf(
				/* translators: 1: reading name, 2: shop name */
				__( 'Written reading "%1$s" delivered by %2$s.', 'hexxsy-vendors' ),
				$item->get_name(),
				self::shop_name( get_current_user_id() )
			)
		);

		self::email_customer( $order, $item );
		self::maybe_complete( $order );

		wc_add_notice( __( 'Reading delivered. The customer has been emailed a PDF copy.', 'hexxsy-vendors' ) );
		return $back;
	}

	/**
	 * Mark the order completed once every line is a delivered written
	 * reading. Orders with live calls are left for the booking flow.
	 *
	 * @param \WC_Order $order Order.
	 */
	private static function maybe_complete( $order ): void {
		foreach ( $order->get_items() as $item ) {
			if ( ! self::is_written( $item ) || ! self::delivered_at( $item ) ) {
				return;
			}
		}
		$order->update_status( 'completed' );
	}

	/**
	 * Email the customer their reading as an attachment.
	 *
	 * @param \WC_Order      $order Order.
	 * @param \WC_Order_Item $item  Order line.
	 */
	private static function email_customer( $order, $item ): void {
		require_once ABSPATH . 'wp-admin/includes/file.php';

		$file = wp_tempnam( 'hexxsy-reading' );
		if ( ! $file ) {
			return;
		}
		$path = $file . '.pdf';
		rename( $file, $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
		file_put_contents( $path, self::build_pdf( $order, $item ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		$first = $order->get_billing_first_name();
		$body  = sprintf(
			/* translators: 1: customer first name, 2: reading name, 3: shop name */
			__( "Hi %1\$s,\n\nYour reading \"%2\$s\" from %3\$s is ready. It's attached as a PDF to keep.", 'hexxsy-vendors' ),
			$first ? $first : __( 'there', 'hexxsy-vendors' ),
			$item->get_name(),
			self::shop_name( (int) $item->get_meta( Order_Split::VENDOR_META ) )
		);
		$body .= "\n\n" . sprintf(
			/* translators: %s: account readings URL */
			__( 'All your readings are kept in your account, ready to download any time: %s', 'hexxsy-vendors' ),
			wc_get_account_endpoint_url( Customer::ENDPOINT )
		);
		$body .= "\n\n" . __( 'Readings are for entertainment purposes only.', 'hexxsy-vendors' );

		wp_mail(
			$order->get_billing_email(),
			/* translators: %s: reading name */
			sprintf( __( 'Your Hexxsy reading is ready: %s', 'hexxsy-vendors' ), $item->get_name() ),
			$body,
			'',
			array( $path )
		);

		wp_delete_file( $path );
	}

	/**
	 * Build the reading PDF.
	 *
	 * @param \WC_Order      $order Order.
	 * @param \WC_Order_Item $item  Order line.
	 */
	public static function build_pdf( $order, $item ): string {
		$pdf       = new Pdf( __( 'For entertainment purposes only.', 'hexxsy-vendors' ) . ' hexxsy.com' );
		$delivered = self::delivered_at( $item );

		$pdf->paragraph( 'Hexxsy', 22, Pdf::BOLD, 6 );
		$pdf->paragraph( $item->get_name(), 15, Pdf::BOLD, 2 );

		$byline = sprintf(
			/* translators: 1: shop name, 2: customer first name, 3: date */
			__( 'Read by %1$s for %2$s, %3$s', 'hexxsy-vendors' ),
			self::shop_name( (int) $item->get_meta( Order_Split::VENDOR_META ) ),
			$order->get_billing_first_name() ? $order->get_billing_first_name() : __( 'you', 'hexxsy-vendors' ),
			wp_date( get_option( 'date_format' ), $delivered ? $delivered : time() )
		);
		$pdf->paragraph( $byline, 10, Pdf::ITALIC, 0 );
		$pdf->rule();

		// The customer's question and other intake answers.
		foreach ( $item->get_formatted_meta_data() as $meta ) {
			$pdf->paragraph( wp_strip_all_tags( $meta->display_key ), 10, Pdf::BOLD, 2 );
			$pdf->paragraph( wp_strip_all_tags( $meta->display_value ), 10, Pdf::ITALIC, 8 );
		}
		$pdf->rule();

		$pdf->paragraph( (string) $item->get_meta( self::TEXT_META ), 11 );

		return $pdf->output();
	}

	/**
	 * Stream a reading PDF to its customer, its vendor, or a shop manager.
	 */
	public static function handle_download(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only, access is checked below.
		$item_id = isset( $_GET[ self::DOWNLOAD_VAR ] ) ? absint( $_GET[ self::DOWNLOAD_VAR ] ) : 0;
		if ( ! $item_id ) {
			return;
		}
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( wp_login_url( self::download_url( $item_id ) ) );
			exit;
		}

		$item  = \WC_Order_Factory::get_order_item( $item_id );
		$order = $item ? wc_get_order( $item->get_order_id() ) : false;
		$user  = get_current_user_id();

		$allowed = $item && $order && self::delivered_at( $item ) && (
			$order->get_customer_id() === $user
			|| (int) $item->get_meta( Order_Split::VENDOR_META ) === $user
			|| current_user_can( 'manage_woocommerce' )
		);
		if ( ! $allowed ) {
			wp_die( esc_html__( 'This reading is not available.', 'hexxsy-vendors' ), '', array( 'response' => 404 ) );
		}

		$pdf = self::build_pdf( $order, $item );

		nocache_headers();
		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: attachment; filename="hexxsy-reading-' . $order->get_order_number() . '-' . $item_id . '.pdf"' );
		header( 'Content-Length: ' . strlen( $pdf ) );
		echo $pdf; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- binary PDF.
		exit;
	}

	/**
	 * Download link under a delivered reading on the customer's order page.
	 *
	 * @param int            $item_id    Item ID.
	 * @param \WC_Order_Item $item       Order line.
	 * @param \WC_Order      $order      Order.
	 * @param bool           $plain_text Plain-text email.
	 */
	public static function customer_link( $item_id, $item, $order, $plain_text = false ): void {
		if ( $plain_text || ! is_account_page() || ! self::delivered_at( $item ) ) {
			return;
		}
		echo '<p><a class="button" href="' . esc_url( self::download_url( (int) $item_id ) ) . '">' . esc_html__( 'Download your reading (PDF)', 'hexxsy-vendors' ) . '</a></p>';
	}

	/**
	 * Vendor's shop name, falling back to their display name.
	 *
	 * @param int $vendor_id User ID.
	 */
	public static function shop_name( int $vendor_id ): string {
		$name = (string) get_user_meta( $vendor_id, 'hexxsy_shop_name', true );
		if ( '' === $name ) {
			$user = get_userdata( $vendor_id );
			$name = $user ? $user->display_name : '';
		}
		return $name;
	}

	/**
	 * The writing panel for one order line in the vendor dashboard.
	 *
	 * @param \WC_Order      $order Order.
	 * @param \WC_Order_Item $item  Order line.
	 */
	public static function render_vendor_panel( $order, $item ): void {
		if ( ! self::is_written( $item ) ) {
			return;
		}

		$delivered = self::delivered_at( $item );
		if ( $delivered ) {
			echo '<p>' . esc_html(
				sprintf(
					/* translators: %s: date */
					__( 'Delivered %s.', 'hexxsy-vendors' ),
					wp_date( get_option( 'date_format' ), $delivered )
				)
			) . ' <a href="' . esc_url( self::download_url( $item->get_id() ) ) . '">' . esc_html__( 'Download PDF', 'hexxsy-vendors' ) . '</a></p>';
			return;
		}
		if ( 'processing' !== $order->get_status() ) {
			return;
		}

		$due = self::due_date( $order, $item );
		?>
		<form method="post" id="reading-<?php echo esc_attr( (string) $item->get_id() ); ?>">
			<?php wp_nonce_field( Dashboard::NONCE, '_hexxsy_nonce' ); ?>
			<input type="hidden" name="hexxsy_dashboard_action" value="deliver_reading">
			<input type="hidden" name="item_id" value="<?php echo esc_attr( (string) $item->get_id() ); ?>">
			<?php if ( $due ) : ?>
				<p><strong><?php echo esc_html( sprintf( /* translators: %s: date */ __( 'Due %s', 'hexxsy-vendors' ), wc_format_datetime( $due ) ) ); ?></strong></p>
			<?php endif; ?>
			<p class="form-row">
				<label for="reading-text-<?php echo esc_attr( (string) $item->get_id() ); ?>"><?php esc_html_e( 'Your reading', 'hexxsy-vendors' ); ?></label>
				<textarea class="input-text" id="reading-text-<?php echo esc_attr( (string) $item->get_id() ); ?>" name="reading_text" rows="12"><?php echo esc_textarea( (string) $item->get_meta( self::TEXT_META ) ); ?></textarea>
			</p>
			<p>
				<button type="submit" class="button" name="delivery_mode" value="draft"><?php esc_html_e( 'Save draft', 'hexxsy-vendors' ); ?></button>
				<button type="submit" class="button" name="delivery_mode" value="deliver" onclick="return confirm('<?php echo esc_js( __( 'Deliver this reading? The customer gets it as a PDF and it cannot be edited afterwards.', 'hexxsy-vendors' ) ); ?>');"><?php esc_html_e( 'Deliver to customer', 'hexxsy-vendors' ); ?></button>
			</p>
		</form>
		<?php
	}
}
