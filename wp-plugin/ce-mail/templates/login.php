<?php
/**
 * Sign-in page for /mail when nobody is signed in: church mailbox email + password.
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
	<title>Sign in · Mail · <?php echo esc_html( $config['site']['name'] ); ?></title>
	<link rel="preconnect" href="https://fonts.googleapis.com" />
	<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet" />
	<link rel="stylesheet" href="<?php echo esc_url( CE_MAIL_URL . 'assets/app.css?v=' . $ver ); ?>" />
</head>
<body class="login-page">
<main class="login">
	<div class="login-card">
		<div class="brand">
			<span class="brand-mark">CE</span>
			<div><strong><?php echo esc_html( $config['site']['name'] ); ?></strong><small>Staff mail</small></div>
		</div>
		<h1>Sign in to your church mailbox</h1>
		<p class="help">Use your <strong>@<?php echo esc_html( $config['domain'] ); ?></strong> email address and its password. Your password is checked against the mail server and kept only in this browser while you are signed in; nothing is stored on the website.</p>
		<form id="login-form" autocomplete="on">
			<label class="field"><span>Email address</span><input type="email" name="email" required autocomplete="username" placeholder="you@<?php echo esc_attr( $config['domain'] ); ?>" /></label>
			<label class="field"><span>Password</span><input type="password" name="password" required autocomplete="current-password" /></label>
			<label class="check"><input type="checkbox" name="remember" value="1" checked /> Keep me signed in on this device</label>
			<div id="login-error" class="login-error" hidden></div>
			<button type="submit" class="button primary" style="width:100%">Sign in</button>
		</form>
		<p class="help login-alt">Have a website login? <a href="<?php echo esc_url( $config['site']['wp_login'] ); ?>">Sign in with WordPress</a> · <a href="<?php echo esc_url( $config['webmail'] ); ?>" target="_blank" rel="noopener">Open Purelymail webmail</a> · <a href="<?php echo esc_url( $config['site']['url'] ); ?>">Back to website</a></p>
	</div>
</main>
<script>
(function () {
	var rest = <?php echo wp_json_encode( $config['rest'] ); ?>;
	var form = document.getElementById("login-form"), err = document.getElementById("login-error");
	form.onsubmit = async function (e) {
		e.preventDefault();
		var btn = form.querySelector("button[type=submit]");
		btn.disabled = true; btn.textContent = "Checking…"; err.hidden = true;
		var f = new FormData(form);
		try {
			var res = await fetch(rest + "session", { method: "POST", credentials: "same-origin", headers: { "Content-Type": "application/json" }, body: JSON.stringify({ email: f.get("email"), password: f.get("password"), remember: f.get("remember") ? 1 : 0 }) });
			var data = null; try { data = await res.json(); } catch (x) { data = null; }
			if (!res.ok) throw new Error(data && data.message ? data.message : "Sign-in failed (" + res.status + ").");
			location.replace(<?php echo wp_json_encode( home_url( '/mail/' ) ); ?>);
		} catch (ex) {
			err.textContent = ex.message; err.hidden = false;
			btn.disabled = false; btn.textContent = "Sign in";
		}
	};
})();
</script>
</body>
</html>
