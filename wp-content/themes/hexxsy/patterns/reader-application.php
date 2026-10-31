<?php
/**
 * Title: Become a reader
 * Slug: hexxsy/reader-application
 * Categories: hexxsy-sections, hexxsy-rooms
 * Description: Hexx's welcome on the left, the reader application form on the right. Use on the Become a Reader page with the Full width page template.
 * Keywords: reader, apply, application, ledger, vendor
 */
?>
<!-- wp:group {"align":"wide","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60"}}},"layout":{"type":"constrained","contentSize":"1120px"}} -->
<div class="wp-block-group alignwide" style="padding-top:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--60)">
	<!-- wp:columns {"align":"wide","style":{"spacing":{"blockGap":{"left":"3.5rem","top":"2.5rem"}}}} -->
	<div class="wp-block-columns alignwide">
		<!-- wp:column {"width":"45%"} -->
		<div class="wp-block-column" style="flex-basis:45%">
			<!-- wp:paragraph {"className":"is-style-label","textColor":"oxblood"} -->
			<p class="is-style-label has-oxblood-color has-text-color">For readers</p>
			<!-- /wp:paragraph -->

			<!-- wp:heading {"level":1,"style":{"typography":{"lineHeight":"1"}}} -->
			<h1 class="wp-block-heading" style="line-height:1">Take a room</h1>
			<!-- /wp:heading -->

			<!-- wp:paragraph {"fontFamily":"fell","style":{"typography":{"fontSize":"1.75rem","lineHeight":"1.35"}}} -->
			<p class="has-fell-font-family" style="font-size:1.75rem;line-height:1.35">You have an invitation. Tell me about yourself.</p>
			<!-- /wp:paragraph -->

			<!-- wp:paragraph -->
			<p>Every room here belongs to an independent reader, who keeps their own hours, sets their own prices and fills their own shelves. I read every application myself.</p>
			<!-- /wp:paragraph -->

			<!-- wp:paragraph {"textColor":"muted-ink","fontSize":"small"} -->
			<p class="has-muted-ink-color has-text-color has-small-font-size">Readers agree to the house code of conduct before taking a room. Readings on Hexxsy are sold for entertainment only.</p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:column -->

		<!-- wp:column {"width":"55%"} -->
		<div class="wp-block-column" style="flex-basis:55%">
			<!-- wp:shortcode -->
[hexxsy_vendor_application]
			<!-- /wp:shortcode -->
		</div>
		<!-- /wp:column -->
	</div>
	<!-- /wp:columns -->
</div>
<!-- /wp:group -->
