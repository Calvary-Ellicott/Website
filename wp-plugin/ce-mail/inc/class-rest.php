<?php
/**
 * REST API for the portal app (namespace ce-mail/v1). Every route requires a signed-in
 * user with the portal capability; the app sends the standard WordPress REST nonce.
 */

defined( 'ABSPATH' ) || exit;

class CE_Mail_REST {

	const NS = 'ce-mail/v1';

	public static function register() {
		$r = function ( $route, $methods, $cb, $args = array() ) {
			$perm = ( 0 === strpos( $route, '/contacts' ) || 0 === strpos( $route, '/campaigns' ) ) ? 'can_market' : 'can';
			if ( 0 === strpos( $route, '/admin' ) ) {
				$perm = 'can_admin';
			}
			register_rest_route( self::NS, $route, array( 'methods' => $methods, 'callback' => array( __CLASS__, $cb ), 'permission_callback' => array( __CLASS__, $perm ), 'args' => $args ) );
		};
		$r( '/me', 'GET', 'me' );
		$r( '/mailbox', 'POST', 'mailbox_link' );
		$r( '/mailbox', 'DELETE', 'mailbox_unlink' );
		$r( '/folders', 'GET', 'folders' );
		$r( '/messages', 'GET', 'messages' );
		$r( '/message', 'GET', 'message' );
		$r( '/attachment', 'GET', 'attachment' );
		$r( '/messages/flag', 'POST', 'flag' );
		$r( '/messages/move', 'POST', 'move' );
		$r( '/send', 'POST', 'send' );
		$r( '/contacts', 'GET', 'contacts' );
		$r( '/contacts', 'POST', 'contact_add' );
		$r( '/contacts/import', 'POST', 'contacts_import' );
		$r( '/contacts/sync', 'POST', 'contacts_sync' );
		$r( '/contacts/(?P<id>\d+)', 'DELETE', 'contact_delete' );
		$r( '/campaigns', 'GET', 'campaigns' );
		$r( '/campaigns', 'POST', 'campaign_save' );
		$r( '/campaigns/(?P<id>\d+)', 'GET', 'campaign' );
		$r( '/campaigns/(?P<id>\d+)', 'PUT', 'campaign_save' );
		$r( '/campaigns/(?P<id>\d+)', 'DELETE', 'campaign_delete' );
		$r( '/campaigns/(?P<id>\d+)/test', 'POST', 'campaign_test' );
		$r( '/campaigns/(?P<id>\d+)/send', 'POST', 'campaign_send' );
		$r( '/campaigns/(?P<id>\d+)/preview', 'GET', 'campaign_preview' );
		// administrators only: Purelymail account, mailboxes, groups → routing rules
		$r( '/admin/status', 'GET', 'admin_status' );
		$r( '/admin/mailboxes', 'GET', 'admin_mailboxes' );
		$r( '/admin/mailboxes', 'POST', 'admin_mailbox_create' );
		$r( '/admin/mailboxes/password', 'POST', 'admin_mailbox_password' );
		$r( '/admin/groups', 'GET', 'admin_groups' );
		$r( '/admin/groups', 'POST', 'admin_group_save' );
		$r( '/admin/groups/(?P<id>\d+)', 'PUT', 'admin_group_save' );
		$r( '/admin/groups/(?P<id>\d+)', 'DELETE', 'admin_group_delete' );
		$r( '/admin/groups/(?P<id>\d+)/members', 'PUT', 'admin_group_members' );
		$r( '/admin/suggestions', 'GET', 'admin_suggestions' );
		$r( '/admin/rules', 'GET', 'admin_rules' );
		$r( '/admin/sync', 'POST', 'admin_sync' );
	}

	public static function can() {
		return is_user_logged_in() && current_user_can( CE_MAIL_CAP );
	}

	public static function can_admin() {
		return is_user_logged_in() && current_user_can( 'manage_options' );
	}

	private static function fail( $message, $status = 400 ) {
		return new WP_Error( 'ce_mail', $message, array( 'status' => $status ) );
	}

	private static function uid() {
		return get_current_user_id();
	}

	private static function demo_mail() {
		return ! CE_Mail_Mailbox::is_linked( self::uid() );
	}

	private static function demo_marketing() {
		return ! CE_Mail_Settings::marketing_ready();
	}

