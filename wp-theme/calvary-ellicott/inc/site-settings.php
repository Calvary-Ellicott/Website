<?php
/**
 * Dashboard page "Site settings": every theme setting as a plain form, no Customizer needed.
 * Editors can use it. Saves the same theme mods the Customizer uses.
 */

defined( 'ABSPATH' ) || exit;

function ce_site_settings_menu() {
	add_menu_page( 'Site settings', 'Site settings', 'edit_pages', 'ce-site-settings', 'ce_site_settings_page', 'dashicons-admin-site-alt3', 3 );
}
add_action( 'admin_menu', 'ce_site_settings_menu' );

function ce_site_settings_assets( $hook ) {
	if ( 'toplevel_page_ce-site-settings' !== $hook ) {
		return;
	}
	wp_enqueue_media();
	wp_enqueue_script( 'ce-admin', CE_THEME_URI . '/assets/admin.js', array( 'jquery' ), CE_THEME_VERSION, true );
	wp_add_inline_style( 'wp-admin', '.ce-settings .postbox{max-width:960px}.ce-settings .inside{padding:6px 16px 14px}.ce-settings .form-table th{width:240px}.ce-settings input.regular-text,.ce-settings textarea,.ce-settings select{width:100%;max-width:640px}.ce-settings .ce-image-preview{display:block;max-width:260px;max-height:160px;margin:8px 0;border:1px solid #dcdcde;border-radius:6px}.ce-settings .ce-image-preview[src=""]{display:none}.ce-quick{display:flex;flex-wrap:wrap;gap:8px;margin:12px 0 18px}.ce-quick a{display:inline-block;padding:8px 14px;background:#fff;border:1px solid #c3c4c7;border-radius:6px;text-decoration:none;font-weight:600}' );
}
add_action( 'admin_enqueue_scripts', 'ce_site_settings_assets' );

function ce_site_settings_save() {
	if ( ! isset( $_POST['ce_settings_nonce'] ) || ! wp_verify_nonce( $_POST['ce_settings_nonce'], 'ce_site_settings' ) || ! current_user_can( 'edit_pages' ) ) {
		return false;
	}
	foreach ( ce_fields() as $key => $f ) {
		$type = $f[2];
		if ( ! isset( $_POST[ $key ] ) ) {
			continue;
		}
		if ( 'boxcast_embed_code' === $key && ! current_user_can( 'unfiltered_html' ) ) {
			continue; // leave the admin-entered code alone
		}
		$sanitize = ce_field_sanitizer( $key, $type );
		$value    = call_user_func( $sanitize, wp_unslash( $_POST[ $key ] ) );
		if ( 'select' === $type ) {
			$choices = isset( $f[3] ) && is_array( $f[3] ) ? $f[3] : array();
			if ( ! isset( $choices[ $value ] ) ) {
				continue;
			}
		}
		set_theme_mod( $key, $value );
	}
	return true;
}

function ce_site_settings_page() {
	$saved = false;
	if ( isset( $_POST['ce_save'] ) ) {
		$saved = ce_site_settings_save();
	}
	$sections = ce_field_sections();
	$fields   = ce_fields();
	$tab      = isset( $_GET['tab'] ) && isset( $sections[ $_GET['tab'] ] ) ? $_GET['tab'] : 'ce_site';

	echo '<div class="wrap ce-settings"><h1>Site settings</h1>';
	if ( $saved ) {
		echo '<div class="notice notice-success is-dismissible"><p>Saved. The website is up to date.</p></div>';
	} elseif ( isset( $_POST['ce_save'] ) ) {
		echo '<div class="notice notice-error"><p>Could not save. Try again.</p></div>';
	}
	echo '<div class="ce-quick">';
	foreach ( array(
		'Staff'            => 'edit.php?post_type=ce_staff',
		'Mission & Vision' => 'edit.php?post_type=ce_mission',
		'Beliefs'          => 'edit.php?post_type=ce_belief',
		'Sermons'          => 'edit.php?post_type=ce_sermon',
		'Alerts'           => 'edit.php?post_type=ce_alert',
		'Pages'            => 'edit.php?post_type=page',
	) as $label => $url ) {
		echo '<a href="' . esc_url( admin_url( $url ) ) . '">' . esc_html( $label ) . '</a>';
	}
	echo '<a href="' . esc_url( admin_url( 'customize.php' ) ) . '">Live preview (Customizer)</a></div>';

	echo '<h2 class="nav-tab-wrapper">';
	foreach ( $sections as $id => $s ) {
		printf( '<a class="nav-tab %s" href="%s">%s</a>', $id === $tab ? 'nav-tab-active' : '', esc_url( admin_url( 'admin.php?page=ce-site-settings&tab=' . $id ) ), esc_html( $s[0] ) );
	}
	echo '</h2>';

	echo '<form method="post"><input type="hidden" name="ce_save" value="1" />';
	wp_nonce_field( 'ce_site_settings', 'ce_settings_nonce' );
	echo '<p class="description" style="margin:12px 0 16px">' . esc_html( $sections[ $tab ][1] ) . '</p>';
	echo '<div class="postbox"><div class="inside"><table class="form-table" role="presentation"><tbody>';
	foreach ( $fields as $key => $f ) {
		list( $section, $label, $type ) = $f;
		if ( $section !== $tab ) {
			continue;
		}
		$extra = isset( $f[3] ) ? $f[3] : null;
		$value = ce_opt( $key );
		echo '<tr><th scope="row"><label for="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th><td>';
		switch ( $type ) {
			case 'textarea':
				printf( '<textarea id="%1$s" name="%1$s" rows="%2$d" class="large-text">%3$s</textarea>', esc_attr( $key ), 'boxcast_embed_code' === $key ? 5 : 3, esc_textarea( $value ) );
				break;
			case 'select':
				echo '<select id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '">';
				foreach ( (array) $extra as $v => $l ) {
					printf( '<option value="%s" %s>%s</option>', esc_attr( $v ), selected( $value, $v, false ), esc_html( $l ) );
				}
				echo '</select>';
				break;
			case 'image':
				printf( '<img class="ce-image-preview" src="%2$s" alt="" /><span style="display:flex;gap:8px;max-width:640px"><input type="url" id="%1$s" name="%1$s" value="%2$s" class="regular-text" placeholder="No picture yet" readonly style="flex:1" /><button type="button" class="button ce-pick-image" data-target="%1$s">Choose picture</button><button type="button" class="button ce-clear-image" data-target="%1$s">Remove</button></span>', esc_attr( $key ), esc_attr( $value ) );
				break;
			case 'number':
				printf( '<input type="number" id="%1$s" name="%1$s" value="%2$s" class="small-text" min="1" max="100" />', esc_attr( $key ), esc_attr( $value ) );
				break;
			default:
				printf( '<input type="%3$s" id="%1$s" name="%1$s" value="%2$s" class="regular-text" />', esc_attr( $key ), esc_attr( $value ), 'url' === $type ? 'url' : ( 'email' === $type ? 'email' : 'text' ) );
		}
		if ( is_string( $extra ) ) {
			echo '<p class="description">' . esc_html( $extra ) . '</p>';
		}
		echo '</td></tr>';
	}
	echo '</tbody></table></div></div>';
	submit_button( 'Save changes' );
	echo '</form></div>';
}
