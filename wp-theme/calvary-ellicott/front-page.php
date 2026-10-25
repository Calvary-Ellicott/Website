<?php
/**
 * Homepage: hero, Sunday details, story teaser, the three pages, and the Church Connect band.
 * Wording comes from Site settings (or Appearance → Customize → Calvary Ellicott), and can be
 * changed on the page itself with "Edit this page" (see inc/inline-edit.php).
 */
get_header();
$discover_url = ce_template_url( 'page-templates/discover.php' );
$visit_url    = ce_template_url( 'page-templates/visit.php' );
?>
<section id="home" class="hero" aria-labelledby="hero-title">
	<div class="hero-media" aria-hidden="true">
		<?php foreach ( ce_hero_images() as $i => $img ) : ?>
			<div class="hero-slide<?php echo 0 === $i ? ' is-active' : ''; ?>" style="background-image:url(<?php echo esc_url( $img ); ?>)"></div>
		<?php endforeach; ?>
	</div>
	<div class="hero-content">
		<p class="eyebrow"<?php echo ce_edit_attr( 'opt:hero_eyebrow' ); ?>><?php echo esc_html( ce_opt( 'hero_eyebrow' ) ); ?></p>
		<h1 id="hero-title"><?php echo ce_opt_span( 'hero_line1' ); ?><br /><em<?php echo ce_edit_attr( 'opt:hero_line2' ); ?>><?php echo esc_html( ce_opt( 'hero_line2' ) ); ?></em></h1>
		<p<?php echo ce_edit_attr( 'opt:hero_intro' ); ?>><?php echo esc_html( ce_opt( 'hero_intro' ) ); ?></p>
		<div class="hero-actions">
			<a class="button primary" href="<?php echo esc_url( $visit_url ); ?>"><?php echo ce_opt_span( 'hero_primary_label' ); ?></a>
			<a class="button light" href="<?php echo esc_url( $discover_url ); ?>"><?php echo ce_opt_span( 'hero_secondary_label' ); ?></a>
		</div>
	</div>
	<div class="service-ribbon"><span><?php echo ce_opt_span( 'service_day' ); ?> worship</span><strong><?php echo ce_opt_span( 'service_time' ); ?></strong><span><?php echo ce_opt_span( 'address_1' ); ?></span></div>
</section>

<section id="visit" class="section welcome-grid">
	<div>
		<p class="eyebrow"<?php echo ce_edit_attr( 'opt:visit_eyebrow' ); ?>><?php echo esc_html( ce_opt( 'visit_eyebrow' ) ); ?></p>
		<h2<?php echo ce_edit_attr( 'opt:visit_headline', 'lines' ); ?>><?php echo nl2br( esc_html( ce_opt( 'visit_headline' ) ) ); ?></h2>
		<p class="lead"<?php echo ce_edit_attr( 'opt:visit_lead' ); ?>><?php echo esc_html( ce_opt( 'visit_lead' ) ); ?></p>
		<a class="text-link" href="<?php echo esc_url( $visit_url ); ?>"><?php echo ce_opt_span( 'visit_link_label' ); ?> <span>→</span></a>
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

<section class="section story">
	<?php ce_story_image( 'story_image', ce_opt( 'story_headline' ) ); ?>
	<div class="story-copy">
		<p class="eyebrow"<?php echo ce_edit_attr( 'opt:story_eyebrow' ); ?>><?php echo esc_html( ce_opt( 'story_eyebrow' ) ); ?></p>
		<h2<?php echo ce_edit_attr( 'opt:story_headline' ); ?>><?php echo esc_html( ce_opt( 'story_headline' ) ); ?></h2>
		<p<?php echo ce_edit_attr( 'opt:story_text' ); ?>><?php echo esc_html( ce_opt( 'story_text' ) ); ?></p>
		<a class="text-link story-link" href="<?php echo esc_url( $discover_url ); ?>"><?php echo ce_opt_span( 'story_link_label' ); ?> <span>→</span></a>
	</div>
</section>

<?php ce_next_steps( 'next_eyebrow', 'next_headline', array( 'page-templates/discover.php', 'page-templates/visit.php', 'page-templates/learn.php', 'page-templates/events.php' ) ); ?>


<section id="connect" class="section connect-section">
	<div>
		<p class="eyebrow"<?php echo ce_edit_attr( 'opt:connect_eyebrow' ); ?>><?php echo esc_html( ce_opt( 'connect_eyebrow' ) ); ?></p>
		<h2<?php echo ce_edit_attr( 'opt:connect_headline' ); ?>><?php echo esc_html( ce_opt( 'connect_headline' ) ); ?></h2>
		<p<?php echo ce_edit_attr( 'opt:connect_text' ); ?>><?php echo esc_html( ce_opt( 'connect_text' ) ); ?></p>
		<?php echo ce_connect_link( '', ce_opt( 'connect_button' ), 'button primary', false, 'connect_button' ); ?>
	</div>
	<div class="form-links">
		<?php
		$links = array(
			'events' => __( 'Events & registration', 'calvary-ellicott' ),
			'groups' => __( 'Groups', 'calvary-ellicott' ),
			'serve'  => __( 'Serving & volunteering', 'calvary-ellicott' ),
			'give'   => __( 'Give', 'calvary-ellicott' ),
			'forms'  => __( 'Prayer, visits & forms', 'calvary-ellicott' ),
		);
		foreach ( $links as $key => $label ) {
			$url = ce_connect_url( $key );
			if ( $url ) {
				printf( '<a href="%s" target="_blank" rel="noopener noreferrer"><span>%s</span><b>→</b></a>', $url, esc_html( $label ) );
			}
		}
		?>
	</div>
</section>
<?php
get_footer();
