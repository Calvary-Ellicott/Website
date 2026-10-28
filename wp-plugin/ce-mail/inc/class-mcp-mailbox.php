<?php
/** One token-bound IMAP connection per tool call, closed by the dispatcher. */
defined( 'ABSPATH' ) || exit;

class CE_Mail_MCP_Mailbox {
	private $grant;
	private $imap;
	private $folders;
	private $deadline;
	public function __construct( $grant ) { $this->grant = $grant; $this->deadline = microtime( true ) + 20; }
	public function close() { if ( $this->imap ) { $this->imap->logout(); } }
	private function imap() {
		if ( microtime( true ) >= $this->deadline ) { throw new RuntimeException( 'Time budget exceeded.' ); }
		if ( ! $this->imap ) {
			$password = CE_Mail_Crypto::decrypt( $this->grant['mailbox_secret'] );
			if ( ! $password ) { throw new InvalidArgumentException( 'Mailbox credentials are no longer available. Please reconnect.' ); }
			$this->imap = new CE_Mail_IMAP( CE_Mail_Settings::get( 'imap_host' ), CE_Mail_Settings::get( 'imap_port' ), 8 );
			$this->imap->set_deadline( $this->deadline );
			$this->imap->connect();
			try { $this->imap->login( $this->grant['mailbox_email'], $password ); }
			catch ( Throwable $e ) { throw new InvalidArgumentException( 'Could not sign in to the mailbox. Please reconnect if the password changed.' ); }
		}
		return $this->imap;
	}
	private function folders() {
		if ( null === $this->folders ) { $this->folders = CE_Mail_Mailbox::folders( $this->imap() ); }
		return $this->folders;
	}
	private function folder( $name ) {
		if ( 'spam' === $name ) { $name = 'junk'; }
		foreach ( $this->folders() as $f ) { if ( $f['name'] === $name || $f['role'] === $name ) { return $f['name']; } }
		throw new InvalidArgumentException( 'Folder not found. Use list_folders to choose a folder.' );
	}
	public static function split_id( $id ) {
		if ( ! preg_match( '/^(.+):([1-9][0-9]{0,9})$/D', $id, $m ) || (float) $m[2] > 4294967295 || preg_match( '/[\x00-\x1f\x7f]/', $m[1] ) ) { throw new InvalidArgumentException( 'Invalid message ID. Use the folder:uid returned by a mail tool.' ); }
		return array( $m[1], (int) $m[2] );
	}
	public static function since( $date ) {
		if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/D', $date, $m ) || ! checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) { throw new InvalidArgumentException( 'since must be a valid YYYY-MM-DD date.' ); }
		return gmdate( 'd-M-Y', strtotime( $date . 'T00:00:00Z' ) );
	}
	public function call( $name, $args ) {
		if ( 'list_folders' === $name ) {
			$out = array();
			foreach ( array_slice( $this->folders(), 0, 50 ) as $f ) {
				$s = $this->imap()->status( $f['name'] );
				$out[] = array( 'role' => 'junk' === $f['role'] ? 'spam' : $f['role'], 'name' => $f['name'], 'unread' => $s['unseen'], 'total' => $s['messages'] );
			}
			return array( 'items' => $out, 'truncated' => count( $this->folders() ) > 50 );
		}
		if ( in_array( $name, array( 'list_messages', 'search_messages' ), true ) ) { return $this->listing( $args, 'search_messages' === $name ); }
		if ( 'get_message' === $name ) { return $this->message( $args['id'], ! empty( $args['include_quoted'] ) ); }
		if ( 'get_thread' === $name ) { return $this->thread( $args['id'] ); }
		if ( in_array( $name, array( 'mark_read', 'flag', 'archive' ), true ) ) { return $this->organize( $name, $args ); }
		if ( in_array( $name, array( 'create_draft', 'send_message' ), true ) ) { return $this->compose( $args, 'send_message' === $name ); }
		throw new InvalidArgumentException( 'Tool not found.' );
	}
	private function listing( $a, $search ) {
		$criteria = ! empty( $a['unread_only'] ) ? 'UNSEEN' : 'ALL';
		if ( isset( $a['since'] ) ) { $criteria .= ' SINCE ' . self::since( $a['since'] ); }
		if ( $search ) { $criteria .= ' TEXT ' . $this->imap()->quote( $a['query'] ); }
		$folders = array();
		if ( isset( $a['folder'] ) || ! $search ) { $folders[] = $this->folder( isset( $a['folder'] ) ? $a['folder'] : 'inbox' ); }
		else { foreach ( $this->folders() as $f ) { if ( in_array( $f['role'], array( 'inbox', 'archive', 'sent' ), true ) ) { $folders[] = $f['name']; } } }
		$limit = isset( $a['limit'] ) ? $a['limit'] : 20; $page = isset( $a['page'] ) ? $a['page'] : 1;
		$out = array(); $total = 0;
		foreach ( array_unique( $folders ) as $folder ) {
			$imap = $this->imap(); $imap->select( $folder );
			$uids = array_reverse( $imap->search( $criteria ) ); $total += count( $uids );
			$slice = array_slice( $uids, ( $page - 1 ) * $limit, $limit );
			foreach ( $imap->fetch_summaries( $slice, true ) as $m ) {
				$out[] = array( 'id' => $folder . ':' . $m['uid'], 'from' => $m['from'], 'to' => $m['to'], 'subject' => $m['subject'], 'date' => gmdate( 'c', $m['date'] ), 'snippet' => $m['snippet'], 'unread' => ! $m['seen'], 'flagged' => $m['flagged'], 'has_attachments' => $m['attachments'] );
			}
		}
		usort( $out, function ( $x, $y ) { return strcmp( $y['date'], $x['date'] ); } );
		return array( 'items' => array_slice( $out, 0, $limit ), 'total' => $total, 'page' => $page, 'truncated' => $total > $page * $limit );
	}
	private function message( $id, $quoted = false ) {
		list( $folder, $uid ) = self::split_id( $id );
		$folder = $this->folder( $folder ); $imap = $this->imap(); $imap->select( $folder );
		$raw = $imap->fetch_raw( $uid, 2000000 );
		if ( '' === $raw ) { throw new InvalidArgumentException( 'Message exceeds the connector size limit. Open it in the mail portal.' ); }
		$p = CE_Mail_MIME::parse_message( $raw ); $h = $p['headers']; $text = $p['text'];
		if ( '' === $text && $p['html'] ) { $text = html_entity_decode( wp_strip_all_tags( preg_replace( '#<(br|/p|/div|/li)[^>]*>#i', "\n", CE_Mail_MIME::safe_html( $p['html'] ) ) ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ); }
		if ( ! $quoted ) { $text = self::without_quotes( $text ); }
		$atts = array(); foreach ( array_slice( $p['attachments'], 0, 50 ) as $att ) { $atts[] = array( 'name' => $att['name'], 'size' => $att['size'], 'type' => $att['type'] ); }
		$date = ! empty( $h['date'] ) ? strtotime( $h['date'] ) : false;
		return array( 'id' => $folder . ':' . $uid, 'from' => CE_Mail_MIME::first_address( isset( $h['from'] ) ? $h['from'] : '' ), 'to' => CE_Mail_MIME::addresses( isset( $h['to'] ) ? $h['to'] : '' ), 'cc' => CE_Mail_MIME::addresses( isset( $h['cc'] ) ? $h['cc'] : '' ), 'subject' => isset( $h['subject'] ) ? $h['subject'] : '', 'date' => $date ? gmdate( 'c', $date ) : null, 'message_id' => isset( $h['message-id'] ) ? $h['message-id'] : '', 'in_reply_to' => isset( $h['in-reply-to'] ) ? $h['in-reply-to'] : '', 'references' => isset( $h['references'] ) ? $h['references'] : '', 'text' => self::text_limit( $text, 65536 ), 'truncated' => strlen( $text ) > 65536, 'attachments' => $atts );
	}
	public static function text_limit( $text, $bytes ) {
		return function_exists( 'mb_strcut' ) ? mb_strcut( $text, 0, $bytes, 'UTF-8' ) : wp_check_invalid_utf8( substr( $text, 0, $bytes ), true );
	}
	public static function without_quotes( $text ) {
		$text = preg_split( '/\n(?:On .{1,500}wrote:|[- ]{2,}Original Message[- ]{2,})\s*\n/i', $text, 2 )[0];
		return trim( preg_replace( '/^>.*(?:\n|$)/m', '', $text ) );
	}
	private function thread( $id ) {
		$first = $this->message( $id ); list( $folder ) = self::split_id( $first['id'] );
		preg_match_all( '/<[^<>\s]{1,250}>/', $first['message_id'] . ' ' . $first['in_reply_to'] . ' ' . $first['references'], $m );
		$references = array_slice( array_unique( $m[0] ), -10 ); $out = array( $first['id'] => $first );
		$folders = array( $folder ); foreach ( $this->folders() as $f ) { if ( 'sent' === $f['role'] ) { $folders[] = $f['name']; } }
		$bytes = strlen( $first['text'] ); $truncated = false;
		foreach ( array_unique( $folders ) as $folder ) {
			$imap = $this->imap(); $imap->select( $folder ); $uids = array();
			foreach ( $references as $reference ) {
				$q = $imap->quote( $reference );
				$uids = array_unique( array_merge( $uids, $imap->search( 'OR OR HEADER Message-ID ' . $q . ' HEADER References ' . $q . ' HEADER In-Reply-To ' . $q ) ) );
				if ( count( $uids ) > 50 ) { $truncated = true; break; }
			}
			foreach ( array_slice( $uids, 0, 50 ) as $uid ) {
				$key = $folder . ':' . $uid; if ( isset( $out[ $key ] ) ) { continue; }
				if ( count( $out ) >= 50 || $bytes >= 262144 ) { $truncated = true; break 2; }
				$msg = $this->message( $key );
				$bytes += strlen( $msg['text'] ); $out[ $key ] = $msg;
			}
		}
		$out = array_values( $out ); usort( $out, function ( $a, $b ) { return strcmp( (string) $a['date'], (string) $b['date'] ); } );
		return array( 'items' => $out, 'truncated' => $truncated );
	}
	private function organize( $name, $a ) {
		$groups = array();
		// Validate every ID before the first mutation.
		foreach ( array_unique( $a['ids'] ) as $id ) { list( $f, $uid ) = self::split_id( $id ); $groups[ $this->folder( $f ) ][] = $uid; }
		$imap = $this->imap(); $dest = '';
		if ( 'archive' === $name ) {
			if ( ! $imap->has_cap( 'MOVE' ) ) { throw new InvalidArgumentException( 'This mail server does not support safe archive moves. Use the mail portal.' ); }
			foreach ( $this->folders() as $f ) { if ( 'archive' === $f['role'] ) { $dest = $f['name']; } }
			if ( ! $dest ) { if ( ! $imap->create( 'Archive' ) ) { throw new RuntimeException(); } $dest = 'Archive'; }
		}
		$count = 0;
		foreach ( $groups as $folder => $uids ) {
			$imap->select( $folder );
			$uids = array_keys( $imap->fetch_summaries( $uids ) );
			if ( ! $uids || $folder === $dest ) { continue; }
			$ok = 'archive' === $name ? $imap->move( $uids, $dest ) : $imap->store_flag( $uids, 'flag' === $name ? '\\Flagged' : '\\Seen', 'flag' === $name ? $a['flagged'] : $a['read'] );
			if ( ! $ok ) { throw new RuntimeException(); }
			$count += count( $uids );
		}
		return array( 'changed' => $count );
	}
	private function compose( $a, $send ) {
		$reply = isset( $a['reply_to_id'] ) ? $this->message( $a['reply_to_id'], true ) : null;
		$to = isset( $a['to'] ) ? $a['to'] : ( $reply ? $reply['from']['email'] : '' );
		$subject = isset( $a['subject'] ) ? $a['subject'] : ( $reply ? ( preg_match( '/^Re:/i', $reply['subject'] ) ? $reply['subject'] : 'Re: ' . $reply['subject'] ) : '' );
		$cc = isset( $a['cc'] ) ? $a['cc'] : '';
		if ( preg_match( '/[\r\n\x00]/', $to . $cc . $subject ) ) { throw new InvalidArgumentException( 'Mail headers cannot contain line breaks.' ); }
		$tos = CE_Mail_Mailbox::split_recipients( $to ); $ccs = CE_Mail_Mailbox::split_recipients( $cc );
		foreach ( array( $to, $cc ) as $addresses ) {
			foreach ( preg_split( '/[,;]+/', $addresses ) as $address ) {
				if ( '' !== trim( $address ) && ! CE_Mail_Mailbox::split_recipients( $address ) ) { throw new InvalidArgumentException( 'A recipient address is invalid. Correct it before continuing.' ); }
			}
		}
		if ( ! $tos || count( $tos ) + count( $ccs ) > 20 ) { throw new InvalidArgumentException( 'Provide at least one To address and at most 20 recipients.' ); }
		require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
		require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
		require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';
		$m = new PHPMailer\PHPMailer\PHPMailer( true ); $m->CharSet = 'UTF-8'; $m->setFrom( $this->grant['mailbox_email'] );
		foreach ( $tos as $address ) { $m->addAddress( $address ); } foreach ( $ccs as $address ) { $m->addCC( $address ); }
		$m->Subject = $subject; $m->Body = $a['body_text'];
		if ( $reply ) {
			preg_match_all( '/<[^<>\s]{1,250}>/', $reply['message_id'], $ids );
			if ( $ids[0] ) {
				$m->addCustomHeader( 'In-Reply-To', $ids[0][0] );
				preg_match_all( '/<[^<>\s]{1,250}>/', $reply['references'], $refs );
				$m->addCustomHeader( 'References', implode( ' ', array_merge( array_slice( $refs[0], -10 ), array( $ids[0][0] ) ) ) );
			}
		}
		$imap = $this->imap(); $dest = '';
		foreach ( $this->folders() as $f ) { if ( $f['role'] === ( $send ? 'sent' : 'drafts' ) ) { $dest = $f['name']; } }
		if ( ! $dest ) { $dest = $send ? 'Sent' : 'Drafts'; if ( ! $imap->create( $dest ) ) { throw new RuntimeException(); } }
		if ( $send ) {
			if ( ! CE_Mail_OAuth::enabled() || ! CE_Mail_Settings::get( 'ai_send' ) || ! in_array( 'mail:send', explode( ' ', $this->grant['scope'] ), true ) ) { throw new InvalidArgumentException( 'Sending is not permitted.' ); }
			$s = CE_Mail_Settings::all(); $m->isSMTP(); $m->Host = $s['smtp_host']; $m->Port = (int) $s['smtp_port']; $m->SMTPAuth = true;
			$m->Username = $this->grant['mailbox_email']; $m->Password = CE_Mail_Crypto::decrypt( $this->grant['mailbox_secret'] );
			$m->SMTPSecure = 'tls' === $s['smtp_secure'] ? 'tls' : 'ssl'; $m->Timeout = 8; $m->Timelimit = 15;
			$m->send();
			try { $copied = $imap->append( $dest, $m->getSentMIMEMessage(), '\\Seen' ); } catch ( Throwable $e ) { $copied = false; }
			return array( 'sent' => true, 'id' => $m->getLastMessageID(), 'sent_copy_saved' => $copied, 'warning' => $copied ? '' : 'Mail was sent but the Sent copy could not be saved. Do not send again.' );
		}
		$m->preSend();
		if ( ! $imap->append( $dest, $m->getSentMIMEMessage(), '\\Draft' ) ) { throw new RuntimeException(); }
		// APPENDUID identifies the exact saved draft without a racy "newest message" lookup.
		return array( 'draft_id' => $imap->last_append_uid ? $dest . ':' . $imap->last_append_uid : null, 'saved' => true, 'message_id' => $m->getLastMessageID(), 'warning' => $imap->last_append_uid ? '' : 'Draft saved. The server did not return its UID; inspect Drafts before creating another.' );
	}
}
