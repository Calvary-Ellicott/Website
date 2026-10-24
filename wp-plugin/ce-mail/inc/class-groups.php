<?php
/**
 * Mail groups: lists of mailboxes kept in WordPress. A group with an address
 * (for example "info") becomes one exact-match routing rule in Purelymail whose
 * targets are the group's members, so mail to info@ reaches everyone in it.
 */

defined( 'ABSPATH' ) || exit;

class CE_Mail_Groups {

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'ce_mail_groups';
	}

	public static function members_table() {
		global $wpdb;
		return $wpdb->prefix . 'ce_mail_group_members';
	}

	public static function install( $charset ) {
		dbDelta(
			'CREATE TABLE ' . self::table() . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(120) NOT NULL DEFAULT '',
			address varchar(64) NOT NULL DEFAULT '',
			description varchar(255) NOT NULL DEFAULT '',
			rule_id bigint(20) unsigned NOT NULL DEFAULT 0,
			synced_at datetime NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY address (address)
			) $charset;"
		);
		dbDelta(
			'CREATE TABLE ' . self::members_table() . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			group_id bigint(20) unsigned NOT NULL,
			email varchar(190) NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY group_email (group_id, email),
			KEY group_id (group_id)
			) $charset;"
		);
	}

	/* ---------- validation ---------- */

	/**
	 * The local part of a group address: letters, digits, dot, dash, underscore.
	 * Returns '' for "no address" and false when the value is not usable.
	 */
	public static function clean_address( $value ) {
		$v = strtolower( trim( (string) $value ) );
		if ( '' === $v ) {
			return '';
		}
		$at = strpos( $v, '@' );
		if ( false !== $at ) {
			$v = substr( $v, 0, $at );
		}
		return preg_match( '/^[a-z0-9][a-z0-9._-]{0,63}$/', $v ) ? $v : false;
	}

	/* ---------- groups ---------- */

	public static function all() {
		global $wpdb;
		$rows = $wpdb->get_results( 'SELECT * FROM ' . self::table() . ' ORDER BY name', ARRAY_A );
		$rows = $rows ? $rows : array();
		$byid = array();
		foreach ( $rows as $i => $g ) {
			$rows[ $i ]['id']      = (int) $g['id'];
			$rows[ $i ]['rule_id'] = (int) $g['rule_id'];
			$rows[ $i ]['members'] = array();
			$byid[ (int) $g['id'] ] = $i;
		}
		if ( $byid ) {
			$members = $wpdb->get_results( 'SELECT group_id, email FROM ' . self::members_table() . ' ORDER BY email', ARRAY_A );
			foreach ( (array) $members as $m ) {
				$gid = (int) $m['group_id'];
				if ( isset( $byid[ $gid ] ) ) {
					$rows[ $byid[ $gid ] ]['members'][] = $m['email'];
				}
			}
		}
		return $rows;
	}

	public static function get( $id ) {
		foreach ( self::all() as $g ) {
			if ( $g['id'] === (int) $id ) {
				return $g;
			}
		}
		return null;
	}

	/**
	 * Create or update. Returns the id or a WP_Error.
	 */
	public static function save( $data, $id = 0 ) {
		global $wpdb;
		$t    = self::table();
		$name = sanitize_text_field( isset( $data['name'] ) ? $data['name'] : '' );
		if ( '' === $name ) {
			return new WP_Error( 'ce_mail', 'Give the group a name.', array( 'status' => 400 ) );
		}
		$address = self::clean_address( isset( $data['address'] ) ? $data['address'] : '' );
		if ( false === $address ) {
			return new WP_Error( 'ce_mail', 'The group address can only have letters, numbers, dots, dashes and underscores (for example "info").', array( 'status' => 400 ) );
		}
		if ( '' !== $address ) {
			$clash = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $t WHERE address = %s AND id <> %d", $address, $id ) );
			if ( $clash ) {
				return new WP_Error( 'ce_mail', 'Another group already uses that address.', array( 'status' => 409 ) );
			}
		}
		$now = current_time( 'mysql' );
		$row = array(
			'name'        => $name,
			'address'     => $address,
			'description' => sanitize_text_field( isset( $data['description'] ) ? $data['description'] : '' ),
			'updated_at'  => $now,
		);
		if ( $id ) {
			$wpdb->update( $t, $row, array( 'id' => (int) $id ) );
			return (int) $id;
		}
		$row['created_at'] = $now;
		$wpdb->insert( $t, $row );
		return (int) $wpdb->insert_id;
	}

	public static function delete( $id ) {
		global $wpdb;
		$wpdb->delete( self::members_table(), array( 'group_id' => (int) $id ) );
		return (bool) $wpdb->delete( self::table(), array( 'id' => (int) $id ) );
	}

	/**
	 * Replace the member list. Members are any email addresses, lowercased and unique.
	 */
	public static function set_members( $id, array $emails ) {
		global $wpdb;
		$t     = self::members_table();
		$clean = array();
		foreach ( $emails as $e ) {
			$e = strtolower( trim( (string) $e ) );
			if ( is_email( $e ) ) {
				$clean[ $e ] = $e;
			}
		}
		$wpdb->delete( $t, array( 'group_id' => (int) $id ) );
		$now = current_time( 'mysql' );
		foreach ( $clean as $e ) {
			$wpdb->insert( $t, array( 'group_id' => (int) $id, 'email' => $e, 'created_at' => $now ) );
		}
		$wpdb->update( self::table(), array( 'updated_at' => $now ), array( 'id' => (int) $id ) );
		return array_values( $clean );
	}

	private static function mark( $id, $rule_id, $synced ) {
		global $wpdb;
		$wpdb->update( self::table(), array( 'rule_id' => (int) $rule_id, 'synced_at' => $synced ? current_time( 'mysql' ) : null ), array( 'id' => (int) $id ) );
	}

	/* ---------- Purelymail sync ---------- */

	/**
	 * Compare every group that has an address with Purelymail's routing rules and
	 * fix the differences: one exact-match rule per address, targets = members.
	 * Rules nobody here owns are left alone. Returns a report per group plus the
	 * rules that are not managed by a group.
	 */
	public static function sync() {
		$rules = CE_Mail_Purelymail::rules();
		if ( is_wp_error( $rules ) ) {
			return $rules;
		}
		$groups = self::all();
		$report = array();
		$owned  = array();

		foreach ( $groups as $g ) {
			$item = array( 'id' => $g['id'], 'name' => $g['name'], 'address' => $g['address'], 'status' => 'ok', 'message' => '' );
			if ( '' === $g['address'] ) {
				// A plain list. If it used to own a rule, drop it.
				if ( $g['rule_id'] ) {
					foreach ( $rules as $r ) {
						if ( $r['id'] === $g['rule_id'] ) {
							$del = CE_Mail_Purelymail::delete_rule( $r['id'] );
							if ( is_wp_error( $del ) ) {
								$item['status']  = 'error';
								$item['message'] = $del->get_error_message();
							}
						}
					}
					self::mark( $g['id'], 0, false );
				}
				if ( 'error' !== $item['status'] ) {
					$item['status']  = 'list';
					$item['message'] = 'No address, so no routing rule.';
				}
				$report[] = $item;
				continue;
			}

			$want = array_map( 'strtolower', $g['members'] );
			sort( $want );
			$want = array_values( array_unique( $want ) );

			// Existing exact-match rules for this local part (there should be at most one),
			// plus the rule this group created before if its address changed since.
			$matching = array();
			foreach ( $rules as $r ) {
				$same_address = ! $r['prefix'] && ! $r['catchall'] && $r['matchUser'] === $g['address'];
				$old_rule     = $g['rule_id'] && $r['id'] === $g['rule_id'] && $r['matchUser'] !== $g['address'];
				if ( $same_address || $old_rule ) {
					$matching[] = $r;
				}
			}
			$keep = null;
			foreach ( $matching as $r ) {
				if ( null === $keep && $want && $r['matchUser'] === $g['address'] && $r['targets'] === $want ) {
					$keep = $r;
				}
			}
			$errors = array();
			foreach ( $matching as $r ) {
				if ( $keep && $r['id'] === $keep['id'] ) {
					continue;
				}
				$del = CE_Mail_Purelymail::delete_rule( $r['id'] );
				if ( is_wp_error( $del ) ) {
					$errors[] = $del->get_error_message();
				}
			}
			if ( $errors ) {
				$item['status']  = 'error';
				$item['message'] = implode( ' ', $errors );
			} elseif ( ! $want ) {
				self::mark( $g['id'], 0, true );
				$item['status']  = 'empty';
				$item['message'] = 'No members, so there is no rule and mail to ' . $g['address'] . '@' . CE_Mail_Purelymail::domain() . ' will bounce.';
			} elseif ( $keep ) {
				self::mark( $g['id'], $keep['id'], true );
				$owned[ $keep['id'] ] = true;
				$item['message']      = 'Rule matches.';
			} else {
				$res = CE_Mail_Purelymail::create_rule( $g['address'], $want );
				if ( is_wp_error( $res ) ) {
					$item['status']  = 'error';
					$item['message'] = $res->get_error_message();
				} else {
					// The create call does not return the id; find it in a fresh listing.
					$new_id = 0;
					$fresh  = CE_Mail_Purelymail::rules();
					if ( ! is_wp_error( $fresh ) ) {
						foreach ( $fresh as $r ) {
							if ( ! $r['prefix'] && ! $r['catchall'] && $r['matchUser'] === $g['address'] ) {
								$new_id = $r['id'];
							}
						}
						$rules = $fresh;
					}
					self::mark( $g['id'], $new_id, true );
					if ( $new_id ) {
						$owned[ $new_id ] = true;
					}
					$item['status']  = $matching ? 'updated' : 'created';
					$item['message'] = ( $matching ? 'Rule replaced: ' : 'Rule created: ' ) . $g['address'] . '@ now goes to ' . implode( ', ', $want );
				}
			}
			$report[] = $item;
		}

		$other = array();
		foreach ( $rules as $r ) {
			if ( empty( $owned[ $r['id'] ] ) ) {
				$other[] = $r;
			}
		}
		return array( 'groups' => $report, 'other_rules' => $other, 'domain' => CE_Mail_Purelymail::domain() );
	}

	/**
	 * Called when a group is deleted: remove the rule it owned so mail does not keep
	 * flowing to a list nobody manages. Errors are returned, not fatal.
	 */
	public static function drop_rule( $group ) {
		if ( empty( $group['rule_id'] ) || ! CE_Mail_Purelymail::ready() ) {
			return true;
		}
		$res = CE_Mail_Purelymail::delete_rule( $group['rule_id'] );
		return is_wp_error( $res ) ? $res : true;
	}

	/* ---------- preview data ---------- */

	public static function demo() {
		return array(
			array( 'id' => 1, 'name' => 'Staff', 'address' => 'info', 'description' => 'Everyone on staff. Mail to info@ goes to all of them.', 'rule_id' => 0, 'synced_at' => null, 'members' => array( 'dan@example.org', 'don@example.org', 'scott@example.org' ) ),
			array( 'id' => 2, 'name' => 'Prayer team', 'address' => 'prayer', 'description' => '', 'rule_id' => 0, 'synced_at' => null, 'members' => array( 'dan@example.org' ) ),
		);
	}
}
