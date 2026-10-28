<?php defined( 'ABSPATH' ) || exit; ?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Connect your church mailbox</title><link rel="stylesheet" href="<?php echo esc_url( CE_MAIL_URL . 'assets/app.css' ); ?>"></head>
<body><main class="pane"><div class="card">
<h1>Connect your church mailbox</h1>
<?php if ( $error ) : ?>
<p role="alert"><?php echo esc_html( $error ); ?></p>
<?php if ( $retry ) : ?>
<p><a class="button primary" href="<?php echo esc_url( add_query_arg( array( 'flow' => $flow ), home_url( '/mail/authorize' ) ) ); ?>">Try signing in again</a></p>
<?php else : ?><p>Close this window and start again from your assistant.</p><?php endif; ?>
<?php else : ?>
<p><strong><?php echo esc_html( $d['client_name'] ); ?></strong> wants access to your mailbox.</p>
<p class="help">This name was supplied by the application. Continue only if you started this connection. Return address: <strong><?php echo esc_html( wp_parse_url( $d['redirect_uri'], PHP_URL_HOST ) ); ?></strong>.</p>
<form method="post" action="<?php echo esc_url( home_url( '/mail/authorize' ) ); ?>">
<input type="hidden" name="flow" value="<?php echo esc_attr( $flow ); ?>">
<?php if ( empty( $d['secret'] ) ) : ?>
<label class="field">Church mailbox<input type="email" name="email" required autocomplete="username"></label>
<label class="field">Mailbox password<input type="password" name="password" required autocomplete="current-password"></label>
<button class="button primary" name="action" value="signin">Sign in</button>
<?php else : ?>
<p>Mailbox: <strong><?php echo esc_html( $d['email'] ); ?></strong></p>
<ul>
<?php foreach ( array( 'mail:read' => 'Read folders, messages, and threads.', 'mail:draft' => 'Create drafts for you to review.', 'mail:organize' => 'Mark messages read, flag them, and move them to Archive.' ) as $scope => $label ) : ?>
<?php if ( in_array( $scope, explode( ' ', $d['scope'] ), true ) ) : ?><li><?php echo esc_html( $label ); ?></li><?php endif; ?>
<?php endforeach; ?>
</ul>
<?php if ( CE_Mail_Settings::get( 'ai_send' ) && in_array( 'mail:send', explode( ' ', $d['scope'] ), true ) ) : ?>
<label><input type="checkbox" name="allow_send" value="1"> Also allow sending mail when I explicitly ask.</label>
<?php else : ?><p>It cannot send mail.</p><?php endif; ?>
<p>It cannot delete mail. You can revoke access in Mail → Settings → Connected assistants.</p>
<p>Connecting shares message content, including member names and pastoral matters, with your assistant vendor. Obtain the pastor's approval before connecting.</p>
<button class="button primary" name="action" value="allow">Allow</button>
<?php endif; ?>
<button class="button ghost" name="action" value="cancel" formnovalidate>Cancel</button>
</form>
<?php endif; ?>
</div></main></body></html>
