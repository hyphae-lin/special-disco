<?php
/**
 * Room addresses, shelves and offering steps. These run without WordPress.
 *
 * @package Hexxsy\Vendors
 */

use Hexxsy\Vendors\Offering;
use Hexxsy\Vendors\Rooms;
use Hexxsy\Vendors\Shelves;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-rooms.php';
require_once dirname( __DIR__ ) . '/includes/class-shelves.php';
require_once dirname( __DIR__ ) . '/includes/class-offering.php';

final class RoomsShelvesTest extends TestCase {

	public function test_room_address_is_the_shop_name(): void {
		$this->assertSame( 'moth-candle', Rooms::slug_for( 'moth-candle', 5, array( 3 => 'ash-ember' ) ) );
	}

	public function test_second_shop_with_the_same_name_gets_its_id(): void {
		$this->assertSame( 'moth-candle-9', Rooms::slug_for( 'moth-candle', 9, array( 5 => 'moth-candle' ) ) );
	}

	public function test_shop_with_no_usable_name_gets_a_room_number_address(): void {
		$this->assertSame( 'room-7', Rooms::slug_for( '', 7, array() ) );
	}

	public function test_readings_file_under_their_shelf(): void {
		$this->assertSame( Shelves::LETTERS, Shelves::for_format( 'written' ) );
		$this->assertSame( Shelves::SITTINGS, Shelves::for_format( 'live' ) );
		$this->assertSame( '', Shelves::for_format( '' ) );
	}

	public function test_every_shelf_starts_with_every_field(): void {
		$this->assertSame( array( 'sittings', 'letters', 'papers', 'wares' ), array_keys( Shelves::defaults() ) );
		foreach ( Shelves::defaults() as $key => $shelf ) {
			$this->assertNotSame( '', $shelf['name'], $key );
			foreach ( array_keys( Shelves::fields() ) as $field ) {
				$this->assertNotSame( '', $shelf[ $field ] ?? '', "$key $field" );
			}
		}
	}

	public function test_steps_are_one_per_line_with_blanks_dropped(): void {
		$this->assertSame( array( 'Pay.', 'Download it.' ), Shelves::lines( "Pay.\r\n\n  Download it.  \n" ) );
		$this->assertSame( array(), Shelves::lines( '' ) );
	}

	public function test_steps_are_numbered_in_roman(): void {
		$this->assertSame(
			array( 'I' => 'a', 'II' => 'b', 'III' => 'c', 'IV' => 'd' ),
			Offering::numbered( array( 'a', 'b', 'c', 'd' ) )
		);
	}
}
