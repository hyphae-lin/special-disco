<?php
/**
 * One Hexxsy email, as WooCommerce sees it.
 *
 * Loaded only when WooCommerce builds its email list, since it extends
 * WooCommerce's email class.
 *
 * @package Hexxsy\Vendors
 */

namespace Hexxsy\Vendors;

/**
 * A WooCommerce email whose message is one editable box. It appears under
 * WooCommerce > Settings > Emails with an on/off switch, subject, heading,
 * message and any extra boxes its definition names, and is sent in
 * WooCommerce's email design (or as plain text, if that's chosen).
 */
class Hexx_Email extends \WC_Email {

	/**
	 * Definition from Emails::definitions().
	 *
	 * @var array<string,mixed>
	 */
	private $definition;

	/**
	 * Which message box this send uses.
	 *
	 * @var string
	 */
	private $body_field = 'body';

	/**
	 * Files attached to this send.
	 *
	 * @var string[]
	 */
	private $files = array();

	/**
	 * Set up.
	 *
	 * @param string              $id         Email id.
	 * @param array<string,mixed> $definition Definition.
	 */
	public function __construct( string $id, array $definition ) {
		$this->definition     = $definition;
		$this->id             = 'hexxsy_' . $id;
		$this->title          = 'Hexxsy: ' . $definition['title'];
		$this->description    = $definition['description'];
		$this->customer_email = true;
		$this->placeholders   = array();

		parent::__construct();
	}

	/**
	 * Default subject.
	 */
	public function get_default_subject() {
		return $this->definition['subject'];
	}

	/**
	 * Default heading: the subject, which reads well at the top of the email.
	 */
	public function get_default_heading() {
		return $this->definition['subject'];
	}

	/**
	 * A setting's wording, falling back to its default when empty.
	 *
	 * @param string $field Setting key: body, or one of the extra boxes.
	 */
	public function wording( string $field ): string {
		$default = 'body' === $field ? $this->definition['body'] : ( $this->definition['extra'][ $field ][1] ?? '' );
		$text    = (string) $this->get_option( $field, $default );
		return '' !== trim( $text ) ? $text : $default;
	}

	/**
	 * The settings screen.
	 */
	public function init_form_fields() {
		$who = array(
			'customer' => __( 'Goes to the customer.', 'hexxsy-vendors' ),
			'reader'   => __( 'Goes to the reader.', 'hexxsy-vendors' ),
			'person'   => __( 'Goes to the address that was typed in.', 'hexxsy-vendors' ),
		);
		$help = trim( ( $who[ $this->definition['to'] ] ?? '' ) . ' ' . __( 'Words in {braces} are filled in when the email is sent; keep them as they are.', 'hexxsy-vendors' ) . ' ' . $this->definition['help'] );

		$this->form_fields = array(
			'enabled' => array(
				'title'   => __( 'Enable/Disable', 'hexxsy-vendors' ),
				'type'    => 'checkbox',
				'label'   => __( 'Send this email', 'hexxsy-vendors' ),
				'default' => 'yes',
			),
			'subject' => array(
				'title'       => __( 'Subject', 'hexxsy-vendors' ),
				'type'        => 'text',
				'desc_tip'    => true,
				'description' => $help,
				'placeholder' => $this->get_default_subject(),
				'default'     => $this->get_default_subject(),
			),
			'heading' => array(
				'title'       => __( 'Email heading', 'hexxsy-vendors' ),
				'type'        => 'text',
				'desc_tip'    => true,
				'description' => __( 'The large line at the top of the email.', 'hexxsy-vendors' ),
				'placeholder' => $this->get_default_heading(),
				'default'     => $this->get_default_heading(),
			),
			'body'    => array(
				'title'       => __( 'Message', 'hexxsy-vendors' ),
				'type'        => 'textarea',
				'css'         => 'width:400px; height: 220px;',
				'description' => $help,
				'default'     => $this->definition['body'],
			),
		);

		foreach ( $this->definition['extra'] as $key => $extra ) {
			$this->form_fields[ $key ] = array(
				'title'   => $extra[0],
				'type'    => 'textarea',
				'css'     => 'width:400px; height: ' . ( false !== strpos( $extra[1], "\n" ) ? '220' : '75' ) . 'px;',
				'default' => $extra[1],
			);
		}

		$this->form_fields['email_type'] = array(
			'title'       => __( 'Email type', 'hexxsy-vendors' ),
			'type'        => 'select',
			'description' => __( 'Choose which format of email to send.', 'hexxsy-vendors' ),
			'default'     => 'html',
			'class'       => 'email_type wc-enhanced-select',
			'options'     => $this->get_email_type_options(),
			'desc_tip'    => true,
		);
	}

	/**
	 * Send it.
	 *
	 * @param string               $to          Recipient address.
	 * @param array<string,string> $vars        Placeholder values.
	 * @param string[]             $attachments File paths.
	 * @param string               $body_field  Which message box to use.
	 */
	public function deliver( string $to, array $vars, array $attachments, string $body_field ): bool {
		if ( ! $this->is_enabled() || '' === $to ) {
			return false;
		}

		$this->setup_locale();
		$this->recipient    = $to;
		$this->body_field   = $body_field;
		$this->files        = $attachments;
		$this->placeholders = array();
		foreach ( $vars as $key => $value ) {
			$this->placeholders[ '{' . $key . '}' ] = (string) $value;
		}

		$sent = (bool) $this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
		$this->restore_locale();
		return $sent;
	}

	/**
	 * Files to attach.
	 */
	public function get_attachments() {
		return $this->files;
	}

	/**
	 * The message with its {braces} filled in.
	 */
	private function message(): string {
		return (string) $this->format_string( $this->wording( $this->body_field ) );
	}

	/**
	 * HTML version: the message inside WooCommerce's email header and footer.
	 */
	public function get_content_html() {
		ob_start();
		wc_get_template( 'emails/email-header.php', array( 'email_heading' => $this->get_heading(), 'email' => $this ) );
		echo wp_kses_post( wpautop( make_clickable( esc_html( $this->message() ) ) ) );
		wc_get_template( 'emails/email-footer.php', array( 'email' => $this ) );
		return (string) ob_get_clean();
	}

	/**
	 * Plain-text version.
	 */
	public function get_content_plain() {
		return $this->message();
	}
}
