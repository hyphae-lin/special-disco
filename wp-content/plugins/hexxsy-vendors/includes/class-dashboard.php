<?php
/**
 * Front-end vendor dashboard inside WooCommerce's My Account.
 *
 * @package Hexxsy\Vendors
 */

namespace Hexxsy\Vendors;

/**
 * Adds three My Account tabs for approved vendors: My readings, Reading
 * orders and Shop profile. Vendors never need wp-admin (WooCommerce already
 * keeps users without editing capabilities out of it).
 */
class Dashboard {

	const READINGS = 'vendor-readings';
	const ORDERS   = 'vendor-orders';
	const SHOP     = 'vendor-shop';

	const NONCE        = 'hexxsy_dashboard';
	const FLUSH_FLAG   = 'hexxsy_flush_rewrites';
	const ORDERS_SHOWN = 50;

	/**
	 * Order statuses a vendor sees, with whether they count toward earnings.
	 */
	const VISIBLE_STATUSES = array(
		'processing' => true,
		'completed'  => true,
		'on-hold'    => false,
		'cancelled'  => false,
		'refunded'   => false,
	);

	/**
	 * Hook in.
	 */
	public static function init(): void {
		add_filter( 'woocommerce_get_query_vars', array( __CLASS__, 'query_vars' ) );
		add_filter( 'woocommerce_account_menu_items', array( __CLASS__, 'menu_items' ) );
		add_filter( 'woocommerce_endpoint_' . self::READINGS . '_title', static fn() => __( 'My readings', 'hexxsy-vendors' ) );
		add_filter( 'woocommerce_endpoint_' . self::ORDERS . '_title', static fn() => __( 'Reading orders', 'hexxsy-vendors' ) );
		add_filter( 'woocommerce_endpoint_' . self::SHOP . '_title', static fn() => __( 'Shop profile', 'hexxsy-vendors' ) );

		add_action( 'woocommerce_account_' . self::READINGS . '_endpoint', array( __CLASS__, 'render_readings' ) );
		add_action( 'woocommerce_account_' . self::ORDERS . '_endpoint', array( __CLASS__, 'render_orders' ) );
		add_action( 'woocommerce_account_' . self::SHOP . '_endpoint', array( __CLASS__, 'render_shop' ) );

		add_action( 'template_redirect', array( __CLASS__, 'handle_post' ) );
		add_action( 'init', array( __CLASS__, 'maybe_flush_rewrites' ), 99 );
	}

	/**
	 * Register the endpoints with WooCommerce, which adds the rewrite rules.
	 *
	 * @param array $vars Query vars.
	 */
	public static function query_vars( $vars ) {
		foreach ( array( self::READINGS, self::ORDERS, self::SHOP ) as $endpoint ) {
			$vars[ $endpoint ] = $endpoint;
		}
		return $vars;
	}

	/**
	 * New endpoints need fresh rewrite rules once, after activation.
	 */
	public static function maybe_flush_rewrites(): void {
		if ( get_option( self::FLUSH_FLAG ) ) {
			delete_option( self::FLUSH_FLAG );
			flush_rewrite_rules();
		}
	}

	/**
	 * Show the vendor tabs to approved vendors, just after Dashboard.
	 *
	 * @param array $items Menu items.
	 */
	public static function menu_items( $items ) {
		if ( ! Vendor::is_approved( get_current_user_id() ) ) {
			return $items;
		}

		$vendor_items = array(
			self::READINGS => __( 'My readings', 'hexxsy-vendors' ),
			self::ORDERS   => __( 'Reading orders', 'hexxsy-vendors' ),
			self::SHOP     => __( 'Shop profile', 'hexxsy-vendors' ),
		);

		$first = array_slice( $items, 0, 1, true );
		return $first + $vendor_items + array_slice( $items, 1, null, true );
	}

	/**
	 * Whether the current user may use the dashboard. Prints a notice if not.
	 */
	private static function guard(): bool {
		if ( Vendor::is_approved( get_current_user_id() ) ) {
			return true;
		}
		echo '<p>' . esc_html__( 'This area is for approved Hexxsy vendors.', 'hexxsy-vendors' ) . '</p>';
		return false;
	}

	/**
	 * A product the current vendor owns, or null.
	 *
	 * @param int $product_id Product ID.
	 */
	private static function own_product( int $product_id ): ?\WC_Product {
		$product = $product_id ? wc_get_product( $product_id ) : null;
		if ( ! $product || (int) get_post_field( 'post_author', $product_id ) !== get_current_user_id() ) {
			return null;
		}
		return $product;
	}

