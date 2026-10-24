<?php
/**
 * App shell for /emailadmin (administrators only). assets/admin.js fills the views.
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
	<title>Email admin · <?php echo esc_html( $config['site']['name'] ); ?></title>
	<link rel="preconnect" href="https://fonts.googleapis.com" />
	<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet" />
	<link rel="stylesheet" href="<?php echo esc_url( CE_MAIL_URL . 'assets/app.css?v=' . $ver ); ?>" />
</head>
<body>
<div id="app" class="app" data-view="groups">
	<aside class="side">
		<div class="brand">
			<span class="brand-mark">CE</span>
			<div><strong><?php echo esc_html( $config['site']['name'] ); ?></strong><small>Email admin</small></div>
		</div>
		<nav class="nav">
			<p class="nav-label">Purelymail</p>
			<ul>
				<li><a href="#groups" data-view="groups">Groups</a></li>
				<li><a href="#mailboxes" data-view="mailboxes">Mailboxes</a></li>
				<li><a href="#rules" data-view="rules">Routing rules</a></li>
			</ul>
			<p class="nav-label">Elsewhere</p>
			<ul>
				<li><a href="<?php echo esc_url( $config['site']['mail'] ); ?>">Mail portal</a></li>
				<li><a href="<?php echo esc_url( $config['site']['settings'] ); ?>">Plugin settings</a></li>
				<li><a href="<?php echo esc_url( $config['site']['url'] ); ?>">← Back to website</a></li>
			</ul>
		</nav>
		<div class="side-foot">
			<span><?php echo esc_html( $config['user']['name'] ); ?></span>
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
<script src="<?php echo esc_url( CE_MAIL_URL . 'assets/admin.js?v=' . $ver ); ?>"></script>
</body>
</html>
