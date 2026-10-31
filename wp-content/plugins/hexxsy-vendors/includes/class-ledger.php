<?php
/**
 * The Ledger: reader seats, invitations and the waitlist.
 *
 * @package Hexxsy\Vendors
 */

namespace Hexxsy\Vendors;

/**
 * Readers join by invitation only. People who'd like a room sign the
 * waitlist ([hexxsy_waitlist]). The admin invites them from WooCommerce >
 * Vendors; the invite emails a single-use link that opens the application
 * form for the account with that email address.
 *
 * Seats: approved readers, applications waiting for review and open invites
 * together can't exceed the "Reader seats" setting, so the admin never
 * invites more people than there's room for. Revoking a reader, rejecting an
 * application, or an invite expiring or being cancelled frees a seat.
 *
 * The pure functions run without WordPress, for tests.
 */
class Ledger {

	const SEATS_OPTION       = 'hexxsy_reader_seats';
	const DEFAULT_SEATS      = 12;
	const INVITES_OPTION     = 'hexxsy_invites';
	const INVITE_DAYS        = 14;
	const APPLY_PAGE_OPTION  = 'hexxsy_application_page';
	const LEDGER_PAGE_OPTION = 'hexxsy_ledger_page';
	const CONDUCT_OPTION     = 'hexxsy_conduct_page';

	const WAITLIST_TYPE = 'hexxsy_waitlist';
	const ACTION        = 'hexxsy_ledger';
	const SIGN_ACTION   = 'hexxsy_sign_ledger';
	const COOKIE        = 'hexxsy_invite';
	const QUERY_ARG     = 'invite';

	const VALID       = 'valid';
	const MISSING     = 'missing';
	const EXPIRED     = 'expired';
	const USED        = 'used';
	const CANCELLED   = 'cancelled';
	const WRONG_EMAIL = 'wrong_email';

