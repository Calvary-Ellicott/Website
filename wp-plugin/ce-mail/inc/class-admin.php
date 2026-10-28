<?php
/**
 * Dashboard settings page: Mail → Settings. Server details, Resend, who can use the portal.
 */

defined( 'ABSPATH' ) || exit;

class CE_Mail_Admin {

	public static function menu() {
		add_menu_page( 'Mail portal', 'Mail', 'manage_options', 'ce-mail', array( __CLASS__, 'page' ), 'dashicons-email-alt', 59 );
	}

	public static function register_settings() {
		register_setting( 'ce_mail', CE_Mail_Settings::OPTION, array( 'sanitize_callback' => array( __CLASS__, 'sanitize' ) ) );
	}

	public static function sanitize( $in ) {
		$in  = is_array( $in ) ? $in : array();
		$cur = CE_Mail_Settings::all();
		$out = array(
			'imap_host'       => sanitize_text_field( isset( $in['imap_host'] ) ? $in['imap_host'] : $cur['imap_host'] ),
			'imap_port'       => (int) ( isset( $in['imap_port'] ) ? $in['imap_port'] : 993 ),
			'smtp_host'       => sanitize_text_field( isset( $in['smtp_host'] ) ? $in['smtp_host'] : $cur['smtp_host'] ),
			'smtp_port'       => (int) ( isset( $in['smtp_port'] ) ? $in['smtp_port'] : 465 ),
			'smtp_secure'     => ( isset( $in['smtp_secure'] ) && 'tls' === $in['smtp_secure'] ) ? 'tls' : 'ssl',
			'webmail_url'     => esc_url_raw( isset( $in['webmail_url'] ) ? $in['webmail_url'] : '' ),
			'resend_key'      => isset( $in['resend_key'] ) && '' !== trim( $in['resend_key'] ) ? trim( $in['resend_key'] ) : ( ! empty( $in['resend_key_clear'] ) ? '' : $cur['resend_key'] ),
			'resend_audience' => sanitize_text_field( isset( $in['resend_audience'] ) ? $in['resend_audience'] : '' ),
			'marketing_from'  => sanitize_email( isset( $in['marketing_from'] ) ? $in['marketing_from'] : '' ),
			'marketing_name'  => sanitize_text_field( isset( $in['marketing_name'] ) ? $in['marketing_name'] : '' ),
			'marketing_reply' => sanitize_email( isset( $in['marketing_reply'] ) ? $in['marketing_reply'] : '' ),
			'roles'           => array_values( array_filter( array_map( 'sanitize_key', isset( $in['roles'] ) ? (array) $in['roles'] : array() ) ) ),
			'demo'            => empty( $in['demo'] ) ? 0 : 1,
			'marketing_enabled' => empty( $in['marketing_enabled'] ) ? 0 : 1,
			'ai_enabled'        => empty( $in['ai_enabled'] ) ? 0 : 1,
			'ai_send'           => empty( $in['ai_send'] ) ? 0 : 1,
			'page_size'       => max( 10, min( 100, (int) ( isset( $in['page_size'] ) ? $in['page_size'] : 25 ) ) ),
			'org_name'        => sanitize_text_field( isset( $in['org_name'] ) ? $in['org_name'] : $cur['org_name'] ),
			'org_address'     => sanitize_text_field( isset( $in['org_address'] ) ? $in['org_address'] : $cur['org_address'] ),
			'purelymail_token' => isset( $in['purelymail_token'] ) && '' !== trim( $in['purelymail_token'] ) ? CE_Mail_Crypto::encrypt( trim( $in['purelymail_token'] ) ) : ( ! empty( $in['purelymail_token_clear'] ) ? '' : $cur['purelymail_token'] ),
			'mail_domain'     => strtolower( sanitize_text_field( isset( $in['mail_domain'] ) ? $in['mail_domain'] : $cur['mail_domain'] ) ),
		);
		// roles take effect immediately
		add_action( 'updated_option', function ( $option ) {
			if ( CE_Mail_Settings::OPTION === $option ) {
				ce_mail_apply_roles();
			}
		} );
		return $out;
	}

