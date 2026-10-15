<?php
/**
 * A small IMAP client over TLS, enough for a simple inbox: folders, list, read,
 * flag, move, append. No extension needed beyond OpenSSL streams.
 */

defined( 'ABSPATH' ) || exit;

class CE_Mail_IMAP {

	private $host;
	private $port;
	private $timeout;
	private $sock = null;
	private $tag  = 0;
	private $caps = array();
	public $selected = '';

	public function __construct( $host, $port = 993, $timeout = 25 ) {
		$this->host    = $host;
		$this->port    = (int) $port;
		$this->timeout = $timeout;
	}

	/* ---------- connection ---------- */

	public function connect() {
		$ctx  = stream_context_create( array( 'ssl' => array( 'verify_peer' => true, 'verify_peer_name' => true, 'SNI_enabled' => true ) ) );
		$this->sock = @stream_socket_client( 'ssl://' . $this->host . ':' . $this->port, $errno, $errstr, $this->timeout, STREAM_CLIENT_CONNECT, $ctx );
		if ( ! $this->sock ) {
			throw new Exception( 'Could not reach the mail server (' . $errstr . ').' );
		}
		stream_set_timeout( $this->sock, $this->timeout );
		$greeting = $this->read_line();
		if ( 0 !== strpos( $greeting, '* OK' ) && 0 !== strpos( $greeting, '* PREAUTH' ) ) {
			throw new Exception( 'Unexpected greeting from the mail server.' );
		}
		return true;
	}

	public function login( $user, $pass ) {
		$r = $this->command( 'LOGIN ' . $this->quote( $user ) . ' ' . $this->quote( $pass ) );
		if ( 'OK' !== $r['status'] ) {
			throw new Exception( 'The mail server did not accept that email and password.' );
		}
		$c = $this->command( 'CAPABILITY' );
		foreach ( $c['records'] as $rec ) {
			if ( 0 === strpos( $rec['text'], '* CAPABILITY' ) ) {
				$this->caps = array_map( 'strtoupper', preg_split( '/\s+/', trim( substr( $rec['text'], 12 ) ) ) );
			}
		}
		return true;
	}

	public function logout() {
		if ( $this->sock ) {
			try {
				$this->command( 'LOGOUT' );
			} catch ( Exception $e ) {
				// closing anyway
			}
			fclose( $this->sock );
			$this->sock = null;
		}
	}

	public function has_cap( $cap ) {
		return in_array( strtoupper( $cap ), $this->caps, true );
	}

	/* ---------- folders ---------- */

	public function list_folders() {
		$r   = $this->command( 'LIST "" "*"' );
		$out = array();
		foreach ( $r['records'] as $rec ) {
			if ( ! preg_match( '/^\* LIST \(([^)]*)\) (?:"([^"]*)"|NIL) (.*)$/s', $rec['text'], $m ) ) {
				continue;
			}
			$flags = strtolower( $m[1] );
			$name  = trim( $m[3] );
			if ( '' === $name && ! empty( $rec['literals'] ) ) {
				$name = $rec['literals'][0];
			} elseif ( preg_match( '/^"(.*)"$/s', $name, $q ) ) {
				$name = stripslashes( $q[1] );
			} elseif ( preg_match( '/^\{\d+\}$/', $name ) && ! empty( $rec['literals'] ) ) {
				$name = $rec['literals'][0];
			}
			if ( false !== strpos( $flags, '\\noselect' ) ) {
				continue;
			}
			$out[] = array( 'name' => $name, 'flags' => $flags, 'delimiter' => isset( $m[2] ) ? $m[2] : '/' );
		}
		return $out;
	}

	public function create( $folder ) {
		$r = $this->command( 'CREATE ' . $this->quote( $folder ) );
		return 'OK' === $r['status'];
	}

	public function status( $folder ) {
		$r = $this->command( 'STATUS ' . $this->quote( $folder ) . ' (MESSAGES UNSEEN)' );
		$out = array( 'messages' => 0, 'unseen' => 0 );
		foreach ( $r['records'] as $rec ) {
			if ( preg_match( '/MESSAGES (\d+)/', $rec['text'], $m ) ) {
				$out['messages'] = (int) $m[1];
			}
			if ( preg_match( '/UNSEEN (\d+)/', $rec['text'], $m ) ) {
				$out['unseen'] = (int) $m[1];
			}
		}
		return $out;
	}

