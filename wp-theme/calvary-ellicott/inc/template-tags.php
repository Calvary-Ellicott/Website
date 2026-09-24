<?php
/**
 * Helper functions used by the templates.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Customizer value with a default. Defaults live in inc/customizer.php.
 */
function ce_opt( $key, $default = '' ) {
	$defaults = ce_customizer_defaults();
	if ( '' === $default && isset( $defaults[ $key ] ) ) {
		$default = $defaults[ $key ];
	}
	$value = get_theme_mod( $key, $default );
	return is_string( $value ) ? trim( $value ) : $value;
}

/**
 * Only https URLs are trusted for links and embeds.
 */
function ce_https_url( $url ) {
	$url = trim( (string) $url );
	return preg_match( '#^https://#i', $url ) ? esc_url( $url ) : '';
}

/**
 * Public ChurchTrac URL for a key such as "visit" or "calendarEmbed".
 */
function ce_churchtrac_url( $key ) {
	return ce_https_url( ce_opt( 'churchtrac_' . $key ) );
}

/**
 * Find the page that uses a given theme template, e.g. "page-templates/connect.php".
 */
function ce_page_by_template( $template ) {
	static $cache = array();
	if ( ! isset( $cache[ $template ] ) ) {
		$pages              = get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'meta_key'       => '_wp_page_template',
				'meta_value'     => $template,
				'fields'         => 'ids',
			)
		);
		$cache[ $template ] = $pages ? (int) $pages[0] : 0;
	}
	return $cache[ $template ];
}

function ce_template_url( $template, $fallback = '' ) {
	$id = ce_page_by_template( $template );
	return $id ? get_permalink( $id ) : ( $fallback ? $fallback : home_url( '/' ) );
}

/**
 * A ChurchTrac link. With a URL it opens in a new tab; without one it sends the
 * visitor to the Connect page with a friendly note.
 */
function ce_churchtrac_link( $key, $label, $class = '', $arrow = true ) {
	$url  = ce_churchtrac_url( $key );
	$text = esc_html( $label ) . ( $arrow ? ' <span>→</span>' : '' );
	if ( $url ) {
		return sprintf( '<a class="%s" href="%s" target="_blank" rel="noopener noreferrer">%s</a>', esc_attr( $class ), $url, $text );
	}
	$connect = add_query_arg( 'soon', $label, ce_template_url( 'page-templates/connect.php' ) ) . '#connect';
	return sprintf( '<a class="%s" href="%s">%s</a>', esc_attr( $class ), esc_url( $connect ), $text );
}

/**
 * Page banner used by every inner page: eyebrow (meta), title, and the excerpt as the lead.
 */
function ce_page_hero( $post = null ) {
	$post    = get_post( $post );
	$eyebrow = get_post_meta( $post->ID, '_ce_eyebrow', true );
	$lead    = has_excerpt( $post ) ? get_the_excerpt( $post ) : '';
	echo '<section class="page-hero">';
	if ( $eyebrow ) {
		echo '<p class="eyebrow">' . esc_html( $eyebrow ) . '</p>';
	}
	echo '<h1>' . esc_html( get_the_title( $post ) ) . '</h1>';
	if ( $lead ) {
		echo '<p>' . esc_html( $lead ) . '</p>';
	}
	echo '</section>';
}

/**
 * An iframe embed, or a placeholder box when the URL is not set yet.
 */
function ce_embed( $url, $title, $placeholder_title, $placeholder_text, $allow_media = false ) {
	$url = ce_https_url( $url );
	if ( $url ) {
		$allow = $allow_media ? ' allow="autoplay; fullscreen; picture-in-picture" allowfullscreen' : ' loading="lazy"';
		printf( '<iframe src="%s" title="%s" referrerpolicy="strict-origin-when-cross-origin"%s></iframe>', $url, esc_attr( $title ), $allow );
		return;
	}
	printf(
		'<div class="calendar-placeholder"><span class="calendar-icon" aria-hidden="true">CE</span><h3>%s</h3><p>%s</p></div>',
		esc_html( $placeholder_title ),
		esc_html( $placeholder_text )
	);
}

/**
 * Service details, shared by several templates.
 */
function ce_service_line() {
	return ce_opt( 'service_day' ) . ' at ' . ce_opt( 'service_time' );
}

function ce_details_card( $rows ) {
	echo '<div class="details-card">';
	foreach ( array_values( $rows ) as $i => $row ) {
		printf(
			'<div><span class="detail-number">%02d</span><p><strong>%s</strong>%s</p></div>',
			$i + 1,
			esc_html( $row[0] ),
			wp_kses( $row[1], array( 'br' => array() ) )
		);
	}
	echo '</div>';
}

/**
 * Long date for a post, e.g. "Sunday, September 13, 2026".
 */
function ce_long_date( $post ) {
	return get_the_date( 'l, F j, Y', $post );
}

/**
 * The active alert, if any: newest published ce_alert whose expiry has not passed.
 */
