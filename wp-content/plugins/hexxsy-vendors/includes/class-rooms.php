<?php
/**
 * Readers' rooms: the room list on the homepage.
 *
 * @package Hexxsy\Vendors
 */

namespace Hexxsy\Vendors;

/**
 * [hexxsy_rooms] lists approved readers as numbered rooms: portrait, shop
 * name, specialties, door line, the shelves they stock and their lowest
 * price, with a Knock button.
 *
 * Empty rooms (Reader seats less approved readers) are either hidden, with
 * Hexx's empty-rooms line and a link to the Ledger below the list, or shown
 * as unlit rooms. The choice is on WooCommerce > Vendors.
 *
 * Until reader rooms have their own pages, Knock opens the shop filtered to
 * that reader (?reader=ID).
 */
class Rooms {

	const EMPTY_OPTION   = 'hexxsy_empty_rooms';
	const HIDDEN         = 'hidden';
	const UNLIT          = 'unlit';
	const DOOR_META      = 'hexxsy_door_line';
	const PORTRAIT_META  = 'hexxsy_portrait_id';
	const READER_ARG     = 'reader';

	/**
	 * Hook in.
	 */
	public static function init(): void {
		add_shortcode( 'hexxsy_rooms', array( __CLASS__, 'render' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'filter_shop' ) );
		add_filter( 'get_the_archive_title', array( __CLASS__, 'shop_title' ) );
		add_filter( 'woocommerce_page_title', array( __CLASS__, 'shop_title' ) );
	}

	/**
	 * Roman numeral for a room number.
	 *
	 * @param int $n 1 or more.
	 */
	public static function numeral( int $n ): string {
		if ( $n < 1 ) {
			return '';
		}
		$map = array(
			'M'  => 1000,
			'CM' => 900,
			'D'  => 500,
			'CD' => 400,
			'C'  => 100,
			'XC' => 90,
			'L'  => 50,
			'XL' => 40,
			'X'  => 10,
			'IX' => 9,
			'V'  => 5,
			'IV' => 4,
			'I'  => 1,
		);
		$out = '';
		foreach ( $map as $roman => $value ) {
			while ( $n >= $value ) {
				$out .= $roman;
				$n   -= $value;
			}
		}
		return $out;
	}

	/**
	 * Empty-rooms setting.
	 */
	public static function empty_mode(): string {
		return self::UNLIT === get_option( self::EMPTY_OPTION, self::HIDDEN ) ? self::UNLIT : self::HIDDEN;
	}

	/**
	 * Approved readers, in the order they joined.
	 *
	 * @return \WP_User[]
	 */
	public static function readers(): array {
		$users = get_users(
			array(
				'meta_key'   => Vendor::STATUS_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value' => Vendor::APPROVED, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'orderby'    => 'registered',
				'order'      => 'ASC',
			)
		);
		$users = array_values( array_filter( $users, static fn( $u ) => Vendor::is_approved( $u->ID ) ) );
		usort(
			$users,
			static fn( $a, $b ) => (int) get_user_meta( $a->ID, 'hexxsy_applied_at', true ) <=> (int) get_user_meta( $b->ID, 'hexxsy_applied_at', true )
		);
		return $users;
	}

	/**
	 * A reader's published products.
	 *
	 * @param int $user_id Reader.
	 * @return \WC_Product[]
	 */
	private static function products( int $user_id ): array {
		$products = wc_get_products(
			array(
				'status' => 'publish',
				'author' => $user_id,
				'limit'  => 100,
			)
		);
		// Live calls while they're off sale don't count toward shelves or prices.
		return array_values( array_filter( $products, static fn( $product ) => '' === Readings::off_sale( $product ) ) );
	}

