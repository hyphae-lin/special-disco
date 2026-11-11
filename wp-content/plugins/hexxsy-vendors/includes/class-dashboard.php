<?php
/**
 * Front-end vendor dashboard inside WooCommerce's My Account.
 *
 * @package Hexxsy\Vendors
 */

namespace Hexxsy\Vendors;

/**
 * Adds three My Account tabs for approved vendors: Readings I offer, Reading
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
	 * Order statuses a vendor sees in Reading orders.
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
		add_filter( 'woocommerce_endpoint_' . self::READINGS . '_title', static fn() => __( 'Readings I offer', 'hexxsy-vendors' ) );
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
	 * New endpoints need fresh rewrite rules once, after activation or an update.
	 */
	public static function maybe_flush_rewrites(): void {
		// Plugin updates can add endpoints too, and don't re-run activation.
		if ( get_option( self::FLUSH_FLAG ) || HEXXSY_VENDORS_VERSION !== get_option( 'hexxsy_vendors_version' ) ) {
			delete_option( self::FLUSH_FLAG );
			update_option( 'hexxsy_vendors_version', HEXXSY_VENDORS_VERSION );
			flush_rewrite_rules();
		}
	}

	/**
	 * Show the vendor tabs to approved vendors, just after Dashboard.
	 *
	 * @param array $items Menu items.
	 */
	public static function menu_items( $items ) {
		$user_id = get_current_user_id();
		if ( Vendor::is_approved( $user_id ) ) {
			$vendor_items = array(
				self::READINGS => __( 'Readings I offer', 'hexxsy-vendors' ),
				self::ORDERS   => __( 'Reading orders', 'hexxsy-vendors' ),
				self::SHOP     => __( 'Shop profile', 'hexxsy-vendors' ),
			);
		} elseif ( Vendor::can_fulfil( $user_id ) ) {
			// Revoked, with orders still to deliver.
			$vendor_items = array( self::ORDERS => __( 'Reading orders', 'hexxsy-vendors' ) );
		} else {
			return $items;
		}

		$first = array_slice( $items, 0, 1, true );
		return $first + $vendor_items + array_slice( $items, 1, null, true );
	}

	/**
	 * Whether the current user may use the dashboard. Prints a notice if not.
	 *
	 * @param bool $fulfil_only The tab is for delivering, which a revoked
	 *                          reader finishing their orders may still do.
	 */
	private static function guard( bool $fulfil_only = false ): bool {
		$user_id = get_current_user_id();
		if ( $fulfil_only ? Vendor::can_fulfil( $user_id ) : Vendor::is_approved( $user_id ) ) {
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
		$action = sanitize_key( $_POST['hexxsy_dashboard_action'] );
		$input  = wp_unslash( $_POST );

		// A revoked reader finishing their orders can deliver and get paid, nothing else.
		$fulfilling = in_array( $action, array( 'deliver_reading', 'stripe_onboard', 'stripe_dashboard' ), true );
		if ( ! ( $fulfilling ? Vendor::can_fulfil( get_current_user_id() ) : Vendor::is_approved( get_current_user_id() ) ) ) {
			return;
		}

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
			case 'deliver_reading':
				$redirect = Delivery::save( $input );
				break;
			case 'stripe_onboard':
				$redirect = self::stripe_redirect( Stripe::onboarding_url( get_current_user_id() ) );
				break;
			case 'stripe_dashboard':
				$redirect = self::stripe_redirect( Stripe::dashboard_url( get_current_user_id() ) );
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
	 * Where to send the reader for a Stripe link: the link itself, or back
	 * to Reading orders with the error.
	 *
	 * @param string|\WP_Error $url Stripe-hosted URL.
	 */
	private static function stripe_redirect( $url ): string {
		if ( is_wp_error( $url ) ) {
			wc_add_notice(
				sprintf(
					/* translators: %s: error from Stripe */
					__( 'Stripe could not be reached: %s', 'hexxsy-vendors' ),
					$url->get_error_message()
				),
				'error'
			);
			return wc_get_account_endpoint_url( self::ORDERS );
		}
		return $url;
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
		} elseif ( Readings::LIVE === $format && ! Readings::live_enabled() && Readings::LIVE !== Readings::format( $product ) ) {
			$errors[] = __( 'Live calls are not open yet.', 'hexxsy-vendors' );
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
		update_user_meta( $user_id, Rooms::DOOR_META, mb_substr( sanitize_text_field( $input['hexxsy_door_line'] ?? '' ), 0, 140 ) );
		update_user_meta( $user_id, Rooms::HOW_META, sanitize_textarea_field( $input[ Rooms::HOW_META ] ?? '' ) );
		foreach ( array_keys( Shelves::all() ) as $shelf_key ) {
			update_user_meta( $user_id, Rooms::SHELF_NOTE . $shelf_key, mb_substr( sanitize_text_field( $input[ Rooms::SHELF_NOTE . $shelf_key ] ?? '' ), 0, 300 ) );
		}

		$portrait = self::handle_image_upload();
		if ( $portrait ) {
			update_user_meta( $user_id, Rooms::PORTRAIT_META, $portrait );
		} elseif ( ! empty( $input['hexxsy_remove_portrait'] ) ) {
			delete_user_meta( $user_id, Rooms::PORTRAIT_META );
		}

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
				<?php
				$live   = 'publish' === $product->get_status();
				$status = $live ? __( 'Live', 'hexxsy-vendors' ) : __( 'Paused', 'hexxsy-vendors' );
				if ( $live && 'live' === Readings::off_sale( $product ) ) {
					$status = __( 'Hidden until live calls open', 'hexxsy-vendors' );
				}
				?>
				<tr>
					<td data-title="<?php esc_attr_e( 'Reading', 'hexxsy-vendors' ); ?>"><a href="<?php echo esc_url( $product->get_permalink() ); ?>"><?php echo esc_html( $product->get_name() ); ?></a></td>
					<td data-title="<?php esc_attr_e( 'Type', 'hexxsy-vendors' ); ?>"><?php echo esc_html( self::format_label( $product ) ); ?></td>
					<td data-title="<?php esc_attr_e( 'Price', 'hexxsy-vendors' ); ?>"><?php echo wp_kses_post( wc_price( (float) $product->get_regular_price() ) ); ?></td>
					<td data-title="<?php esc_attr_e( 'Status', 'hexxsy-vendors' ); ?>"><?php echo esc_html( $status ); ?></td>
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

		// Live calls are offered only once they're open, or to keep editing one made earlier.
		$offer_live = Readings::live_enabled() || ( $product && Readings::LIVE === Readings::format( $product ) );
		if ( ! $offer_live ) {
			$format = Readings::WRITTEN;
		}
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
				<label for="hexxsy_before"><?php esc_html_e( 'Before you buy', 'hexxsy-vendors' ); ?></label>
				<textarea class="input-text" id="hexxsy_before" name="<?php echo esc_attr( Offering::BEFORE_META ); ?>" rows="4" aria-describedby="hexxsy_before_hint"><?php echo esc_textarea( (string) $value( Offering::BEFORE_META, $product ? $product->get_meta( Offering::BEFORE_META ) : '' ) ); ?></textarea>
				<span class="hexxsy-hint" id="hexxsy_before_hint"><?php esc_html_e( 'Anything a customer should know first: how to prepare, what you won\'t read on, what to send you.', 'hexxsy-vendors' ); ?></span>
			</p>
			<p class="form-row">
				<label for="hexxsy_price"><?php echo esc_html( sprintf( /* translators: %s: currency symbol */ __( 'Price (%s)', 'hexxsy-vendors' ), get_woocommerce_currency_symbol() ) ); ?></label>
				<input type="number" class="input-text" id="hexxsy_price" name="price" required min="1" step="0.01" value="<?php echo esc_attr( (string) $value( 'price', $product ? $product->get_regular_price() : '' ) ); ?>">
			</p>

			<?php if ( $offer_live ) : ?>
			<fieldset>
				<legend><?php esc_html_e( 'Type', 'hexxsy-vendors' ); ?></legend>
				<label><input type="radio" name="<?php echo esc_attr( Readings::FORMAT_META ); ?>" value="<?php echo esc_attr( Readings::WRITTEN ); ?>" <?php checked( Readings::WRITTEN, $format ); ?>> <?php esc_html_e( 'Written reading', 'hexxsy-vendors' ); ?></label><br>
				<label><input type="radio" name="<?php echo esc_attr( Readings::FORMAT_META ); ?>" value="<?php echo esc_attr( Readings::LIVE ); ?>" <?php checked( Readings::LIVE, $format ); ?>> <?php esc_html_e( 'Live call', 'hexxsy-vendors' ); ?></label>
				<?php if ( ! Readings::live_enabled() ) : ?>
					<br><small><?php esc_html_e( 'Live calls are not open yet, so this one is hidden from customers.', 'hexxsy-vendors' ); ?></small>
				<?php endif; ?>
			</fieldset>
			<?php else : ?>
				<input type="hidden" name="<?php echo esc_attr( Readings::FORMAT_META ); ?>" value="<?php echo esc_attr( Readings::WRITTEN ); ?>">
			<?php endif; ?>

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

			<?php if ( $offer_live ) : ?>
			<p class="form-row">
				<label for="hexxsy_duration"><?php esc_html_e( 'Live calls: length in minutes', 'hexxsy-vendors' ); ?></label>
				<input type="number" class="input-text" id="hexxsy_duration" name="<?php echo esc_attr( Readings::DURATION_META ); ?>" min="5" step="5" value="<?php echo esc_attr( (string) $duration ); ?>">
			</p>
			<?php endif; ?>

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
		if ( ! self::guard( true ) ) {
			return;
		}
		if ( ! Vendor::is_approved( get_current_user_id() ) ) {
			echo '<p><strong>' . esc_html__( 'Your room is closed. The orders below were paid for before it closed: deliver them and you will be paid as usual.', 'hexxsy-vendors' ) . '</strong></p>';
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

		$rows = array();
		foreach ( $orders as $order ) {
			foreach ( $order->get_items() as $item ) {
				if ( (int) $item->get_meta( Order_Split::VENDOR_META ) !== $vendor_id ) {
					continue;
				}
				$rows[] = array( $order, $item, (float) $item->get_meta( Order_Split::EARNINGS_META ) );
			}
		}

		self::render_payout_summary( $vendor_id );
		self::render_payout_setup( $vendor_id );

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
					(<?php echo esc_html( self::payout_label( Payout::line_state( $order, $item ) ) ); ?>)
				</p>
				<?php if ( $details ) : ?>
					<dl>
						<?php foreach ( $details as $meta ) : ?>
							<dt><?php echo wp_kses_post( $meta->display_key ); ?></dt>
							<dd><?php echo wp_kses_post( wpautop( $meta->display_value ) ); ?></dd>
						<?php endforeach; ?>
					</dl>
				<?php endif; ?>
				<?php Delivery::render_vendor_panel( $order, $item ); ?>
			</div>
			<?php
		}
	}

	/**
	 * What the reader is owed, across every order they've ever had.
	 *
	 * @param int $vendor_id Vendor.
	 */
	private static function render_payout_summary( int $vendor_id ): void {
		$totals = array(
			Payout::HELD    => 0,
			Payout::PAYABLE => 0,
			Payout::PAID    => 0,
		);
		foreach ( Payout::lines( $vendor_id ) as list( $order, $item ) ) {
			$state = Payout::line_state( $order, $item );
			if ( Payout::PAID === $state ) {
				$totals[ $state ] += Payout::to_minor( $item->get_meta( Payout::PAID_AMOUNT_META ) );
			} elseif ( isset( $totals[ $state ] ) ) {
				$totals[ $state ] += Payout::line_share( $order, $item );
			}
		}
		?>
		<p>
			<strong><?php esc_html_e( 'On hold:', 'hexxsy-vendors' ); ?></strong> <?php echo wp_kses_post( wc_price( Payout::to_decimal( $totals[ Payout::HELD ] ) ) ); ?><br>
			<strong><?php esc_html_e( 'Ready to pay:', 'hexxsy-vendors' ); ?></strong> <?php echo wp_kses_post( wc_price( Payout::to_decimal( $totals[ Payout::PAYABLE ] ) ) ); ?><br>
			<strong><?php esc_html_e( 'Paid to you:', 'hexxsy-vendors' ); ?></strong> <?php echo wp_kses_post( wc_price( Payout::to_decimal( $totals[ Payout::PAID ] ) ) ); ?>
		</p>
		<p><small>
			<?php
			printf(
				/* translators: %d: number of days */
				esc_html__( 'Hexxsy holds each payment until you deliver the reading, then for %d more days in case the customer needs a refund.', 'hexxsy-vendors' ),
				(int) Payout::hold_days()
			);
			?>
		</small></p>
		<?php
	}

	/**
	 * Stripe payout setup: start, finish, or open the reader's Stripe
	 * dashboard. Hidden until Stripe is connected on the site.
	 *
	 * @param int $vendor_id Vendor.
	 */
	private static function render_payout_setup( int $vendor_id ): void {
		if ( ! Stripe::is_configured() ) {
			return;
		}
		$has_account = '' !== Stripe::account_id( $vendor_id );
		// Stripe may have finished verifying since the reader last looked.
		$ready = Stripe::is_ready( $vendor_id ) || ( $has_account && Stripe::refresh( $vendor_id ) );
		?>
		<form method="post" style="margin-bottom:1em;">
			<?php if ( $ready ) : ?>
				<?php self::form_fields( 'stripe_dashboard' ); ?>
				<p>
					<?php esc_html_e( 'Your earnings are sent to your Stripe account once each hold ends.', 'hexxsy-vendors' ); ?>
					<button type="submit" class="button"><?php esc_html_e( 'Open my Stripe dashboard', 'hexxsy-vendors' ); ?></button>
				</p>
			<?php else : ?>
				<?php self::form_fields( 'stripe_onboard' ); ?>
				<p>
					<?php esc_html_e( 'To get paid, set up a free Stripe account. Stripe asks for your ID, bank details and tax information, and sends your 1099.', 'hexxsy-vendors' ); ?>
					<button type="submit" class="button button-primary"><?php echo esc_html( $has_account ? __( 'Finish setting up payouts', 'hexxsy-vendors' ) : __( 'Set up payouts', 'hexxsy-vendors' ) ); ?></button>
				</p>
			<?php endif; ?>
			<?php if ( 'test' === Stripe::mode() ) : ?>
				<p><small><?php esc_html_e( 'Stripe is in test mode. No real money moves.', 'hexxsy-vendors' ); ?></small></p>
			<?php endif; ?>
		</form>
		<?php
	}

	/**
	 * Reader-facing name for a payout state.
	 *
	 * @param string $state Payout state.
	 */
	private static function payout_label( string $state ): string {
		$labels = array(
			Payout::HELD    => __( 'on hold', 'hexxsy-vendors' ),
			Payout::PAYABLE => __( 'ready to pay', 'hexxsy-vendors' ),
			Payout::PAID    => __( 'paid to you', 'hexxsy-vendors' ),
			Payout::VOID    => __( 'refunded or cancelled, nothing owed', 'hexxsy-vendors' ),
		);
		return $labels[ $state ] ?? '';
	}

	/**
	 * Shop profile form.
	 */
	public static function render_shop(): void {
		if ( ! self::guard() ) {
			return;
		}
		$user_id  = get_current_user_id();
		$portrait = (int) get_user_meta( $user_id, Rooms::PORTRAIT_META, true );
		?>
		<form method="post" enctype="multipart/form-data">
			<?php self::form_fields( 'save_shop' ); ?>
			<p class="form-row">
				<label for="hexxsy_shop_name"><?php esc_html_e( 'Shop name', 'hexxsy-vendors' ); ?></label>
				<input type="text" class="input-text" id="hexxsy_shop_name" name="hexxsy_shop_name" required maxlength="80" value="<?php echo esc_attr( (string) get_user_meta( $user_id, 'hexxsy_shop_name', true ) ); ?>">
			</p>
			<p class="form-row">
				<label for="hexxsy_bio"><?php esc_html_e( 'Introduction', 'hexxsy-vendors' ); ?></label>
				<textarea class="input-text" id="hexxsy_bio" name="hexxsy_bio" rows="6"><?php echo esc_textarea( (string) get_user_meta( $user_id, 'hexxsy_bio', true ) ); ?></textarea>
			</p>
			<p class="form-row">
				<label for="hexxsy_how_i_read"><?php esc_html_e( 'How I read', 'hexxsy-vendors' ); ?></label>
				<textarea class="input-text" id="hexxsy_how_i_read" name="<?php echo esc_attr( Rooms::HOW_META ); ?>" rows="4" aria-describedby="hexxsy_how_hint"><?php echo esc_textarea( (string) get_user_meta( $user_id, Rooms::HOW_META, true ) ); ?></textarea>
				<span class="hexxsy-hint" id="hexxsy_how_hint"><?php esc_html_e( 'Shown in your room under your introduction. Your decks, your method, what a reading with you is like.', 'hexxsy-vendors' ); ?></span>
			</p>
			<p class="form-row">
				<label for="hexxsy_specialties"><?php esc_html_e( 'Specialties', 'hexxsy-vendors' ); ?></label>
				<input type="text" class="input-text" id="hexxsy_specialties" name="hexxsy_specialties" maxlength="200" value="<?php echo esc_attr( (string) get_user_meta( $user_id, 'hexxsy_specialties', true ) ); ?>">
			</p>
			<p class="form-row">
				<label for="hexxsy_door_line"><?php esc_html_e( 'Door line', 'hexxsy-vendors' ); ?></label>
				<input type="text" class="input-text" id="hexxsy_door_line" name="hexxsy_door_line" maxlength="140" aria-describedby="hexxsy_door_line_hint" value="<?php echo esc_attr( (string) get_user_meta( $user_id, Rooms::DOOR_META, true ) ); ?>">
				<span class="hexxsy-hint" id="hexxsy_door_line_hint"><?php esc_html_e( 'One sentence on your room\'s card on the homepage. 140 characters at most.', 'hexxsy-vendors' ); ?></span>
			</p>
			<p class="form-row">
				<label for="hexxsy_portrait"><?php esc_html_e( 'Portrait', 'hexxsy-vendors' ); ?></label>
				<?php if ( $portrait && wp_attachment_is_image( $portrait ) ) : ?>
					<?php echo wp_get_attachment_image( $portrait, 'thumbnail', false, array( 'alt' => '' ) ); ?><br>
					<label><input type="checkbox" name="hexxsy_remove_portrait" value="1"> <?php esc_html_e( 'Remove this portrait', 'hexxsy-vendors' ); ?></label><br>
				<?php endif; ?>
				<input type="file" id="hexxsy_portrait" name="image" accept="image/jpeg,image/png,image/webp" aria-describedby="hexxsy_portrait_hint">
				<span class="hexxsy-hint" id="hexxsy_portrait_hint"><?php esc_html_e( 'Shown on your room\'s card. A JPG, PNG or WebP, at least 600 pixels wide. Taller than wide crops best.', 'hexxsy-vendors' ); ?></span>
			</p>
			<?php foreach ( Shelves::all() as $shelf_key => $shelf ) : ?>
				<p class="form-row">
					<label for="<?php echo esc_attr( Rooms::SHELF_NOTE . $shelf_key ); ?>">
						<?php
						/* translators: %s: shelf name, e.g. Letters */
						echo esc_html( sprintf( __( 'A note on your %s', 'hexxsy-vendors' ), $shelf['name'] ) );
						?>
					</label>
					<input type="text" class="input-text" id="<?php echo esc_attr( Rooms::SHELF_NOTE . $shelf_key ); ?>" name="<?php echo esc_attr( Rooms::SHELF_NOTE . $shelf_key ); ?>" maxlength="300" value="<?php echo esc_attr( (string) get_user_meta( $user_id, Rooms::SHELF_NOTE . $shelf_key, true ) ); ?>">
				</p>
			<?php endforeach; ?>
			<p class="hexxsy-hint"><?php esc_html_e( 'Optional. Each note shows in your room above that shelf, when you have something on it.', 'hexxsy-vendors' ); ?></p>
			<p><button type="submit" class="button"><?php esc_html_e( 'Save profile', 'hexxsy-vendors' ); ?></button></p>
		</form>
		<?php
	}
}
