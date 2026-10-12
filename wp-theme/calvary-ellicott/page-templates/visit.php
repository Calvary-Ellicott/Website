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
			<p class="eyebrow"><?php echo esc_html( ce_opt( 'visit_eyebrow' ) ); ?></p>
			<h2><?php echo nl2br( esc_html( ce_opt( 'visit_headline' ) ) ); ?></h2>
			<div class="prose lead"><?php the_content(); ?></div>
			<div class="hero-actions">
				<?php echo ce_connect_link( 'forms', __( 'Tell us you’re coming', 'calvary-ellicott' ), 'button primary', false ); ?>
				<a class="button outline" href="<?php echo esc_url( ce_maps_url() ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Get directions', 'calvary-ellicott' ); ?></a>
			</div>
		</div>
		<?php
		ce_details_card(
			array(
				array( 'When', esc_html( ce_service_line() ) ),
				array( 'Where', esc_html( ce_opt( 'address_1' ) ) . '<br />' . esc_html( ce_opt( 'address_2' ) ) ),
				array( 'What to expect', esc_html( ce_opt( 'visit_expect' ) ) ),
			)
		);
		?>
	</section>

	<section id="map" class="section map-section">
		<div class="section-heading">
			<div>
				<p class="eyebrow"><?php esc_html_e( 'Find us', 'calvary-ellicott' ); ?></p>
				<h2><?php echo esc_html( ce_opt( 'address_1' ) ); ?></h2>
				<p><?php echo esc_html( ce_opt( 'address_2' ) ); ?></p>
			</div>
			<a class="button primary" href="<?php echo esc_url( ce_maps_url() ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open in Google Maps', 'calvary-ellicott' ); ?></a>
		</div>
		<div class="map-shell">
			<iframe src="<?php echo esc_url( ce_map_embed_url() ); ?>" title="<?php echo esc_attr( sprintf( __( 'Map to %s', 'calvary-ellicott' ), get_bloginfo( 'name' ) ) ); ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>
		</div>
	</section>

	<section id="sunday" class="section story">
		<div class="story-image" role="img" aria-label="<?php esc_attr_e( 'Open Colorado prairie beneath a wide sky', 'calvary-ellicott' ); ?>"></div>
		<div class="story-copy">
			<p class="eyebrow"><?php esc_html_e( 'What a Sunday looks like', 'calvary-ellicott' ); ?></p>
			<h2><?php esc_html_e( 'Simple, warm, and centered on Jesus.', 'calvary-ellicott' ); ?></h2>
			<p><?php esc_html_e( 'We gather to worship, open the Bible together, and pray for one another. Children are cared for with age-appropriate teaching, and there’s always time to talk afterward.', 'calvary-ellicott' ); ?></p>
			<p><?php esc_html_e( 'Have questions before you come? Send us a note and someone will get back to you.', 'calvary-ellicott' ); ?></p>
			<?php echo ce_connect_link( 'forms', __( 'Ask a question', 'calvary-ellicott' ), 'text-link story-link' ); ?>
		</div>
	</section>
	<?php
	ce_next_steps( __( 'Before you come', 'calvary-ellicott' ), __( 'Get to know us.', 'calvary-ellicott' ), array( 'page-templates/discover.php', 'page-templates/learn.php' ) );
endwhile;
get_footer();
