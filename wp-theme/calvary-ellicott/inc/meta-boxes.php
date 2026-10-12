<?php
/**
 * Prebuilt forms for the theme's content types. Each type gets one form directly under
 * the title, no block editor: Staff (role, email, bio), Beliefs (text), Bulletins
 * (Sunday, PDF), Sermons (Sunday, handout, notes, summary), Alerts (style, expiry, link).
 * Pages keep the block editor and get a small "Page banner" box in the sidebar.
 */

defined( 'ABSPATH' ) || exit;

const CE_FORM_TYPES = array( 'ce_staff', 'ce_belief', 'ce_bulletin', 'ce_sermon', 'ce_alert' );

/**
 * Classic edit screen for our types, so the form is the whole page.
 */
function ce_no_block_editor( $use, $post_type ) {
	return in_array( $post_type, CE_FORM_TYPES, true ) ? false : $use;
}
add_filter( 'use_block_editor_for_post_type', 'ce_no_block_editor', 10, 2 );

/* ---------- field helpers ---------- */

function ce_row( $label, $input, $help = '' ) {
	echo '<tr><th scope="row" style="width:180px;padding:10px 10px 10px 0;text-align:left;vertical-align:top">' . esc_html( $label ) . '</th><td style="padding:10px 0">' . $input;
	if ( $help ) {
		echo '<p class="description" style="margin:4px 0 0">' . esc_html( $help ) . '</p>';
	}
	echo '</td></tr>';
}

function ce_input( $key, $value, $type = 'text', $placeholder = '' ) {
	return sprintf( '<input type="%s" id="%s" name="%s" value="%s" class="regular-text" placeholder="%s" style="width:100%%;max-width:640px" />', esc_attr( $type ), esc_attr( $key ), esc_attr( $key ), esc_attr( $value ), esc_attr( $placeholder ) );
}

function ce_textarea( $key, $value, $rows = 4 ) {
	return sprintf( '<textarea id="%s" name="%s" rows="%d" class="large-text" style="max-width:640px">%s</textarea>', esc_attr( $key ), esc_attr( $key ), $rows, esc_textarea( $value ) );
}

function ce_pdf_input( $key, $value ) {
	return sprintf(
		'<span style="display:flex;gap:8px;max-width:640px"><input type="url" id="%1$s" name="%1$s" value="%2$s" class="regular-text" placeholder="No file yet" style="flex:1" readonly /><button type="button" class="button ce-pick-pdf" data-target="%1$s">Choose PDF</button><button type="button" class="button ce-clear-pdf" data-target="%1$s">Remove</button></span>',
		esc_attr( $key ),
		esc_attr( $value )
	);
}

function ce_sunday_input( $post ) {
	$date = 'auto-draft' === $post->post_status ? ce_next_sunday() : get_the_date( 'Y-m-d', $post );
	return ce_input( '_ce_sunday', $date, 'date' );
}

function ce_next_sunday() {
	$now = current_time( 'timestamp' );
	$dow = (int) gmdate( 'w', $now );
	return gmdate( 'Y-m-d', $now + ( ( 7 - $dow ) % 7 ) * DAY_IN_SECONDS );
}

/* ---------- the forms, rendered right under the title ---------- */

function ce_render_form( $post ) {
	if ( ! in_array( $post->post_type, CE_FORM_TYPES, true ) ) {
		return;
	}
	wp_nonce_field( 'ce_save_meta', 'ce_meta_nonce' );
	echo '<div class="postbox" style="margin-top:16px"><div class="inside" style="padding:4px 16px 12px"><table class="form-table" role="presentation" style="margin:0"><tbody>';
	$m = function ( $key ) use ( $post ) {
		return get_post_meta( $post->ID, $key, true );
	};

	switch ( $post->post_type ) {
		case 'ce_staff':
			ce_row( 'Role or title', ce_input( '_ce_role', $m( '_ce_role' ), 'text', 'For example Pastor, Assistant Pastor, Worship Leader' ) );
			ce_row( 'Short bio', ce_textarea( '_ce_body', $post->post_content, 4 ), 'One or two sentences. This is the text on the card.' );
			ce_row( 'Email (optional)', ce_input( '_ce_email', $m( '_ce_email' ), 'email' ), 'Adds a "Send an email" link to the card.' );
			ce_row( 'Photo', '<em>Use the <strong>Photo</strong> box on the right. A landscape picture works best.</em>' );
			ce_row( 'Order', '<em>Use the <strong>Order</strong> number in the Attributes box on the right. Lower numbers come first.</em>' );
			break;

		case 'ce_belief':
			ce_row( 'Statement', ce_textarea( '_ce_body', $post->post_content, 4 ), 'The title above is the heading (for example "The Bible"); this is the sentence shown when it is opened.' );
			ce_row( 'Order', '<em>Use the <strong>Order</strong> number in the Attributes box on the right.</em>' );
			break;

		case 'ce_bulletin':
			ce_row( 'Sunday', ce_sunday_input( $post ), 'The Sunday this bulletin is for. Newest shows on the Learn page; older ones are listed under it.' );
			ce_row( 'Bulletin PDF', ce_pdf_input( '_ce_pdf', $m( '_ce_pdf' ) ) );
			break;

		case 'ce_sermon':
			ce_row( 'Sunday', ce_sunday_input( $post ), 'The Sunday of this sermon. The title above can be the sermon title or passage.' );
			ce_row( 'Sermon handout', ce_pdf_input( '_ce_handout', $m( '_ce_handout' ) ), 'The follow-along sheet. Add it before the service.' );
			ce_row( 'Pastor’s notes', ce_pdf_input( '_ce_notes', $m( '_ce_notes' ) ), 'Add after the service. Until then the page says the notes are coming.' );
			ce_row( 'Summary (optional)', ce_textarea( '_ce_body', $post->post_content, 3 ), 'A sentence or two shown under the title on the Learn page.' );
			break;

		case 'ce_alert':
			$style = $m( '_ce_style' );
			ce_row( 'Banner message', '<em>The title above is the message people see. Keep it to one or two sentences.</em>' );
			ce_row( 'Style', sprintf( '<label><input type="radio" name="_ce_style" value="warning" %s /> Warning (red)</label> &nbsp; <label><input type="radio" name="_ce_style" value="info" %s /> Information (gold)</label>', checked( 'info' !== $style, true, false ), checked( 'info', $style, false ) ) );
			ce_row( 'Hide after (optional)', ce_input( '_ce_expires', $m( '_ce_expires' ), 'date' ), 'The banner disappears the day after this date.' );
			$dropdown = wp_dropdown_pages( array( 'name' => '_ce_link_page', 'show_option_none' => '— none —', 'option_none_value' => 0, 'selected' => (int) $m( '_ce_link_page' ), 'echo' => 0 ) );
			ce_row( 'Link to a page (optional)', $dropdown );
			ce_row( 'Or a web address (optional)', ce_input( '_ce_link', $m( '_ce_link' ), 'url', 'https://…' ) );
			ce_row( 'Link label', ce_input( '_ce_link_text', $m( '_ce_link_text' ), 'text', 'Learn more' ) );
			ce_row( 'Showing it', '<em><strong>Publish</strong> shows the banner on every page. <strong>Move to Trash</strong> or switch it to Draft to hide it, or let the date above hide it for you. Only the newest published alert shows.</em>' );
			break;
	}
	echo '</tbody></table></div></div>';
}
add_action( 'edit_form_after_title', 'ce_render_form' );

