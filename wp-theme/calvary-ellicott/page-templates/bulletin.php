<?php
/**
 * Template Name: Bulletin
 * Newest Bulletin post with a PDF in a viewer, older ones listed.
 */
get_header();
while ( have_posts() ) :
	the_post();
	ce_page_hero();
	$bulletins = get_posts(
		array(
			'post_type'      => 'ce_bulletin',
			'post_status'    => 'publish',
			'posts_per_page' => max( 1, (int) ce_opt( 'archive_count' ) ) + 1,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'meta_key'       => '_ce_pdf',
			'meta_compare'   => '!=',
			'meta_value'     => '',
		)
	);
	$latest = array_shift( $bulletins );
	?>
	<section id="bulletin" class="section bulletin-section">
		<div class="bulletin-current">
			<?php if ( $latest ) : ?>
				<?php $pdf = get_post_meta( $latest->ID, '_ce_pdf', true ); ?>
				<div class="bulletin-heading">
					<p class="eyebrow"><?php echo esc_html( ce_long_date( $latest ) ); ?></p>
					<?php echo ce_pdf_link( $pdf, __( 'Open PDF', 'calvary-ellicott' ), 'button primary' ); ?>
				</div>
				<iframe src="<?php echo esc_url( $pdf ); ?>#view=FitH" title="<?php echo esc_attr( sprintf( __( 'Bulletin for %s', 'calvary-ellicott' ), ce_long_date( $latest ) ) ); ?>" loading="lazy"></iframe>
			<?php else : ?>
				<div class="calendar-placeholder"><span class="calendar-icon" aria-hidden="true">CE</span><h3><?php echo esc_html( ce_opt( 'bulletin_empty_title' ) ); ?></h3><p><?php echo esc_html( ce_opt( 'bulletin_empty_text' ) ); ?></p></div>
			<?php endif; ?>
		</div>
		<?php if ( $bulletins ) : ?>
			<aside class="bulletin-archive">
				<h2><?php esc_html_e( 'Previous bulletins', 'calvary-ellicott' ); ?></h2>
				<ul>
					<?php foreach ( $bulletins as $b ) : ?>
						<li><?php echo ce_pdf_link( get_post_meta( $b->ID, '_ce_pdf', true ), ce_long_date( $b ) ); ?></li>
					<?php endforeach; ?>
				</ul>
			</aside>
		<?php endif; ?>
	</section>
	<?php
endwhile;
get_footer();
