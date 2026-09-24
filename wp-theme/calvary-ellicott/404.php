<?php
get_header();
?>
<section class="page-hero">
	<p class="eyebrow"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></p>
	<h1><?php esc_html_e( 'We couldn’t find that page.', 'calvary-ellicott' ); ?></h1>
	<p><?php esc_html_e( 'The link may be old or mistyped. Head back to the homepage to plan a visit, see events, or connect with us.', 'calvary-ellicott' ); ?></p>
</section>
<section class="section page-content">
	<a class="button primary" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Back to the homepage', 'calvary-ellicott' ); ?></a>
</section>
<?php
get_footer();