	/**
	 * Marketing routes answer only while the section is switched on in settings.
	 */
	public static function can_market() {
		return self::can() && CE_Mail_Settings::marketing_enabled();
	}

	/* ---------- account ---------- */

	public static function me() {
		$u = wp_get_current_user();
		$s = CE_Mail_Settings::all();
		$c = CE_Mail_Mailbox::creds( $u->ID );
		return array(
			'name'      => $u->display_name,
			'email'     => $u->user_email,
			'admin'     => current_user_can( 'manage_options' ),
			'mailbox'   => array( 'linked' => (bool) $c, 'email' => $c ? $c['email'] : '' ),
			'marketing' => array(
				'enabled'  => CE_Mail_Settings::marketing_enabled(),
				'ready'    => CE_Mail_Settings::marketing_ready(),
				'from'     => $s['marketing_from'],
				'name'     => $s['marketing_name'],
				'reply_to' => $s['marketing_reply'],
				'has_key'  => ! empty( $s['resend_key'] ),
				'audience' => $s['resend_audience'],
			),
			'webmail'   => $s['webmail_url'],
			'imap_host' => $s['imap_host'],
			'page_size' => (int) $s['page_size'],
			'counts'    => self::demo_marketing() ? array( 'total' => 6, 'synced' => 4, 'unsynced' => 2 ) : CE_Mail_Store::contact_counts(),
		);
	}

	public static function mailbox_link( WP_REST_Request $req ) {
		$email    = sanitize_email( $req['email'] );
		$password = (string) $req['password'];
		if ( ! is_email( $email ) || '' === $password ) {
			return self::fail( 'Enter the mailbox email address and its password.' );
		}
		try {
			$imap = CE_Mail_Mailbox::open( $email, $password );
			$imap->logout();
		} catch ( Exception $e ) {
			return self::fail( $e->getMessage(), 422 );
		}
		CE_Mail_Mailbox::save( self::uid(), $email, $password );
		return array( 'ok' => true, 'email' => $email );
	}

	public static function mailbox_unlink() {
		CE_Mail_Mailbox::unlink( self::uid() );
		return array( 'ok' => true );
	}

	/* ---------- mail ---------- */

	public static function folders() {
		if ( self::demo_mail() ) {
			return array( 'demo' => true, 'folders' => CE_Mail_Mailbox::demo_folders() );
		}
		try {
			$imap    = CE_Mail_Mailbox::connect( self::uid() );
			$folders = CE_Mail_Mailbox::folders( $imap );
			foreach ( $folders as &$f ) {
				if ( 'inbox' === $f['role'] ) {
					$st          = $imap->status( $f['name'] );
					$f['unseen'] = $st['unseen'];
				}
			}
			$imap->logout();
			return array( 'demo' => false, 'folders' => $folders );
		} catch ( Exception $e ) {
			return self::fail( $e->getMessage(), 502 );
		}
	}

	public static function messages( WP_REST_Request $req ) {
		$folder = (string) ( $req['folder'] ? $req['folder'] : 'INBOX' );
		$page   = max( 1, (int) $req['page'] );
		$per    = max( 5, min( 100, (int) CE_Mail_Settings::get( 'page_size' ) ) );
		$q      = sanitize_text_field( (string) $req['q'] );
		if ( self::demo_mail() ) {
			$items = CE_Mail_Mailbox::demo_messages( $folder );
			if ( $q ) {
				$items = array_values( array_filter( $items, function ( $m ) use ( $q ) {
					return false !== stripos( $m['subject'] . ' ' . $m['from']['name'] . ' ' . $m['from']['email'] . ' ' . $m['text'], $q );
				} ) );
			}
			foreach ( $items as &$m ) {
				unset( $m['text'], $m['atts'] );
			}
			return array( 'demo' => true, 'items' => $items, 'total' => count( $items ), 'page' => 1, 'per' => $per );
		}
		try {
			$out         = CE_Mail_Mailbox::list_messages( self::uid(), $folder, $page, $per, $q );
			$out['demo'] = false;
			return $out;
		} catch ( Exception $e ) {
			return self::fail( $e->getMessage(), 502 );
		}
	}

