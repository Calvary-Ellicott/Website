<?php
/**
 * One staff member's Purelymail mailbox: stored login, folder roles, listing,
 * reading, flags, moves, and sending through SMTP (with a copy saved to Sent).
 */

defined( 'ABSPATH' ) || exit;

class CE_Mail_Mailbox {

	const META = 'ce_mail_mailbox';

	/* ---------- stored login ---------- */

	public static function creds( $user_id ) {
		$stored = get_user_meta( $user_id, self::META, true );
		if ( ! is_array( $stored ) || empty( $stored['email'] ) || empty( $stored['secret'] ) ) {
			return null;
		}
		$password = CE_Mail_Crypto::decrypt( $stored['secret'] );
		return $password ? array( 'email' => $stored['email'], 'password' => $password ) : null;
	}

	public static function save( $user_id, $email, $password ) {
		update_user_meta( $user_id, self::META, array( 'email' => $email, 'secret' => CE_Mail_Crypto::encrypt( $password ), 'linked_at' => time() ) );
	}

	public static function unlink( $user_id ) {
		delete_user_meta( $user_id, self::META );
	}

	public static function is_linked( $user_id ) {
		return null !== self::creds( $user_id );
	}

	/* ---------- connections ---------- */

	public static function open( $email, $password ) {
		$s    = CE_Mail_Settings::all();
		$imap = new CE_Mail_IMAP( $s['imap_host'], $s['imap_port'] );
		$imap->connect();
		$imap->login( $email, $password );
		return $imap;
	}

	public static function connect( $user_id ) {
		$c = self::creds( $user_id );
		if ( ! $c ) {
			throw new Exception( 'No mailbox is connected yet. Add your email login under Settings.' );
		}
		return self::open( $c['email'], $c['password'] );
	}

	/**
	 * Map the account's folders onto the roles the portal shows.
	 */
	public static function folders( CE_Mail_IMAP $imap ) {
		$all   = $imap->list_folders();
		$roles = array( 'inbox' => 'INBOX', 'sent' => '', 'drafts' => '', 'archive' => '', 'junk' => '', 'trash' => '' );
		$match = array(
			'sent'    => array( 'sent', 'sent items', 'sent mail', 'sent messages' ),
			'drafts'  => array( 'drafts', 'draft' ),
			'archive' => array( 'archive', 'archives', 'all mail' ),
			'junk'    => array( 'junk', 'spam', 'junk e-mail', 'junk email' ),
			'trash'   => array( 'trash', 'deleted', 'deleted items', 'deleted messages', 'bin' ),
		);
		foreach ( $all as $f ) {
			$parts = $f['delimiter'] ? explode( $f['delimiter'], strtolower( $f['name'] ) ) : array( strtolower( $f['name'] ) );
			$leaf  = end( $parts ); // leaf name after the delimiter
			$flags = $f['flags'];
			foreach ( $match as $role => $names ) {
				if ( $roles[ $role ] ) {
					continue;
				}
				if ( false !== strpos( $flags, '\\' . $role ) || in_array( $leaf, $names, true ) ) {
					$roles[ $role ] = $f['name'];
				}
			}
		}
		$out = array();
		foreach ( $roles as $role => $name ) {
			if ( $name ) {
				$out[] = array( 'role' => $role, 'name' => $name );
			}
		}
		// every other folder, for completeness
		$used = wp_list_pluck( $out, 'name' );
		foreach ( $all as $f ) {
			if ( ! in_array( $f['name'], $used, true ) ) {
				$out[] = array( 'role' => 'other', 'name' => $f['name'] );
			}
		}
		return $out;
	}

	public static function folder_for_role( CE_Mail_IMAP $imap, $role ) {
		foreach ( self::folders( $imap ) as $f ) {
			if ( $f['role'] === $role ) {
				return $f['name'];
			}
		}
		return '';
	}

	/* ---------- listing and reading ---------- */

	public static function list_messages( $user_id, $folder, $page = 1, $per = 25, $q = '' ) {
		$imap = self::connect( $user_id );
		try {
			$imap->select( $folder );
			$criteria = 'ALL';
			if ( '' !== trim( $q ) ) {
				$criteria = 'OR OR FROM ' . $imap->quote( $q ) . ' SUBJECT ' . $imap->quote( $q ) . ' TEXT ' . $imap->quote( $q );
			}
			$uids  = $imap->search( $criteria );
			$total = count( $uids );
			$uids  = array_reverse( $uids ); // newest first
			$slice = array_slice( $uids, ( $page - 1 ) * $per, $per );
			$items = $imap->fetch_summaries( $slice );
			$ordered = array();
			foreach ( $slice as $uid ) {
				if ( isset( $items[ $uid ] ) ) {
					$ordered[] = $items[ $uid ];
				}
			}
			return array( 'items' => $ordered, 'total' => $total, 'page' => $page, 'per' => $per );
		} finally {
			$imap->logout();
		}
	}

