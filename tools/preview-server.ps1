# Tiny local web server for previewing the website. Started by preview.bat.
# Serves the folder above this one at http://localhost:8080/ and opens the browser.
# Nothing to install: this uses only what ships with Windows.
param([int]$Port = 8080)

$root = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$mime = @{
  '.html' = 'text/html; charset=utf-8'; '.css' = 'text/css; charset=utf-8'; '.js' = 'text/javascript; charset=utf-8'
  '.json' = 'application/json; charset=utf-8'; '.pdf' = 'application/pdf'; '.png' = 'image/png'; '.jpg' = 'image/jpeg'
  '.jpeg' = 'image/jpeg'; '.webp' = 'image/webp'; '.gif' = 'image/gif'; '.svg' = 'image/svg+xml'; '.ico' = 'image/x-icon'
  '.md' = 'text/plain; charset=utf-8'; '.txt' = 'text/plain; charset=utf-8'
}

try {
  $listener = New-Object System.Net.Sockets.TcpListener([System.Net.IPAddress]::Loopback, $Port)
  $listener.Start()
} catch {
  Write-Host ""
  Write-Host "Could not start on port $Port. Is another preview window already open? Close it and try again." -ForegroundColor Yellow
  exit 1
}

Write-Host ""
Write-Host "  Website preview is running at  http://localhost:$Port/" -ForegroundColor Green
Write-Host "  Your browser should open by itself. Refresh the page to see changes you make."
Write-Host "  Close this window when you are done."
Write-Host ""
Start-Process "http://localhost:$Port/"

while ($true) {
  $client = $listener.AcceptTcpClient()
  try {
    $stream = $client.GetStream()
    $reader = New-Object System.IO.StreamReader($stream, [System.Text.Encoding]::ASCII)
    $requestLine = $reader.ReadLine()
    while ($true) { $line = $reader.ReadLine(); if ($null -eq $line -or $line -eq '') { break } }
    if (-not $requestLine) { continue }

    $parts = $requestLine.Split(' ')
    $method = $parts[0]
    $path = [uri]::UnescapeDataString(($parts[1] -split '\?')[0])
    if ($path.EndsWith('/')) { $path += 'index.html' }
    $full = [System.IO.Path]::GetFullPath((Join-Path $root ($path.TrimStart('/') -replace '/', '\')))

    $status = '200 OK'; $type = 'application/octet-stream'
    if (-not $full.StartsWith($root, [System.StringComparison]::OrdinalIgnoreCase) -or -not (Test-Path -LiteralPath $full -PathType Leaf)) {
      $status = '404 Not Found'; $type = 'text/html; charset=utf-8'
      $notFound = Join-Path $root '404.html'
      if (Test-Path -LiteralPath $notFound) { $body = [System.IO.File]::ReadAllBytes($notFound) } else { $body = [System.Text.Encoding]::UTF8.GetBytes('Not found') }
    } else {
      $body = [System.IO.File]::ReadAllBytes($full)
      $ext = [System.IO.Path]::GetExtension($full).ToLower()
      if ($mime.ContainsKey($ext)) { $type = $mime[$ext] }
    }

    $header = "HTTP/1.0 $status`r`nContent-Type: $type`r`nContent-Length: $($body.Length)`r`nCache-Control: no-store`r`nConnection: close`r`n`r`n"
    $headerBytes = [System.Text.Encoding]::ASCII.GetBytes($header)
    $stream.Write($headerBytes, 0, $headerBytes.Length)
    if ($method -ne 'HEAD') { $stream.Write($body, 0, $body.Length) }
    $stream.Flush()
  } catch {
  } finally {
    $client.Close()
  }
}
