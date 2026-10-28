<?php
/** Render the production handoff template with synthetic callback data only. */
define( 'ABSPATH', __DIR__ );
define( 'CE_MAIL_URL', '/' );
function esc_url( $value ) { return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8', false ); }
function esc_attr( $value ) { return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8', false ); }
$return_url = $argv[1];
$cancelled = false;
include __DIR__ . '/../wp-plugin/ce-mail/templates/authorize-return.php';
