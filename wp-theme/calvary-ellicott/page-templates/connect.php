<?php
/**
 * Template Name: Connect
 * Links to every ChurchTrac form, plus the address and service time.
 */
get_header();
while ( have_posts() ) :
	the_post();
	ce_page_hero();
	$forms = array(
		'visit'   => __( 'Plan a visit', 'calvary-ellicott' ),
		'prayer'  => __( 'Request prayer', 'calvary-ellicott' ),
		'connect' => __( 'Connect with us', 'calvary-ellicott' ),
		'serve'   => __( 'Start serving', 'calvary-ellicott' ),
		'contact' => __( 'Contact our team', 'calvary-ellicott' ),
	);
	$missing = false;
	foreach ( array_keys( $forms ) as $key ) {
		if ( ! ce_churchtrac_url( $key ) ) {
			$missing = true;
		}
	}
	$soon = isset( $_GET['soon'] ) ? sanitize_text_field( wp_unslash( $_GET['soon'] ) ) : '';
	?>
	<section id="connect" class="section connect-section">
		<div>
			<p class="eyebrow"><?php esc_html_e( 'Get in touch', 'calvary-ellicott' ); ?></p>
			<h2><?php esc_html_e( 'We’d love to', 'calvary-ellicott' ); ?><br /><?php esc_html_e( 'meet you.', 'calvary-ellicott' ); ?></h2>
			<div class="prose"><?php the_content(); ?></div>
			<?php
			ce_details_card(
				array(
					array( 'When', esc_html( ce_service_line() ) ),
					array( 'Where', esc_html( ce_opt( 'address_1' ) ) . '<br />' . esc_html( ce_opt( 'address_2' ) ) ),
				)
			);
			?>
		</div>
		<div>
			<div class="form-links">
				<?php foreach ( $forms as $key => $label ) : ?>
					<?php
					$url = ce_churchtrac_url( $key );
					if ( $url ) {
						printf( '<a href="%s" target="_blank" rel="noopener noreferrer"><span>%s</span><b>→</b></a>', $url, esc_html( $label ) );
					} else {
						printf( '<a href="%s" data-soon="%s"><span>%s</span><b>→</b></a>', esc_url( add_query_arg( 'soon', $label ) . '#connect' ), esc_attr( $label ), esc_html( $label ) );
					}
					?>
				<?php endforeach; ?>
			</div>
			<?php if ( $missing || $soon ) : ?>
				<p class="integration-note" data-integration-status role="status">
					<?php echo $soon ? esc_html( sprintf( __( '%s will open in ChurchTrac once its public form link is added.', 'calvary-ellicott' ), $soon ) ) : esc_html( ce_opt( 'connect_note' ) ); ?>
				</p>
			<?php endif; ?>
		</div>
	</section>
	<?php
endwhile;
get_footer();
