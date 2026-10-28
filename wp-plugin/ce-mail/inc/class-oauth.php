<?php
/** Public-client OAuth authorization code + S256 PKCE. No token passthrough. */
defined( 'ABSPATH' ) || exit;

class CE_Mail_OAuth {
	const COOKIE = '__Host-ce_mail_oauth';

	public static function enabled() { return is_ssl() && (bool) CE_Mail_Settings::get( 'ai_enabled' ); }
	public static function resource() { return rest_url( 'ce-mail/v1/mcp' ); }
	public static function random() { return rtrim( strtr( base64_encode( random_bytes( 48 ) ), '+/', '-_' ), '=' ); }
	public static function hash( $value ) { return hash( 'sha256', $value ); }
	public static function scopes() {
		$out = array( 'mail:read', 'mail:draft', 'mail:organize' );
		if ( CE_Mail_Settings::get( 'ai_send' ) ) { $out[] = 'mail:send'; }
		return $out;
	}
	public static function response( $data, $status = 200 ) {
		return new WP_REST_Response( $data, $status, array( 'Cache-Control' => 'no-store', 'Pragma' => 'no-cache' ) );
	}
	public static function error( $code, $message, $status = 400 ) {
		return self::response( array( 'error' => $code, 'error_description' => $message ), $status );
	}
	public static function str( $data, $key, $default = '' ) {
		return isset( $data[ $key ] ) && is_string( $data[ $key ] ) ? $data[ $key ] : $default;
	}
	public static function register_routes() {
		foreach ( array( 'register', 'token', 'revoke' ) as $name ) {
			register_rest_route( CE_Mail_REST::NS, '/oauth/' . $name, array( 'methods' => 'POST', 'permission_callback' => '__return_true', 'callback' => array( __CLASS__, 'endpoint_' . $name ) ) );
		}
		register_rest_route( CE_Mail_REST::NS, '/assistants', array( 'methods' => 'GET', 'permission_callback' => array( __CLASS__, 'portal_permission' ), 'callback' => array( __CLASS__, 'assistants' ) ) );
		register_rest_route( CE_Mail_REST::NS, '/assistants/(?P<id>[a-f0-9]{32})', array( 'methods' => 'DELETE', 'permission_callback' => array( __CLASS__, 'portal_permission' ), 'callback' => array( __CLASS__, 'assistant_revoke' ) ) );
	}
	public static function portal_permission( $req ) {
		// Guest REST authorization must check a session-bound nonce explicitly as well.
		return CE_Mail_REST::can() && wp_verify_nonce( $req->get_header( 'X-WP-Nonce' ), 'wp_rest' );
	}
	public static function valid_redirect( $uri ) {
		if ( ! is_string( $uri ) || strlen( $uri ) > 2048 || false !== strpos( $uri, '\\' ) || preg_match( '/[\x00-\x20\x7f]/', $uri ) ) { return false; }
		$p = wp_parse_url( $uri );
		if ( ! is_array( $p ) || empty( $p['host'] ) || isset( $p['fragment'] ) || isset( $p['user'] ) || isset( $p['pass'] ) ) { return false; }
		return isset( $p['scheme'] ) && ( 'https' === $p['scheme'] || ( 'http' === $p['scheme'] && in_array( $p['host'], array( 'localhost', '127.0.0.1' ), true ) ) );
	}
	public static function endpoint_register( $req ) {
		if ( ! self::enabled() ) { return self::error( 'temporarily_unavailable', 'AI assistants are disabled.', 503 ); }
		if ( strlen( $req->get_body() ) > 16384 ) { return self::error( 'invalid_client_metadata', 'Registration is too large.' ); }
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : '';
		if ( ! CE_Mail_OAuth_Store::rate( 'register:' . $ip, 20, 3600 ) || ! CE_Mail_OAuth_Store::rate( 'register:site', 200, 3600 ) ) { return self::error( 'temporarily_unavailable', 'Registration limit reached. Try later.', 429 ); }
		$d = $req->get_json_params();
		if ( ! is_array( $d ) || empty( $d['redirect_uris'] ) || ! is_array( $d['redirect_uris'] ) || count( $d['redirect_uris'] ) > 10 ) { return self::error( 'invalid_redirect_uri', 'Register one to ten redirect URLs.' ); }
		foreach ( $d['redirect_uris'] as $uri ) {
			if ( ! self::valid_redirect( $uri ) ) { return self::error( 'invalid_redirect_uri', 'Redirect URLs must use HTTPS, or HTTP on localhost.' ); }
		}
		$grant_types = isset( $d['grant_types'] ) ? $d['grant_types'] : array( 'authorization_code' );
		if ( ! is_array( $grant_types ) || ! in_array( 'authorization_code', $grant_types, true ) ) { return self::error( 'invalid_client_metadata', 'Authorization code grant is required.' ); }
		foreach ( $grant_types as $grant_type ) { if ( ! in_array( $grant_type, array( 'authorization_code', 'refresh_token' ), true ) ) { return self::error( 'invalid_client_metadata', 'Unsupported grant type.' ); } }
		if ( ( isset( $d['token_endpoint_auth_method'] ) && 'none' !== $d['token_endpoint_auth_method'] ) || ( isset( $d['response_types'] ) && $d['response_types'] !== array( 'code' ) ) ) { return self::error( 'invalid_client_metadata', 'Only public clients using authorization code and refresh tokens are supported.' ); }
		$id = bin2hex( random_bytes( 16 ) );
		$name = substr( sanitize_text_field( self::str( $d, 'client_name', 'AI assistant' ) ), 0, 190 );
		try { CE_Mail_OAuth_Store::insert( 'clients', array( 'client_id' => $id, 'name' => $name, 'redirect_uris' => wp_json_encode( array_values( $d['redirect_uris'] ) ), 'created_at' => time() ) ); }
		catch ( Throwable $e ) { return self::error( 'server_error', 'Registration could not be saved.', 503 ); }
		return self::response( array( 'client_id' => $id, 'client_name' => $name, 'redirect_uris' => array_values( $d['redirect_uris'] ), 'token_endpoint_auth_method' => 'none', 'grant_types' => array( 'authorization_code', 'refresh_token' ), 'response_types' => array( 'code' ) ), 201 );
	}
	public static function authorize_request( $d ) {
		global $wpdb;
		$t = CE_Mail_OAuth_Store::table( 'clients' );
		$client = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE client_id=%s", self::str( $d, 'client_id' ) ), ARRAY_A );
		$redirect = self::str( $d, 'redirect_uri' );
		if ( ! $client || ! self::valid_redirect( $redirect ) || ! in_array( $redirect, json_decode( $client['redirect_uris'], true ), true ) ) { throw new InvalidArgumentException( 'Unknown client or unregistered redirect URL.' ); }
		if ( 'code' !== self::str( $d, 'response_type' ) || 'S256' !== self::str( $d, 'code_challenge_method' ) || ! preg_match( '/^[A-Za-z0-9_-]{43}$/D', self::str( $d, 'code_challenge' ) ) ) { throw new InvalidArgumentException( 'Authorization code with S256 PKCE is required.' ); }
		if ( self::resource() !== self::str( $d, 'resource' ) ) { throw new InvalidArgumentException( 'The resource must be this mail connector URL.' ); }
		$state = self::str( $d, 'state' );
		if ( '' === $state || strlen( $state ) > 2048 ) { throw new InvalidArgumentException( 'A state value is required (maximum 2048 bytes).' ); }
		$scope = array_values( array_unique( preg_split( '/ +/', trim( self::str( $d, 'scope', 'mail:read' ) ) ) ) );
		if ( ! $scope || array_diff( $scope, self::scopes() ) ) { throw new InvalidArgumentException( 'The requested permissions are not available.' ); }
		return array( 'client_id' => $client['client_id'], 'client_name' => $client['name'], 'redirect_uri' => $redirect, 'code_challenge' => $d['code_challenge'], 'state' => $state, 'scope' => implode( ' ', $scope ), 'resource' => self::resource() );
	}
	public static function browser() {
		$value = isset( $_COOKIE[ self::COOKIE ] ) && is_string( $_COOKIE[ self::COOKIE ] ) ? $_COOKIE[ self::COOKIE ] : '';
		return preg_match( '/^[A-Za-z0-9_-]{64}$/D', $value ) ? $value : '';
	}
	public static function flow( $flow, $browser ) {
		if ( ! preg_match( '/^[A-Za-z0-9_-]{64}$/D', $flow ) ) { throw new InvalidArgumentException( 'The authorization form is missing its session reference. Start a new connection. [form_missing]' ); }
		if ( ! $browser ) { throw new InvalidArgumentException( 'The browser did not return the secure sign-in cookie. Allow cookies for this site and start a new connection in the same browser. [cookie_missing]' ); }
		global $wpdb;
		$t = CE_Mail_OAuth_Store::table( 'flows' );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE flow_hash=%s", self::hash( $flow ) ), ARRAY_A );
		if ( ! $row ) { throw new InvalidArgumentException( 'This authorization session was already completed or is no longer available. Start a new connection. [flow_missing]' ); }
		if ( ! hash_equals( $row['browser_hash'], self::hash( $browser ) ) ) { throw new InvalidArgumentException( 'This page belongs to a different browser session. Start a new connection and keep it in the same browser. [cookie_changed]' ); }
		if ( (int) $row['expires_at'] <= time() ) { throw new InvalidArgumentException( 'This authorization session reached its ten-minute time limit. Start a new connection. [flow_expired]' ); }
		return $row;
	}
	private static function show_flow( $flow ) {
		// GET renders sign-in/consent; refreshing never resubmits a password or Allow.
		wp_safe_redirect( add_query_arg( array( 'flow' => $flow ), home_url( '/mail/authorize' ) ), 303, 'CE Mail OAuth' );
		exit;
	}
	public static function parse_request( $wp ) {
		$path = wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '', PHP_URL_PATH );
		if ( rtrim( (string) $path, '/' ) !== rtrim( wp_parse_url( home_url( '/mail/authorize' ), PHP_URL_PATH ), '/' ) ) { return; }
		nocache_headers();
		header( 'Cache-Control: no-store, private, max-age=0' );
		header( "Content-Security-Policy: default-src 'none'; style-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'" );
		header( 'X-Frame-Options: DENY' );
		header( 'Referrer-Policy: no-referrer' );
		if ( ! self::enabled() ) { wp_die( 'AI assistants are disabled. Ask the website administrator to enable them.', 'Mail connector', array( 'response' => 503 ) ); }
		global $wpdb;
		$t = CE_Mail_OAuth_Store::table( 'flows' );
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : 'GET';
		$error = '';
		$retry = false;
		try {
			if ( 'GET' === $method && isset( $_GET['flow'] ) ) {
				$flow = self::str( wp_unslash( $_GET ), 'flow' );
				$row = self::flow( $flow, self::browser() );
				$d = json_decode( $row['payload'], true );
			} elseif ( 'GET' === $method ) {
				$d = self::authorize_request( wp_unslash( $_GET ) );
				$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : '';
				if ( ! CE_Mail_OAuth_Store::rate( 'authorize:' . $ip, 40, 900 ) ) { throw new InvalidArgumentException( 'Too many authorization requests. Try later.' ); }
				$browser = self::browser();
				if ( ! $browser ) {
					$browser = self::random();
					setcookie( self::COOKIE, $browser, array( 'expires' => 0, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Lax' ) );
				}
				$flow = self::random();
				// A WordPress user must still have the portal capability to reuse a linked mailbox.
				$c = ( ! is_user_logged_in() || current_user_can( CE_MAIL_CAP ) ) ? CE_Mail_Mailbox::creds( get_current_user_id() ) : null;
				if ( $c && CE_Mail_Session::allowed_email( $c['email'] ) ) {
					$d['email'] = strtolower( $c['email'] );
					$d['secret'] = CE_Mail_Crypto::encrypt( $c['password'] );
				}
				CE_Mail_OAuth_Store::insert( 'flows', array( 'flow_hash' => self::hash( $flow ), 'browser_hash' => self::hash( $browser ), 'payload' => wp_json_encode( $d ), 'expires_at' => time() + 600 ) );
				// Verify cookie roundtrip before showing any password form.
				self::show_flow( $flow );
			} elseif ( 'POST' === $method ) {
				$post = wp_unslash( $_POST );
				$flow = self::str( $post, 'flow' );
				$row = self::flow( $flow, self::browser() );
				$d = json_decode( $row['payload'], true );
				$action = self::str( $post, 'action' );
				if ( 'cancel' === $action ) {
					$wpdb->delete( $t, array( 'flow_hash' => self::hash( $flow ) ) );
					self::redirect( $d, array( 'error' => 'access_denied' ) );
				}
				if ( 'signin' === $action && empty( $d['secret'] ) ) {
					$retry = true;
					$email = strtolower( trim( self::str( $post, 'email' ) ) );
					$password = self::str( $post, 'password' );
					if ( ! CE_Mail_Session::allowed_email( $email ) || '' === $password || strlen( $password ) > 1024 ) { throw new InvalidArgumentException( 'Enter your church mailbox address and password.' ); }
					if ( ! CE_Mail_Session::allow_attempt() || ! CE_Mail_OAuth_Store::rate( 'signin:' . $email, 8, 900 ) ) { throw new InvalidArgumentException( 'Too many sign-in attempts. Wait 15 minutes.' ); }
					try { $imap = CE_Mail_Mailbox::open( $email, $password ); $imap->logout(); }
					catch ( Throwable $e ) { throw new InvalidArgumentException( 'Could not sign in. Check your mailbox credentials and try again.' ); }
					$d['email'] = $email;
					$d['secret'] = CE_Mail_Crypto::encrypt( $password );
					if ( 1 !== $wpdb->update( $t, array( 'payload' => wp_json_encode( $d ), 'expires_at' => time() + 600 ), array( 'flow_hash' => self::hash( $flow ) ) ) ) { throw new RuntimeException(); }
					self::show_flow( $flow );
				} elseif ( 'allow' === $action && ! empty( $d['secret'] ) ) {
					if ( ! CE_Mail_Session::allowed_email( $d['email'] ) ) { throw new InvalidArgumentException( 'This mailbox is no longer allowed.' ); }
					$scope = explode( ' ', $d['scope'] );
					if ( ! CE_Mail_Settings::get( 'ai_send' ) || empty( $post['allow_send'] ) ) { $scope = array_diff( $scope, array( 'mail:send' ) ); }
					if ( ! $scope ) { throw new InvalidArgumentException( 'No permissions were selected. Cancel and reconnect with read permission.' ); }
					$d['scope'] = implode( ' ', $scope );
					$code = self::random();
					if ( false === $wpdb->query( 'START TRANSACTION' ) ) { throw new RuntimeException(); }
					try {
						if ( 1 !== $wpdb->query( $wpdb->prepare( "DELETE FROM $t WHERE flow_hash=%s AND expires_at>%d", self::hash( $flow ), time() ) ) ) { throw new RuntimeException(); }
						CE_Mail_OAuth_Store::insert( 'codes', array( 'code_hash' => self::hash( $code ), 'payload' => wp_json_encode( $d ), 'expires_at' => time() + 600 ) );
						if ( false === $wpdb->query( 'COMMIT' ) ) { throw new RuntimeException(); }
					} catch ( Throwable $e ) { $wpdb->query( 'ROLLBACK' ); throw $e; }
					self::redirect( $d, array( 'code' => $code ) );
				} else { throw new InvalidArgumentException( 'Invalid authorization action. Reconnect from your assistant.' ); }
			} else {
				header( 'Allow: GET, POST' );
				wp_die( 'Method not allowed.', 'Mail connector', array( 'response' => 405 ) );
			}
		} catch ( InvalidArgumentException $e ) { $error = $e->getMessage(); }
		catch ( Throwable $e ) { $retry = false; $error = 'Authorization could not be saved. Reconnect from your assistant and try again. [storage_error]'; }
		status_header( $error ? 400 : 200 );
		include CE_MAIL_DIR . 'templates/authorize.php';
		exit;
	}
	private static function redirect( $d, $params ) {
		$params['state'] = $d['state'];
		// URI was exact-matched to registration before flow creation; never accept one from POST.
		// A cross-origin HTTP redirect after POST can be blocked by form-action 'self'
		// even though the consent was committed. Finish the POST with a document first;
		// its refresh/link is a navigation, not a form submission. Keep the strict CSP.
		$return_url = add_query_arg( $params, $d['redirect_uri'] );
		$cancelled = isset( $params['error'] );
		status_header( 200 );
		include CE_MAIL_DIR . 'templates/authorize-return.php';
		exit;
	}
	public static function valid_verifier( $verifier, $challenge ) {
		return is_string( $verifier ) && preg_match( '/^[A-Za-z0-9._~-]{43,128}$/D', $verifier ) && hash_equals( $challenge, rtrim( strtr( base64_encode( hash( 'sha256', $verifier, true ) ), '+/', '-_' ), '=' ) );
	}
	public static function endpoint_token( $req ) {
		if ( ! self::enabled() ) { return self::error( 'temporarily_unavailable', 'AI assistants are disabled.', 503 ); }
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : '';
		if ( ! CE_Mail_OAuth_Store::rate( 'token:' . $ip, 120, 600 ) ) { return self::error( 'temporarily_unavailable', 'Try again later.', 429 ); }
		if ( strlen( $req->get_body() ) > 8192 || 0 !== strpos( $req->get_header( 'Content-Type' ), 'application/x-www-form-urlencoded' ) ) { return self::error( 'invalid_request', 'Use form-encoded token parameters.' ); }
		$d = $req->get_body_params();
		if ( self::str( $d, 'resource' ) !== self::resource() || ! preg_match( '/^[a-f0-9]{32}$/D', self::str( $d, 'client_id' ) ) ) { return self::error( 'invalid_request', 'The client ID and connector resource are required.' ); }
		global $wpdb;
		$codes = CE_Mail_OAuth_Store::table( 'codes' );
		$tokens = CE_Mail_OAuth_Store::table( 'tokens' );
		$grants = CE_Mail_OAuth_Store::table( 'grants' );
		if ( false === $wpdb->query( 'START TRANSACTION' ) ) { return self::error( 'server_error', 'Token storage is unavailable.', 503 ); }
		try {
			if ( 'authorization_code' === self::str( $d, 'grant_type' ) ) {
				$hash = self::hash( self::str( $d, 'code' ) );
				$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $codes WHERE code_hash=%s FOR UPDATE", $hash ), ARRAY_A );
				$p = $row ? json_decode( $row['payload'], true ) : null;
				if ( ! $p || $row['expires_at'] <= time() || $p['client_id'] !== $d['client_id'] || $p['redirect_uri'] !== self::str( $d, 'redirect_uri' ) || $p['resource'] !== $d['resource'] || ! self::valid_verifier( self::str( $d, 'code_verifier' ), $p['code_challenge'] ) || ! CE_Mail_Session::allowed_email( $p['email'] ) ) { throw new InvalidArgumentException(); }
				if ( 1 !== $wpdb->delete( $codes, array( 'code_hash' => $hash ) ) ) { throw new InvalidArgumentException(); }
				$g = array( 'grant_id' => bin2hex( random_bytes( 16 ) ), 'client_id' => $p['client_id'], 'mailbox_email' => $p['email'], 'mailbox_secret' => $p['secret'], 'scope' => $p['scope'], 'resource' => $p['resource'], 'created_at' => time(), 'last_used_at' => time(), 'expires_at' => time() + 90 * DAY_IN_SECONDS, 'revoked_at' => 0 );
				CE_Mail_OAuth_Store::insert( 'grants', $g );
			} elseif ( 'refresh_token' === self::str( $d, 'grant_type' ) ) {
				$hash = self::hash( self::str( $d, 'refresh_token' ) );
				$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $tokens WHERE token_hash=%s AND kind='refresh' FOR UPDATE", $hash ), ARRAY_A );
				$g = $row ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $grants WHERE grant_id=%s FOR UPDATE", $row['grant_id'] ), ARRAY_A ) : null;
				if ( ! $g || $g['client_id'] !== $d['client_id'] || $g['resource'] !== $d['resource'] || $g['revoked_at'] || $g['expires_at'] <= time() || $row['expires_at'] <= time() || ! CE_Mail_Session::allowed_email( $g['mailbox_email'] ) ) { throw new InvalidArgumentException(); }
				if ( $row['used_at'] ) {
					CE_Mail_OAuth_Store::revoke( $g['grant_id'] );
					$wpdb->query( 'COMMIT' );
					return self::error( 'invalid_grant', 'Refresh token reuse detected. Please reconnect.' );
				}
				if ( isset( $d['scope'] ) && self::str( $d, 'scope' ) !== $g['scope'] ) { throw new InvalidArgumentException(); }
				if ( 1 !== $wpdb->update( $tokens, array( 'used_at' => time() ), array( 'token_hash' => $hash, 'used_at' => 0 ) ) ) { throw new InvalidArgumentException(); }
			} else {
				$wpdb->query( 'ROLLBACK' );
				return self::error( 'unsupported_grant_type', 'Use authorization_code or refresh_token.' );
			}
			$access = self::random(); $refresh = self::random();
			CE_Mail_OAuth_Store::insert( 'tokens', array( 'token_hash' => self::hash( $access ), 'grant_id' => $g['grant_id'], 'kind' => 'access', 'expires_at' => min( time() + 3600, $g['expires_at'] ), 'used_at' => 0 ) );
			CE_Mail_OAuth_Store::insert( 'tokens', array( 'token_hash' => self::hash( $refresh ), 'grant_id' => $g['grant_id'], 'kind' => 'refresh', 'expires_at' => $g['expires_at'], 'used_at' => 0 ) );
			if ( false === $wpdb->query( 'COMMIT' ) ) { throw new RuntimeException(); }
			return self::response( array( 'access_token' => $access, 'refresh_token' => $refresh, 'token_type' => 'Bearer', 'expires_in' => min( 3600, $g['expires_at'] - time() ), 'scope' => $g['scope'] ) );
		} catch ( InvalidArgumentException $e ) { $wpdb->query( 'ROLLBACK' ); return self::error( 'invalid_grant', 'Authorization is invalid or expired. Please reconnect.' ); }
		catch ( Throwable $e ) { $wpdb->query( 'ROLLBACK' ); return self::error( 'server_error', 'Token exchange failed. Please reconnect.', 503 ); }
	}
	public static function bearer( $header ) {
		if ( ! preg_match( '/^Bearer ([A-Za-z0-9_-]{64})$/iD', $header, $m ) ) { return null; }
		global $wpdb;
		$t = CE_Mail_OAuth_Store::table( 'tokens' ); $g = CE_Mail_OAuth_Store::table( 'grants' );
		$hash = self::hash( $m[1] );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT g.*,t.token_hash FROM $t t JOIN $g g ON g.grant_id=t.grant_id WHERE t.token_hash=%s AND t.kind='access' AND t.expires_at>%d AND g.expires_at>%d AND g.revoked_at=0", $hash, time(), time() ), ARRAY_A );
		return $row && hash_equals( $row['token_hash'], $hash ) && $row['resource'] === self::resource() && CE_Mail_Session::allowed_email( $row['mailbox_email'] ) ? $row : null;
	}
	public static function endpoint_revoke( $req ) {
		// Revocation remains available while the kill switch is off.
		global $wpdb;
		$d = $req->get_body_params();
		$t = CE_Mail_OAuth_Store::table( 'tokens' ); $g = CE_Mail_OAuth_Store::table( 'grants' );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT g.grant_id FROM $t t JOIN $g g ON g.grant_id=t.grant_id WHERE t.token_hash=%s AND g.client_id=%s", self::hash( self::str( $d, 'token' ) ), self::str( $d, 'client_id' ) ), ARRAY_A );
		try { if ( $row ) { CE_Mail_OAuth_Store::revoke( $row['grant_id'] ); } }
		catch ( Throwable $e ) { return self::error( 'server_error', 'Revocation failed. Try again.', 503 ); }
		return self::response( new stdClass() );
	}
	public static function assistants() {
		global $wpdb;
		$c = CE_Mail_Mailbox::creds( get_current_user_id() );
		if ( ! $c ) { return self::response( array( 'items' => array() ) ); }
		$g = CE_Mail_OAuth_Store::table( 'grants' ); $t = CE_Mail_OAuth_Store::table( 'clients' );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT g.grant_id,g.mailbox_email,g.scope,g.created_at,g.last_used_at,c.name FROM $g g LEFT JOIN $t c ON c.client_id=g.client_id WHERE g.mailbox_email=%s AND g.revoked_at=0 AND g.expires_at>%d ORDER BY g.created_at DESC LIMIT 100", strtolower( $c['email'] ), time() ), ARRAY_A );
		return self::response( array( 'items' => $rows ? $rows : array() ) );
	}
	public static function assistant_revoke( $req ) {
		global $wpdb;
		$c = CE_Mail_Mailbox::creds( get_current_user_id() ); $t = CE_Mail_OAuth_Store::table( 'grants' );
		$row = $c ? $wpdb->get_var( $wpdb->prepare( "SELECT grant_id FROM $t WHERE grant_id=%s AND mailbox_email=%s", $req['id'], strtolower( $c['email'] ) ) ) : null;
		if ( ! $row ) { return new WP_Error( 'not_found', 'Connection not found.', array( 'status' => 404 ) ); }
		try { CE_Mail_OAuth_Store::revoke( $row ); }
		catch ( Throwable $e ) { return new WP_Error( 'revoke_failed', 'Revocation failed. Try again.', array( 'status' => 503 ) ); }
		return self::response( array( 'revoked' => true ) );
	}
}
