<?php
/**
 * Plugin Name: Calvary Ellicott Mail
 * Description: A simple staff mail portal at /mail: Purelymail inboxes, marketing email through Resend, and a ChurchTrac contact import. Administrators get /emailadmin: mailboxes and mail groups kept in sync with Purelymail's routing rules. Can be installed without being used; signed-in staff see a preview with sample data until a mailbox is connected.
 * Version: 0.2.3
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Author: Calvary Ellicott
 * Text Domain: ce-mail
 */

defined( 'ABSPATH' ) || exit;

define( 'CE_MAIL_VERSION', '0.2.3' );
define( 'CE_MAIL_FILE', __FILE__ );
define( 'CE_MAIL_DIR', plugin_dir_path( __FILE__ ) );
define( 'CE_MAIL_URL', plugin_dir_url( __FILE__ ) );
define( 'CE_MAIL_CAP', 'ce_mail_access' );

require CE_MAIL_DIR . 'inc/class-settings.php';
require CE_MAIL_DIR . 'inc/class-crypto.php';
require CE_MAIL_DIR . 'inc/class-imap.php';
require CE_MAIL_DIR . 'inc/class-mime.php';
require CE_MAIL_DIR . 'inc/class-mailbox.php';
require CE_MAIL_DIR . 'inc/class-resend.php';
require CE_MAIL_DIR . 'inc/class-purelymail.php';
require CE_MAIL_DIR . 'inc/class-groups.php';
require CE_MAIL_DIR . 'inc/class-store.php';
require CE_MAIL_DIR . 'inc/class-rest.php';
require CE_MAIL_DIR . 'inc/class-admin.php';
require CE_MAIL_DIR . 'inc/class-frontend.php';

/**
 * Activation: tables, capability, rewrite rule.
 */
function ce_mail_activate() {
	CE_Mail_Store::install();
	ce_mail_apply_roles();
	CE_Mail_Frontend::add_rewrite();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'ce_mail_activate' );

function ce_mail_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'ce_mail_deactivate' );

/**
 * Grant the portal capability to the roles chosen in settings (administrator always).
 */
function ce_mail_apply_roles() {
	$allowed = CE_Mail_Settings::get( 'roles' );
	foreach ( wp_roles()->role_objects as $name => $role ) {
		if ( 'administrator' === $name || in_array( $name, (array) $allowed, true ) ) {
			$role->add_cap( CE_MAIL_CAP );
		} else {
			$role->remove_cap( CE_MAIL_CAP );
		}
	}
}

add_action( 'init', array( 'CE_Mail_Frontend', 'add_rewrite' ) );
add_action( 'init', array( 'CE_Mail_Store', 'maybe_upgrade' ) );

/**
 * "Replace current with uploaded" does not re-run activation, so after an update
 * flush the rewrite rules once (new routes such as /emailadmin would otherwise 404
 * until Settings → Permalinks is saved).
 */
function ce_mail_maybe_flush() {
	if ( get_option( 'ce_mail_version' ) !== CE_MAIL_VERSION ) {
		flush_rewrite_rules();
		update_option( 'ce_mail_version', CE_MAIL_VERSION, false );
	}
}
add_action( 'init', 'ce_mail_maybe_flush', 20 );
add_action( 'rest_api_init', array( 'CE_Mail_REST', 'register' ) );
add_action( 'admin_menu', array( 'CE_Mail_Admin', 'menu' ) );
add_action( 'admin_init', array( 'CE_Mail_Admin', 'register_settings' ) );
add_action( 'template_redirect', array( 'CE_Mail_Frontend', 'maybe_render' ) );
add_filter( 'query_vars', array( 'CE_Mail_Frontend', 'query_vars' ) );
add_action( 'parse_request', array( 'CE_Mail_Frontend', 'parse_request' ) );
add_action( 'admin_bar_menu', array( 'CE_Mail_Frontend', 'admin_bar' ), 90 );
