<?php
/**
 * Minimal PDF writer for reading documents.
 *
 * @package Hexxsy\Vendors
 */

namespace Hexxsy\Vendors;

/**
 * Builds a text-only, multi-page PDF using the base Helvetica fonts every
 * PDF reader has, so no font files or libraries are needed. Text is
 * converted to Windows-1252, which covers English and Western European
 * languages; anything outside it (emoji, symbols) is transliterated or
 * dropped.
 *
 * Has no WordPress dependencies, so it can be unit tested on its own.
 */
class Pdf {

	const PAGE_WIDTH  = 612; // US Letter, in points.
	const PAGE_HEIGHT = 792;
	const MARGIN      = 72;
	const FOOTER_Y    = 40;

	const REGULAR = 'F1';
	const BOLD    = 'F2';
	const ITALIC  = 'F3';

	/**
	 * Helvetica glyph widths (per 1000 units) for ASCII 32 to 126.
	 */
	const WIDTHS = array(
		278, 278, 355, 556, 556, 889, 667, 191, 333, 333, 389, 584, 278, 333, 278, 278,
		556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 278, 278, 584, 584, 584, 556,
		1015, 667, 667, 722, 722, 667, 611, 778, 722, 278, 500, 667, 556, 833, 722, 778,
		667, 778, 722, 667, 611, 722, 667, 944, 667, 667, 611, 278, 278, 278, 469, 556,
		333, 556, 556, 500, 556, 556, 278, 556, 556, 222, 222, 500, 222, 833, 556, 556,
		556, 556, 333, 500, 278, 556, 500, 722, 500, 500, 500, 334, 260, 334, 584,
	);

	/**
	 * Finished page content streams.
	 *
	 * @var string[]
	 */
	private array $pages = array();

	/**
	 * Content stream of the page being written.
	 */
	private string $current = '';

	/**
	 * Baseline of the next line, from the bottom of the page.
	 */
	private float $y = 0;

	/**
	 * Footer text printed on every page, with the page number.
	 */
	private string $footer;

	/**
	 * @param string $footer Footer text for every page (UTF-8).
	 */
	public function __construct( string $footer = '' ) {
		$this->footer = $footer;
		$this->new_page();
	}

	/**
	 * Add a paragraph, wrapped to the page width. Blank lines in the text
	 * start new paragraphs.
	 *
	 * @param string $text  UTF-8 text.
	 * @param float  $size  Font size in points.
	 * @param string $font  One of the font constants.
	 * @param float  $after Space after the paragraph, in points.
	 */
	public function paragraph( string $text, float $size = 11, string $font = self::REGULAR, float $after = 10 ): void {
		$leading = $size * 1.45;
		$text    = str_replace( array( "\r\n", "\r" ), "\n", $text );

		foreach ( preg_split( "/\n\s*\n/", trim( $text ) ) as $block ) {
			foreach ( explode( "\n", $block ) as $line ) {
				foreach ( $this->wrap( self::encode( $line ), $size, $font ) as $wrapped ) {
					$this->ensure_space( $leading );
					$this->y -= $leading;
					$this->current .= sprintf( "BT /%s %.1F Tf %.2F %.2F Td (%s) Tj ET\n", $font, $size, self::MARGIN, $this->y, self::escape( $wrapped ) );
				}
			}
			$this->y -= $after;
		}
	}

	/**
	 * Add vertical space.
	 *
	 * @param float $points Space in points.
	 */
	public function space( float $points ): void {
		$this->y -= $points;
	}

	/**
	 * Draw a thin horizontal rule across the text width.
	 */
	public function rule(): void {
		$this->ensure_space( 12 );
		$this->y -= 6;
		$this->current .= sprintf( "0.6 G 0.5 w %.2F %.2F m %.2F %.2F l S 0 G\n", self::MARGIN, $this->y, self::PAGE_WIDTH - self::MARGIN, $this->y );
		$this->y -= 12;
	}

	/**
	 * The finished PDF as a binary string.
	 */
	public function output(): string {
		$pages   = $this->pages;
		$pages[] = $this->current;
		$count   = count( $pages );

		// Objects 1-2: catalog and page tree. 3-5: fonts. Then a page and a
		// content stream for each page.
		$objects    = array();
		$kids       = array();
		$objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
		$objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
		$objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
		$objects[5] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Oblique /Encoding /WinAnsiEncoding >>';

		foreach ( $pages as $index => $content ) {
			$content .= $this->footer_stream( $index + 1, $count );
			$page_id  = 6 + $index * 2;
			$kids[]   = $page_id . ' 0 R';

			$objects[ $page_id ] = sprintf(
				'<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %d %d] /Resources << /Font << /F1 3 0 R /F2 4 0 R /F3 5 0 R >> >> /Contents %d 0 R >>',
				self::PAGE_WIDTH,
				self::PAGE_HEIGHT,
				$page_id + 1
			);
			$objects[ $page_id + 1 ] = '<< /Length ' . strlen( $content ) . " >>\nstream\n" . $content . "\nendstream";
		}
		$objects[2] = '<< /Type /Pages /Kids [' . implode( ' ', $kids ) . '] /Count ' . $count . ' >>';
		ksort( $objects );

		$pdf     = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
		$offsets = array();
		foreach ( $objects as $id => $body ) {
			$offsets[ $id ] = strlen( $pdf );
			$pdf           .= $id . " 0 obj\n" . $body . "\nendobj\n";
		}

		$xref = strlen( $pdf );
		$pdf .= 'xref' . "\n" . '0 ' . ( count( $objects ) + 1 ) . "\n" . "0000000000 65535 f \n";
		foreach ( $offsets as $offset ) {
			$pdf .= sprintf( "%010d 00000 n \n", $offset );
		}
		$pdf .= 'trailer' . "\n" . '<< /Size ' . ( count( $objects ) + 1 ) . ' /Root 1 0 R >>' . "\n" . 'startxref' . "\n" . $xref . "\n%%EOF\n";

		return $pdf;
	}

