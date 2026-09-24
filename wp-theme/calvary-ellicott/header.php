<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<?php ce_alert_banner(); ?>
<a class="skip-link" href="#main"><?php esc_html_e( 'Skip to content', 'calvary-ellicott' ); ?></a>
<header class="site-header" data-scrolled="false">
	<a class="brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?> home">
		<span class="brand-mark" aria-hidden="true">CE</span>
		<span><strong>Calvary</strong><small>Ellicott</small></span>
	</a>
	<button class="nav-toggle" type="button" aria-label="<?php esc_attr_e( 'Toggle navigation', 'calvary-ellicott' ); ?>" aria-expanded="false"><span></span><span></span><span></span></button>
	<nav class="nav-links" aria-label="<?php esc_attr_e( 'Primary navigation', 'calvary-ellicott' ); ?>">
		<?php
		$ce_menu_items = has_nav_menu( 'primary' ) ? wp_get_nav_menu_items( get_nav_menu_locations()['primary'] ) : array();
		$ce_last_id    = $ce_menu_items ? end( $ce_menu_items )->ID : 0;
		wp_nav_menu(
			array(
				'theme_location' => 'primary',
				'container'      => false,
				'items_wrap'     => '<ul>%3$s</ul>',
				'depth'          => 1,
				'fallback_cb'    => 'ce_nav_fallback',
				'ce_cta_last'    => true,
				'ce_last_id'     => $ce_last_id,
			)
		);
		?>
	</nav>
</header>

<main id="main">
