<?php
/**
 * Site settings: one list of fields used by BOTH the dashboard page (Site settings)
 * and the Customizer (Appearance → Customize → Calvary Ellicott, for live preview).
 * Values are theme mods, so the two screens always agree.
 */

defined( 'ABSPATH' ) || exit;

function ce_field_sections() {
	return array(
		'ce_site'   => array( 'Church details', 'Service time, address, and contact details. Used on the homepage, the Visit page, and the footer.' ),
		'ce_church' => array( 'Church Connect links', 'The ChurchTrac Church Connect site. Every form, event, group, serving sign-up, and gift happens there; this site links to it. Deeper links are optional: leave one blank and its button goes to the main address.' ),
		'ce_home'   => array( 'Homepage', 'The big banner and the sections under it.' ),
		'ce_staff'  => array( 'Discover page', 'Mission and vision intro, beliefs intro, staff section, and the gold invite card.' ),
		'ce_visit'  => array( 'Visit page', 'The photo beside "What a Sunday looks like".' ),
		'ce_events' => array( 'Events page', 'The embedded ChurchTrac calendar.' ),
		'ce_watch'  => array( 'Learn page', 'Livestream, video archive, sermon notes, and the announcements card.' ),
		'ce_files'  => array( 'Sermons', 'How many past weeks to list.' ),
	);
}

/**
 * key => array( section, label, type, extra )  where extra is choices (select) or help text.
 */
