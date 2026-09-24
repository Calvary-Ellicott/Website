<?php
/**
 * Template Name: Watch
 * BoxCast livestream embed and sermon notes from the Sermon post type.
 */
get_header();
while ( have_posts() ) :
	the_post();
	ce_page_hero();
	$sermons = get_posts(
		array(
			'post_type'      => 'ce_sermon',
			'post_status'    => 'publish',
			'posts_per_page' => max( 1, (int) ce_opt( 'archive_count' ) ) + 1,
			'orderby'        => 'date',
			'order'          => 'DESC',
		)
	);
	$latest = array_shift( $sermons );
	?>
	<section id="watch" class="section watch-section">
		<div class="section-heading narrow">
			<div>
				<p class="eyebrow"><?php echo esc_html( ce_opt( 'watch_live_eyebrow' ) ); ?></p>
				<h2><?php echo esc_html( ce_opt( 'service_day' ) . 's at ' . ce_opt( 'service_time' ) ); ?></h2>
				<p><?php echo esc_html( ce_opt( 'watch_live_text' ) ); ?></p>
			</div>
		</div>
		<div class="watch-shell">
			<?php
			ce_embed(
				ce_opt( 'boxcast_embed' ),
				__( 'Livestream', 'calvary-ellicott' ),
				__( 'The livestream is being connected', 'calvary-ellicott' ),
				__( 'Our online service will appear here once the BoxCast link is added.', 'calvary-ellicott' ),
				true
			);
			?>
		</div>
	</section>

	<section id="notes" class="section sermons-section">
		<div class="sermons-intro">
			<p class="eyebrow"><?php echo esc_html( ce_opt( 'watch_notes_eyebrow' ) ); ?></p>
			<h2><?php echo esc_html( ce_opt( 'watch_notes_headline' ) ); ?></h2>
			<div class="prose lead"><?php the_content(); ?></div>
			<div class="sermons-current">
				<?php if ( $latest ) : ?>
					<?php
					$handout = get_post_meta( $latest->ID, '_ce_handout', true );
					$notes   = get_post_meta( $latest->ID, '_ce_notes', true );
					?>
					<p class="eyebrow"><?php echo esc_html( ce_long_date( $latest ) ); ?></p>
					<?php if ( $latest->post_title ) : ?><h3 style="margin:0 0 14px;font-size:1.6rem"><?php echo esc_html( $latest->post_title ); ?></h3><?php endif; ?>
					<div class="sermon-buttons">
						<?php echo ce_pdf_link( $handout, __( 'Sermon handout', 'calvary-ellicott' ), 'button primary' ); ?>
						<?php echo ce_pdf_link( $notes, __( 'Pastor’s notes', 'calvary-ellicott' ), 'button outline' ); ?>
						<?php if ( ! $notes ) : ?><p class="sermon-pending"><?php echo esc_html( ce_opt( 'watch_notes_pending' ) ); ?></p><?php endif; ?>
					</div>
				<?php else : ?>
					<p class="eyebrow"><?php echo esc_html( ce_opt( 'watch_notes_empty' ) ); ?></p>
				<?php endif; ?>
			</div>
		</div>
		<?php if ( $sermons ) : ?>
			<aside class="sermons-archive">
				<h2><?php esc_html_e( 'Previous weeks', 'calvary-ellicott' ); ?></h2>
				<ul>
					<?php foreach ( $sermons as $s ) : ?>
						<li>
							<span class="sermon-date"><?php echo esc_html( ce_long_date( $s ) ); ?><?php echo $s->post_title ? ' · ' . esc_html( $s->post_title ) : ''; ?></span>
							<span class="sermon-links">
								<?php echo ce_pdf_link( get_post_meta( $s->ID, '_ce_handout', true ), __( 'Handout', 'calvary-ellicott' ) ); ?>
								<?php echo ce_pdf_link( get_post_meta( $s->ID, '_ce_notes', true ), __( 'Notes', 'calvary-ellicott' ) ); ?>
							</span>
						</li>
					<?php endforeach; ?>
				</ul>
			</aside>
		<?php endif; ?>
	</section>
	<?php
endwhile;
get_footer();
