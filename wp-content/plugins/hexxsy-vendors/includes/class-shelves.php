<?php
/**
 * Shelves: the four kinds of thing a room can stock.
 *
 * @package Hexxsy\Vendors
 */

namespace Hexxsy\Vendors;

/**
 * A shelf is a WooCommerce product category marked with a shelf key:
 * Sittings (live readings), Letters (written readings, delivered later),
 * Papers (digital goods) and Wares (physical goods). The four categories are
 * made once, and after that everything about them is edited on the category
 * itself in Products > Categories: its name, the small line beside the
 * heading, Hexx's lines, the button labels and the "How it goes" steps.
 *
 * Readings are filed automatically: live calls under Sittings, written
 * readings under Letters. Papers and Wares are ordinary products the admin
 * files by hand.
 */
class Shelves {

	const TAXONOMY  = 'product_cat';
	const KEY_META  = 'hexxsy_shelf';
	const INSTALLED = 'hexxsy_shelves_installed';

	const SITTINGS = 'sittings';
	const LETTERS  = 'letters';
	const PAPERS   = 'papers';
	const WARES    = 'wares';

	/**
	 * Editable fields on each shelf: key => [term meta key, label, help, rows].
	 *
	 * @return array<string,array{0:string,1:string,2:string,3:int}>
	 */
	public static function fields(): array {
		return array(
			'kind'   => array( 'hexxsy_shelf_kind', 'What it is', 'The small line beside the shelf\'s name in a room, and after it on an offering\'s page.', 1 ),
			'line'   => array( 'hexxsy_shelf_line', 'Hexx\'s line in a room', 'What Hexx says beside this shelf in every reader\'s room.', 2 ),
			'action' => array( 'hexxsy_shelf_action', 'Button on a room\'s cards', 'For example "Request".', 1 ),
			'buy'    => array( 'hexxsy_shelf_buy', 'Button on the offering\'s page', 'For example "Request a letter".', 1 ),
			'steps'  => array( 'hexxsy_shelf_steps', 'How it goes', 'The numbered steps on an offering\'s page, one per line.', 4 ),
			'aside'  => array( 'hexxsy_shelf_aside', 'Hexx\'s line on the offering\'s page', 'What Hexx says under the price box.', 2 ),
		);
	}

	/**
	 * The four shelves as first made, in the order rooms show them.
	 *
	 * @return array<string,array<string,string>>
	 */
	public static function defaults(): array {
		return array(
			self::SITTINGS => array(
				'name'   => 'Sittings',
				'kind'   => 'Live readings, by appointment',
				'line'   => 'Face to face, at a set hour. Don\'t be late, it throws everybody off.',
				'action' => 'Book',
				'buy'    => 'Choose a time',
				'steps'  => "Pick a time that suits you both, and pay to hold it.\nYou'll get the link or number by email, with anything the reader wants you to bring.\nShow up at the hour. The reading happens live.",
				'aside'  => 'Bring the real question, not the polite one.',
			),
			self::LETTERS  => array(
				'name'   => 'Letters',
				'kind'   => 'Written readings, sent later',
				'line'   => 'Sent on when it\'s ready. Good for people who think better alone.',
				'action' => 'Request',
				'buy'    => 'Request a letter',
				'steps'  => "Pay and send your question, with whatever the reader asks for.\nThe reader does the reading on their own time.\nIt arrives in your inbox, yours to keep and reread.",
				'aside'  => 'Ask one thing, and ask it well. You\'ll get a better answer.',
			),
			self::PAPERS   => array(
				'name'   => 'Papers',
				'kind'   => 'Digital goods',
				'line'   => 'Spreads, guides, things to print. Yours the moment you pay.',
				'action' => 'Take a copy',
				'buy'    => 'Take a copy',
				'steps'  => "Pay.\nDownload it straight away, and again later from your account.",
				'aside'  => 'Print it on decent paper. It matters more than you\'d think.',
			),
			self::WARES    => array(
				'name'   => 'Wares',
				'kind'   => 'Physical goods, shipped by the reader',
				'line'   => 'These leave from the reader\'s own hands, so allow some time.',
				'action' => 'Add to basket',
				'buy'    => 'Add to basket',
				'steps'  => "Order it here.\nThe reader packs it and sends it on themselves.\nIt arrives with tracking, if the reader offers it.",
				'aside'  => 'This comes from their hands, not a warehouse. You can tell.',
			),
		);
	}