function ce_fields() {
	return array(
		'service_day'          => array( 'ce_site', 'Service day', 'text' ),
		'service_time'         => array( 'ce_site', 'Service time', 'text' ),
		'address_1'            => array( 'ce_site', 'Address line 1', 'text' ),
		'address_2'            => array( 'ce_site', 'Address line 2', 'text' ),
		'contact_email'        => array( 'ce_site', 'Church email', 'email', 'Shown in the footer and on the Visit page.' ),
		'phone'                => array( 'ce_site', 'Church phone (optional)', 'text', 'Shown in the footer when filled in.' ),
		'map_embed'            => array( 'ce_site', 'Map embed address (optional)', 'url', 'Leave blank to map the address above. Or in Google Maps use Share → Embed a map and paste the address inside src="…".' ),

		'connect_url'          => array( 'ce_church', 'Church Connect address (main)', 'url' ),
		'connect_label'        => array( 'ce_church', 'Menu label for it', 'text' ),
		'connect_events_url'   => array( 'ce_church', 'Events page (optional)', 'url' ),
		'connect_groups_url'   => array( 'ce_church', 'Groups page (optional)', 'url' ),
		'connect_serve_url'    => array( 'ce_church', 'Serving / volunteer sign-up (optional)', 'url' ),
		'connect_give_url'     => array( 'ce_church', 'Giving page (optional)', 'url' ),
		'connect_forms_url'    => array( 'ce_church', 'Forms, such as plan a visit or prayer request (optional)', 'url' ),
		'connect_announcements_url' => array( 'ce_church', 'Announcements page (optional)', 'url', 'Used by the announcements card on the Learn page.' ),
		'connect_eyebrow'      => array( 'ce_church', 'Homepage band: small line', 'text' ),
		'connect_headline'     => array( 'ce_church', 'Homepage band: headline', 'text' ),
		'connect_text'         => array( 'ce_church', 'Homepage band: paragraph', 'textarea' ),
		'connect_button'       => array( 'ce_church', 'Homepage band: button label', 'text' ),

		'hero_eyebrow'         => array( 'ce_home', 'Small line above the headline', 'text' ),
		'hero_line1'           => array( 'ce_home', 'Headline, first line', 'text' ),
		'hero_line2'           => array( 'ce_home', 'Headline, second line (gold italic)', 'text' ),
		'hero_intro'           => array( 'ce_home', 'Intro sentence', 'textarea' ),
		'hero_primary_label'   => array( 'ce_home', 'Red button label (goes to Visit)', 'text' ),
		'hero_secondary_label' => array( 'ce_home', 'Cream button label (goes to Discover)', 'text' ),
		'hero_images'          => array( 'ce_home', 'Banner photos (the album)', 'gallery', 'Pick as many as you like; the banner fades from one to the next. Wide landscape photos work best. One URL per line in the Customizer.' ),
		'hero_interval'        => array( 'ce_home', 'Seconds between photos', 'number' ),
		'hero_image'           => array( 'ce_home', 'Single banner photo (used only if the album is empty)', 'image' ),
		'visit_eyebrow'        => array( 'ce_home', 'Visit section: small line', 'text' ),
		'visit_headline'       => array( 'ce_home', 'Visit section: headline (one line per row)', 'textarea' ),
		'visit_lead'           => array( 'ce_home', 'Visit section: paragraph', 'textarea' ),
		'visit_link_label'     => array( 'ce_home', 'Visit section: link label (goes to Visit)', 'text' ),
		'visit_expect'         => array( 'ce_home', 'Visit section: "What to expect" line', 'text' ),
		'story_eyebrow'        => array( 'ce_home', 'Story section: small line', 'text' ),
		'story_headline'       => array( 'ce_home', 'Story section: headline', 'text' ),
		'story_text'           => array( 'ce_home', 'Story section: paragraph', 'textarea' ),
		'story_link_label'     => array( 'ce_home', 'Story section: link label (goes to Discover)', 'text' ),
		'story_image'          => array( 'ce_home', 'Story section photo', 'image', 'The photo beside "Our next chapter" on the homepage.' ),
		'next_eyebrow'         => array( 'ce_home', 'Cards section: small line', 'text' ),
		'next_headline'        => array( 'ce_home', 'Cards section: headline', 'text' ),

		'discover_image'       => array( 'ce_staff', 'Story photo', 'image', 'The photo beside the story at the top of the Discover page.' ),
		'mission_eyebrow'      => array( 'ce_staff', 'Mission and vision: small line', 'text' ),
		'mission_headline'     => array( 'ce_staff', 'Mission and vision: headline', 'text' ),
		'mission_text'         => array( 'ce_staff', 'Mission and vision: intro sentence (optional)', 'textarea', 'The statements themselves are entries under Mission & Vision in the dashboard.' ),
		'beliefs_eyebrow'      => array( 'ce_staff', 'Beliefs: small line', 'text' ),
		'beliefs_headline'     => array( 'ce_staff', 'Beliefs: headline', 'text' ),
		'beliefs_text'         => array( 'ce_staff', 'Beliefs: intro sentence', 'textarea' ),
		'staff_mode'           => array( 'ce_staff', 'Staff section comes from', 'select', array( 'grid' => 'WordPress (the Staff screen in the dashboard)', 'link' => 'Church Connect, as a button', 'embed' => 'Church Connect, embedded on the page', 'off' => 'Hide the staff section' ) ),
		'connect_staff_url'    => array( 'ce_staff', 'Church Connect staff page address', 'url' ),
		'staff_link_text'      => array( 'ce_staff', 'Button label for the Church Connect staff page', 'text' ),
		'staff_connect_text'   => array( 'ce_staff', 'Sentence shown with that button', 'textarea' ),
		'invite_eyebrow'       => array( 'ce_staff', 'Invite card: small line', 'text' ),
		'invite_headline'      => array( 'ce_staff', 'Invite card: headline', 'text' ),
		'invite_text'          => array( 'ce_staff', 'Invite card: text', 'textarea' ),
		'invite_link_label'    => array( 'ce_staff', 'Invite card: link label (opens Church Connect forms)', 'text' ),

		'visit_image'          => array( 'ce_visit', 'Sunday photo', 'image', 'The photo beside "What a Sunday looks like".' ),

		'calendar_embed'       => array( 'ce_events', 'Public calendar embed address', 'url', 'From ChurchTrac → Events → Options → Embed Calendar. Add &view=listMonth for a list instead of a month grid.' ),
		'calendar_eyebrow'     => array( 'ce_events', 'Small line', 'text' ),
		'calendar_headline'    => array( 'ce_events', 'Headline', 'text' ),
		'calendar_text'        => array( 'ce_events', 'Sentence', 'text' ),

		'boxcast_embed'        => array( 'ce_watch', 'BoxCast embed URL', 'url', 'The src="…" address from BoxCast’s embed code.' ),
		'boxcast_embed_code'   => array( 'ce_watch', 'Or paste BoxCast’s whole embed code', 'textarea', 'Used instead of the address above if filled in. Administrators only.' ),
		'watch_live_eyebrow'   => array( 'ce_watch', 'Livestream: small line', 'text' ),
		'watch_live_text'      => array( 'ce_watch', 'Livestream: paragraph', 'textarea' ),
		'video_archive_url'    => array( 'ce_watch', 'Video archive link', 'url', 'Where past services live, for example the YouTube channel or BoxCast archive.' ),
		'video_archive_label'  => array( 'ce_watch', 'Video archive button label', 'text' ),
		'watch_notes_eyebrow'  => array( 'ce_watch', 'Sermon notes: small line', 'text' ),
		'watch_notes_headline' => array( 'ce_watch', 'Sermon notes: headline', 'text' ),
		'watch_notes_pending'  => array( 'ce_watch', 'Shown when only the handout is up', 'text' ),
		'watch_notes_empty'    => array( 'ce_watch', 'Shown when there are no sermons yet', 'text' ),
		'announce_image'       => array( 'ce_watch', 'Announcements card: picture', 'image', 'Links to the announcements page in Church Connect. Landscape works best.' ),
		'announce_eyebrow'     => array( 'ce_watch', 'Announcements card: small line', 'text' ),
		'announce_headline'    => array( 'ce_watch', 'Announcements card: headline', 'text' ),
		'announce_text'        => array( 'ce_watch', 'Announcements card: sentence', 'textarea' ),
		'announce_button'      => array( 'ce_watch', 'Announcements card: button label', 'text' ),

		'archive_count'        => array( 'ce_files', 'How many past weeks to list', 'number' ),
	);
}

