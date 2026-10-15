# Zips the mail portal plugin for upload under Plugins → Add New → Upload Plugin.
# Output: wp-plugin\dist\ce-mail.zip  (the zip contains the ce-mail folder itself)
$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

$root   = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$plugin = Join-Path $root 'wp-plugin\ce-mail'
$dist   = Join-Path $root 'wp-plugin\dist'
$zip    = Join-Path $dist 'ce-mail.zip'

if (-not (Test-Path $plugin)) { throw "Plugin folder not found: $plugin" }
New-Item -ItemType Directory -Force $dist | Out-Null
if (Test-Path $zip) { Remove-Item $zip -Force }

$version = (Select-String -Path (Join-Path $plugin 'ce-mail.php') -Pattern '^\s*\*\s*Version:\s*(.+)$' | Select-Object -First 1).Matches[0].Groups[1].Value.Trim()

$archive = [System.IO.Compression.ZipFile]::Open($zip, [System.IO.Compression.ZipArchiveMode]::Create)
try {
  Get-ChildItem -Path $plugin -Recurse -File | Where-Object { $_.Name -ne '.DS_Store' } | ForEach-Object {
    $relative = $_.FullName.Substring($plugin.Length).TrimStart('\').Replace('\', '/')
    [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($archive, $_.FullName, "ce-mail/$relative", [System.IO.Compression.CompressionLevel]::Optimal) | Out-Null
  }
} finally {
  $archive.Dispose()
}

$size = [math]::Round((Get-Item $zip).Length / 1KB)
Write-Host ""
Write-Host "  Mail portal $version packaged: $zip ($size KB)" -ForegroundColor Green
Write-Host "  Upload it in WordPress under Plugins > Add New > Upload Plugin."
Write-Host ""
