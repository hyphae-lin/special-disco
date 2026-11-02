<?php
/**
 * The plugin's emails, as WooCommerce emails.
 *
 * @package Hexxsy\Vendors
 */

namespace Hexxsy\Vendors;

/**
 * Every email the plugin sends about readers lives in WooCommerce > Settings
 * > Emails, next to the order emails, with its own subject, heading, body
 * and on/off switch, and WooCommerce's email design around it.
 *
 * The texts here are the defaults. The invite and Ledger emails used to be
 * edited on Hexx's lines; anything changed there carries over as the
 * default until it's edited in WooCommerce.
 *
 * Reading delivery still sends its own email (it carries the PDF).
 */
class Emails {

	/**
	 * Every email: key => definition.
	 *
	 * to:     'customer' (the address passed in) or 'admin' (a setting,
	 *         the site admin email by default).
	 * toggle: whether it can be switched off. The invite can't, since its
	 *         link reaches the reader no other way.
	 * fills:  placeholder => what it's filled with.
	 * legacy: Hexx's lines keys that held the subject and body before 0.9.0.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function definitions(): array {
		return array(
			'reader_invite'      => array(
				'title'       => __( 'Hexxsy: reader invite', 'hexxsy-vendors' ),
				'description' => __( 'Sent when you invite someone from Hexxsy > Readers. It carries their single-use application link, so it is always sent.', 'hexxsy-vendors' ),
				'to'          => 'customer',
				'toggle'      => false,
				'subject'     => 'A key to a room at Hexxsy',
				'heading'     => 'A key to a room',
				'body'        => "I've kept a room aside for you at Hexxsy.\n\nThis link opens the reader application. It works once, for an account with this email address, and it goes stale in {days} days:\n\n{link_url}\n\nIf you don't have an account yet, the page will show you where to make one.\n\nHexx",
				'fills'       => array(
					'link_url' => __( 'the invite link itself', 'hexxsy-vendors' ),
					'days'     => __( 'how many days the invite lasts', 'hexxsy-vendors' ),
				),
				'legacy'      => array(
					'subject' => 'invite_subject',
					'body'    => 'invite_body',
				),
			),
			'ledger_signed'      => array(
				'title'       => __( 'Hexxsy: Ledger signed', 'hexxsy-vendors' ),
				'description' => __( 'Sent to someone who signs the reader Ledger (waitlist).', 'hexxsy-vendors' ),
				'to'          => 'customer',
				'toggle'      => true,
				'subject'     => 'Your name is in the ledger',
				'heading'     => 'Your name is in the ledger',
				'body'        => "You signed the reader ledger at Hexxsy. I go through it on Sundays, and if it fits, I'll write to you with an invitation.\n\nIf you didn't sign it, ignore this and nothing more will come.\n\nHexx",
				'fills'       => array(),
				'legacy'      => array(
					'subject' => 'ledger_email_subject',
					'body'    => 'ledger_email_body',
				),
			),
			'new_application'    => array(
				'title'       => __( 'Hexxsy: new reader application', 'hexxsy-vendors' ),
				'description' => __( 'Sent to you when an invited reader sends in their application.', 'hexxsy-vendors' ),
				'to'          => 'admin',
				'toggle'      => true,
				'subject'     => 'New Hexxsy vendor application: {shop_name}',
				'heading'     => 'New reader application',
				'body'        => "A new vendor application is waiting for review:\n{review_url}",
				'fills'       => array(
					'shop_name'  => __( 'the shop name they applied with', 'hexxsy-vendors' ),
					'review_url' => __( 'a link to Hexxsy > Readers', 'hexxsy-vendors' ),
				),
				'legacy'      => array(),
			),
			'application_approved' => array(
				'title'       => __( 'Hexxsy: application approved', 'hexxsy-vendors' ),
				'description' => __( 'Sent to a reader when you approve their application.', 'hexxsy-vendors' ),
				'to'          => 'customer',
				'toggle'      => true,
				'subject'     => 'Your Hexxsy vendor application was approved',
				'heading'     => 'Welcome to Hexxsy',
				'body'        => 'Welcome to Hexxsy. Your vendor account is approved, and you can now set up your readings.',
				'fills'       => array(),
				'legacy'      => array(),
			),
			'application_rejected' => array(
				'title'       => __( 'Hexxsy: application not approved', 'hexxsy-vendors' ),
				'description' => __( 'Sent when you reject an application or revoke a reader.', 'hexxsy-vendors' ),
				'to'          => 'customer',
				'toggle'      => true,
				'subject'     => 'About your Hexxsy vendor application',
				'heading'     => 'About your application',
				'body'        => 'Thank you for applying to sell on Hexxsy. We are not able to approve your application at this time.',
				'fills'       => array(),
				'legacy'      => array(),
			),
		);
	}

	/**
	 * One definition.
	 *
	 * @param string $key Email key.
	 * @return array<string,mixed>
	 */
	public static function definition( string $key ): array {
		return self::definitions()[ $key ] ?? array();
	}

