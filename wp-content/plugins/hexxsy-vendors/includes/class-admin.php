<?php
/**
 * Admin screens: vendor review and commission settings.
 *
 * @package Hexxsy\Vendors
 */

namespace Hexxsy\Vendors;

/**
 * Adds WooCommerce > Vendors, where the site owner reviews applications and
 * sets the default commission, plus a per-vendor override on the user profile.
 */
class Admin {

	const PAGE       = 'hexxsy-vendors';
	const CAPABILITY = 'manage_woocommerce';
	const ACTION     = 'hexxsy_vendor_review';

	/**
	 * Hook in.
	 */
	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 60 );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle_review' ) );

		add_action( 'show_user_profile', array( __CLASS__, 'profile_fields' ) );
		add_action( 'edit_user_profile', array( __CLASS__, 'profile_fields' ) );
		add_action( 'personal_options_update', array( __CLASS__, 'save_profile_fields' ) );
		add_action( 'edit_user_profile_update', array( __CLASS__, 'save_profile_fields' ) );
	}

	/**
	 * Register the submenu.
	 */
	public static function menu(): void {
		add_submenu_page(
			'woocommerce',
			__( 'Vendors', 'hexxsy-vendors' ),
			__( 'Vendors', 'hexxsy-vendors' ),
			self::CAPABILITY,
			self::PAGE,
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Register the default commission setting.
	 */
	public static function register_settings(): void {
		register_setting(
			self::PAGE,
			Commission::OPTION,
			array(
				'type'              => 'number',
				'default'           => Commission::DEFAULT_RATE,
				'sanitize_callback' => static fn( $value ) => Commission::clamp_rate( (float) $value ),
			)
		);
		register_setting(
			self::PAGE,
			Welcome_Offer::EMAILS_OPTION,
			array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => static fn( $value ) => implode( ', ', Welcome_Offer::parse_ids( (string) $value ) ),
			)
		);
	}

	/**
	 * Show whether the welcome coupon's minimum spend keeps its discount
	 * inside the commission.
	 */
	private static function render_welcome_check(): void {
		$rate   = Welcome_Offer::lowest_rate();
		$coupon = Welcome_Offer::latest_coupon();
		?>
		<h2><?php esc_html_e( 'Welcome offer check', 'hexxsy-vendors' ); ?></h2>
		<p>
			<?php
			printf(
				/* translators: %s: commission percentage */
				esc_html__( 'Lowest commission in force: %s%%. A fixed welcome discount fits inside the commission when the minimum spend is at least the discount divided by this rate.', 'hexxsy-vendors' ),
				esc_html( wc_format_localized_decimal( $rate ) )
			);
			?>
		</p>
		<?php
		if ( ! $coupon ) {
			echo '<p>' . esc_html__( 'No welcome coupon has been generated yet. Send yourself the welcome email, then check back here.', 'hexxsy-vendors' ) . '</p>';
			return;
		}

		$factor   = 10 ** wc_get_price_decimals();
		$amount   = (float) $coupon->get_amount();
		$minimum  = (float) $coupon->get_minimum_amount();
		$type     = $coupon->get_discount_type();
		$problems = array();

		if ( 'percent' === $type ) {
			if ( $amount > $rate ) {
				/* translators: 1: discount percentage, 2: commission percentage */
				$problems[] = sprintf( __( 'The discount (%1$s%%) is more than the lowest commission (%2$s%%). Some lines will get a smaller discount.', 'hexxsy-vendors' ), wc_format_localized_decimal( $amount ), wc_format_localized_decimal( $rate ) );
			}
		} else {
			if ( 'fixed_cart' !== $type ) {
				$problems[] = __( 'The coupon is not a "Fixed cart discount". Set the coupon block\'s discount type to Fixed cart discount.', 'hexxsy-vendors' );
			}
			$safe = Welcome_Offer::safe_minimum( (int) round( $amount * $factor ), $rate );
			if ( null === $safe ) {
				$problems[] = __( 'A commission of 0% leaves no room for any welcome discount.', 'hexxsy-vendors' );
			} elseif ( (int) round( $minimum * $factor ) < $safe ) {
				/* translators: 1: current minimum spend, 2: safe minimum spend */
				$problems[] = sprintf( __( 'The minimum spend (%1$s) is below the safe minimum (%2$s). Some customers will get less than the full discount.', 'hexxsy-vendors' ), wp_strip_all_tags( wc_price( $minimum ) ), wp_strip_all_tags( wc_price( $safe / $factor ) ) );
			}
		}

		printf(
			'<p>%s</p>',
			esc_html(
				sprintf(
					/* translators: 1: coupon code, 2: amount, 3: minimum spend */
					__( 'Latest welcome coupon: %1$s, %2$s off, minimum spend %3$s.', 'hexxsy-vendors' ),
					$coupon->get_code(),
					'percent' === $type ? wc_format_localized_decimal( $amount ) . '%' : wp_strip_all_tags( wc_price( $amount ) ),
					$minimum > 0 ? wp_strip_all_tags( wc_price( $minimum ) ) : __( 'none', 'hexxsy-vendors' )
				)
			)
		);

		if ( $problems ) {
			echo '<div class="notice notice-warning inline">';
			foreach ( $problems as $problem ) {
				echo '<p>' . esc_html( $problem ) . '</p>';
			}
			echo '<p>' . esc_html__( 'Fix it in MailPoet\'s coupon block. Codes already sent keep their old settings.', 'hexxsy-vendors' ) . '</p></div>';
		} else {
			echo '<p>' . esc_html__( 'Settings are safe.', 'hexxsy-vendors' ) . '</p>';
		}
	}

	/**
	 * Render the Vendors page.
	 */
	public static function render_page(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		$groups = array(
			Vendor::PENDING  => __( 'Waiting for review', 'hexxsy-vendors' ),
			Vendor::APPROVED => __( 'Approved vendors', 'hexxsy-vendors' ),
			Vendor::REJECTED => __( 'Rejected', 'hexxsy-vendors' ),
		);
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Vendors', 'hexxsy-vendors' ); ?></h1>

			<form method="post" action="options.php">
				<?php settings_fields( self::PAGE ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="hexxsy_commission_rate"><?php esc_html_e( 'Default commission (%)', 'hexxsy-vendors' ); ?></label></th>
						<td>
							<input type="number" id="hexxsy_commission_rate" name="<?php echo esc_attr( Commission::OPTION ); ?>" value="<?php echo esc_attr( (string) Commission::default_rate() ); ?>" min="0" max="100" step="0.01" class="small-text">
							<p class="description"><?php esc_html_e( 'The site keeps this share of each sale. You can override it per vendor on their user profile.', 'hexxsy-vendors' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="hexxsy_welcome_email_ids"><?php esc_html_e( 'Welcome offer emails', 'hexxsy-vendors' ); ?></label></th>
						<td>
							<input type="text" id="hexxsy_welcome_email_ids" name="<?php echo esc_attr( Welcome_Offer::EMAILS_OPTION ); ?>" value="<?php echo esc_attr( implode( ', ', Welcome_Offer::email_ids() ) ); ?>" class="regular-text">
							<p class="description"><?php esc_html_e( 'MailPoet email IDs whose generated coupons are welcome offers, separated by commas. The ID is the number in the email\'s address in MailPoet. Leave empty to treat every coupon MailPoet generates as a welcome offer.', 'hexxsy-vendors' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Save settings', 'hexxsy-vendors' ) ); ?>
			</form>

			<?php self::render_welcome_check(); ?>

			<?php foreach ( $groups as $status => $heading ) : ?>
				<?php
				$users = get_users(
					array(
						'meta_key'   => Vendor::STATUS_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
						'meta_value' => $status, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
						'orderby'    => 'registered',
						'order'      => 'DESC',
					)
				);
				?>
				<h2><?php echo esc_html( $heading ); ?> (<?php echo count( $users ); ?>)</h2>
				<?php if ( ! $users ) : ?>
					<p><?php esc_html_e( 'None.', 'hexxsy-vendors' ); ?></p>
					<?php continue; ?>
				<?php endif; ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Shop', 'hexxsy-vendors' ); ?></th>
							<th><?php esc_html_e( 'Account', 'hexxsy-vendors' ); ?></th>
							<th><?php esc_html_e( 'Specialties', 'hexxsy-vendors' ); ?></th>
							<th><?php esc_html_e( 'About', 'hexxsy-vendors' ); ?></th>
							<th><?php esc_html_e( 'Commission', 'hexxsy-vendors' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'hexxsy-vendors' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $users as $user ) : ?>
						<tr>
							<td><strong><?php echo esc_html( get_user_meta( $user->ID, 'hexxsy_shop_name', true ) ); ?></strong></td>
							<td><a href="<?php echo esc_url( get_edit_user_link( $user->ID ) ); ?>"><?php echo esc_html( $user->user_email ); ?></a></td>
							<td><?php echo esc_html( get_user_meta( $user->ID, 'hexxsy_specialties', true ) ); ?></td>
							<td>
								<?php echo esc_html( wp_trim_words( (string) get_user_meta( $user->ID, 'hexxsy_bio', true ), 40 ) ); ?>
								<?php $experience = (string) get_user_meta( $user->ID, 'hexxsy_experience', true ); ?>
								<?php if ( '' !== $experience ) : ?>
									<br><em><?php echo esc_html( wp_trim_words( $experience, 40 ) ); ?></em>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( Commission::rate_for_vendor( $user->ID ) . '%' ); ?></td>
							<td>
								<?php if ( Vendor::APPROVED !== $status ) : ?>
									<a class="button button-primary" href="<?php echo esc_url( self::review_url( $user->ID, 'approve' ) ); ?>"><?php esc_html_e( 'Approve', 'hexxsy-vendors' ); ?></a>
								<?php endif; ?>
								<?php if ( Vendor::REJECTED !== $status ) : ?>
									<a class="button" href="<?php echo esc_url( self::review_url( $user->ID, 'reject' ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Reject this vendor? They will be emailed.', 'hexxsy-vendors' ) ); ?>');">
										<?php echo esc_html( Vendor::APPROVED === $status ? __( 'Revoke', 'hexxsy-vendors' ) : __( 'Reject', 'hexxsy-vendors' ) ); ?>
									</a>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Signed URL for approving or rejecting a vendor.
	 *
	 * @param int    $user_id  Vendor.
	 * @param string $decision approve|reject.
	 */
	private static function review_url( int $user_id, string $decision ): string {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action'   => self::ACTION,
					'user_id'  => $user_id,
					'decision' => $decision,
				),
				admin_url( 'admin-post.php' )
			),
			self::ACTION . '_' . $user_id
		);
	}

	/**
	 * Apply an approve or reject decision.
	 */
	public static function handle_review(): void {
		$user_id = isset( $_GET['user_id'] ) ? absint( $_GET['user_id'] ) : 0;

		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You are not allowed to review vendors.', 'hexxsy-vendors' ), 403 );
		}
		check_admin_referer( self::ACTION . '_' . $user_id );

		$decision = isset( $_GET['decision'] ) ? sanitize_key( $_GET['decision'] ) : '';
		if ( 'approve' === $decision ) {
			Vendor::approve( $user_id );
		} elseif ( 'reject' === $decision ) {
			Vendor::reject( $user_id );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE ) );
		exit;
	}

	/**
	 * Vendor details and commission override on the user profile.
	 *
	 * @param \WP_User $user Profile being edited.
	 */
	public static function profile_fields( \WP_User $user ): void {
		if ( ! current_user_can( self::CAPABILITY ) || '' === Vendor::status( $user->ID ) ) {
			return;
		}
		$override = get_user_meta( $user->ID, Commission::USER_META, true );
		?>
		<h2><?php esc_html_e( 'Hexxsy vendor', 'hexxsy-vendors' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th><?php esc_html_e( 'Status', 'hexxsy-vendors' ); ?></th>
				<td><?php echo esc_html( ucfirst( Vendor::status( $user->ID ) ) ); ?></td>
			</tr>
			<tr>
				<th><label for="hexxsy_commission_override"><?php esc_html_e( 'Commission override (%)', 'hexxsy-vendors' ); ?></label></th>
				<td>
					<?php wp_nonce_field( 'hexxsy_commission_override', '_hexxsy_commission_nonce' ); ?>
					<input type="number" id="hexxsy_commission_override" name="hexxsy_commission_override" value="<?php echo esc_attr( (string) $override ); ?>" min="0" max="100" step="0.01" class="small-text">
					<p class="description">
						<?php
						printf(
							/* translators: %s: default commission percentage */
							esc_html__( 'Leave blank to use the site default (%s%%).', 'hexxsy-vendors' ),
							esc_html( (string) Commission::default_rate() )
						);
						?>
					</p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Save the commission override.
	 *
	 * @param int $user_id Profile being saved.
	 */
	public static function save_profile_fields( int $user_id ): void {
		if ( ! current_user_can( self::CAPABILITY ) || ! isset( $_POST['hexxsy_commission_override'] ) ) {
			return;
		}
		if ( ! isset( $_POST['_hexxsy_commission_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_hexxsy_commission_nonce'] ), 'hexxsy_commission_override' ) ) {
			return;
		}

		$raw = trim( sanitize_text_field( wp_unslash( $_POST['hexxsy_commission_override'] ) ) );
		if ( '' === $raw || ! is_numeric( $raw ) ) {
			delete_user_meta( $user_id, Commission::USER_META );
			return;
		}

		update_user_meta( $user_id, Commission::USER_META, Commission::clamp_rate( (float) $raw ) );
	}
}