function ce_customizer_defaults() {
	return array(
		'service_day'          => 'Sunday',
		'service_time'         => '9:30 AM',
		'address_1'            => '2150 N. Ellicott Hwy',
		'address_2'            => 'Calhan, CO 80808',
		'contact_email'        => 'info@calvaryellicott.org',
		'phone'                => '',
		'map_embed'            => '',

		'connect_url'          => 'https://connect.calvaryellicott.org',
		'connect_label'        => 'Church Connect',
		'connect_events_url'   => '',
		'connect_groups_url'   => '',
		'connect_serve_url'    => '',
		'connect_give_url'     => '',
		'connect_forms_url'    => '',
		'connect_announcements_url' => '',
		'connect_eyebrow'      => 'Church Connect',
		'connect_headline'     => 'Events, groups, giving, and more.',
		'connect_text'         => 'Church Connect is where you register for events, join a group, sign up to serve, give, and keep your family’s information up to date. Create an account once and everything is in one place.',
		'connect_button'       => 'Open Church Connect',

		'hero_eyebrow'         => 'A church family in Ellicott, Colorado',
		'hero_line1'           => 'Rooted in truth.',
		'hero_line2'           => 'Growing in grace.',
		'hero_intro'           => 'Come as you are and join us as we study God’s Word, follow Jesus, and love our neighbors—together.',
		'hero_primary_label'   => 'Plan your visit',
		'hero_secondary_label' => 'Get to know us',
		'hero_images'          => '',
		'hero_interval'        => 7,
		'hero_image'           => '',
		'visit_eyebrow'        => 'You’re welcome here',
		'visit_headline'       => "A simple Sunday.\nA meaningful community.",
		'visit_lead'           => 'Whether church is familiar or completely new, we want your first visit to feel easy. Expect genuine people, worship, age-appropriate care for children, and practical teaching through the Bible.',
		'visit_link_label'     => 'Tell us you’re coming',
		'visit_expect'         => 'Biblical teaching, worship, and a relaxed atmosphere.',
		'story_eyebrow'        => 'Our next chapter',
		'story_headline'       => 'Calvary Ellicott',
		'story_text'           => 'Our congregation is prayerfully preparing for a new season as a locally led church serving Ellicott and the surrounding plains. Our foundation is not changing: Jesus Christ, the authority of Scripture, and the call to make disciples.',
		'story_link_label'     => 'Read our story',
		'story_image'          => '',
		'next_eyebrow'         => 'Take a look around',
		'next_headline'        => 'Discover, visit, learn.',

		'discover_image'       => '',
		'visit_image'          => '',
		'mission_eyebrow'      => 'Our purpose',
		'mission_headline'     => 'Mission and vision',
		'mission_text'         => '',
		'beliefs_eyebrow'      => 'What we believe',
		'beliefs_headline'     => 'Historic faith. Everyday hope.',
		'beliefs_text'         => 'We hold to the historic Christian faith and seek to live it with conviction, humility, and love.',
		'staff_mode'           => 'grid',
		'connect_staff_url'    => 'https://connect.calvaryellicott.org/staff',
		'staff_link_text'      => 'See our staff in Church Connect',
		'staff_connect_text'   => 'Our pastors and ministry team, with their contact details, are listed in Church Connect.',
		'invite_eyebrow'       => 'Need to talk?',
		'invite_headline'      => 'We’d love to hear from you.',
		'invite_text'          => 'Questions, prayer needs, and conversations are always welcome.',
		'invite_link_label'    => 'Contact our team',

		'calendar_embed'       => 'https://www.churchtrac.com/public_calendar?ui=609F3F85',
		'calendar_eyebrow'     => 'Life together',
		'calendar_headline'    => 'Upcoming events',
		'calendar_text'        => 'Tap an event for details. Registration and sign-ups happen in Church Connect.',

		'boxcast_embed'        => '',
		'boxcast_embed_code'   => '',
		'watch_live_eyebrow'   => 'Livestream',
		'watch_live_text'      => 'The stream starts a few minutes before the service. Past services are available in the player.',
		'video_archive_url'    => 'https://www.youtube.com/@rmcellicott5120/videos',
		'video_archive_label'  => 'Watch past services',
		'watch_notes_eyebrow'  => 'Follow along',
		'watch_notes_headline' => 'Sermon notes',
		'watch_notes_pending'  => 'Pastor’s notes will be posted after the service.',
		'watch_notes_empty'    => 'This week’s notes will be posted soon',
		'announce_image'       => '',
		'announce_eyebrow'     => 'Announcements',
		'announce_headline'    => 'What’s happening this week',
		'announce_text'        => 'News, sign-ups, and updates are posted in Church Connect.',
		'announce_button'      => 'See announcements',

		'archive_count'        => 12,
	);
}

