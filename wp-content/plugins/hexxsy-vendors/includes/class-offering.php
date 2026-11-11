<?php
/**
 * The offering page: one thing a reader sells.
 *
 * @package Hexxsy\Vendors
 */

namespace Hexxsy\Vendors;

/**
 * [hexxsy_offering] lays out a product's page the house way: where it sits
 * (the rooms, the reader's room, the shelf), its name and reader, the
 * reader's own description, "Before you buy", "How it goes", and a price box
 * with the facts, the questions and the button. Hexx has a line under the box.
 *
 * The theme's single product template holds the shortcode, so the header and
 * footer stay the theme's and WooCommerce's own add-to-cart form (with the
 * intake questions and the policy note hooked into it) does the selling.
 */
class Offering {

	const BEFORE_META = '_hexxsy_before_you_buy';

	/**
	 * Hook in.
	 */
	public static function init(): void {
		add_shortcode( 'hexxsy_offering', array( __CLASS__, 'render' ) );
	}

	/**
	 * Roman step numbers.
	 *
	 * @param string[] $steps Steps.
	 * @return array<string,string> Numeral => step.
	 */
	public static function numbered( array $steps ): array {
		$out = array();
		foreach ( array_values( $steps ) as $i => $step ) {
			$out[ Rooms::numeral( $i + 1 ) ] = $step;
		}
		return $out;
	}

	/**
	 * The facts in the price box: label => value.
	 *
	 * @param \WC_Product $product Product.
	 * @return array<string,string>
	 */
	private static function facts( \WC_Product $product ): array {
		$format = Readings::format( $product );
		if ( Readings::WRITTEN === $format ) {
			$days = (int) $product->get_meta( Readings::TURNAROUND_META );
			return array(
				Lines::text( 'fact_format' )  => Lines::text( 'fact_written' ),
				Lines::text( 'fact_arrives' ) => 1 === $days ? Lines::text( 'fact_arrives_one' ) : Lines::text( 'fact_arrives_value', array( 'days' => (string) $days ) ),
			);
		}
		if ( Readings::LIVE === $format ) {
			return array(
				Lines::text( 'fact_format' ) => Lines::text( 'fact_live' ),
				Lines::text( 'fact_length' ) => Lines::text( 'fact_length_value', array( 'minutes' => (string) (int) $product->get_meta( Readings::DURATION_META ) ) ),
			);
		}
		// Papers and Wares: whatever attributes the product shows.
		$facts = array();
		foreach ( $product->get_attributes() as $attribute ) {
			if ( $attribute instanceof \WC_Product_Attribute && $attribute->get_visible() ) {
				$values = $attribute->is_taxonomy()
					? wc_get_product_terms( $product->get_id(), $attribute->get_name(), array( 'fields' => 'names' ) )
					: $attribute->get_options();
				$facts[ wc_attribute_label( $attribute->get_name() ) ] = implode( ', ', (array) $values );
			}
		}
		return $facts;
	}

