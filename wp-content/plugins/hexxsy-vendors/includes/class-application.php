<?php
/**
 * Vendor application form.
 *
 * @package Hexxsy\Vendors
 */

namespace Hexxsy\Vendors;

/**
 * The [hexxsy_vendor_application] shortcode. Readers join by invitation only
 * (see Ledger): the form opens only for a logged-in account whose email
 * matches a valid invite. WooCommerce's My Account page handles sign-up.
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
		$token = Ledger::request_token();

		if ( ! is_user_logged_in() ) {
			$check = Ledger::check( $token, 0 );
			if ( Ledger::MISSING === $check['state'] ) {
				return self::invitation_only();
			}
			return self::message(
				sprintf(
					/* translators: 1: invited email, 2: account page URL */
					wp_kses_post( __( 'This invite is for %1$s. <a href="%2$s">Log in or make an account</a> with that email address, then come back to this page.', 'hexxsy-vendors' ) ),
					esc_html( $check['email'] ),
					esc_url( wc_get_page_permalink( 'myaccount' ) )
				)
			);
		}

		$user_id = get_current_user_id();
		$status  = Vendor::status( $user_id );

		if ( Vendor::PENDING === $status ) {
			return self::message( esc_html__( 'Your application is in. I read every one myself, and I\'ll write to you once I\'ve decided.', 'hexxsy-vendors' ) );
		}
		if ( Vendor::APPROVED === $status ) {
			return self::message( esc_html__( 'You already have a room here. Your reader tabs are in My Account.', 'hexxsy-vendors' ) );
		}

		$check = Ledger::check( $token, $user_id );
		switch ( $check['state'] ) {
			case Ledger::VALID:
				break;
			case Ledger::WRONG_EMAIL:
				return self::message(
					sprintf(
						/* translators: %s: invited email */
						esc_html__( 'This invite is for %s, and you\'re logged in with a different email address. Log out and log in with that one, or ask me to send the invite somewhere else.', 'hexxsy-vendors' ),
						esc_html( $check['email'] )
					)
				);
			case Ledger::EXPIRED:
				return self::message( esc_html__( 'This invite has gone stale. Write to me if you still want the room, and I\'ll send a fresh one.', 'hexxsy-vendors' ) );
			case Ledger::USED:
			case Ledger::CANCELLED:
				return self::message( esc_html__( 'This invite has already been used up. Write to me if that\'s a surprise.', 'hexxsy-vendors' ) );
			default:
				if ( Vendor::REJECTED === $status ) {
					return self::message( esc_html__( 'Your application wasn\'t accepted this time. If you think I got it wrong, write to me.', 'hexxsy-vendors' ) );
				}
				return self::invitation_only();
		}

		ob_start();
		// Show errors from a failed submit; ordinary pages don't print WooCommerce notices.
		if ( function_exists( 'wc_print_notices' ) ) {
			wc_print_notices();
		}
		?>
		<form method="post" class="hexxsy-form hexxsy-vendor-application">
			<?php wp_nonce_field( self::ACTION, '_hexxsy_nonce' ); ?>
			<input type="hidden" name="hexxsy_action" value="<?php echo esc_attr( self::ACTION ); ?>">
			<input type="hidden" name="hexxsy_invite" value="<?php echo esc_attr( $token ); ?>">

			<div class="hexxsy-field">
				<label for="hexxsy_shop_name"><?php esc_html_e( 'Shop name', 'hexxsy-vendors' ); ?></label>
				<input type="text" id="hexxsy_shop_name" name="hexxsy_shop_name" required maxlength="80">
			</div>
			<div class="hexxsy-field">
				<label for="hexxsy_bio"><?php esc_html_e( 'About you', 'hexxsy-vendors' ); ?></label>
				<textarea id="hexxsy_bio" name="hexxsy_bio" rows="5" required></textarea>
			</div>
			<div class="hexxsy-field">
				<label for="hexxsy_specialties"><?php esc_html_e( 'What you read', 'hexxsy-vendors' ); ?></label>
				<input type="text" id="hexxsy_specialties" name="hexxsy_specialties" maxlength="200" aria-describedby="hexxsy_specialties_hint">
				<span class="hexxsy-hint" id="hexxsy_specialties_hint"><?php esc_html_e( 'Tarot, oracle cards, runes, astrology, or whatever else you work with.', 'hexxsy-vendors' ); ?></span>
			</div>
			<div class="hexxsy-field">
				<label for="hexxsy_experience"><?php esc_html_e( 'How long you\'ve been reading, and for whom', 'hexxsy-vendors' ); ?></label>
				<textarea id="hexxsy_experience" name="hexxsy_experience" rows="4"></textarea>
			</div>
			<div class="hexxsy-field hexxsy-check">
				<input type="checkbox" id="hexxsy_agree" name="hexxsy_agree" value="1" required>
				<label for="hexxsy_agree"><?php echo wp_kses_post( self::agreement_label() ); ?></label>
			</div>
			<button type="submit" class="wp-element-button"><?php esc_html_e( 'Send my application', 'hexxsy-vendors' ); ?></button>
		</form>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Checkbox label: the code of conduct, linked when its page is chosen.
	 */
	private static function agreement_label(): string {
		$url = Ledger::page_url( Ledger::CONDUCT_OPTION );
		if ( '' === $url ) {
			return esc_html__( 'I\'ve read the house code of conduct and agree to keep it. I understand readings on Hexxsy are sold for entertainment only.', 'hexxsy-vendors' );
		}
		return sprintf(
			/* translators: %s: code of conduct URL */
			__( 'I\'ve read the <a href="%s" target="_blank" rel="noopener">house code of conduct</a> and agree to keep it. I understand readings on Hexxsy are sold for entertainment only.', 'hexxsy-vendors' ),
			esc_url( $url )
		);
	}

	/**
	 * Shown to anyone without an invite.
	 */
	private static function invitation_only(): string {
		$ledger = Ledger::page_url( Ledger::LEDGER_PAGE_OPTION );
		if ( '' === $ledger ) {
			return self::message( esc_html__( 'Hexxsy readers join by invitation. I don\'t hand out keys to just anyone.', 'hexxsy-vendors' ) );
		}
		return self::message(
			sprintf(
				/* translators: %s: ledger page URL */
				wp_kses_post( __( 'Hexxsy readers join by invitation. If you read and want a room, <a href="%s">put your name in the ledger</a>. I\'ll come find you if it fits.', 'hexxsy-vendors' ) ),
				esc_url( $ledger )
			)
		);
	}

	/**
	 * A status message in the same box as the form.
	 *
	 * @param string $html Escaped message HTML.
	 */
	private static function message( string $html ): string {
		return '<div class="hexxsy-form hexxsy-form-message"><p>' . $html . '</p></div>';
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

		// Only a new or previously rejected account, holding a valid invite.
		if ( ! in_array( Vendor::status( $user_id ), array( '', Vendor::REJECTED ), true ) ) {
			return;
		}
		$token = Ledger::request_token();
		if ( Ledger::VALID !== Ledger::check( $token, $user_id )['state'] ) {
			wc_add_notice( __( 'Your invite isn\'t valid any more, so the application wasn\'t sent.', 'hexxsy-vendors' ), 'error' );
			return;
		}

		$shop_name = isset( $_POST['hexxsy_shop_name'] ) ? sanitize_text_field( wp_unslash( $_POST['hexxsy_shop_name'] ) ) : '';
		$bio       = isset( $_POST['hexxsy_bio'] ) ? sanitize_textarea_field( wp_unslash( $_POST['hexxsy_bio'] ) ) : '';

		if ( '' === $shop_name || '' === $bio || empty( $_POST['hexxsy_agree'] ) ) {
			wc_add_notice( __( 'I need a shop name, a few words about you, and your agreement to the code of conduct.', 'hexxsy-vendors' ), 'error' );
			return;
		}

		update_user_meta( $user_id, 'hexxsy_shop_name', $shop_name );
		update_user_meta( $user_id, 'hexxsy_bio', $bio );
		update_user_meta( $user_id, 'hexxsy_specialties', isset( $_POST['hexxsy_specialties'] ) ? sanitize_text_field( wp_unslash( $_POST['hexxsy_specialties'] ) ) : '' );
		update_user_meta( $user_id, 'hexxsy_experience', isset( $_POST['hexxsy_experience'] ) ? sanitize_textarea_field( wp_unslash( $_POST['hexxsy_experience'] ) ) : '' );
		update_user_meta( $user_id, 'hexxsy_applied_at', time() );
		update_user_meta( $user_id, Vendor::STATUS_META, Vendor::PENDING );
		Ledger::use_invite( $token, $user_id );

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