/**
 * Sanitizer for a field, shared by the dashboard page and the Customizer.
 */
function ce_field_sanitizer( $key, $type ) {
	if ( 'boxcast_embed_code' === $key ) {
		return 'ce_sanitize_embed_code';
	}
	switch ( $type ) {
		case 'gallery':
			return 'ce_sanitize_url_list';
		case 'textarea':
			return 'sanitize_textarea_field';
		case 'url':
			return 'ce_sanitize_https';
		case 'number':
			return 'absint';
		case 'image':
			return 'esc_url_raw';
		case 'select':
			return 'sanitize_key';
		case 'email':
			return 'sanitize_email';
		default:
			return 'sanitize_text_field';
	}
}

function ce_customize_register( $wp_customize ) {
	$d = ce_customizer_defaults();
	$wp_customize->add_panel( 'ce', array( 'title' => 'Calvary Ellicott', 'priority' => 10, 'description' => 'The same settings as Site settings in the dashboard, with a live preview.' ) );
	foreach ( ce_field_sections() as $id => $s ) {
		$wp_customize->add_section( $id, array( 'title' => $s[0], 'description' => $s[1], 'panel' => 'ce' ) );
	}
	foreach ( ce_fields() as $key => $f ) {
		list( $section, $label, $type ) = $f;
		$extra = isset( $f[3] ) ? $f[3] : null;
		$wp_customize->add_setting( $key, array( 'default' => $d[ $key ], 'sanitize_callback' => ce_field_sanitizer( $key, $type ), 'transport' => 'refresh' ) );
		$args = array( 'label' => $label, 'section' => $section );
		if ( is_string( $extra ) ) {
			$args['description'] = $extra;
		}
		if ( 'image' === $type ) {
			$wp_customize->add_control( new WP_Customize_Image_Control( $wp_customize, $key, $args ) );
		} elseif ( 'select' === $type ) {
			$wp_customize->add_control( $key, $args + array( 'type' => 'select', 'choices' => is_array( $extra ) ? $extra : array() ) );
		} else {
			$wp_customize->add_control( $key, $args + array( 'type' => 'email' === $type ? 'email' : ( 'gallery' === $type ? 'textarea' : $type ) ) );
		}
	}
}
add_action( 'customize_register', 'ce_customize_register' );

function ce_sanitize_https( $value ) {
	$value = trim( (string) $value );
	return preg_match( '#^https://#i', $value ) ? esc_url_raw( $value ) : '';
}

function ce_sanitize_url_list( $value ) {
	$out = array();
	foreach ( preg_split( '/[\r\n,]+/', (string) $value ) as $line ) {
		$line = trim( $line );
		if ( preg_match( '#^https://#i', $line ) ) {
			$out[] = esc_url_raw( $line );
		}
	}
	return implode( "\n", array_unique( $out ) );
}

/**
 * The banner album as an array of URLs (falls back to the single photo, then the stock shot).
 */
function ce_hero_images() {
	$list = array_filter( array_map( 'trim', explode( "\n", (string) ce_opt( 'hero_images' ) ) ) );
	if ( ! $list ) {
		$single = ce_opt( 'hero_image' );
		$list   = array( $single ? $single : 'https://images.unsplash.com/photo-1500534314209-a25ddb2bd429?auto=format&fit=crop&w=1900&q=85' );
	}
	return array_values( $list );
}

function ce_sanitize_embed_code( $value ) {
	return current_user_can( 'unfiltered_html' ) ? trim( (string) $value ) : '';
}