	/**
	 * The page.
	 */
	public static function render(): string {
		global $product, $post;

		$shown = is_singular( 'product' ) ? wc_get_product( get_queried_object_id() ) : null;
		if ( ! $shown ) {
			return '';
		}
		// WooCommerce's add-to-cart template reads the global.
		$product = $shown;                           // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$post    = get_post( $shown->get_id() );     // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		setup_postdata( $post );

		$author  = (int) $post->post_author;
		$reader  = $author && Vendor::is_approved( $author ) ? get_userdata( $author ) : null;
		$name    = $reader ? Rooms::name( $reader ) : '';
		$numeral = $reader ? Rooms::numeral( Rooms::number( $reader->ID ) ) : '';
		$room    = $reader ? Rooms::room_url( $reader->ID ) : '';
		$shelf   = Shelves::of( $shown );
		$before  = (string) $shown->get_meta( self::BEFORE_META );
		$steps   = $shelf ? self::numbered( Shelves::lines( $shelf['steps'] ) ) : array();
		$facts   = self::facts( $shown );
		$words   = $shown->get_description();
		$vars    = array(
			'reader'  => $name,
			'numeral' => $numeral,
		);

		ob_start();
		?>
		<div class="hexxsy-offering">
			<nav class="hexxsy-crumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'hexxsy-vendors' ); ?>">
				<a href="<?php echo esc_url( home_url( '/#rooms' ) ); ?>"><?php echo esc_html( Lines::text( 'offering_rooms' ) ); ?></a>
				<?php if ( $reader ) : ?>
					<span aria-hidden="true">/</span>
					<a href="<?php echo esc_url( $room ); ?>"><?php echo esc_html( Lines::text( 'rooms_room' ) . ' ' . $numeral . ' · ' . $name ); ?></a>
				<?php endif; ?>
				<?php if ( $shelf ) : ?>
					<span aria-hidden="true">/</span>
					<span><?php echo esc_html( $shelf['name'] ); ?></span>
				<?php endif; ?>
			</nav>

			<?php wc_print_notices(); ?>

			<div class="hexxsy-offering-cols">
				<div class="hexxsy-offering-main">
					<?php if ( $shelf ) : ?>
						<p class="hexxsy-room-number"><?php echo esc_html( $shelf['name'] . ( '' !== $shelf['kind'] ? ' · ' . $shelf['kind'] : '' ) ); ?></p>
					<?php endif; ?>
					<h1 class="hexxsy-room-title"><?php echo esc_html( $shown->get_name() ); ?></h1>
					<?php if ( $reader ) : ?>
						<p class="hexxsy-offering-by"><?php echo Lines::html( 'offering_by', $vars, $room ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in Lines. ?></p>
					<?php endif; ?>

					<?php if ( $shown->get_image_id() ) : ?>
						<div class="hexxsy-offering-image"><?php echo wp_get_attachment_image( $shown->get_image_id(), 'large' ); ?></div>
					<?php endif; ?>

					<?php if ( '' !== trim( wp_strip_all_tags( $words ) ) ) : ?>
						<div class="hexxsy-offering-words">
							<h2 class="hexxsy-small-label"><?php echo esc_html( $reader ? Lines::text( 'offering_words', $vars ) : Lines::text( 'offering_about' ) ); ?></h2>
							<?php echo wp_kses_post( wpautop( $words ) ); ?>
						</div>
					<?php endif; ?>

					<?php if ( '' !== $before ) : ?>
						<div class="hexxsy-offering-before">
							<h2 class="hexxsy-small-label"><?php echo esc_html( Lines::text( 'offering_before' ) ); ?></h2>
							<?php echo wp_kses_post( wpautop( esc_html( $before ) ) ); ?>
						</div>
					<?php endif; ?>

					<?php if ( $steps ) : ?>
						<div class="hexxsy-offering-steps">
							<h2><?php echo esc_html( Lines::text( 'offering_how' ) ); ?></h2>
							<ol>
								<?php foreach ( $steps as $n => $step ) : ?>
									<li><span class="hexxsy-step-n" aria-hidden="true"><?php echo esc_html( $n ); ?></span><span><?php echo esc_html( $step ); ?></span></li>
								<?php endforeach; ?>
							</ol>
						</div>
					<?php endif; ?>
				</div>

				<aside class="hexxsy-offering-side">
					<div class="hexxsy-buy-box">
						<p class="hexxsy-buy-price"><?php echo wp_kses_post( $shown->get_price_html() ); ?></p>
						<?php if ( $facts ) : ?>
							<dl class="hexxsy-facts">
								<?php foreach ( $facts as $label => $value ) : ?>
									<div><dt><?php echo esc_html( $label ); ?></dt><dd><?php echo esc_html( $value ); ?></dd></div>
								<?php endforeach; ?>
							</dl>
						<?php endif; ?>
						<?php
						Readings::closed_note();
						woocommerce_template_single_add_to_cart();
						?>
					</div>
					<?php if ( $shelf && '' !== $shelf['aside'] ) : ?>
						<div class="hexxsy-offering-hexx">
							<p class="hexxsy-small-label"><?php echo esc_html( Lines::text( 'offering_hexx' ) ); ?></p>
							<p class="hexxsy-room-note-text"><?php echo esc_html( $shelf['aside'] ); ?></p>
						</div>
					<?php endif; ?>
				</aside>
			</div>

			<?php if ( $reader ) : ?>
				<p class="hexxsy-room-back"><a href="<?php echo esc_url( $room ); ?>"><?php echo esc_html( Lines::text( 'offering_back', $vars ) ); ?></a></p>
			<?php endif; ?>
		</div>
		<?php
		wp_reset_postdata();
		return (string) ob_get_clean();
	}
}
