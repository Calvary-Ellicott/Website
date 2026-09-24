<?php
/**
 * Template Name: Staff
 * Cards from the Staff post type, in menu order, then the invite card.
 */
get_header();
while ( have_posts() ) :
	the_post();
	ce_page_hero();
	$people = get_posts(
		array(
			'post_type'      => 'ce_staff',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => array( 'menu_order' => 'ASC', 'title' => 'ASC' ),
		)
	);
	?>
	<section id="staff" class="section staff-section">
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
				<?php echo ce_churchtrac_link( 'contact', ce_opt( 'invite_link_label' ), 'text-link' ); ?>
			</article>
		</div>
	</section>
	<?php
endwhile;
get_footer();
