<?php
/**
 * Hexxsy's emails: definitions, and carrying wording over from Hexx's lines.
 * These run without WordPress.
 *
 * @package Hexxsy\Vendors
 */

use Hexxsy\Vendors\Emails;
use Hexxsy\Vendors\Lines;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-lines.php';
require_once dirname( __DIR__ ) . '/includes/class-emails.php';

final class EmailsTest extends TestCase {

	public function test_every_email_is_complete(): void {
		$this->assertCount( 11, Emails::definitions() );
		foreach ( Emails::definitions() as $id => $email ) {
			foreach ( array( 'title', 'description', 'subject', 'body' ) as $field ) {
				$this->assertNotSame( '', $email[ $field ], "$id $field" );
			}
			$this->assertContains( $email['to'], array( 'customer', 'reader', 'person' ), $id );
			foreach ( $email['extra'] as $key => $extra ) {
				$this->assertNotSame( '', $extra[0], "$id $key" );
				$this->assertNotSame( '', $extra[1], "$id $key" );
			}
		}
	}

	public function test_no_email_wording_is_left_in_hexxs_lines(): void {
		foreach ( array_keys( Emails::moved_lines() ) as $key ) {
			$this->assertArrayNotHasKey( $key, Lines::defaults(), $key );
		}
	}

	public function test_every_moved_line_lands_on_a_real_setting(): void {
		$emails = Emails::definitions();
		$this->assertCount( 24, Emails::moved_lines() );
		foreach ( Emails::moved_lines() as $key => $target ) {
			$this->assertArrayHasKey( $target[0], $emails, $key );
			$this->assertTrue( in_array( $target[1], array( 'subject', 'body' ), true ) || isset( $emails[ $target[0] ]['extra'][ $target[1] ] ), $key );
		}
	}

	public function test_changed_lines_become_email_settings(): void {
		$settings = Emails::settings_from_lines(
			array(
				'invite_subject'       => 'A key for you',
				'revoke_finish_body'   => 'Finish up: {count}.',
				'refund_reason_late'   => 'It was late.',
				'ledger_email_body'    => '   ',
				'rooms_knock'          => 'Rap twice',
			)
		);
		$this->assertSame(
			array(
				'revoke' => array( 'body_finish' => 'Finish up: {count}.' ),
				'invite' => array( 'subject' => 'A key for you' ),
				'refund' => array( 'reason_late' => 'It was late.' ),
			),
			$settings
		);
	}

	public function test_placeholders_fill_and_unknown_ones_stay(): void {
		$this->assertSame( 'Due Friday, {order}', Emails::fill( 'Due {due}, {order}', array( 'due' => 'Friday' ) ) );
	}
}
