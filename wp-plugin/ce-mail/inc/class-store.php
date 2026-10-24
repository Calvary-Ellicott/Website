<?php
/**
 * Local tables: contacts (mirrored to a Resend audience) and campaigns.
 */

defined( 'ABSPATH' ) || exit;

class CE_Mail_Store {

	const DB_VERSION = '2';

	public static function contacts_table() {
		global $wpdb;
		return $wpdb->prefix . 'ce_mail_contacts';
	}

	public static function campaigns_table() {
		global $wpdb;
		return $wpdb->prefix . 'ce_mail_campaigns';
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		dbDelta(
			'CREATE TABLE ' . self::contacts_table() . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			email varchar(190) NOT NULL,
			first_name varchar(100) NOT NULL DEFAULT '',
			last_name varchar(100) NOT NULL DEFAULT '',
			tags varchar(255) NOT NULL DEFAULT '',
			status varchar(20) NOT NULL DEFAULT 'subscribed',
			resend_id varchar(64) NOT NULL DEFAULT '',
			source varchar(40) NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY email (email),
			KEY resend_id (resend_id)
			) $charset;"
		);
		dbDelta(
			'CREATE TABLE ' . self::campaigns_table() . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(190) NOT NULL DEFAULT '',
			subject varchar(255) NOT NULL DEFAULT '',
			from_email varchar(190) NOT NULL DEFAULT '',
			from_name varchar(190) NOT NULL DEFAULT '',
			reply_to varchar(190) NOT NULL DEFAULT '',
			html longtext NULL,
			text longtext NULL,
			status varchar(20) NOT NULL DEFAULT 'draft',
			resend_id varchar(64) NOT NULL DEFAULT '',
			sent_at datetime NULL,
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY status (status)
			) $charset;"
		);
		CE_Mail_Groups::install( $charset );
		update_option( 'ce_mail_db_version', self::DB_VERSION, false );
	}

	public static function maybe_upgrade() {
		if ( get_option( 'ce_mail_db_version' ) !== self::DB_VERSION ) {
			self::install();
		}
	}

	/* ---------- contacts ---------- */

	public static function contacts( $q = '', $page = 1, $per = 50 ) {
		global $wpdb;
		$t     = self::contacts_table();
		$where = '1=1';
		$args  = array();
		if ( '' !== trim( $q ) ) {
			$like  = '%' . $wpdb->esc_like( trim( $q ) ) . '%';
			$where = '(email LIKE %s OR first_name LIKE %s OR last_name LIKE %s OR tags LIKE %s)';
			$args  = array( $like, $like, $like, $like );
		}
		$total = (int) $wpdb->get_var( $args ? $wpdb->prepare( "SELECT COUNT(*) FROM $t WHERE $where", $args ) : "SELECT COUNT(*) FROM $t WHERE $where" );
		$sql   = "SELECT * FROM $t WHERE $where ORDER BY last_name, first_name, email LIMIT %d OFFSET %d";
		$items = $wpdb->get_results( $wpdb->prepare( $sql, array_merge( $args, array( $per, ( $page - 1 ) * $per ) ) ), ARRAY_A );
		return array( 'items' => $items ? $items : array(), 'total' => $total, 'page' => $page, 'per' => $per );
	}

	public static function contact_counts() {
		global $wpdb;
		$t = self::contacts_table();
		return array(
			'total'    => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t" ),
			'synced'   => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t WHERE resend_id <> ''" ),
			'unsynced' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t WHERE resend_id = '' AND status = 'subscribed'" ),
		);
	}

	public static function upsert_contact( $email, $first = '', $last = '', $tags = '', $source = 'manual' ) {
		global $wpdb;
		$t     = self::contacts_table();
		$email = strtolower( trim( $email ) );
		if ( ! is_email( $email ) ) {
			return 'skipped';
		}
		$now      = current_time( 'mysql' );
		$existing = $wpdb->get_row( $wpdb->prepare( "SELECT id, first_name, last_name, tags FROM $t WHERE email = %s", $email ), ARRAY_A );
		if ( $existing ) {
			$wpdb->update(
				$t,
				array(
					'first_name' => $first ? $first : $existing['first_name'],
					'last_name'  => $last ? $last : $existing['last_name'],
					'tags'       => $tags ? $tags : $existing['tags'],
					'updated_at' => $now,
				),
				array( 'id' => $existing['id'] )
			);
			return 'updated';
		}
		$wpdb->insert( $t, array( 'email' => $email, 'first_name' => $first, 'last_name' => $last, 'tags' => $tags, 'status' => 'subscribed', 'source' => $source, 'created_at' => $now, 'updated_at' => $now ) );
		return 'added';
	}

	public static function delete_contact( $id ) {
		global $wpdb;
		$t   = self::contacts_table();
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE id = %d", $id ), ARRAY_A );
		if ( ! $row ) {
			return false;
		}
		$wpdb->delete( $t, array( 'id' => (int) $id ) );
		return $row;
	}

	public static function unsynced( $limit = 20 ) {
		global $wpdb;
		$t = self::contacts_table();
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t WHERE resend_id = '' AND status = 'subscribed' ORDER BY id LIMIT %d", $limit ), ARRAY_A );
	}

	public static function mark_synced( $id, $resend_id ) {
		global $wpdb;
		$wpdb->update( self::contacts_table(), array( 'resend_id' => $resend_id, 'updated_at' => current_time( 'mysql' ) ), array( 'id' => (int) $id ) );
	}

	/**
	 * Import a CSV export (ChurchTrac or anything else). Columns are matched by name,
	 * so the order does not matter: an email column, optional first/last or full name, optional tags/group.
	 */
	public static function import_csv( $csv, $source = 'churchtrac' ) {
		$csv   = str_replace( "\r\n", "\n", $csv );
		$csv   = preg_replace( '/^\xEF\xBB\xBF/', '', $csv ); // BOM
		$lines = preg_split( '/\n/', trim( $csv ) );
		if ( count( $lines ) < 2 ) {
			return array( 'added' => 0, 'updated' => 0, 'skipped' => 0, 'error' => 'The file needs a header row and at least one contact.' );
		}
		$delim  = substr_count( $lines[0], "\t" ) > substr_count( $lines[0], ',' ) ? "\t" : ',';
		$header = array_map( function ( $h ) {
			return strtolower( trim( preg_replace( '/[^a-z0-9]+/i', ' ', $h ) ) );
		}, str_getcsv( $lines[0], $delim ) );

		$find = function ( $candidates ) use ( $header ) {
			foreach ( $candidates as $c ) {
				foreach ( $header as $i => $h ) {
					if ( $h === $c ) {
						return $i;
					}
				}
			}
			foreach ( $candidates as $c ) {
				foreach ( $header as $i => $h ) {
					if ( false !== strpos( $h, $c ) ) {
						return $i;
					}
				}
			}
			return -1;
		};
		$ie = $find( array( 'email', 'e mail', 'email address', 'primary email' ) );
		$if = $find( array( 'first name', 'first', 'given name' ) );
		$il = $find( array( 'last name', 'last', 'surname', 'family name' ) );
		$in = $find( array( 'name', 'full name', 'display name' ) );
		$it = $find( array( 'tags', 'group', 'groups', 'list', 'category' ) );
		if ( $ie < 0 ) {
			return array( 'added' => 0, 'updated' => 0, 'skipped' => 0, 'error' => 'No email column was found. The header row needs a column named Email.' );
		}

		$counts = array( 'added' => 0, 'updated' => 0, 'skipped' => 0, 'error' => '' );
		for ( $r = 1, $n = count( $lines ); $r < $n; $r++ ) {
			if ( '' === trim( $lines[ $r ] ) ) {
				continue;
			}
			$row   = str_getcsv( $lines[ $r ], $delim );
			$email = isset( $row[ $ie ] ) ? trim( $row[ $ie ] ) : '';
			$first = $if >= 0 && isset( $row[ $if ] ) ? trim( $row[ $if ] ) : '';
			$last  = $il >= 0 && isset( $row[ $il ] ) ? trim( $row[ $il ] ) : '';
			if ( '' === $first && '' === $last && $in >= 0 && isset( $row[ $in ] ) ) {
				$parts = preg_split( '/\s+/', trim( $row[ $in ] ), 2 );
				$first = isset( $parts[0] ) ? $parts[0] : '';
				$last  = isset( $parts[1] ) ? $parts[1] : '';
			}
			$tags = $it >= 0 && isset( $row[ $it ] ) ? trim( $row[ $it ] ) : '';
			$res  = self::upsert_contact( $email, sanitize_text_field( $first ), sanitize_text_field( $last ), sanitize_text_field( $tags ), $source );
			$counts[ $res ]++;
		}
		return $counts;
	}

	/* ---------- campaigns ---------- */

	public static function campaigns() {
		global $wpdb;
		$rows = $wpdb->get_results( 'SELECT id, name, subject, from_email, from_name, reply_to, status, resend_id, sent_at, created_at, updated_at FROM ' . self::campaigns_table() . ' ORDER BY updated_at DESC LIMIT 200', ARRAY_A );
		return $rows ? $rows : array();
	}

	public static function campaign( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::campaigns_table() . ' WHERE id = %d', $id ), ARRAY_A );
	}

	public static function save_campaign( $data, $id = 0 ) {
		global $wpdb;
		$t   = self::campaigns_table();
		$now = current_time( 'mysql' );
		$row = array(
			'name'       => sanitize_text_field( isset( $data['name'] ) ? $data['name'] : '' ),
			'subject'    => sanitize_text_field( isset( $data['subject'] ) ? $data['subject'] : '' ),
			'from_email' => sanitize_email( isset( $data['from_email'] ) ? $data['from_email'] : '' ),
			'from_name'  => sanitize_text_field( isset( $data['from_name'] ) ? $data['from_name'] : '' ),
			'reply_to'   => sanitize_email( isset( $data['reply_to'] ) ? $data['reply_to'] : '' ),
			'html'       => isset( $data['html'] ) ? wp_kses_post( $data['html'] ) : '',
			'text'       => isset( $data['text'] ) ? sanitize_textarea_field( $data['text'] ) : '',
			'updated_at' => $now,
		);
		if ( $id ) {
			$wpdb->update( $t, $row, array( 'id' => (int) $id ) );
			return (int) $id;
		}
		$row['status']     = 'draft';
		$row['created_by'] = get_current_user_id();
		$row['created_at'] = $now;
		$wpdb->insert( $t, $row );
		return (int) $wpdb->insert_id;
	}

	public static function set_campaign_status( $id, $status, $resend_id = null ) {
		global $wpdb;
		$row = array( 'status' => $status, 'updated_at' => current_time( 'mysql' ) );
		if ( null !== $resend_id ) {
			$row['resend_id'] = $resend_id;
		}
		if ( 'sent' === $status ) {
			$row['sent_at'] = current_time( 'mysql' );
		}
		$wpdb->update( self::campaigns_table(), $row, array( 'id' => (int) $id ) );
	}

	public static function delete_campaign( $id ) {
		global $wpdb;
		return (bool) $wpdb->delete( self::campaigns_table(), array( 'id' => (int) $id, 'status' => 'draft' ) );
	}

	/* ---------- preview data ---------- */

	public static function demo_contacts() {
		$names = array( array( 'Maria', 'Alvarez' ), array( 'James', 'Carter' ), array( 'Linda', 'Nguyen' ), array( 'Robert', 'Okafor' ), array( 'Susan', 'Miller' ), array( 'Tom', 'Reyes' ) );
		$out   = array();
		foreach ( $names as $i => $n ) {
			$out[] = array( 'id' => $i + 1, 'email' => strtolower( $n[0] . '.' . $n[1] ) . '@example.org', 'first_name' => $n[0], 'last_name' => $n[1], 'tags' => $i % 2 ? 'Members' : 'Visitors', 'status' => 'subscribed', 'resend_id' => $i < 4 ? 'demo' : '', 'source' => 'churchtrac' );
		}
		return $out;
	}

	public static function demo_campaigns() {
		return array(
			array( 'id' => 2, 'name' => 'November newsletter', 'subject' => 'What’s happening at Calvary Ellicott this November', 'from_email' => 'info@calvaryellicott.org', 'from_name' => 'Calvary Ellicott', 'status' => 'draft', 'sent_at' => null, 'updated_at' => gmdate( 'Y-m-d H:i:s' ) ),
			array( 'id' => 1, 'name' => 'Pancake breakfast', 'subject' => 'Join us Saturday for the community pancake breakfast', 'from_email' => 'info@calvaryellicott.org', 'from_name' => 'Calvary Ellicott', 'status' => 'sent', 'sent_at' => gmdate( 'Y-m-d H:i:s', time() - 5 * 86400 ), 'updated_at' => gmdate( 'Y-m-d H:i:s', time() - 5 * 86400 ) ),
		);
	}
}
