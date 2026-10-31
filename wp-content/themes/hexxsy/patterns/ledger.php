<?php
/**
 * Title: The Ledger (reader waitlist)
 * Slug: hexxsy/ledger
 * Categories: hexxsy-sections, hexxsy-rooms
 * Description: Hexx on the left (her lines change on their own when every room is taken), the waitlist form on the right, as in the Ledger mockup. Use on the Ledger page with the Full width page template.
 * Keywords: ledger, waitlist, reader, invitation
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
			<h1 class="wp-block-heading" style="line-height:1">The Ledger</h1>
			<!-- /wp:heading -->

			<!-- wp:shortcode -->
[hexxsy_line key="ledger"]
			<!-- /wp:shortcode -->

			<!-- wp:paragraph {"textColor":"muted-ink","fontSize":"small"} -->
			<p class="has-muted-ink-color has-text-color has-small-font-size">Rooms open by invitation only. Invited readers fill in the full application and agree to the house code of conduct before taking a room.</p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:column -->

		<!-- wp:column {"width":"55%"} -->
		<div class="wp-block-column" style="flex-basis:55%">
			<!-- wp:shortcode -->
[hexxsy_waitlist]
			<!-- /wp:shortcode -->
		</div>
		<!-- /wp:column -->
	</div>
	<!-- /wp:columns -->
</div>
<!-- /wp:group -->
