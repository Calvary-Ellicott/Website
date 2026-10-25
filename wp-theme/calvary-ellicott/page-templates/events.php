<?php
/**
 * Template Name: Events
 * The ChurchTrac public calendar, with a link to events in Church Connect.
 */
get_header();
while ( have_posts() ) :
	the_post();
	ce_page_hero();
	?>
	<section id="events" class="section events-section">
		<div class="section-heading">
			<div>
				<p class="eyebrow"<?php echo ce_edit_attr( 'opt:calendar_eyebrow' ); ?>><?php echo esc_html( ce_opt( 'calendar_eyebrow' ) ); ?></p>
				<h2<?php echo ce_edit_attr( 'opt:calendar_headline' ); ?>><?php echo esc_html( ce_opt( 'calendar_headline' ) ); ?></h2>
				<p<?php echo ce_edit_attr( 'opt:calendar_text' ); ?>><?php echo esc_html( ce_opt( 'calendar_text' ) ); ?></p>
			</div>
			<?php echo ce_connect_link( 'events', ce_opt( 'calendar_button' ), 'button primary', false, 'calendar_button' ); ?>
		</div>
		<?php if ( trim( get_the_content() ) ) : ?>
			<div class="prose" style="margin-top:24px"><?php the_content(); ?></div>
		<?php endif; ?>
		<?php ce_editor_link(); ?>
		<div class="calendar-shell">
			<?php
			ce_embed(
				ce_opt( 'calendar_embed' ),
				__( 'Event calendar', 'calvary-ellicott' ),
				__( 'The calendar is being connected', 'calvary-ellicott' ),
				__( 'Upcoming gatherings will appear here as soon as the ChurchTrac calendar link is added under Site settings.', 'calvary-ellicott' )
			);
			?>
		</div>
	</section>
	<?php
	ce_next_steps( 'events_next_eyebrow', 'events_next_headline', array( 'page-templates/discover.php', 'page-templates/visit.php', 'page-templates/learn.php' ) );
endwhile;
get_footer();
