<?php
/**
 * App shell for /mail. The JavaScript in assets/app.js fills in every view.
 *
 * @var array $config
 */
defined( 'ABSPATH' ) || exit;
$ver = CE_MAIL_VERSION;
?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<meta name="robots" content="noindex, nofollow" />
	<title>Mail · <?php echo esc_html( $config['site']['name'] ); ?></title>
	<link rel="preconnect" href="https://fonts.googleapis.com" />
	<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet" />
	<link rel="stylesheet" href="<?php echo esc_url( CE_MAIL_URL . 'assets/app.css?v=' . $ver ); ?>" />
</head>
<body>
<div id="app" class="app" data-view="inbox">
	<aside class="side">
		<div class="brand">
			<span class="brand-mark">CE</span>
			<div><strong><?php echo esc_html( $config['site']['name'] ); ?></strong><small>Mail</small></div>
		</div>
		<button class="button primary compose-btn" type="button" data-action="compose">✎ &nbsp;New message</button>
		<nav class="nav">
			<p class="nav-label">Mail</p>
			<ul id="folder-list"></ul>
			<div id="marketing-nav" hidden>
			<p class="nav-label">Marketing</p>
			<ul>
				<li><a href="#campaigns" data-view="campaigns">Campaigns</a></li>
				<li><a href="#contacts" data-view="contacts">Contacts</a></li>
				<li><a href="#import" data-view="import">Import from ChurchTrac</a></li>
			</ul>
			</div>
			<p class="nav-label">Account</p>
			<ul>
				<li><a href="#settings" data-view="settings">Settings</a></li>
				<li><a href="<?php echo esc_url( $config['site']['url'] ); ?>">← Back to website</a></li>
			</ul>
		</nav>
		<div class="side-foot">
			<span id="side-user"><?php echo esc_html( $config['user']['name'] ); ?></span>
			<a href="<?php echo esc_url( $config['site']['logout'] ); ?>">Sign out</a>
		</div>
	</aside>

	<button class="menu-btn" type="button" aria-label="Menu" data-action="menu">☰</button>

	<main class="main">
		<div id="banner" class="banner" hidden></div>
		<div id="toast" class="toast" hidden></div>
		<section id="view" class="view"></section>
	</main>

	<div id="modal" class="modal" hidden><div class="modal-card" role="dialog" aria-modal="true"></div></div>
</div>
<script>window.CE_MAIL = <?php echo wp_json_encode( $config ); ?>;</script>
<script src="<?php echo esc_url( CE_MAIL_URL . 'assets/app.js?v=' . $ver ); ?>"></script>
</body>
</html>
