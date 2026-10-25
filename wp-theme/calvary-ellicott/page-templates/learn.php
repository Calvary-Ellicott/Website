<?php
/**
 * Template Name: Learn
 * Livestream (BoxCast) with a video archive link, sermon handouts and notes,
 * and an announcements card that links into Church Connect.
 */
get_header();
while ( have_posts() ) :
	the_post();
	ce_page_hero();
	$count         = max( 1, (int) ce_opt( 'archive_count' ) ) + 1;
	$sermons       = get_posts( array( 'post_type' => 'ce_sermon', 'post_status' => 'publish', 'posts_per_page' => $count, 'orderby' => 'date', 'order' => 'DESC' ) );
	$latest_sermon = array_shift( $sermons );
	$archive_url   = ce_https_url( ce_opt( 'video_archive_url' ) );
	$announce_url  = ce_connect_url( 'announcements' );
	$announce_img  = ce_https_url( ce_opt( 'announce_image' ) );
	?>
	<section id="watch" class="section watch-section">
		<div class="section-heading">
			<div>
				<p class="eyebrow"<?php echo ce_edit_attr( 'opt:watch_live_eyebrow' ); ?>><?php echo esc_html( ce_opt( 'watch_live_eyebrow' ) ); ?></p>
				<h2><?php echo ce_opt_span( 'service_day' ); ?>s at <?php echo ce_opt_span( 'service_time' ); ?></h2>
				<p<?php echo ce_edit_attr( 'opt:watch_live_text' ); ?>><?php echo esc_html( ce_opt( 'watch_live_text' ) ); ?></p>
			</div>
			<?php if ( $archive_url ) : ?>
				<a class="button gold" href="<?php echo $archive_url; ?>" target="_blank" rel="noopener noreferrer"><?php echo ce_opt_span( 'video_archive_label' ); ?></a>
			<?php endif; ?>
		</div>
		<div class="watch-shell">
			<?php
			$embed_code = ce_opt( 'boxcast_embed_code' );
			if ( $embed_code ) {
				echo $embed_code; // Admin-entered embed code from BoxCast, output as pasted.
			} else {
				ce_embed(
					ce_opt( 'boxcast_embed' ),
					__( 'Livestream', 'calvary-ellicott' ),
					__( 'The livestream is being connected', 'calvary-ellicott' ),
					__( 'Our online service will appear here once the BoxCast link is added under Site settings.', 'calvary-ellicott' ),
					true
				);
			}
			?>
		</div>
	</section>

	<section id="notes" class="section sermons-section">
		<div class="sermons-intro">
			<p class="eyebrow"<?php echo ce_edit_attr( 'opt:watch_notes_eyebrow' ); ?>><?php echo esc_html( ce_opt( 'watch_notes_eyebrow' ) ); ?></p>
			<h2<?php echo ce_edit_attr( 'opt:watch_notes_headline' ); ?>><?php echo esc_html( ce_opt( 'watch_notes_headline' ) ); ?></h2>
			<div class="prose lead"><?php the_content(); ?></div>
			<?php ce_editor_link(); ?>
			<div class="sermons-current">
				<?php if ( $latest_sermon ) : ?>
					<?php
					$handout = get_post_meta( $latest_sermon->ID, '_ce_handout', true );
					$notes   = get_post_meta( $latest_sermon->ID, '_ce_notes', true );
					?>
					<p class="eyebrow"><?php echo esc_html( ce_long_date( $latest_sermon ) ); ?></p>
					<?php if ( $latest_sermon->post_title || ce_editing() ) : ?><h3 style="margin:0 0 10px;font-size:1.6rem"<?php echo ce_post_attr( $latest_sermon->ID, 'title' ); ?>><?php echo esc_html( $latest_sermon->post_title ); ?></h3><?php endif; ?>
					<?php if ( trim( $latest_sermon->post_content ) || ce_editing() ) : ?><p style="margin:0 0 16px;color:var(--muted)"<?php echo ce_post_attr( $latest_sermon->ID, 'content' ); ?>><?php echo esc_html( wp_strip_all_tags( $latest_sermon->post_content ) ); ?></p><?php endif; ?>
					<div class="sermon-buttons">
						<?php echo ce_pdf_link( $handout, __( 'Sermon handout', 'calvary-ellicott' ), 'button primary' ); ?>
						<?php echo ce_pdf_link( $notes, __( 'Pastor’s notes', 'calvary-ellicott' ), 'button outline' ); ?>
						<?php if ( ! $notes ) : ?><p class="sermon-pending"<?php echo ce_edit_attr( 'opt:watch_notes_pending' ); ?>><?php echo esc_html( ce_opt( 'watch_notes_pending' ) ); ?></p><?php endif; ?>
					</div>
				<?php else : ?>
					<p class="eyebrow"<?php echo ce_edit_attr( 'opt:watch_notes_empty' ); ?>><?php echo esc_html( ce_opt( 'watch_notes_empty' ) ); ?></p>
				<?php endif; ?>
			</div>
		</div>
		<?php if ( $sermons ) : ?>
			<aside class="sermons-archive">
				<h2<?php echo ce_edit_attr( 'opt:watch_archive_headline' ); ?>><?php echo esc_html( ce_opt( 'watch_archive_headline' ) ); ?></h2>
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

	<?php if ( $announce_url ) : ?>
	<section id="announcements" class="section announce-section">
		<a class="announce-card" href="<?php echo $announce_url; ?>" target="_blank" rel="noopener noreferrer">
			<div class="announce-media"<?php echo $announce_img ? ' style="background-image:url(' . esc_url( $announce_img ) . ')"' : ''; ?>>
				<?php if ( ! $announce_img ) : ?><span class="calendar-icon" aria-hidden="true">CE</span><?php endif; ?>
			</div>
			<div class="announce-copy">
				<p class="eyebrow"<?php echo ce_edit_attr( 'opt:announce_eyebrow' ); ?>><?php echo esc_html( ce_opt( 'announce_eyebrow' ) ); ?></p>
				<h2<?php echo ce_edit_attr( 'opt:announce_headline' ); ?>><?php echo esc_html( ce_opt( 'announce_headline' ) ); ?></h2>
				<p<?php echo ce_edit_attr( 'opt:announce_text' ); ?>><?php echo esc_html( ce_opt( 'announce_text' ) ); ?></p>
				<span class="button primary"><?php echo ce_opt_span( 'announce_button' ); ?></span>
			</div>
		</a>
	</section>
	<?php endif; ?>
	<?php
	ce_next_steps( 'learn_next_eyebrow', 'learn_next_headline', array( 'page-templates/discover.php', 'page-templates/visit.php', 'page-templates/events.php' ) );
endwhile;
get_footer();
