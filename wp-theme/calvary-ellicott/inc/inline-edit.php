<?php
/**
 * Edit mode: logged-in editors click "Edit this page" in the bar at the bottom of any page,
 * then click a heading, label or paragraph and type, the way they would in a document.
 * Save writes every change back to where it already lives (a theme setting, a staff /
 * belief / mission / sermon / alert entry, or a page's title, small line and lead).
 *
 * Templates mark editable text with ce_edit_attr() / ce_opt_span() / ce_post_attr() /
 * ce_meta_attr(). Those print nothing for visitors, so the public page is unchanged.
 * Long page content (blocks) stays in the page editor; ce_editor_link() offers it.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Whether this request is a front-end page view by someone who may edit on the page.
 */
function ce_editing() {
	static $can = null;
	if ( null === $can ) {
		$can = ! is_admin() && ! is_customize_preview() && ! is_feed() && ! defined( 'REST_REQUEST' )
			&& is_user_logged_in() && current_user_can( 'edit_pages' );
	}
	return $can;
}

/**
 * Targets that edit mode may write, and how the saved value is cleaned:
 *  opt:<key>              a theme setting of type text or textarea (see ce_fields)
 *  post:<id>:title        the title of a page, staff member, belief, mission, sermon or alert
 *  post:<id>:content      the plain-text body of a staff / belief / mission / sermon entry
 *  post:<id>:excerpt      a page's lead (the sentence under the page title)
 *  meta:<id>:<key>        _ce_eyebrow (page), _ce_role (staff), _ce_link_text (alert)
 */
function ce_edit_meta_keys() {
	return array( '_ce_eyebrow' => 'page', '_ce_role' => 'ce_staff', '_ce_link_text' => 'ce_alert' );
}

/**
 * Attributes that make an element editable. $format is "text" (one line) or "lines"
 * (line breaks are kept; the template must print the value with nl2br).
 */
function ce_edit_attr( $target, $format = 'text' ) {
	if ( ! ce_editing() ) {
		return '';
	}
	return sprintf( ' data-ce-edit="%s" data-ce-format="%s"', esc_attr( $target ), 'lines' === $format ? 'lines' : 'text' );
}

function ce_post_attr( $post_id, $field, $format = 'text' ) {
	return ce_edit_attr( 'post:' . (int) $post_id . ':' . $field, $format );
}

function ce_meta_attr( $post_id, $key ) {
	return ce_edit_attr( 'meta:' . (int) $post_id . ':' . $key );
}

/**
 * A theme setting's value, escaped, wrapped in an editable span when in edit mode.
 * Use it where the setting is part of a longer line (the service ribbon, a button label).
 */
function ce_opt_span( $key, $format = 'text' ) {
	$value = ce_opt( $key );
	$html  = 'lines' === $format ? nl2br( esc_html( $value ) ) : esc_html( $value );
	if ( ! ce_editing() ) {
		return $html;
	}
	return '<span' . ce_edit_attr( 'opt:' . $key, $format ) . '>' . $html . '</span>';
}

/**
 * Shown next to block content in edit mode: a link into the page editor for that page.
 */
function ce_editor_link( $post = null ) {
	if ( ! ce_editing() ) {
		return;
	}
	$post = get_post( $post );
	$url  = $post ? get_edit_post_link( $post->ID, 'url' ) : '';
	if ( $url ) {
		printf( '<a class="ce-open-editor" href="%s">%s</a>', esc_url( $url ), esc_html__( 'Edit this text in the page editor', 'calvary-ellicott' ) );
	}
}

/* ---------- assets ---------- */

function ce_edit_assets() {
	if ( ! ce_editing() ) {
		return;
	}
	wp_enqueue_style( 'ce-edit', CE_THEME_URI . '/assets/edit.css', array( 'ce-style' ), CE_THEME_VERSION );
	wp_enqueue_script( 'ce-edit', CE_THEME_URI . '/assets/edit.js', array(), CE_THEME_VERSION, true );
	$page = get_queried_object_id();
	wp_localize_script(
		'ce-edit',
		'CE_EDIT',
		array(
			'rest'     => esc_url_raw( rest_url( 'ce/v1/inline' ) ),
			'nonce'    => wp_create_nonce( 'wp_rest' ),
			'page'     => (int) $page,
			'settings' => esc_url_raw( admin_url( 'admin.php?page=ce-site-settings' ) ),
			'editor'   => $page && current_user_can( 'edit_post', $page ) ? esc_url_raw( get_edit_post_link( $page, 'url' ) ) : '',
			'text'     => array(
				'edit'      => __( 'Edit this page', 'calvary-ellicott' ),
				'hint'      => __( 'Click any outlined text and type. Nothing changes until you save.', 'calvary-ellicott' ),
				'save'      => __( 'Save', 'calvary-ellicott' ),
				'saving'    => __( 'Saving…', 'calvary-ellicott' ),
				'saved'     => __( 'Saved. The page is up to date.', 'calvary-ellicott' ),
				'discard'   => __( 'Discard changes', 'calvary-ellicott' ),
				'done'      => __( 'Done', 'calvary-ellicott' ),
				'changes'   => __( '%d to save', 'calvary-ellicott' ),
				'more'      => __( 'More in the dashboard', 'calvary-ellicott' ),
				'confirm'   => __( 'Discard your unsaved changes?', 'calvary-ellicott' ),
				'leave'     => __( 'You have unsaved changes on this page.', 'calvary-ellicott' ),
				'failed'    => __( 'Could not save. Try again.', 'calvary-ellicott' ),
				'nothing'   => __( 'There is no editable text on this page. Use the dashboard instead.', 'calvary-ellicott' ),
				'empty'     => __( 'Type here', 'calvary-ellicott' ),
			),
		)
	);
}
add_action( 'wp_enqueue_scripts', 'ce_edit_assets' );

