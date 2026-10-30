<?php
/**
 * Public OAuth and protected-resource metadata, including while access is disabled.
 */

defined( 'ABSPATH' ) || exit;

class CE_Mail_Discovery {

	public static function document( $path ) {
		$issuer = untrailingslashit( home_url( '/' ) );
		$oauth  = rest_url( 'ce-mail/v1/oauth' );
		$stage  = array( 'ce_mail_stage' => 'connector', 'ce_mail_version' => CE_MAIL_VERSION );
		if ( '/.well-known/oauth-authorization-server' === $path ) {
			return array_merge( array(
				'issuer'                                => $issuer,
				'authorization_endpoint'                => home_url( '/mail/authorize' ),
				'token_endpoint'                        => $oauth . '/token',
				'registration_endpoint'                 => $oauth . '/register',
				'revocation_endpoint'                   => $oauth . '/revoke',
				'response_types_supported'              => array( 'code' ),
				'grant_types_supported'                 => array( 'authorization_code', 'refresh_token' ),
				'code_challenge_methods_supported'      => array( 'S256' ),
				'token_endpoint_auth_methods_supported' => array( 'none' ),
				'scopes_supported'                      => CE_Mail_OAuth::scopes(),
			), $stage );
		}
		$resource_path = (string) wp_parse_url( rest_url( 'ce-mail/v1/mcp' ), PHP_URL_PATH );
		if ( '/.well-known/oauth-protected-resource' === $path || '/.well-known/oauth-protected-resource' . $resource_path === $path ) {
			return array_merge( array(
				'resource'                => rest_url( 'ce-mail/v1/mcp' ),
				'authorization_servers'   => array( $issuer ),
				'scopes_supported'        => CE_Mail_OAuth::scopes(),
				'bearer_methods_supported' => array( 'header' ),
			), $stage );
		}
		return null;
	}

	public static function parse_request( $wp ) {
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
		$path = (string) wp_parse_url( $uri, PHP_URL_PATH );
		$data = self::document( $path );
		if ( null === $data ) {
			return;
		}
		nocache_headers();
		header( 'X-Content-Type-Options: nosniff' );
		// Metadata is public, contains no credentials, and must be readable by browser clients.
		header( 'Access-Control-Allow-Origin: *' );
		header( 'Access-Control-Allow-Methods: GET, HEAD, OPTIONS' );
		header( 'Access-Control-Allow-Headers: MCP-Protocol-Version' );
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : 'GET';
		if ( 'OPTIONS' === $method ) { status_header( 204 ); exit; }
		if ( 'GET' !== $method && 'HEAD' !== $method ) {
			header( 'Allow: GET, HEAD, OPTIONS' );
			wp_send_json( array( 'error' => 'method_not_allowed' ), 405 );
		}
		if ( 'HEAD' === $method ) {
			status_header( 200 );
			header( 'Content-Type: application/json; charset=UTF-8' );
			exit;
		}
		wp_send_json( $data, 200 );
	}
}
