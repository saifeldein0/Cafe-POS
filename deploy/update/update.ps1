# Applies a small CafePOS update without re-copying the whole app.
# Copies files\ over the existing install, keeps the database and settings, stops the old
# server, refreshes both desktop icons, then checks the dashboard style file over HTTP.
#
# -Target / -DesktopDir override detection (testing). CAFEPOS_PORT is honoured by the launcher.

[CmdletBinding()]
param(
    [string]$Target,
    [string]$DesktopDir
)

$ErrorActionPreference = 'Stop'
$Source = Join-Path $PSScriptRoot 'files'
if (-not $DesktopDir) { $DesktopDir = [Environment]::GetFolderPath('Desktop') }
$Port = if ($env:CAFEPOS_PORT) { [int]$env:CAFEPOS_PORT } else { 8000 }

function Find-Install {
    $shell = New-Object -ComObject WScript.Shell
    $folders = @($DesktopDir, [Environment]::GetFolderPath('CommonDesktopDirectory'))
    foreach ($folder in $folders) {
        $link = Join-Path $folder 'Caisse CafePOS.lnk'
        if (Test-Path $link) {
            $shortcut = $shell.CreateShortcut($link)
            $found = @($shortcut.WorkingDirectory)
            if ($shortcut.Arguments -match '-File "(.+?)\\deploy\\launch-cafepos\.ps1"') { $found += $Matches[1] }
            foreach ($candidate in $found) {
                if ($candidate -and (Test-Path (Join-Path $candidate 'artisan'))) { return $candidate }
            }
        }
    }
    foreach ($candidate in @('C:\CafePOS', 'D:\CafePOS', 'C:\cafepos-release', 'D:\cafepos-release')) {
        if (Test-Path (Join-Path $candidate 'artisan')) { return $candidate }
    }
    Add-Type -AssemblyName System.Windows.Forms
    $dialog = New-Object System.Windows.Forms.FolderBrowserDialog
    $dialog.Description = 'Choisissez le dossier CafePOS / Choose the CafePOS folder'
    if ($dialog.ShowDialog() -eq 'OK' -and (Test-Path (Join-Path $dialog.SelectedPath 'artisan'))) {
        return $dialog.SelectedPath
    }
    throw 'CafePOS folder not found (no artisan file).'
}

if (-not $Target) { $Target = Find-Install }
if (-not (Test-Path (Join-Path $Target 'artisan'))) { throw "Not a CafePOS folder: $Target" }
Write-Host "Dossier / Folder: $Target"

Write-Host '[1/4] Stopping the old server...'
$php = Join-Path $Target 'deploy\php\php.exe'
for ($i = 0; $i -lt 15; $i++) {
    $running = @(Get-Process php -ErrorAction SilentlyContinue | Where-Object { $_.Path -eq $php })
    if (-not $running) { break }
    $running | Stop-Process -Force -ErrorAction SilentlyContinue
    Start-Sleep -Milliseconds 700
}
if (@(Get-Process php -ErrorAction SilentlyContinue | Where-Object { $_.Path -eq $php })) {
    throw 'The old server would not stop. Restart the laptop, then run this update again.'
}

Write-Host '[2/4] Copying the updated files...'
& robocopy $Source $Target /E /NFL /NDL /NJH /NJS /NP /R:2 /W:1 | Out-Null
if ($LASTEXITCODE -ge 8) { throw "File copy failed (robocopy code $LASTEXITCODE)." }

Write-Host '[3/4] Desktop icons...'
$system = [Environment]::SystemDirectory
$launcher = Join-Path $Target 'deploy\launch-cafepos.ps1'
$powershell = Join-Path $system 'WindowsPowerShell\v1.0\powershell.exe'
$baseArgs = '--headless "' + $powershell + '" -NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File "' + $launcher + '"'
$shell = New-Object -ComObject WScript.Shell
foreach ($icon in @(
    @{ Name = 'Caisse CafePOS'; Extra = '' },
    @{ Name = 'Gerant CafePOS'; Extra = ' -Page admin' }
)) {
    $shortcut = $shell.CreateShortcut((Join-Path $DesktopDir ($icon.Name + '.lnk')))
    $shortcut.TargetPath = Join-Path $system 'conhost.exe'
    $shortcut.Arguments = $baseArgs + $icon.Extra
    $shortcut.WorkingDirectory = $Target
    $shortcut.IconLocation = (Join-Path $Target 'deploy\cafepos.ico') + ',0'
    $shortcut.Description = $icon.Name
    $shortcut.WindowStyle = 7
    $shortcut.Save()
}

Write-Host '[4/4] Starting and checking the dashboard...'
$env:CAFEPOS_NO_BROWSER = '1'
& $powershell -NoProfile -ExecutionPolicy Bypass -File $launcher | Out-Null
Remove-Item Env:CAFEPOS_NO_BROWSER
if ($LASTEXITCODE -ne 0) { throw 'The server did not start. Restart the laptop, then double-click Gerant CafePOS.' }

$manifest = Get-Content (Join-Path $Target 'public\build\manifest.json') -Raw | ConvertFrom-Json
$themePath = $manifest.'resources/css/filament/admin/theme.css'.file
$themeFile = Get-Item (Join-Path $Target ('public\build\' + ($themePath -replace '/', '\')))
$response = Invoke-WebRequest "http://127.0.0.1:$Port/build/$themePath" -UseBasicParsing -TimeoutSec 30
if ($response.StatusCode -ne 200 -or $response.RawContentLength -ne $themeFile.Length) {
    throw "Dashboard style file check failed (HTTP $($response.StatusCode), $($response.RawContentLength) of $($themeFile.Length) bytes)."
}
Write-Host "  Style file OK ($($themeFile.Length) bytes)"

& $powershell -NoProfile -ExecutionPolicy Bypass -File $launcher -Page admin | Out-Null
Write-Host ''
Write-Host '============================================================'
Write-Host " OK - Caisse CafePOS : la caisse / the till"
Write-Host " OK - Gerant CafePOS : le tableau de bord / the dashboard"
Write-Host '============================================================'