	/**
	 * Default subject, heading or body. A line changed on Hexx's lines before
	 * the emails moved wins over the built-in text.
	 *
	 * @param string               $key   Email key.
	 * @param string               $field subject, heading or body.
	 * @param array<string,string> $lines Saved Hexx's lines.
	 */
	public static function default_text( string $key, string $field, array $lines ): string {
		$def    = self::definition( $key );
		$legacy = $def['legacy'][ $field ] ?? '';
		if ( '' !== $legacy && isset( $lines[ $legacy ] ) && '' !== trim( (string) $lines[ $legacy ] ) ) {
			return (string) $lines[ $legacy ];
		}
		return (string) ( $def[ $field ] ?? '' );
	}

	/**
	 * Hook in.
	 */
	public static function init(): void {
		add_filter( 'woocommerce_email_classes', array( __CLASS__, 'register' ) );
		add_filter( 'woocommerce_email_groups', array( __CLASS__, 'group' ) );
	}

	/**
	 * A Hexxsy group on WooCommerce > Settings > Emails (WooCommerce 10.3+).
	 *
	 * @param array<string,string> $groups Email groups.
	 * @return array<string,string>
	 */
	public static function group( $groups ) {
		$groups['hexxsy'] = __( 'Hexxsy', 'hexxsy-vendors' );
		return $groups;
	}

	/**
	 * Add the emails to WooCommerce.
	 *
	 * @param array<string,\WC_Email> $emails WooCommerce's emails.
	 * @return array<string,\WC_Email>
	 */
	public static function register( $emails ) {
		require_once __DIR__ . '/class-email-classes.php';
		foreach ( Email_Base::classes() as $class ) {
			$emails[ 'Hexxsy_' . $class::KEY ] = new $class();
		}
		return $emails;
	}

	/**
	 * Send one email.
	 *
	 * @param string               $key  Email key.
	 * @param string               $to   Recipient, for customer emails.
	 * @param array<string,string> $vars Placeholder values.
	 */
	public static function send( string $key, string $to, array $vars = array() ): void {
		$mailer = function_exists( 'WC' ) ? WC()->mailer() : null;
		if ( $mailer ) {
			foreach ( $mailer->get_emails() as $email ) {
				if ( $email instanceof Email_Base && $email::KEY === $key ) {
					$email->trigger( $to, $vars );
					return;
				}
			}
		}

		// WooCommerce's mailer isn't there: send the defaults as plain text.
		$lines = get_option( Lines::OPTION, array() );
		$lines = is_array( $lines ) ? $lines : array();
		$to    = 'admin' === ( self::definition( $key )['to'] ?? '' ) ? (string) get_option( 'admin_email' ) : $to;
		wp_mail(
			$to,
			Lines::fill( self::default_text( $key, 'subject', $lines ), $vars ),
			Lines::fill( self::default_text( $key, 'body', $lines ), $vars )
		);
	}
}