	/**
	 * Shelves a reader stocks: the names of their products' categories, in
	 * the order the categories are sorted in WooCommerce.
	 *
	 * @param \WC_Product[] $products Products.
	 * @return string[]
	 */
	private static function shelves( array $products ): array {
		$ids = array();
		foreach ( $products as $product ) {
			if ( $product->is_in_stock() ) {
				foreach ( $product->get_category_ids() as $id ) {
					$ids[ $id ] = true;
				}
			}
		}
		if ( ! $ids ) {
			return array();
		}
		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'include'    => array_keys( $ids ),
				'hide_empty' => false,
				'orderby'    => 'term_order',
			)
		);
		return is_array( $terms ) ? wp_list_pluck( $terms, 'name' ) : array();
	}

	/**
	 * Lowest price across products, or null.
	 *
	 * @param \WC_Product[] $products Products.
	 */
	private static function lowest_price( array $products ): ?float {
		$prices = array();
		foreach ( $products as $product ) {
			$price = $product->get_price();
			if ( '' !== $price && null !== $price ) {
				$prices[] = (float) $price;
			}
		}
		return $prices ? min( $prices ) : null;
	}

	/**
	 * Where Knock goes.
	 *
	 * @param int $user_id Reader.
	 */
	public static function room_url( int $user_id ): string {
		$url = apply_filters( 'hexxsy_room_url', '', $user_id );
		if ( '' !== $url ) {
			return (string) $url;
		}
		return add_query_arg( self::READER_ARG, $user_id, (string) wc_get_page_permalink( 'shop' ) );
	}

	/**
	 * Show only one reader's products on the shop when ?reader= is set.
	 *
	 * @param \WP_Query $query Query.
	 */
	public static function filter_shop( $query ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a public filter.
		if ( is_admin() || ! $query->is_main_query() || empty( $_GET[ self::READER_ARG ] ) ) {
			return;
		}
		if ( ! ( $query->is_post_type_archive( 'product' ) || $query->is_tax( get_object_taxonomies( 'product' ) ) ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$reader = absint( $_GET[ self::READER_ARG ] );
		if ( $reader && Vendor::is_approved( $reader ) ) {
			$query->set( 'author', $reader );
		}
	}

	/**
	 * Title the filtered shop with the reader's shop name.
	 *
	 * @param string $title Title.
	 */
	public static function shop_title( $title ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( empty( $_GET[ self::READER_ARG ] ) || ! function_exists( 'is_shop' ) || ! is_shop() ) {
			return $title;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$reader = absint( $_GET[ self::READER_ARG ] );
		$name   = $reader && Vendor::is_approved( $reader ) ? (string) get_user_meta( $reader, 'hexxsy_shop_name', true ) : '';
		return '' !== $name ? $name : $title;
	}

	/**
	 * The room list.
	 */
	public static function render(): string {
		$readers = self::readers();
		$seats   = Ledger::seats();
		$empty   = max( 0, $seats - count( $readers ) );
		$mode    = self::empty_mode();
		$ledger  = Ledger::page_url( Ledger::LEDGER_PAGE_OPTION );

		ob_start();
		?>
		<div class="hexxsy-rooms">
			<?php if ( ! $readers && self::HIDDEN === $mode ) : ?>
				<p class="hexxsy-rooms-note"><?php echo Lines::html( 'rooms_none' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in Lines. ?></p>
			<?php endif; ?>
			<div class="hexxsy-rooms-grid">
				<?php foreach ( $readers as $i => $reader ) : ?>
					<?php echo self::card( $reader, $i + 1 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in card(). ?>
				<?php endforeach; ?>
				<?php if ( self::UNLIT === $mode ) : ?>
					<?php for ( $n = count( $readers ) + 1; $n <= $seats; $n++ ) : ?>
						<article class="hexxsy-room is-unlit" aria-label="<?php echo esc_attr( Lines::text( 'rooms_room' ) . ' ' . self::numeral( $n ) . ', ' . Lines::text( 'rooms_unlit' ) ); ?>">
							<div class="hexxsy-room-head"><span><?php echo esc_html( Lines::text( 'rooms_room' ) ); ?></span><span class="hexxsy-room-numeral"><?php echo esc_html( self::numeral( $n ) ); ?></span></div>
							<div class="hexxsy-room-unlit">
								<svg aria-hidden="true" viewBox="0 0 40 56" width="34" height="48" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M14 18 L26 18 L30 10 L10 10 Z"></path><path d="M12 10 L20 2 L28 10"></path><path d="M20 18 L20 54"></path><path d="M14 54 L26 54"></path></svg>
								<p class="hexxsy-room-unlit-label"><?php echo esc_html( Lines::text( 'rooms_unlit' ) ); ?></p>
								<p class="hexxsy-room-unlit-line"><?php echo esc_html( Lines::text( 'rooms_unlit_line' ) ); ?></p>
							</div>
						</article>
					<?php endfor; ?>
				<?php endif; ?>
			</div>
			<?php if ( self::HIDDEN === $mode && $empty > 0 ) : ?>
				<p class="hexxsy-rooms-note"><?php echo esc_html( 1 === $empty ? Lines::text( 'rooms_empty_one' ) : Lines::text( 'rooms_empty_many', array( 'count' => (string) $empty ) ) ); ?></p>
				<p class="hexxsy-rooms-ledger"><?php echo Lines::html( 'rooms_ledger', array(), $ledger ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in Lines. ?></p>
			<?php endif; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * One reader's card.
	 *
	 * @param \WP_User $reader Reader.
	 * @param int      $n      Room number.
	 */
	private static function card( \WP_User $reader, int $n ): string {
		$name        = (string) get_user_meta( $reader->ID, 'hexxsy_shop_name', true );
		$name        = '' !== $name ? $name : $reader->display_name;
		$specialties = (string) get_user_meta( $reader->ID, 'hexxsy_specialties', true );
		$door        = (string) get_user_meta( $reader->ID, self::DOOR_META, true );
		$portrait    = (int) get_user_meta( $reader->ID, self::PORTRAIT_META, true );
		$products    = self::products( $reader->ID );
		$shelves     = self::shelves( $products );
		$price       = self::lowest_price( $products );
		$url         = self::room_url( $reader->ID );

		ob_start();
		?>
		<article class="hexxsy-room">
			<div class="hexxsy-room-head"><span><?php echo esc_html( Lines::text( 'rooms_room' ) ); ?></span><span class="hexxsy-room-numeral"><?php echo esc_html( self::numeral( $n ) ); ?></span></div>
			<a class="hexxsy-room-portrait" href="<?php echo esc_url( $url ); ?>" tabindex="-1" aria-hidden="true">
				<?php if ( $portrait && wp_attachment_is_image( $portrait ) ) : ?>
					<?php echo wp_get_attachment_image( $portrait, 'medium_large', false, array( 'alt' => '' ) ); ?>
				<?php else : ?>
					<span class="hexxsy-room-initial"><?php echo esc_html( mb_substr( $name, 0, 1 ) ); ?></span>
				<?php endif; ?>
			</a>
			<div class="hexxsy-room-body">
				<h3 class="hexxsy-room-name"><?php echo esc_html( $name ); ?></h3>
				<?php if ( '' !== $specialties ) : ?>
					<p class="hexxsy-room-specialties"><?php echo esc_html( $specialties ); ?></p>
				<?php endif; ?>
				<?php if ( '' !== $door ) : ?>
					<p class="hexxsy-room-door"><?php echo esc_html( $door ); ?></p>
				<?php endif; ?>
				<?php if ( $shelves ) : ?>
					<p class="hexxsy-room-shelves"><?php echo esc_html( implode( ' · ', $shelves ) ); ?></p>
				<?php endif; ?>
				<div class="hexxsy-room-foot">
					<span class="hexxsy-room-price">
						<?php
						if ( null !== $price ) {
							echo esc_html( Lines::text( 'rooms_from', array( 'price' => wp_strip_all_tags( wc_price( $price ) ) ) ) );
						}
						?>
					</span>
					<a class="hexxsy-room-knock" href="<?php echo esc_url( $url ); ?>" aria-label="<?php echo esc_attr( Lines::text( 'rooms_knock' ) . ': ' . $name ); ?>"><?php echo esc_html( Lines::text( 'rooms_knock' ) ); ?></a>
				</div>
			</div>
		</article>
		<?php
		return (string) ob_get_clean();
	}
}
