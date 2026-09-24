<?php
/**
 * One-click starter content: Appearance → Site setup.
 * Creates the standard pages with their templates, sample beliefs and staff,
 * the primary menu, and sets the homepage. Safe to run more than once; it skips
 * anything that already exists.
 */

defined( 'ABSPATH' ) || exit;

function ce_setup_menu() {
	add_theme_page( 'Site setup', 'Site setup', 'manage_options', 'ce-setup', 'ce_setup_page' );
}
add_action( 'admin_menu', 'ce_setup_menu' );

function ce_setup_page() {
	$done = array();
	if ( isset( $_POST['ce_setup'] ) && check_admin_referer( 'ce_setup' ) && current_user_can( 'manage_options' ) ) {
		$done = ce_run_setup();
	}
	echo '<div class="wrap"><h1>Calvary Ellicott site setup</h1>';
	if ( $done ) {
		echo '<div class="notice notice-success"><p>Done:</p><ul style="list-style:disc;margin-left:20px">';
		foreach ( $done as $line ) {
			echo '<li>' . esc_html( $line ) . '</li>';
		}
		echo '</ul></div>';
	}
	echo '<p>This creates the standard pages (Home, About, Staff, Beliefs, Events, Bulletin, Watch, Connect), fills in the statement of faith and two placeholder staff cards, builds the menu, and sets the homepage. It skips anything that already exists, so it is safe to run again.</p>';
	echo '<p>Afterwards, go to <strong>Appearance → Customize → Calvary Ellicott</strong> to enter the service time, address, ChurchTrac links, and the BoxCast embed.</p>';
	echo '<form method="post">';
	wp_nonce_field( 'ce_setup' );
	submit_button( 'Create starter content', 'primary', 'ce_setup' );
	echo '</form></div>';
}

function ce_setup_page_exists( $slug ) {
	$page = get_page_by_path( $slug );
	return $page ? $page->ID : 0;
}

function ce_setup_make_page( $slug, $title, $template, $eyebrow, $excerpt, $content = '', $order = 0 ) {
	$id = ce_setup_page_exists( $slug );
	if ( $id ) {
		return array( $id, false );
	}
	$id = wp_insert_post(
		array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_name'    => $slug,
			'post_title'   => $title,
			'post_excerpt' => $excerpt,
			'post_content' => $content,
			'menu_order'   => $order,
		)
	);
	if ( $template ) {
		update_post_meta( $id, '_wp_page_template', $template );
	}
	if ( $eyebrow ) {
		update_post_meta( $id, '_ce_eyebrow', $eyebrow );
	}
	return array( $id, true );
}