	public function select( $folder ) {
		$r = $this->command( 'SELECT ' . $this->quote( $folder ) );
		if ( 'OK' !== $r['status'] ) {
			throw new Exception( 'That folder could not be opened.' );
		}
		$this->selected = $folder;
		$exists         = 0;
		foreach ( $r['records'] as $rec ) {
			if ( preg_match( '/^\* (\d+) EXISTS/', $rec['text'], $m ) ) {
				$exists = (int) $m[1];
			}
		}
		return $exists;
	}

	/* ---------- messages ---------- */

	/**
	 * UIDs matching the criteria, ascending.
	 */
	public function search( $criteria = 'ALL' ) {
		$r    = $this->command( 'UID SEARCH ' . $criteria );
		$uids = array();
		foreach ( $r['records'] as $rec ) {
			if ( 0 === strpos( $rec['text'], '* SEARCH' ) ) {
				$uids = array_merge( $uids, array_map( 'intval', array_filter( preg_split( '/\s+/', trim( substr( $rec['text'], 8 ) ) ) ) ) );
			}
		}
		sort( $uids );
		return $uids;
	}

	/**
	 * Header summaries for a set of UIDs: flags, date, size, from, to, subject, attachment hint.
	 */
	public function fetch_summaries( array $uids ) {
		if ( ! $uids ) {
			return array();
		}
		$set = implode( ',', array_map( 'intval', $uids ) );
		$r   = $this->command( 'UID FETCH ' . $set . ' (UID FLAGS INTERNALDATE RFC822.SIZE BODYSTRUCTURE BODY.PEEK[HEADER.FIELDS (FROM TO CC SUBJECT DATE MESSAGE-ID IN-REPLY-TO REFERENCES)])' );
		$out = array();
		foreach ( $r['records'] as $rec ) {
			if ( ! preg_match( '/^\* \d+ FETCH /', $rec['text'] ) || ! preg_match( '/\bUID (\d+)/', $rec['text'], $u ) ) {
				continue;
			}
			$flags   = preg_match( '/FLAGS \(([^)]*)\)/', $rec['text'], $f ) ? strtolower( $f[1] ) : '';
			$idate   = preg_match( '/INTERNALDATE "([^"]+)"/', $rec['text'], $d ) ? $d[1] : '';
			$size    = preg_match( '/RFC822\.SIZE (\d+)/', $rec['text'], $s ) ? (int) $s[1] : 0;
			$headers = CE_Mail_MIME::parse_headers( ! empty( $rec['literals'] ) ? $rec['literals'][0] : '' );
			$struct  = strtolower( $rec['text'] );
			$has_att = ( false !== strpos( $struct, '"attachment"' ) ) || ( substr_count( $struct, '("application"' ) > 0 ) || ( false !== strpos( $struct, '"image"' ) && false !== strpos( $struct, 'mixed' ) );
			$date    = ! empty( $headers['date'] ) ? strtotime( $headers['date'] ) : false;
			if ( ! $date && $idate ) {
				$date = strtotime( $idate );
			}
			$out[ (int) $u[1] ] = array(
				'uid'         => (int) $u[1],
				'seen'        => false !== strpos( $flags, '\\seen' ),
				'flagged'     => false !== strpos( $flags, '\\flagged' ),
				'answered'    => false !== strpos( $flags, '\\answered' ),
				'date'        => $date ? $date : time(),
				'size'        => $size,
				'from'        => CE_Mail_MIME::first_address( isset( $headers['from'] ) ? $headers['from'] : '' ),
				'to'          => CE_Mail_MIME::addresses( isset( $headers['to'] ) ? $headers['to'] : '' ),
				'subject'     => isset( $headers['subject'] ) ? $headers['subject'] : '',
				'message_id'  => isset( $headers['message-id'] ) ? $headers['message-id'] : '',
				'attachments' => $has_att,
			);
		}
		return $out;
	}

	/**
	 * The whole raw message (headers + body).
	 */
	public function fetch_raw( $uid, $max_bytes = 8000000 ) {
		$r = $this->command( 'UID FETCH ' . (int) $uid . ' (BODY.PEEK[])', $max_bytes );
		foreach ( $r['records'] as $rec ) {
			if ( preg_match( '/^\* \d+ FETCH /', $rec['text'] ) && ! empty( $rec['literals'] ) ) {
				return $rec['literals'][0];
			}
		}
		throw new Exception( 'That message could not be loaded.' );
	}

