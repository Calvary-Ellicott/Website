<?php
/**
 * The /mail route: a full-screen app page outside the theme, for signed-in staff only.
 */

defined( 'ABSPATH' ) || exit;

class CE_Mail_Frontend {

	public static function add_rewrite() {
		add_rewrite_rule( '^mail/?$', 'index.php?ce_mail=1', 'top' );
		add_rewrite_rule( '^emailadmin/?$', 'index.php?ce_mail=admin', 'top' );
	}

	public static function query_vars( $vars ) {
		$vars[] = 'ce_mail';
		return $vars;
	}

	/**
	 * Belt and braces: recognise /mail and /emailadmin from the request path itself,
	 * so the pages work even when the rewrite rules were never flushed (for example
	 * right after "Replace current with uploaded" on WordPress.com).
	 */
	public static function parse_request( $wp ) {
		if ( ! empty( $wp->query_vars['ce_mail'] ) ) {
			return;
		}
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$path = strtolower( trim( (string) wp_parse_url( $uri, PHP_URL_PATH ), '/' ) );
		$home = trim( (string) wp_parse_url( home_url(), PHP_URL_PATH ), '/' );
		if ( '' !== $home && 0 === strpos( $path, $home . '/' ) ) {
			$path = substr( $path, strlen( $home ) + 1 );
		}
		if ( 'mail' === $path ) {
			$wp->query_vars['ce_mail'] = '1';
		} elseif ( 'emailadmin' === $path ) {
			$wp->query_vars['ce_mail'] = 'admin';
		}
	}

	public static function maybe_render() {
		$which = get_query_var( 'ce_mail' );
		if ( ! $which ) {
			return;
		}
		$is_admin = 'admin' === $which;
		$path     = $is_admin ? '/emailadmin/' : '/mail/';
		nocache_headers();
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( wp_login_url( home_url( $path ) ) );
			exit;
		}
		if ( $is_admin && ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Email administration is for website administrators only.', 'Email admin', array( 'response' => 403, 'back_link' => true ) );
		}
		if ( ! current_user_can( CE_MAIL_CAP ) ) {
			wp_die( 'This mail portal is for church staff. Ask the website admin to give your account access.', 'Mail portal', array( 'response' => 403, 'back_link' => true ) );
		}
		status_header( 200 );
		header( 'X-Frame-Options: SAMEORIGIN' );
		$config = self::config();
		include CE_MAIL_DIR . ( $is_admin ? 'templates/admin.php' : 'templates/app.php' );
		exit;
	}

	public static function config() {
		$s = CE_Mail_Settings::all();
		$u = wp_get_current_user();
		return array(
			'rest'      => esc_url_raw( rest_url( CE_Mail_REST::NS . '/' ) ),
			'nonce'     => wp_create_nonce( 'wp_rest' ),
			'user'      => array( 'name' => $u->display_name, 'email' => $u->user_email, 'admin' => current_user_can( 'manage_options' ) ),
			'site'      => array( 'name' => get_bloginfo( 'name' ), 'url' => home_url( '/' ), 'logout' => wp_logout_url( home_url( '/' ) ), 'settings' => admin_url( 'admin.php?page=ce-mail' ), 'mail' => home_url( '/mail/' ), 'emailadmin' => home_url( '/emailadmin/' ) ),
			'webmail'   => $s['webmail_url'],
			'imap_host' => $s['imap_host'],
			'domain'    => CE_Mail_Purelymail::domain(),
			'version'   => CE_MAIL_VERSION,
		);
	}

	public static function admin_bar( $bar ) {
		if ( ! current_user_can( CE_MAIL_CAP ) ) {
			return;
		}
		$bar->add_node( array( 'id' => 'ce-mail', 'title' => '<span class="ab-icon dashicons dashicons-email-alt" style="margin-top:2px"></span> Mail', 'href' => home_url( '/mail/' ) ) );
		if ( current_user_can( 'manage_options' ) ) {
			$bar->add_node( array( 'id' => 'ce-mail-admin', 'parent' => 'ce-mail', 'title' => 'Email admin', 'href' => home_url( '/emailadmin/' ) ) );
		}
	}
}
