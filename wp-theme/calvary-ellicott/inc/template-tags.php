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
 * Church Connect (ChurchTrac) address. Everything people do, from forms to events to
 * giving, lives there. $key picks a deeper link (events, groups, serve, give, forms) if
 * one is set in the Customizer; otherwise the main Church Connect address is used.
 */
function ce_connect_url( $key = '' ) {
	if ( $key ) {
		$specific = ce_https_url( ce_opt( 'connect_' . $key . '_url' ) );
		if ( $specific ) {
			return $specific;
		}
	}
	return ce_https_url( ce_opt( 'connect_url' ) );
}

/**
 * The brand block in the header and footer: the chosen logo, or the "CE" mark with the name.
 */
function ce_brand( $class = '' ) {
	$logo = ce_https_url( ce_opt( 'site_logo' ) );
	printf( '<a class="brand%s" href="%s" aria-label="%s">', $class ? ' ' . esc_attr( $class ) : '', esc_url( home_url( '/' ) ), esc_attr( get_bloginfo( 'name' ) . ' ' . __( 'home', 'calvary-ellicott' ) ) );
	if ( $logo ) {
		printf( '<img class="brand-logo" src="%s" alt="" />', $logo );
	} else {
		echo '<span class="brand-mark" aria-hidden="true">CE</span><span><strong>Calvary</strong><small>Ellicott</small></span>';
	}
	echo '</a>';
}

/**
 * The photo panel used by the story-style sections. Shows the chosen picture, or a
 * branded placeholder when none is set, instead of hotlinking a stock photo.
 */
function ce_story_image( $key, $alt = '' ) {
	$url = ce_https_url( ce_opt( $key ) );
	if ( $url ) {
		printf( '<div class="story-image has-photo"><img src="%s" alt="%s" loading="lazy" /></div>', $url, esc_attr( $alt ) );
	} else {
		echo '<div class="story-image" aria-hidden="true"><span class="calendar-icon">CE</span></div>';
	}
}

/**
 * Google Maps directions link for the church address.
 */
function ce_maps_url() {
	$address = ce_opt( 'address_1' ) . ' ' . ce_opt( 'address_2' );
	return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( $address );
}

/**
 * Embeddable map. A Customizer value wins (paste the src from Google Maps → Share → Embed a map);
 * otherwise the address is used directly, which needs no API key.
 */
function ce_map_embed_url() {
	$custom = ce_https_url( ce_opt( 'map_embed' ) );
	if ( $custom ) {
		return $custom;
	}
	$address = ce_opt( 'address_1' ) . ', ' . ce_opt( 'address_2' );
	return 'https://www.google.com/maps?q=' . rawurlencode( $address ) . '&output=embed';
}

/**
 * Find the page that uses a given theme template, e.g. "page-templates/visit.php".
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
 * A link into Church Connect, opened in a new tab. If no address is configured at all,
 * the link points at the Visit page so nothing on the site dead-ends.
 */
function ce_connect_link( $key, $label, $class = '', $arrow = true, $label_key = '' ) {
	$url  = ce_connect_url( $key );
	$text = ( $label_key ? ce_opt_span( $label_key ) : esc_html( $label ) ) . ( $arrow ? ' <span>→</span>' : '' );
	if ( $url ) {
		return sprintf( '<a class="%s" href="%s" target="_blank" rel="noopener noreferrer">%s</a>', esc_attr( $class ), $url, $text );
	}
	return sprintf( '<a class="%s" href="%s">%s</a>', esc_attr( $class ), esc_url( ce_template_url( 'page-templates/visit.php' ) ), $text );
}

/**
 * Page banner used by every inner page: eyebrow (meta), title, and the excerpt as the lead.
 */
