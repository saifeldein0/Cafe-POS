# Assembles a self-contained release folder to copy onto the cafe laptop (USB stick).
#
# Usage:  powershell -ExecutionPolicy Bypass -File deploy\package.ps1 [-Destination <dir>] [-SkipBuild]
#
# Includes app code, vendor\, public\build (fresh `npm run build`) and deploy\ with the
# portable PHP runtime (run deploy\get-php.ps1 first). Excludes .git, node_modules, tests,
# logs, backups, the dev database, .env and dev caches: setup.bat creates fresh ones.

[CmdletBinding()]
param(
    [string]$Destination,
    [switch]$SkipBuild
)

$ErrorActionPreference = 'Stop'
$AppDir = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
if (-not $Destination) { $Destination = Join-Path (Split-Path $AppDir -Parent) 'cafepos-release' }
$Destination = [IO.Path]::GetFullPath($Destination)

if ($Destination.TrimEnd('\').StartsWith($AppDir.TrimEnd('\') + '\', [StringComparison]::OrdinalIgnoreCase)) {
    throw "Destination must be outside the app folder ($AppDir)"
}

if (-not (Test-Path (Join-Path $PSScriptRoot 'php\php.exe'))) {
    Write-Warning 'deploy\php is missing: the package will need PHP installed on the target. Run deploy\get-php.ps1 first.'
}

if (-not $SkipBuild) {
    Write-Host '==> npm run build'
    Push-Location $AppDir
    try {
        & npm run build
        if ($LASTEXITCODE -ne 0) { throw 'npm run build failed' }
    } finally {
        Pop-Location
    }
}
if (-not (Test-Path (Join-Path $AppDir 'public\build\manifest.json'))) {
    throw 'public\build\manifest.json missing: run npm run build'
}
if (-not (Test-Path (Join-Path $AppDir 'vendor\autoload.php'))) {
    throw 'vendor\ missing: run composer install'
}

if (Test-Path $Destination) {
    $existing = Get-ChildItem $Destination -Force
    if ($existing -and -not (Test-Path (Join-Path $Destination 'artisan'))) {
        throw "$Destination exists and does not look like a previous release; refusing to overwrite"
    }
    Write-Host "==> Clearing previous release in $Destination"
    Remove-Item $Destination -Recurse -Force
}

Write-Host "==> Copying $AppDir -> $Destination"
$excludeDirs = @('.git', '.claude', '.idea', '.vscode', 'node_modules', 'tests', 'storage\backups') |
    ForEach-Object { Join-Path $AppDir $_ }
$excludeFiles = @('.env', '.env.backup', '.env.production', '.phpunit.result.cache', 'php-*.zip')

& robocopy $AppDir $Destination /E /NFL /NDL /NJH /NP /R:1 /W:1 /XD $excludeDirs /XF $excludeFiles | Out-Host
if ($LASTEXITCODE -ge 8) { throw "robocopy failed with exit code $LASTEXITCODE" }

# Remove machine-specific / dev state; keep the folders' .gitignore placeholders.
Write-Host '==> Removing dev database, logs and caches'
Get-ChildItem (Join-Path $Destination 'database') -Filter 'database.sqlite*' -Force | Remove-Item -Force
$cleanDirs = @(
    'storage\logs',
    'storage\framework\cache\data',
    'storage\framework\sessions',
    'storage\framework\views',
    'storage\framework\testing',
    'bootstrap\cache'
)
foreach ($dir in $cleanDirs) {
    $path = Join-Path $Destination $dir
    if (Test-Path $path) {
        Get-ChildItem $path -Force | Where-Object { $_.Name -ne '.gitignore' } | Remove-Item -Recurse -Force
    }
}

$bytes = (Get-ChildItem $Destination -Recurse -File -Force | Measure-Object Length -Sum).Sum
$sizeMb = [math]::Round($bytes / 1MB, 1)
Write-Host ''
Write-Host "Release ready: $Destination ($sizeMb MB)"
Write-Host 'Copy this folder to the cafe laptop, then right-click deploy\setup.bat -> Run as administrator.'
