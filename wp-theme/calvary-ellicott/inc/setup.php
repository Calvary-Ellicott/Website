<?php
/**
 * One-click starter content: Appearance → Site setup.
 * Creates Home, Discover, Visit, and Learn with their templates, sample beliefs and
 * staff, the menu (with a Church Connect link), and sets the homepage. Safe to run
 * more than once; it skips anything that already exists.
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
	echo '<p>This creates the pages (Home, Discover, Visit, Learn, Events), fills in the mission and vision, the statement of faith, and placeholder staff cards, builds the menu with a Church Connect link, and sets the homepage. It skips anything that already exists, so it is safe to run again.</p>';
	echo '<p>Afterwards, open <strong>Site settings</strong> in the left menu to check the service time, address, Church Connect links, and the BoxCast embed.</p>';
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
			'discover',
			'Discover',
			'page-templates/discover.php',
			'Who we are',
			'Our story, what we believe, and the people who serve here.',
			"<!-- wp:paragraph -->\n<p>Our congregation is prayerfully preparing for a new season as a locally led church serving Ellicott and the surrounding plains. While our name and organizational relationship may be changing, our foundation is not: Jesus Christ, the authority of Scripture, and the call to make disciples.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p>We’re grateful for our history with Rocky Mountain Calvary and hopeful about what God is building here. We’ll share formal updates with clarity as decisions are finalized.</p>\n<!-- /wp:paragraph -->",
			1,
		),
		array(
			'visit',
			'Visit',
			'page-templates/visit.php',
			'Plan your Sunday',
			'When we meet, where to find us, and what to expect on your first visit.',
			"<!-- wp:paragraph -->\n<p>Whether church is familiar or completely new, we want your first visit to feel easy. Expect genuine people, worship, age-appropriate care for children, and practical teaching through the Bible. Come as you are; there is no dress code and no pressure.</p>\n<!-- /wp:paragraph -->",
			2,
		),
		array( 'events', 'Events', 'page-templates/events.php', 'Life together', 'Sunday worship, studies, and gatherings for every generation. Registration happens in Church Connect.', '', 3 ),
		array(
			'learn',
			'Learn',
			'page-templates/learn.php',
			'Grow with us',
			'Watch the livestream, follow along with sermon notes, and catch up on the weekly bulletin.',
			"<!-- wp:paragraph -->\n<p>Download this week’s handout to follow along, and check back after the service for the pastor’s full notes.</p>\n<!-- /wp:paragraph -->",
			4,
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

	if ( ! get_posts( array( 'post_type' => 'ce_mission', 'posts_per_page' => 1, 'post_status' => 'any', 'fields' => 'ids' ) ) ) {
		$mission = array(
			array( 'Our mission', 'To know Jesus and make Him known: worshiping God, growing together in His Word, and serving Ellicott and the plains with His love.' ),
			array( 'Our vision', 'A church family where every person is welcomed, discipled, and sent to love their neighbors.' ),
			array( 'Our values', 'The authority of Scripture, grace-filled community, faithful teaching, and generous service.' ),
		);
		foreach ( $mission as $i => $m ) {
			wp_insert_post( array( 'post_type' => 'ce_mission', 'post_status' => 'publish', 'post_title' => $m[0], 'post_content' => $m[1], 'menu_order' => $i + 1 ) );
		}
		$done[] = 'Added mission, vision, and values placeholders';
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
			array( 'Dr. Dan Hooker', 'Pastor', 'A short paragraph about Pastor Dan, his family, and his heart for the Ellicott community.' ),
			array( 'Don Drury', 'Assistant Pastor', 'A short paragraph about Pastor Don and the areas of ministry he serves.' ),
			array( 'Staff member name', 'Role or ministry', 'A short paragraph about this staff member and the ministry area they serve.' ),
		);
		foreach ( $staff as $i => $s ) {
			$id = wp_insert_post( array( 'post_type' => 'ce_staff', 'post_status' => 'publish', 'post_title' => $s[0], 'post_content' => $s[2], 'menu_order' => $i + 1 ) );
			update_post_meta( $id, '_ce_role', $s[1] );
		}
		$done[] = 'Added three staff cards (two named, one placeholder)';
	}

	if ( ! has_nav_menu( 'primary' ) ) {
		$menu_id = wp_create_nav_menu( 'Primary' );
		if ( ! is_wp_error( $menu_id ) ) {
			foreach ( array( 'discover', 'visit', 'learn', 'events' ) as $i => $slug ) {
				wp_update_nav_menu_item(
					$menu_id,
					0,
					array(
						'menu-item-object-id' => $ids[ $slug ],
						'menu-item-object'    => 'page',
						'menu-item-type'      => 'post_type',
						'menu-item-status'    => 'publish',
						'menu-item-position'  => $i + 1,
					)
				);
			}
			wp_update_nav_menu_item(
				$menu_id,
				0,
				array(
					'menu-item-title'    => ce_opt( 'connect_label' ),
					'menu-item-url'      => ce_opt( 'connect_url' ),
					'menu-item-type'     => 'custom',
					'menu-item-status'   => 'publish',
					'menu-item-position' => 5,
					'menu-item-classes'  => 'nav-cta',
					'menu-item-target'   => '_blank',
				)
			);
			$locations            = get_theme_mod( 'nav_menu_locations', array() );
			$locations['primary'] = $menu_id;
			$locations['footer']  = $menu_id;
			set_theme_mod( 'nav_menu_locations', $locations );
			$done[] = 'Built the navigation menu (Discover, Visit, Learn, Events, Church Connect)';
		}
	}

	if ( ! $done ) {
		$done[] = 'Nothing to do. Everything already exists.';
	}
	flush_rewrite_rules();
	return $done;
}
