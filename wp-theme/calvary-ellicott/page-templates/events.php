<?php
/**
 * Template Name: Events
 * ChurchTrac calendar embed. Links come from Customize → ChurchTrac links.
 */
get_header();
while ( have_posts() ) :
	the_post();
	ce_page_hero();
	?>
	<section id="events" class="section events-section">
		<div class="section-heading">
			<div>
				<p class="eyebrow"><?php esc_html_e( 'Calendar', 'calvary-ellicott' ); ?></p>
				<h2><?php esc_html_e( 'What’s coming up', 'calvary-ellicott' ); ?></h2>
			</div>
			<?php echo ce_churchtrac_link( 'calendar', __( 'Open full calendar', 'calvary-ellicott' ), 'button outline', false ); ?>
		</div>
		<?php if ( get_the_content() ) : ?>
			<div class="prose" style="margin-top:24px"><?php the_content(); ?></div>
		<?php endif; ?>
		<div class="calendar-shell">
			<?php
			ce_embed(
				ce_churchtrac_url( 'calendarEmbed' ),
				__( 'Event calendar', 'calvary-ellicott' ),
				__( 'Our calendar is moving to ChurchTrac', 'calvary-ellicott' ),
				__( 'Upcoming gatherings and registrations will appear here as soon as the public ChurchTrac calendar link is connected.', 'calvary-ellicott' )
			);
			?>
		</div>
	</section>
	<?php
endwhile;
get_footer();