	public static function message( WP_REST_Request $req ) {
		$folder = (string) ( $req['folder'] ? $req['folder'] : 'INBOX' );
		$uid    = (int) $req['uid'];
		if ( self::demo_mail() ) {
			foreach ( CE_Mail_Mailbox::demo_messages( $folder ) as $m ) {
				if ( $m['uid'] === $uid ) {
					return array(
						'uid' => $uid, 'folder' => $folder, 'subject' => $m['subject'], 'from' => $m['from'], 'to' => $m['to'], 'cc' => array(), 'reply_to' => $m['from'],
						'date' => $m['date'], 'message_id' => $m['message_id'], 'references' => '', 'html' => '', 'text' => $m['text'],
						'attachments' => isset( $m['atts'] ) ? $m['atts'] : array(), 'demo' => true,
					);
				}
			}
			return self::fail( 'Sample message not found.', 404 );
		}
		try {
			return CE_Mail_Mailbox::read_message( self::uid(), $folder, $uid );
		} catch ( Exception $e ) {
			return self::fail( $e->getMessage(), 502 );
		}
	}

	public static function attachment( WP_REST_Request $req ) {
		if ( self::demo_mail() ) {
			return self::fail( 'Attachments are not available in the preview.', 404 );
		}
		try {
			$a = CE_Mail_Mailbox::attachment( self::uid(), (string) $req['folder'], (int) $req['uid'], (string) $req['part'] );
		} catch ( Exception $e ) {
			return self::fail( $e->getMessage(), 502 );
		}
		if ( ! $a ) {
			return self::fail( 'Attachment not found.', 404 );
		}
		nocache_headers();
		header( 'Content-Type: ' . ( preg_match( '#^[\w.+-]+/[\w.+-]+$#', $a['type'] ) ? $a['type'] : 'application/octet-stream' ) );
		header( 'Content-Length: ' . strlen( $a['content'] ) );
		header( 'Content-Disposition: attachment; filename="' . rawurlencode( $a['name'] ) . '"' );
		header( 'X-Content-Type-Options: nosniff' );
		echo $a['content']; // binary
		exit;
	}

	public static function flag( WP_REST_Request $req ) {
		if ( self::demo_mail() ) {
			return array( 'ok' => true, 'demo' => true );
		}
		$uids = array_map( 'intval', (array) $req['uids'] );
		$flag = 'flagged' === $req['flag'] ? '\Flagged' : '\Seen';
		try {
			return array( 'ok' => CE_Mail_Mailbox::flag( self::uid(), (string) $req['folder'], $uids, $flag, (bool) $req['value'] ) );
		} catch ( Exception $e ) {
			return self::fail( $e->getMessage(), 502 );
		}
	}

	public static function move( WP_REST_Request $req ) {
		if ( self::demo_mail() ) {
			return array( 'ok' => true, 'demo' => true );
		}
		$uids = array_map( 'intval', (array) $req['uids'] );
		try {
			return array( 'ok' => CE_Mail_Mailbox::move( self::uid(), (string) $req['folder'], $uids, (string) $req['to'] ) );
		} catch ( Exception $e ) {
			return self::fail( $e->getMessage(), 502 );
		}
	}

	public static function send( WP_REST_Request $req ) {
		$subject = sanitize_text_field( (string) $req['subject'] );
		$text    = (string) $req['text'];
		if ( '' === trim( $text ) ) {
			return self::fail( 'Write a message before sending.' );
		}
		if ( self::demo_mail() ) {
			return array( 'ok' => true, 'demo' => true );
		}
		$html = '<div style="font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.6;color:#1b2822">' . nl2br( esc_html( $text ) ) . '</div>';
		try {
			CE_Mail_Mailbox::send( self::uid(), (string) $req['to'], (string) $req['cc'], (string) $req['bcc'], $subject, $text, $html, sanitize_text_field( (string) $req['in_reply_to'] ), sanitize_text_field( (string) $req['references'] ) );
			return array( 'ok' => true );
		} catch ( Exception $e ) {
			return self::fail( $e->getMessage(), 502 );
		}
	}

	/* ---------- contacts ---------- */

