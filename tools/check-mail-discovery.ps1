# Run after uploading the discovery-only plugin. No mailbox credentials needed.
param([uri]$BaseUrl = 'https://calvaryellicott.org', [string]$ExpectedVersion = '0.4.4')
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
    if ($response.Headers['Access-Control-Allow-Origin'] -ne '*') { throw "Public metadata is missing cross-origin access at $url" }
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
$specificUrl = "$origin/.well-known/oauth-protected-resource/wp-json/ce-mail/v1/mcp"
$specific = Invoke-RestMethod $specificUrl
if ($specific.resource -cne $resource.resource -or $specific.ce_mail_version -ne $ExpectedVersion) { throw 'Resource-specific discovery mismatch.' }
Write-Host "PASS $specificUrl"
foreach ($clientOrigin in @('', 'https://chatgpt.com')) {
    foreach ($method in @('GET', 'POST')) {
        $request = @{Uri="$origin/wp-json/ce-mail/v1/mcp"; Method=$method; UseBasicParsing=$true}
        if ($clientOrigin) { $request.Headers = @{Origin=$clientOrigin} }
        if ($method -eq 'POST') {
            $request.ContentType = 'application/json'
            $request.Body = '{"jsonrpc":"2.0","id":1,"method":"ping"}'
        }
        $label = if ($clientOrigin) {"ChatGPT-origin $method"} else {"Server-to-server $method without Origin"}
        try {
            Invoke-WebRequest @request | Out-Null
            throw "Unauthenticated $label unexpectedly succeeded."
        } catch {
            $errorResponse = $_.Exception.Response
            if (-not $errorResponse -or [int]$errorResponse.StatusCode -ne 401) { throw }
            $challenge = $errorResponse.Headers['WWW-Authenticate']
            if (-not $challenge) { $challenge = $errorResponse.Headers.WwwAuthenticate.ToString() }
            if ($challenge -notmatch 'resource_metadata=') { throw "$label is missing its discovery challenge." }
            Write-Host "PASS $label receives HTTP 401 and OAuth discovery challenge"
        }
    }
}
