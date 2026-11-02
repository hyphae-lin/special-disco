<?php
/**
 * Hexx's lines: every line the plugin speaks, editable in one place.
 *
 * @package Hexxsy\Vendors
 */

namespace Hexxsy\Vendors;

/**
 * Hexxsy > Hexx's lines lists every customer- and reader-facing line the
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
			'rooms_empty_many'    => array( 'rooms', 'Empty rooms, several', '{count} rooms stand empty for now. I keep them aired.' ),
			'rooms_ledger'        => array( 'rooms', 'Under the empty rooms', 'I don\'t hand out keys to just anyone. If you read tarot and want a room of your own here, {link}put your name in the ledger{/link}. I\'ll come find you if it fits. Don\'t wait by the door.' ),
			'rooms_none'          => array( 'rooms', 'No readers yet', 'The rooms are being aired. Come back soon.' ),
			'rooms_unlit'         => array( 'rooms', 'Unlit room, label', 'Unlit' ),
			'rooms_unlit_line'    => array( 'rooms', 'Unlit room, line', 'Nobody\'s taken this one yet.' ),
			'rooms_room'          => array( 'rooms', 'Room card, label', 'Room' ),
			'rooms_from'          => array( 'rooms', 'Room card, price', 'From {price}' ),
			'rooms_knock'         => array( 'rooms', 'Room card, button', 'Knock' ),

			// The Ledger.
			'ledger_open_title'   => array( 'ledger', 'Rooms open, first line', 'There are rooms open, but I don\'t hand out keys.' ),
			'ledger_open_body'    => array( 'ledger', 'Rooms open, second line', 'Leave your name and I\'ll come find you if it fits. I go through the list on Sundays.' ),
			'ledger_full_title'   => array( 'ledger', 'Every room taken, first line', 'Every chair is taken. Has been for a while.' ),
			'ledger_full_body'    => array( 'ledger', 'Every room taken, second line', 'Leave your name anyway. I go through the list on Sundays.' ),
			'ledger_thanks'       => array( 'ledger', 'After signing', 'Your name is in the ledger. I go through it on Sundays. If it fits, I\'ll find you.' ),

			// Invitations and the application.
			'apply_invitation'    => array( 'apply', 'No invite', 'Hexxsy readers join by invitation. If you read and want a room, {link}put your name in the ledger{/link}. I\'ll come find you if it fits.' ),
			'apply_log_in'        => array( 'apply', 'Invite, not logged in', 'This invite is for {email}. {link}Log in or make an account{/link} with that email address, then come back to this page.' ),
			'apply_wrong_email'   => array( 'apply', 'Invite, wrong account', 'This invite is for {email}, and you\'re logged in with a different email address. Log out and log in with that one, or ask me to send the invite somewhere else.' ),
			'apply_expired'       => array( 'apply', 'Invite expired', 'This invite has gone stale. Write to me if you still want the room, and I\'ll send a fresh one.' ),
			'apply_used'          => array( 'apply', 'Invite used or cancelled', 'This invite has already been used up. Write to me if that\'s a surprise.' ),
			'apply_pending'       => array( 'apply', 'Application waiting', 'Your application is in. I read every one myself, and I\'ll write to you once I\'ve decided.' ),
			'apply_approved'      => array( 'apply', 'Already a reader', 'You already have a room here. Your reader tabs are in My Account.' ),
			'apply_rejected'      => array( 'apply', 'Application not accepted', 'Your application wasn\'t accepted this time. If you think I got it wrong, write to me.' ),
		);
	}

	/**
	 * What each {placeholder} is filled with.
	 *
	 * @return array<string,string>
	 */
	public static function placeholder_help(): array {
		return array(
			'count' => __( 'how many rooms are empty', 'hexxsy-vendors' ),
			'price' => __( "the reader's lowest price, like $25.00", 'hexxsy-vendors' ),
			'email' => __( 'the email address the invite was sent to', 'hexxsy-vendors' ),
		);
	}

	/**
	 * Where {link}…{/link} goes, for lines that have one.
	 *
	 * @return array<string,string>
	 */
	public static function link_targets(): array {
		return array(
			'rooms_ledger'     => __( 'the Ledger page', 'hexxsy-vendors' ),
			'apply_invitation' => __( 'the Ledger page', 'hexxsy-vendors' ),
			'apply_log_in'     => __( 'My Account, where they log in or sign up', 'hexxsy-vendors' ),
		);
	}

	/**
	 * The placeholders a line uses, in order, without {link} and {/link}.
	 *
	 * @param string $text Line.
	 * @return string[]
	 */
	public static function placeholders_in( string $text ): array {
		preg_match_all( '/\{([a-z_]+)\}/', $text, $m );
		return array_values( array_unique( array_diff( $m[1], array( 'link' ) ) ) );
	}

	/**
	 * Merge a save into what's stored. Lines not on the submitted tab keep
	 * their saved text. A line that's empty or matches its default isn't
	 * stored, so a later change to a default still reaches the site.
	 *
	 * @param array<string,string> $saved     What's stored now.
	 * @param array<string,mixed>  $submitted What the form sent.
	 * @param callable             $clean     Sanitizer for one line.
	 * @return array<string,string>
	 */
	public static function merge( array $saved, array $submitted, callable $clean ): array {
		$out = array();
		foreach ( self::defaults() as $key => $line ) {
			if ( array_key_exists( $key, $submitted ) ) {
				$text = str_replace( "\r\n", "\n", (string) $clean( (string) $submitted[ $key ] ) );
				if ( '' !== trim( $text ) && $text !== $line[2] ) {
					$out[ $key ] = $text;
				}
			} elseif ( isset( $saved[ $key ] ) && '' !== trim( (string) $saved[ $key ] ) ) {
				$out[ $key ] = (string) $saved[ $key ];
			}
		}
		return $out;
	}

	/**
	 * Box height: enough rows to show the whole line at about 60 characters
	 * a row, between 1 and 12.
	 *
	 * @param string $text Line.
	 */
	public static function rows_for( string $text ): int {
		$rows = 0;
		foreach ( explode( "\n", str_replace( "\r\n", "\n", $text ) ) as $para ) {
			$rows += max( 1, (int) ceil( mb_strlen( $para ) / 60 ) );
		}
		return max( 1, min( 12, $rows ) );
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
			Admin::PAGE,
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
	 * Sanitize a save from one tab, keeping the other tabs' lines.
	 *
	 * @param mixed $value Submitted lines.
	 * @return array<string,string>
	 */
	public static function sanitize( $value ): array {
		$saved = get_option( self::OPTION, array() );
		return self::merge(
			is_array( $saved ) ? $saved : array(),
			is_array( $value ) ? $value : array(),
			'sanitize_textarea_field'
		);
	}

	/**
	 * The settings screen: one tab per group, each box filled with the line
	 * as it reads now.
	 */
	public static function render(): void {
		if ( ! current_user_can( Admin::CAPABILITY ) ) {
			return;
		}
		$saved  = get_option( self::OPTION, array() );
		$saved  = is_array( $saved ) ? $saved : array();
		$groups = self::groups();
		$tab    = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- choosing a tab only.
		$tab    = isset( $groups[ $tab ] ) ? $tab : (string) array_key_first( $groups );
		$help   = self::placeholder_help();
		$links  = self::link_targets();
		$base   = admin_url( 'admin.php?page=' . self::PAGE );
		?>
		<div class="wrap hexxsy-lines">
			<h1><?php esc_html_e( "Hexx's lines", 'hexxsy-vendors' ); ?></h1>
			<p><?php esc_html_e( 'Every line Hexx speaks on the site through the Hexxsy Vendors plugin. Edit any box and save. Reset to default puts the original line back. Emails are in WooCommerce > Settings > Emails. Lines in the theme (the homepage, the footer, the 404 page) are edited in Appearance > Editor.', 'hexxsy-vendors' ); ?></p>
			<?php settings_errors(); ?>
			<nav class="nav-tab-wrapper" aria-label="<?php esc_attr_e( "Hexx's lines sections", 'hexxsy-vendors' ); ?>">
				<?php foreach ( $groups as $group => $heading ) : ?>
					<a href="<?php echo esc_url( add_query_arg( 'tab', $group, $base ) ); ?>" class="nav-tab<?php echo $group === $tab ? ' nav-tab-active' : ''; ?>"<?php echo $group === $tab ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $heading ); ?></a>
				<?php endforeach; ?>
			</nav>
			<form method="post" action="options.php">
				<?php settings_fields( self::PAGE ); ?>
				<table class="form-table" role="presentation">
					<?php foreach ( self::defaults() as $key => $line ) : ?>
						<?php
						if ( $line[0] !== $tab ) {
							continue;
						}
						$id      = 'hexxsy_line_' . $key;
						$changed = isset( $saved[ $key ] ) && '' !== trim( (string) $saved[ $key ] );
						$value   = $changed ? (string) $saved[ $key ] : $line[2];
						$rows    = self::rows_for( $value );
						$fills   = self::placeholders_in( $line[2] );
						?>
						<tr>
							<th scope="row"><label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $line[1] ); ?></label></th>
							<td>
								<textarea id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( self::OPTION . '[' . $key . ']' ); ?>" rows="<?php echo (int) $rows; ?>" class="large-text hexxsy-line-box" data-default="<?php echo esc_attr( $line[2] ); ?>" aria-describedby="<?php echo esc_attr( $id . '_help' ); ?>"><?php echo esc_textarea( $value ); ?></textarea>
								<div id="<?php echo esc_attr( $id . '_help' ); ?>" class="description">
									<?php if ( $fills ) : ?>
										<p><?php esc_html_e( 'Filled in for you:', 'hexxsy-vendors' ); ?></p>
										<ul class="hexxsy-line-fills">
											<?php foreach ( $fills as $name ) : ?>
												<li><code>{<?php echo esc_html( $name ); ?>}</code> <?php echo esc_html( $help[ $name ] ?? '' ); ?></li>
											<?php endforeach; ?>
										</ul>
									<?php endif; ?>
									<?php if ( isset( $links[ $key ] ) ) : ?>
										<p>
											<?php
											/* translators: %s: where the link goes. */
											echo esc_html( sprintf( __( 'The words between {link} and {/link} link to %s.', 'hexxsy-vendors' ), $links[ $key ] ) );
											?>
										</p>
									<?php endif; ?>
									<p class="hexxsy-line-state" data-changed-text="<?php esc_attr_e( 'Changed from the default.', 'hexxsy-vendors' ); ?>">
										<span class="hexxsy-line-state-text"><?php echo $changed ? esc_html__( 'Changed from the default.', 'hexxsy-vendors' ) : ''; ?></span>
										<button type="button" class="button-link hexxsy-line-reset" data-for="<?php echo esc_attr( $id ); ?>"<?php echo $changed ? '' : ' hidden'; ?>><?php esc_html_e( 'Reset to default', 'hexxsy-vendors' ); ?></button>
									</p>
								</div>
							</td>
						</tr>
					<?php endforeach; ?>
				</table>
				<p class="description"><?php esc_html_e( 'Keep the words in {braces} as they are. Saving changes this tab only.', 'hexxsy-vendors' ); ?></p>
				<?php submit_button( __( "Save Hexx's lines", 'hexxsy-vendors' ) ); ?>
			</form>
		</div>
		<script>
		( function () {
			var norm = function ( s ) { return s.replace( /\r\n/g, '\n' ); };
			document.querySelectorAll( '.hexxsy-lines .hexxsy-line-box' ).forEach( function ( box ) {
				var state = document.getElementById( box.id + '_help' ).querySelector( '.hexxsy-line-state' );
				var text  = state.querySelector( '.hexxsy-line-state-text' );
				var reset = state.querySelector( '.hexxsy-line-reset' );
				var fit   = function () {
					box.style.height = 'auto';
					box.style.height = ( box.scrollHeight + 2 ) + 'px';
				};
				var sync  = function () {
					var changed = norm( box.value ) !== norm( box.dataset.default ) && '' !== box.value.trim();
					text.textContent = changed ? state.dataset.changedText : '';
					reset.hidden = ! changed;
					fit();
				};
				reset.addEventListener( 'click', function () {
					box.value = box.dataset.default;
					sync();
					box.focus();
				} );
				box.addEventListener( 'input', sync );
				fit();
			} );
		} )();
		</script>
		<?php
	}
}
