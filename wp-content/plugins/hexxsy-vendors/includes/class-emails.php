<?php
/**
 * Hexxsy's emails, as WooCommerce emails.
 *
 * @package Hexxsy\Vendors
 */

namespace Hexxsy\Vendors;

/**
 * Every email Hexxsy sends to a customer, a reader or someone on the
 * waitlist is a WooCommerce email. Each one has its own screen under
 * WooCommerce > Settings > Emails, with the usual on/off switch, subject,
 * heading and message, and goes out in WooCommerce's email design.
 *
 * Words in {braces} in a subject or message are filled in when it's sent.
 *
 * Emails to the admin (a new application, a refund that failed) are plain
 * notices and stay in code.
 */
class Emails {

	const MIGRATED = 'hexxsy_emails_migrated';

	/**
	 * Every email: id => title, description, who gets it, help on the
	 * {braces} it can use, default subject and message, and any extra
	 * boxes (key => [label, default]).
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function definitions(): array {
		return array(
			'ledger_signed' => array(
				'title'       => 'Ledger: name signed',
				'description' => 'Sent to someone who signs the reader waitlist.',
				'to'          => 'person',
				'help'        => '',
				'subject'     => 'Your name is in the ledger',
				'body'        => "You signed the reader ledger at Hexxsy. I go through it on Sundays, and if it fits, I'll write to you with an invitation.\n\nIf you didn't sign it, ignore this and nothing more will come.\n\nHexx",
				'extra'       => array(
				),
			),
			'invite' => array(
				'title'       => 'Reader invitation',
				'description' => 'Sent when you invite someone to apply for a room. The link in it is the only way in, so leave this one switched on.',
				'to'          => 'person',
				'help'        => '{link_url} is the invite link, {days} how long it lasts.',
				'subject'     => 'A key to a room at Hexxsy',
				'body'        => "I've kept a room aside for you at Hexxsy.\n\nThis link opens the reader application. It works once, for an account with this email address, and it goes stale in {days} days:\n\n{link_url}\n\nIf you don't have an account yet, the page will show you where to make one.\n\nHexx",
				'extra'       => array(
				),
			),
			'due_soon' => array(
				'title'       => 'Reading due soon',
				'description' => 'Reminds a reader before a written reading is due.',
				'to'          => 'reader',
				'help'        => '{reading}, {order}, {due}, and {link_url} for Reading orders.',
				'subject'     => 'A reading is due {due}',
				'body'        => "\"{reading}\" (order #{order}) is due {due}.\n\nWrite it and deliver it from Reading orders:\n{link_url}\n\nHexx",
				'extra'       => array(
				),
			),
			'reader_late' => array(
				'title'       => 'Reading late (reader)',
				'description' => 'Tells a reader a written reading has passed its due date.',
				'to'          => 'reader',
				'help'        => '{reading}, {order}, {due}, {refund_date} for the day it gets refunded, and {link_url} for Reading orders.',
				'subject'     => 'A reading is late',
				'body'        => "\"{reading}\" (order #{order}) was due {due} and hasn't been delivered.\n\nThe customer can cancel it for a full refund from now on. If it's still not delivered on {refund_date}, I refund it myself and you earn nothing on it.\n\nDeliver it here:\n{link_url}\n\nHexx",
				'extra'       => array(
				),
			),
			'customer_late' => array(
				'title'       => 'Reading late (customer)',
				'description' => 'Tells a customer their written reading is late and that they can cancel.',
				'to'          => 'customer',
				'help'        => '{name}, {reading}, {reader}, {due}, {refund_date}, and {link_url} for My readings.',
				'subject'     => 'Your reading is late',
				'body'        => "{name},\n\nYour reading \"{reading}\" from {reader} was due {due}, and it hasn't come. I've told them so.\n\nYou can wait for it, or cancel for a full refund from My readings:\n{link_url}\n\nIf it still hasn't come on {refund_date}, I'll refund you without being asked.\n\nHexx",
				'extra'       => array(
				),
			),
			'refund' => array(
				'title'       => 'Reading cancelled and refunded',
				'description' => 'Sent to a customer when a reading is cancelled and refunded, whatever the cause.',
				'to'          => 'customer',
				'help'        => '{name}, {reading}, {reader}, {amount}, and {reason}, which is whichever of the reasons below applies.',
				'subject'     => 'Your reading is cancelled and refunded',
				'body'        => "{name},\n\n{reason}\n\n{amount} is on its way back to however you paid. Banks take a few days over it.\n\nHexx",
				'extra'       => array(
					'reason_window' => array( 'Reason: they cancelled in time', 'You cancelled "{reading}" from {reader}.' ),
					'reason_late' => array( 'Reason: they cancelled a late reading', 'You cancelled "{reading}" from {reader} because it was late. Fair enough.' ),
					'reason_auto' => array( 'Reason: late past the grace period', '"{reading}" from {reader} never came, so I\'ve cancelled it.' ),
					'reason_revoked' => array( 'Reason: the reader was revoked', '{reader} no longer reads at Hexxsy, so "{reading}" is cancelled.' ),
				),
			),
			'reader_cancelled' => array(
				'title'       => 'Order cancelled (reader)',
				'description' => 'Tells a reader that an order of theirs was cancelled and refunded.',
				'to'          => 'reader',
				'help'        => '{reading}, {order}, and {reason}, which is whichever of the reasons below applies. The reasons can use {hours} and {days}.',
				'subject'     => 'An order was cancelled',
				'body'        => "\"{reading}\" (order #{order}) was cancelled and refunded. {reason}\n\nThere's nothing to deliver, and nothing is owed on it.\n\nHexx",
				'extra'       => array(
					'reason_window' => array( 'Reason: customer cancelled in time', 'The customer changed their mind inside the first {hours} hours.' ),
					'reason_late' => array( 'Reason: customer cancelled a late reading', 'It was late, and the customer cancelled.' ),
					'reason_auto' => array( 'Reason: late past the grace period', 'It was {days} days late.' ),
				),
			),
			'revoke' => array(
				'title'       => 'Room closed',
				'description' => 'Sent to a reader when you revoke them.',
				'to'          => 'reader',
				'help'        => 'The second message can use {count} for how many orders are left and {link_url} for Reading orders.',
				'subject'     => 'Your room at Hexxsy is closed',
				'body'        => "Your room at Hexxsy is closed, and your readings are off sale.\n\nAny orders you hadn't delivered have been refunded to the customers. Whatever you'd already earned will still be paid.\n\nHexx",
				'extra'       => array(
					'body_finish' => array( 'Message when they still have orders to deliver', "Your room at Hexxsy is closed, and your readings are off sale.\n\nCustomers have paid for readings you haven't delivered yet: {count}, by my count. Deliver them from Reading orders and you'll be paid for them as usual:\n{link_url}\n\nAnything that runs late gets refunded.\n\nHexx" ),
				),
			),
			'approved' => array(
				'title'       => 'Reader application approved',
				'description' => 'Sent to an applicant when you approve them.',
				'to'          => 'reader',
				'help'        => '{link_url} is their account page, where the reader tabs are.',
				'subject'     => 'Your Hexxsy vendor application was approved',
				'body'        => 'Welcome to Hexxsy. Your vendor account is approved, and you can now set up your readings.',
				'extra'       => array(),
			),
			'rejected' => array(
				'title'       => 'Reader application not accepted',
				'description' => 'Sent to an applicant when you reject them.',
				'to'          => 'reader',
				'help'        => '',
				'subject'     => 'About your Hexxsy vendor application',
				'body'        => 'Thank you for applying to sell on Hexxsy. We are not able to approve your application at this time.',
				'extra'       => array(),
			),
			'delivered' => array(
				'title'       => 'Reading delivered',
				'description' => 'Sent to a customer when their written reading is delivered, with the PDF attached.',
				'to'          => 'customer',
				'help'        => '{name}, {reading}, {reader}, and {link_url} for My readings.',
				'subject'     => 'Your Hexxsy reading is ready: {reading}',
				'body'        => "Hi {name},\n\nYour reading \"{reading}\" from {reader} is ready. It's attached as a PDF to keep.\n\nAll your readings are kept in your account, ready to download any time: {link_url}\n\nReadings are for entertainment purposes only.",
				'extra'       => array(),
			),
		);
	}

	/**
	 * Where each email's wording lived in Hexx's lines before it moved:
	 * old line key => [email id, setting].
	 *
	 * @return array<string,array{0:string,1:string}>
	 */
	public static function moved_lines(): array {
		$moved = array(
			'ledger_email_subject' => array( 'ledger_signed', 'subject' ),
			'ledger_email_body'    => array( 'ledger_signed', 'body' ),
			'revoke_finish_body'   => array( 'revoke', 'body_finish' ),
		);
		foreach ( array( 'invite', 'due_soon', 'reader_late', 'customer_late', 'refund', 'reader_cancelled', 'revoke' ) as $id ) {
			$moved[ $id . '_subject' ] = array( $id, 'subject' );
			$moved[ $id . '_body' ]    = array( $id, 'body' );
		}
		foreach ( array( 'window', 'late', 'auto', 'revoked' ) as $reason ) {
			$moved[ 'refund_reason_' . $reason ] = array( 'refund', 'reason_' . $reason );
		}
		foreach ( array( 'window', 'late', 'auto' ) as $reason ) {
			$moved[ 'reader_reason_' . $reason ] = array( 'reader_cancelled', 'reason_' . $reason );
		}
		return $moved;
	}

