<?php
/**
 * Just enough MIME: header parsing, address parsing, multipart bodies,
 * transfer-encoding and charset decoding, attachments, inline images.
 */

defined( 'ABSPATH' ) || exit;

class CE_Mail_MIME {

	/* ---------- headers ---------- */

	public static function parse_headers( $block ) {
		$block = str_replace( "\r\n", "\n", (string) $block );
		$block = preg_replace( "/\n[ \t]+/", ' ', $block ); // unfold
		$out   = array();
		foreach ( explode( "\n", $block ) as $line ) {
			if ( false === strpos( $line, ':' ) ) {
				continue;
			}
			list( $k, $v ) = explode( ':', $line, 2 );
			$k = strtolower( trim( $k ) );
			$v = trim( $v );
			if ( in_array( $k, array( 'subject', 'from', 'to', 'cc', 'bcc', 'reply-to' ), true ) ) {
				$v = self::decode_header( $v );
			}
			if ( ! isset( $out[ $k ] ) ) {
				$out[ $k ] = $v;
			}
		}
		return $out;
	}

	public static function decode_header( $v ) {
		if ( false === strpos( $v, '=?' ) ) {
			return $v;
		}
		if ( function_exists( 'iconv_mime_decode' ) ) {
			$d = @iconv_mime_decode( $v, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8' );
			if ( false !== $d ) {
				return $d;
			}
		}
		if ( function_exists( 'mb_decode_mimeheader' ) ) {
			return mb_decode_mimeheader( $v );
		}
		return $v;
	}

	/**
	 * "Name <a@b>, c@d" -> [ ['name'=>..,'email'=>..], ... ]
	 */
	public static function addresses( $v ) {
		$out = array();
		if ( '' === trim( (string) $v ) ) {
			return $out;
		}
		// split on commas outside quotes and angle brackets
		$parts = preg_split( '/,(?=(?:[^"]*"[^"]*")*[^"]*$)(?![^<]*>)/', $v );
		foreach ( $parts as $p ) {
			$p = trim( $p );
			if ( '' === $p ) {
				continue;
			}
			if ( preg_match( '/^(?:"?([^"<]*?)"?\s*)?<([^<>]+)>$/', $p, $m ) ) {
				$out[] = array( 'name' => trim( $m[1] ), 'email' => trim( $m[2] ) );
			} elseif ( preg_match( '/([^\s@<>]+@[^\s@<>]+)/', $p, $m ) ) {
				$out[] = array( 'name' => '', 'email' => $m[1] );
			}
		}
		return $out;
	}

	public static function first_address( $v ) {
		$a = self::addresses( $v );
		return $a ? $a[0] : array( 'name' => '', 'email' => '' );
	}

	/* ---------- bodies ---------- */

	/**
	 * Parse a raw RFC 2822 message into headers, html/text body, and attachments.
	 */
	public static function parse_message( $raw ) {
		$raw = str_replace( "\r\n", "\n", (string) $raw );
		list( $head, $body ) = self::split( $raw );
		$headers = self::parse_headers( $head );
		$result  = array( 'headers' => $headers, 'html' => '', 'text' => '', 'attachments' => array(), 'inline' => array() );
		self::walk( $headers, $body, $result, '1' );
		// inline images referenced by cid:
		if ( $result['html'] && $result['inline'] ) {
			foreach ( $result['inline'] as $cid => $img ) {
				if ( strlen( $img['content'] ) < 1500000 ) {
					$result['html'] = str_ireplace( 'cid:' . $cid, 'data:' . $img['type'] . ';base64,' . base64_encode( $img['content'] ), $result['html'] );
				}
			}
		}
		return $result;
	}

	private static function split( $block ) {
		$pos = strpos( $block, "\n\n" );
		if ( false === $pos ) {
			return array( $block, '' );
		}
		return array( substr( $block, 0, $pos ), substr( $block, $pos + 2 ) );
	}

	private static function param( $header, $name ) {
		if ( preg_match( '/;\s*' . preg_quote( $name, '/' ) . '\*?=\s*"([^"]*)"/i', $header, $m ) || preg_match( '/;\s*' . preg_quote( $name, '/' ) . '\*?=\s*([^;\s]+)/i', $header, $m ) ) {
			$v = $m[1];
			if ( preg_match( "/^[\w-]*'[\w-]*'(.*)$/", $v, $e ) ) { // RFC 2231
				$v = rawurldecode( $e[1] );
			}
			return self::decode_header( $v );
		}
		return '';
	}

	private static function walk( $headers, $body, &$result, $path ) {
		$ctype = isset( $headers['content-type'] ) ? $headers['content-type'] : 'text/plain';
		$type  = strtolower( trim( strtok( $ctype, ';' ) ) );
		if ( 0 === strpos( $type, 'multipart/' ) ) {
			$boundary = self::param( $ctype, 'boundary' );
			if ( '' === $boundary ) {
				return;
			}
			$pieces = preg_split( '/^--' . preg_quote( $boundary, '/' ) . '(--)?[ \t]*$/m', $body );
			array_shift( $pieces ); // preamble
			$i = 0;
			foreach ( $pieces as $piece ) {
				$piece = ltrim( $piece, "\n" );
				if ( '' === trim( $piece ) ) {
					continue;
				}
				$i++;
				list( $ph, $pb ) = self::split( $piece );
				$sub = self::parse_headers( $ph );
				if ( 'multipart/alternative' === $type ) {
					// take every alternative; html wins later because we only keep the first of each kind
				}
				self::walk( $sub, rtrim( $pb, "\n" ), $result, $path . '.' . $i );
			}
			return;
		}

		$content  = self::decode_body( $body, isset( $headers['content-transfer-encoding'] ) ? $headers['content-transfer-encoding'] : '' );
		$disp     = isset( $headers['content-disposition'] ) ? $headers['content-disposition'] : '';
		$filename = self::param( $disp, 'filename' );
		if ( '' === $filename ) {
			$filename = self::param( $ctype, 'name' );
		}
		$is_attachment = ( 0 === stripos( $disp, 'attachment' ) ) || ( '' !== $filename && 0 !== strpos( $type, 'text/' ) );
		$cid           = isset( $headers['content-id'] ) ? trim( $headers['content-id'], '<> ' ) : '';

		if ( 0 === strpos( $type, 'image/' ) && $cid && ! ( 0 === stripos( $disp, 'attachment' ) ) ) {
			$result['inline'][ $cid ] = array( 'type' => $type, 'content' => $content );
			return;
		}
		if ( $is_attachment ) {
			$result['attachments'][] = array( 'part' => $path, 'name' => $filename ? $filename : 'attachment', 'type' => $type, 'size' => strlen( $content ), 'content' => $content );
			return;
		}
		$charset = self::param( $ctype, 'charset' );
		$content = self::to_utf8( $content, $charset );
		if ( 'text/html' === $type ) {
			if ( '' === $result['html'] ) {
				$result['html'] = $content;
			}
		} elseif ( 'text/plain' === $type || 0 === strpos( $type, 'text/' ) ) {
			if ( '' === $result['text'] ) {
				$result['text'] = $content;
			}
		} elseif ( 'message/rfc822' === $type ) {
			$result['attachments'][] = array( 'part' => $path, 'name' => $filename ? $filename : 'forwarded-message.eml', 'type' => $type, 'size' => strlen( $content ), 'content' => $content );
		}
	}

	public static function decode_body( $body, $encoding ) {
		switch ( strtolower( trim( $encoding ) ) ) {
			case 'base64':
				return base64_decode( preg_replace( '/\s+/', '', $body ) );
			case 'quoted-printable':
				return quoted_printable_decode( $body );
			default:
				return $body;
		}
	}

	public static function to_utf8( $s, $charset ) {
		$charset = strtoupper( trim( $charset ) );
		if ( '' === $charset || 'UTF-8' === $charset || 'US-ASCII' === $charset ) {
			return $s;
		}
		if ( function_exists( 'mb_convert_encoding' ) ) {
			$c = @mb_convert_encoding( $s, 'UTF-8', $charset );
			if ( false !== $c ) {
				return $c;
			}
		}
		if ( function_exists( 'iconv' ) ) {
			$c = @iconv( $charset, 'UTF-8//IGNORE', $s );
			if ( false !== $c ) {
				return $c;
			}
		}
		return $s;
	}

	/**
	 * Email HTML safe to show inside the portal: no scripts, forms, or frames; styles kept.
	 */
	public static function safe_html( $html ) {
		$html = preg_replace( '#<(script|iframe|object|embed|form|meta|link|base)[^>]*>.*?</\1>#is', '', $html );
		$html = preg_replace( '#<(script|iframe|object|embed|form|meta|link|base)[^>]*/?>#i', '', $html );
		$allowed = wp_kses_allowed_html( 'post' );
		foreach ( array( 'table', 'tr', 'td', 'th', 'tbody', 'thead', 'div', 'span', 'p', 'a', 'img', 'ul', 'ol', 'li', 'h1', 'h2', 'h3', 'h4', 'blockquote', 'center', 'font', 'strong', 'em', 'b', 'i', 'u', 'hr', 'br', 'pre', 'code' ) as $tag ) {
			if ( ! isset( $allowed[ $tag ] ) ) {
				$allowed[ $tag ] = array();
			}
			$allowed[ $tag ] = array_merge( $allowed[ $tag ], array( 'style' => true, 'class' => true, 'id' => true, 'width' => true, 'height' => true, 'align' => true, 'valign' => true, 'bgcolor' => true, 'border' => true, 'cellpadding' => true, 'cellspacing' => true, 'color' => true, 'face' => true, 'size' => true, 'dir' => true ) );
		}
		$allowed['a']   = array_merge( $allowed['a'], array( 'href' => true, 'target' => true, 'rel' => true, 'title' => true ) );
		$allowed['img'] = array_merge( $allowed['img'], array( 'src' => true, 'alt' => true ) );
		$html = wp_kses( $html, $allowed, array( 'http', 'https', 'mailto', 'tel', 'data', 'cid' ) );
		// open links in a new tab
		return preg_replace( '/<a\s/i', '<a target="_blank" rel="noopener noreferrer" ', $html );
	}

	/**
	 * Build a simple RFC 2822 message (text + optional html) for sending or appending.
	 */
	public static function build_message( $from, $from_name, $to, $cc, $subject, $text, $html = '', $extra_headers = array() ) {
		$eol      = "\r\n";
		$boundary = 'ce-' . bin2hex( random_bytes( 12 ) );
		$h        = array();
		$h[]      = 'From: ' . self::format_address( $from, $from_name );
		$h[]      = 'To: ' . $to;
		if ( $cc ) {
			$h[] = 'Cc: ' . $cc;
		}
		$h[] = 'Subject: ' . self::encode_header( $subject );
		$h[] = 'Date: ' . gmdate( 'D, d M Y H:i:s O' );
		$h[] = 'Message-ID: <' . bin2hex( random_bytes( 10 ) ) . '@' . substr( strrchr( $from, '@' ), 1 ) . '>';
		foreach ( $extra_headers as $k => $v ) {
			if ( '' !== $v ) {
				$h[] = $k . ': ' . $v;
			}
		}
		$h[] = 'MIME-Version: 1.0';
		if ( $html ) {
			$h[]  = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';
			$body = '--' . $boundary . $eol . 'Content-Type: text/plain; charset=UTF-8' . $eol . 'Content-Transfer-Encoding: quoted-printable' . $eol . $eol . quoted_printable_encode( $text ) . $eol
				. '--' . $boundary . $eol . 'Content-Type: text/html; charset=UTF-8' . $eol . 'Content-Transfer-Encoding: quoted-printable' . $eol . $eol . quoted_printable_encode( $html ) . $eol . '--' . $boundary . '--' . $eol;
		} else {
			$h[]  = 'Content-Type: text/plain; charset=UTF-8';
			$h[]  = 'Content-Transfer-Encoding: quoted-printable';
			$body = quoted_printable_encode( $text ) . $eol;
		}
		return implode( $eol, $h ) . $eol . $eol . $body;
	}

	public static function format_address( $email, $name = '' ) {
		$name = trim( (string) $name );
		return $name ? self::encode_header( $name ) . ' <' . $email . '>' : $email;
	}

	public static function encode_header( $s ) {
		return preg_match( '/[^\x20-\x7E]/', $s ) ? '=?UTF-8?B?' . base64_encode( $s ) . '?=' : $s;
	}
}
