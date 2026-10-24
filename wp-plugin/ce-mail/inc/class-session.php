<?php
/**
 * Mailbox sign-in for staff who have no WordPress account. The portal checks the
 * email and password against the IMAP server, then keeps them in one encrypted,
 * HttpOnly cookie for the browser session (or 30 days with "keep me signed in").
 * Nothing is written to the database; signing out just clears the cookie.
 */

defined( 'ABSPATH' ) || exit;

class CE_Mail_Session {

	const COOKIE   = 'ce_mail_s';
	const MAX_DAYS = 30;
	const ATTEMPTS = 8;      // sign-in attempts per window per address
	const WINDOW   = 900;    // seconds

	private static $cache = false;

	/**
	 * The guest's mailbox login from the cookie, or null when there is none.
	 */
	public static function guest() {
		if ( false !== self::$cache ) {
			return self::$cache;
		}
		self::$cache = null;
		if ( empty( $_COOKIE[ self::COOKIE ] ) ) {
			return null;
		}
		$raw = CE_Mail_Crypto::decrypt( wp_unslash( $_COOKIE[ self::COOKIE ] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$d   = $raw ? json_decode( $raw, true ) : null;
		if ( ! is_array( $d ) || empty( $d['e'] ) || empty( $d['p'] ) || empty( $d['t'] ) || empty( $d['x'] ) || (int) $d['x'] < time() ) {
			return null;
		}
		self::$cache = array( 'email' => (string) $d['e'], 'password' => (string) $d['p'], 'token' => (string) $d['t'] );
		return self::$cache;
	}

	public static function active() {
		return null !== self::guest();
	}

	/**
	 * Start a session after the login has been verified against IMAP.
	 */
	public static function start( $email, $password, $remember ) {
		$exp     = time() + self::MAX_DAYS * DAY_IN_SECONDS;
		$payload = wp_json_encode( array( 'e' => $email, 'p' => $password, 't' => bin2hex( random_bytes( 12 ) ), 'x' => $exp ) );
		self::set_cookie( CE_Mail_Crypto::encrypt( $payload ), $remember ? $exp : 0 );
		self::$cache = false;
	}

	public static function end() {
		self::set_cookie( '', time() - YEAR_IN_SECONDS );
		unset( $_COOKIE[ self::COOKIE ] );
		self::$cache = null;
	}

	private static function set_cookie( $value, $expires ) {
		$opts = array(
			'expires'  => $expires,
			'path'     => '/',
			'secure'   => is_ssl(),
			'httponly' => true,
			'samesite' => 'Lax',
		);
		if ( PHP_VERSION_ID >= 70300 ) {
			setcookie( self::COOKIE, $value, $opts );
		} else {
			setcookie( self::COOKIE, $value, $expires, '/; samesite=Lax', '', is_ssl(), true );
		}
	}

	/**
	 * Give each guest session its own nonce "user id" so the REST nonce is tied to
	 * the session instead of being the same for every logged-out visitor.
	 */
	public static function nonce_uid( $uid, $action = '' ) {
		$g = self::guest();
		if ( $g ) {
			return 1000000000 + hexdec( substr( sha1( $g['token'] ), 0, 7 ) );
		}
		return $uid;
	}

	/**
	 * Simple sign-in throttle keyed by client address: true when another attempt is allowed.
	 */
	public static function allow_attempt() {
		$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$key = 'ce_mail_login_' . md5( $ip );
		$n   = (int) get_transient( $key );
		if ( $n >= self::ATTEMPTS ) {
			return false;
		}
		set_transient( $key, $n + 1, self::WINDOW );
		return true;
	}

	/**
	 * Guests may only sign in to mailboxes on the church domain.
	 */
	public static function allowed_email( $email ) {
		$email  = strtolower( trim( (string) $email ) );
		$domain = CE_Mail_Purelymail::domain();
		return is_email( $email ) && '' !== $domain && substr( $email, -strlen( '@' . $domain ) ) === '@' . $domain;
	}
}
