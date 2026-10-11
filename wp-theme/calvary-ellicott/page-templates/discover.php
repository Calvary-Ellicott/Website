<?php
/**
 * Template Name: Discover
 * Who we are: the story (page content), what we believe (Beliefs), and the people who serve (Staff).
 */
get_header();
while ( have_posts() ) :
	the_post();
	ce_page_hero();
	$beliefs = get_posts( array( 'post_type' => 'ce_belief', 'post_status' => 'publish', 'posts_per_page' => -1, 'orderby' => array( 'menu_order' => 'ASC', 'date' => 'ASC' ) ) );
	$people  = get_posts( array( 'post_type' => 'ce_staff', 'post_status' => 'publish', 'posts_per_page' => -1, 'orderby' => array( 'menu_order' => 'ASC', 'title' => 'ASC' ) ) );
	?>
	<section id="story" class="section story">
		<div class="story-image" role="img" aria-label="<?php esc_attr_e( 'Open Colorado prairie beneath a wide sky', 'calvary-ellicott' ); ?>"></div>
		<div class="story-copy">
			<p class="eyebrow"><?php echo esc_html( ce_opt( 'story_eyebrow' ) ); ?></p>
			<h2><?php echo esc_html( get_bloginfo( 'name' ) ); ?></h2>
			<div class="prose"><?php the_content(); ?></div>
		</div>
	</section>

	<?php if ( $beliefs ) : ?>
	<section id="beliefs" class="section beliefs-section">
		<div class="beliefs-intro">
			<p class="eyebrow"><?php echo esc_html( ce_opt( 'beliefs_eyebrow' ) ); ?></p>
			<h2><?php echo esc_html( ce_opt( 'beliefs_headline' ) ); ?></h2>
			<p><?php echo esc_html( ce_opt( 'beliefs_text' ) ); ?></p>
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
	<?php endif; ?>

	<section id="staff" class="section staff-section">
		<div class="section-heading narrow"><div>
			<p class="eyebrow"><?php esc_html_e( 'People who serve', 'calvary-ellicott' ); ?></p>
			<h2><?php esc_html_e( 'Meet our staff', 'calvary-ellicott' ); ?></h2>
		</div></div>
		<div class="staff-grid">
			<?php foreach ( $people as $person ) : ?>
				<?php
				$role     = get_post_meta( $person->ID, '_ce_role', true );
				$email    = get_post_meta( $person->ID, '_ce_email', true );
				$initials = strtoupper( mb_substr( implode( '', array_map( function ( $w ) { return mb_substr( $w, 0, 1 ); }, preg_split( '/\s+/', trim( $person->post_title ) ) ) ), 0, 2 ) );
				?>
				<article class="staff-card">
					<?php if ( has_post_thumbnail( $person ) ) : ?>
						<div class="portrait"><?php echo get_the_post_thumbnail( $person, 'ce-portrait', array( 'alt' => esc_attr( $person->post_title ), 'loading' => 'lazy' ) ); ?></div>
					<?php else : ?>
						<div class="portrait portrait-placeholder" aria-hidden="true"><?php echo esc_html( $initials ); ?></div>
					<?php endif; ?>
					<?php if ( $role ) : ?><p class="role"><?php echo esc_html( $role ); ?></p><?php endif; ?>
					<h3><?php echo esc_html( $person->post_title ); ?></h3>
					<p><?php echo esc_html( wp_strip_all_tags( $person->post_content ) ); ?></p>
					<?php if ( $email && is_email( $email ) ) : ?>
						<a class="text-link" href="mailto:<?php echo esc_attr( $email ); ?>"><?php esc_html_e( 'Send an email', 'calvary-ellicott' ); ?> <span>→</span></a>
					<?php endif; ?>
				</article>
			<?php endforeach; ?>
			<article class="staff-card staff-invite">
				<p class="eyebrow"><?php echo esc_html( ce_opt( 'invite_eyebrow' ) ); ?></p>
				<h3><?php echo esc_html( ce_opt( 'invite_headline' ) ); ?></h3>
				<p><?php echo esc_html( ce_opt( 'invite_text' ) ); ?></p>
				<?php echo ce_connect_link( 'forms', ce_opt( 'invite_link_label' ), 'text-link' ); ?>
			</article>
		</div>
	</section>
	<?php
	ce_next_steps( __( 'Keep going', 'calvary-ellicott' ), __( 'Plan a visit, or keep learning.', 'calvary-ellicott' ), array( 'page-templates/visit.php', 'page-templates/learn.php' ) );
endwhile;
get_footer();