	/**
	 * Hook in.
	 */
	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'register_type' ) );
		add_shortcode( 'hexxsy_waitlist', array( __CLASS__, 'render_waitlist' ) );
		add_action( 'template_redirect', array( __CLASS__, 'remember_invite' ), 5 );
		add_action( 'template_redirect', array( __CLASS__, 'handle_sign' ) );
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle_admin' ) );
	}

	// ---------------------------------------------------------------------
	// Pure logic.
	// ---------------------------------------------------------------------

	/**
	 * Seats left to invite into.
	 *
	 * @param int $seats        Reader seats setting.
	 * @param int $approved     Approved readers.
	 * @param int $pending      Applications waiting for review.
	 * @param int $open_invites Unused, unexpired invites.
	 */
	public static function seats_free( int $seats, int $approved, int $pending, int $open_invites ): int {
		return max( 0, $seats - $approved - $pending - $open_invites );
	}

	/**
	 * Lowercased, trimmed email for comparing.
	 *
	 * @param string $email Email.
	 */
	public static function normalize_email( string $email ): string {
		return strtolower( trim( $email ) );
	}

	/**
	 * Whether an invite is usable by someone with this email right now.
	 *
	 * @param array|null $invite Stored invite, or null if none matched.
	 * @param string     $email  The account's email.
	 * @param int        $now    Unix time.
	 */
	public static function invite_state( ?array $invite, string $email, int $now ): string {
		if ( ! $invite ) {
			return self::MISSING;
		}
		if ( ! empty( $invite['cancelled'] ) ) {
			return self::CANCELLED;
		}
		if ( ! empty( $invite['used_by'] ) ) {
			return self::USED;
		}
		if ( (int) ( $invite['expires'] ?? 0 ) <= $now ) {
			return self::EXPIRED;
		}
		if ( self::normalize_email( (string) ( $invite['email'] ?? '' ) ) !== self::normalize_email( $email ) ) {
			return self::WRONG_EMAIL;
		}

		return self::VALID;
	}

	/**
	 * Whether an invite still holds a seat.
	 *
	 * @param array $invite Stored invite.
	 * @param int   $now    Unix time.
	 */
	public static function is_open( array $invite, int $now ): bool {
		return empty( $invite['cancelled'] ) && empty( $invite['used_by'] ) && (int) ( $invite['expires'] ?? 0 ) > $now;
	}

	/**
	 * Storage key for a token. Only the hash is stored, so a database leak
	 * doesn't hand out working links.
	 *
	 * @param string $token Token from the link.
	 */
	public static function key( string $token ): string {
		return hash( 'sha256', $token );
	}

	// ---------------------------------------------------------------------
	// Storage.
	// ---------------------------------------------------------------------

	/**
	 * Register the private waitlist post type. Entries show only on the
	 * Vendors page.
	 */
	public static function register_type(): void {
		register_post_type(
			self::WAITLIST_TYPE,
			array(
				'label'           => __( 'Waitlist', 'hexxsy-vendors' ),
				'public'          => false,
				'show_ui'         => false,
				'rewrite'         => false,
				'query_var'       => false,
				'supports'        => array( 'title' ),
				'capability_type' => 'post',
			)
		);
	}

	/**
	 * Reader seats setting.
	 */
	public static function seats(): int {
		return max( 1, (int) get_option( self::SEATS_OPTION, self::DEFAULT_SEATS ) );
	}

	/**
	 * All stored invites, keyed by token hash.
	 *
	 * @return array<string,array>
	 */
	public static function invites(): array {
		$invites = get_option( self::INVITES_OPTION, array() );
		return is_array( $invites ) ? $invites : array();
	}

	/**
	 * Save invites, dropping finished ones older than 90 days.
	 *
	 * @param array<string,array> $invites Invites.
	 */
	private static function save_invites( array $invites ): void {
		$cutoff  = time() - 90 * DAY_IN_SECONDS;
		$invites = array_filter( $invites, static fn( $i ) => self::is_open( $i, time() ) || (int) $i['created'] > $cutoff );
		update_option( self::INVITES_OPTION, $invites, false );
	}

	/**
	 * Open invites, newest first.
	 *
	 * @return array<string,array>
	 */
	public static function open_invites(): array {
		$open = array_filter( self::invites(), static fn( $i ) => self::is_open( $i, time() ) );
		uasort( $open, static fn( $a, $b ) => $b['created'] <=> $a['created'] );
		return $open;
	}

	/**
	 * Count users with a vendor status.
	 *
	 * @param string $status Vendor status.
	 */
	private static function count_status( string $status ): int {
		return count(
			get_users(
				array(
					'meta_key'   => Vendor::STATUS_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'meta_value' => $status, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
					'fields'     => 'ID',
				)
			)
		);
	}

	/**
	 * Approved readers.
	 */
	public static function approved_count(): int {
		return self::count_status( Vendor::APPROVED );
	}

	/**
	 * Seats left to invite into right now.
	 */
	public static function free(): int {
		return self::seats_free( self::seats(), self::approved_count(), self::count_status( Vendor::PENDING ), count( self::open_invites() ) );
	}

	/**
	 * Whether there's room to approve another reader.
	 */
	public static function can_approve(): bool {
		return self::approved_count() < self::seats();
	}

	/**
	 * URL of a page chosen in settings, or ''.
	 *
	 * @param string $option Option name.
	 */
	public static function page_url( string $option ): string {
		$id = (int) get_option( $option, 0 );
		return $id ? (string) get_permalink( $id ) : '';
	}

	// ---------------------------------------------------------------------
	// Invites.
	// ---------------------------------------------------------------------

	/**
	 * Invite an email address. Returns an error message, or '' on success.
	 *
	 * @param string $email Email.
	 */
	public static function invite( string $email ): string {
		$email = sanitize_email( $email );
		if ( ! is_email( $email ) ) {
			return __( 'That email address doesn\'t look right.', 'hexxsy-vendors' );
		}
		if ( self::free() < 1 ) {
			return __( 'Every seat is taken. Raise Reader seats, or wait for one to free up.', 'hexxsy-vendors' );
		}
		$apply = self::page_url( self::APPLY_PAGE_OPTION );
		if ( '' === $apply ) {
			return __( 'Choose the application page in the settings above first, so the invite has somewhere to link to.', 'hexxsy-vendors' );
		}
		foreach ( self::open_invites() as $open ) {
			if ( self::normalize_email( $open['email'] ) === self::normalize_email( $email ) ) {
				return __( 'That person already has an open invite.', 'hexxsy-vendors' );
			}
		}
		$user = get_user_by( 'email', $email );
		if ( $user && in_array( Vendor::status( $user->ID ), array( Vendor::APPROVED, Vendor::PENDING ), true ) ) {
			return __( 'That person is already a reader, or has an application waiting.', 'hexxsy-vendors' );
		}

		$token   = wp_generate_password( 32, false );
		$invites = self::invites();

		$invites[ self::key( $token ) ] = array(
			'email'   => $email,
			'created' => time(),
			'expires' => time() + self::INVITE_DAYS * DAY_IN_SECONDS,
		);
		self::save_invites( $invites );

		$link = add_query_arg( self::QUERY_ARG, rawurlencode( $token ), $apply );
		wp_mail(
			$email,
			__( 'A key to a room at Hexxsy', 'hexxsy-vendors' ),
			sprintf(
				/* translators: 1: application link, 2: days the link lasts */
				__( "I've kept a room aside for you at Hexxsy.\n\nThis link opens the reader application. It works once, for an account with this email address, and it goes stale in %2\$d days:\n\n%1\$s\n\nIf you don't have an account yet, the page will show you where to make one.\n\nHexx", 'hexxsy-vendors' ),
				$link,
				self::INVITE_DAYS
			)
		);

		do_action( 'hexxsy_reader_invited', $email );
		return '';
	}

	/**
	 * Cancel an open invite.
	 *
	 * @param string $key Token hash.
	 */
	public static function cancel( string $key ): void {
		$invites = self::invites();
		if ( isset( $invites[ $key ] ) ) {
			$invites[ $key ]['cancelled'] = time();
			self::save_invites( $invites );
		}
	}

	/**
	 * Keep an invite token from the link in a cookie, so it survives a trip
	 * to the account page to log in or sign up.
	 */
	public static function remember_invite(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the token is its own proof.
		if ( empty( $_GET[ self::QUERY_ARG ] ) || headers_sent() ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$token = sanitize_text_field( wp_unslash( $_GET[ self::QUERY_ARG ] ) );
		setcookie(
			self::COOKIE,
			$token,
			array(
				'expires'  => time() + self::INVITE_DAYS * DAY_IN_SECONDS,
				'path'     => COOKIEPATH ? COOKIEPATH : '/',
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);
		$_COOKIE[ self::COOKIE ] = $token;
	}

	/**
	 * The invite token for this request, from the link or the cookie.
	 */
	public static function request_token(): string {
		// phpcs:disable WordPress.Security.NonceVerification
		if ( ! empty( $_POST['hexxsy_invite'] ) ) {
			return sanitize_text_field( wp_unslash( $_POST['hexxsy_invite'] ) );
		}
		if ( ! empty( $_GET[ self::QUERY_ARG ] ) ) {
			return sanitize_text_field( wp_unslash( $_GET[ self::QUERY_ARG ] ) );
		}
		// phpcs:enable
		return isset( $_COOKIE[ self::COOKIE ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE ] ) ) : '';
	}

	/**
	 * State of a token for a user.
	 *
	 * @param string $token   Token.
	 * @param int    $user_id User ID; 0 for logged out.
	 * @return array{state:string,email:string}
	 */
	public static function check( string $token, int $user_id ): array {
		$invite = '' !== $token ? ( self::invites()[ self::key( $token ) ] ?? null ) : null;
		$user   = $user_id ? get_userdata( $user_id ) : null;

		return array(
			'state' => self::invite_state( $invite, $user ? $user->user_email : '', time() ),
			'email' => $invite ? (string) $invite['email'] : '',
		);
	}

	/**
	 * Mark a token used by a user.
	 *
	 * @param string $token   Token.
	 * @param int    $user_id User ID.
	 */
	public static function use_invite( string $token, int $user_id ): void {
		$invites = self::invites();
		$key     = self::key( $token );
		if ( isset( $invites[ $key ] ) ) {
			$invites[ $key ]['used_by'] = $user_id;
			$invites[ $key ]['used_at'] = time();
			self::save_invites( $invites );
		}
		if ( ! headers_sent() ) {
			setcookie( self::COOKIE, '', time() - HOUR_IN_SECONDS, COOKIEPATH ? COOKIEPATH : '/' );
		}

		// Their waitlist entry has done its job.
		$user = get_userdata( $user_id );
		if ( $user ) {
			foreach ( self::waitlist_ids_for( $user->user_email ) as $id ) {
				wp_delete_post( $id, true );
			}
		}
	}

	// ---------------------------------------------------------------------
	// Waitlist.
	// ---------------------------------------------------------------------

	/**
	 * Waitlist entry IDs for an email.
	 *
	 * @param string $email Email.
	 * @return int[]
	 */
	private static function waitlist_ids_for( string $email ): array {
		return get_posts(
			array(
				'post_type'   => self::WAITLIST_TYPE,
				'post_status' => 'private',
				'numberposts' => -1,
				'fields'      => 'ids',
				'meta_key'    => '_hexxsy_email', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'  => self::normalize_email( $email ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
	}

	/**
	 * Waitlist entries, oldest first.
	 *
	 * @return \WP_Post[]
	 */
	public static function waitlist(): array {
		return get_posts(
			array(
				'post_type'   => self::WAITLIST_TYPE,
				'post_status' => 'private',
				'numberposts' => -1,
				'orderby'     => 'date',
				'order'       => 'ASC',
			)
		);
	}

	/**
	 * The waitlist form.
	 */
	public static function render_waitlist(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['signed'] ) ) {
			return '<div class="hexxsy-form hexxsy-form-message"><p>' . esc_html__( 'Your name is in the ledger. I go through it on Sundays. If it fits, I\'ll find you.', 'hexxsy-vendors' ) . '</p></div>';
		}

		ob_start();
		if ( function_exists( 'wc_print_notices' ) ) {
			wc_print_notices();
		}
		?>
		<form method="post" class="hexxsy-form hexxsy-waitlist">
			<?php wp_nonce_field( self::SIGN_ACTION, '_hexxsy_nonce' ); ?>
			<input type="hidden" name="hexxsy_action" value="<?php echo esc_attr( self::SIGN_ACTION ); ?>">

			<div class="hexxsy-field">
				<label for="hexxsy_ledger_name"><?php esc_html_e( 'Name', 'hexxsy-vendors' ); ?></label>
				<input type="text" id="hexxsy_ledger_name" name="hexxsy_ledger_name" required maxlength="100" autocomplete="name">
			</div>
			<div class="hexxsy-field">
				<label for="hexxsy_ledger_email"><?php esc_html_e( 'Where to find you (email)', 'hexxsy-vendors' ); ?></label>
				<input type="email" id="hexxsy_ledger_email" name="hexxsy_ledger_email" required maxlength="200" autocomplete="email">
			</div>
			<div class="hexxsy-field">
				<label for="hexxsy_ledger_practice"><?php esc_html_e( 'What you read, and how long', 'hexxsy-vendors' ); ?></label>
				<input type="text" id="hexxsy_ledger_practice" name="hexxsy_ledger_practice" maxlength="200">
			</div>
			<div class="hexxsy-field">
				<label for="hexxsy_ledger_note"><?php esc_html_e( 'Anything I should know', 'hexxsy-vendors' ); ?></label>
				<textarea id="hexxsy_ledger_note" name="hexxsy_ledger_note" rows="4" maxlength="2000"></textarea>
			</div>
			<div class="hexxsy-trap" aria-hidden="true" style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden">
				<label for="hexxsy_ledger_site">Leave this empty</label>
				<input type="text" id="hexxsy_ledger_site" name="hexxsy_ledger_site" tabindex="-1" autocomplete="off">
			</div>
			<button type="submit" class="wp-element-button"><?php esc_html_e( 'Sign the ledger', 'hexxsy-vendors' ); ?></button>
		</form>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Save a waitlist signature.
	 */
	public static function handle_sign(): void {
		if ( ! isset( $_POST['hexxsy_action'] ) || self::SIGN_ACTION !== $_POST['hexxsy_action'] ) {
			return;
		}
		if ( ! isset( $_POST['_hexxsy_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_hexxsy_nonce'] ), self::SIGN_ACTION ) ) {
			wc_add_notice( __( 'The page sat open too long. Please try again.', 'hexxsy-vendors' ), 'error' );
			return;
		}

		$back = add_query_arg( 'signed', '1', wp_get_referer() ? wp_get_referer() : home_url( '/' ) );

		// Bots fill in the hidden field; pretend it worked.
		if ( ! empty( $_POST['hexxsy_ledger_site'] ) ) {
			wp_safe_redirect( $back );
			exit;
		}

		$name  = isset( $_POST['hexxsy_ledger_name'] ) ? sanitize_text_field( wp_unslash( $_POST['hexxsy_ledger_name'] ) ) : '';
		$email = isset( $_POST['hexxsy_ledger_email'] ) ? sanitize_email( wp_unslash( $_POST['hexxsy_ledger_email'] ) ) : '';

		if ( '' === $name || ! is_email( $email ) ) {
			wc_add_notice( __( 'I need your name and an email address that works.', 'hexxsy-vendors' ), 'error' );
			return;
		}

		// One entry per address. Say the same thing either way, so the form
		// doesn't reveal who's already on the list.
		if ( ! self::waitlist_ids_for( $email ) ) {
			$id = wp_insert_post(
				array(
					'post_type'   => self::WAITLIST_TYPE,
					'post_status' => 'private',
					'post_title'  => $name,
				)
			);
			if ( $id && ! is_wp_error( $id ) ) {
				update_post_meta( $id, '_hexxsy_email', self::normalize_email( $email ) );
				update_post_meta( $id, '_hexxsy_practice', isset( $_POST['hexxsy_ledger_practice'] ) ? sanitize_text_field( wp_unslash( $_POST['hexxsy_ledger_practice'] ) ) : '' );
				update_post_meta( $id, '_hexxsy_note', isset( $_POST['hexxsy_ledger_note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['hexxsy_ledger_note'] ) ) : '' );

				wp_mail(
					$email,
					__( 'Your name is in the ledger', 'hexxsy-vendors' ),
					__( "You signed the reader ledger at Hexxsy. I go through it on Sundays, and if it fits, I'll write to you with an invitation.\n\nIf you didn't sign it, ignore this and nothing more will come.\n\nHexx", 'hexxsy-vendors' )
				);
				do_action( 'hexxsy_waitlist_signed', $id );
			}
		}

		wp_safe_redirect( $back );
		exit;
	}

	// ---------------------------------------------------------------------
	// Admin.
	// ---------------------------------------------------------------------

	/**
	 * Link to a Ledger admin action.
	 *
	 * @param string $do   Action.
	 * @param string $item Invite key or waitlist ID.
	 */
	private static function admin_url( string $do, string $item ): string {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action' => self::ACTION,
					'do'     => $do,
					'item'   => $item,
				),
				admin_url( 'admin-post.php' )
			),
			self::ACTION . '_' . $do . '_' . $item
		);
	}

	/**
	 * Handle invite, cancel, and waitlist actions from the Vendors page.
	 */
	public static function handle_admin(): void {
		if ( ! current_user_can( Admin::CAPABILITY ) ) {
			wp_die( esc_html__( 'You are not allowed to manage readers.', 'hexxsy-vendors' ), 403 );
		}

		// phpcs:disable WordPress.Security.NonceVerification -- checked below per action.
		$do   = isset( $_REQUEST['do'] ) ? sanitize_key( $_REQUEST['do'] ) : '';
		$item = isset( $_REQUEST['item'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['item'] ) ) : '';
		// phpcs:enable

		$error = '';
		$done  = '';

		if ( 'invite_email' === $do ) {
			check_admin_referer( self::ACTION . '_invite_email' );
			$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
			$error = self::invite( $email );
			$done  = 'invited';
		} else {
			check_admin_referer( self::ACTION . '_' . $do . '_' . $item );
			if ( 'invite_waitlist' === $do ) {
				$email = (string) get_post_meta( (int) $item, '_hexxsy_email', true );
				$error = self::invite( $email );
				if ( '' === $error ) {
					update_post_meta( (int) $item, '_hexxsy_invited_at', time() );
				}
				$done = 'invited';
			} elseif ( 'remove_waitlist' === $do && self::WAITLIST_TYPE === get_post_type( (int) $item ) ) {
				wp_delete_post( (int) $item, true );
				$done = 'removed';
			} elseif ( 'cancel_invite' === $do ) {
				self::cancel( $item );
				$done = 'cancelled';
			}
		}

		$args = array( 'page' => Admin::PAGE );
		if ( '' !== $error ) {
			set_transient( 'hexxsy_ledger_error_' . get_current_user_id(), $error, 60 );
			$args['ledger'] = 'error';
		} else {
			$args['ledger'] = $done;
		}
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * The Ledger section of the Vendors page.
	 */
	public static function render_admin(): void {
		$free    = self::free();
		$seats   = self::seats();
		$invites = self::open_invites();
		$list    = self::waitlist();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$result = isset( $_GET['ledger'] ) ? sanitize_key( $_GET['ledger'] ) : '';
		if ( 'error' === $result ) {
			$message = (string) get_transient( 'hexxsy_ledger_error_' . get_current_user_id() );
			echo '<div class="notice notice-error"><p>' . esc_html( $message ) . '</p></div>';
		} elseif ( '' !== $result ) {
			$messages = array(
				'invited'   => __( 'Invite sent.', 'hexxsy-vendors' ),
				'removed'   => __( 'Removed from the waitlist.', 'hexxsy-vendors' ),
				'cancelled' => __( 'Invite cancelled. Its link no longer works.', 'hexxsy-vendors' ),
			);
			if ( isset( $messages[ $result ] ) ) {
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $messages[ $result ] ) . '</p></div>';
			}
		}
		?>
		<h2><?php esc_html_e( 'The Ledger', 'hexxsy-vendors' ); ?></h2>
		<p>
			<?php
			printf(
				/* translators: 1: approved readers, 2: seats, 3: seats free to invite into */
				esc_html__( '%1$d of %2$d seats filled by approved readers. %3$d free to invite into, after applications waiting for review and open invites.', 'hexxsy-vendors' ),
				(int) self::approved_count(),
				(int) $seats,
				(int) $free
			);
			?>
		</p>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( self::ACTION . '_invite_email' ); ?>
			<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>">
			<input type="hidden" name="do" value="invite_email">
			<label for="hexxsy_invite_email"><strong><?php esc_html_e( 'Invite a reader by email', 'hexxsy-vendors' ); ?></strong></label><br>
			<input type="email" id="hexxsy_invite_email" name="email" class="regular-text" required <?php disabled( $free < 1 ); ?>>
			<?php submit_button( __( 'Send invite', 'hexxsy-vendors' ), 'primary', 'submit', false, $free < 1 ? array( 'disabled' => 'disabled' ) : array() ); ?>
			<?php if ( $free < 1 ) : ?>
				<p class="description"><?php esc_html_e( 'Every seat is taken. Raise Reader seats above to invite more.', 'hexxsy-vendors' ); ?></p>
			<?php endif; ?>
		</form>

		<h3><?php esc_html_e( 'Open invites', 'hexxsy-vendors' ); ?> (<?php echo count( $invites ); ?>)</h3>
		<?php if ( ! $invites ) : ?>
			<p><?php esc_html_e( 'None.', 'hexxsy-vendors' ); ?></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead><tr>
					<th><?php esc_html_e( 'Email', 'hexxsy-vendors' ); ?></th>
					<th><?php esc_html_e( 'Sent', 'hexxsy-vendors' ); ?></th>
					<th><?php esc_html_e( 'Expires', 'hexxsy-vendors' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'hexxsy-vendors' ); ?></th>
				</tr></thead>
				<tbody>
				<?php foreach ( $invites as $key => $invite ) : ?>
					<tr>
						<td><?php echo esc_html( $invite['email'] ); ?></td>
						<td><?php echo esc_html( wp_date( get_option( 'date_format' ), (int) $invite['created'] ) ); ?></td>
						<td><?php echo esc_html( wp_date( get_option( 'date_format' ), (int) $invite['expires'] ) ); ?></td>
						<td><a class="button" href="<?php echo esc_url( self::admin_url( 'cancel_invite', (string) $key ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Cancel this invite? Its link will stop working.', 'hexxsy-vendors' ) ); ?>');"><?php esc_html_e( 'Cancel', 'hexxsy-vendors' ); ?></a></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

		<h3><?php esc_html_e( 'Waitlist', 'hexxsy-vendors' ); ?> (<?php echo count( $list ); ?>)</h3>
		<?php if ( ! $list ) : ?>
			<p><?php esc_html_e( 'Nobody has signed the ledger yet.', 'hexxsy-vendors' ); ?></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead><tr>
					<th><?php esc_html_e( 'Name', 'hexxsy-vendors' ); ?></th>
					<th><?php esc_html_e( 'Email', 'hexxsy-vendors' ); ?></th>
					<th><?php esc_html_e( 'What they read', 'hexxsy-vendors' ); ?></th>
					<th><?php esc_html_e( 'Note', 'hexxsy-vendors' ); ?></th>
					<th><?php esc_html_e( 'Signed', 'hexxsy-vendors' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'hexxsy-vendors' ); ?></th>
				</tr></thead>
				<tbody>
				<?php foreach ( $list as $entry ) : ?>
					<?php $invited = (int) get_post_meta( $entry->ID, '_hexxsy_invited_at', true ); ?>
					<tr>
						<td><strong><?php echo esc_html( $entry->post_title ); ?></strong></td>
						<td><?php echo esc_html( (string) get_post_meta( $entry->ID, '_hexxsy_email', true ) ); ?></td>
						<td><?php echo esc_html( (string) get_post_meta( $entry->ID, '_hexxsy_practice', true ) ); ?></td>
						<td><?php echo esc_html( wp_trim_words( (string) get_post_meta( $entry->ID, '_hexxsy_note', true ), 40 ) ); ?></td>
						<td><?php echo esc_html( get_the_date( '', $entry ) ); ?></td>
						<td>
							<?php if ( $invited ) : ?>
								<em><?php echo esc_html( sprintf( /* translators: %s: date */ __( 'Invited %s', 'hexxsy-vendors' ), wp_date( get_option( 'date_format' ), $invited ) ) ); ?></em><br>
							<?php endif; ?>
							<?php if ( $free > 0 ) : ?>
								<a class="button button-primary" href="<?php echo esc_url( self::admin_url( 'invite_waitlist', (string) $entry->ID ) ); ?>"><?php echo esc_html( $invited ? __( 'Invite again', 'hexxsy-vendors' ) : __( 'Invite', 'hexxsy-vendors' ) ); ?></a>
							<?php endif; ?>
							<a class="button" href="<?php echo esc_url( self::admin_url( 'remove_waitlist', (string) $entry->ID ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Remove this person from the waitlist? Their details are deleted.', 'hexxsy-vendors' ) ); ?>');"><?php esc_html_e( 'Remove', 'hexxsy-vendors' ); ?></a>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
		<?php
	}
}
