<?php
/** Persistent grants, one-use tokens, and atomic fixed-window counters. */
defined( 'ABSPATH' ) || exit;

class CE_Mail_OAuth_Store {
	public static function table( $name ) {
		global $wpdb;
		return $wpdb->prefix . 'ce_mail_oauth_' . $name;
	}

	public static function install( $charset ) {
		$schemas = array(
			'clients' => "client_id varchar(32) NOT NULL, name varchar(190) NOT NULL, redirect_uris text NOT NULL, created_at bigint NOT NULL, PRIMARY KEY  (client_id)",
			'flows' => "flow_hash varchar(64) NOT NULL, browser_hash varchar(64) NOT NULL, payload longtext NOT NULL, expires_at bigint NOT NULL, PRIMARY KEY  (flow_hash)",
			'codes' => "code_hash varchar(64) NOT NULL, payload longtext NOT NULL, expires_at bigint NOT NULL, PRIMARY KEY  (code_hash)",
			'grants' => "grant_id varchar(32) NOT NULL, client_id varchar(32) NOT NULL, mailbox_email varchar(190) NOT NULL, mailbox_secret text NOT NULL, scope varchar(190) NOT NULL, resource text NOT NULL, created_at bigint NOT NULL, last_used_at bigint NOT NULL, expires_at bigint NOT NULL, revoked_at bigint NOT NULL DEFAULT 0, PRIMARY KEY  (grant_id), KEY mailbox (mailbox_email)",
			'tokens' => "token_hash varchar(64) NOT NULL, grant_id varchar(32) NOT NULL, kind varchar(10) NOT NULL, expires_at bigint NOT NULL, used_at bigint NOT NULL DEFAULT 0, PRIMARY KEY  (token_hash), KEY grant_id (grant_id)",
			'rates' => "bucket varchar(64) NOT NULL, hits int NOT NULL, expires_at bigint NOT NULL, PRIMARY KEY  (bucket)",
			'audit' => "id bigint unsigned NOT NULL AUTO_INCREMENT, ts bigint NOT NULL, mailbox_email varchar(190) NOT NULL, client_id varchar(32) NOT NULL, tool varchar(64) NOT NULL, args_summary varchar(255) NOT NULL, ok tinyint NOT NULL, ms int NOT NULL, PRIMARY KEY  (id), KEY ts (ts)",
		);
		foreach ( $schemas as $name => $fields ) {
			// dbDelta requires each field/index on its own line.
			dbDelta( 'CREATE TABLE ' . self::table( $name ) . " (\n" . str_replace( ', ', ",\n", $fields ) . "\n) ENGINE=InnoDB $charset;" );
		}
	}

	public static function insert( $name, $row ) {
		global $wpdb;
		if ( false === $wpdb->insert( self::table( $name ), $row ) ) {
			throw new RuntimeException( 'Connector storage unavailable.' );
		}
	}

	public static function rate( $key, $limit, $seconds ) {
		global $wpdb;
		$t = self::table( 'rates' );
		$bucket = hash( 'sha256', $key . ':' . (int) floor( time() / $seconds ) );
		$sql = $wpdb->prepare( "INSERT INTO $t (bucket,hits,expires_at) VALUES (%s,1,%d) ON DUPLICATE KEY UPDATE hits=hits+1", $bucket, time() + $seconds );
		if ( false === $wpdb->query( $sql ) ) {
			return false;
		}
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT hits FROM $t WHERE bucket=%s", $bucket ) ) <= $limit;
	}

	public static function revoke( $id ) {
		global $wpdb;
		// Keep the tombstone so replayed refresh tokens can never resurrect a grant.
		$ok = $wpdb->update( self::table( 'grants' ), array( 'revoked_at' => time(), 'mailbox_secret' => '' ), array( 'grant_id' => $id ) );
		if ( false === $ok ) {
			throw new RuntimeException( 'Could not revoke the connection.' );
		}
	}

	public static function sweep() {
		global $wpdb;
		$now = time();
		foreach ( array( 'flows', 'codes', 'rates', 'tokens' ) as $name ) {
			$t = self::table( $name );
			$wpdb->query( $wpdb->prepare( "DELETE FROM $t WHERE expires_at < %d", $now ) );
		}
		$t = self::table( 'grants' );
		$wpdb->query( $wpdb->prepare( "DELETE FROM $t WHERE expires_at < %d OR revoked_at > 0", $now ) );
		$t = self::table( 'audit' );
		$wpdb->query( $wpdb->prepare( "DELETE FROM $t WHERE ts < %d", $now - 90 * DAY_IN_SECONDS ) );
		$t = self::table( 'clients' );
		$g = self::table( 'grants' );
		$wpdb->query( $wpdb->prepare( "DELETE FROM $t WHERE created_at < %d AND client_id NOT IN (SELECT client_id FROM $g)", $now - 90 * DAY_IN_SECONDS ) );
	}
}
