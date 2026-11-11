<?php
/**
 * Readers' rooms: the room list on the homepage, and each reader's own room.
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
 * [hexxsy_room] is one reader's room: portrait, introduction, Hexx's note,
 * and a shelf for each kind of thing they have on sale. It goes on the page
 * chosen as the Room page on WooCommerce > Vendors, and each room's address
 * is that page's address plus the reader's shop name, e.g. /room/moth-candle/.
 *
 * With no Room page chosen, Knock opens the shop filtered to that reader
 * (?reader=ID), as before.
 */
class Rooms {

	const EMPTY_OPTION   = 'hexxsy_empty_rooms';
	const HIDDEN         = 'hidden';
	const UNLIT          = 'unlit';
	const DOOR_META      = 'hexxsy_door_line';
	const PORTRAIT_META  = 'hexxsy_portrait_id';
	const READER_ARG     = 'reader';
	const PAGE_OPTION    = 'hexxsy_room_page';
	const QUERY_VAR      = 'hexxsy_room';
	const NOTE_META      = 'hexxsy_hexx_note';
	const HOW_META       = 'hexxsy_how_i_read';
	const SHELF_NOTE     = 'hexxsy_shelf_note_';

	/**
	 * Hook in.
	 */
	public static function init(): void {
		add_shortcode( 'hexxsy_rooms', array( __CLASS__, 'render' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'filter_shop' ) );
		add_filter( 'get_the_archive_title', array( __CLASS__, 'shop_title' ) );
		add_filter( 'woocommerce_page_title', array( __CLASS__, 'shop_title' ) );

		add_shortcode( 'hexxsy_room', array( __CLASS__, 'render_room' ) );
		add_action( 'init', array( __CLASS__, 'rewrite' ) );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_filter( 'redirect_canonical', array( __CLASS__, 'keep_room_address' ) );
		add_action( 'template_redirect', array( __CLASS__, 'missing_room' ) );
		add_filter( 'document_title_parts', array( __CLASS__, 'document_title' ) );
		// A new Room page needs new rewrite rules.
		add_action( 'update_option_' . self::PAGE_OPTION, array( __CLASS__, 'flag_flush' ) );
		add_action( 'add_option_' . self::PAGE_OPTION, array( __CLASS__, 'flag_flush' ) );
	}

	/**
	 * The address word for a room: the shop name, made URL-safe. Falls back
	 * to room-ID, and adds the ID when another reader already has the word.
	 *
	 * @param string   $slug  The shop name as a URL slug (from sanitize_title), or ''.
	 * @param int      $id    Reader's user ID.
	 * @param string[] $taken Slugs of readers who joined earlier.
	 */
	public static function slug_for( string $slug, int $id, array $taken ): string {
		if ( '' === $slug ) {
			return 'room-' . $id;
		}
		return in_array( $slug, $taken, true ) ? $slug . '-' . $id : $slug;
	}

	/**
	 * Every reader's address word, in room order: user ID => slug.
	 *
	 * @return array<int,string>
	 */
	public static function slugs(): array {
		static $slugs = null;
		if ( null === $slugs ) {
			$slugs = array();
			foreach ( self::readers() as $reader ) {
				$slugs[ $reader->ID ] = self::slug_for( sanitize_title( (string) get_user_meta( $reader->ID, 'hexxsy_shop_name', true ) ), $reader->ID, $slugs );
			}
		}
		return $slugs;
	}

	/**
	 * A reader's room number, or 0 if they have no room.
	 *
	 * @param int $user_id Reader.
	 */
	public static function number( int $user_id ): int {
		$position = array_search( $user_id, array_keys( self::slugs() ), true );
		return false === $position ? 0 : $position + 1;
	}

	/**
	 * A reader's shop name, falling back to their display name.
	 *
	 * @param \WP_User $reader Reader.
	 */
	public static function name( \WP_User $reader ): string {
		$name = (string) get_user_meta( $reader->ID, 'hexxsy_shop_name', true );
		return '' !== $name ? $name : $reader->display_name;
	}

	/**
	 * Route /room-page/shop-name/ to the Room page.
	 */
	public static function rewrite(): void {
		$page = (int) get_option( self::PAGE_OPTION, 0 );
		$path = $page ? get_page_uri( $page ) : '';
		if ( $path ) {
			add_rewrite_rule( '^' . preg_quote( $path, '#' ) . '/([^/]+)/?$', 'index.php?page_id=' . $page . '&' . self::QUERY_VAR . '=$matches[1]', 'top' );
		}
	}

