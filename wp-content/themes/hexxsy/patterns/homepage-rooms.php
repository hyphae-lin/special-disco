<?php
/**
 * Title: Readers' rooms
 * Slug: hexxsy/homepage-rooms
 * Categories: hexxsy-sections, hexxsy-rooms
 * Description: The band with Readers' Rooms, Hexx's advice on choosing, and the list of rooms.
 * Keywords: rooms, readers, homepage
 */
?>
<!-- wp:group {"anchor":"rooms","align":"full","className":"is-style-band","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60"}}},"layout":{"type":"constrained","contentSize":"1120px"}} -->
<div id="rooms" class="wp-block-group alignfull is-style-band" style="padding-top:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--60)">
	<!-- wp:group {"align":"wide","style":{"spacing":{"margin":{"bottom":"2.5rem"},"blockGap":"1.25rem"}},"layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between","verticalAlignment":"bottom"}} -->
	<div class="wp-block-group alignwide" style="margin-bottom:2.5rem">
		<!-- wp:heading {"style":{"typography":{"fontSize":"clamp(2.25rem, 5vw, 3.5rem)"}}} -->
		<h2 class="wp-block-heading" style="font-size:clamp(2.25rem, 5vw, 3.5rem)">Readers’ Rooms</h2>
		<!-- /wp:heading -->

		<!-- wp:paragraph {"className":"is-style-hexx","style":{"layout":{"selfStretch":"fixed","flexSize":"440px"}}} -->
		<p class="is-style-hexx">Don’t pick by the photo. Pick the one you’d be a little nervous to sit across from. Some of them are better in the morning. I won’t say which.</p>
		<!-- /wp:paragraph -->
	</div>
	<!-- /wp:group -->

	<!-- wp:shortcode {"align":"wide"} -->
[hexxsy_rooms]
	<!-- /wp:shortcode -->

	<!-- wp:paragraph {"align":"center","className":"is-style-hexx","style":{"spacing":{"margin":{"top":"2.5rem"}}}} -->
	<p class="has-text-align-center is-style-hexx" style="margin-top:2.5rem">Every room keeps its own hours and its own shelves. Knock and see what’s inside.</p>
	<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->