	/**
	 * The WooCommerce settings a set of saved Hexx's lines turns into:
	 * email id => [setting => text].
	 *
	 * @param array<string,string> $lines Saved lines.
	 * @return array<string,array<string,string>>
	 */
	public static function settings_from_lines( array $lines ): array {
		$settings = array();
		foreach ( self::moved_lines() as $key => $target ) {
			if ( isset( $lines[ $key ] ) && '' !== trim( (string) $lines[ $key ] ) ) {
				$settings[ $target[0] ][ $target[1] ] = (string) $lines[ $key ];
			}
		}
		return $settings;
	}

	/**
	 * Fill {placeholders} in plain text; unknown ones are left as typed.
	 *
	 * @param string               $text Text.
	 * @param array<string,string> $vars Values.
	 */
	public static function fill( string $text, array $vars ): string {
		return Lines::fill( $text, $vars );
	}

	/**
	 * Hook in.
	 */
	public static function init(): void {
		add_filter( 'woocommerce_email_classes', array( __CLASS__, 'register' ) );
		add_action( 'init', array( __CLASS__, 'maybe_migrate' ), 30 );
	}

	/**
	 * Add Hexxsy's emails to WooCommerce's list.
	 *
	 * @param array $emails WooCommerce's emails.
	 */
	public static function register( $emails ) {
		require_once HEXXSY_VENDORS_DIR . 'includes/class-hexx-email.php';
		foreach ( self::definitions() as $id => $definition ) {
			$emails[ 'Hexxsy_Email_' . $id ] = new Hexx_Email( $id, $definition );
		}
		return $emails;
	}