	public static function contacts( WP_REST_Request $req ) {
		$q    = sanitize_text_field( (string) $req['q'] );
		$page = max( 1, (int) $req['page'] );
		if ( self::demo_marketing() && ! CE_Mail_Store::contact_counts()['total'] ) {
			$items = CE_Mail_Store::demo_contacts();
			if ( $q ) {
				$items = array_values( array_filter( $items, function ( $c ) use ( $q ) {
					return false !== stripos( implode( ' ', $c ), $q );
				} ) );
			}
			return array( 'demo' => true, 'items' => $items, 'total' => count( $items ), 'page' => 1, 'per' => 50, 'counts' => array( 'total' => 6, 'synced' => 4, 'unsynced' => 2 ) );
		}
		$out           = CE_Mail_Store::contacts( $q, $page, 50 );
		$out['demo']   = false;
		$out['counts'] = CE_Mail_Store::contact_counts();
		return $out;
	}

	public static function contact_add( WP_REST_Request $req ) {
		$result = CE_Mail_Store::upsert_contact( (string) $req['email'], sanitize_text_field( (string) $req['first_name'] ), sanitize_text_field( (string) $req['last_name'] ), sanitize_text_field( (string) $req['tags'] ), 'manual' );
		if ( 'skipped' === $result ) {
			return self::fail( 'That email address does not look right.' );
		}
		return array( 'ok' => true, 'result' => $result, 'counts' => CE_Mail_Store::contact_counts() );
	}

	public static function contacts_import( WP_REST_Request $req ) {
		$csv = (string) $req['csv'];
		if ( strlen( $csv ) > 5000000 ) {
			return self::fail( 'That file is too large. Split it into smaller exports.' );
		}
		$counts = CE_Mail_Store::import_csv( $csv, 'churchtrac' );
		if ( ! empty( $counts['error'] ) ) {
			return self::fail( $counts['error'] );
		}
		$counts['counts'] = CE_Mail_Store::contact_counts();
		return $counts;
	}

	/**
	 * Push a batch of unsynced contacts to the Resend audience. The app calls this
	 * repeatedly until "remaining" is zero.
	 */
	public static function contacts_sync() {
		if ( self::demo_marketing() ) {
			return self::fail( 'Connect Resend first (Mail → Settings in the dashboard).', 409 );
		}
		$audience = CE_Mail_Settings::get( 'resend_audience' );
		$done     = 0;
		$errors   = array();
		foreach ( CE_Mail_Store::unsynced( 15 ) as $c ) {
			$res = CE_Mail_Resend::add_contact( $audience, $c['email'], $c['first_name'], $c['last_name'] );
			if ( is_wp_error( $res ) ) {
				$errors[] = $c['email'] . ': ' . $res->get_error_message();
				if ( count( $errors ) >= 3 ) {
					break;
				}
				continue;
			}
			CE_Mail_Store::mark_synced( $c['id'], ! empty( $res['id'] ) ? $res['id'] : 'synced' );
			$done++;
			usleep( 550000 ); // Resend allows about two requests per second
		}
		$counts = CE_Mail_Store::contact_counts();
		return array( 'synced' => $done, 'remaining' => $counts['unsynced'], 'errors' => $errors, 'counts' => $counts );
	}

	public static function contact_delete( WP_REST_Request $req ) {
		$row = CE_Mail_Store::delete_contact( (int) $req['id'] );
		if ( ! $row ) {
			return self::fail( 'Contact not found.', 404 );
		}
		if ( $row['resend_id'] && ! self::demo_marketing() ) {
			CE_Mail_Resend::remove_contact( CE_Mail_Settings::get( 'resend_audience' ), 'synced' === $row['resend_id'] ? $row['email'] : $row['resend_id'] );
		}
		return array( 'ok' => true, 'counts' => CE_Mail_Store::contact_counts() );
	}

	/* ---------- campaigns ---------- */

	public static function campaigns() {
		$items = CE_Mail_Store::campaigns();
		if ( ! $items && self::demo_marketing() ) {
			return array( 'demo' => true, 'items' => CE_Mail_Store::demo_campaigns() );
		}
		return array( 'demo' => false, 'items' => $items );
	}

	public static function campaign( WP_REST_Request $req ) {
		$c = CE_Mail_Store::campaign( (int) $req['id'] );
		if ( ! $c ) {
			foreach ( CE_Mail_Store::demo_campaigns() as $d ) {
				if ( $d['id'] === (int) $req['id'] ) {
					$d['html'] = '<p>Dear friends,</p><p>This is a sample campaign so you can see how the editor and preview work. Replace this text with your own.</p><p>See you Sunday!</p>';
					$d['text'] = '';
					$d['demo'] = true;
					return $d;
				}
			}
			return self::fail( 'Campaign not found.', 404 );
		}
		return $c;
	}

