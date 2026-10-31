<?php
/**
 * Title: Homepage hero
 * Slug: hexxsy/homepage-hero
 * Categories: hexxsy-sections
 * Description: The woodcut beside Hexx's welcome and the Walk the rooms button.
 * Keywords: homepage, hero, welcome, hexx
 */
?>
<!-- wp:group {"align":"wide","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60"}}},"layout":{"type":"constrained","contentSize":"1120px"}} -->
<div class="wp-block-group alignwide" style="padding-top:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--60)">
	<!-- wp:columns {"verticalAlignment":"center","align":"wide","style":{"spacing":{"blockGap":{"left":"3.5rem","top":"2.5rem"}}}} -->
	<div class="wp-block-columns alignwide are-vertically-aligned-center">
		<!-- wp:column {"verticalAlignment":"center","width":"40%"} -->
		<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:40%">
			<!-- wp:image {"align":"center","width":"340px","sizeSlug":"full","linkDestination":"none"} -->
			<figure class="wp-block-image aligncenter size-full is-resized"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/images/crossroads.svg' ) ); ?>" alt="A woodcut of two roads crossing beneath a lamp" style="width:340px"/></figure>
			<!-- /wp:image -->
		</div>
		<!-- /wp:column -->

		<!-- wp:column {"verticalAlignment":"center","width":"60%"} -->
		<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:60%">
			<!-- wp:paragraph {"className":"is-style-label","textColor":"oxblood"} -->
			<p class="is-style-label has-oxblood-color has-text-color">Hexx, Proprietress</p>
			<!-- /wp:paragraph -->

			<!-- wp:paragraph {"fontFamily":"fell","style":{"typography":{"fontSize":"clamp(1.875rem, 4vw, 2.75rem)","lineHeight":"1.15"}}} -->
			<p class="has-fell-font-family" style="font-size:clamp(1.875rem, 4vw, 2.75rem);line-height:1.15">You found the crossroads. Most people walk right past.</p>
			<!-- /wp:paragraph -->

			<!-- wp:paragraph {"style":{"layout":{"selfStretch":"fit"}}} -->
			<p>This is Hexxsy, a house of rooms and the tarot readers who keep them. Some will read for you live, others send it on later, and most keep a shelf of goods besides. I keep the gate, and the lamps, when I remember. Have a look around.</p>
			<!-- /wp:paragraph -->

			<!-- wp:buttons -->
			<div class="wp-block-buttons">
				<!-- wp:button -->
				<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="#rooms">Walk the rooms</a></div>
				<!-- /wp:button -->
			</div>
			<!-- /wp:buttons -->
		</div>
		<!-- /wp:column -->
	</div>
	<!-- /wp:columns -->
</div>
<!-- /wp:group -->
