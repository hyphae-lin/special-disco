<?php
/**
 * Hexx's lines and room numbering. These run without WordPress.
 *
 * @package Hexxsy\Vendors
 */

use Hexxsy\Vendors\Lines;
use Hexxsy\Vendors\Rooms;
use PHPUnit\Framework\TestCase;

if ( ! function_exists( '__' ) ) {
	function __( $text ) { // phpcs:ignore -- WordPress stub for tests.
		return $text;
	}
}

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
		$groups = array( 'rooms', 'ledger', 'apply' );
		foreach ( Lines::defaults() as $key => $line ) {
			$this->assertContains( $line[0], $groups, $key );
			$this->assertNotSame( '', $line[1], $key );
			$this->assertNotSame( '', $line[2], $key );
		}
	}

	public function test_saving_one_tab_keeps_the_other_tabs(): void {
		$saved = array(
			'rooms_knock'   => 'Enter',
			'apply_expired' => 'Too late.',
		);
		$out   = Lines::merge( $saved, array( 'rooms_knock' => 'Come in' ), 'trim' );
		$this->assertSame( 'Come in', $out['rooms_knock'] );
		$this->assertSame( 'Too late.', $out['apply_expired'] );
	}

	public function test_a_line_saved_as_its_default_is_not_stored(): void {
		$default = Lines::defaults()['rooms_knock'][2];
		$out     = Lines::merge( array( 'rooms_knock' => 'Enter' ), array( 'rooms_knock' => $default ), 'trim' );
		$this->assertArrayNotHasKey( 'rooms_knock', $out );
	}

	public function test_an_emptied_box_goes_back_to_the_default(): void {
		$out = Lines::merge( array( 'rooms_knock' => 'Enter' ), array( 'rooms_knock' => '   ' ), 'trim' );
		$this->assertArrayNotHasKey( 'rooms_knock', $out );
	}

	public function test_browser_line_endings_still_match_the_default(): void {
		$default = Lines::defaults()['ledger_email_body'][2];
		$out     = Lines::merge( array(), array( 'ledger_email_body' => str_replace( "\n", "\r\n", $default ) ), static fn( $s ) => $s );
		$this->assertArrayNotHasKey( 'ledger_email_body', $out );
	}

	public function test_unknown_keys_are_dropped(): void {
		$out = Lines::merge( array( 'nope' => 'x' ), array( 'also_nope' => 'y' ), 'trim' );
		$this->assertSame( array(), $out );
	}

	public function test_placeholders_in_a_line(): void {
		$this->assertSame( array( 'email' ), Lines::placeholders_in( 'This invite is for {email}. {link}Log in{/link}.' ) );
		$this->assertSame( array( 'link_url', 'days' ), Lines::placeholders_in( 'Go to {link_url} within {days} days, {days}.' ) );
	}

	public function test_every_placeholder_and_link_is_explained(): void {
		$help  = Lines::placeholder_help();
		$links = Lines::link_targets();
		foreach ( Lines::defaults() as $key => $line ) {
			foreach ( Lines::placeholders_in( $line[2] ) as $name ) {
				$this->assertArrayHasKey( $name, $help, $key . ' uses {' . $name . '}' );
			}
			$this->assertSame( str_contains( $line[2], '{link}' ), isset( $links[ $key ] ), $key . ' link target' );
		}
	}

	public function test_box_height_fits_the_line(): void {
		$this->assertSame( 1, Lines::rows_for( 'Knock' ) );
		$this->assertSame( 3, Lines::rows_for( str_repeat( 'a', 130 ) ) );
		$this->assertSame( 3, Lines::rows_for( "One\n\nTwo" ) );
		$this->assertSame( 12, Lines::rows_for( str_repeat( "x\n", 40 ) ) );
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