	private static function field( $key, $label, $value, $type = 'text', $help = '' ) {
		printf( '<tr><th scope="row"><label for="%1$s">%2$s</label></th><td><input type="%3$s" id="%1$s" name="%4$s[%1$s]" value="%5$s" class="regular-text" />', esc_attr( $key ), esc_html( $label ), esc_attr( $type ), CE_Mail_Settings::OPTION, esc_attr( $value ) );
		if ( $help ) {
			echo '<p class="description">' . wp_kses_post( $help ) . '</p>';
		}
		echo '</td></tr>';
	}

	public static function page() {
		$s      = CE_Mail_Settings::all();
		$opt    = CE_Mail_Settings::OPTION;
		$portal = home_url( '/mail/' );
		echo '<div class="wrap"><h1>Mail portal</h1>';
		echo '<p>The staff mail portal lives at <a href="' . esc_url( $portal ) . '" target="_blank">' . esc_html( $portal ) . '</a>. Until a person connects their mailbox there, they see a preview with sample messages.</p>';

		// status
		$ready = CE_Mail_Settings::marketing_ready();
		echo '<div class="notice ' . ( $ready ? 'notice-success' : 'notice-info' ) . ' inline"><p>';
		if ( empty( $s['marketing_enabled'] ) ) {
			echo 'The portal currently shows inboxes only. Marketing email (Resend) is switched off and hidden; mass email goes out from ChurchTrac.';
		} else {
			echo $ready ? 'Marketing email is set up: campaigns send through Resend from <strong>' . esc_html( $s['marketing_from'] ) . '</strong>.' : 'Marketing email is shown but not set up yet. Add a Resend API key, an audience ID, and the sending address below.';
		}
		echo '</p></div>';

		echo '<form method="post" action="options.php">';
		settings_fields( 'ce_mail' );
		echo '<h2>AI assistants</h2><p>Connecting a mailbox shares member names, message content, and pastoral matters with the assistant vendor. Obtain the pastor\'s approval before staff connect.</p><table class="form-table" role="presentation">';
		printf( '<tr><th>Allow AI assistants</th><td><label><input type="checkbox" name="%s[ai_enabled]" value="1" %s> Allow staff to authorize their own church mailbox</label></td></tr>', $opt, checked( ! empty( $s['ai_enabled'] ), true, false ) );
		printf( '<tr><th>Allow assistants to send</th><td><label><input type="checkbox" name="%s[ai_send]" value="1" %s> Offer sending permission on the consent screen (each person must also opt in)</label></td></tr>', $opt, checked( ! empty( $s['ai_send'] ), true, false ) );
		echo '</table><p>Connector URL: <code>' . esc_html( CE_Mail_OAuth::resource() ) . '</code>. Turning assistants off suspends access; existing grants remain until revoked or expired.</p>';

		echo '<h2>Mailboxes (Purelymail)</h2><p class="description">Each staff member connects their own mailbox from the portal\'s Settings tab with their Purelymail email and password. These are the server details the portal uses for everyone.</p><table class="form-table" role="presentation">';
		self::field( 'imap_host', 'IMAP server', $s['imap_host'] );
		self::field( 'imap_port', 'IMAP port', $s['imap_port'], 'number' );
		self::field( 'smtp_host', 'SMTP server', $s['smtp_host'] );
		self::field( 'smtp_port', 'SMTP port', $s['smtp_port'], 'number', 'Use 465 with SSL (default) or 587 with STARTTLS.' );
		printf( '<tr><th scope="row">SMTP encryption</th><td><label><input type="radio" name="%1$s[smtp_secure]" value="ssl" %2$s /> SSL (port 465)</label> &nbsp; <label><input type="radio" name="%1$s[smtp_secure]" value="tls" %3$s /> STARTTLS (port 587)</label></td></tr>', $opt, checked( $s['smtp_secure'], 'ssl', false ), checked( $s['smtp_secure'], 'tls', false ) );
		self::field( 'webmail_url', 'Provider webmail link', $s['webmail_url'], 'url', 'Shown in the portal as a fallback ("Open in Purelymail").' );
		echo '</table>';

		$emailadmin = home_url( '/emailadmin/' );
		echo '<h2>Email administration (Purelymail account API)</h2><p class="description">Lets administrators manage mailboxes and <strong>mail groups</strong> at <a href="' . esc_url( $emailadmin ) . '" target="_blank">' . esc_html( $emailadmin ) . '</a>. A group with an address (for example <code>info</code>) becomes a routing rule in Purelymail, so mail to that address reaches every member. The token can create mailboxes and routing rules, so only administrators ever see this page.</p><table class="form-table" role="presentation">';
		printf( '<tr><th scope="row"><label for="purelymail_token">Purelymail API token</label></th><td><input type="password" id="purelymail_token" name="%1$s[purelymail_token]" value="" class="regular-text" placeholder="%2$s" autocomplete="new-password" /><p class="description">%3$s</p></td></tr>', $opt, $s['purelymail_token'] ? 'Saved. Enter a new token to replace it.' : 'Paste the token', $s['purelymail_token'] ? 'A token is saved (encrypted). Leave blank to keep it, or <label><input type="checkbox" name="' . $opt . '[purelymail_token_clear]" value="1" /> clear it</label>.' : 'In Purelymail: Account → API → create or refresh the API key, then paste it here. It is stored encrypted and never shown again.' );
		self::field( 'mail_domain', 'Mail domain', $s['mail_domain'], 'text', 'The custom domain whose mailboxes and routing rules the admin page manages.' );
		echo '</table>';

		echo '<h2>Marketing email (Resend)</h2><p class="description">Off by default: the church sends newsletters and mass email from ChurchTrac. Everything below stays saved in the background and can be switched on later.</p><table class="form-table" role="presentation">';
		printf( '<tr><th scope="row">Show in the portal</th><td><label><input type="checkbox" name="%s[marketing_enabled]" value="1" %s /> Show the Campaigns, Contacts, and Import sections to portal users</label></td></tr>', $opt, checked( ! empty( $s['marketing_enabled'] ), true, false ) );
		printf( '<tr><th scope="row"><label for="resend_key">Resend API key</label></th><td><input type="password" id="resend_key" name="%1$s[resend_key]" value="" class="regular-text" placeholder="%2$s" autocomplete="new-password" /><p class="description">%3$s</p></td></tr>', $opt, $s['resend_key'] ? 'Saved. Enter a new key to replace it.' : 're_…', $s['resend_key'] ? 'A key is saved. Leave blank to keep it, or <label><input type="checkbox" name="' . $opt . '[resend_key_clear]" value="1" /> clear it</label>.' : 'From resend.com → API Keys. The key needs sending access and the domain <code>calvaryellicott.org</code> verified in Resend.' );
		self::field( 'resend_audience', 'Audience ID', $s['resend_audience'], 'text', 'From resend.com → Audiences. Create one audience (for example "Church family") and paste its ID. Contacts imported in the portal are synced to it.' );
		self::field( 'marketing_from', 'Send from', $s['marketing_from'], 'email', 'The address campaigns come from. Must be on a domain verified in Resend.' );
		self::field( 'marketing_name', 'Sender name', $s['marketing_name'] );
		self::field( 'marketing_reply', 'Replies go to (optional)', $s['marketing_reply'], 'email', 'A mailbox someone reads, since the sending address is for broadcasts.' );
		self::field( 'org_name', 'Organisation name in the footer', $s['org_name'] );
		self::field( 'org_address', 'Mailing address in the footer', $s['org_address'], 'text', 'Bulk email law requires a physical address in every campaign.' );
		echo '</table>';

		echo '<h2>Who can use the portal</h2><table class="form-table" role="presentation"><tr><th scope="row">Roles</th><td>';
		foreach ( wp_roles()->roles as $slug => $role ) {
			if ( 'administrator' === $slug ) {
				continue;
			}
			printf( '<label style="display:block;margin:4px 0"><input type="checkbox" name="%s[roles][]" value="%s" %s /> %s</label>', $opt, esc_attr( $slug ), checked( in_array( $slug, (array) $s['roles'], true ), true, false ), esc_html( $role['name'] ) );
		}
		echo '<p class="description">Administrators always have access. People in these roles see a <strong>Mail</strong> link in the toolbar and can open ' . esc_html( $portal ) . '.</p></td></tr>';
		self::field( 'page_size', 'Messages per page', $s['page_size'], 'number' );
		echo '</table>';

		submit_button( 'Save settings' );
		echo '</form></div>';
	}
}
