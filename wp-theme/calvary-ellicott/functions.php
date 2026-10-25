<?php
/**
 * Calvary Ellicott theme bootstrap.
 *
 * The theme keeps the hand-built design of the static site and adds WordPress
 * editing on top: pages for copy, custom post types for staff, beliefs,
 * mission statements, sermons and alerts, and site settings for details,
 * ChurchTrac links and the BoxCast embed.
 */

defined( 'ABSPATH' ) || exit;

define( 'CE_THEME_VERSION', wp_get_theme( get_template() )->get( 'Version' ) ?: '1.3.1' );
define( 'CE_THEME_DIR', get_template_directory() );
define( 'CE_THEME_URI', get_template_directory_uri() );

require CE_THEME_DIR . '/inc/template-tags.php';
require CE_THEME_DIR . '/inc/post-types.php';
require CE_THEME_DIR . '/inc/meta-boxes.php';
require CE_THEME_DIR . '/inc/customizer.php';
require CE_THEME_DIR . '/inc/setup.php';
require CE_THEME_DIR . '/inc/site-settings.php';
require CE_THEME_DIR . '/inc/inline-edit.php';

/**
 * Theme supports, menus, image sizes.
 */
function ce_theme_setup() {
	load_theme_textdomain( 'calvary-ellicott', CE_THEME_DIR . '/languages' );
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' ) );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'editor-styles' );
	add_theme_support( 'align-wide' );
	add_post_type_support( 'page', 'excerpt' );

	add_image_size( 'ce-portrait', 1200, 900, true );
	add_image_size( 'ce-hero', 1900, 1200, true );

	register_nav_menus(
		array(
			'primary' => __( 'Primary navigation', 'calvary-ellicott' ),
			'footer'  => __( 'Footer navigation', 'calvary-ellicott' ),
		)
	);
}
add_action( 'after_setup_theme', 'ce_theme_setup' );

/**
 * Front-end styles and scripts.
 */
function ce_enqueue_assets() {
	wp_enqueue_style(
		'ce-fonts',
		'https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Newsreader:opsz,wght@6..72,500;6..72,600&display=swap',
		array(),
		null
	);
	wp_enqueue_style( 'ce-style', get_stylesheet_uri(), array( 'ce-fonts' ), CE_THEME_VERSION );
	wp_enqueue_script( 'ce-site', CE_THEME_URI . '/assets/site.js', array(), CE_THEME_VERSION, true );
	wp_localize_script( 'ce-site', 'CE_HERO', array( 'interval' => max( 3, (int) ce_opt( 'hero_interval' ) ) ) );
}
add_action( 'wp_enqueue_scripts', 'ce_enqueue_assets' );

/**
 * Preconnect hints for Google Fonts.
 */
function ce_resource_hints( $urls, $relation_type ) {
	if ( 'preconnect' === $relation_type ) {
		$urls[] = array( 'href' => 'https://fonts.googleapis.com' );
		$urls[] = array( 'href' => 'https://fonts.gstatic.com', 'crossorigin' => 'anonymous' );
	}
	return $urls;
}
add_filter( 'wp_resource_hints', 'ce_resource_hints', 10, 2 );

/**
 * Theme colour and load the theme stylesheet in the block editor too.
 */
function ce_editor_styles() {
	add_editor_style( 'style.css' );
}
add_action( 'admin_init', 'ce_editor_styles' );

function ce_theme_color_meta() {
	echo '<meta name="theme-color" content="#182820" />' . "\n";
}
add_action( 'wp_head', 'ce_theme_color_meta', 1 );

/**
 * Body classes the stylesheet expects.
 */
function ce_body_class( $classes ) {
	$classes[] = is_front_page() ? 'ce-home' : 'ce-inner';
	return $classes;
}
add_filter( 'body_class', 'ce_body_class' );

/**
 * Menu item classes: mark the Connect item as the call-to-action button.
 */
function ce_nav_menu_link_attributes( $atts, $item, $args ) {
	if ( isset( $args->theme_location ) && 'primary' === $args->theme_location ) {
		$is_last = ! empty( $args->ce_last_id ) && (int) $args->ce_last_id === (int) $item->ID;
		if ( $is_last || in_array( 'nav-cta', (array) $item->classes, true ) ) {
			$atts['class'] = trim( ( $atts['class'] ?? '' ) . ' nav-cta' );
		}
	}
	return $atts;
}
add_filter( 'nav_menu_link_attributes', 'ce_nav_menu_link_attributes', 10, 3 );

/**
 * Excerpt tweaks: no "[...]" and a sensible length for hero leads and cards.
 */
function ce_excerpt_more() {
	return '…';
}
add_filter( 'excerpt_more', 'ce_excerpt_more' );

/**
 * Sermons are listed by their own date, so order them newest first everywhere.
 */
function ce_dated_post_types_order( $query ) {
	if ( is_admin() && $query->is_main_query() && 'ce_sermon' === $query->get( 'post_type' ) && ! $query->get( 'orderby' ) ) {
		$query->set( 'orderby', 'date' );
		$query->set( 'order', 'DESC' );
	}
}
add_action( 'pre_get_posts', 'ce_dated_post_types_order' );
