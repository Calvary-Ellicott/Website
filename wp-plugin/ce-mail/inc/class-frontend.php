<?php
/**
 * The /mail and /emailadmin routes: full-screen app pages outside the theme.
 *
 * /mail is for staff: either a WordPress user with the portal capability, or anyone
 * who signs in with a church mailbox (email + password checked against IMAP, kept in
 * an encrypted cookie, nothing stored). /emailadmin is for WordPress administrators.
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
	 * Which page the current request is for: '1' (mail), 'admin', or '' for neither.
	 * Read from the path itself so the pages work even when rewrite rules were never
	 * flushed (for example right after "Replace current with uploaded").
	 */
	public static function requested() {
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$path = strtolower( trim( (string) wp_parse_url( $uri, PHP_URL_PATH ), '/' ) );
		$home = trim( (string) wp_parse_url( home_url(), PHP_URL_PATH ), '/' );
		if ( '' !== $home && 0 === strpos( $path, $home . '/' ) ) {
			$path = substr( $path, strlen( $home ) + 1 );
		}
		if ( 'mail' === $path ) {
			return '1';
		}
		if ( 'emailadmin' === $path ) {
			return 'admin';
		}
		return '';
	}

	/**
	 * Runs as soon as WordPress has parsed the request, before any theme or
	 * "coming soon" logic, so the pages render for signed-in staff regardless.
	 */
	public static function parse_request( $wp ) {
		$which = ! empty( $wp->query_vars['ce_mail'] ) ? (string) $wp->query_vars['ce_mail'] : self::requested();
		if ( $which ) {
			$wp->query_vars['ce_mail'] = $which;
			self::render( $which );
		}
	}

	/**
	 * Fallback for the rewrite-rule path (normally parse_request has already rendered).
	 */
	public static function maybe_render() {
		$which = get_query_var( 'ce_mail' );
		if ( $which ) {
			self::render( (string) $which );
		}
	}

	private static function render( $which ) {
		$is_admin = 'admin' === $which;
		$path     = $is_admin ? '/emailadmin/' : '/mail/';
		nocache_headers();

		if ( ! $is_admin && isset( $_GET['signout'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			CE_Mail_Session::end();
			wp_safe_redirect( home_url( '/mail/' ) );
			exit;
		}

		$wp_user = is_user_logged_in();
		$guest   = ! $wp_user && ! $is_admin && CE_Mail_Session::active();

		if ( ! $wp_user && ! $guest ) {
			if ( $is_admin ) {
				wp_safe_redirect( wp_login_url( home_url( $path ) ) );
				exit;
			}
			status_header( 200 );
			header( 'X-Frame-Options: SAMEORIGIN' );
			$config = self::login_config();
			include CE_MAIL_DIR . 'templates/login.php';
			exit;
		}
		if ( $is_admin && ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Email administration is for website administrators only.', 'Email admin', array( 'response' => 403, 'back_link' => true ) );
		}
		if ( $wp_user && ! current_user_can( CE_MAIL_CAP ) ) {
			wp_die( 'This mail portal is for church staff. Ask the website admin to give your account access, or sign out and sign in with your church mailbox instead.', 'Mail portal', array( 'response' => 403, 'back_link' => true ) );
		}
		status_header( 200 );
		header( 'X-Frame-Options: SAMEORIGIN' );
		$config = self::config();
		include CE_MAIL_DIR . ( $is_admin ? 'templates/admin.php' : 'templates/app.php' );
		exit;
	}

	public static function config() {
		$s     = CE_Mail_Settings::all();
		$guest = ! is_user_logged_in() ? CE_Mail_Session::guest() : null;
		if ( $guest ) {
			$name = ucwords( str_replace( array( '.', '_', '-' ), ' ', strstr( $guest['email'], '@', true ) ) );
			$user = array( 'name' => $name, 'email' => $guest['email'], 'admin' => false, 'guest' => true );
		} else {
			$u    = wp_get_current_user();
			$user = array( 'name' => $u->display_name, 'email' => $u->user_email, 'admin' => current_user_can( 'manage_options' ), 'guest' => false );
		}
		return array(
			'rest'      => esc_url_raw( rest_url( CE_Mail_REST::NS . '/' ) ),
			'nonce'     => wp_create_nonce( 'wp_rest' ),
			'user'      => $user,
			'site'      => array(
				'name'       => get_bloginfo( 'name' ),
				'url'        => home_url( '/' ),
				'logout'     => $guest ? home_url( '/mail/?signout=1' ) : wp_logout_url( home_url( '/' ) ),
				'settings'   => admin_url( 'admin.php?page=ce-mail' ),
				'mail'       => home_url( '/mail/' ),
				'emailadmin' => home_url( '/emailadmin/' ),
			),
			'webmail'   => $s['webmail_url'],
			'imap_host' => $s['imap_host'],
			'domain'    => CE_Mail_Purelymail::domain(),
			'version'   => CE_MAIL_VERSION,
		);
	}

	public static function login_config() {
		$s = CE_Mail_Settings::all();
		return array(
			'rest'     => esc_url_raw( rest_url( CE_Mail_REST::NS . '/' ) ),
			'site'     => array( 'name' => get_bloginfo( 'name' ), 'url' => home_url( '/' ), 'wp_login' => wp_login_url( home_url( '/mail/' ) ) ),
			'domain'   => CE_Mail_Purelymail::domain(),
			'webmail'  => $s['webmail_url'],
			'imap_host' => $s['imap_host'],
			'version'  => CE_MAIL_VERSION,
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
