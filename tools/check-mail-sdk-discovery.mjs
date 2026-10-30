// Install the SDK in wp-plugin/dist/mcp-diagnostics, then run this file.
// Public metadata only: never reads mailbox data or creates grants.
import { discoverOAuthServerInfo } from '../wp-plugin/dist/mcp-diagnostics/node_modules/@modelcontextprotocol/sdk/dist/esm/client/auth.js';
const server = new URL('https://calvaryellicott.org/wp-json/ce-mail/v1/mcp');
const tracedFetch = async (url, init) => {
  const response = await fetch(url, {...init, signal: AbortSignal.timeout(20000)});
  console.log(`${init?.method || 'GET'} ${url} -> ${response.status} ${response.headers.get('content-type')}`);
  return response;
};
try {
  const info = await discoverOAuthServerInfo(server, {fetchFn:tracedFetch});
  if (!info.authorizationServerMetadata) throw new Error('No authorization server metadata');
  console.log(JSON.stringify({
    issuer: info.authorizationServerMetadata.issuer,
    resource: info.resourceMetadata?.resource,
    registration: info.authorizationServerMetadata.registration_endpoint,
    tokenAuth: info.authorizationServerMetadata.token_endpoint_auth_methods_supported,
    pkce: info.authorizationServerMetadata.code_challenge_methods_supported,
    sendingAdvertised: info.authorizationServerMetadata.scopes_supported?.includes('mail:send'),
    result:'PASS official SDK discovery'
  },null,2));
} catch (error) {
  console.error(error.message);
  process.exitCode=1;
}
