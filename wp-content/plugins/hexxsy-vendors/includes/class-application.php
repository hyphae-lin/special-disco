<?php
/**
 * Vendor application form.
 *
 * @package Hexxsy\Vendors
 */

namespace Hexxsy\Vendors;

/**
 * The [hexxsy_vendor_application] shortcode. Applicants must be logged in
 * (WooCommerce's My Account page handles registration), so every application
 * is tied to a real account.
 */
class Application {

	const ACTION = 'hexxsy_vendor_apply';

	/**
	 * Hook in.
	 */
	public static function init(): void {
		add_shortcode( 'hexxsy_vendor_application', array( __CLASS__, 'render' ) );
		add_action( 'template_redirect', array( __CLASS__, 'handle_submit' ) );
	}

	/**
	 * Render the form, or the applicant's current status.
	 */
	public static function render(): string {
		if ( ! is_user_logged_in() ) {
			$url = wc_get_page_permalink( 'myaccount' );
			return '<p>' . sprintf(
				/* translators: %s: account page URL */
				wp_kses_post( __( 'Please <a href="%s">log in or create an account</a> before applying to sell on Hexxsy.', 'hexxsy-vendors' ) ),
				esc_url( $url )
			) . '</p>';
		}

		$user_id = get_current_user_id();
		$status  = Vendor::status( $user_id );

		if ( Vendor::PENDING === $status ) {
			return '<p>' . esc_html__( 'Thank you for applying. Your application is waiting for review, and we will email you once it has been decided.', 'hexxsy-vendors' ) . '</p>';
		}
		if ( Vendor::APPROVED === $status ) {
			return '<p>' . esc_html__( 'Your vendor account is approved.', 'hexxsy-vendors' ) . '</p>';
		}
		if ( Vendor::REJECTED === $status ) {
			return '<p>' . esc_html__( 'Your application was not approved. If you think this was a mistake, please contact us.', 'hexxsy-vendors' ) . '</p>';
		}

		ob_start();
		?>
		<form method="post" class="hexxsy-vendor-application">
			<?php wp_nonce_field( self::ACTION, '_hexxsy_nonce' ); ?>
			<input type="hidden" name="hexxsy_action" value="<?php echo esc_attr( self::ACTION ); ?>">

			<p>
				<label for="hexxsy_shop_name"><?php esc_html_e( 'Shop name', 'hexxsy-vendors' ); ?></label>
				<input type="text" id="hexxsy_shop_name" name="hexxsy_shop_name" required maxlength="80">
			</p>
			<p>
				<label for="hexxsy_bio"><?php esc_html_e( 'About you', 'hexxsy-vendors' ); ?></label>
				<textarea id="hexxsy_bio" name="hexxsy_bio" rows="5" required></textarea>
			</p>
			<p>
				<label for="hexxsy_specialties"><?php esc_html_e( 'Specialties (tarot, oracle cards, runes, astrology...)', 'hexxsy-vendors' ); ?></label>
				<input type="text" id="hexxsy_specialties" name="hexxsy_specialties" maxlength="200">
			</p>
			<p>
				<label for="hexxsy_experience"><?php esc_html_e( 'Your experience as a reader', 'hexxsy-vendors' ); ?></label>
				<textarea id="hexxsy_experience" name="hexxsy_experience" rows="4"></textarea>
			</p>
			<p>
				<label>
					<input type="checkbox" name="hexxsy_agree" value="1" required>
					<?php esc_html_e( 'I understand readings on Hexxsy are sold for entertainment purposes only, and I agree to the vendor terms.', 'hexxsy-vendors' ); ?>
				</label>
			</p>
			<p><button type="submit"><?php esc_html_e( 'Apply', 'hexxsy-vendors' ); ?></button></p>
		</form>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Save a submitted application.
	 */
	public static function handle_submit(): void {
		if ( ! isset( $_POST['hexxsy_action'] ) || self::ACTION !== $_POST['hexxsy_action'] ) {
			return;
		}
		if ( ! is_user_logged_in() ) {
			return;
		}
		if ( ! isset( $_POST['_hexxsy_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_hexxsy_nonce'] ), self::ACTION ) ) {
			wc_add_notice( __( 'Your session expired. Please try again.', 'hexxsy-vendors' ), 'error' );
			return;
		}

		$user_id = get_current_user_id();

		// One application per account; admins can reset status if needed.
		if ( '' !== Vendor::status( $user_id ) ) {
			return;
		}

		$shop_name = isset( $_POST['hexxsy_shop_name'] ) ? sanitize_text_field( wp_unslash( $_POST['hexxsy_shop_name'] ) ) : '';
		$bio       = isset( $_POST['hexxsy_bio'] ) ? sanitize_textarea_field( wp_unslash( $_POST['hexxsy_bio'] ) ) : '';

		if ( '' === $shop_name || '' === $bio || empty( $_POST['hexxsy_agree'] ) ) {
			wc_add_notice( __( 'Please fill in your shop name and bio, and agree to the vendor terms.', 'hexxsy-vendors' ), 'error' );
			return;
		}

		update_user_meta( $user_id, 'hexxsy_shop_name', $shop_name );
		update_user_meta( $user_id, 'hexxsy_bio', $bio );
		update_user_meta( $user_id, 'hexxsy_specialties', isset( $_POST['hexxsy_specialties'] ) ? sanitize_text_field( wp_unslash( $_POST['hexxsy_specialties'] ) ) : '' );
		update_user_meta( $user_id, 'hexxsy_experience', isset( $_POST['hexxsy_experience'] ) ? sanitize_textarea_field( wp_unslash( $_POST['hexxsy_experience'] ) ) : '' );
		update_user_meta( $user_id, 'hexxsy_applied_at', time() );
		update_user_meta( $user_id, Vendor::STATUS_META, Vendor::PENDING );

		wp_mail(
			get_option( 'admin_email' ),
			/* translators: %s: shop name */
			sprintf( __( 'New Hexxsy vendor application: %s', 'hexxsy-vendors' ), $shop_name ),
			sprintf(
				/* translators: %s: admin URL */
				__( "A new vendor application is waiting for review:\n%s", 'hexxsy-vendors' ),
				admin_url( 'admin.php?page=' . Admin::PAGE )
			)
		);

		do_action( 'hexxsy_vendor_applied', $user_id );

		// Redirect so a refresh can't resubmit the form.
		wp_safe_redirect( add_query_arg( 'applied', '1', wp_get_referer() ? wp_get_referer() : home_url( '/' ) ) );
		exit;
	}
}
