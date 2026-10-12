<?php
/**
 * Customizer: site details, homepage copy, ChurchTrac links, BoxCast embed, staff invite card.
 * Appearance → Customize → Calvary Ellicott.
 */

defined( 'ABSPATH' ) || exit;

function ce_customizer_defaults() {
	return array(
		'service_day'            => 'Sunday',
		'service_time'           => '9:30 AM',
		'address_1'              => '2150 N. Ellicott Hwy',
		'address_2'              => 'Calhan, CO 80808',
		'map_embed'              => '',

		'hero_eyebrow'           => 'A church family in Ellicott, Colorado',
		'hero_line1'             => 'Rooted in truth.',
		'hero_line2'             => 'Growing in grace.',
		'hero_intro'             => 'Come as you are and join us as we study God’s Word, follow Jesus, and love our neighbors—together.',
		'hero_primary_label'     => 'Plan your visit',
		'hero_secondary_label'   => 'Get to know us',
		'hero_image'             => '',
		'visit_eyebrow'          => 'You’re welcome here',
		'visit_headline'         => "A simple Sunday.\nA meaningful community.",
		'visit_lead'             => 'Whether church is familiar or completely new, we want your first visit to feel easy. Expect genuine people, worship, age-appropriate care for children, and practical teaching through the Bible.',
		'visit_link_label'       => 'Tell us you’re coming',
		'visit_expect'           => 'Biblical teaching, worship, and a relaxed atmosphere.',
		'story_eyebrow'          => 'Our next chapter',
		'story_headline'         => 'Calvary Ellicott',
		'story_text'             => 'Our congregation is prayerfully preparing for a new season as a locally led church serving Ellicott and the surrounding plains. Our foundation is not changing: Jesus Christ, the authority of Scripture, and the call to make disciples.',
		'story_link_label'       => 'Read our story',
		'story_image'            => '',
		'next_eyebrow'           => 'Take a look around',
		'next_headline'          => 'Discover, visit, learn.',

		'connect_url'            => 'https://connect.calvaryellicott.org',
		'connect_label'          => 'Church Connect',
		'connect_events_url'     => '',
		'connect_groups_url'     => '',
		'connect_serve_url'      => '',
		'connect_give_url'       => '',
		'connect_forms_url'      => '',
		'connect_eyebrow'        => 'Church Connect',
		'connect_headline'       => 'Events, groups, giving, and more.',
		'connect_text'           => 'Church Connect is where you register for events, join a group, sign up to serve, give, and keep your family’s information up to date. Create an account once and everything is in one place.',
		'connect_button'         => 'Open Church Connect',

		'beliefs_eyebrow'        => 'What we believe',
		'beliefs_headline'       => 'Historic faith. Everyday hope.',
		'beliefs_text'           => 'We hold to the historic Christian faith and seek to live it with conviction, humility, and love.',

		'boxcast_embed'          => '',
		'boxcast_embed_code'     => '',
		'watch_live_eyebrow'     => 'Livestream',
		'watch_live_text'        => 'The stream starts a few minutes before the service. Past services are available in the player.',
		'watch_notes_eyebrow'    => 'Follow along',
		'watch_notes_headline'   => 'Sermon notes',
		'watch_notes_pending'    => 'Pastor’s notes will be posted after the service.',
		'watch_notes_empty'      => 'This week’s notes will be posted soon',

		'invite_eyebrow'         => 'Need to talk?',
		'invite_headline'        => 'We’d love to hear from you.',
		'invite_text'            => 'Questions, prayer needs, and conversations are always welcome.',
		'invite_link_label'      => 'Contact our team',

		'bulletin_empty_title'   => 'This week’s bulletin will be posted soon',
		'bulletin_empty_text'    => 'Check back before Sunday, or pick one up when you arrive.',
		'archive_count'          => 12,
	);
}