	public static function campaign_save( WP_REST_Request $req ) {
		$id = (int) $req['id'];
		if ( $id ) {
			$existing = CE_Mail_Store::campaign( $id );
			if ( ! $existing ) {
				return self::fail( 'Campaign not found.', 404 );
			}
			if ( 'draft' !== $existing['status'] ) {
				return self::fail( 'A campaign that has been sent cannot be edited. Duplicate it instead.' );
			}
		}
		$s    = CE_Mail_Settings::all();
		$data = array(
			'name'       => $req['name'] ? $req['name'] : ( $req['subject'] ? $req['subject'] : 'Untitled campaign' ),
			'subject'    => $req['subject'],
			'from_email' => $req['from_email'] ? $req['from_email'] : $s['marketing_from'],
			'from_name'  => $req['from_name'] ? $req['from_name'] : $s['marketing_name'],
			'reply_to'   => $req['reply_to'] ? $req['reply_to'] : $s['marketing_reply'],
			'html'       => $req['html'],
			'text'       => $req['text'],
		);
		$id = CE_Mail_Store::save_campaign( $data, $id );
		return CE_Mail_Store::campaign( $id );
	}

	public static function campaign_delete( WP_REST_Request $req ) {
		return array( 'ok' => CE_Mail_Store::delete_campaign( (int) $req['id'] ) );
	}

	private static function campaign_html( $c ) {
		$inner = $c['html'] ? $c['html'] : nl2br( esc_html( $c['text'] ) );
		return CE_Mail_Resend::wrap_html( $c['subject'], $inner );
	}

	public static function campaign_preview( WP_REST_Request $req ) {
		$c = self::campaign( $req );
		if ( is_wp_error( $c ) ) {
			return $c;
		}
		return array( 'html' => str_replace( '{{{RESEND_UNSUBSCRIBE_URL}}}', '#', self::campaign_html( $c ) ) );
	}

	public static function campaign_test( WP_REST_Request $req ) {
		$c  = CE_Mail_Store::campaign( (int) $req['id'] );
		$to = sanitize_email( (string) $req['to'] );
		if ( ! $c ) {
			return self::fail( 'Save the campaign first.', 404 );
		}
		if ( ! is_email( $to ) ) {
			return self::fail( 'Enter the address the test should go to.' );
		}
		if ( self::demo_marketing() ) {
			return self::fail( 'Connect Resend first (Mail → Settings in the dashboard).', 409 );
		}
		$from = CE_Mail_MIME::format_address( $c['from_email'], $c['from_name'] );
		$res  = CE_Mail_Resend::send_email( $from, $to, '[Test] ' . $c['subject'], str_replace( '{{{RESEND_UNSUBSCRIBE_URL}}}', '#', self::campaign_html( $c ) ), $c['text'], $c['reply_to'] );
		if ( is_wp_error( $res ) ) {
			return self::fail( $res->get_error_message(), 502 );
		}
		return array( 'ok' => true );
	}

	public static function campaign_send( WP_REST_Request $req ) {
		$c = CE_Mail_Store::campaign( (int) $req['id'] );
		if ( ! $c ) {
			return self::fail( 'Save the campaign first.', 404 );
		}
		if ( 'draft' !== $c['status'] ) {
			return self::fail( 'This campaign has already been sent.' );
		}
		if ( self::demo_marketing() ) {
			return self::fail( 'Connect Resend first (Mail → Settings in the dashboard).', 409 );
		}
		if ( '' === trim( $c['subject'] ) || ( '' === trim( (string) $c['html'] ) && '' === trim( (string) $c['text'] ) ) ) {
			return self::fail( 'The campaign needs a subject and a message.' );
		}
		$counts = CE_Mail_Store::contact_counts();
		if ( $counts['unsynced'] > 0 ) {
			return self::fail( $counts['unsynced'] . ' contacts have not been synced to Resend yet. Open Contacts and click Sync, then send.', 409 );
		}
		$audience = CE_Mail_Settings::get( 'resend_audience' );
		$from     = CE_Mail_MIME::format_address( $c['from_email'], $c['from_name'] );
		$res      = CE_Mail_Resend::create_broadcast( $audience, $from, $c['subject'], self::campaign_html( $c ), $c['text'], $c['name'], $c['reply_to'] );
		if ( is_wp_error( $res ) || empty( $res['id'] ) ) {
			return self::fail( is_wp_error( $res ) ? $res->get_error_message() : 'Resend did not return a broadcast id.', 502 );
		}
		CE_Mail_Store::set_campaign_status( $c['id'], 'sending', $res['id'] );
		$sent = CE_Mail_Resend::send_broadcast( $res['id'] );
		if ( is_wp_error( $sent ) ) {
			CE_Mail_Store::set_campaign_status( $c['id'], 'failed' );
			return self::fail( $sent->get_error_message(), 502 );
		}
		CE_Mail_Store::set_campaign_status( $c['id'], 'sent' );
		return array( 'ok' => true, 'campaign' => CE_Mail_Store::campaign( $c['id'] ) );
	}

