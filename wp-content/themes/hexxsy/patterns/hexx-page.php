<?php
/**
 * Title: Hexx's page
 * Slug: hexxsy/hexx-page
 * Categories: hexxsy-hexx, hexxsy-sections
 * Description: Hexx's own page: her portrait, who she is, the Rules of the House and where to find her. Use on a page with the Full width page template.
 * Keywords: hexx, proprietress, about, rules, contact
 * Block Types: core/post-content
 */
?>
<!-- wp:group {"align":"wide","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60"}}},"layout":{"type":"constrained","contentSize":"1120px"}} -->
<div class="wp-block-group alignwide" style="padding-top:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--60)">
	<!-- wp:columns {"align":"wide","style":{"spacing":{"blockGap":{"left":"3.5rem","top":"2.5rem"}}}} -->
	<div class="wp-block-columns alignwide">
		<!-- wp:column {"width":"38%"} -->
		<div class="wp-block-column" style="flex-basis:38%">
			<!-- wp:image {"sizeSlug":"full","linkDestination":"none","className":"is-style-framed"} -->
			<figure class="wp-block-image size-full is-style-framed"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/images/hexx-portrait.svg' ) ); ?>" alt="A portrait of Hexx"/><figcaption class="wp-element-caption">Taken without permission. She’s still annoyed about it.</figcaption></figure>
			<!-- /wp:image -->
		</div>
		<!-- /wp:column -->

		<!-- wp:column {"width":"62%"} -->
		<div class="wp-block-column" style="flex-basis:62%">
			<!-- wp:paragraph {"className":"is-style-label","textColor":"oxblood","style":{"typography":{"fontSize":"1.125rem","letterSpacing":"0.16em"}}} -->
			<p class="is-style-label has-oxblood-color has-text-color" style="font-size:1.125rem;letter-spacing:0.16em">Proprietress</p>
			<!-- /wp:paragraph -->

			<!-- wp:heading {"level":1,"style":{"typography":{"fontSize":"clamp(4.5rem, 10vw, 8rem)","lineHeight":"0.9","letterSpacing":"0.04em"},"spacing":{"margin":{"top":"0.375rem","bottom":"0"}}},"fontFamily":"fell-sc"} -->
			<h1 class="wp-block-heading has-fell-sc-font-family" style="margin-top:0.375rem;margin-bottom:0;font-size:clamp(4.5rem, 10vw, 8rem);letter-spacing:0.04em;line-height:0.9">Hexx</h1>
			<!-- /wp:heading -->

			<!-- wp:paragraph {"className":"is-style-hexx"} -->
			<p class="is-style-hexx">Keeper of the gate, the ledger and the lamps</p>
			<!-- /wp:paragraph -->

			<!-- wp:separator {"className":"is-style-thin-rule"} -->
			<hr class="wp-block-separator has-alpha-channel-opacity is-style-thin-rule"/>
			<!-- /wp:separator -->

			<!-- wp:paragraph {"fontFamily":"fell","style":{"typography":{"fontSize":"1.3125rem","lineHeight":"1.55"}}} -->
			<p class="has-fell-font-family" style="font-size:1.3125rem;line-height:1.55">I’ve kept this house longer than I care to count. Nobody built it for me. I found it at the crossroads with the lamps already lit and a chair by the gate, so I sat down. I’ve been sitting there since.</p>
			<!-- /wp:paragraph -->

			<!-- wp:paragraph {"fontFamily":"fell","style":{"typography":{"fontSize":"1.3125rem","lineHeight":"1.55"}}} -->
			<p class="has-fell-font-family" style="font-size:1.3125rem;line-height:1.55">I don’t read. People ask, and I tell them the same thing: I’m better at knowing who should. Every reader here came through that gate because I watched them work and liked what I saw. Some of them I’d trust with my own questions. A few I have.</p>
			<!-- /wp:paragraph -->

			<!-- wp:paragraph {"fontFamily":"fell","style":{"typography":{"fontSize":"1.3125rem","lineHeight":"1.55"}}} -->
			<p class="has-fell-font-family" style="font-size:1.3125rem;line-height:1.55">What I do is keep the place. I trim the lamps and go through the ledger on Sundays. I sweep the step. When there’s a dispute I settle it, which happens less often than you’d think. And I write a word about each reader, so you know what you’re walking into.</p>
			<!-- /wp:paragraph -->

			<!-- wp:paragraph {"fontFamily":"fell","style":{"typography":{"fontSize":"1.3125rem","lineHeight":"1.55"}}} -->
			<p class="has-fell-font-family" style="font-size:1.3125rem;line-height:1.55">If something here goes wrong, come find me. I’m usually at the gate.</p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:column -->
	</div>
	<!-- /wp:columns -->
