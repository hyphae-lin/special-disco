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

			// A reader's room.
			'room_back'           => array( 'room', 'Link back to the homepage', 'Back to the rooms' ),
			'room_how'            => array( 'room', 'Heading over the reader\'s "How I read"', 'How I read' ),
			'room_hexx_note'      => array( 'room', 'Heading over your note about the reader', 'Hexx\'s note' ),
			'room_nav_label'      => array( 'room', 'Shelf menu, name for screen readers', 'What this room offers' ),
			'room_more'           => array( 'room', 'Link on each card', 'More about this' ),
			'room_detail_written' => array( 'room', 'Under a written reading\'s name ({days} is the turnaround)', 'Written · arrives within {days} days' ),
			'room_detail_written_one' => array( 'room', 'The same, for a one-day turnaround', 'Written · arrives within a day' ),
			'room_detail_live'    => array( 'room', 'Under a live call\'s name ({minutes} is its length)', 'Live · {minutes} minutes' ),
			'room_empty'          => array( 'room', 'A room with nothing on sale', 'Nothing on the shelves just now. Come back soon.' ),

			// An offering's page.
			'offering_rooms'      => array( 'offering', 'First link in the trail at the top', 'The Rooms' ),
			'offering_by'         => array( 'offering', 'Under the name ({reader}, {numeral} is the room number, {link}…{/link} links to the room)', 'Offered by {link}{reader}{/link}, Room {numeral}' ),
			'offering_words'      => array( 'offering', 'Heading over the reader\'s description ({reader})', 'In {reader}\'s words' ),
			'offering_about'      => array( 'offering', 'The same heading, for something Hexxsy sells itself', 'About this' ),
			'offering_before'     => array( 'offering', 'Heading over the reader\'s "Before you buy"', 'Before you buy' ),
			'offering_how'        => array( 'offering', 'Heading over the steps', 'How it goes' ),
			'offering_hexx'       => array( 'offering', 'Label over your line under the price box', 'Hexx' ),
			'offering_back'       => array( 'offering', 'Link at the bottom ({numeral} is the room number)', 'Back to Room {numeral}' ),
			'fact_format'         => array( 'offering', 'Price box, label: format', 'Format' ),
			'fact_arrives'        => array( 'offering', 'Price box, label: when a written reading arrives', 'Arrives' ),
			'fact_length'         => array( 'offering', 'Price box, label: a live call\'s length', 'Length' ),
			'fact_written'        => array( 'offering', 'Price box, format of a written reading', 'Written, as a PDF to keep' ),
			'fact_live'           => array( 'offering', 'Price box, format of a live call', 'Live call' ),
			'fact_arrives_value'  => array( 'offering', 'Price box, arrival ({days})', 'Within {days} days' ),
			'fact_arrives_one'    => array( 'offering', 'Price box, arrival for a one-day turnaround', 'Within a day' ),
			'fact_length_value'   => array( 'offering', 'Price box, length ({minutes})', '{minutes} minutes' ),

			// Buying a reading.
			'reading_policy'      => array( 'buying', 'Under a written reading\'s questions ({hours} is the cancellation window, {link}…{/link} links to the refund policy page)', 'Change your mind inside {hours} hours and you get your money back, so long as the reading hasn\'t reached you yet. If it comes late, you can cancel then too. {link}The whole policy{/link}.' ),
			'live_closed'         => array( 'buying', 'On a live call\'s page while live calls are off', 'I\'m not booking calls yet. The written readings are open.' ),

			// Late readings, cancellations and refunds.
			'due_soon_subject'    => array( 'late', 'Reader reminder before the due date, subject ({due} is the date)', 'A reading is due {due}' ),
			'due_soon_body'       => array( 'late', 'Reader reminder, body ({reading}, {order}, {due}, {link_url} is Reading orders)', "\"{reading}\" (order #{order}) is due {due}.\n\nWrite it and deliver it from Reading orders:\n{link_url}\n\nHexx" ),
			'reader_late_subject' => array( 'late', 'Reader, reading is late, subject', 'A reading is late' ),
			'reader_late_body'    => array( 'late', 'Reader, reading is late, body ({refund_date} is when it gets refunded)', "\"{reading}\" (order #{order}) was due {due} and hasn't been delivered.\n\nThe customer can cancel it for a full refund from now on. If it's still not delivered on {refund_date}, I refund it myself and you earn nothing on it.\n\nDeliver it here:\n{link_url}\n\nHexx" ),
			'customer_late_subject' => array( 'late', 'Customer, reading is late, subject', 'Your reading is late' ),
			'customer_late_body'  => array( 'late', 'Customer, reading is late, body ({name}, {reading}, {reader}, {due}, {refund_date}, {link_url} is My readings)', "{name},\n\nYour reading \"{reading}\" from {reader} was due {due}, and it hasn't come. I've told them so.\n\nYou can wait for it, or cancel for a full refund from My readings:\n{link_url}\n\nIf it still hasn't come on {refund_date}, I'll refund you without being asked.\n\nHexx" ),
			'refund_subject'      => array( 'late', 'Customer, cancelled and refunded, subject', 'Your reading is cancelled and refunded' ),
			'refund_body'         => array( 'late', 'Customer, cancelled and refunded, body ({reason} is one of the four lines below, {amount} the refund)', "{name},\n\n{reason}\n\n{amount} is on its way back to however you paid. Banks take a few days over it.\n\nHexx" ),
			'refund_reason_window' => array( 'late', 'Customer reason: they cancelled in time', 'You cancelled "{reading}" from {reader}.' ),
			'refund_reason_late'  => array( 'late', 'Customer reason: they cancelled a late reading', 'You cancelled "{reading}" from {reader} because it was late. Fair enough.' ),
			'refund_reason_auto'  => array( 'late', 'Customer reason: late past the grace period', '"{reading}" from {reader} never came, so I\'ve cancelled it.' ),
			'refund_reason_revoked' => array( 'late', 'Customer reason: the reader was revoked', '{reader} no longer reads at Hexxsy, so "{reading}" is cancelled.' ),
			'reader_cancelled_subject' => array( 'late', 'Reader, an order was cancelled, subject', 'An order was cancelled' ),
			'reader_cancelled_body' => array( 'late', 'Reader, an order was cancelled, body ({reason} is one of the three lines below)', "\"{reading}\" (order #{order}) was cancelled and refunded. {reason}\n\nThere's nothing to deliver, and nothing is owed on it.\n\nHexx" ),
			'reader_reason_window' => array( 'late', 'Reader reason: customer cancelled in time ({hours})', 'The customer changed their mind inside the first {hours} hours.' ),
			'reader_reason_late'  => array( 'late', 'Reader reason: customer cancelled a late reading', 'It was late, and the customer cancelled.' ),
			'reader_reason_auto'  => array( 'late', 'Reader reason: late past the grace period ({days})', 'It was {days} days late.' ),

			// Closing a reader's room.
			'revoke_subject'      => array( 'revoke', 'Reader revoked, subject', 'Your room at Hexxsy is closed' ),
			'revoke_body'         => array( 'revoke', 'Reader revoked, nothing left to deliver', "Your room at Hexxsy is closed, and your readings are off sale.\n\nAny orders you hadn't delivered have been refunded to the customers. Whatever you'd already earned will still be paid.\n\nHexx" ),
			'revoke_finish_body'  => array( 'revoke', 'Reader revoked, orders still to deliver ({count} is how many, {link_url} is Reading orders)', "Your room at Hexxsy is closed, and your readings are off sale.\n\nCustomers have paid for readings you haven't delivered yet: {count}, by my count. Deliver them from Reading orders and you'll be paid for them as usual:\n{link_url}\n\nAnything that runs late gets refunded.\n\nHexx" ),
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
			'room'     => __( "A reader's room", 'hexxsy-vendors' ),
			'offering' => __( "An offering's page", 'hexxsy-vendors' ),
			'buying'   => __( 'Buying a reading', 'hexxsy-vendors' ),
			'late'   => __( 'Late readings, cancellations and refunds', 'hexxsy-vendors' ),
			'revoke' => __( "Closing a reader's room", 'hexxsy-vendors' ),
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
