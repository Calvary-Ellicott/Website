<?php
/**
 * Template Name: Learn
 * Livestream (BoxCast), sermon handouts and notes, and the weekly bulletin.
 */
get_header();
while ( have_posts() ) :
	the_post();
	ce_page_hero();
	$count   = max( 1, (int) ce_opt( 'archive_count' ) ) + 1;
	$sermons = get_posts( array( 'post_type' => 'ce_sermon', 'post_status' => 'publish', 'posts_per_page' => $count, 'orderby' => 'date', 'order' => 'DESC' ) );
	$latest_sermon = array_shift( $sermons );
	$bulletins = get_posts( array( 'post_type' => 'ce_bulletin', 'post_status' => 'publish', 'posts_per_page' => $count, 'orderby' => 'date', 'order' => 'DESC', 'meta_key' => '_ce_pdf', 'meta_compare' => '!=', 'meta_value' => '' ) );
	$latest_bulletin = array_shift( $bulletins );
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
				<?php if ( $latest_sermon ) : ?>
					<?php
					$handout = get_post_meta( $latest_sermon->ID, '_ce_handout', true );
					$notes   = get_post_meta( $latest_sermon->ID, '_ce_notes', true );
					?>
					<p class="eyebrow"><?php echo esc_html( ce_long_date( $latest_sermon ) ); ?></p>
					<?php if ( $latest_sermon->post_title ) : ?><h3 style="margin:0 0 10px;font-size:1.6rem"><?php echo esc_html( $latest_sermon->post_title ); ?></h3><?php endif; ?>
					<?php if ( trim( $latest_sermon->post_content ) ) : ?><p style="margin:0 0 16px;color:var(--muted)"><?php echo esc_html( wp_strip_all_tags( $latest_sermon->post_content ) ); ?></p><?php endif; ?>
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

	<section id="bulletin" class="section bulletin-section">
		<div class="bulletin-current">
			<?php if ( $latest_bulletin ) : ?>
				<?php $pdf = get_post_meta( $latest_bulletin->ID, '_ce_pdf', true ); ?>
				<div class="bulletin-heading">
					<p class="eyebrow"><?php echo esc_html( sprintf( __( 'Bulletin · %s', 'calvary-ellicott' ), ce_long_date( $latest_bulletin ) ) ); ?></p>
					<?php echo ce_pdf_link( $pdf, __( 'Open PDF', 'calvary-ellicott' ), 'button primary' ); ?>
				</div>
				<iframe src="<?php echo esc_url( $pdf ); ?>#view=FitH" title="<?php echo esc_attr( sprintf( __( 'Bulletin for %s', 'calvary-ellicott' ), ce_long_date( $latest_bulletin ) ) ); ?>" loading="lazy"></iframe>
			<?php else : ?>
				<div class="calendar-placeholder"><span class="calendar-icon" aria-hidden="true">CE</span><h3><?php echo esc_html( ce_opt( 'bulletin_empty_title' ) ); ?></h3><p><?php echo esc_html( ce_opt( 'bulletin_empty_text' ) ); ?></p></div>
			<?php endif; ?>
		</div>
		<aside class="bulletin-archive">
			<h2><?php esc_html_e( 'Previous bulletins', 'calvary-ellicott' ); ?></h2>
			<?php if ( $bulletins ) : ?>
				<ul>
					<?php foreach ( $bulletins as $b ) : ?>
						<li><?php echo ce_pdf_link( get_post_meta( $b->ID, '_ce_pdf', true ), ce_long_date( $b ) ); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php else : ?>
				<p><?php esc_html_e( 'Older bulletins will be listed here.', 'calvary-ellicott' ); ?></p>
			<?php endif; ?>
		</aside>
	</section>
	<?php
	ce_next_steps( __( 'Keep going', 'calvary-ellicott' ), __( 'Get to know us, or plan a visit.', 'calvary-ellicott' ), array( 'page-templates/discover.php', 'page-templates/visit.php' ) );
endwhile;
get_footer();
