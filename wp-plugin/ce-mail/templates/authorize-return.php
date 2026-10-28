<?php defined( 'ABSPATH' ) || exit; ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="referrer" content="no-referrer">
<meta http-equiv="refresh" content="0;url=<?php echo esc_attr( esc_url( $return_url ) ); ?>">
<title><?php echo $cancelled ? 'Connection cancelled' : 'Authorization approved'; ?></title>
<link rel="stylesheet" href="<?php echo esc_url( CE_MAIL_URL . 'assets/app.css' ); ?>">
</head>
<body><main class="pane"><div class="card">
<h1><?php echo $cancelled ? 'Connection cancelled' : 'Authorization approved'; ?></h1>
<p>Returning you to your assistant. If this page stays open, use the link below.</p>
<p><a class="button primary" href="<?php echo esc_url( $return_url ); ?>" rel="noreferrer">Continue to assistant</a></p>
<p>You can close this window after your assistant confirms the result. Do not submit the consent form again.</p>
</div></main></body>
</html>
