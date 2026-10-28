// Manual browser regression fixture. No real OAuth clients, tokens, or mailboxes.
// Usage: node tests/oauth-return-browser.cjs <path-to-php>
const http = require('node:http');
const path = require('node:path');
const {spawnSync} = require('node:child_process');
const php = process.argv[2];
if (!php) throw new Error('Pass a PHP executable path');
const policy = "default-src 'none'; style-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'";
const callback = http.createServer((req, res) => {
  res.writeHead(200, {'Content-Type':'text/html', 'Cache-Control':'no-store'});
  res.end('<h1>Callback received</h1><p>Synthetic browser test passed. No real authorization or mailbox access occurred.</p>');
  console.log('CALLBACK_RECEIVED');
});
callback.listen(0, '127.0.0.1', () => {
  const returnUrl = `http://127.0.0.1:${callback.address().port}/callback?code=synthetic&state=fixture`;
  const rendered = spawnSync(php, [path.join(__dirname, 'oauth-return-render.php'), returnUrl], {encoding:'utf8'});
  if (rendered.status !== 0) throw new Error(rendered.stderr);
  const server = http.createServer((req, res) => {
    const headers = {'Content-Type':'text/html', 'Cache-Control':'no-store', 'Referrer-Policy':'no-referrer', 'Content-Security-Policy':policy};
    if (req.method === 'POST' && req.url === '/old') {
      res.writeHead(302, {...headers, Location:returnUrl}); res.end();
    } else if (req.method === 'POST' && req.url === '/fixed') {
      res.writeHead(200, headers); res.end(rendered.stdout);
    } else if (req.url === '/old' || req.url === '/fixed') {
      res.writeHead(200, headers);
      res.end(`<h1>Synthetic consent ${req.url === '/old' ? 'old redirect' : 'fixed handoff'}</h1><p>No account access is granted.</p><form method="post" action="${req.url}"><button>Simulate Allow</button></form>`);
    } else { res.writeHead(404); res.end(); }
  });
  server.listen(0, '127.0.0.1', () => {
    console.log(`OLD_URL=http://127.0.0.1:${server.address().port}/old`);
    console.log(`FIXED_URL=http://127.0.0.1:${server.address().port}/fixed`);
  });
  setTimeout(() => {server.close(); callback.close();}, 10 * 60 * 1000).unref();
});
