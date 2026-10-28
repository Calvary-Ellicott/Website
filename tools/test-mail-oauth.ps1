# Interactive local OAuth acceptance test. Tokens and verifier remain in process memory.
# No mailbox tools are called; the grant is revoked when testing completes.
param([uri]$BaseUrl = 'https://calvaryellicott.org')
$ErrorActionPreference = 'Stop'
if ($BaseUrl.Scheme -ne 'https' -or $BaseUrl.AbsolutePath -ne '/' -or $BaseUrl.UserInfo -or $BaseUrl.Query -or $BaseUrl.Fragment) { throw 'Use an HTTPS origin.' }
$origin = $BaseUrl.GetLeftPart([System.UriPartial]::Authority)
$resource = "$origin/wp-json/ce-mail/v1/mcp"
function New-RandomValue {
    $bytes = [byte[]]::new(48)
    [System.Security.Cryptography.RandomNumberGenerator]::Fill($bytes)
    [Convert]::ToBase64String($bytes).TrimEnd('=').Replace('+','-').Replace('/','_')
}
function Assert-Test($condition, $label) {
    if (-not $condition) { throw "FAIL: $label" }
    Write-Output "PASS: $label"
}
function Invoke-Rpc($token, $method, $params, $id) {
    $body = @{jsonrpc='2.0'; id=$id; method=$method; params=$params} | ConvertTo-Json -Depth 8 -Compress
    Invoke-RestMethod -Uri $resource -Method POST -ContentType 'application/json' -Headers @{Authorization="Bearer $token"; 'MCP-Protocol-Version'='2025-06-18'; Accept='application/json, text/event-stream'} -Body $body
}
$listener = [System.Net.Sockets.TcpListener]::new([System.Net.IPAddress]::Loopback, 0)
$refreshToken = $null
$accessToken = $null
$clientId = $null
try {
    $listener.Start()
    $port = $listener.LocalEndpoint.Port
    $state = New-RandomValue
    $verifier = New-RandomValue
    $digest = [System.Security.Cryptography.SHA256]::HashData([System.Text.Encoding]::ASCII.GetBytes($verifier))
    $challenge = [Convert]::ToBase64String($digest).TrimEnd('=').Replace('+','-').Replace('/','_')
    $callbackPath = '/callback/' + (New-RandomValue)
    $redirect = "http://127.0.0.1:$port$callbackPath"
    $registration = @{client_name='CE Mail sign-in test (temporary)'; redirect_uris=@($redirect); token_endpoint_auth_method='none'; grant_types=@('authorization_code','refresh_token'); response_types=@('code')} | ConvertTo-Json -Compress
    $client = Invoke-RestMethod -Uri "$origin/wp-json/ce-mail/v1/oauth/register" -Method POST -ContentType 'application/json' -Body $registration
    $clientId = $client.client_id
    Assert-Test ($clientId -match '^[a-f0-9]{32}$') 'Dynamic client registration'
    $parameters = [ordered]@{client_id=$clientId; redirect_uri=$redirect; response_type='code'; code_challenge=$challenge; code_challenge_method='S256'; state=$state; scope='mail:read mail:draft mail:organize'; resource=$resource}
    $query = ($parameters.GetEnumerator() | ForEach-Object { [uri]::EscapeDataString($_.Key) + '=' + [uri]::EscapeDataString($_.Value) }) -join '&'
    Write-Output "AUTHORIZE_URL=$origin/mail/authorize?$query"
    Write-Output 'Waiting up to ten minutes for browser sign-in and consent. No mailbox messages will be accessed.'
    $deadline = [datetime]::UtcNow.AddMinutes(10)
    $code = $null
    while ([datetime]::UtcNow -lt $deadline -and -not $code) {
        if (-not $listener.Pending()) { Start-Sleep -Milliseconds 200; continue }
        $connection = $listener.AcceptTcpClient()
        try {
            $connection.ReceiveTimeout = 5000
            $stream = $connection.GetStream()
            $reader = [System.IO.StreamReader]::new($stream, [System.Text.Encoding]::ASCII, $false, 1024, $true)
            $requestLine = $reader.ReadLine()
            $accepted = $false
            $cancelled = $false
            if ($requestLine -match '^GET (/[^ ]{1,12000}) HTTP/1\.[01]$') {
                $uri = [uri]("http://127.0.0.1:$port" + $Matches[1])
                if ($uri.AbsolutePath -ceq $callbackPath) {
                    $values = @{}
                    foreach ($pair in $uri.Query.TrimStart('?').Split('&')) {
                        $parts = $pair.Split('=',2)
                        if ($parts.Count -eq 2) { $values[[uri]::UnescapeDataString($parts[0])] = [uri]::UnescapeDataString($parts[1]) }
                    }
                    if ($values.state -ceq $state) {
                        $cancelled = $values.error -eq 'access_denied'
                        if ($values.code -match '^[A-Za-z0-9_-]{64}$') { $code = $values.code; $accepted = $true }
                    }
                }
            }
            $html = if ($accepted) {'<!doctype html><title>Consent received</title><h1>Consent received</h1><p>Return to Codex for the test results. This test does not read or change messages and will revoke its connection after checking the protocol.</p>'} elseif ($cancelled) {'<!doctype html><title>Cancelled</title><h1>Connection cancelled</h1><p>Return to Codex.</p>'} else {'<!doctype html><title>Invalid callback</title><h1>Invalid callback</h1>'}
            $bodyBytes = [System.Text.Encoding]::UTF8.GetBytes($html)
            $status = if ($accepted -or $cancelled) {'200 OK'} else {'400 Bad Request'}
            $headers = "HTTP/1.1 $status`r`nContent-Type: text/html; charset=utf-8`r`nContent-Length: $($bodyBytes.Length)`r`nCache-Control: no-store`r`nReferrer-Policy: no-referrer`r`nContent-Security-Policy: default-src 'none'; frame-ancestors 'none'`r`nConnection: close`r`n`r`n"
            $headerBytes = [System.Text.Encoding]::ASCII.GetBytes($headers)
            $stream.Write($headerBytes,0,$headerBytes.Length)
            $stream.Write($bodyBytes,0,$bodyBytes.Length)
            $stream.Flush()
            if ($cancelled) { throw 'The user cancelled consent.' }
        } finally { $connection.Dispose() }
    }
    if (-not $code) { throw 'Sign-in window expired. Restart the test to obtain a fresh link.' }
    Assert-Test $true 'OAuth callback and state validation'
    $token = Invoke-RestMethod -Uri "$origin/wp-json/ce-mail/v1/oauth/token" -Method POST -ContentType 'application/x-www-form-urlencoded' -Body @{grant_type='authorization_code'; code=$code; code_verifier=$verifier; client_id=$clientId; redirect_uri=$redirect; resource=$resource}
    $refreshToken = $token.refresh_token
    $accessToken = $token.access_token
    Assert-Test ($accessToken -and $refreshToken) 'PKCE authorization-code exchange'
    Assert-Test ($token.scope -notmatch 'mail:send') 'Sending permission not granted'
    $init = Invoke-Rpc $accessToken 'initialize' @{protocolVersion='2025-06-18'; capabilities=@{}; clientInfo=@{name='CE Mail acceptance test'; version='1'}} 1
    Assert-Test ($init.result.protocolVersion -eq '2025-06-18') 'MCP initialize'
    $tools = Invoke-Rpc $accessToken 'tools/list' @{} 2
    $names = @($tools.result.tools | ForEach-Object {$_.name})
    Assert-Test ($names -contains 'list_messages' -and $names -contains 'create_draft' -and $names -contains 'archive') 'Read, draft and organize tools available'
    Assert-Test ($names -notcontains 'send_message') 'Sending tool absent'
    $rotated = Invoke-RestMethod -Uri "$origin/wp-json/ce-mail/v1/oauth/token" -Method POST -ContentType 'application/x-www-form-urlencoded' -Body @{grant_type='refresh_token'; refresh_token=$refreshToken; client_id=$clientId; resource=$resource}
    Assert-Test ($rotated.refresh_token -and $rotated.refresh_token -ne $refreshToken) 'Refresh token rotation'
    $refreshToken = $rotated.refresh_token
    $accessToken = $rotated.access_token
    $ping = Invoke-Rpc $accessToken 'ping' @{} 3
    Assert-Test ($ping.id -eq 3 -and -not $ping.error) 'Refreshed access token works'
    Invoke-RestMethod -Uri "$origin/wp-json/ce-mail/v1/oauth/revoke" -Method POST -ContentType 'application/x-www-form-urlencoded' -Body @{token=$refreshToken; client_id=$clientId} | Out-Null
    $refreshToken = $null
    $after = Invoke-WebRequest -Uri $resource -Method POST -ContentType 'application/json' -Headers @{Authorization="Bearer $accessToken"} -Body '{"jsonrpc":"2.0","id":4,"method":"ping"}' -SkipHttpErrorCheck
    Assert-Test ([int]$after.StatusCode -eq 401) 'Revocation invalidates access token'
    Write-Output 'COMPLETE: Sign-in, consent, token exchange, tool discovery, refresh and revocation passed. No messages were read, changed or sent.'
} catch {
    # Never print response bodies or exception request objects: they can contain credentials.
    Write-Output 'TEST STOPPED: A step failed, consent was cancelled, or the sign-in window expired. Inspect the last PASS above.'
    if ($_.Exception.Message -like 'FAIL:*' -or $_.Exception.Message -like 'Sign-in window*' -or $_.Exception.Message -eq 'The user cancelled consent.') { Write-Output $_.Exception.Message }
} finally {
    if ($refreshToken -and $clientId) {
        try { Invoke-RestMethod -Uri "$origin/wp-json/ce-mail/v1/oauth/revoke" -Method POST -ContentType 'application/x-www-form-urlencoded' -Body @{token=$refreshToken; client_id=$clientId} | Out-Null; Write-Output 'Temporary grant revoked during cleanup.' }
        catch { Write-Output 'Cleanup could not confirm revocation. Revoke CE Mail sign-in test (temporary) in Mail Settings.' }
    }
    $listener.Stop()
    $verifier=$null; $code=$null; $accessToken=$null; $refreshToken=$null
}
