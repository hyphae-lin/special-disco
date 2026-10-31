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
		$groups = array( 'rooms', 'ledger', 'apply' );
		foreach ( Lines::defaults() as $key => $line ) {
			$this->assertContains( $line[0], $groups, $key );
			$this->assertNotSame( '', $line[1], $key );
			$this->assertNotSame( '', $line[2], $key );
		}
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