/**
 * "Edit this page" in the admin bar too, for people who look there first.
 */
function ce_edit_admin_bar( $bar ) {
	if ( ! ce_editing() ) {
		return;
	}
	$bar->add_node(
		array(
			'id'    => 'ce-inline-edit',
			'title' => __( 'Edit this page', 'calvary-ellicott' ),
			'href'  => '#ce-edit',
		)
	);
}
add_action( 'admin_bar_menu', 'ce_edit_admin_bar', 35 );

/* ---------- saving ---------- */

function ce_edit_rest_routes() {
	register_rest_route(
		'ce/v1',
		'/inline',
		array(
			'methods'             => 'POST',
			'permission_callback' => function () {
				return current_user_can( 'edit_pages' );
			},
			'callback'            => 'ce_edit_save',
			'args'                => array(
				'changes' => array( 'required' => true, 'type' => 'array' ),
				'page'    => array( 'type' => 'integer', 'default' => 0 ),
			),
		)
	);
}
add_action( 'rest_api_init', 'ce_edit_rest_routes' );

function ce_edit_save( WP_REST_Request $request ) {
	$changes = $request->get_param( 'changes' );
	$results = array();
	$errors  = 0;
	$options = 0;
	foreach ( (array) $changes as $change ) {
		$target = isset( $change['target'] ) ? (string) $change['target'] : '';
		$value  = isset( $change['value'] ) ? (string) $change['value'] : '';
		$saved  = ce_edit_apply( $target, $value );
		if ( is_wp_error( $saved ) ) {
			$errors++;
			$results[] = array( 'target' => $target, 'error' => $saved->get_error_message() );
		} else {
			if ( 0 === strpos( $target, 'opt:' ) ) {
				$options++;
			}
			$results[] = array( 'target' => $target, 'value' => $saved );
		}
	}
	// Theme settings have no post of their own, so "touch" the page being edited: that fires
	// the host's normal cache purge, the same way saving a staff entry refreshes Discover.
	$page = (int) $request->get_param( 'page' );
	if ( $options && $page && current_user_can( 'edit_post', $page ) ) {
		clean_post_cache( $page );
		wp_update_post( array( 'ID' => $page ) );
	}
	return rest_ensure_response( array( 'ok' => 0 === $errors, 'results' => $results ) );
}

/**
 * Write one value to its home. Returns the cleaned value, or a WP_Error.
 */
function ce_edit_apply( $target, $value ) {
	$parts = explode( ':', $target, 3 );
	$kind  = $parts[0];

	if ( 'opt' === $kind && isset( $parts[1] ) ) {
		$key    = $parts[1];
		$fields = ce_fields();
		if ( ! isset( $fields[ $key ] ) || ! in_array( $fields[ $key ][2], array( 'text', 'textarea' ), true ) || 'boxcast_embed_code' === $key ) {
			return new WP_Error( 'ce_not_editable', __( 'This setting cannot be changed here.', 'calvary-ellicott' ) );
		}
		$value = call_user_func( ce_field_sanitizer( $key, $fields[ $key ][2] ), $value );
		set_theme_mod( $key, $value );
		return $value;
	}

	if ( ( 'post' === $kind || 'meta' === $kind ) && isset( $parts[2] ) ) {
		$post_id = (int) $parts[1];
		$post    = get_post( $post_id );
		$types   = array_merge( CE_FORM_TYPES, array( 'page' ) );
		if ( ! $post || ! in_array( $post->post_type, $types, true ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_Error( 'ce_forbidden', __( 'You cannot change this item.', 'calvary-ellicott' ) );
		}
		$field = $parts[2];

		if ( 'meta' === $kind ) {
			$keys = ce_edit_meta_keys();
			if ( ! isset( $keys[ $field ] ) || $keys[ $field ] !== $post->post_type ) {
				return new WP_Error( 'ce_not_editable', __( 'This field cannot be changed here.', 'calvary-ellicott' ) );
			}
			$value = sanitize_text_field( $value );
			update_post_meta( $post_id, $field, $value );
			return $value;
		}

		$data = array( 'ID' => $post_id );
		if ( 'title' === $field ) {
			$value              = sanitize_text_field( $value );
			$data['post_title'] = $value;
		} elseif ( 'content' === $field && 'page' !== $post->post_type ) {
			$value                = sanitize_textarea_field( $value );
			$data['post_content'] = $value;
		} elseif ( 'excerpt' === $field && 'page' === $post->post_type ) {
			$value                = sanitize_textarea_field( $value );
			$data['post_excerpt'] = $value;
		} else {
			return new WP_Error( 'ce_not_editable', __( 'This text cannot be changed here.', 'calvary-ellicott' ) );
		}
		$result = wp_update_post( wp_slash( $data ), true );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return $value;
	}

	return new WP_Error( 'ce_bad_target', __( 'Unknown field.', 'calvary-ellicott' ) );
}