function ce_active_alert() {
	$alerts = get_posts(
		array(
			'post_type'      => 'ce_alert',
			'post_status'    => 'publish',
			'posts_per_page' => 5,
			'orderby'        => 'date',
			'order'          => 'DESC',
		)
	);
	foreach ( $alerts as $alert ) {
		$expires = get_post_meta( $alert->ID, '_ce_expires', true );
		if ( $expires && strtotime( $expires . ' 23:59:59' ) < current_time( 'timestamp' ) ) {
			continue;
		}
		return $alert;
	}
	return null;
}

function ce_alert_banner() {
	$alert = ce_active_alert();
	if ( ! $alert ) {
		return;
	}
	$style   = 'info' === get_post_meta( $alert->ID, '_ce_style', true ) ? 'info' : 'warning';
	$link    = ce_https_url( get_post_meta( $alert->ID, '_ce_link', true ) );
	$page    = (int) get_post_meta( $alert->ID, '_ce_link_page', true );
	if ( ! $link && $page ) {
		$link = get_permalink( $page );
	}
	$label   = get_post_meta( $alert->ID, '_ce_link_text', true );
	$message = $alert->post_content ? wp_strip_all_tags( $alert->post_content ) : $alert->post_title;
	printf( '<div class="site-alert site-alert-%s" role="status" data-alert-id="%d"><p>%s', esc_attr( $style ), $alert->ID, esc_html( $message ) );
	if ( $link ) {
		$external = 0 === strpos( $link, 'http' ) && false === strpos( $link, home_url() );
		printf( ' <a href="%s"%s>%s</a>', esc_url( $link ), $external ? ' target="_blank" rel="noopener noreferrer"' : '', esc_html( $label ? $label : __( 'Learn more', 'calvary-ellicott' ) ) );
	}
	echo '</p><button type="button" class="site-alert-close" aria-label="' . esc_attr__( 'Dismiss message', 'calvary-ellicott' ) . '">×</button></div>';
}

/**
 * Fallback navigation when no menu has been assigned yet: the standard pages in order.
 */
function ce_nav_fallback( $args ) {
	$order = array(
		'page-templates/about.php'    => __( 'About', 'calvary-ellicott' ),
		'page-templates/staff.php'    => __( 'Staff', 'calvary-ellicott' ),
		'page-templates/beliefs.php'  => __( 'Beliefs', 'calvary-ellicott' ),
		'page-templates/events.php'   => __( 'Events', 'calvary-ellicott' ),
		'page-templates/bulletin.php' => __( 'Bulletin', 'calvary-ellicott' ),
		'page-templates/watch.php'    => __( 'Watch', 'calvary-ellicott' ),
		'page-templates/connect.php'  => __( 'Connect', 'calvary-ellicott' ),
	);
	$items = array();
	foreach ( $order as $template => $label ) {
		$id = ce_page_by_template( $template );
		if ( $id ) {
			$items[] = array( 'url' => get_permalink( $id ), 'label' => get_the_title( $id ), 'current' => is_page( $id ) );
		}
	}
	$last = count( $items ) - 1;
	foreach ( $items as $i => $item ) {
		$class = ( $i === $last && ! empty( $args['ce_cta_last'] ) ) ? ' class="nav-cta"' : '';
		$aria  = $item['current'] ? ' aria-current="page"' : '';
		printf( '<a href="%s"%s%s>%s</a>', esc_url( $item['url'] ), $class, $aria, esc_html( $item['label'] ) );
	}
}

/**
 * Card grid linking to other pages. Uses each page's title and excerpt.
 */
function ce_next_steps( $eyebrow, $headline, $templates ) {
	$cards = array();
	foreach ( $templates as $template ) {
		$id = ce_page_by_template( $template );
		if ( $id ) {
			$cards[] = $id;
		}
	}
	if ( ! $cards ) {
		return;
	}
	echo '<section class="section next-steps-section"><div class="section-heading narrow"><div>';
	echo '<p class="eyebrow">' . esc_html( $eyebrow ) . '</p><h2>' . esc_html( $headline ) . '</h2></div></div><div class="next-steps">';
	foreach ( $cards as $i => $id ) {
		printf(
			'<a class="next-step" href="%s"><span class="detail-number">%02d</span><h3>%s</h3><p>%s</p></a>',
			esc_url( get_permalink( $id ) ),
			$i + 1,
			esc_html( get_the_title( $id ) ),
			esc_html( has_excerpt( $id ) ? get_the_excerpt( $id ) : '' )
		);
	}
	echo '</div></section>';
}

/**
 * PDF link helper for bulletins and sermons.
 */
function ce_pdf_link( $url, $label, $class = '' ) {
	$url = esc_url( $url );
	return $url ? sprintf( '<a class="%s" href="%s" target="_blank" rel="noopener">%s</a>', esc_attr( $class ), $url, esc_html( $label ) ) : '';
}
