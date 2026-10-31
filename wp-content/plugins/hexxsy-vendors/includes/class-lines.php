<?php
/**
 * Hexx's lines: every line the plugin speaks, editable in one place.
 *
 * @package Hexxsy\Vendors
 */

namespace Hexxsy\Vendors;

/**
 * WooCommerce > Hexx's lines lists every customer- and reader-facing line the
 * plugin writes, grouped by where it appears, each with its default. A blank
 * field falls back to the default.
 *
 * Lines are plain text. {placeholders} are filled in when the line is used;
 * {link}…{/link} marks the words that become a link.
 *
 * [hexxsy_line key="…"] puts any line on a page. [hexxsy_line key="ledger"]
 * shows the "rooms open" or "every room taken" pair, whichever is true now.
 *
 * Lines the theme speaks (the 404, the footer, the homepage hero) are edited
 * in the Site Editor instead.
 */
class Lines {

	const OPTION = 'hexxsy_lines';
	const PAGE   = 'hexxsy-lines';

	/**
	 * Every line: key => [group, label, default].
	 *
	 * @return array<string,array{0:string,1:string,2:string}>
	 */
	public static function defaults(): array {
		return array(
			// Rooms list.
			'rooms_empty_one'     => array( 'rooms', 'Empty rooms, one', 'One room stands empty for now. I keep it aired.' ),
			'rooms_empty_many'    => array( 'rooms', 'Empty rooms, several ({count} is the number)', '{count} rooms stand empty for now. I keep them aired.' ),
			'rooms_ledger'        => array( 'rooms', 'Under the empty rooms ({link}…{/link} links to the Ledger)', 'I don\'t hand out keys to just anyone. If you read tarot and want a room of your own here, {link}put your name in the ledger{/link}. I\'ll come find you if it fits. Don\'t wait by the door.' ),
			'rooms_none'          => array( 'rooms', 'No readers yet', 'The rooms are being aired. Come back soon.' ),
			'rooms_unlit'         => array( 'rooms', 'Unlit room, label', 'Unlit' ),
			'rooms_unlit_line'    => array( 'rooms', 'Unlit room, line', 'Nobody\'s taken this one yet.' ),
			'rooms_room'          => array( 'rooms', 'Room card, label', 'Room' ),
			'rooms_from'          => array( 'rooms', 'Room card, price ({price} is the lowest price)', 'From {price}' ),
			'rooms_knock'         => array( 'rooms', 'Room card, button', 'Knock' ),

			// The Ledger.
			'ledger_open_title'   => array( 'ledger', 'Rooms open, first line', 'There are rooms open, but I don\'t hand out keys.' ),
			'ledger_open_body'    => array( 'ledger', 'Rooms open, second line', 'Leave your name and I\'ll come find you if it fits. I go through the list on Sundays.' ),
			'ledger_full_title'   => array( 'ledger', 'Every room taken, first line', 'Every chair is taken. Has been for a while.' ),
			'ledger_full_body'    => array( 'ledger', 'Every room taken, second line', 'Leave your name anyway. I go through the list on Sundays.' ),
			'ledger_thanks'       => array( 'ledger', 'After signing', 'Your name is in the ledger. I go through it on Sundays. If it fits, I\'ll find you.' ),
			'ledger_email_subject' => array( 'ledger', 'Confirmation email, subject', 'Your name is in the ledger' ),
			'ledger_email_body'   => array( 'ledger', 'Confirmation email, body', "You signed the reader ledger at Hexxsy. I go through it on Sundays, and if it fits, I'll write to you with an invitation.\n\nIf you didn't sign it, ignore this and nothing more will come.\n\nHexx" ),

			// Invitations and the application.
			'invite_subject'      => array( 'apply', 'Invite email, subject', 'A key to a room at Hexxsy' ),
			'invite_body'         => array( 'apply', 'Invite email, body ({link_url} is the link, {days} how long it lasts)', "I've kept a room aside for you at Hexxsy.\n\nThis link opens the reader application. It works once, for an account with this email address, and it goes stale in {days} days:\n\n{link_url}\n\nIf you don't have an account yet, the page will show you where to make one.\n\nHexx" ),
			'apply_invitation'    => array( 'apply', 'No invite ({link}…{/link} links to the Ledger)', 'Hexxsy readers join by invitation. If you read and want a room, {link}put your name in the ledger{/link}. I\'ll come find you if it fits.' ),
			'apply_log_in'        => array( 'apply', 'Invite, not logged in ({email} is the invited address)', 'This invite is for {email}. {link}Log in or make an account{/link} with that email address, then come back to this page.' ),
			'apply_wrong_email'   => array( 'apply', 'Invite, wrong account', 'This invite is for {email}, and you\'re logged in with a different email address. Log out and log in with that one, or ask me to send the invite somewhere else.' ),
			'apply_expired'       => array( 'apply', 'Invite expired', 'This invite has gone stale. Write to me if you still want the room, and I\'ll send a fresh one.' ),
			'apply_used'          => array( 'apply', 'Invite used or cancelled', 'This invite has already been used up. Write to me if that\'s a surprise.' ),
			'apply_pending'       => array( 'apply', 'Application waiting', 'Your application is in. I read every one myself, and I\'ll write to you once I\'ve decided.' ),
			'apply_approved'      => array( 'apply', 'Already a reader', 'You already have a room here. Your reader tabs are in My Account.' ),
			'apply_rejected'      => array( 'apply', 'Application not accepted', 'Your application wasn\'t accepted this time. If you think I got it wrong, write to me.' ),
		);
	}