	public function store_flag( array $uids, $flag, $add = true ) {
		$set = implode( ',', array_map( 'intval', $uids ) );
		$r   = $this->command( 'UID STORE ' . $set . ' ' . ( $add ? '+' : '-' ) . 'FLAGS.SILENT (' . $flag . ')' );
		return 'OK' === $r['status'];
	}

	public function move( array $uids, $dest ) {
		$set = implode( ',', array_map( 'intval', $uids ) );
		if ( $this->has_cap( 'MOVE' ) ) {
			$r = $this->command( 'UID MOVE ' . $set . ' ' . $this->quote( $dest ) );
			return 'OK' === $r['status'];
		}
		$r = $this->command( 'UID COPY ' . $set . ' ' . $this->quote( $dest ) );
		if ( 'OK' !== $r['status'] ) {
			return false;
		}
		$this->command( 'UID STORE ' . $set . ' +FLAGS.SILENT (\Deleted)' );
		$this->command( 'EXPUNGE' );
		return true;
	}

	public function append( $folder, $raw, $flags = '\Seen' ) {
		$raw = str_replace( "\r\n", "\n", $raw );
		$raw = str_replace( "\n", "\r\n", $raw );
		$tag = $this->next_tag();
		$this->write( $tag . ' APPEND ' . $this->quote( $folder ) . ' (' . $flags . ') {' . strlen( $raw ) . "}\r\n" );
		$line = $this->read_line();
		if ( 0 !== strpos( $line, '+' ) ) {
			return false;
		}
		$this->write( $raw . "\r\n" );
		$r = $this->read_response( $tag );
		return 'OK' === $r['status'];
	}

	/* ---------- protocol plumbing ---------- */

	private function next_tag() {
		$this->tag++;
		return 'A' . str_pad( (string) $this->tag, 4, '0', STR_PAD_LEFT );
	}

	public function quote( $s ) {
		return '"' . str_replace( array( '\\', '"' ), array( '\\\\', '\\"' ), (string) $s ) . '"';
	}

	private function write( $data ) {
		if ( ! $this->sock ) {
			throw new Exception( 'Not connected.' );
		}
		$len = strlen( $data );
		$off = 0;
		while ( $off < $len ) {
			$n = fwrite( $this->sock, substr( $data, $off ) );
			if ( false === $n || 0 === $n ) {
				throw new Exception( 'The connection to the mail server dropped.' );
			}
			$off += $n;
		}
	}

	private function read_line() {
		$line = '';
		while ( true ) {
			$chunk = fgets( $this->sock, 8192 );
			if ( false === $chunk ) {
				$meta = stream_get_meta_data( $this->sock );
				throw new Exception( ! empty( $meta['timed_out'] ) ? 'The mail server took too long to answer.' : 'The connection to the mail server dropped.' );
			}
			$line .= $chunk;
			if ( "\n" === substr( $chunk, -1 ) ) {
				break;
			}
		}
		return rtrim( $line, "\r\n" );
	}

	private function read_literal( $n ) {
		$data = '';
		while ( strlen( $data ) < $n ) {
			$chunk = fread( $this->sock, min( 65536, $n - strlen( $data ) ) );
			if ( false === $chunk || '' === $chunk ) {
				throw new Exception( 'The connection to the mail server dropped mid-message.' );
			}
			$data .= $chunk;
		}
		return $data;
	}

	/**
	 * Send a command and collect its untagged records and tagged status.
	 */
	private function command( $cmd, $max_literal = 2000000 ) {
		$tag = $this->next_tag();
		$this->write( $tag . ' ' . $cmd . "\r\n" );
		return $this->read_response( $tag, $max_literal );
	}

	private function read_response( $tag, $max_literal = 2000000 ) {
		$records = array();
		while ( true ) {
			$line = $this->read_line();
			if ( 0 === strpos( $line, $tag . ' ' ) ) {
				$rest   = substr( $line, strlen( $tag ) + 1 );
				$status = strtoupper( strtok( $rest, ' ' ) );
				return array( 'status' => $status, 'text' => $rest, 'records' => $records );
			}
			$rec = array( 'text' => $line, 'literals' => array() );
			while ( preg_match( '/\{(\d+)\}$/', $rec['text'], $m ) ) {
				$n = (int) $m[1];
				if ( $n > $max_literal ) {
					$this->read_literal( $n ); // discard oversized data to stay in sync
					$rec['literals'][] = '';
				} else {
					$rec['literals'][] = $this->read_literal( $n );
				}
				$rec['text'] .= $this->read_line();
			}
			$records[] = $rec;
		}
	}
}
