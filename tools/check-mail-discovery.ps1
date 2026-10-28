# Run after uploading the discovery-only plugin. No mailbox credentials needed.
param([uri]$BaseUrl = 'https://calvaryellicott.org', [string]$ExpectedVersion = '0.4.2')
$ErrorActionPreference = 'Stop'
if ($BaseUrl.Scheme -ne 'https' -or $BaseUrl.AbsolutePath -ne '/' -or $BaseUrl.Query -or $BaseUrl.Fragment -or $BaseUrl.UserInfo) {
    throw 'BaseUrl must be an HTTPS origin, without credentials, path, query, or fragment.'
}
$origin = $BaseUrl.GetLeftPart([System.UriPartial]::Authority)
$documents = @{}
foreach ($name in @('oauth-authorization-server', 'oauth-protected-resource')) {
    $url = "$origin/.well-known/$name"
    $response = Invoke-WebRequest -Uri $url -UseBasicParsing -MaximumRedirection 0
    if ($response.StatusCode -ne 200 -or $response.Headers['Content-Type'] -notmatch '^application/json') {
        throw "Discovery failed at $url : expected HTTP 200 and application/json."
    }
    $data = $response.Content | ConvertFrom-Json
    if ($data.ce_mail_stage -notin @('discovery-only', 'connector') -or $data.ce_mail_version -ne $ExpectedVersion) {
        throw "Unexpected plugin response at $url; verify the uploaded version and host caching."
    }
    $documents[$name] = $data
    Write-Host "PASS $url (plugin $($data.ce_mail_version))"
}
$server = $documents['oauth-authorization-server']
$resource = $documents['oauth-protected-resource']
if ($server.issuer -cne $origin -or $resource.resource -cne "$origin/wp-json/ce-mail/v1/mcp" -or
    @($resource.authorization_servers).Count -ne 1 -or $resource.authorization_servers[0] -cne $origin) {
    throw 'Discovery issuer/resource URLs do not match the site origin.'
}
foreach ($field in @('authorization_endpoint', 'token_endpoint', 'registration_endpoint', 'revocation_endpoint')) {
    $endpoint = [uri]$server.$field
    if (-not $endpoint.IsAbsoluteUri -or $endpoint.Scheme -ne 'https' -or $endpoint.Authority -ne $BaseUrl.Authority) {
        throw "Invalid $field in authorization server metadata."
    }
}
Write-Host 'Discovery passed. Test OAuth and mailbox operations separately; discovery alone does not verify them.'
