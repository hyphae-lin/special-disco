<?php
/**
 * Email definitions and defaults. These run without WordPress.
 *
 * @package Hexxsy\Vendors
 */

use Hexxsy\Vendors\Emails;
use Hexxsy\Vendors\Lines;
use PHPUnit\Framework\TestCase;

if ( ! function_exists( '__' ) ) {
	function __( $text ) { // phpcs:ignore -- WordPress stub for tests.
		return $text;
	}
}

require_once dirname( __DIR__ ) . '/includes/class-lines.php';
require_once dirname( __DIR__ ) . '/includes/class-emails.php';

final class EmailsTest extends TestCase {

	public function test_every_email_is_complete(): void {
		foreach ( Emails::definitions() as $key => $def ) {
			foreach ( array( 'title', 'description', 'subject', 'heading', 'body' ) as $field ) {
				$this->assertNotSame( '', $def[ $field ], $key . ' ' . $field );
			}
			$this->assertContains( $def['to'], array( 'customer', 'admin' ), $key );
			$this->assertIsBool( $def['toggle'], $key );
		}
	}

	public function test_every_placeholder_is_explained(): void {
		foreach ( Emails::definitions() as $key => $def ) {
			$used = Lines::placeholders_in( $def['subject'] . ' ' . $def['heading'] . ' ' . $def['body'] );
			$this->assertEqualsCanonicalizing( array_keys( $def['fills'] ), $used, $key );
		}
	}

	public function test_the_invite_cannot_be_switched_off(): void {
		$this->assertFalse( Emails::definition( 'reader_invite' )['toggle'] );
	}

	public function test_a_line_changed_on_hexxs_lines_carries_over(): void {
		$lines = array( 'invite_body' => 'Come up, {link_url}' );
		$this->assertSame( 'Come up, {link_url}', Emails::default_text( 'reader_invite', 'body', $lines ) );
		$this->assertSame( 'A key to a room at Hexxsy', Emails::default_text( 'reader_invite', 'subject', $lines ) );
	}

	public function test_built_in_text_when_nothing_was_changed(): void {
		$this->assertSame( 'Your name is in the ledger', Emails::default_text( 'ledger_signed', 'subject', array( 'ledger_email_subject' => '  ' ) ) );
		$this->assertSame( '', Emails::default_text( 'no_such_email', 'body', array() ) );
	}

	public function test_moved_lines_are_gone_from_hexxs_lines(): void {
		foreach ( Emails::definitions() as $def ) {
			foreach ( $def['legacy'] as $line_key ) {
				$this->assertArrayNotHasKey( $line_key, Lines::defaults() );
			}
		}
	}

	public function test_the_built_in_texts_match_what_hexxs_lines_had(): void {
		$this->assertStringContainsString( "it goes stale in {days} days:\n\n{link_url}", Emails::definition( 'reader_invite' )['body'] );
		$this->assertStringStartsWith( 'You signed the reader ledger at Hexxsy.', Emails::definition( 'ledger_signed' )['body'] );
	}
}
