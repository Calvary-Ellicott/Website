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
	$mission = get_posts( array( 'post_type' => 'ce_mission', 'post_status' => 'publish', 'posts_per_page' => -1, 'orderby' => array( 'menu_order' => 'ASC', 'date' => 'ASC' ) ) );
	$people  = get_posts( array( 'post_type' => 'ce_staff', 'post_status' => 'publish', 'posts_per_page' => -1, 'orderby' => array( 'menu_order' => 'ASC', 'title' => 'ASC' ) ) );
	?>
	<section id="story" class="section story">
		<?php ce_story_image( 'discover_image', get_bloginfo( 'name' ) ); ?>
		<div class="story-copy">
			<p class="eyebrow"><?php echo esc_html( ce_opt( 'story_eyebrow' ) ); ?></p>
			<h2><?php echo esc_html( get_bloginfo( 'name' ) ); ?></h2>
			<div class="prose"><?php the_content(); ?></div>
		</div>
	</section>

	<?php if ( $mission ) : ?>
	<section id="mission" class="section mission-section">
		<div class="section-heading narrow"><div>
			<p class="eyebrow"><?php echo esc_html( ce_opt( 'mission_eyebrow' ) ); ?></p>
			<h2><?php echo esc_html( ce_opt( 'mission_headline' ) ); ?></h2>
			<?php if ( ce_opt( 'mission_text' ) ) : ?><p><?php echo esc_html( ce_opt( 'mission_text' ) ); ?></p><?php endif; ?>
		</div></div>
		<div class="mission-grid">
			<?php foreach ( $mission as $i => $m ) : ?>
				<article class="mission-card">
					<span class="detail-number"><?php printf( '%02d', $i + 1 ); ?></span>
					<h3><?php echo esc_html( $m->post_title ); ?></h3>
					<p><?php echo nl2br( esc_html( wp_strip_all_tags( $m->post_content ) ) ); ?></p>
				</article>
			<?php endforeach; ?>
		</div>
	</section>
	<?php endif; ?>

	<?php if ( $beliefs ) : ?>
	<section id="beliefs" class="section beliefs-section">
		<div class="beliefs-intro">
			<p class="eyebrow"><?php echo esc_html( ce_opt( 'beliefs_eyebrow' ) ); ?></p>
			<h2><?php echo esc_html( ce_opt( 'beliefs_headline' ) ); ?></h2>
			<p><?php echo esc_html( ce_opt( 'beliefs_text' ) ); ?></p>
		</div>
		<div class="belief-list">
			<?php foreach ( $beliefs as $i => $belief ) : ?>
				<details open>
					<summary><span><?php printf( '%02d', $i + 1 ); ?></span><?php echo esc_html( $belief->post_title ); ?></summary>
					<p><?php echo esc_html( wp_strip_all_tags( $belief->post_content ) ); ?></p>
				</details>
			<?php endforeach; ?>
		</div>
	</section>
	<?php endif; ?>

	<?php $staff_mode = ce_opt( 'staff_mode' ); $staff_url = ce_https_url( ce_opt( 'connect_staff_url' ) ); ?>
	<?php if ( 'off' !== $staff_mode && ( 'grid' === $staff_mode || ! $staff_url ) ) : ?>
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
	<?php elseif ( 'off' !== $staff_mode ) : ?>
	<section id="staff" class="section staff-section">
		<div class="section-heading">
			<div>
				<p class="eyebrow"><?php esc_html_e( 'People who serve', 'calvary-ellicott' ); ?></p>
				<h2><?php esc_html_e( 'Meet our staff', 'calvary-ellicott' ); ?></h2>
				<p><?php echo esc_html( ce_opt( 'staff_connect_text' ) ); ?></p>
			</div>
			<a class="button primary" href="<?php echo $staff_url; ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( ce_opt( 'staff_link_text' ) ); ?></a>
		</div>
		<?php if ( 'embed' === $staff_mode ) : ?>
		<div class="calendar-shell staff-embed">
			<iframe src="<?php echo $staff_url; ?>" title="<?php esc_attr_e( 'Our staff', 'calvary-ellicott' ); ?>" loading="lazy" referrerpolicy="strict-origin-when-cross-origin"></iframe>
		</div>
		<?php endif; ?>
	</section>
	<?php endif; ?>
	<?php
	ce_next_steps( __( 'Keep going', 'calvary-ellicott' ), __( 'Plan a visit, or keep learning.', 'calvary-ellicott' ), array( 'page-templates/visit.php', 'page-templates/learn.php', 'page-templates/events.php' ) );
endwhile;
get_footer();
