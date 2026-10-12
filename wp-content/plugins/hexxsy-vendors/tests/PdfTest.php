<?php
/**
 * PDF writer tests. These run without WordPress.
 *
 * @package Hexxsy\Vendors
 */

use Hexxsy\Vendors\Pdf;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-pdf.php';

final class PdfTest extends TestCase {

	public function test_output_is_a_well_formed_pdf(): void {
		$pdf = new Pdf( 'Footer' );
		$pdf->paragraph( 'Hello' );
		$out = $pdf->output();

		$this->assertSame( '%PDF-1.4', substr( $out, 0, 8 ) );
		$this->assertSame( "%%EOF\n", substr( $out, -6 ) );

		// startxref must point at the xref table.
		preg_match( '/startxref\n(\d+)\n/', $out, $m );
		$this->assertSame( 'xref', substr( $out, (int) $m[1], 4 ) );
	}

	public function test_long_text_flows_onto_more_pages(): void {
		$pdf = new Pdf();
		$pdf->paragraph( str_repeat( 'The Star upright brings hope and renewal. ', 400 ) );
		preg_match( '/\/Count (\d+)/', $pdf->output(), $m );
		$this->assertSame( true, (int) $m[1] > 1 );
	}

	public function test_encode_keeps_western_accents_and_drops_unsupported_symbols(): void {
		$this->assertSame( "Zo\xEB caf\xE9 ", Pdf::encode( "Zoë café ☽" ) );
	}

	public function test_escape_protects_pdf_string_syntax(): void {
		$this->assertSame( 'a\\(b\\) c\\\\', Pdf::escape( 'a(b) c\\' ) );
	}
}
