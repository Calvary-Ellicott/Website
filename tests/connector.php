<?php
/** Offline security regression tests. SQLite adapter tests persistence, not MySQL locking. */
error_reporting( E_ALL );
set_error_handler( function ( $severity, $message, $file, $line ) { throw new ErrorException( $message, 0, $severity, $file, $line ); } );
define( 'ABSPATH', __DIR__ . '/' ); define( 'ARRAY_A', 'ARRAY_A' ); define( 'DAY_IN_SECONDS', 86400 ); define( 'CE_MAIL_VERSION', '0.4.4' );
$options = array( 'ai_enabled' => 1, 'ai_send' => 0 ); $checks = 0;
function check( $condition, $label ) { global $checks; if ( ! $condition ) { throw new RuntimeException( 'FAILED: ' . $label ); } $checks++; }
function rejected( $fn, $label ) { try { $fn(); } catch ( InvalidArgumentException $e ) { check( true, $label ); return; } throw new RuntimeException( 'FAILED: ' . $label ); }
function is_ssl() { return true; }
function home_url( $path = '' ) { return 'https://church.example' . $path; }
function rest_url( $path = '' ) { return home_url( '/wp-json/' . $path ); }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function wp_json_encode( $data ) { return json_encode( $data ); }
function sanitize_text_field( $value ) { return strip_tags( $value ); }
function get_option( $name, $default = null ) { global $options; return 'ce_mail_settings' === $name ? $options : $default; }
function get_current_user_id() { return 0; }
function wp_verify_nonce( $nonce, $action ) { return 'session-nonce' === $nonce; }
function wp_salt( $scheme ) { return 'test-only-salt-' . $scheme; }
function is_email( $value ) { return filter_var( $value, FILTER_VALIDATE_EMAIL ); }
function untrailingslashit( $value ) { return rtrim( $value, '/' ); }
class CE_Mail_REST { const NS = 'ce-mail/v1'; public static function can() { return true; } }
class CE_Mail_Purelymail { public static function domain() { return 'church.example'; } }
class CE_Mail_Mailbox {
	public static function creds( $id ) { return array( 'email' => 'other@church.example', 'password' => 'fixture' ); }
	public static function folders( $imap ) { return array( array( 'role' => 'inbox', 'name' => 'INBOX' ), array( 'role' => 'archive', 'name' => 'Archive' ) ); }
	public static function open( $email, $password ) { if ( 'fixture-valid' !== $password ) { throw new RuntimeException( 'Fixture login rejected' ); } return new FixtureIMAP( 'example.invalid' ); }
}
class WP_Error { public $code; public function __construct( $code, $message, $data ) { $this->code = $code; } }
class WP_REST_Response {
	public $data; public $status; public $headers;
	public function __construct( $data, $status = 200, $headers = array() ) { $this->data = $data; $this->status = $status; $this->headers = $headers; }
	public function header( $key, $value ) { $this->headers[ $key ] = $value; }
	public function get_status() { return $this->status; }
}
class Request {
	public $params; public $headers; public $raw; public $method; public $route = '/ce-mail/v1/mcp';
	public function __construct( $params, $type = 'application/x-www-form-urlencoded', $method = 'POST' ) { $this->params = $params; $this->headers = array( 'content-type' => $type ); $this->raw = 'application/json' === $type ? json_encode( $params ) : http_build_query( $params ); $this->method = $method; }
	public function get_body_params() { return $this->params; }
	public function get_json_params() { return $this->params; }
	public function get_body() { return $this->raw; }
	public function get_method() { return $this->method; }
	public function get_route() { return $this->route; }
	public function get_header( $name ) { return isset( $this->headers[ strtolower( $name ) ] ) ? $this->headers[ strtolower( $name ) ] : null; }
}
class Database {
	public $prefix = 'wp_'; public $pdo; public $insert_id;
	public function __construct() { $this->pdo = new PDO( 'sqlite::memory:' ); $this->pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION ); }
	public function prepare( $sql, ...$args ) { $i = 0; return preg_replace_callback( '/%[sd]/', function ( $m ) use ( $args, &$i ) { $v = $args[$i++]; return '%d' === $m[0] ? (string) (int) $v : $this->pdo->quote( $v ); }, $sql ); }
	private function sql( $sql ) { $sql = str_replace( ' FOR UPDATE', '', $sql ); $sql = str_replace( 'START TRANSACTION', 'BEGIN TRANSACTION', $sql ); return str_replace( 'ON DUPLICATE KEY UPDATE hits=hits+1', 'ON CONFLICT(bucket) DO UPDATE SET hits=hits+1', $sql ); }
	public function query( $sql ) { return $this->pdo->exec( $this->sql( $sql ) ); }
	public function get_row( $sql, $format = null ) { $row = $this->pdo->query( $this->sql( $sql ) )->fetch( PDO::FETCH_ASSOC ); return $row ?: null; }
	public function get_var( $sql ) { return $this->pdo->query( $this->sql( $sql ) )->fetchColumn(); }
	public function get_results( $sql, $format = null ) { return $this->pdo->query( $this->sql( $sql ) )->fetchAll( PDO::FETCH_ASSOC ); }
	public function insert( $table, $row ) { $s = $this->pdo->prepare( 'INSERT INTO ' . $table . ' (' . implode( ',', array_keys( $row ) ) . ') VALUES (' . implode( ',', array_fill( 0, count( $row ), '?' ) ) . ')' ); $s->execute( array_values( $row ) ); $this->insert_id = $this->pdo->lastInsertId(); return $s->rowCount(); }
	public function update( $table, $row, $where ) { $set = implode( ',', array_map( function ( $k ) { return $k . '=?'; }, array_keys( $row ) ) ); $w = implode( ' AND ', array_map( function ( $k ) { return $k . '=?'; }, array_keys( $where ) ) ); $s = $this->pdo->prepare( "UPDATE $table SET $set WHERE $w" ); $s->execute( array_merge( array_values( $row ), array_values( $where ) ) ); return $s->rowCount(); }
	public function delete( $table, $where ) { $w = implode( ' AND ', array_map( function ( $k ) { return $k . '=?'; }, array_keys( $where ) ) ); $s = $this->pdo->prepare( "DELETE FROM $table WHERE $w" ); $s->execute( array_values( $where ) ); return $s->rowCount(); }
}
$wpdb = new Database();
function dbDelta( $sql ) {
	global $wpdb;
	$sql = preg_replace( '/,\nKEY [^\n]+/', '', $sql );
	$sql = str_replace( "id bigint unsigned NOT NULL AUTO_INCREMENT", 'id INTEGER PRIMARY KEY AUTOINCREMENT', $sql );
	$sql = str_replace( ",\nPRIMARY KEY  (id)", '', $sql );
	$sql = preg_replace( '/ ENGINE=InnoDB .*;$/', ';', $sql );
	$wpdb->query( $sql );
}
foreach ( array( 'settings', 'session', 'crypto', 'oauth-store', 'oauth', 'mcp', 'mcp-mailbox', 'imap', 'mime', 'discovery' ) as $class ) { require __DIR__ . '/../wp-plugin/ce-mail/inc/class-' . $class . '.php'; }
CE_Mail_OAuth_Store::install( '' );
$flow_id = CE_Mail_OAuth::random(); $browser_id = CE_Mail_OAuth::random();
CE_Mail_OAuth_Store::insert( 'flows', array( 'flow_hash' => CE_Mail_OAuth::hash( $flow_id ), 'browser_hash' => CE_Mail_OAuth::hash( $browser_id ), 'payload' => '{"stage":"signin"}', 'expires_at' => time() + 600 ) );
check( '{"stage":"signin"}' === CE_Mail_OAuth::flow( $flow_id, $browser_id )['payload'], 'Cookie-bound flow survives a new page request' );
function flow_rejected( $flow, $browser, $reason ) {
	try { CE_Mail_OAuth::flow( $flow, $browser ); }
	catch ( InvalidArgumentException $e ) { check( false !== strpos( $e->getMessage(), '[' . $reason . ']' ), 'Specific flow diagnostic: ' . $reason ); return; }
	throw new RuntimeException( 'Expected flow rejection: ' . $reason );
}
flow_rejected( '', $browser_id, 'form_missing' );
flow_rejected( $flow_id, '', 'cookie_missing' );
flow_rejected( $flow_id, CE_Mail_OAuth::random(), 'cookie_changed' );
flow_rejected( CE_Mail_OAuth::random(), $browser_id, 'flow_missing' );
$wpdb->update( CE_Mail_OAuth_Store::table( 'flows' ), array( 'payload' => '{"stage":"consent"}', 'expires_at' => time() + 600 ), array( 'flow_hash' => CE_Mail_OAuth::hash( $flow_id ) ) );
check( '{"stage":"consent"}' === CE_Mail_OAuth::flow( $flow_id, $browser_id )['payload'], 'Consent reload retains cookie binding and server-side payload' );
$wpdb->update( CE_Mail_OAuth_Store::table( 'flows' ), array( 'expires_at' => time() - 1 ), array( 'flow_hash' => CE_Mail_OAuth::hash( $flow_id ) ) );
flow_rejected( $flow_id, $browser_id, 'flow_expired' );
check( CE_Mail_OAuth::valid_redirect( 'https://client.example/callback?source=mail' ), 'HTTPS redirect accepted' );
check( CE_Mail_OAuth::valid_redirect( 'http://localhost:6274/callback' ), 'Inspector loopback accepted' );
foreach ( array( 'http://evil.example/cb', 'https://good.example/#fragment', 'https://user:password@good.example/', "https://good.example/\r\nLocation:evil", 'javascript:alert(1)' ) as $url ) { check( ! CE_Mail_OAuth::valid_redirect( $url ), 'Unsafe redirect refused' ); }
$client = CE_Mail_OAuth::endpoint_register( new Request( array( 'client_name' => 'Fixture', 'redirect_uris' => array( 'https://client.example/callback' ), 'token_endpoint_auth_method' => 'none' ), 'application/json' ) );
check( 201 === $client->status && 32 === strlen( $client->data['client_id'] ), 'Client registered' );
$cid = $client->data['client_id'];
$verifier = str_repeat( 'a', 43 ); $challenge = rtrim( strtr( base64_encode( hash( 'sha256', $verifier, true ) ), '+/', '-_' ), '=' );
$auth = array( 'client_id' => $cid, 'redirect_uri' => 'https://client.example/callback', 'response_type' => 'code', 'code_challenge' => $challenge, 'code_challenge_method' => 'S256', 'state' => 'opaque', 'scope' => 'mail:read mail:draft', 'resource' => CE_Mail_OAuth::resource() );
$payload = CE_Mail_OAuth::authorize_request( $auth );
check( 'mail:read mail:draft' === $payload['scope'], 'Requested scopes retained' );
foreach ( array( 'redirect_uri' => 'https://client.example/other', 'code_challenge_method' => 'plain', 'resource' => 'https://other.example', 'scope' => 'mail:send', 'state' => '' ) as $key => $value ) { $bad = $auth; $bad[$key] = $value; rejected( function () use ( $bad ) { CE_Mail_OAuth::authorize_request( $bad ); }, 'Authorization validation: ' . $key ); }
check( CE_Mail_OAuth::valid_verifier( $verifier, $challenge ) && ! CE_Mail_OAuth::valid_verifier( str_repeat( 'b', 43 ), $challenge ), 'S256 verifier' );
$payload['email'] = 'office@church.example'; $payload['secret'] = CE_Mail_Crypto::encrypt( 'test-password' );
check( 'test-password' === CE_Mail_Crypto::decrypt( $payload['secret'] ), 'Encrypted mailbox credential roundtrip' );
function code_fixture( $payload ) { $code = CE_Mail_OAuth::random(); CE_Mail_OAuth_Store::insert( 'codes', array( 'code_hash' => CE_Mail_OAuth::hash( $code ), 'payload' => json_encode( $payload ), 'expires_at' => time() + 600 ) ); return $code; }
$code = code_fixture( $payload );
$exchange = array( 'grant_type' => 'authorization_code', 'code' => $code, 'client_id' => $cid, 'redirect_uri' => $auth['redirect_uri'], 'resource' => CE_Mail_OAuth::resource(), 'code_verifier' => $verifier );
$wrong = $exchange; $wrong['code_verifier'] = str_repeat( 'b', 43 );
check( 'invalid_grant' === CE_Mail_OAuth::endpoint_token( new Request( $wrong ) )->data['error'], 'Wrong PKCE cannot exchange code' );
$wrong = $exchange; $wrong['resource'] = 'https://other.example';
check( 'invalid_request' === CE_Mail_OAuth::endpoint_token( new Request( $wrong ) )->data['error'], 'Wrong audience refused' );
$token = CE_Mail_OAuth::endpoint_token( new Request( $exchange ) );
check( 200 === $token->status && 64 === strlen( $token->data['access_token'] ), 'Code exchanged' );
check( 'invalid_grant' === CE_Mail_OAuth::endpoint_token( new Request( $exchange ) )->data['error'], 'Code replay refused' );
$bearer = 'Bearer ' . $token->data['access_token']; $grant = CE_Mail_OAuth::bearer( $bearer );
check( $grant && 'office@church.example' === $grant['mailbox_email'], 'Token bound to authorized mailbox' );
check( ! CE_Mail_OAuth::bearer( 'Bearer arbitrary' ), 'Unrecognized bearer refused' );
check( false === strpos( json_encode( $wpdb->get_results( 'SELECT * FROM wp_ce_mail_oauth_tokens' ) ), $token->data['access_token'] ), 'Only hashes stored' );
check( ! in_array( 'send_message', array_column( CE_Mail_MCP::tools( 'mail:read mail:draft' ), 'name' ), true ), 'Send absent without scope' );
$options['ai_send'] = 1;
check( in_array( 'send_message', array_column( CE_Mail_MCP::tools( 'mail:send' ), 'name' ), true ), 'Send requires enabled setting and scope' );
$options['ai_send'] = 0;
check( ! CE_Mail_MCP::tools( 'mail:send' ), 'Send kill switch overrides existing scope' );
$rpc = new Request( array( 'jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => array( 'protocolVersion' => '2025-06-18' ) ), 'application/json' ); $rpc->headers['authorization'] = $bearer;
check( '2025-06-18' === CE_Mail_MCP::dispatch( $rpc )->data['result']['protocolVersion'], 'MCP initialize' );
$rpc->headers['origin'] = 'https://chatgpt.com';
check( '2025-06-18' === CE_Mail_MCP::dispatch( $rpc )->data['result']['protocolVersion'], 'ChatGPT browser origin can initialize with valid bearer' );
$unauthenticated = new Request( array( 'jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping' ), 'application/json' );
$unauthenticated->headers['origin'] = 'https://chatgpt.com';
$challenge_response = CE_Mail_MCP::dispatch( $unauthenticated );
check( 401 === $challenge_response->status && isset( $challenge_response->headers['WWW-Authenticate'] ), 'ChatGPT browser origin receives discovery challenge before login' );
$cors_response = CE_Mail_MCP::cors( $challenge_response, null, $unauthenticated );
check( false !== strpos( $cors_response->headers['Access-Control-Expose-Headers'], 'WWW-Authenticate' ), 'Browser can read discovery challenge header' );
check( false !== strpos( $cors_response->headers['Access-Control-Allow-Headers'], 'MCP-Protocol-Version' ), 'Browser preflight allows protocol header' );
unset( $unauthenticated->headers['origin'] );
check( null === $unauthenticated->get_header( 'Origin' ), 'Request stub matches WordPress absent header behavior' );
foreach ( array( 'GET', 'POST' ) as $method ) {
	$unauthenticated->method = $method;
	$server_challenge = CE_Mail_MCP::dispatch( $unauthenticated );
	check( 401 === $server_challenge->status && isset( $server_challenge->headers['WWW-Authenticate'] ), 'Server-to-server ' . $method . ' receives OAuth challenge without Origin' );
}
check( CE_Mail_MCP::allowed_origin( null ) && CE_Mail_MCP::allowed_origin( '' ), 'Absent Origin allowed while literal null origin remains untrusted' );
check( null === CE_Mail_OAuth::bearer( null ), 'Absent Authorization header handled without PHP warnings' );
foreach ( array( 'https://chatgpt.com.evil.example', 'http://chatgpt.com', 'https://evil.example', 'null', 'https://chatgpt.com:444' ) as $bad_origin ) {
	check( ! CE_Mail_MCP::allowed_origin( $bad_origin ), 'Unrelated/spoofed origins remain blocked' );
}
$root_metadata = CE_Mail_Discovery::document( '/.well-known/oauth-protected-resource' );
check( $root_metadata === CE_Mail_Discovery::document( '/.well-known/oauth-protected-resource/wp-json/ce-mail/v1/mcp' ), 'Resource-specific discovery matches root document' );
check( null === CE_Mail_Discovery::document( '/.well-known/oauth-protected-resource/wp-json/other/mcp' ), 'Metadata alias is limited to this resource' );
$rpc->headers['origin'] = 'https://evil.example'; check( 403 === CE_Mail_MCP::dispatch( $rpc )->status, 'Cross-origin request refused' ); unset( $rpc->headers['origin'] );
$rpc->raw = '{'; check( -32700 === CE_Mail_MCP::dispatch( $rpc )->data['error']['code'], 'Invalid JSON error' );
check( -32700 === CE_Mail_MCP::pre_dispatch( null, null, $rpc )->data['error']['code'], 'REST bridge handles malformed JSON' );
$rpc->route = '/ce-mail/v1/messages'; check( null === CE_Mail_MCP::pre_dispatch( null, null, $rpc ), 'REST bridge leaves portal routes alone' ); $rpc->route = '/ce-mail/v1/mcp';
check( 'existing-result' === CE_Mail_MCP::pre_dispatch( 'existing-result', null, $rpc ), 'REST bridge preserves upstream rejection' );
$rpc->raw = json_encode( array( 'jsonrpc' => '2.0', 'method' => 'notifications/initialized' ) ); check( 202 === CE_Mail_MCP::dispatch( $rpc )->status, 'Notification accepted without result' );
check( CE_Mail_MCP::empty_response( false, CE_Mail_MCP::dispatch( $rpc ), $rpc ), 'Notification body suppressed by REST bridge' );
$rpc->raw = json_encode( array( 'jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/call', 'params' => array( 'name' => 'send_message', 'arguments' => new stdClass() ) ) );
check( CE_Mail_MCP::dispatch( $rpc )->data['result']['isError'], 'Unauthorized tool call refused before mailbox access' );
check( 1 === (int) $wpdb->get_var( 'SELECT COUNT(*) FROM wp_ce_mail_oauth_audit' ), 'Rejected tool call audited' );
$refresh = array( 'grant_type' => 'refresh_token', 'refresh_token' => $token->data['refresh_token'], 'resource' => CE_Mail_OAuth::resource(), 'client_id' => $cid );
$wrong = $refresh; $wrong['client_id'] = str_repeat( 'f', 32 ); check( 'invalid_grant' === CE_Mail_OAuth::endpoint_token( new Request( $wrong ) )->data['error'], 'Refresh cannot cross clients' );
$rotated = CE_Mail_OAuth::endpoint_token( new Request( $refresh ) ); check( 200 === $rotated->status && $rotated->data['refresh_token'] !== $refresh['refresh_token'], 'Refresh rotates' );
check( 'invalid_grant' === CE_Mail_OAuth::endpoint_token( new Request( $refresh ) )->data['error'], 'Replayed refresh refused' );
check( ! CE_Mail_OAuth::bearer( 'Bearer ' . $rotated->data['access_token'] ) && ! CE_Mail_OAuth::bearer( $bearer ), 'Refresh replay revokes all access tokens' );
$exchange['code'] = code_fixture( $payload ); $token = CE_Mail_OAuth::endpoint_token( new Request( $exchange ) );
$g = CE_Mail_OAuth::bearer( 'Bearer ' . $token->data['access_token'] );
check( array() === CE_Mail_OAuth::assistants()->data['items'], 'Other mailbox cannot list this grant' );
check( CE_Mail_OAuth::assistant_revoke( array( 'id' => $g['grant_id'] ) ) instanceof WP_Error, 'Other mailbox cannot revoke this grant' );
$options['ai_enabled'] = 0;
check( 503 === CE_Mail_MCP::dispatch( $rpc )->status, 'Site kill switch blocks MCP' );
check( 503 === CE_Mail_OAuth::endpoint_token( new Request( $exchange ) )->status, 'Site kill switch blocks new tokens' );
check( isset( CE_Mail_Discovery::document( '/.well-known/oauth-authorization-server' )['issuer'] ), 'Discovery remains available' );
CE_Mail_OAuth::endpoint_revoke( new Request( array( 'token' => $token->data['refresh_token'], 'client_id' => $cid ) ) );
check( ! CE_Mail_OAuth::bearer( 'Bearer ' . $token->data['access_token'] ), 'Revocation works while disabled' );
check( CE_Mail_OAuth_Store::rate( 'fixture', 2, 60 ) && CE_Mail_OAuth_Store::rate( 'fixture', 2, 60 ) && ! CE_Mail_OAuth_Store::rate( 'fixture', 2, 60 ), 'Atomic rate counter limit' );
check( array( 'folder:with:colon', 42 ) === CE_Mail_MCP_Mailbox::split_id( 'folder:with:colon:42' ), 'Folder IDs support colons' );
foreach ( array( 'INBOX:0', 'INBOX:1,2', "INBOX\r\n:1", 'INBOX:4294967296' ) as $id ) { rejected( function () use ( $id ) { CE_Mail_MCP_Mailbox::split_id( $id ); }, 'Malformed UID refused' ); }
check( '29-Feb-2024' === CE_Mail_MCP_Mailbox::since( '2024-02-29' ), 'ISO date converted' );
rejected( function () { CE_Mail_MCP_Mailbox::since( '2026-02-29' ); }, 'Invalid leap day refused' );
$tool = CE_Mail_MCP::tools( 'mail:read' )[1];
rejected( function () use ( $tool ) { CE_Mail_MCP::validate( (object) array( 'limit' => 51 ), $tool['inputSchema'] ); }, 'Page limit enforced' );
rejected( function () use ( $tool ) { CE_Mail_MCP::validate( (object) array( 'unread_only' => 'false' ), $tool['inputSchema'] ); }, 'Boolean types enforced' );
$imap = new CE_Mail_IMAP( 'example.invalid' ); rejected( function () use ( $imap ) { $imap->quote( "INBOX\r\nEXPUNGE" ); }, 'IMAP command injection refused' );
check( 'Hello' === CE_Mail_MCP_Mailbox::without_quotes( "Hello\n> quoted" ), 'Quoted lines omitted' );
CE_Mail_OAuth_Store::sweep();
function wp_check_invalid_utf8( $s, $strip = false ) { return $s; }
class FixtureIMAP extends CE_Mail_IMAP {
	public $flags = 0; public $moves = 0; public $safe_move = false;
	public function select( $folder ) { $this->selected = $folder; return 1; }
	public function fetch_raw( $uid, $max_bytes = 8000000 ) { return "From: Sender <sender@example.org>\r\nTo: office@church.example\r\nSubject: Fixture\r\nMessage-ID: <fixture@example.org>\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\nHello\n> Previous message"; }
	public function store_flag( array $uids, $flag, $add = true ) { $this->flags++; return true; }
	public function has_cap( $cap ) { return $this->safe_move; }
	public function fetch_summaries( array $uids, $snippets = false ) { return array( 42 => array( 'uid' => 42 ) ); }
	public function move( array $uids, $dest ) { $this->moves++; return true; }
}
$fixture = new FixtureIMAP( 'example.invalid' );
$mailbox = new CE_Mail_MCP_Mailbox( array() ); $property = new ReflectionProperty( $mailbox, 'imap' ); $property->setAccessible( true ); $property->setValue( $mailbox, $fixture );
$message = $mailbox->call( 'get_message', array( 'id' => 'INBOX:42' ) );
check( 'Hello' === $message['text'] && 0 === $fixture->flags, 'Reading omits quotes and does not mark seen' );
check( false !== strpos( $mailbox->call( 'get_message', array( 'id' => 'INBOX:42', 'include_quoted' => true ) )['text'], 'Previous message' ), 'Quoted text opt-in' );
rejected( function () use ( $mailbox ) { $mailbox->call( 'archive', array( 'ids' => array( 'INBOX:42' ) ) ); }, 'Unsafe archive fallback refused' );
check( 0 === $fixture->moves && 0 === $fixture->flags, 'Unsafe archive made no mutations' );
$fixture->safe_move = true;
rejected( function () use ( $mailbox ) { $mailbox->call( 'archive', array( 'ids' => array( 'INBOX:42', 'bad-id' ) ) ); }, 'All IDs validated before mutation' );
check( 0 === $fixture->moves, 'Invalid batch made no mutations' );
check( 1 === $mailbox->call( 'archive', array( 'ids' => array( 'INBOX:42', 'INBOX:42' ) ) )['changed'] && 1 === $fixture->moves, 'Safe move deduplicates IDs' );
echo "PASS: $checks connector security and protocol checks\n";
