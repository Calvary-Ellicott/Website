<?php
/** Exercise the real authorize handler/template in isolated CLI requests with fake mail login. */
ob_start();
require __DIR__ . '/connector.php';
ob_end_clean();
define( 'CE_MAIL_DIR', __DIR__ . '/../wp-plugin/ce-mail/' );
define( 'CE_MAIL_URL', 'https://church.example/plugin/' );
function nocache_headers() {}
function status_header( $status ) { $GLOBALS['http_status'] = $status; }
function wp_unslash( $data ) { return $data; }
function esc_url( $value ) { return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8', false ); }
function esc_attr( $value ) { return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8', false ); }
function esc_html( $value ) { return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' ); }
function add_query_arg( $params, $url ) { return $url . '?' . http_build_query( $params ); }
function wp_safe_redirect( $url, $status, $by ) { $GLOBALS['redirect_result'] = array( $url, $status ); }
function wp_redirect( $url, $status, $by ) { $GLOBALS['redirect_result'] = array( $url, $status ); }
function is_user_logged_in() { return false; }
function get_transient( $key ) { return 0; }
function set_transient( $key, $value, $seconds ) { return true; }
$options['ai_enabled'] = 1;
$mode = isset( $argv[1] ) ? $argv[1] : 'signin';
$flow_id = CE_Mail_OAuth::random(); $browser_id = CE_Mail_OAuth::random();
$payload = $auth;
$payload['client_name'] = 'Fixture consent client';
if ( in_array( $mode, array( 'consent', 'allow' ), true ) ) { $payload['email'] = 'office@church.example'; $payload['secret'] = CE_Mail_Crypto::encrypt( 'fixture-valid' ); }
CE_Mail_OAuth_Store::insert( 'flows', array( 'flow_hash' => CE_Mail_OAuth::hash( $flow_id ), 'browser_hash' => CE_Mail_OAuth::hash( $browser_id ), 'payload' => json_encode( $payload ), 'expires_at' => time() + 600 ) );
$_COOKIE = array( CE_Mail_OAuth::COOKIE => $browser_id );
$_SERVER['REQUEST_URI'] = '/mail/authorize';
$_SERVER['REQUEST_METHOD'] = in_array( $mode, array( 'consent', 'initial', 'missing-cookie' ), true ) ? 'GET' : 'POST';
$_GET = 'initial' === $mode ? $auth : array( 'flow' => $flow_id );
$_POST = array( 'flow' => $flow_id, 'action' => 'cancel' === $mode ? 'cancel' : ( 'allow' === $mode ? 'allow' : 'signin' ), 'email' => 'office@church.example', 'password' => 'wrong-password' === $mode ? 'fixture-wrong' : 'fixture-valid' );
if ( 'missing-cookie' === $mode ) { $_COOKIE = array(); }
ob_start();
register_shutdown_function( function () use ( $mode, $flow_id, $browser_id ) {
	global $wpdb;
	$html = ob_get_clean(); $redirect = isset( $GLOBALS['redirect_result'] ) ? $GLOBALS['redirect_result'] : null;
	try {
		if ( 'initial' === $mode || 'signin' === $mode ) {
			check( $redirect && 303 === $redirect[1] && 0 === strpos( $redirect[0], 'https://church.example/mail/authorize?flow=' ), 'Redirect to GET flow page' );
			if ( 'signin' === $mode ) {
				$row = CE_Mail_OAuth::flow( $flow_id, $browser_id );
				check( isset( json_decode( $row['payload'], true )['secret'] ), 'Successful login persists encrypted credentials' );
			}
		} elseif ( 'consent' === $mode ) {
			check( false !== strpos( $html, 'value="allow"' ) && false === strpos( $html, 'type="password"' ) && false !== strpos( $html, 'It cannot send mail.' ), 'Consent GET shows permissions without password or send option' );
		} elseif ( 'wrong-password' === $mode ) {
			check( false !== strpos( $html, 'Try signing in again' ) && false === strpos( $html, 'fixture-wrong' ), 'Wrong password has safe retry without echoing credentials' );
			check( null !== CE_Mail_OAuth::flow( $flow_id, $browser_id ), 'Retry preserves the original flow' );
		} elseif ( 'missing-cookie' === $mode ) {
			check( false !== strpos( $html, '[cookie_missing]' ) && false === strpos( $html, 'type="password"' ), 'Cookie failure detected before password prompt' );
		} elseif ( 'allow' === $mode || 'cancel' === $mode ) {
			check( ! $redirect && 200 === $GLOBALS['http_status'], 'POST completes with a document rather than a blocked cross-origin redirect' );
			check( false !== strpos( $html, 'http-equiv="refresh"' ) && false !== strpos( $html, 'Continue to assistant' ) && false === strpos( $html, '<form' ), 'Automatic navigation and clickable fallback, without resubmittable consent' );
			preg_match( '/<a[^>]+href="([^"]+)"/', $html, $match );
			$url = html_entity_decode( $match[1], ENT_QUOTES, 'UTF-8' );
			check( 0 === strpos( $url, 'https://client.example/callback?' ), 'Return uses registered callback' );
			parse_str( parse_url( $url, PHP_URL_QUERY ), $query );
			check( 'opaque' === $query['state'], 'Return preserves OAuth state' );
			if ( 'allow' === $mode ) { check( isset( $query['code'] ) && 64 === strlen( $query['code'] ), 'Return includes one-use authorization code' ); }
			else { check( 'access_denied' === $query['error'] && ! isset( $query['code'] ), 'Cancellation returns error without a code' ); }
			flow_rejected( $flow_id, $browser_id, 'flow_missing' );
		} else { throw new RuntimeException( 'Unknown test mode' ); }
		echo 'PASS: authorize handler ' . $mode . "\n";
	} catch ( Throwable $e ) { fwrite( STDERR, $e->getMessage() . "\n" ); exit( 1 ); }
} );
CE_Mail_OAuth::parse_request( new stdClass() );
