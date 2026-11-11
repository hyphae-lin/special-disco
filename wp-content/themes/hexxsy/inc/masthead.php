<?php
/**
 * The masthead: the "Hexxsy" title at the top of every page.
 *
 * Appearance > Masthead offers three ways to show it:
 *
 * 1. Styled text, the default.
 * 2. A logo image in place of the text.
 * 3. The text over a background image, darkened so it stays readable.
 *
 * The site's name stays in the page as text in every mode (hidden from sight
 * behind a logo), and on the homepage it is the page's main heading, for
 * screen readers and search engines.
 *
 * This works on the header as it's rendered, so it keeps working after the
 * header is edited in the Site Editor, as long as the masthead group and its
 * site title are still there.
 *
 * @package Hexxsy
 */

defined( 'ABSPATH' ) || exit;

const HEXXSY_MASTHEAD_OPTION = 'hexxsy_masthead';

/**
 * Masthead settings, with defaults filled in.
 *
 * @return array{mode:string,logo:int,logo_height:int,image:int,overlay:int,focal_x:int,focal_y:int}
 */
function hexxsy_masthead(): array {
	$saved = get_option( HEXXSY_MASTHEAD_OPTION, array() );
	return hexxsy_masthead_clean( is_array( $saved ) ? $saved : array() );
}

/**
 * Keep the settings in range.
 *
 * @param mixed $value Submitted or stored settings.
 * @return array{mode:string,logo:int,logo_height:int,image:int,overlay:int,focal_x:int,focal_y:int}
 */
function hexxsy_masthead_clean( $value ): array {
	$value = is_array( $value ) ? $value : array();
	$mode  = (string) ( $value['mode'] ?? 'text' );
	$range = static fn( $key, int $default, int $min, int $max ): int => max( $min, min( $max, isset( $value[ $key ] ) && is_numeric( $value[ $key ] ) ? (int) $value[ $key ] : $default ) );

	return array(
		'mode'        => in_array( $mode, array( 'text', 'logo', 'image' ), true ) ? $mode : 'text',
		'logo'        => $range( 'logo', 0, 0, PHP_INT_MAX ),
		'logo_height' => $range( 'logo_height', 120, 40, 320 ),
		'image'       => $range( 'image', 0, 0, PHP_INT_MAX ),
		'overlay'     => $range( 'overlay', 45, 0, 80 ),
		'focal_x'     => $range( 'focal_x', 50, 0, 100 ),
		'focal_y'     => $range( 'focal_y', 50, 0, 100 ),
	);
}

/**
 * Register the setting and its screen.
 */
function hexxsy_masthead_admin(): void {
	add_theme_page( __( 'Masthead', 'hexxsy' ), __( 'Masthead', 'hexxsy' ), 'edit_theme_options', 'hexxsy-masthead', 'hexxsy_masthead_screen' );
}
add_action( 'admin_menu', 'hexxsy_masthead_admin' );

add_action(
	'admin_init',
	static function (): void {
		register_setting(
			'hexxsy_masthead',
			HEXXSY_MASTHEAD_OPTION,
			array(
				'type'              => 'array',
				'default'           => array(),
				'sanitize_callback' => 'hexxsy_masthead_clean',
			)
		);
	}
);

add_action(
	'admin_enqueue_scripts',
	static function ( $hook ): void {
		if ( 'appearance_page_hexxsy-masthead' === $hook ) {
			wp_enqueue_media();
		}
	}
);

/**
 * One image slot on the settings screen: a preview, Choose and Remove.
 *
 * @param string $key   Setting key (logo or image).
 * @param int    $id    Attachment ID, or 0.
 * @param string $label Button label.
 */
function hexxsy_masthead_slot( string $key, int $id, string $label ): void {
	$src = $id ? wp_get_attachment_image_url( $id, 'medium' ) : '';
	?>
	<div class="hexxsy-slot" data-title="<?php echo esc_attr( $label ); ?>">
		<input type="hidden" name="<?php echo esc_attr( HEXXSY_MASTHEAD_OPTION . '[' . $key . ']' ); ?>" value="<?php echo esc_attr( (string) $id ); ?>">
		<img src="<?php echo esc_url( (string) $src ); ?>" alt="" style="max-width:320px;max-height:160px;display:<?php echo $src ? 'block' : 'none'; ?>;margin-bottom:8px;background:#F1E9D8;border:1px solid #c3c4c7">
		<button type="button" class="button hexxsy-slot-choose"><?php echo esc_html( $label ); ?></button>
		<button type="button" class="button-link hexxsy-slot-remove" style="margin-left:8px;<?php echo $id ? '' : 'display:none'; ?>"><?php esc_html_e( 'Remove', 'hexxsy' ); ?></button>
	</div>
	<?php
}

