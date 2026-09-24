<?php
/**
 * Fallback template for anything without a more specific one (search, archives, posts).
 */
get_header();
?>
<section class="page-hero">
	<p class="eyebrow"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></p>
	<h1><?php echo is_search() ? esc_html__( 'Search results', 'calvary-ellicott' ) : esc_html( wp_strip_all_tags( get_the_archive_title() ) ); ?></h1>
</section>
<section class="section page-content">
	<div class="prose">
		<?php if ( have_posts() ) : ?>
			<?php
			while ( have_posts() ) :
				the_post();
				?>
				<h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
				<?php the_excerpt(); ?>
			<?php endwhile; ?>
			<?php the_posts_pagination(); ?>
		<?php else : ?>
			<p><?php esc_html_e( 'Nothing here yet.', 'calvary-ellicott' ); ?></p>
		<?php endif; ?>
	</div>
</section>
<?php
get_footer();
