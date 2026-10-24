<?php
/**
 * Thin client for Purelymail's account API (https://purelymail.com/api/v0/).
 * Every call is a POST with a JSON body and the account's API token in a header.
 * Only the calls the groups admin needs are wrapped; nothing here deletes a mailbox.
 */

defined( 'ABSPATH' ) || exit;

class CE_Mail_Purelymail {

	const API = 'https://purelymail.com/api/v0/';

	/**
	 * The decrypted API token, or '' when none is saved.
	 */
	public static function token() {
		$stored = CE_Mail_Settings::get( 'purelymail_token' );
		return $stored ? CE_Mail_Crypto::decrypt( $stored ) : '';
	}

	public static function ready() {
		return '' !== self::token();
	}

	/**
	 * The domain whose mailboxes and routing rules the admin page manages.
	 */
	public static function domain() {
		$d = strtolower( trim( (string) CE_Mail_Settings::get( 'mail_domain' ) ) );
		if ( '' === $d ) {
			$d = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
			$d = preg_replace( '/^www\./', '', $d );
		}
		return $d;
	}

	private static function request( $endpoint, $body = array() ) {
		$token = self::token();
		if ( '' === $token ) {
			return new WP_Error( 'ce_mail_purelymail', 'Purelymail is not connected yet. Add the account API token under Mail → Settings.', array( 'status' => 409 ) );
		}
		$res = wp_remote_post(
			self::API . $endpoint,
			array(
				'timeout' => 25,
				'headers' => array( 'Content-Type' => 'application/json', 'Purelymail-Api-Token' => $token ),
				'body'    => wp_json_encode( (object) $body ),
			)
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$code = wp_remote_retrieve_response_code( $res );
		$data = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( ! is_array( $data ) ) {
			return new WP_Error( 'ce_mail_purelymail', 'Purelymail returned an unexpected response (' . $code . ').', array( 'status' => 502 ) );
		}
		if ( ( isset( $data['type'] ) && 'error' === $data['type'] ) || $code < 200 || $code >= 300 ) {
			$msg = ! empty( $data['message'] ) ? $data['message'] : 'Purelymail returned an error (' . $code . ').';
			if ( ! empty( $data['code'] ) ) {
				$msg .= ' [' . $data['code'] . ']';
			}
			return new WP_Error( 'ce_mail_purelymail', $msg, array( 'status' => 502 ) );
		}
		return isset( $data['result'] ) && is_array( $data['result'] ) ? $data['result'] : array();
	}

	/**
	 * Cheap connectivity check: the account's remaining credit.
	 */
	public static function check() {
		$r = self::request( 'checkAccountCredit' );
		return is_wp_error( $r ) ? $r : array( 'credit' => isset( $r['credit'] ) ? $r['credit'] : '' );
	}

	/**
	 * Mailboxes on the managed domain, as full lowercase addresses, sorted.
	 */
	public static function users() {
		$r = self::request( 'listUser' );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		$domain = self::domain();
		$out    = array();
		foreach ( (array) ( isset( $r['users'] ) ? $r['users'] : array() ) as $u ) {
			$u = strtolower( trim( (string) $u ) );
			if ( false === strpos( $u, '@' ) ) {
				$u .= '@' . $domain;
			}
			if ( substr( $u, -strlen( '@' . $domain ) ) === '@' . $domain ) {
				$out[] = $u;
			}
		}
		sort( $out );
		return $out;
	}

	public static function create_user( $local, $password, $recovery_email = '', $welcome = false ) {
		$body = array(
			'userName'            => $local,
			'domainName'          => self::domain(),
			'password'            => $password,
			'enablePasswordReset' => '' !== $recovery_email,
			'enableSearchIndexing' => true,
			'sendWelcomeEmail'    => (bool) $welcome,
		);
		if ( '' !== $recovery_email ) {
			$body['recoveryEmail'] = $recovery_email;
		}
		return self::request( 'createUser', $body );
	}

	public static function reset_password( $address, $password ) {
		return self::request( 'modifyUser', array( 'userName' => $address, 'newPassword' => $password ) );
	}

	/**
	 * Routing rules for the managed domain, normalised:
	 * id, matchUser (lowercase local part), prefix, catchall, targets (lowercase, sorted).
	 */
	public static function rules() {
		$r = self::request( 'listRoutingRules' );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		$domain = self::domain();
		$out    = array();
		foreach ( (array) ( isset( $r['rules'] ) ? $r['rules'] : array() ) as $rule ) {
			if ( strtolower( (string) ( isset( $rule['domainName'] ) ? $rule['domainName'] : '' ) ) !== $domain ) {
				continue;
			}
			$targets = array_map( 'strtolower', array_map( 'trim', (array) ( isset( $rule['targetAddresses'] ) ? $rule['targetAddresses'] : array() ) ) );
			sort( $targets );
			$out[] = array(
				'id'        => isset( $rule['id'] ) ? (int) $rule['id'] : 0,
				'matchUser' => strtolower( trim( (string) ( isset( $rule['matchUser'] ) ? $rule['matchUser'] : '' ) ) ),
				'prefix'    => ! empty( $rule['prefix'] ),
				'catchall'  => ! empty( $rule['catchall'] ),
				'targets'   => array_values( array_unique( $targets ) ),
			);
		}
		return $out;
	}

	public static function create_rule( $local, array $targets ) {
		return self::request(
			'createRoutingRule',
			array(
				'domainName'      => self::domain(),
				'prefix'          => false,
				'matchUser'       => $local,
				'targetAddresses' => array_values( $targets ),
				'catchall'        => false,
			)
		);
	}

	public static function delete_rule( $id ) {
		return self::request( 'deleteRoutingRule', array( 'routingRuleId' => (int) $id ) );
	}

	/**
	 * A random password that is easy to read out loud: four words plus a number.
	 */
	public static function suggest_password() {
		$words = array( 'maple', 'river', 'harvest', 'candle', 'meadow', 'willow', 'anchor', 'lantern', 'prairie', 'summit', 'garden', 'shepherd', 'olive', 'cedar', 'canyon', 'orchard', 'bluebird', 'ember', 'harbor', 'quiet', 'timber', 'wander', 'golden', 'basin' );
		$pick  = array();
		for ( $i = 0; $i < 4; $i++ ) {
			$pick[] = ucfirst( $words[ random_int( 0, count( $words ) - 1 ) ] );
		}
		return implode( '-', $pick ) . '-' . random_int( 10, 99 );
	}
}
