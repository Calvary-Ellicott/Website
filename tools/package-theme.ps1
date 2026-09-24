# Zips the WordPress theme for upload under Appearance → Themes → Upload Theme.
# Output: wp-theme\dist\calvary-ellicott.zip  (the zip contains the calvary-ellicott folder itself)
#
# Built with .NET directly rather than Compress-Archive, because Windows PowerShell's
# Compress-Archive writes backslashes into entry names, which WordPress on Linux rejects.
$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

$root  = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$theme = Join-Path $root 'wp-theme\calvary-ellicott'
$dist  = Join-Path $root 'wp-theme\dist'
$zip   = Join-Path $dist 'calvary-ellicott.zip'

if (-not (Test-Path $theme)) { throw "Theme folder not found: $theme" }
New-Item -ItemType Directory -Force $dist | Out-Null
if (Test-Path $zip) { Remove-Item $zip -Force }

$version = (Select-String -Path (Join-Path $theme 'style.css') -Pattern '^Version:\s*(.+)$' | Select-Object -First 1).Matches[0].Groups[1].Value.Trim()

$archive = [System.IO.Compression.ZipFile]::Open($zip, [System.IO.Compression.ZipArchiveMode]::Create)
try {
  Get-ChildItem -Path $theme -Recurse -File | Where-Object { $_.Name -ne '.DS_Store' } | ForEach-Object {
    $relative = $_.FullName.Substring($theme.Length).TrimStart('\').Replace('\', '/')
    $entryName = "calvary-ellicott/$relative"
    [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($archive, $_.FullName, $entryName, [System.IO.Compression.CompressionLevel]::Optimal) | Out-Null
  }
} finally {
  $archive.Dispose()
}

$size = [math]::Round((Get-Item $zip).Length / 1KB)
Write-Host ""
Write-Host "  Theme $version packaged: $zip ($size KB)" -ForegroundColor Green
Write-Host "  Upload it in WordPress under Appearance > Themes > Add New > Upload Theme."
Write-Host ""
