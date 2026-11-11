<?php
/**
 * Hexx's lines and room numbering. These run without WordPress.
 *
 * @package Hexxsy\Vendors
 */

use Hexxsy\Vendors\Lines;
use Hexxsy\Vendors\Rooms;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-lines.php';
require_once dirname( __DIR__ ) . '/includes/class-rooms.php';

final class LinesRoomsTest extends TestCase {

	public function test_fill_replaces_known_placeholders_only(): void {
		$this->assertSame( '3 rooms stand empty.', Lines::fill( '{count} rooms stand empty.', array( 'count' => '3' ) ) );
		$this->assertSame( 'Hello {name}', Lines::fill( 'Hello {name}', array() ) );
	}

	public function test_linkify_makes_a_link_and_escapes_text(): void {
		$e    = static fn( string $s ): string => htmlspecialchars( $s, ENT_QUOTES );
		$html = Lines::linkify( 'Put your <name> in the {link}ledger{/link}.', 'https://x.test/ledger', $e, $e );
		$this->assertSame( 'Put your &lt;name&gt; in the <a href="https://x.test/ledger">ledger</a>.', $html );
	}

	public function test_linkify_without_url_leaves_plain_words(): void {
		$e = static fn( string $s ): string => htmlspecialchars( $s, ENT_QUOTES );
		$this->assertSame( 'In the ledger.', Lines::linkify( 'In the {link}ledger{/link}.', '', $e, $e ) );
	}

	public function test_every_default_has_group_label_and_text(): void {
		$groups = array( 'rooms', 'ledger', 'apply', 'room', 'offering', 'buying', 'late', 'revoke' );
		foreach ( Lines::defaults() as $key => $line ) {
			$this->assertContains( $line[0], $groups, $key );
			$this->assertNotSame( '', $line[1], $key );
			$this->assertNotSame( '', $line[2], $key );
		}
	}

	public function test_saving_one_tab_leaves_the_others_alone(): void {
		$same  = static fn( string $s ): string => $s;
		$saved = array( 'rooms_knock' => 'Rap twice', 'ledger_thanks' => 'Noted.' );
		$out   = Lines::merge( $saved, array( 'rooms_knock' => 'Come in' ), $same );
		$this->assertSame( array( 'rooms_knock' => 'Come in', 'ledger_thanks' => 'Noted.' ), $out );
	}

	public function test_a_line_left_as_the_original_is_not_stored(): void {
		$same     = static fn( string $s ): string => $s;
		$original = Lines::defaults()['ledger_email_body'][2];
		// The box arrives prefilled, with the browser's \r\n line breaks.
		$out = Lines::merge( array(), array( 'ledger_email_body' => str_replace( "\n", "\r\n", $original ), 'rooms_knock' => 'Knock' ), $same );
		$this->assertSame( array(), $out );
	}

	public function test_emptying_a_box_brings_back_the_original(): void {
		$same = static fn( string $s ): string => $s;
		$this->assertSame( array(), Lines::merge( array( 'rooms_knock' => 'Rap twice' ), array( 'rooms_knock' => '  ' ), $same ) );
	}

	public function test_unknown_lines_are_dropped(): void {
		$same = static fn( string $s ): string => $s;
		$this->assertSame( array(), Lines::merge( array( 'gone' => 'x' ), array( 'nope' => 'y' ), $same ) );
	}

	public function test_room_numerals(): void {
		$this->assertSame( 'I', Rooms::numeral( 1 ) );
		$this->assertSame( 'IV', Rooms::numeral( 4 ) );
		$this->assertSame( 'XII', Rooms::numeral( 12 ) );
		$this->assertSame( 'XIV', Rooms::numeral( 14 ) );
		$this->assertSame( 'XL', Rooms::numeral( 40 ) );
		$this->assertSame( '', Rooms::numeral( 0 ) );
	}
}