	/**
	 * Group headings.
	 *
	 * @return array<string,string>
	 */
	public static function groups(): array {
		return array(
			'rooms'  => __( 'Rooms list', 'hexxsy-vendors' ),
			'ledger' => __( 'The Ledger (reader waitlist)', 'hexxsy-vendors' ),
			'apply'  => __( 'Invitations and the reader application', 'hexxsy-vendors' ),
		);
	}

	/**
	 * Fill {placeholders} in plain text. Placeholder names are letters and
	 * underscores; unknown ones are left as typed.
	 *
	 * @param string               $text Line.
	 * @param array<string,string> $vars Values.
	 */
	public static function fill( string $text, array $vars ): string {
		return (string) preg_replace_callback(
			'/\{([a-z_]+)\}/',
			static fn( $m ) => array_key_exists( $m[1], $vars ) ? (string) $vars[ $m[1] ] : $m[0],
			$text
		);
	}

	/**
	 * Escape a line for HTML and turn {link}…{/link} into a link. With no
	 * URL, the marked words stay as plain text.
	 *
	 * @param string $text Line, already filled.
	 * @param string $url  Link target, or ''.
	 * @param callable $escape Escaper for text, e.g. esc_html.
	 * @param callable $escape_url Escaper for URLs, e.g. esc_url.
	 */
	public static function linkify( string $text, string $url, callable $escape, callable $escape_url ): string {
		$html = $escape( $text );
		if ( '' === $url ) {
			return str_replace( array( '{link}', '{/link}' ), '', $html );
		}
		return str_replace(
			array( '{link}', '{/link}' ),
			array( '<a href="' . $escape_url( $url ) . '">', '</a>' ),
			$html
		);
	}

	/**
	 * A line as plain text, with placeholders filled.
	 *
	 * @param string               $key  Line key.
	 * @param array<string,string> $vars Placeholder values.
	 */
	public static function text( string $key, array $vars = array() ): string {
		$saved = get_option( self::OPTION, array() );
		$line  = is_array( $saved ) && isset( $saved[ $key ] ) && '' !== trim( (string) $saved[ $key ] )
			? (string) $saved[ $key ]
			: ( self::defaults()[ $key ][2] ?? '' );

		return self::fill( $line, $vars );
	}

	/**
	 * A line as safe HTML, with an optional link.
	 *
	 * @param string               $key  Line key.
	 * @param array<string,string> $vars Placeholder values.
	 * @param string               $url  Target for {link}…{/link}.
	 */
	public static function html( string $key, array $vars = array(), string $url = '' ): string {
		return self::linkify( self::text( $key, $vars ), $url, 'esc_html', 'esc_url' );
	}

