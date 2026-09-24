<?php
/**
 * Template Name: About
 * Banner, then the page content beside the story photo, then cards to Staff, Beliefs, Connect.
 */
get_header();
while ( have_posts() ) :
	the_post();
	ce_page_hero();
	?>
	<section class="section story">
		<div class="story-image" role="img" aria-label="<?php esc_attr_e( 'Open Colorado prairie beneath a wide sky', 'calvary-ellicott' ); ?>"></div>
		<div class="story-copy">
			<p class="eyebrow"><?php esc_html_e( 'Who we are', 'calvary-ellicott' ); ?></p>
			<h2><?php echo esc_html( get_bloginfo( 'name' ) ); ?></h2>
			<div class="prose"><?php the_content(); ?></div>
		</div>
	</section>
	<?php
	ce_next_steps( __( 'Keep exploring', 'calvary-ellicott' ), __( 'Get to know us.', 'calvary-ellicott' ), array( 'page-templates/staff.php', 'page-templates/beliefs.php', 'page-templates/connect.php' ) );
endwhile;
get_footer();