function ce_customize_register( $wp_customize ) {
	$d = ce_customizer_defaults();
	$wp_customize->add_panel( 'ce', array( 'title' => 'Calvary Ellicott', 'priority' => 10, 'description' => 'Site details, homepage text, and the ChurchTrac and BoxCast connections.' ) );

	$sections = array(
		'ce_site'    => array( 'Site details', 'Service time and address. Used on the homepage, the Connect page, and the footer.' ),
		'ce_home'    => array( 'Homepage', 'The big banner and the sections under it.' ),
		'ce_church'  => array( 'Church Connect', 'The ChurchTrac Church Connect site. Every form, event, group, serving sign-up, and gift happens there; this site only links to it. Deeper links are optional: leave one blank and its button goes to the main address.' ),
		'ce_watch'   => array( 'Learn page', 'BoxCast embed and the wording around the livestream and sermon notes.' ),
		'ce_staff'   => array( 'Discover page', 'Beliefs intro and the gold invite card at the end of the staff grid.' ),
		'ce_files'   => array( 'Bulletins and sermons', 'Empty-state wording and how many past weeks to list on the Learn page.' ),
	);
	foreach ( $sections as $id => $s ) {
		$wp_customize->add_section( $id, array( 'title' => $s[0], 'description' => $s[1], 'panel' => 'ce' ) );
	}

	$fields = array(
		// key => [section, label, type]
		'service_day'            => array( 'ce_site', 'Service day', 'text' ),
		'service_time'           => array( 'ce_site', 'Service time', 'text' ),
		'address_1'              => array( 'ce_site', 'Address line 1', 'text' ),
		'address_2'              => array( 'ce_site', 'Address line 2', 'text' ),
		'map_embed'              => array( 'ce_site', 'Map embed address (optional). Leave blank to map the address above; or in Google Maps use Share, Embed a map, and paste the address inside src="..."', 'url' ),

		'hero_eyebrow'           => array( 'ce_home', 'Small line above the headline', 'text' ),
		'hero_line1'             => array( 'ce_home', 'Headline, first line', 'text' ),
		'hero_line2'             => array( 'ce_home', 'Headline, second line (gold italic)', 'text' ),
		'hero_intro'             => array( 'ce_home', 'Intro sentence', 'textarea' ),
		'hero_primary_label'     => array( 'ce_home', 'Red button label (goes to Visit)', 'text' ),
		'hero_secondary_label'   => array( 'ce_home', 'Cream button label (goes to Discover)', 'text' ),
		'hero_image'             => array( 'ce_home', 'Banner photo (wide landscape)', 'image' ),
		'visit_eyebrow'          => array( 'ce_home', 'Visit section: small line', 'text' ),
		'visit_headline'         => array( 'ce_home', 'Visit section: headline (one line per row)', 'textarea' ),
		'visit_lead'             => array( 'ce_home', 'Visit section: paragraph', 'textarea' ),
		'visit_link_label'       => array( 'ce_home', 'Visit section: link label (goes to Visit)', 'text' ),
		'visit_expect'           => array( 'ce_home', 'Visit section: "What to expect" line', 'text' ),
		'story_eyebrow'          => array( 'ce_home', 'Story section: small line', 'text' ),
		'story_headline'         => array( 'ce_home', 'Story section: headline', 'text' ),
		'story_text'             => array( 'ce_home', 'Story section: paragraph', 'textarea' ),
		'story_link_label'       => array( 'ce_home', 'Story section: link label (goes to Discover)', 'text' ),
		'story_image'            => array( 'ce_home', 'Story section photo', 'image' ),
		'next_eyebrow'           => array( 'ce_home', 'Cards section: small line', 'text' ),
		'next_headline'          => array( 'ce_home', 'Cards section: headline', 'text' ),

		'connect_url'            => array( 'ce_church', 'Church Connect address (main)', 'url' ),
		'connect_label'          => array( 'ce_church', 'Menu label for it', 'text' ),
		'connect_events_url'     => array( 'ce_church', 'Events page in Church Connect (optional)', 'url' ),
		'connect_groups_url'     => array( 'ce_church', 'Groups page in Church Connect (optional)', 'url' ),
		'connect_serve_url'      => array( 'ce_church', 'Serving / volunteer sign-up (optional)', 'url' ),
		'connect_give_url'       => array( 'ce_church', 'Giving page (optional)', 'url' ),
		'connect_forms_url'      => array( 'ce_church', 'Forms, such as plan a visit or prayer request (optional)', 'url' ),
		'connect_eyebrow'        => array( 'ce_church', 'Homepage band: small line', 'text' ),
		'connect_headline'       => array( 'ce_church', 'Homepage band: headline', 'text' ),
		'connect_text'           => array( 'ce_church', 'Homepage band: paragraph', 'textarea' ),
		'connect_button'         => array( 'ce_church', 'Homepage band: button label', 'text' ),

		'boxcast_embed'          => array( 'ce_watch', 'BoxCast embed URL (the src="…" address from the embed code)', 'url' ),
		'boxcast_embed_code'     => array( 'ce_watch', 'Or paste BoxCast’s whole embed code here (used instead of the address above if filled in)', 'textarea' ),
		'watch_live_eyebrow'     => array( 'ce_watch', 'Livestream: small line', 'text' ),
		'watch_live_text'        => array( 'ce_watch', 'Livestream: paragraph', 'textarea' ),
		'watch_notes_eyebrow'    => array( 'ce_watch', 'Sermon notes: small line', 'text' ),
		'watch_notes_headline'   => array( 'ce_watch', 'Sermon notes: headline', 'text' ),
		'watch_notes_pending'    => array( 'ce_watch', 'Shown when only the handout is up', 'text' ),
		'watch_notes_empty'      => array( 'ce_watch', 'Shown when there are no sermons yet', 'text' ),

		'beliefs_eyebrow'        => array( 'ce_staff', 'Beliefs: small line', 'text' ),
		'beliefs_headline'       => array( 'ce_staff', 'Beliefs: headline', 'text' ),
		'beliefs_text'           => array( 'ce_staff', 'Beliefs: intro sentence', 'textarea' ),
		'invite_eyebrow'         => array( 'ce_staff', 'Invite card: small line', 'text' ),
		'invite_headline'        => array( 'ce_staff', 'Headline', 'text' ),
		'invite_text'            => array( 'ce_staff', 'Text', 'textarea' ),
		'invite_link_label'      => array( 'ce_staff', 'Invite card: link label (opens Church Connect forms)', 'text' ),

		'bulletin_empty_title'   => array( 'ce_files', 'Bulletins: title when nothing is posted', 'text' ),
		'bulletin_empty_text'    => array( 'ce_files', 'Bulletins: text when nothing is posted', 'textarea' ),
		'archive_count'          => array( 'ce_files', 'How many past weeks to list', 'number' ),
	);

	foreach ( $fields as $key => $f ) {
		list( $section, $label, $type ) = $f;
		$sanitize = 'sanitize_text_field';
		if ( 'boxcast_embed_code' === $key ) {
			$sanitize = 'ce_sanitize_embed_code';
		} elseif ( 'textarea' === $type ) {
			$sanitize = 'sanitize_textarea_field';
		} elseif ( 'url' === $type ) {
			$sanitize = 'ce_sanitize_https';
		} elseif ( 'number' === $type ) {
			$sanitize = 'absint';
		} elseif ( 'image' === $type ) {
			$sanitize = 'esc_url_raw';
		}
		$wp_customize->add_setting( $key, array( 'default' => $d[ $key ], 'sanitize_callback' => $sanitize, 'transport' => 'refresh' ) );
		if ( 'image' === $type ) {
			$wp_customize->add_control( new WP_Customize_Image_Control( $wp_customize, $key, array( 'label' => $label, 'section' => $section ) ) );
		} else {
			$wp_customize->add_control( $key, array( 'label' => $label, 'section' => $section, 'type' => $type ) );
		}
	}
}
add_action( 'customize_register', 'ce_customize_register' );

function ce_sanitize_https( $value ) {
	$value = trim( (string) $value );
	return preg_match( '#^https://#i', $value ) ? esc_url_raw( $value ) : '';
}

function ce_sanitize_embed_code( $value ) {
	return current_user_can( 'unfiltered_html' ) ? trim( (string) $value ) : '';
}
