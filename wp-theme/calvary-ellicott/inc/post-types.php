<?php
/**
 * Custom post types: staff, beliefs, bulletins, sermons, alerts.
 * None of them get their own public URL; the page templates display them.
 */

defined( 'ABSPATH' ) || exit;

function ce_register_post_types() {
	$common = array(
		'public'              => false,
		'show_ui'             => true,
		'show_in_menu'        => true,
		'show_in_rest'        => true,
		'publicly_queryable'  => false,
		'exclude_from_search' => true,
		'has_archive'         => false,
		'rewrite'             => false,
	);

	register_post_type(
		'ce_staff',
		$common + array(
			'labels'      => ce_labels( 'Staff member', 'Staff' ) + array( 'featured_image' => 'Photo', 'set_featured_image' => 'Add photo', 'remove_featured_image' => 'Remove photo', 'use_featured_image' => 'Use as photo' ),
			'menu_icon'   => 'dashicons-groups',
			'supports'    => array( 'title', 'thumbnail', 'page-attributes' ),
			'description' => 'People shown on the Staff page. Title is the name, the content is the bio, the featured image is the photo.',
		)
	);

	register_post_type(
		'ce_mission',
		$common + array(
			'labels'      => ce_labels( 'Statement', 'Mission & Vision' ),
			'menu_icon'   => 'dashicons-flag',
			'supports'    => array( 'title', 'page-attributes' ),
			'description' => 'Mission, vision, and values statements shown on the Discover page, in menu order.',
		)
	);

	register_post_type(
		'ce_belief',
		$common + array(
			'labels'      => ce_labels( 'Belief', 'Beliefs' ),
			'menu_icon'   => 'dashicons-book-alt',
			'supports'    => array( 'title', 'page-attributes' ),
			'description' => 'Statement of faith items shown on the Beliefs page, in menu order.',
		)
	);

	register_post_type(
		'ce_sermon',
		$common + array(
			'labels'      => ce_labels( 'Sermon', 'Sermons' ),
			'menu_icon'   => 'dashicons-microphone',
			'supports'    => array( 'title' ),
			'description' => 'Weekly sermon handout and pastor notes, shown on the Learn page.',
		)
	);

	register_post_type(
		'ce_alert',
		$common + array(
			'labels'      => ce_labels( 'Alert', 'Alerts' ),
			'menu_icon'   => 'dashicons-megaphone',
			'supports'    => array( 'title' ),
			'description' => 'Site-wide banner. The title is the message. Publish to show it, draft or trash to hide it, or set an expiry date.',
		)
	);
}
add_action( 'init', 'ce_register_post_types' );

function ce_labels( $singular, $plural ) {
	return array(
		'name'               => $plural,
		'singular_name'      => $singular,
		'add_new'            => 'Add new',
		'add_new_item'       => 'Add new ' . strtolower( $singular ),
		'edit_item'          => 'Edit ' . strtolower( $singular ),
		'new_item'           => 'New ' . strtolower( $singular ),
		'view_item'          => 'View ' . strtolower( $singular ),
		'search_items'       => 'Search ' . strtolower( $plural ),
		'not_found'          => 'No ' . strtolower( $plural ) . ' yet.',
		'not_found_in_trash' => 'No ' . strtolower( $plural ) . ' in the trash.',
		'all_items'          => 'All ' . strtolower( $plural ),
		'menu_name'          => $plural,
	);
}

/**
 * Helpful column in the admin lists: the Sunday date and whether files are attached.
 */
function ce_admin_columns( $columns ) {
	$screen = get_current_screen();
	if ( ! $screen ) {
		return $columns;
	}
	if ( 'ce_sermon' === $screen->post_type ) {
		$columns = array( 'cb' => $columns['cb'], 'title' => 'Title', 'ce_sunday' => 'Sunday', 'ce_files' => 'Files' );
	} elseif ( 'ce_alert' === $screen->post_type ) {
		$columns = array( 'cb' => $columns['cb'], 'title' => 'Message', 'ce_status' => 'Showing?', 'ce_expires' => 'Expires' );
	} elseif ( 'ce_staff' === $screen->post_type ) {
		$columns = array( 'cb' => $columns['cb'], 'title' => 'Name', 'ce_role' => 'Role', 'ce_photo' => 'Photo', 'menu_order' => 'Order' );
	}
	return $columns;
}
add_filter( 'manage_ce_sermon_posts_columns', 'ce_admin_columns' );
add_filter( 'manage_ce_alert_posts_columns', 'ce_admin_columns' );
add_filter( 'manage_ce_staff_posts_columns', 'ce_admin_columns' );

function ce_admin_column_content( $column, $post_id ) {
	switch ( $column ) {
		case 'ce_sunday':
			echo esc_html( get_the_date( 'D, M j, Y', $post_id ) );
			break;
		case 'ce_files':
			$parts = array();
			if ( get_post_meta( $post_id, '_ce_handout', true ) ) {
				$parts[] = 'Handout';
			}
			if ( get_post_meta( $post_id, '_ce_notes', true ) ) {
				$parts[] = 'Notes';
			}
			echo $parts ? esc_html( implode( ', ', $parts ) ) : '<span style="color:#b32d2e">none attached</span>';
			break;
		case 'ce_status':
			$active = ce_active_alert();
			echo ( $active && $active->ID === $post_id ) ? '<strong style="color:#00a32a">Yes, live now</strong>' : ( 'publish' === get_post_status( $post_id ) ? 'Published but superseded or expired' : 'No (draft)' );
			break;
		case 'ce_expires':
			echo esc_html( get_post_meta( $post_id, '_ce_expires', true ) ?: '—' );
			break;
		case 'ce_role':
			echo esc_html( get_post_meta( $post_id, '_ce_role', true ) );
			break;
		case 'ce_photo':
			echo has_post_thumbnail( $post_id ) ? get_the_post_thumbnail( $post_id, array( 60, 45 ) ) : '<span style="color:#757575">initials</span>';
			break;
		case 'menu_order':
			echo (int) get_post_field( 'menu_order', $post_id );
			break;
	}
}
add_action( 'manage_ce_sermon_posts_custom_column', 'ce_admin_column_content', 10, 2 );
add_action( 'manage_ce_alert_posts_custom_column', 'ce_admin_column_content', 10, 2 );
add_action( 'manage_ce_staff_posts_custom_column', 'ce_admin_column_content', 10, 2 );