	public static function read_message( $user_id, $folder, $uid ) {
		$imap = self::connect( $user_id );
		try {
			$imap->select( $folder );
			$raw    = $imap->fetch_raw( $uid );
			$parsed = CE_Mail_MIME::parse_message( $raw );
			$imap->store_flag( array( $uid ), '\Seen', true );
			$h = $parsed['headers'];
			$attachments = array();
			foreach ( $parsed['attachments'] as $a ) {
				$attachments[] = array( 'part' => $a['part'], 'name' => $a['name'], 'type' => $a['type'], 'size' => $a['size'] );
			}
			$text = $parsed['text'];
			if ( '' === $text && $parsed['html'] ) {
				$text = trim( wp_strip_all_tags( preg_replace( '#<(br|/p|/div|/tr|/li)[^>]*>#i', "\n", $parsed['html'] ) ) );
			}
			return array(
				'uid'         => (int) $uid,
				'folder'      => $folder,
				'subject'     => isset( $h['subject'] ) ? $h['subject'] : '',
				'from'        => CE_Mail_MIME::first_address( isset( $h['from'] ) ? $h['from'] : '' ),
				'to'          => CE_Mail_MIME::addresses( isset( $h['to'] ) ? $h['to'] : '' ),
				'cc'          => CE_Mail_MIME::addresses( isset( $h['cc'] ) ? $h['cc'] : '' ),
				'reply_to'    => CE_Mail_MIME::first_address( isset( $h['reply-to'] ) ? $h['reply-to'] : '' ),
				'date'        => ! empty( $h['date'] ) ? strtotime( $h['date'] ) : time(),
				'message_id'  => isset( $h['message-id'] ) ? $h['message-id'] : '',
				'references'  => isset( $h['references'] ) ? $h['references'] : '',
				'html'        => $parsed['html'] ? CE_Mail_MIME::safe_html( $parsed['html'] ) : '',
				'text'        => $text,
				'attachments' => $attachments,
			);
		} finally {
			$imap->logout();
		}
	}

	public static function attachment( $user_id, $folder, $uid, $part ) {
		$imap = self::connect( $user_id );
		try {
			$imap->select( $folder );
			$parsed = CE_Mail_MIME::parse_message( $imap->fetch_raw( $uid ) );
			foreach ( $parsed['attachments'] as $a ) {
				if ( $a['part'] === $part ) {
					return $a;
				}
			}
			return null;
		} finally {
			$imap->logout();
		}
	}

	public static function flag( $user_id, $folder, array $uids, $flag, $add ) {
		$imap = self::connect( $user_id );
		try {
			$imap->select( $folder );
			return $imap->store_flag( $uids, $flag, $add );
		} finally {
			$imap->logout();
		}
	}

	public static function move( $user_id, $folder, array $uids, $to_role_or_name ) {
		$imap = self::connect( $user_id );
		try {
			$dest = self::folder_for_role( $imap, $to_role_or_name );
			if ( '' === $dest ) {
				$dest = $to_role_or_name;
				if ( 'archive' === $to_role_or_name ) {
					$dest = 'Archive';
					$imap->create( $dest ); // first archive on this account creates the folder
				}
			}
			$imap->select( $folder );
			return $imap->move( $uids, $dest );
		} finally {
			$imap->logout();
		}
	}

	/* ---------- sending ---------- */

	public static function send( $user_id, $to, $cc, $bcc, $subject, $text, $html, $in_reply_to = '', $references = '' ) {
		$c = self::creds( $user_id );
		if ( ! $c ) {
			throw new Exception( 'No mailbox is connected yet.' );
		}
		$s    = CE_Mail_Settings::all();
		$user = get_userdata( $user_id );
		require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
		require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
		require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';

		$m = new PHPMailer\PHPMailer\PHPMailer( true );
		$m->isSMTP();
		$m->Host       = $s['smtp_host'];
		$m->Port       = (int) $s['smtp_port'];
		$m->SMTPAuth   = true;
		$m->Username   = $c['email'];
		$m->Password   = $c['password'];
		$m->SMTPSecure = 'tls' === $s['smtp_secure'] ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
		$m->Timeout    = 25;
		$m->CharSet    = 'UTF-8';
		$m->setFrom( $c['email'], $user ? $user->display_name : '' );
		$count = 0;
		foreach ( self::split_recipients( $to ) as $a ) {
			$m->addAddress( $a );
			$count++;
		}
		foreach ( self::split_recipients( $cc ) as $a ) {
			$m->addCC( $a );
			$count++;
		}
		foreach ( self::split_recipients( $bcc ) as $a ) {
			$m->addBCC( $a );
			$count++;
		}
		if ( ! $count ) {
			throw new Exception( 'Add at least one valid email address.' );
		}
		$m->Subject = $subject;
		if ( $html ) {
			$m->isHTML( true );
			$m->Body    = $html;
			$m->AltBody = $text;
		} else {
			$m->Body = $text;
		}
		if ( $in_reply_to ) {
			$m->addCustomHeader( 'In-Reply-To', $in_reply_to );
			$m->addCustomHeader( 'References', trim( $references . ' ' . $in_reply_to ) );
		}
		$m->send();

		// keep a copy in Sent
		try {
			$imap = self::open( $c['email'], $c['password'] );
			$sent = self::folder_for_role( $imap, 'sent' );
			if ( $sent ) {
				$imap->append( $sent, $m->getSentMIMEMessage(), '\Seen' );
			}
			$imap->logout();
		} catch ( Exception $e ) {
			// sending succeeded; a missing Sent copy is not worth failing the request
		}
		return true;
	}