	/**
	 * Carry wording that was changed in Hexx's lines over to the emails'
	 * own settings, once.
	 */
	public static function maybe_migrate(): void {
		if ( get_option( self::MIGRATED ) ) {
			return;
		}
		update_option( self::MIGRATED, 1 );

		$lines = get_option( Lines::OPTION, array() );
		foreach ( self::settings_from_lines( is_array( $lines ) ? $lines : array() ) as $id => $fields ) {
			$option = 'woocommerce_hexxsy_' . $id . '_settings';
			$saved  = get_option( $option, array() );
			// Anything already set on the email's own screen wins.
			update_option( $option, ( is_array( $saved ) ? $saved : array() ) + $fields );
		}
	}

	/**
	 * One email object, or null.
	 *
	 * @param string $id Email id.
	 */
	private static function get( string $id ): ?Hexx_Email {
		$emails = WC()->mailer()->get_emails();
		$email  = $emails[ 'Hexxsy_Email_' . $id ] ?? null;
		return $email instanceof Hexx_Email ? $email : null;
	}

	/**
	 * One of an email's extra boxes (a reason line, say), filled in.
	 *
	 * @param string               $id    Email id.
	 * @param string               $field Setting key.
	 * @param array<string,string> $vars  Placeholder values.
	 */
	public static function text( string $id, string $field, array $vars = array() ): string {
		$email = self::get( $id );
		return $email ? self::fill( $email->wording( $field ), $vars ) : '';
	}

	/**
	 * Send an email.
	 *
	 * @param string               $id          Email id.
	 * @param string               $to          Recipient address.
	 * @param array<string,string> $vars        Placeholder values.
	 * @param string[]             $attachments File paths.
	 * @param string               $body        Which message to use, for emails with more than one.
	 */
	public static function send( string $id, string $to, array $vars = array(), array $attachments = array(), string $body = 'body' ): bool {
		$email = self::get( $id );
		return $email ? $email->deliver( $to, $vars, $attachments, $body ) : false;
	}
}
