<?php
/**
 * WooCommerce email classes for the plugin's emails. Loaded only once
 * WooCommerce's emails are, from Emails::register().
 *
 * @package Hexxsy\Vendors
 */

namespace Hexxsy\Vendors;

defined( 'ABSPATH' ) || exit;

/**
 * One plain-text email from Emails::definitions(), with an editable
 * subject, heading and body. The body is plain text: blank lines make
 * paragraphs and links become clickable.
 */
abstract class Email_Base extends \WC_Email {

	const KEY = '';

	/**
	 * Every concrete email class.
	 *
	 * @return string[]
	 */
	public static function classes(): array {
		return array(
			Email_Reader_Invite::class,
			Email_Ledger_Signed::class,
			Email_New_Application::class,
			Email_Application_Approved::class,
			Email_Application_Rejected::class,
		);
	}

	/**
	 * Set up from the definition.
	 */
	public function __construct() {
		$def                  = Emails::definition( static::KEY );
		$this->id             = 'hexxsy_' . static::KEY;
		$this->title          = (string) $def['title'];
		$this->description    = (string) $def['description'];
		$this->customer_email = 'admin' !== $def['to'];
		$this->email_group    = 'hexxsy';

		foreach ( array_keys( $def['fills'] ) as $name ) {
			$this->placeholders[ '{' . $name . '}' ] = '';
		}

		parent::__construct();

		if ( ! $this->customer_email ) {
			$this->recipient = $this->get_option( 'recipient', get_option( 'admin_email' ) );
		}
	}

	/**
	 * Saved Hexx's lines, for defaults carried over from before 0.9.0.
	 *
	 * @return array<string,string>
	 */
	private function lines(): array {
		$lines = get_option( Lines::OPTION, array() );
		return is_array( $lines ) ? $lines : array();
	}

	/**
	 * Default subject.
	 */
	public function get_default_subject() {
		return Emails::default_text( static::KEY, 'subject', $this->lines() );
	}

	/**
	 * Default heading.
	 */
	public function get_default_heading() {
		return Emails::default_text( static::KEY, 'heading', $this->lines() );
	}

	/**
	 * Default body.
	 */
	public function get_default_body(): string {
		return Emails::default_text( static::KEY, 'body', $this->lines() );
	}

	/**
	 * Body with placeholders filled. An emptied box uses the default.
	 */
	public function get_body(): string {
		$body = (string) $this->get_option( 'body', '' );
		return $this->format_string( '' === trim( $body ) ? $this->get_default_body() : $body );
	}

	/**
	 * The invite can't be switched off.
	 */
	public function is_enabled() {
		$def = Emails::definition( static::KEY );
		return empty( $def['toggle'] ) ? true : parent::is_enabled();
	}

	/**
	 * Send it.
	 *
	 * @param string               $to   Recipient, for customer emails.
	 * @param array<string,string> $vars Placeholder values.
	 */
	public function trigger( string $to, array $vars = array() ): void {
		$this->setup_locale();

		if ( $this->customer_email ) {
			$this->recipient = $to;
		}
		foreach ( $vars as $name => $value ) {
			$this->placeholders[ '{' . $name . '}' ] = (string) $value;
		}

		if ( $this->is_enabled() && $this->get_recipient() ) {
			$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
		}

		$this->restore_locale();
	}

	/**
	 * HTML version, in WooCommerce's email design.
	 */
	public function get_content_html() {
		ob_start();
		do_action( 'woocommerce_email_header', $this->get_heading(), $this );
		echo wp_kses_post( wpautop( make_clickable( esc_html( $this->get_body() ) ) ) );
		do_action( 'woocommerce_email_footer', $this );
		return (string) ob_get_clean();
	}

	/**
	 * Plain-text version.
	 */
	public function get_content_plain() {
		return '= ' . wp_strip_all_tags( $this->get_heading() ) . " =\n\n" . $this->get_body() . "\n";
	}

	/**
	 * Settings fields.
	 */
	public function init_form_fields() {
		$def   = Emails::definition( static::KEY );
		$fills = array( '{site_title}' => __( 'the site name', 'hexxsy-vendors' ) );
		foreach ( $def['fills'] as $name => $what ) {
			$fills[ '{' . $name . '}' ] = $what;
		}
		$list = implode(
			'; ',
			array_map(
				static fn( $tag, $what ) => $tag . ' ' . $what,
				array_keys( $fills ),
				$fills
			)
		);
		/* translators: %s: list of placeholders and what they become. */
		$help = sprintf( __( 'Filled in for you: %s.', 'hexxsy-vendors' ), $list );

		$fields = array();
		if ( ! empty( $def['toggle'] ) ) {
			$fields['enabled'] = array(
				'title'   => __( 'Enable/Disable', 'hexxsy-vendors' ),
				'type'    => 'checkbox',
				'label'   => __( 'Send this email', 'hexxsy-vendors' ),
				'default' => 'yes',
			);
		}
		if ( 'admin' === $def['to'] ) {
			$fields['recipient'] = array(
				'title'       => __( 'Recipient(s)', 'hexxsy-vendors' ),
				'type'        => 'text',
				/* translators: %s: admin email */
				'description' => sprintf( __( 'Separate addresses with commas. Defaults to %s.', 'hexxsy-vendors' ), esc_html( get_option( 'admin_email' ) ) ),
				'placeholder' => '',
				'default'     => '',
				'desc_tip'    => true,
			);
		}
		$fields['subject']    = array(
			'title'       => __( 'Subject', 'hexxsy-vendors' ),
			'type'        => 'text',
			'description' => $help,
			'placeholder' => $this->get_default_subject(),
			'default'     => '',
			'desc_tip'    => true,
		);
		$fields['heading']    = array(
			'title'       => __( 'Email heading', 'hexxsy-vendors' ),
			'type'        => 'text',
			'description' => $help,
			'placeholder' => $this->get_default_heading(),
			'default'     => '',
			'desc_tip'    => true,
		);
		$fields['body']       = array(
			'title'       => __( 'Message', 'hexxsy-vendors' ),
			'type'        => 'textarea',
			'css'         => 'width:400px; height:220px;',
			'description' => $help . ' ' . __( 'Plain text. A blank line starts a new paragraph, and web addresses become links. Leave it empty to use the default shown in the box.', 'hexxsy-vendors' ),
			'placeholder' => $this->get_default_body(),
			'default'     => $this->get_default_body(),
		);
		$fields['email_type'] = array(
			'title'       => __( 'Email type', 'hexxsy-vendors' ),
			'type'        => 'select',
			'description' => __( 'Choose which format of email to send.', 'hexxsy-vendors' ),
			'default'     => 'html',
			'class'       => 'email_type wc-enhanced-select',
			'options'     => $this->get_email_type_options(),
			'desc_tip'    => true,
		);

		$this->form_fields = $fields;
	}
}

/** Reader invite. */
class Email_Reader_Invite extends Email_Base {
	const KEY = 'reader_invite';
}

/** Ledger signed. */
class Email_Ledger_Signed extends Email_Base {
	const KEY = 'ledger_signed';
}

/** New application, to the admin. */
class Email_New_Application extends Email_Base {
	const KEY = 'new_application';
}

/** Application approved. */
class Email_Application_Approved extends Email_Base {
	const KEY = 'application_approved';
}

/** Application rejected or reader revoked. */
class Email_Application_Rejected extends Email_Base {
	const KEY = 'application_rejected';
}
