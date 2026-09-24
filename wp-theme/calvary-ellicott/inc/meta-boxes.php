<?php
/**
 * Meta boxes: page eyebrow, staff role/email, bulletin PDF, sermon files, alert settings.
 */

defined( 'ABSPATH' ) || exit;

function ce_add_meta_boxes() {
	add_meta_box( 'ce_page_hero', 'Page banner', 'ce_page_hero_box', 'page', 'side', 'high' );
	add_meta_box( 'ce_staff_details', 'Staff details', 'ce_staff_box', 'ce_staff', 'normal', 'high' );
	add_meta_box( 'ce_bulletin_file', 'Bulletin PDF', 'ce_bulletin_box', 'ce_bulletin', 'normal', 'high' );
	add_meta_box( 'ce_sermon_files', 'Sermon files', 'ce_sermon_box', 'ce_sermon', 'normal', 'high' );
	add_meta_box( 'ce_alert_settings', 'Alert settings', 'ce_alert_box', 'ce_alert', 'normal', 'high' );
}
add_action( 'add_meta_boxes', 'ce_add_meta_boxes' );

function ce_field( $key, $label, $post_id, $type = 'text', $help = '' ) {
	$value = get_post_meta( $post_id, $key, true );
	printf( '<p><label for="%1$s"><strong>%2$s</strong></label><br /><input type="%3$s" id="%1$s" name="%1$s" value="%4$s" class="widefat" />', esc_attr( $key ), esc_html( $label ), esc_attr( $type ), esc_attr( $value ) );
	if ( $help ) {
		echo '<span class="description">' . esc_html( $help ) . '</span>';
	}
	echo '</p>';
}

function ce_pdf_field( $key, $label, $post_id, $help = '' ) {
	$value = get_post_meta( $post_id, $key, true );
	printf(
		'<p><label for="%1$s"><strong>%2$s</strong></label><br /><span class="ce-pdf-field" style="display:flex;gap:8px"><input type="url" id="%1$s" name="%1$s" value="%3$s" class="widefat" placeholder="https://…/file.pdf" /><button type="button" class="button ce-pick-pdf" data-target="%1$s">Choose PDF</button></span>',
		esc_attr( $key ),
		esc_html( $label ),
		esc_attr( $value )
	);
	if ( $help ) {
		echo '<span class="description">' . esc_html( $help ) . '</span>';
	}
	echo '</p>';
}

function ce_page_hero_box( $post ) {
	wp_nonce_field( 'ce_save_meta', 'ce_meta_nonce' );
	ce_field( '_ce_eyebrow', 'Small line above the title', $post->ID, 'text', 'For example "About us" or "Your next step". The excerpt box is the sentence under the title.' );
}

function ce_staff_box( $post ) {
	wp_nonce_field( 'ce_save_meta', 'ce_meta_nonce' );
	ce_field( '_ce_role', 'Role or title', $post->ID, 'text', 'For example "Lead Pastor".' );
	ce_field( '_ce_email', 'Email (optional)', $post->ID, 'email', 'Adds a "Send an email" link to the card.' );
	echo '<p class="description">The name is the title above, the bio is the main text box, and the photo is the featured image on the right. Use the Order field to arrange cards.</p>';
}

function ce_bulletin_box( $post ) {
	wp_nonce_field( 'ce_save_meta', 'ce_meta_nonce' );
	ce_pdf_field( '_ce_pdf', 'Bulletin PDF', $post->ID, 'Set the publish date on the right to the Sunday this bulletin is for.' );
}

function ce_sermon_box( $post ) {
	wp_nonce_field( 'ce_save_meta', 'ce_meta_nonce' );
	ce_pdf_field( '_ce_handout', 'Sermon handout (before the service)', $post->ID );
	ce_pdf_field( '_ce_notes', 'Pastor’s notes (after the service)', $post->ID, 'Leave blank until the notes are ready. The page says they are coming.' );
	echo '<p class="description">Set the publish date on the right to the Sunday of this sermon. The title can be the sermon title or passage; the main text box is optional.</p>';
}