	/**
	 * The shelf a reading format is filed under, or ''.
	 *
	 * @param string $format Reading format.
	 */
	public static function for_format( string $format ): string {
		if ( 'live' === $format ) {
			return self::SITTINGS;
		}
		return 'written' === $format ? self::LETTERS : '';
	}

	/**
	 * Lines of a multi-line field, trimmed, blanks dropped.
	 *
	 * @param string $text Field value.
	 * @return string[]
	 */
	public static function lines( string $text ): array {
		return array_values( array_filter( array_map( 'trim', preg_split( '/\R/', $text ) ), static fn( $line ) => '' !== $line ) );
	}

	/**
	 * Hook in.
	 */
	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'maybe_install' ), 20 );
		add_action( self::TAXONOMY . '_edit_form_fields', array( __CLASS__, 'term_fields' ) );
		add_action( 'edited_' . self::TAXONOMY, array( __CLASS__, 'save_term_fields' ) );
		add_action( 'woocommerce_before_product_object_save', array( __CLASS__, 'file' ) );
		add_filter( 'woocommerce_product_single_add_to_cart_text', array( __CLASS__, 'buy_text' ), 10, 2 );
	}

	/**
	 * Make the four shelf categories once, and file readings that exist.
	 */
	public static function maybe_install(): void {
		if ( get_option( self::INSTALLED ) || ! taxonomy_exists( self::TAXONOMY ) ) {
			return;
		}
		update_option( self::INSTALLED, 1 );

		foreach ( self::defaults() as $key => $shelf ) {
			if ( self::get( $key ) ) {
				continue;
			}
			$term = get_term_by( 'name', $shelf['name'], self::TAXONOMY );
			if ( $term ) {
				$term_id = (int) $term->term_id;
			} else {
				$made = wp_insert_term( $shelf['name'], self::TAXONOMY );
				if ( is_wp_error( $made ) ) {
					continue;
				}
				$term_id = (int) $made['term_id'];
			}
			update_term_meta( $term_id, self::KEY_META, $key );
			foreach ( self::fields() as $field => $def ) {
				update_term_meta( $term_id, $def[0], $shelf[ $field ] );
			}
		}
		self::$cache = null;

		$readings = wc_get_products(
			array(
				'limit'    => -1,
				'status'   => array( 'publish', 'draft', 'pending', 'private' ),
				'meta_key' => Readings::FORMAT_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			)
		);
		foreach ( $readings as $product ) {
			// Saving files it (see file()).
			$product->save();
		}
	}

	/**
	 * Cached shelves for this request.
	 *
	 * @var array<string,array<string,mixed>>|null
	 */
	private static $cache = null;

	/**
	 * Every shelf, in house order: key => [key, id, name, url, and each field].
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function all(): array {
		if ( null !== self::$cache ) {
			return self::$cache;
		}
		$terms = get_terms(
			array(
				'taxonomy'   => self::TAXONOMY,
				'hide_empty' => false,
				'meta_key'   => self::KEY_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			)
		);
		$found = array();
		foreach ( is_array( $terms ) ? $terms : array() as $term ) {
			$key = (string) get_term_meta( $term->term_id, self::KEY_META, true );
			if ( ! isset( self::defaults()[ $key ] ) || isset( $found[ $key ] ) ) {
				continue;
			}
			$shelf = array(
				'key'  => $key,
				'id'   => (int) $term->term_id,
				'name' => $term->name,
			);
			foreach ( self::fields() as $field => $def ) {
				$shelf[ $field ] = (string) get_term_meta( $term->term_id, $def[0], true );
			}
			$found[ $key ] = $shelf;
		}
		// House order.
		self::$cache = array();
		foreach ( array_keys( self::defaults() ) as $key ) {
			if ( isset( $found[ $key ] ) ) {
				self::$cache[ $key ] = $found[ $key ];
			}
		}
		return self::$cache;
	}

	/**
	 * One shelf by key, or null.
	 *
	 * @param string $key Shelf key.
	 * @return array<string,mixed>|null
	 */
	public static function get( string $key ): ?array {
		return self::all()[ $key ] ?? null;
	}

	/**
	 * The shelf a product sits on, or null.
	 *
	 * @param \WC_Product $product Product.
	 * @return array<string,mixed>|null
	 */
	public static function of( \WC_Product $product ): ?array {
		$ids = $product->get_category_ids();
		foreach ( self::all() as $shelf ) {
			if ( in_array( $shelf['id'], $ids, true ) ) {
				return $shelf;
			}
		}
		return null;
	}

	/**
	 * File a reading on its shelf whenever it's saved: live calls under
	 * Sittings, written readings under Letters. Other products are left as
	 * the admin filed them.
	 *
	 * @param \WC_Product $product Product about to be saved.
	 */
	public static function file( $product ): void {
		$want = self::get( self::for_format( Readings::format( $product ) ) );
		if ( ! $want ) {
			return;
		}
		$reading_shelves = array();
		foreach ( array( self::SITTINGS, self::LETTERS ) as $key ) {
			$shelf = self::get( $key );
			if ( $shelf ) {
				$reading_shelves[] = $shelf['id'];
			}
		}
		$ids   = array_diff( $product->get_category_ids(), $reading_shelves );
		$ids[] = $want['id'];
		$product->set_category_ids( array_values( array_unique( $ids ) ) );
	}

	/**
	 * The button on an offering's page says what its shelf says.
	 *
	 * @param string      $text    Button text.
	 * @param \WC_Product $product Product.
	 */
	public static function buy_text( $text, $product ) {
		$shelf = $product instanceof \WC_Product ? self::of( $product ) : null;
		return $shelf && '' !== $shelf['buy'] ? $shelf['buy'] : $text;
	}

	/**
	 * Shelf fields on the category's edit screen.
	 *
	 * @param \WP_Term $term Category.
	 */
	public static function term_fields( $term ): void {
		$key = (string) get_term_meta( $term->term_id, self::KEY_META, true );
		if ( ! isset( self::defaults()[ $key ] ) ) {
			return;
		}
		wp_nonce_field( 'hexxsy_shelf_' . $term->term_id, '_hexxsy_shelf_nonce' );
		?>
		<tr class="form-field">
			<th scope="row"><?php esc_html_e( 'Hexxsy shelf', 'hexxsy-vendors' ); ?></th>
			<td><p class="description"><?php esc_html_e( 'This category is one of the four shelves in every reader\'s room. Its name above is the shelf\'s name. The fields below are the words that go with it.', 'hexxsy-vendors' ); ?></p></td>
		</tr>
		<?php foreach ( self::fields() as $field => $def ) : ?>
			<?php $value = (string) get_term_meta( $term->term_id, $def[0], true ); ?>
			<tr class="form-field">
				<th scope="row"><label for="<?php echo esc_attr( $def[0] ); ?>"><?php echo esc_html( $def[1] ); ?></label></th>
				<td>
					<?php if ( $def[3] > 1 ) : ?>
						<textarea id="<?php echo esc_attr( $def[0] ); ?>" name="<?php echo esc_attr( $def[0] ); ?>" rows="<?php echo (int) $def[3]; ?>"><?php echo esc_textarea( $value ); ?></textarea>
					<?php else : ?>
						<input type="text" id="<?php echo esc_attr( $def[0] ); ?>" name="<?php echo esc_attr( $def[0] ); ?>" value="<?php echo esc_attr( $value ); ?>">
					<?php endif; ?>
					<p class="description"><?php echo esc_html( $def[2] ); ?></p>
				</td>
			</tr>
		<?php endforeach; ?>
		<?php
	}

	/**
	 * Save the shelf fields.
	 *
	 * @param int $term_id Category.
	 */
	public static function save_term_fields( $term_id ): void {
		if ( ! isset( $_POST['_hexxsy_shelf_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_hexxsy_shelf_nonce'] ), 'hexxsy_shelf_' . $term_id ) || ! current_user_can( 'manage_product_terms' ) ) {
			return;
		}
		foreach ( self::fields() as $def ) {
			if ( isset( $_POST[ $def[0] ] ) ) {
				$raw = wp_unslash( $_POST[ $def[0] ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized on the next line.
				update_term_meta( $term_id, $def[0], $def[3] > 1 ? sanitize_textarea_field( $raw ) : sanitize_text_field( $raw ) );
			}
		}
	}
}