	/**
	 * Hook in.
	 */
	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 61 );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_shortcode( 'hexxsy_line', array( __CLASS__, 'shortcode' ) );
	}

	/**
	 * [hexxsy_line key="…"]. key="ledger" shows the pair for whether rooms
	 * are open right now.
	 *
	 * @param array|string $atts Attributes.
	 */
	public static function shortcode( $atts ): string {
		$atts = shortcode_atts( array( 'key' => '' ), $atts, 'hexxsy_line' );
		$key  = sanitize_key( $atts['key'] );

		if ( 'ledger' === $key ) {
			$open = Ledger::approved_count() < Ledger::seats();
			$pre  = $open ? 'ledger_open' : 'ledger_full';
			return '<p class="hexxsy-line hexxsy-line-title">' . self::html( $pre . '_title' ) . '</p>'
				. '<p class="hexxsy-line">' . self::html( $pre . '_body' ) . '</p>';
		}
		if ( ! isset( self::defaults()[ $key ] ) ) {
			return '';
		}

		return '<span class="hexxsy-line">' . self::html( $key, array(), Ledger::page_url( Ledger::LEDGER_PAGE_OPTION ) ) . '</span>';
	}

	/**
	 * Register the submenu.
	 */
	public static function menu(): void {
		add_submenu_page(
			'woocommerce',
			__( "Hexx's lines", 'hexxsy-vendors' ),
			__( "Hexx's lines", 'hexxsy-vendors' ),
			Admin::CAPABILITY,
			self::PAGE,
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * Register the option.
	 */
	public static function register(): void {
		register_setting(
			self::PAGE,
			self::OPTION,
			array(
				'type'              => 'array',
				'default'           => array(),
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
			)
		);
	}

	/**
	 * Keep known keys only. Lines that match their default aren't stored, so
	 * a later change to a default still reaches the site.
	 *
	 * @param mixed $value Submitted lines.
	 * @return array<string,string>
	 */
	public static function sanitize( $value ): array {
		$clean = array();
		if ( ! is_array( $value ) ) {
			return $clean;
		}
		foreach ( self::defaults() as $key => $line ) {
			if ( ! isset( $value[ $key ] ) ) {
				continue;
			}
			$text = sanitize_textarea_field( (string) $value[ $key ] );
			if ( '' !== trim( $text ) && $text !== $line[2] ) {
				$clean[ $key ] = $text;
			}
		}
		return $clean;
	}

	/**
	 * The settings screen.
	 */
	public static function render(): void {
		if ( ! current_user_can( Admin::CAPABILITY ) ) {
			return;
		}
		$saved = get_option( self::OPTION, array() );
		$saved = is_array( $saved ) ? $saved : array();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( "Hexx's lines", 'hexxsy-vendors' ); ?></h1>
			<p><?php esc_html_e( 'Every line Hexx speaks through the Hexxsy Vendors plugin. Leave a box empty to use the default shown under it. Words in {braces} are filled in for you; keep them as they are. {link} and {/link} go around the words that become a link.', 'hexxsy-vendors' ); ?></p>
			<p><?php esc_html_e( 'Lines in the theme (the homepage, the footer, the 404 page) are edited in Appearance > Editor.', 'hexxsy-vendors' ); ?></p>
			<form method="post" action="options.php">
				<?php settings_fields( self::PAGE ); ?>
				<?php foreach ( self::groups() as $group => $heading ) : ?>
					<h2><?php echo esc_html( $heading ); ?></h2>
					<table class="form-table" role="presentation">
						<?php foreach ( self::defaults() as $key => $line ) : ?>
							<?php
							if ( $line[0] !== $group ) {
								continue;
							}
							$id    = 'hexxsy_line_' . $key;
							$value = $saved[ $key ] ?? '';
							$rows  = max( 2, min( 10, substr_count( $line[2], "\n" ) + 2 ) );
							?>
							<tr>
								<th scope="row"><label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $line[1] ); ?></label></th>
								<td>
									<textarea id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( self::OPTION . '[' . $key . ']' ); ?>" rows="<?php echo (int) $rows; ?>" class="large-text" placeholder="<?php echo esc_attr( $line[2] ); ?>"><?php echo esc_textarea( (string) $value ); ?></textarea>
									<p class="description"><?php echo esc_html( __( 'Default:', 'hexxsy-vendors' ) . ' ' . $line[2] ); ?></p>
								</td>
							</tr>
						<?php endforeach; ?>
					</table>
				<?php endforeach; ?>
				<?php submit_button( __( "Save Hexx's lines", 'hexxsy-vendors' ) ); ?>
			</form>
		</div>
		<?php
	}
}