function ce_alert_box( $post ) {
	wp_nonce_field( 'ce_save_meta', 'ce_meta_nonce' );
	$style = get_post_meta( $post->ID, '_ce_style', true );
	echo '<p><strong>Style</strong><br />';
	printf( '<label><input type="radio" name="_ce_style" value="warning" %s /> Warning (red)</label> &nbsp; ', checked( $style !== 'info', true, false ) );
	printf( '<label><input type="radio" name="_ce_style" value="info" %s /> Information (gold)</label></p>', checked( $style, 'info', false ) );
	ce_field( '_ce_expires', 'Hide automatically after (optional)', $post->ID, 'date', 'The banner disappears the day after this date.' );
	$page = (int) get_post_meta( $post->ID, '_ce_link_page', true );
	echo '<p><strong>Link to a page (optional)</strong><br />';
	wp_dropdown_pages( array( 'name' => '_ce_link_page', 'show_option_none' => '— none —', 'option_none_value' => 0, 'selected' => $page ) );
	echo '</p>';
	ce_field( '_ce_link', 'Or link to a web address (optional)', $post->ID, 'url', 'Must start with https://' );
	ce_field( '_ce_link_text', 'Link label', $post->ID, 'text', 'Defaults to "Learn more".' );
	echo '<p class="description">The message is the main text box above (the title is used if it is empty). <strong>Publish</strong> shows the banner on every page. Move it to <strong>draft</strong> to hide it, or let the date above hide it for you.</p>';
}

function ce_save_meta( $post_id ) {
	if ( ! isset( $_POST['ce_meta_nonce'] ) || ! wp_verify_nonce( $_POST['ce_meta_nonce'], 'ce_save_meta' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	$text = array( '_ce_eyebrow', '_ce_role', '_ce_link_text', '_ce_expires' );
	$urls = array( '_ce_pdf', '_ce_handout', '_ce_notes', '_ce_link' );
	foreach ( $text as $key ) {
		if ( isset( $_POST[ $key ] ) ) {
			update_post_meta( $post_id, $key, sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) );
		}
	}
	foreach ( $urls as $key ) {
		if ( isset( $_POST[ $key ] ) ) {
			update_post_meta( $post_id, $key, esc_url_raw( wp_unslash( $_POST[ $key ] ) ) );
		}
	}
	if ( isset( $_POST['_ce_email'] ) ) {
		update_post_meta( $post_id, '_ce_email', sanitize_email( wp_unslash( $_POST['_ce_email'] ) ) );
	}
	if ( isset( $_POST['_ce_style'] ) ) {
		update_post_meta( $post_id, '_ce_style', 'info' === $_POST['_ce_style'] ? 'info' : 'warning' );
	}
	if ( isset( $_POST['_ce_link_page'] ) ) {
		update_post_meta( $post_id, '_ce_link_page', absint( $_POST['_ce_link_page'] ) );
	}
}
add_action( 'save_post', 'ce_save_meta' );

/**
 * Media picker for the PDF fields.
 */
function ce_admin_assets( $hook ) {
	if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
		return;
	}
	$screen = get_current_screen();
	if ( $screen && in_array( $screen->post_type, array( 'ce_bulletin', 'ce_sermon' ), true ) ) {
		wp_enqueue_media();
		wp_enqueue_script( 'ce-admin', CE_THEME_URI . '/assets/admin.js', array( 'jquery' ), CE_THEME_VERSION, true );
	}
}
add_action( 'admin_enqueue_scripts', 'ce_admin_assets' );

/**
 * Allow PDF uploads for everyone who can upload at all (some hosts restrict this).
 */
function ce_allow_pdf( $mimes ) {
	$mimes['pdf'] = 'application/pdf';
	return $mimes;
}
add_filter( 'upload_mimes', 'ce_allow_pdf' );