function ce_run_setup() {
	$done  = array();
	$pages = array(
		array( 'home', 'Home', '', '', 'Calvary Ellicott is a church family in Ellicott, Colorado.', '', 0 ),
		array(
			'about',
			'About',
			'page-templates/about.php',
			'About us',
			'A church family rooted in Ellicott, grateful for our history, and hopeful about what God is building here.',
			"<!-- wp:paragraph -->\n<p>Our congregation is prayerfully preparing for a new season as a locally led church serving Ellicott and the surrounding plains. While our name and organizational relationship may be changing, our foundation is not: Jesus Christ, the authority of Scripture, and the call to make disciples.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p>We’re grateful for our history with Rocky Mountain Calvary and hopeful about what God is building here. We’ll share formal updates with clarity as decisions are finalized.</p>\n<!-- /wp:paragraph -->",
			1,
		),
		array( 'staff', 'Staff', 'page-templates/staff.php', 'People who serve', 'Our leaders are here to teach faithfully, care personally, and equip every person for ministry.', '', 2 ),
		array(
			'beliefs',
			'Beliefs',
			'page-templates/beliefs.php',
			'What we believe',
			'We hold to the historic Christian faith and seek to live it with conviction, humility, and love.',
			"<!-- wp:paragraph -->\n<p>These convictions shape how we worship, teach, and care for one another.</p>\n<!-- /wp:paragraph -->",
			3,
		),
		array( 'events', 'Events', 'page-templates/events.php', 'Life together', 'Sunday worship, studies, and gatherings for every generation. Registration happens right on the calendar.', '', 4 ),
		array( 'bulletin', 'Bulletin', 'page-templates/bulletin.php', 'This week', 'Announcements, the order of service, and ways to get involved this week.', '', 5 ),
		array(
			'watch',
			'Watch',
			'page-templates/watch.php',
			'Watch online',
			'Can’t make it in person? Worship with us from home.',
			"<!-- wp:paragraph -->\n<p>Download this week’s handout to follow along, and check back after the service for the pastor’s full notes.</p>\n<!-- /wp:paragraph -->",
			6,
		),
		array(
			'connect',
			'Connect',
			'page-templates/connect.php',
			'Your next step',
			'Plan a visit, ask for prayer, find a group, or learn where you can serve.',
			"<!-- wp:paragraph -->\n<p>Each option opens a secure ChurchTrac form. If you’d rather talk in person, find us Sunday morning.</p>\n<!-- /wp:paragraph -->",
			7,
		),
	);

	$ids = array();
	foreach ( $pages as $p ) {
		list( $id, $created ) = ce_setup_make_page( $p[0], $p[1], $p[2], $p[3], $p[4], $p[5], $p[6] );
		$ids[ $p[0] ]         = $id;
		if ( $created ) {
			$done[] = 'Created page: ' . $p[1];
		}
	}

	if ( 'page' !== get_option( 'show_on_front' ) || (int) get_option( 'page_on_front' ) !== $ids['home'] ) {
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $ids['home'] );
		$done[] = 'Set Home as the homepage';
	}

	if ( ! get_posts( array( 'post_type' => 'ce_belief', 'posts_per_page' => 1, 'post_status' => 'any', 'fields' => 'ids' ) ) ) {
		$beliefs = array(
			array( 'The Bible', 'We believe the Scriptures are inspired by God, trustworthy, and our final authority for faith and life.' ),
			array( 'God', 'We believe in one God—Father, Son, and Holy Spirit—eternal, holy, loving, and sovereign over all.' ),
			array( 'Jesus Christ', 'We believe Jesus is fully God and fully man, lived without sin, died for our sins, rose bodily, and will return.' ),
			array( 'Salvation', 'We believe salvation is God’s gift of grace, received through repentance and faith in Jesus Christ—not earned by our works.' ),
			array( 'The Church', 'We believe the Church is the family of all who follow Jesus, called to worship, grow, serve, and make disciples together.' ),
		);
		foreach ( $beliefs as $i => $b ) {
			wp_insert_post( array( 'post_type' => 'ce_belief', 'post_status' => 'publish', 'post_title' => $b[0], 'post_content' => $b[1], 'menu_order' => $i + 1 ) );
		}
		$done[] = 'Added the five statement-of-faith items';
	}

	if ( ! get_posts( array( 'post_type' => 'ce_staff', 'posts_per_page' => 1, 'post_status' => 'any', 'fields' => 'ids' ) ) ) {
		$staff = array(
			array( 'Pastor name', 'Lead Pastor', 'A short paragraph about the pastor, their family, and their heart for the Ellicott community.' ),
			array( 'Staff member name', 'Worship & Ministry', 'A short paragraph about this staff member and the ministry area they serve.' ),
		);
		foreach ( $staff as $i => $s ) {
			$id = wp_insert_post( array( 'post_type' => 'ce_staff', 'post_status' => 'publish', 'post_title' => $s[0], 'post_content' => $s[2], 'menu_order' => $i + 1 ) );
			update_post_meta( $id, '_ce_role', $s[1] );
		}
		$done[] = 'Added two placeholder staff cards';
	}

	if ( ! has_nav_menu( 'primary' ) ) {
		$menu_id = wp_create_nav_menu( 'Primary' );
		if ( ! is_wp_error( $menu_id ) ) {
			foreach ( array( 'about', 'staff', 'beliefs', 'events', 'bulletin', 'watch', 'connect' ) as $i => $slug ) {
				wp_update_nav_menu_item(
					$menu_id,
					0,
					array(
						'menu-item-object-id' => $ids[ $slug ],
						'menu-item-object'    => 'page',
						'menu-item-type'      => 'post_type',
						'menu-item-status'    => 'publish',
						'menu-item-position'  => $i + 1,
						'menu-item-classes'   => 'connect' === $slug ? 'nav-cta' : '',
					)
				);
			}
			$locations            = get_theme_mod( 'nav_menu_locations', array() );
			$locations['primary'] = $menu_id;
			$locations['footer']  = $menu_id;
			set_theme_mod( 'nav_menu_locations', $locations );
			$done[] = 'Built the navigation menu';
		}
	}

	if ( ! $done ) {
		$done[] = 'Nothing to do. Everything already exists.';
	}
	flush_rewrite_rules();
	return $done;
}