	/* ---------- email administration (Purelymail account) ---------- */

	private static function demo_admin() {
		return ! CE_Mail_Purelymail::ready();
	}

	public static function admin_status() {
		$out = array(
			'connected' => CE_Mail_Purelymail::ready(),
			'domain'    => CE_Mail_Purelymail::domain(),
			'settings'  => admin_url( 'admin.php?page=ce-mail' ),
			'credit'    => '',
			'error'     => '',
		);
		if ( $out['connected'] ) {
			$check = CE_Mail_Purelymail::check();
			if ( is_wp_error( $check ) ) {
				$out['error'] = $check->get_error_message();
			} else {
				$out['credit'] = $check['credit'];
			}
		}
		return $out;
	}

	public static function admin_mailboxes() {
		if ( self::demo_admin() ) {
			return array( 'demo' => true, 'items' => array( 'dan@example.org', 'don@example.org', 'scott@example.org' ) );
		}
		$users = CE_Mail_Purelymail::users();
		if ( is_wp_error( $users ) ) {
			return $users;
		}
		return array( 'demo' => false, 'items' => $users );
	}

	public static function admin_mailbox_create( WP_REST_Request $req ) {
		$local = CE_Mail_Groups::clean_address( (string) $req['local'] );
		if ( ! $local ) {
			return self::fail( 'Enter the part before the @ (letters, numbers, dots, dashes).' );
		}
		$password = (string) $req['password'];
		if ( '' === $password ) {
			$password = CE_Mail_Purelymail::suggest_password();
		}
		if ( strlen( $password ) < 12 ) {
			return self::fail( 'Use a password of at least 12 characters, or leave it blank to get one suggested.' );
		}
		$recovery = sanitize_email( (string) $req['recovery'] );
		if ( self::demo_admin() ) {
			return self::fail( 'Connect the Purelymail API token first (Mail → Settings in the dashboard).', 409 );
		}
		$res = CE_Mail_Purelymail::create_user( $local, $password, $recovery, ! empty( $req['welcome'] ) );
		if ( is_wp_error( $res ) ) {
			return self::fail( $res->get_error_message(), 502 );
		}
		return array( 'ok' => true, 'email' => $local . '@' . CE_Mail_Purelymail::domain(), 'password' => $password );
	}

	public static function admin_mailbox_password( WP_REST_Request $req ) {
		$email = strtolower( sanitize_email( (string) $req['email'] ) );
		if ( ! is_email( $email ) ) {
			return self::fail( 'Pick a mailbox.' );
		}
		$password = (string) $req['password'];
		if ( '' === $password ) {
			$password = CE_Mail_Purelymail::suggest_password();
		}
		if ( strlen( $password ) < 12 ) {
			return self::fail( 'Use a password of at least 12 characters, or leave it blank to get one suggested.' );
		}
		if ( self::demo_admin() ) {
			return self::fail( 'Connect the Purelymail API token first (Mail → Settings in the dashboard).', 409 );
		}
		$res = CE_Mail_Purelymail::reset_password( $email, $password );
		if ( is_wp_error( $res ) ) {
			return self::fail( $res->get_error_message(), 502 );
		}
		return array( 'ok' => true, 'email' => $email, 'password' => $password );
	}