/**
 * The settings screen.
 */
function hexxsy_masthead_screen(): void {
	if ( ! current_user_can( 'edit_theme_options' ) ) {
		return;
	}
	$m    = hexxsy_masthead();
	$name = HEXXSY_MASTHEAD_OPTION;
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Masthead', 'hexxsy' ); ?></h1>
		<p><?php esc_html_e( 'How the site\'s name shows at the top of every page. It is large on the homepage and small everywhere else. The name itself is set in Settings > General, and stays in the page as text whichever you choose, so screen readers and search engines always find it.', 'hexxsy' ); ?></p>
		<form method="post" action="options.php">
			<?php settings_fields( 'hexxsy_masthead' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Show the name as', 'hexxsy' ); ?></th>
					<td>
						<fieldset>
							<label><input type="radio" name="<?php echo esc_attr( $name ); ?>[mode]" value="text" <?php checked( $m['mode'], 'text' ); ?>> <?php esc_html_e( 'Styled text', 'hexxsy' ); ?></label><br>
							<label><input type="radio" name="<?php echo esc_attr( $name ); ?>[mode]" value="logo" <?php checked( $m['mode'], 'logo' ); ?>> <?php esc_html_e( 'A logo image in place of the text', 'hexxsy' ); ?></label><br>
							<label><input type="radio" name="<?php echo esc_attr( $name ); ?>[mode]" value="image" <?php checked( $m['mode'], 'image' ); ?>> <?php esc_html_e( 'The text over a background image', 'hexxsy' ); ?></label>
						</fieldset>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Logo image', 'hexxsy' ); ?></th>
					<td>
						<?php hexxsy_masthead_slot( 'logo', $m['logo'], __( 'Choose a logo', 'hexxsy' ) ); ?>
						<p class="description"><?php esc_html_e( 'A PNG, JPG, WebP or SVG. A transparent background sits best on the paper. With no logo chosen, the styled text shows.', 'hexxsy' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="hexxsy_logo_height"><?php esc_html_e( 'Logo height on the homepage (pixels)', 'hexxsy' ); ?></label></th>
					<td>
						<input type="number" id="hexxsy_logo_height" name="<?php echo esc_attr( $name ); ?>[logo_height]" value="<?php echo esc_attr( (string) $m['logo_height'] ); ?>" min="40" max="320" step="1" class="small-text">
						<p class="description"><?php esc_html_e( 'On other pages the logo is 48 pixels tall. On phones it shrinks to fit.', 'hexxsy' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Background image', 'hexxsy' ); ?></th>
					<td>
						<?php hexxsy_masthead_slot( 'image', $m['image'], __( 'Choose a background', 'hexxsy' ) ); ?>
						<p class="description"><?php esc_html_e( 'Fills the band behind the name and is cropped to fit. With no image chosen, the styled text shows on paper.', 'hexxsy' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="hexxsy_overlay"><?php esc_html_e( 'Darken the background (%)', 'hexxsy' ); ?></label></th>
					<td>
						<input type="number" id="hexxsy_overlay" name="<?php echo esc_attr( $name ); ?>[overlay]" value="<?php echo esc_attr( (string) $m['overlay'] ); ?>" min="0" max="80" step="5" class="small-text">
						<p class="description"><?php esc_html_e( '0 to 80. The name turns paper-coloured over the image, so darken a light picture until it reads.', 'hexxsy' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Focal point', 'hexxsy' ); ?></th>
					<td>
						<label><?php esc_html_e( 'Across (%)', 'hexxsy' ); ?> <input type="number" name="<?php echo esc_attr( $name ); ?>[focal_x]" value="<?php echo esc_attr( (string) $m['focal_x'] ); ?>" min="0" max="100" step="1" class="small-text"></label>
						<label style="margin-left:12px"><?php esc_html_e( 'Down (%)', 'hexxsy' ); ?> <input type="number" name="<?php echo esc_attr( $name ); ?>[focal_y]" value="<?php echo esc_attr( (string) $m['focal_y'] ); ?>" min="0" max="100" step="1" class="small-text"></label>
						<p class="description"><?php esc_html_e( 'The part of the background image to keep in view when it is cropped. 50 and 50 is the middle; 0 and 0 is the top left corner.', 'hexxsy' ); ?></p>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
	</div>
	<script>
	( function () {
		document.querySelectorAll( '.hexxsy-slot' ).forEach( function ( slot ) {
			var input = slot.querySelector( 'input' ), img = slot.querySelector( 'img' ), remove = slot.querySelector( '.hexxsy-slot-remove' ), frame;
			slot.querySelector( '.hexxsy-slot-choose' ).addEventListener( 'click', function () {
				if ( ! frame ) {
					frame = wp.media( { title: slot.dataset.title, library: { type: 'image' }, multiple: false } );
					frame.on( 'select', function () {
						var file = frame.state().get( 'selection' ).first().toJSON();
						input.value = file.id;
						img.src = ( file.sizes && file.sizes.medium ? file.sizes.medium.url : file.url );
						img.style.display = 'block';
						remove.style.display = '';
					} );
				}
				frame.open();
			} );
			remove.addEventListener( 'click', function () {
				input.value = 0;
				img.style.display = 'none';
				remove.style.display = 'none';
			} );
		} );
	}() );
	</script>
	<?php
}

/**
 * The masthead's site title: the main heading on the homepage, and a logo
 * with the name kept as hidden text when a logo is chosen.
 *
 * @param string $html  Rendered block.
 * @param array  $block Parsed block.
 */
function hexxsy_masthead_title( $html, $block ) {
	if ( 'masthead' !== ( $block['attrs']['fontSize'] ?? '' ) ) {
		return $html;
	}

	if ( is_front_page() ) {
		$html = preg_replace( '#^(\s*)<p\b#', '$1<h1', $html, 1 );
		$html = preg_replace( '#</p>(\s*)$#', '</h1>$1', $html, 1 );
	}

	$m   = hexxsy_masthead();
	$src = 'logo' === $m['mode'] && $m['logo'] ? wp_get_attachment_image_url( $m['logo'], 'full' ) : '';
	if ( ! $src ) {
		return $html;
	}

	$img  = '<img class="hexxsy-masthead-logo-img" src="' . esc_url( $src ) . '" alt="" style="--hexxsy-logo-height:' . (int) $m['logo_height'] . 'px">';
	$name = '<span class="screen-reader-text">' . esc_html( get_bloginfo( 'name' ) ) . '</span>';
	$html = preg_replace( '#(<a\b[^>]*>).*?(</a>)#s', '$1' . $img . $name . '$2', $html, 1 );

	$tags = new WP_HTML_Tag_Processor( $html );
	if ( $tags->next_tag() ) {
		$tags->add_class( 'hexxsy-masthead-logo' );
		$html = $tags->get_updated_html();
	}
	return $html;
}
add_filter( 'render_block_core/site-title', 'hexxsy_masthead_title', 10, 2 );

/**
 * The masthead band: a background image under a darkening overlay.
 *
 * @param string $html  Rendered block.
 * @param array  $block Parsed block.
 */
function hexxsy_masthead_band( $html, $block ) {
	if ( ! str_contains( (string) ( $block['attrs']['className'] ?? '' ), 'hexxsy-masthead' ) ) {
		return $html;
	}
	$m   = hexxsy_masthead();
	$src = 'image' === $m['mode'] && $m['image'] ? wp_get_attachment_image_url( $m['image'], 'full' ) : '';
	if ( ! $src ) {
		return $html;
	}

	$tags = new WP_HTML_Tag_Processor( $html );
	if ( $tags->next_tag() ) {
		$style = rtrim( (string) $tags->get_attribute( 'style' ), '; ' );
		$style = ( '' !== $style ? $style . ';' : '' )
			. "--hexxsy-masthead-image:url('" . esc_url( $src ) . "');"
			. '--hexxsy-masthead-overlay:' . ( $m['overlay'] / 100 ) . ';'
			. '--hexxsy-masthead-focal:' . $m['focal_x'] . '% ' . $m['focal_y'] . '%';
		$tags->set_attribute( 'style', $style );
		$tags->add_class( 'has-masthead-image' );
		$html = $tags->get_updated_html();
	}
	return $html;
}
add_filter( 'render_block_core/group', 'hexxsy_masthead_band', 10, 2 );