	public static function split_recipients( $list ) {
		$out = array();
		foreach ( preg_split( '/[,;\n]+/', (string) $list ) as $piece ) {
			$piece = trim( $piece );
			if ( preg_match( '/<([^>]+)>/', $piece, $m ) ) {
				$piece = $m[1];
			}
			if ( is_email( $piece ) ) {
				$out[] = $piece;
			}
		}
		return array_unique( $out );
	}

	/* ---------- preview data ---------- */

	public static function demo_folders() {
		return array(
			array( 'role' => 'inbox', 'name' => 'INBOX', 'unseen' => 2 ),
			array( 'role' => 'sent', 'name' => 'Sent' ),
			array( 'role' => 'drafts', 'name' => 'Drafts' ),
			array( 'role' => 'archive', 'name' => 'Archive' ),
			array( 'role' => 'trash', 'name' => 'Trash' ),
		);
	}

	public static function demo_messages( $folder ) {
		$now = time();
		$inbox = array(
			array( 'uid' => 1003, 'seen' => false, 'flagged' => true, 'date' => $now - 3600, 'from' => array( 'name' => 'Pastor Dan', 'email' => 'dan@calvaryellicott.org' ), 'subject' => 'Sunday order of service', 'attachments' => true, 'text' => "Here is the order of service for Sunday. The bulletin PDF is attached.\n\nLet me know if the announcements need anything added.\n\nDan", 'atts' => array( array( 'part' => '2', 'name' => 'bulletin-oct-19.pdf', 'type' => 'application/pdf', 'size' => 184000 ) ) ),
			array( 'uid' => 1002, 'seen' => false, 'flagged' => false, 'date' => $now - 7200, 'from' => array( 'name' => 'Church Connect', 'email' => 'no-reply@churchtrac.com' ), 'subject' => 'New prayer request submitted', 'attachments' => false, 'text' => "A new prayer request was submitted through Church Connect.\n\nName: Maria\nRequest: Please pray for my mother's surgery on Thursday.\n\nSign in to Church Connect to respond." ),
			array( 'uid' => 1001, 'seen' => true, 'flagged' => false, 'date' => $now - 86400, 'from' => array( 'name' => 'Don Drury', 'email' => 'don@calvaryellicott.org' ), 'subject' => 'Re: Volunteer schedule for November', 'attachments' => false, 'text' => "Thanks. I have the greeters covered for the first two Sundays. Still need one more for the 23rd.\n\nDon" ),
			array( 'uid' => 1000, 'seen' => true, 'flagged' => false, 'date' => $now - 2 * 86400, 'from' => array( 'name' => 'Ellicott Fire Department', 'email' => 'info@example.org' ), 'subject' => 'Community pancake breakfast', 'attachments' => false, 'text' => "We would love to have the church help with the pancake breakfast on the first Saturday in November. Could we borrow tables?" ),
		);
		$sent = array(
			array( 'uid' => 2001, 'seen' => true, 'flagged' => false, 'date' => $now - 90000, 'from' => array( 'name' => 'You', 'email' => 'you@calvaryellicott.org' ), 'to' => array( array( 'name' => 'Don Drury', 'email' => 'don@calvaryellicott.org' ) ), 'subject' => 'Volunteer schedule for November', 'attachments' => false, 'text' => "Don, can you check the greeter schedule for November and let me know where we have gaps?" ),
		);
		$list = 'INBOX' === $folder ? $inbox : ( 'Sent' === $folder ? $sent : array() );
		foreach ( $list as &$m ) {
			$m['to']         = isset( $m['to'] ) ? $m['to'] : array( array( 'name' => 'You', 'email' => 'you@calvaryellicott.org' ) );
			$m['size']       = strlen( $m['text'] ) + 2000;
			$m['message_id'] = '<demo-' . $m['uid'] . '@calvaryellicott.org>';
			$m['answered']   = false;
		}
		return $list;
	}
}