function ce_page_hero( $post = null ) {
	$post    = get_post( $post );
	$eyebrow = get_post_meta( $post->ID, '_ce_eyebrow', true );
	$lead    = has_excerpt( $post ) ? get_the_excerpt( $post ) : '';
	$editing = ce_editing(); // empty lines still render then, so they can be filled in
	echo '<section class="page-hero">';
	if ( $eyebrow || $editing ) {
		echo '<p class="eyebrow"' . ce_meta_attr( $post->ID, '_ce_eyebrow' ) . '>' . esc_html( $eyebrow ) . '</p>';
	}
	echo '<h1' . ce_post_attr( $post->ID, 'title' ) . '>' . esc_html( get_the_title( $post ) ) . '</h1>';
	if ( $lead || $editing ) {
		echo '<p' . ce_post_attr( $post->ID, 'excerpt' ) . '>' . esc_html( $lead ) . '</p>';
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

/**
 * The same line as HTML, with each part editable in edit mode.
 */
function ce_service_line_html() {
	return ce_opt_span( 'service_day' ) . ' at ' . ce_opt_span( 'service_time' );
}

/**
 * The two address lines, each editable in edit mode.
 */
function ce_address_html( $separator = '<br />' ) {
	return ce_opt_span( 'address_1' ) . $separator . ce_opt_span( 'address_2' );
}

function ce_details_card( $rows ) {
	echo '<div class="details-card">';
	foreach ( array_values( $rows ) as $i => $row ) {
		printf(
			'<div><span class="detail-number">%02d</span><p><strong>%s</strong>%s</p></div>',
			$i + 1,
			esc_html( $row[0] ),
			wp_kses( $row[1], array( 'br' => array(), 'span' => array( 'data-ce-edit' => true, 'data-ce-format' => true ) ) )
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
	$message  = $alert->post_content ? wp_strip_all_tags( $alert->post_content ) : $alert->post_title;
	$editable = $alert->post_content ? '' : ce_post_attr( $alert->ID, 'title' );
	printf( '<div class="site-alert site-alert-%s" role="status" data-alert-id="%d"><p><span%s>%s</span>', esc_attr( $style ), $alert->ID, $editable, esc_html( $message ) );
	if ( $link ) {
		$external = 0 === strpos( $link, 'http' ) && false === strpos( $link, home_url() );
		printf( ' <a href="%s"%s><span%s>%s</span></a>', esc_url( $link ), $external ? ' target="_blank" rel="noopener noreferrer"' : '', ce_meta_attr( $alert->ID, '_ce_link_text' ), esc_html( $label ? $label : __( 'Learn more', 'calvary-ellicott' ) ) );
	}
	echo '</p><button type="button" class="site-alert-close" aria-label="' . esc_attr__( 'Dismiss message', 'calvary-ellicott' ) . '">×</button></div>';
}

/**
 * Fallback navigation when no menu has been assigned yet: the standard pages in order.
 */
function ce_nav_fallback( $args ) {
	$order = array(
		'page-templates/discover.php' => __( 'Discover', 'calvary-ellicott' ),
		'page-templates/visit.php'    => __( 'Visit', 'calvary-ellicott' ),
		'page-templates/learn.php'    => __( 'Learn', 'calvary-ellicott' ),
		'page-templates/events.php'   => __( 'Events', 'calvary-ellicott' ),
	);
	foreach ( $order as $template => $label ) {
		$id = ce_page_by_template( $template );
		if ( $id ) {
			printf( '<a href="%s"%s>%s</a>', esc_url( get_permalink( $id ) ), is_page( $id ) ? ' aria-current="page"' : '', esc_html( get_the_title( $id ) ) );
		}
	}
	$announcements = ce_https_url( ce_opt( 'connect_announcements_url' ) );
	if ( $announcements ) {
		printf( '<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>', $announcements, esc_html( ce_opt( 'announcements_label' ) ) );
	}
	$connect = ce_connect_url();
	if ( $connect ) {
		$class = ! empty( $args['ce_cta_last'] ) ? ' class="nav-cta"' : '';
		printf( '<a href="%s"%s target="_blank" rel="noopener noreferrer">%s</a>', $connect, $class, esc_html( ce_opt( 'connect_label' ) ) );
	}
}

/**
 * Card grid linking to other pages. Uses each page's title and excerpt. The small line and
 * headline are theme settings, named by key.
 */
function ce_next_steps( $eyebrow_key, $headline_key, $templates ) {
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
	echo '<p class="eyebrow"' . ce_edit_attr( 'opt:' . $eyebrow_key ) . '>' . esc_html( ce_opt( $eyebrow_key ) ) . '</p>';
	echo '<h2' . ce_edit_attr( 'opt:' . $headline_key ) . '>' . esc_html( ce_opt( $headline_key ) ) . '</h2></div></div><div class="next-steps">';
	foreach ( $cards as $i => $id ) {
		printf(
			'<a class="next-step" href="%s"><span class="detail-number">%02d</span><h3%s>%s</h3><p%s>%s</p></a>',
			esc_url( get_permalink( $id ) ),
			$i + 1,
			ce_post_attr( $id, 'title' ),
			esc_html( get_the_title( $id ) ),
			ce_post_attr( $id, 'excerpt' ),
			esc_html( has_excerpt( $id ) ? get_the_excerpt( $id ) : '' )
		);
	}
	echo '</div></section>';
}

/**
 * PDF link helper for sermon files.
 */
function ce_pdf_link( $url, $label, $class = '' ) {
	$url = esc_url( $url );
	return $url ? sprintf( '<a class="%s" href="%s" target="_blank" rel="noopener">%s</a>', esc_attr( $class ), $url, esc_html( $label ) ) : '';
}
