<?php
/**
 * Template Name: Beliefs
 * Page content is the intro; the accordion lists the Belief post type in menu order.
 */
get_header();
while ( have_posts() ) :
	the_post();
	ce_page_hero();
	$beliefs = get_posts(
		array(
			'post_type'      => 'ce_belief',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => array( 'menu_order' => 'ASC', 'date' => 'ASC' ),
		)
	);
	?>
	<section id="beliefs" class="section beliefs-section">
		<div class="beliefs-intro">
			<p class="eyebrow"><?php esc_html_e( 'Our statement of faith', 'calvary-ellicott' ); ?></p>
			<h2><?php esc_html_e( 'What we', 'calvary-ellicott' ); ?><br /><?php esc_html_e( 'hold to.', 'calvary-ellicott' ); ?></h2>
			<p><?php echo esc_html( wp_strip_all_tags( get_the_content() ) ); ?></p>
		</div>
		<div class="belief-list">
			<?php foreach ( $beliefs as $i => $belief ) : ?>
				<details<?php echo 0 === $i ? ' open' : ''; ?>>
					<summary><span><?php printf( '%02d', $i + 1 ); ?></span><?php echo esc_html( $belief->post_title ); ?></summary>
					<p><?php echo esc_html( wp_strip_all_tags( $belief->post_content ) ); ?></p>
				</details>
			<?php endforeach; ?>
		</div>
	</section>
	<?php
endwhile;
get_footer();