	/**
	 * Register the room query var.
	 *
	 * @param string[] $vars Query vars.
	 */
	public static function query_vars( $vars ) {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	/**
	 * Ask for fresh rewrite rules on the next page load.
	 */
	public static function flag_flush(): void {
		update_option( Dashboard::FLUSH_FLAG, 1 );
	}

	/**
	 * Stop WordPress redirecting a room's address back to the bare Room page.
	 *
	 * @param string $url Redirect target.
	 */
	public static function keep_room_address( $url ) {
		return get_query_var( self::QUERY_VAR ) ? false : $url;
	}

	/**
	 * The reader whose room is being asked for, or null.
	 */
	public static function current_reader(): ?\WP_User {
		$slug = (string) get_query_var( self::QUERY_VAR );
		if ( '' !== $slug ) {
			$id = array_search( sanitize_title( $slug ), self::slugs(), true );
			return false === $id ? null : get_userdata( (int) $id );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a public page.
		$id = isset( $_GET[ self::READER_ARG ] ) ? absint( $_GET[ self::READER_ARG ] ) : 0;
		return $id && isset( self::slugs()[ $id ] ) ? get_userdata( $id ) : null;
	}

	/**
	 * A room address nobody keeps is a 404, not an empty page.
	 */
	public static function missing_room(): void {
		if ( '' !== (string) get_query_var( self::QUERY_VAR ) && ! self::current_reader() ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			nocache_headers();
		}
	}

	/**
	 * Title the browser tab with the reader's shop name.
	 *
	 * @param array $parts Title parts.
	 */
	public static function document_title( $parts ) {
		$page = (int) get_option( self::PAGE_OPTION, 0 );
		if ( $page && is_page( $page ) ) {
			$reader = self::current_reader();
			if ( $reader ) {
				$parts['title'] = self::name( $reader );
			}
		}
		return $parts;
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
	 * A reader's stocked shelves, in house order, each with the products on
	 * it. A shelf only appears when it holds something in stock.
	 *
	 * @param \WC_Product[] $products Products.
	 * @return array<string,array<string,mixed>> Shelf key => shelf, plus 'items'.
	 */
	private static function stocked( array $products ): array {
		$stocked = array();
		foreach ( Shelves::all() as $key => $shelf ) {
			$items = array_values( array_filter( $products, static fn( $product ) => $product->is_in_stock() && in_array( $shelf['id'], $product->get_category_ids(), true ) ) );
			if ( $items ) {
				$stocked[ $key ] = $shelf + array( 'items' => $items );
			}
		}
		return $stocked;
	}

	/**
	 * Names of the shelves a reader stocks.
	 *
	 * @param \WC_Product[] $products Products.
	 * @return string[]
	 */
	private static function shelves( array $products ): array {
		return array_values( wp_list_pluck( self::stocked( $products ), 'name' ) );
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
		$page = Ledger::page_url( self::PAGE_OPTION );
		$slug = self::slugs()[ $user_id ] ?? '';
		if ( '' !== $page && '' !== $slug ) {
			return get_option( 'permalink_structure' )
				? trailingslashit( $page ) . $slug . '/'
				: add_query_arg( self::QUERY_VAR, $slug, $page );
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

	/**
	 * The small line under a product's name on a shelf.
	 *
	 * @param \WC_Product $product Product.
	 */
	public static function detail( \WC_Product $product ): string {
		$format = Readings::format( $product );
		if ( Readings::WRITTEN === $format ) {
			$days = (int) $product->get_meta( Readings::TURNAROUND_META );
			return 1 === $days ? Lines::text( 'room_detail_written_one' ) : Lines::text( 'room_detail_written', array( 'days' => (string) $days ) );
		}
		if ( Readings::LIVE === $format ) {
			return Lines::text( 'room_detail_live', array( 'minutes' => (string) (int) $product->get_meta( Readings::DURATION_META ) ) );
		}
		return wp_strip_all_tags( $product->get_short_description() );
	}

	/**
	 * [hexxsy_room]: one reader's room.
	 */
	public static function render_room(): string {
		$reader = self::current_reader();
		if ( ! $reader ) {
			// The bare Room page, with nobody named.
			return '<p class="hexxsy-line"><a href="' . esc_url( home_url( '/#rooms' ) ) . '">' . esc_html( Lines::text( 'room_back' ) ) . '</a></p>';
		}

		$name        = self::name( $reader );
		$specialties = (string) get_user_meta( $reader->ID, 'hexxsy_specialties', true );
		$bio         = (string) get_user_meta( $reader->ID, 'hexxsy_bio', true );
		$how         = (string) get_user_meta( $reader->ID, self::HOW_META, true );
		$note        = (string) get_user_meta( $reader->ID, self::NOTE_META, true );
		$portrait    = (int) get_user_meta( $reader->ID, self::PORTRAIT_META, true );
		$shelves     = self::stocked( self::products( $reader->ID ) );

		ob_start();
		?>
		<div class="hexxsy-room-page">
			<p class="hexxsy-room-back"><a href="<?php echo esc_url( home_url( '/#rooms' ) ); ?>"><?php echo esc_html( Lines::text( 'room_back' ) ); ?></a></p>

			<section class="hexxsy-room-intro">
				<div class="hexxsy-room-figure">
					<?php if ( $portrait && wp_attachment_is_image( $portrait ) ) : ?>
						<?php
						/* translators: %s: reader's shop name */
						echo wp_get_attachment_image( $portrait, 'large', false, array( 'alt' => sprintf( __( 'Portrait of %s', 'hexxsy-vendors' ), $name ) ) );
						?>
					<?php else : ?>
						<span class="hexxsy-room-initial" aria-hidden="true"><?php echo esc_html( mb_substr( $name, 0, 1 ) ); ?></span>
					<?php endif; ?>
				</div>
				<div class="hexxsy-room-about">
					<p class="hexxsy-room-number"><?php echo esc_html( Lines::text( 'rooms_room' ) . ' ' . self::numeral( self::number( $reader->ID ) ) ); ?></p>
					<h1 class="hexxsy-room-title"><?php echo esc_html( $name ); ?></h1>
					<?php if ( '' !== $specialties ) : ?>
						<p class="hexxsy-room-specialties"><?php echo esc_html( $specialties ); ?></p>
					<?php endif; ?>
					<?php if ( '' !== $bio ) : ?>
						<div class="hexxsy-room-bio"><?php echo wp_kses_post( wpautop( esc_html( $bio ) ) ); ?></div>
					<?php endif; ?>
					<?php if ( '' !== $how ) : ?>
						<div class="hexxsy-room-how">
							<h2 class="hexxsy-small-label"><?php echo esc_html( Lines::text( 'room_how' ) ); ?></h2>
							<?php echo wp_kses_post( wpautop( esc_html( $how ) ) ); ?>
						</div>
					<?php endif; ?>
					<?php if ( '' !== $note ) : ?>
						<div class="hexxsy-room-note">
							<p class="hexxsy-small-label"><?php echo esc_html( Lines::text( 'room_hexx_note' ) ); ?></p>
							<p class="hexxsy-room-note-text"><?php echo esc_html( $note ); ?></p>
						</div>
					<?php endif; ?>
					<?php if ( $shelves ) : ?>
						<nav class="hexxsy-shelf-nav" aria-label="<?php echo esc_attr( Lines::text( 'room_nav_label' ) ); ?>">
							<?php foreach ( $shelves as $key => $shelf ) : ?>
								<a href="#<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $shelf['name'] ); ?></a>
							<?php endforeach; ?>
						</nav>
					<?php endif; ?>
				</div>
			</section>

			<div class="hexxsy-shelves">
				<?php if ( ! $shelves ) : ?>
					<p class="hexxsy-rooms-note"><?php echo esc_html( Lines::text( 'room_empty' ) ); ?></p>
				<?php endif; ?>
				<?php foreach ( $shelves as $key => $shelf ) : ?>
					<?php $reader_note = (string) get_user_meta( $reader->ID, self::SHELF_NOTE . $key, true ); ?>
					<section class="hexxsy-shelf" id="<?php echo esc_attr( $key ); ?>">
						<div class="hexxsy-shelf-head">
							<div class="hexxsy-shelf-name">
								<h2><?php echo esc_html( $shelf['name'] ); ?></h2>
								<?php if ( '' !== $shelf['kind'] ) : ?>
									<span><?php echo esc_html( $shelf['kind'] ); ?></span>
								<?php endif; ?>
							</div>
							<?php if ( '' !== $shelf['line'] ) : ?>
								<p class="hexxsy-shelf-line"><?php echo esc_html( $shelf['line'] ); ?></p>
							<?php endif; ?>
						</div>
						<?php if ( '' !== $reader_note ) : ?>
							<p class="hexxsy-shelf-note"><?php echo esc_html( $reader_note ); ?></p>
						<?php endif; ?>
						<div class="hexxsy-shelf-grid">
							<?php foreach ( $shelf['items'] as $product ) : ?>
								<?php
								$link   = $product->get_permalink();
								$detail = self::detail( $product );
								// Readings need their questions answered first, so the button opens the page.
								$action = '' !== Readings::format( $product ) ? $link : $product->add_to_cart_url();
								?>
								<article class="hexxsy-offer-card">
									<h3><a href="<?php echo esc_url( $link ); ?>"><?php echo esc_html( $product->get_name() ); ?></a></h3>
									<?php if ( '' !== $detail ) : ?>
										<p class="hexxsy-offer-detail"><?php echo esc_html( $detail ); ?></p>
									<?php endif; ?>
									<a class="hexxsy-offer-more" href="<?php echo esc_url( $link ); ?>" aria-label="<?php echo esc_attr( Lines::text( 'room_more' ) . ': ' . $product->get_name() ); ?>"><?php echo esc_html( Lines::text( 'room_more' ) ); ?></a>
									<div class="hexxsy-offer-foot">
										<span class="hexxsy-offer-price"><?php echo wp_kses_post( $product->get_price_html() ); ?></span>
										<a class="hexxsy-offer-action" href="<?php echo esc_url( $action ); ?>" aria-label="<?php echo esc_attr( $shelf['action'] . ': ' . $product->get_name() ); ?>"><?php echo esc_html( '' !== $shelf['action'] ? $shelf['action'] : Lines::text( 'room_more' ) ); ?></a>
									</div>
								</article>
							<?php endforeach; ?>
						</div>
					</section>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}
}
