<?php
/**
 * Default page: banner plus the page content in a readable column.
 */
get_header();
while ( have_posts() ) :
	the_post();
	ce_page_hero();
	?>
	<section class="section page-content">
		<article class="prose">
			<?php the_content(); ?>
		</article>
		<?php ce_editor_link(); ?>
	</section>
	<?php
endwhile;
get_footer();
