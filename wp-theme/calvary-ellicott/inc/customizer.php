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
		'next_headline'          => 'Find your next step.',

		'churchtrac_calendarEmbed' => '',
		'churchtrac_calendar'    => '',
		'churchtrac_visit'       => '',
		'churchtrac_prayer'      => '',
		'churchtrac_connect'     => '',
		'churchtrac_serve'       => '',
		'churchtrac_contact'     => '',
		'connect_note'           => 'ChurchTrac forms are being connected. Check back soon or visit us Sunday.',

		'boxcast_embed'          => '',
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
		'ce_church'  => array( 'ChurchTrac links', 'Paste the public links from ChurchTrac. Each must start with https://. Leave blank to show a "coming soon" note.' ),
		'ce_watch'   => array( 'Watch page', 'BoxCast embed and the wording around the livestream and sermon notes.' ),
		'ce_staff'   => array( 'Staff page invite card', 'The gold card at the end of the staff grid.' ),
		'ce_files'   => array( 'Bulletin and archive', 'Empty-state wording and how many past weeks to list.' ),
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

		'hero_eyebrow'           => array( 'ce_home', 'Small line above the headline', 'text' ),
		'hero_line1'             => array( 'ce_home', 'Headline, first line', 'text' ),
		'hero_line2'             => array( 'ce_home', 'Headline, second line (gold italic)', 'text' ),
		'hero_intro'             => array( 'ce_home', 'Intro sentence', 'textarea' ),
		'hero_primary_label'     => array( 'ce_home', 'Red button label (scrolls to visit details)', 'text' ),
		'hero_secondary_label'   => array( 'ce_home', 'Cream button label (goes to About)', 'text' ),
		'hero_image'             => array( 'ce_home', 'Banner photo (wide landscape)', 'image' ),
		'visit_eyebrow'          => array( 'ce_home', 'Visit section: small line', 'text' ),
		'visit_headline'         => array( 'ce_home', 'Visit section: headline (one line per row)', 'textarea' ),
		'visit_lead'             => array( 'ce_home', 'Visit section: paragraph', 'textarea' ),
		'visit_link_label'       => array( 'ce_home', 'Visit section: link label (opens the ChurchTrac visit form)', 'text' ),
		'visit_expect'           => array( 'ce_home', 'Visit section: "What to expect" line', 'text' ),
		'story_eyebrow'          => array( 'ce_home', 'Story section: small line', 'text' ),
		'story_headline'         => array( 'ce_home', 'Story section: headline', 'text' ),
		'story_text'             => array( 'ce_home', 'Story section: paragraph', 'textarea' ),
		'story_link_label'       => array( 'ce_home', 'Story section: link label (goes to About)', 'text' ),
		'story_image'            => array( 'ce_home', 'Story section photo', 'image' ),
		'next_eyebrow'           => array( 'ce_home', 'Cards section: small line', 'text' ),
		'next_headline'          => array( 'ce_home', 'Cards section: headline', 'text' ),

		'churchtrac_calendarEmbed' => array( 'ce_church', 'Calendar embed URL (shown inside the Events page)', 'url' ),
		'churchtrac_calendar'    => array( 'ce_church', 'Full calendar page URL', 'url' ),
		'churchtrac_visit'       => array( 'ce_church', 'Plan a visit form', 'url' ),
		'churchtrac_prayer'      => array( 'ce_church', 'Prayer request form', 'url' ),
		'churchtrac_connect'     => array( 'ce_church', 'Connect / new here form', 'url' ),
		'churchtrac_serve'       => array( 'ce_church', 'Volunteer / start serving form', 'url' ),
		'churchtrac_contact'     => array( 'ce_church', 'General contact form', 'url' ),
		'connect_note'           => array( 'ce_church', 'Note shown under the form links while links are missing', 'textarea' ),

		'boxcast_embed'          => array( 'ce_watch', 'BoxCast embed URL (the src="…" address from the embed code)', 'url' ),
		'watch_live_eyebrow'     => array( 'ce_watch', 'Livestream: small line', 'text' ),
		'watch_live_text'        => array( 'ce_watch', 'Livestream: paragraph', 'textarea' ),
		'watch_notes_eyebrow'    => array( 'ce_watch', 'Sermon notes: small line', 'text' ),
		'watch_notes_headline'   => array( 'ce_watch', 'Sermon notes: headline', 'text' ),
		'watch_notes_pending'    => array( 'ce_watch', 'Shown when only the handout is up', 'text' ),
		'watch_notes_empty'      => array( 'ce_watch', 'Shown when there are no sermons yet', 'text' ),

		'invite_eyebrow'         => array( 'ce_staff', 'Small line', 'text' ),
		'invite_headline'        => array( 'ce_staff', 'Headline', 'text' ),
		'invite_text'            => array( 'ce_staff', 'Text', 'textarea' ),
		'invite_link_label'      => array( 'ce_staff', 'Link label (opens the ChurchTrac contact form)', 'text' ),

		'bulletin_empty_title'   => array( 'ce_files', 'Bulletin page: title when nothing is posted', 'text' ),
		'bulletin_empty_text'    => array( 'ce_files', 'Bulletin page: text when nothing is posted', 'textarea' ),
		'archive_count'          => array( 'ce_files', 'How many past weeks to list on Bulletin and Watch', 'number' ),
	);

	foreach ( $fields as $key => $f ) {
		list( $section, $label, $type ) = $f;
		$sanitize = 'sanitize_text_field';
		if ( 'textarea' === $type ) {
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