	/**
	 * Start a new page if fewer than $height points remain above the footer.
	 *
	 * @param float $height Space needed.
	 */
	private function ensure_space( float $height ): void {
		if ( $this->y - $height < self::FOOTER_Y + 30 ) {
			$this->pages[] = $this->current;
			$this->new_page();
		}
	}

	/**
	 * Reset to the top of a blank page.
	 */
	private function new_page(): void {
		$this->current = '';
		$this->y       = self::PAGE_HEIGHT - self::MARGIN;
	}

	/**
	 * Footer content for one page.
	 *
	 * @param int $number Page number.
	 * @param int $total  Page count.
	 */
	private function footer_stream( int $number, int $total ): string {
		$text = trim( $this->footer . '   ' . $number . ' / ' . $total );
		return sprintf( "0.4 g BT /%s 8 Tf %.2F %.2F Td (%s) Tj ET 0 g\n", self::REGULAR, self::MARGIN, self::FOOTER_Y, self::escape( self::encode( $text ) ) );
	}

	/**
	 * Break an encoded line into pieces that fit the text width.
	 *
	 * @param string $line Windows-1252 text.
	 * @param float  $size Font size.
	 * @param string $font Font constant.
	 * @return string[]
	 */
	private function wrap( string $line, float $size, string $font ): array {
		$max   = self::PAGE_WIDTH - 2 * self::MARGIN;
		$lines = array();
		$cur   = '';

		foreach ( preg_split( '/ +/', trim( $line ) ) as $word ) {
			$try = '' === $cur ? $word : $cur . ' ' . $word;
			if ( '' === $cur || self::width( $try, $size, $font ) <= $max ) {
				$cur = $try;
				continue;
			}
			$lines[] = $cur;
			$cur     = $word;
		}
		$lines[] = $cur;

		// Split any single word too long for a line.
		$out = array();
		foreach ( $lines as $piece ) {
			while ( strlen( $piece ) > 1 && self::width( $piece, $size, $font ) > $max ) {
				$cut = strlen( $piece ) - 1;
				while ( $cut > 1 && self::width( substr( $piece, 0, $cut ), $size, $font ) > $max ) {
					--$cut;
				}
				$out[] = substr( $piece, 0, $cut );
				$piece = substr( $piece, $cut );
			}
			$out[] = $piece;
		}
		return $out;
	}

	/**
	 * Width of encoded text in points. Bold is estimated 10% wider, which
	 * keeps wrapping on the safe side.
	 *
	 * @param string $text Windows-1252 text.
	 * @param float  $size Font size.
	 * @param string $font Font constant.
	 */
	public static function width( string $text, float $size, string $font = self::REGULAR ): float {
		$units = 0;
		$len   = strlen( $text );
		for ( $i = 0; $i < $len; $i++ ) {
			$code   = ord( $text[ $i ] );
			$units += ( $code >= 32 && $code <= 126 ) ? self::WIDTHS[ $code - 32 ] : 556;
		}
		$factor = self::BOLD === $font ? 1.1 : 1.0;
		return $units * $size / 1000 * $factor;
	}

	/**
	 * Convert UTF-8 to Windows-1252, transliterating what it can.
	 *
	 * @param string $text UTF-8 text.
	 */
	public static function encode( string $text ): string {
		$text = str_replace( "\t", '    ', $text );

		// Convert one character at a time so a character with no equivalent
		// is dropped, instead of iconv's "?" which reads like a typo.
		$out = preg_replace_callback(
			'/[^\x00-\x7F]/u',
			static function ( $match ) {
				$converted = @iconv( 'UTF-8', 'Windows-1252//TRANSLIT', $match[0] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
				return ( false === $converted || '?' === $converted ) ? '' : $converted;
			},
			$text
		);

		// Invalid UTF-8: keep the plain ASCII.
		return null === $out ? preg_replace( '/[^\x20-\x7E]/', '', $text ) : $out;
	}

	/**
	 * Escape a string for a PDF literal.
	 *
	 * @param string $text Encoded text.
	 */
	public static function escape( string $text ): string {
		return str_replace( array( '\\', '(', ')', "\r", "\n" ), array( '\\\\', '\\(', '\\)', '', '' ), $text );
	}
}
