<?php
/**
 * Title: Homepage
 * Slug: hexxsy/homepage
 * Categories: hexxsy-sections
 * Description: The whole homepage: hero, readers' rooms and the house notice. Use on the homepage with the Full width page template.
 * Keywords: homepage, front page
 * Block Types: core/post-content
 */
?>
<!-- wp:pattern {"slug":"hexxsy/homepage-hero"} /-->

<!-- wp:pattern {"slug":"hexxsy/homepage-rooms"} /-->

<!-- wp:group {"align":"wide","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|50"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignwide" style="padding-top:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--50)">
	<!-- wp:pattern {"slug":"hexxsy/house-notice"} /-->
</div>
<!-- /wp:group -->