	/**
	 * Addresses the member picker can offer: mailboxes on the domain, website users,
	 * and people already in a group. One entry per address, mailboxes first.
	 */
	public static function admin_suggestions() {
		$out = array();
		$add = function ( $email, $label, $source ) use ( &$out ) {
			$email = strtolower( trim( (string) $email ) );
			if ( ! is_email( $email ) ) {
				return;
			}
			if ( isset( $out[ $email ] ) ) {
				if ( '' !== $label && '' === $out[ $email ]['label'] ) {
					$out[ $email ]['label'] = $label;
				}
				return;
			}
			$out[ $email ] = array( 'email' => $email, 'label' => (string) $label, 'source' => $source );
		};
		if ( self::demo_admin() ) {
			foreach ( array( 'dan@example.org', 'don@example.org', 'scott@example.org' ) as $u ) {
				$add( $u, '', 'mailbox' );
			}
		} else {
			$users = get_transient( 'ce_mail_pm_users' );
			if ( ! is_array( $users ) ) {
				$users = CE_Mail_Purelymail::users();
				if ( ! is_wp_error( $users ) ) {
					set_transient( 'ce_mail_pm_users', $users, 60 );
				}
			}
			if ( is_array( $users ) ) {
				foreach ( $users as $u ) {
					$add( $u, '', 'mailbox' );
				}
			}
		}
		foreach ( get_users( array( 'number' => 300, 'orderby' => 'display_name' ) ) as $u ) {
			$add( $u->user_email, $u->display_name, 'website user' );
		}
		foreach ( CE_Mail_Groups::all() as $g ) {
			foreach ( $g['members'] as $m ) {
				$add( $m, '', 'in ' . $g['name'] );
			}
		}
		return array( 'items' => array_values( $out ) );
	}

	public static function admin_groups() {
		$items = CE_Mail_Groups::all();
		if ( ! $items && self::demo_admin() ) {
			return array( 'demo' => true, 'items' => CE_Mail_Groups::demo(), 'domain' => CE_Mail_Purelymail::domain() );
		}
		return array( 'demo' => false, 'items' => $items, 'domain' => CE_Mail_Purelymail::domain() );
	}

	public static function admin_group_save( WP_REST_Request $req ) {
		$id = (int) $req['id'];
		if ( $id && ! CE_Mail_Groups::get( $id ) ) {
			return self::fail( 'Group not found.', 404 );
		}
		$res = CE_Mail_Groups::save( array( 'name' => $req['name'], 'address' => $req['address'], 'description' => $req['description'] ), $id );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		if ( null !== $req['members'] ) {
			CE_Mail_Groups::set_members( $res, (array) $req['members'] );
		}
		return array( 'ok' => true, 'group' => CE_Mail_Groups::get( $res ) );
	}

	public static function admin_group_members( WP_REST_Request $req ) {
		$g = CE_Mail_Groups::get( (int) $req['id'] );
		if ( ! $g ) {
			return self::fail( 'Group not found.', 404 );
		}
		CE_Mail_Groups::set_members( $g['id'], (array) $req['members'] );
		return array( 'ok' => true, 'group' => CE_Mail_Groups::get( $g['id'] ) );
	}

	public static function admin_group_delete( WP_REST_Request $req ) {
		$g = CE_Mail_Groups::get( (int) $req['id'] );
		if ( ! $g ) {
			return self::fail( 'Group not found.', 404 );
		}
		$dropped = CE_Mail_Groups::drop_rule( $g );
		CE_Mail_Groups::delete( $g['id'] );
		return array( 'ok' => true, 'warning' => is_wp_error( $dropped ) ? 'The group is gone but its Purelymail rule could not be removed: ' . $dropped->get_error_message() : '' );
	}

	public static function admin_rules() {
		if ( self::demo_admin() ) {
			return array( 'demo' => true, 'items' => array(), 'domain' => CE_Mail_Purelymail::domain() );
		}
		$rules = CE_Mail_Purelymail::rules();
		if ( is_wp_error( $rules ) ) {
			return $rules;
		}
		return array( 'demo' => false, 'items' => $rules, 'domain' => CE_Mail_Purelymail::domain() );
	}

	public static function admin_sync() {
		if ( self::demo_admin() ) {
			return self::fail( 'Connect the Purelymail API token first (Mail → Settings in the dashboard).', 409 );
		}
		$res = CE_Mail_Groups::sync();
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$res['groups_full'] = CE_Mail_Groups::all();
		return $res;
	}
}