</div>
<!-- /wp:group -->

<!-- wp:group {"align":"full","className":"is-style-band","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60"}}},"layout":{"type":"constrained","contentSize":"1120px"}} -->
<div class="wp-block-group alignfull is-style-band" style="padding-top:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--60)">
	<!-- wp:columns {"align":"wide","style":{"spacing":{"blockGap":{"left":"3rem","top":"2.5rem"}}}} -->
	<div class="wp-block-columns alignwide">
		<!-- wp:column {"width":"62%"} -->
		<div class="wp-block-column" style="flex-basis:62%">
			<!-- wp:heading {"style":{"typography":{"fontSize":"clamp(2.25rem, 5vw, 3.25rem)"}}} -->
			<h2 class="wp-block-heading" style="font-size:clamp(2.25rem, 5vw, 3.25rem)">Rules of the House</h2>
			<!-- /wp:heading -->

			<!-- wp:list {"ordered":true,"className":"is-style-rules"} -->
			<ol class="wp-block-list is-style-rules">
				<!-- wp:list-item -->
				<li>Readers keep their own rooms. I don’t tell them how to read.</li>
				<!-- /wp:list-item -->

				<!-- wp:list-item -->
				<li>Everything here is for entertainment. I’ve said it before and I’ll keep saying it.</li>
				<!-- /wp:list-item -->

				<!-- wp:list-item -->
				<li>Be decent to the readers. They answer to me for how they treat you, and you answer to me for how you treat them.</li>
				<!-- /wp:list-item -->

				<!-- wp:list-item -->
				<li>Wipe your feet.</li>
				<!-- /wp:list-item -->
			</ol>
			<!-- /wp:list -->
		</div>
		<!-- /wp:column -->

		<!-- wp:column {"width":"38%"} -->
		<div class="wp-block-column" style="flex-basis:38%">
			<!-- wp:group {"className":"is-style-card","style":{"spacing":{"padding":{"top":"1.625rem","bottom":"1.625rem","left":"1.625rem","right":"1.625rem"},"blockGap":"0.75rem"}},"layout":{"type":"constrained"}} -->
			<div class="wp-block-group is-style-card" style="padding-top:1.625rem;padding-right:1.625rem;padding-bottom:1.625rem;padding-left:1.625rem">
				<!-- wp:paragraph {"className":"is-style-label","style":{"typography":{"fontSize":"1.375rem","letterSpacing":"0.08em"}}} -->
				<p class="is-style-label" style="font-size:1.375rem;letter-spacing:0.08em">Find me at the gate</p>
				<!-- /wp:paragraph -->

				<!-- wp:paragraph -->
				<p>For trouble with an order, a reader, or anything else that isn’t sitting right.</p>
				<!-- /wp:paragraph -->

				<!-- wp:buttons -->
				<div class="wp-block-buttons">
					<!-- wp:button {"width":100} -->
					<div class="wp-block-button has-custom-width wp-block-button__width-100"><a class="wp-block-button__link wp-element-button" href="mailto:">Write to me</a></div>
					<!-- /wp:button -->
				</div>
				<!-- /wp:buttons -->
			</div>
			<!-- /wp:group -->
		</div>
		<!-- /wp:column -->
	</div>
	<!-- /wp:columns -->
</div>
<!-- /wp:group -->
