<?php
/**
 * Template Name: Visit
 * When, where, what to expect, and a plan-a-visit link into Church Connect.
 */
get_header();
while ( have_posts() ) :
	the_post();
	ce_page_hero();
	?>
	<section id="details" class="section welcome-grid">
		<div>
			<p class="eyebrow"<?php echo ce_edit_attr( 'opt:visit_eyebrow' ); ?>><?php echo esc_html( ce_opt( 'visit_eyebrow' ) ); ?></p>
			<h2<?php echo ce_edit_attr( 'opt:visit_headline', 'lines' ); ?>><?php echo nl2br( esc_html( ce_opt( 'visit_headline' ) ) ); ?></h2>
			<div class="prose lead"><?php the_content(); ?></div>
			<?php ce_editor_link(); ?>
			<div class="hero-actions">
				<?php echo ce_connect_link( 'forms', ce_opt( 'visit_cta_label' ), 'button primary', false, 'visit_cta_label' ); ?>
				<a class="button outline" href="<?php echo esc_url( ce_maps_url() ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Get directions', 'calvary-ellicott' ); ?></a>
			</div>
			<?php if ( is_email( ce_opt( 'contact_email' ) ) ) : ?><p class="visit-contact"><?php esc_html_e( 'Questions?', 'calvary-ellicott' ); ?> <a href="mailto:<?php echo esc_attr( ce_opt( 'contact_email' ) ); ?>"><?php echo esc_html( ce_opt( 'contact_email' ) ); ?></a><?php if ( ce_opt( 'phone' ) ) : ?> · <?php echo ce_opt_span( 'phone' ); ?><?php endif; ?></p><?php endif; ?>
		</div>
		<?php
		ce_details_card(
			array(
				array( 'When', ce_service_line_html() ),
				array( 'Where', ce_address_html() ),
				array( 'What to expect', ce_opt_span( 'visit_expect' ) ),
			)
		);
		?>
	</section>

	<section id="map" class="section map-section">
		<div class="section-heading">
			<div>
				<p class="eyebrow"<?php echo ce_edit_attr( 'opt:map_eyebrow' ); ?>><?php echo esc_html( ce_opt( 'map_eyebrow' ) ); ?></p>
				<h2<?php echo ce_edit_attr( 'opt:address_1' ); ?>><?php echo esc_html( ce_opt( 'address_1' ) ); ?></h2>
				<p<?php echo ce_edit_attr( 'opt:address_2' ); ?>><?php echo esc_html( ce_opt( 'address_2' ) ); ?></p>
			</div>
			<a class="button primary" href="<?php echo esc_url( ce_maps_url() ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open in Google Maps', 'calvary-ellicott' ); ?></a>
		</div>
		<div class="map-shell">
			<iframe src="<?php echo esc_url( ce_map_embed_url() ); ?>" title="<?php echo esc_attr( sprintf( __( 'Map to %s', 'calvary-ellicott' ), get_bloginfo( 'name' ) ) ); ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>
		</div>
	</section>

	<section id="sunday" class="section story">
		<?php ce_story_image( 'visit_image', ce_opt( 'sunday_eyebrow' ) ); ?>
		<div class="story-copy">
			<p class="eyebrow"<?php echo ce_edit_attr( 'opt:sunday_eyebrow' ); ?>><?php echo esc_html( ce_opt( 'sunday_eyebrow' ) ); ?></p>
			<h2<?php echo ce_edit_attr( 'opt:sunday_headline' ); ?>><?php echo esc_html( ce_opt( 'sunday_headline' ) ); ?></h2>
			<p<?php echo ce_edit_attr( 'opt:sunday_text' ); ?>><?php echo esc_html( ce_opt( 'sunday_text' ) ); ?></p>
			<?php if ( ce_opt( 'sunday_text2' ) || ce_editing() ) : ?><p<?php echo ce_edit_attr( 'opt:sunday_text2' ); ?>><?php echo esc_html( ce_opt( 'sunday_text2' ) ); ?></p><?php endif; ?>
			<?php echo ce_connect_link( 'forms', ce_opt( 'sunday_link_label' ), 'text-link story-link', true, 'sunday_link_label' ); ?>
		</div>
	</section>
	<?php
	ce_next_steps( 'visit_next_eyebrow', 'visit_next_headline', array( 'page-templates/discover.php', 'page-templates/learn.php' ) );
endwhile;
get_footer();