/**
 * Pages keep the block editor; this small box holds the banner's small line.
 */
function ce_page_banner_box() {
	add_meta_box( 'ce_page_hero', 'Page banner', 'ce_page_hero_box', 'page', 'side', 'high' );
}
add_action( 'add_meta_boxes', 'ce_page_banner_box' );

function ce_page_hero_box( $post ) {
	wp_nonce_field( 'ce_save_meta', 'ce_meta_nonce' );
	echo '<p><label for="_ce_eyebrow"><strong>Small line above the title</strong></label><br />' . ce_input( '_ce_eyebrow', get_post_meta( $post->ID, '_ce_eyebrow', true ), 'text', 'For example "Who we are"' ) . '</p>';
	echo '<p class="description">The sentence under the title comes from the Excerpt box.</p>';
}

/* ---------- saving ---------- */

function ce_meta_nonce_ok() {
	return isset( $_POST['ce_meta_nonce'] ) && wp_verify_nonce( $_POST['ce_meta_nonce'], 'ce_save_meta' );
}

/**
 * Body text and Sunday date are stored on the post itself, so they ride along in post data.
 */
function ce_insert_post_data( $data, $postarr ) {
	if ( ! ce_meta_nonce_ok() || ! in_array( $data['post_type'], CE_FORM_TYPES, true ) ) {
		return $data;
	}
	if ( isset( $_POST['_ce_body'] ) ) {
		$data['post_content'] = sanitize_textarea_field( wp_unslash( $_POST['_ce_body'] ) );
	}
	if ( isset( $_POST['_ce_sunday'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $_POST['_ce_sunday'] ) ) {
		$local                 = $_POST['_ce_sunday'] . ' 08:00:00';
		$data['post_date']     = $local;
		$data['post_date_gmt'] = get_gmt_from_date( $local );
		if ( 'future' === $data['post_status'] && strtotime( $data['post_date_gmt'] . ' UTC' ) <= time() ) {
			$data['post_status'] = 'publish';
		}
	}
	return $data;
}
add_filter( 'wp_insert_post_data', 'ce_insert_post_data', 10, 2 );

function ce_save_meta( $post_id ) {
	if ( ! ce_meta_nonce_ok() || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	foreach ( array( '_ce_eyebrow', '_ce_role', '_ce_link_text', '_ce_expires' ) as $key ) {
		if ( isset( $_POST[ $key ] ) ) {
			update_post_meta( $post_id, $key, sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) );
		}
	}
	foreach ( array( '_ce_pdf', '_ce_handout', '_ce_notes', '_ce_link' ) as $key ) {
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

/* ---------- admin polish ---------- */

/**
 * Media picker for the PDF fields, on every form screen.
 */
function ce_admin_assets( $hook ) {
	if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
		return;
	}
	$screen = get_current_screen();
	if ( $screen && in_array( $screen->post_type, CE_FORM_TYPES, true ) ) {
		wp_enqueue_media();
		wp_enqueue_script( 'ce-admin', CE_THEME_URI . '/assets/admin.js', array( 'jquery' ), CE_THEME_VERSION, true );
		wp_add_inline_style( 'wp-admin', '#postdivrich,#postexcerpt,#commentstatusdiv,#commentsdiv,#slugdiv,#trackbacksdiv,#postcustom{display:none}' );
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

/**
 * Friendlier title placeholders on the form screens.
 */
function ce_title_placeholder( $text, $post ) {
	$map = array(
		'ce_staff'    => 'Person’s name',
		'ce_belief'   => 'Heading, for example "The Bible"',
		'ce_bulletin' => 'For your reference, for example "October 12 bulletin"',
		'ce_sermon'   => 'Sermon title or passage',
		'ce_alert'    => 'The message people will see',
	);
	return isset( $map[ $post->post_type ] ) ? $map[ $post->post_type ] : $text;
}
add_filter( 'enter_title_here', 'ce_title_placeholder', 10, 2 );