	/**
	 * Handle dashboard form posts before any output.
	 */
	public static function handle_post(): void {
		if ( empty( $_POST['hexxsy_dashboard_action'] ) || ! is_user_logged_in() ) {
			return;
		}
		if ( ! isset( $_POST['_hexxsy_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_hexxsy_nonce'] ), self::NONCE ) ) {
			wc_add_notice( __( 'Your session expired. Please try again.', 'hexxsy-vendors' ), 'error' );
			return;
		}
		if ( ! Vendor::is_approved( get_current_user_id() ) ) {
			return;
		}

		$action = sanitize_key( $_POST['hexxsy_dashboard_action'] );
		$input  = wp_unslash( $_POST );

		switch ( $action ) {
			case 'save_reading':
				$redirect = self::save_reading( $input );
				break;
			case 'set_status':
				$redirect = self::set_reading_status( $input );
				break;
			case 'save_shop':
				$redirect = self::save_shop( $input );
				break;
			default:
				return;
		}

		if ( $redirect ) {
			wp_safe_redirect( $redirect );
			exit;
		}
	}

	/**
	 * Create or update a reading. Returns a redirect URL on success, or ''
	 * to re-show the form with errors.
	 *
	 * @param array $input Unslashed form data.
	 */
	private static function save_reading( array $input ): string {
		$product_id = isset( $input['product_id'] ) ? absint( $input['product_id'] ) : 0;

		if ( $product_id ) {
			$product = self::own_product( $product_id );
			if ( ! $product ) {
				wc_add_notice( __( 'That reading could not be found.', 'hexxsy-vendors' ), 'error' );
				return wc_get_account_endpoint_url( self::READINGS );
			}
		} else {
			// The data store sets the author to the current user on create.
			$product = new \WC_Product_Simple();
			$product->set_status( 'publish' );
		}

		$name   = isset( $input['name'] ) ? sanitize_text_field( $input['name'] ) : '';
		$price  = isset( $input['price'] ) ? wc_format_decimal( sanitize_text_field( $input['price'] ) ) : '';
		$format = isset( $input[ Readings::FORMAT_META ] ) ? sanitize_key( $input[ Readings::FORMAT_META ] ) : '';

		$errors = array();
		if ( '' === $name ) {
			$errors[] = __( 'Give your reading a name.', 'hexxsy-vendors' );
		}
		if ( '' === $price || (float) $price <= 0 ) {
			$errors[] = __( 'Set a price above zero.', 'hexxsy-vendors' );
		}
		if ( ! in_array( $format, array( Readings::WRITTEN, Readings::LIVE ), true ) ) {
			$errors[] = __( 'Choose written reading or live call.', 'hexxsy-vendors' );
		} elseif ( Readings::WRITTEN === $format && absint( $input[ Readings::TURNAROUND_META ] ?? 0 ) < 1 ) {
			$errors[] = __( 'Set how many days you need to deliver a written reading.', 'hexxsy-vendors' );
		} elseif ( Readings::LIVE === $format && absint( $input[ Readings::DURATION_META ] ?? 0 ) < 5 ) {
			$errors[] = __( 'Set the call length in minutes.', 'hexxsy-vendors' );
		}

		if ( $errors ) {
			foreach ( $errors as $error ) {
				wc_add_notice( $error, 'error' );
			}
			return '';
		}

		$product->set_name( $name );
		$product->set_description( isset( $input['description'] ) ? wp_kses_post( $input['description'] ) : '' );
		$product->set_regular_price( $price );
		Readings::apply_settings( $product, $input );

		$image_id = self::handle_image_upload();
		if ( $image_id ) {
			$product->set_image_id( $image_id );
		}

		$product->save();

		wc_add_notice( __( 'Reading saved.', 'hexxsy-vendors' ) );
		return wc_get_account_endpoint_url( self::READINGS );
	}

	/**
	 * Save an uploaded reading image to the media library. Images only.
	 *
	 * @return int Attachment ID, or 0 if nothing usable was uploaded.
	 */
	private static function handle_image_upload(): int {
		if ( empty( $_FILES['image']['name'] ) || ! current_user_can( 'upload_files' ) ) {
			return 0;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$attachment_id = media_handle_upload(
			'image',
			0,
			array(),
			array(
				'test_form' => false,
				'mimes'     => array(
					'jpg|jpeg|jpe' => 'image/jpeg',
					'png'          => 'image/png',
					'webp'         => 'image/webp',
				),
			)
		);

		if ( is_wp_error( $attachment_id ) ) {
			wc_add_notice( $attachment_id->get_error_message(), 'error' );
			return 0;
		}
		return (int) $attachment_id;
	}

	/**
	 * Pause, resume or remove a reading.
	 *
	 * @param array $input Unslashed form data.
	 */
	private static function set_reading_status( array $input ): string {
		$product = self::own_product( absint( $input['product_id'] ?? 0 ) );
		$status  = sanitize_key( $input['status'] ?? '' );

		if ( $product ) {
			if ( 'trash' === $status ) {
				$product->delete( false );
				wc_add_notice( __( 'Reading removed.', 'hexxsy-vendors' ) );
			} elseif ( in_array( $status, array( 'publish', 'draft' ), true ) ) {
				$product->set_status( $status );
				$product->save();
				wc_add_notice( 'publish' === $status ? __( 'Reading is live.', 'hexxsy-vendors' ) : __( 'Reading paused.', 'hexxsy-vendors' ) );
			}
		}
		return wc_get_account_endpoint_url( self::READINGS );
	}

	/**
	 * Save the vendor's public shop profile.
	 *
	 * @param array $input Unslashed form data.
	 */
	private static function save_shop( array $input ): string {
		$user_id   = get_current_user_id();
		$shop_name = sanitize_text_field( $input['hexxsy_shop_name'] ?? '' );

		if ( '' === $shop_name ) {
			wc_add_notice( __( 'Your shop needs a name.', 'hexxsy-vendors' ), 'error' );
			return '';
		}

		update_user_meta( $user_id, 'hexxsy_shop_name', $shop_name );
		update_user_meta( $user_id, 'hexxsy_bio', sanitize_textarea_field( $input['hexxsy_bio'] ?? '' ) );
		update_user_meta( $user_id, 'hexxsy_specialties', sanitize_text_field( $input['hexxsy_specialties'] ?? '' ) );

		wc_add_notice( __( 'Shop profile saved.', 'hexxsy-vendors' ) );
		return wc_get_account_endpoint_url( self::SHOP );
	}

	/**
	 * Hidden fields every dashboard form needs.
	 *
	 * @param string $action Dashboard action.
	 */
	private static function form_fields( string $action ): void {
		wp_nonce_field( self::NONCE, '_hexxsy_nonce' );
		echo '<input type="hidden" name="hexxsy_dashboard_action" value="' . esc_attr( $action ) . '">';
	}

	/**
	 * My readings: the list, or the add/edit form.
	 */
	public static function render_readings(): void {
		if ( ! self::guard() ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only view switches.
		if ( isset( $_GET['edit'] ) ) {
			self::render_reading_form( self::own_product( absint( $_GET['edit'] ) ) );
			return;
		}
		if ( isset( $_GET['new'] ) ) {
			self::render_reading_form( null );
			return;
		}
		// phpcs:enable

		$base     = wc_get_account_endpoint_url( self::READINGS );
		$products = wc_get_products(
			array(
				'author' => get_current_user_id(),
				'status' => array( 'publish', 'draft', 'pending' ),
				'limit'  => -1,
			)
		);

		echo '<p><a class="button" href="' . esc_url( add_query_arg( 'new', '1', $base ) ) . '">' . esc_html__( 'Add a reading', 'hexxsy-vendors' ) . '</a></p>';

		if ( ! $products ) {
			echo '<p>' . esc_html__( 'You have no readings yet.', 'hexxsy-vendors' ) . '</p>';
			return;
		}
		?>
		<table class="shop_table shop_table_responsive">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Reading', 'hexxsy-vendors' ); ?></th>
					<th><?php esc_html_e( 'Type', 'hexxsy-vendors' ); ?></th>
					<th><?php esc_html_e( 'Price', 'hexxsy-vendors' ); ?></th>
					<th><?php esc_html_e( 'Status', 'hexxsy-vendors' ); ?></th>
					<th></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $products as $product ) : ?>
				<?php $live = 'publish' === $product->get_status(); ?>
				<tr>
					<td data-title="<?php esc_attr_e( 'Reading', 'hexxsy-vendors' ); ?>"><a href="<?php echo esc_url( $product->get_permalink() ); ?>"><?php echo esc_html( $product->get_name() ); ?></a></td>
					<td data-title="<?php esc_attr_e( 'Type', 'hexxsy-vendors' ); ?>"><?php echo esc_html( self::format_label( $product ) ); ?></td>
					<td data-title="<?php esc_attr_e( 'Price', 'hexxsy-vendors' ); ?>"><?php echo wp_kses_post( wc_price( (float) $product->get_regular_price() ) ); ?></td>
					<td data-title="<?php esc_attr_e( 'Status', 'hexxsy-vendors' ); ?>"><?php echo esc_html( $live ? __( 'Live', 'hexxsy-vendors' ) : __( 'Paused', 'hexxsy-vendors' ) ); ?></td>
					<td>
						<a class="button" href="<?php echo esc_url( add_query_arg( 'edit', $product->get_id(), $base ) ); ?>"><?php esc_html_e( 'Edit', 'hexxsy-vendors' ); ?></a>
						<form method="post" style="display:inline">
							<?php self::form_fields( 'set_status' ); ?>
							<input type="hidden" name="product_id" value="<?php echo esc_attr( (string) $product->get_id() ); ?>">
							<button type="submit" class="button" name="status" value="<?php echo esc_attr( $live ? 'draft' : 'publish' ); ?>"><?php echo esc_html( $live ? __( 'Pause', 'hexxsy-vendors' ) : __( 'Resume', 'hexxsy-vendors' ) ); ?></button>
							<button type="submit" class="button" name="status" value="trash" onclick="return confirm('<?php echo esc_js( __( 'Remove this reading?', 'hexxsy-vendors' ) ); ?>');"><?php esc_html_e( 'Remove', 'hexxsy-vendors' ); ?></button>
						</form>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Human label for a reading's format.
	 *
	 * @param \WC_Product $product Product.
	 */
	private static function format_label( \WC_Product $product ): string {
		$format = Readings::format( $product );
		if ( Readings::WRITTEN === $format ) {
			/* translators: %d: days */
			return sprintf( _n( 'Written, %d day', 'Written, %d days', (int) $product->get_meta( Readings::TURNAROUND_META ), 'hexxsy-vendors' ), (int) $product->get_meta( Readings::TURNAROUND_META ) );
		}
		if ( Readings::LIVE === $format ) {
			/* translators: %d: minutes */
			return sprintf( __( 'Live call, %d min', 'hexxsy-vendors' ), (int) $product->get_meta( Readings::DURATION_META ) );
		}
		return '';
	}

	/**
	 * Add or edit form. Re-fills from the last post if validation failed.
	 *
	 * @param \WC_Product|null $product Reading being edited, or null for new.
	 */
	private static function render_reading_form( ?\WC_Product $product ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- refilling a form the nonce check already rejected or accepted.
		$posted = isset( $_POST['hexxsy_dashboard_action'] ) ? wp_unslash( $_POST ) : array();

		$value = static function ( string $key, $saved ) use ( $posted ) {
			return array_key_exists( $key, $posted ) ? $posted[ $key ] : $saved;
		};

		$format     = $value( Readings::FORMAT_META, $product ? Readings::format( $product ) : Readings::WRITTEN );
		$turnaround = $value( Readings::TURNAROUND_META, $product ? $product->get_meta( Readings::TURNAROUND_META ) : 3 );
		$duration   = $value( Readings::DURATION_META, $product ? $product->get_meta( Readings::DURATION_META ) : 30 );
		$intake     = (array) $value( Readings::INTAKE_META, $product ? Readings::intake_fields( $product ) : array() );
		?>
		<h3><?php echo esc_html( $product ? __( 'Edit reading', 'hexxsy-vendors' ) : __( 'Add a reading', 'hexxsy-vendors' ) ); ?></h3>
		<form method="post" enctype="multipart/form-data" class="hexxsy-reading-form">
			<?php self::form_fields( 'save_reading' ); ?>
			<input type="hidden" name="product_id" value="<?php echo esc_attr( $product ? (string) $product->get_id() : '0' ); ?>">

			<p class="form-row">
				<label for="hexxsy_name"><?php esc_html_e( 'Name', 'hexxsy-vendors' ); ?></label>
				<input type="text" class="input-text" id="hexxsy_name" name="name" required maxlength="120" value="<?php echo esc_attr( (string) $value( 'name', $product ? $product->get_name() : '' ) ); ?>" placeholder="<?php esc_attr_e( 'Three-card past, present, future', 'hexxsy-vendors' ); ?>">
			</p>
			<p class="form-row">
				<label for="hexxsy_description"><?php esc_html_e( 'Description', 'hexxsy-vendors' ); ?></label>
				<textarea class="input-text" id="hexxsy_description" name="description" rows="6"><?php echo esc_textarea( (string) $value( 'description', $product ? $product->get_description() : '' ) ); ?></textarea>
			</p>
			<p class="form-row">
				<label for="hexxsy_price"><?php echo esc_html( sprintf( /* translators: %s: currency symbol */ __( 'Price (%s)', 'hexxsy-vendors' ), get_woocommerce_currency_symbol() ) ); ?></label>
				<input type="number" class="input-text" id="hexxsy_price" name="price" required min="1" step="0.01" value="<?php echo esc_attr( (string) $value( 'price', $product ? $product->get_regular_price() : '' ) ); ?>">
			</p>

			<fieldset>
				<legend><?php esc_html_e( 'Type', 'hexxsy-vendors' ); ?></legend>
				<label><input type="radio" name="<?php echo esc_attr( Readings::FORMAT_META ); ?>" value="<?php echo esc_attr( Readings::WRITTEN ); ?>" <?php checked( Readings::WRITTEN, $format ); ?>> <?php esc_html_e( 'Written reading', 'hexxsy-vendors' ); ?></label><br>
				<label><input type="radio" name="<?php echo esc_attr( Readings::FORMAT_META ); ?>" value="<?php echo esc_attr( Readings::LIVE ); ?>" <?php checked( Readings::LIVE, $format ); ?>> <?php esc_html_e( 'Live call', 'hexxsy-vendors' ); ?></label>
			</fieldset>

			<p class="form-row">
				<label for="hexxsy_turnaround"><?php esc_html_e( 'Written readings: days to deliver', 'hexxsy-vendors' ); ?></label>
				<input type="number" class="input-text" id="hexxsy_turnaround" name="<?php echo esc_attr( Readings::TURNAROUND_META ); ?>" min="1" step="1" value="<?php echo esc_attr( (string) $turnaround ); ?>">
			</p>
			<fieldset>
				<legend><?php esc_html_e( 'Written readings: what to ask the customer besides their question', 'hexxsy-vendors' ); ?></legend>
				<?php foreach ( Readings::INTAKE_FIELDS as $key => $label ) : ?>
					<label><input type="checkbox" name="<?php echo esc_attr( Readings::INTAKE_META ); ?>[]" value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, $intake, true ) ); ?>> <?php echo esc_html( $label ); ?></label><br>
				<?php endforeach; ?>
			</fieldset>

			<p class="form-row">
				<label for="hexxsy_duration"><?php esc_html_e( 'Live calls: length in minutes', 'hexxsy-vendors' ); ?></label>
				<input type="number" class="input-text" id="hexxsy_duration" name="<?php echo esc_attr( Readings::DURATION_META ); ?>" min="5" step="5" value="<?php echo esc_attr( (string) $duration ); ?>">
			</p>

			<p class="form-row">
				<label for="hexxsy_image"><?php esc_html_e( 'Image (JPG, PNG or WebP)', 'hexxsy-vendors' ); ?></label>
				<?php if ( $product && $product->get_image_id() ) : ?>
					<?php echo wp_get_attachment_image( $product->get_image_id(), 'thumbnail' ); ?><br>
				<?php endif; ?>
				<input type="file" id="hexxsy_image" name="image" accept="image/jpeg,image/png,image/webp">
			</p>

			<p><button type="submit" class="button"><?php esc_html_e( 'Save reading', 'hexxsy-vendors' ); ?></button>
			<a href="<?php echo esc_url( wc_get_account_endpoint_url( self::READINGS ) ); ?>"><?php esc_html_e( 'Cancel', 'hexxsy-vendors' ); ?></a></p>
		</form>
		<?php
	}

	/**
	 * Reading orders: the vendor's own lines only, plus earnings so far.
	 */
	public static function render_orders(): void {
		if ( ! self::guard() ) {
			return;
		}

		$vendor_id = get_current_user_id();
		$orders    = wc_get_orders(
			array(
				'limit'      => self::ORDERS_SHOWN,
				'orderby'    => 'date',
				'order'      => 'DESC',
				'status'     => array_map( static fn( $s ) => 'wc-' . $s, array_keys( self::VISIBLE_STATUSES ) ),
				'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => Order_Split::ORDER_VENDOR_META,
						'value' => $vendor_id,
					),
				),
			)
		);

		$rows   = array();
		$earned = 0.0;
		foreach ( $orders as $order ) {
			foreach ( $order->get_items() as $item ) {
				if ( (int) $item->get_meta( Order_Split::VENDOR_META ) !== $vendor_id ) {
					continue;
				}
				$earnings = (float) $item->get_meta( Order_Split::EARNINGS_META );
				if ( ! empty( self::VISIBLE_STATUSES[ $order->get_status() ] ) ) {
					$earned += $earnings;
				}
				$rows[] = array( $order, $item, $earnings );
			}
		}

		echo '<p><strong>' . esc_html__( 'Earned so far:', 'hexxsy-vendors' ) . '</strong> ' . wp_kses_post( wc_price( $earned ) );
		echo ' <small>' . esc_html(
			sprintf(
				/* translators: %d: number of orders */
				__( '(paid and completed orders among your latest %d; payouts are not set up yet)', 'hexxsy-vendors' ),
				self::ORDERS_SHOWN
			)
		) . '</small></p>';

		if ( ! $rows ) {
			echo '<p>' . esc_html__( 'No orders yet.', 'hexxsy-vendors' ) . '</p>';
			return;
		}

		foreach ( $rows as list( $order, $item, $earnings ) ) {
			$details = array();
			foreach ( $item->get_formatted_meta_data() as $meta ) {
				$details[] = $meta;
			}
			?>
			<div class="hexxsy-vendor-order" style="border:1px solid currentColor;padding:12px;margin-bottom:12px;">
				<p>
					<strong><?php echo esc_html( $item->get_name() ); ?></strong><br>
					<?php
					printf(
						/* translators: 1: order number, 2: date, 3: customer first name */
						esc_html__( 'Order #%1$s, %2$s, for %3$s', 'hexxsy-vendors' ),
						esc_html( $order->get_order_number() ),
						esc_html( wc_format_datetime( $order->get_date_created() ) ),
						esc_html( $order->get_billing_first_name() ? $order->get_billing_first_name() : __( 'a customer', 'hexxsy-vendors' ) )
					);
					?>
					<br>
					<?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?>,
					<?php
					printf(
						/* translators: %s: amount */
						esc_html__( 'you earn %s', 'hexxsy-vendors' ),
						wp_kses_post( wc_price( $earnings, array( 'currency' => $order->get_currency() ) ) )
					);
					?>
				</p>
				<?php if ( $details ) : ?>
					<dl>
						<?php foreach ( $details as $meta ) : ?>
							<dt><?php echo wp_kses_post( $meta->display_key ); ?></dt>
							<dd><?php echo wp_kses_post( wpautop( $meta->display_value ) ); ?></dd>
						<?php endforeach; ?>
					</dl>
				<?php endif; ?>
			</div>
			<?php
		}
	}

	/**
	 * Shop profile form.
	 */
	public static function render_shop(): void {
		if ( ! self::guard() ) {
			return;
		}
		$user_id = get_current_user_id();
		?>
		<form method="post">
			<?php self::form_fields( 'save_shop' ); ?>
			<p class="form-row">
				<label for="hexxsy_shop_name"><?php esc_html_e( 'Shop name', 'hexxsy-vendors' ); ?></label>
				<input type="text" class="input-text" id="hexxsy_shop_name" name="hexxsy_shop_name" required maxlength="80" value="<?php echo esc_attr( (string) get_user_meta( $user_id, 'hexxsy_shop_name', true ) ); ?>">
			</p>
			<p class="form-row">
				<label for="hexxsy_bio"><?php esc_html_e( 'About you', 'hexxsy-vendors' ); ?></label>
				<textarea class="input-text" id="hexxsy_bio" name="hexxsy_bio" rows="6"><?php echo esc_textarea( (string) get_user_meta( $user_id, 'hexxsy_bio', true ) ); ?></textarea>
			</p>
			<p class="form-row">
				<label for="hexxsy_specialties"><?php esc_html_e( 'Specialties', 'hexxsy-vendors' ); ?></label>
				<input type="text" class="input-text" id="hexxsy_specialties" name="hexxsy_specialties" maxlength="200" value="<?php echo esc_attr( (string) get_user_meta( $user_id, 'hexxsy_specialties', true ) ); ?>">
			</p>
			<p><button type="submit" class="button"><?php esc_html_e( 'Save profile', 'hexxsy-vendors' ); ?></button></p>
		</form>
		<?php
	}
}
