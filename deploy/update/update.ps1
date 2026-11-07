# Applies a CafePOS update without re-copying the whole app.
#   1. stop the server       2. back up the database and count the existing orders
#   3. copy files\ over the install (code and assets only: never .env, the database or storage)
#   4. clear caches, run the new database migrations, rebuild caches
#   5. refresh both desktop icons
#   6. start the app, check the dashboard style file, check that no order was lost
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

function Invoke-Artisan([string[]]$Arguments) {
    & $php artisan @Arguments
    if ($LASTEXITCODE -ne 0) { throw "php artisan $($Arguments -join ' ') failed (code $LASTEXITCODE)." }
}

# Order count and total straight from the database file, independent of the app code.
function Get-OrderFingerprint {
    $database = Join-Path $Target 'database\database.sqlite'
    $script = Join-Path ([IO.Path]::GetTempPath()) 'cafepos-orders-check.php'
    $code = @'
<?php
$pdo = new PDO('sqlite:' . $argv[1]);
$orders = $pdo->query('SELECT COUNT(*) AS n, ROUND(COALESCE(SUM(total), 0), 3) AS t FROM orders')->fetch(PDO::FETCH_ASSOC);
$items = $pdo->query('SELECT COUNT(*) FROM order_items')->fetchColumn();
echo $orders['n'] . '|' . $orders['t'] . '|' . $items;
'@
    [IO.File]::WriteAllText($script, $code)
    try {
        $result = & $php $script $database
        if ($LASTEXITCODE -ne 0) { throw 'Could not read the orders from the database.' }
    } finally {
        Remove-Item $script -ErrorAction SilentlyContinue
    }
    return ([string]$result).Trim()
}

if (-not $Target) { $Target = Find-Install }
if (-not (Test-Path (Join-Path $Target 'artisan'))) { throw "Not a CafePOS folder: $Target" }
$php = Join-Path $Target 'deploy\php\php.exe'
Write-Host "Dossier / Folder: $Target"
Push-Location $Target
try {
    Write-Host '[1/6] Stopping the old server...'
    for ($i = 0; $i -lt 15; $i++) {
        $running = @(Get-Process php -ErrorAction SilentlyContinue | Where-Object { $_.Path -eq $php })
        if (-not $running) { break }
        $running | Stop-Process -Force -ErrorAction SilentlyContinue
        Start-Sleep -Milliseconds 700
    }
    if (@(Get-Process php -ErrorAction SilentlyContinue | Where-Object { $_.Path -eq $php })) {
        throw 'The old server would not stop. Restart the laptop, then run this update again.'
    }

    Write-Host '[2/6] Backing up the data...'
    Invoke-Artisan @('app:backup')
    $before = Get-OrderFingerprint
    Write-Host "  Orders before: $($before.Split('|')[0])"

    Write-Host '[3/6] Copying the updated files...'
    & robocopy $Source $Target /E /NFL /NDL /NJH /NJS /NP /R:2 /W:1 | Out-Null
    if ($LASTEXITCODE -ge 8) { throw "File copy failed (robocopy code $LASTEXITCODE)." }

    Write-Host '[4/6] Updating the database and caches...'
    Invoke-Artisan @('optimize:clear')
    Invoke-Artisan @('migrate', '--force')
    Invoke-Artisan @('optimize')
    Invoke-Artisan @('filament:optimize')

    Write-Host '[5/6] Desktop icons...'
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

    Write-Host '[6/6] Starting and checking...'
    $env:CAFEPOS_NO_BROWSER = '1'
    & $powershell -NoProfile -ExecutionPolicy Bypass -File $launcher | Out-Null
    $launcherCode = $LASTEXITCODE
    Remove-Item Env:CAFEPOS_NO_BROWSER
    if ($launcherCode -ne 0) { throw 'The server did not start. Restart the laptop, then double-click Caisse CafePOS.' }

    $manifest = Get-Content (Join-Path $Target 'public\build\manifest.json') -Raw | ConvertFrom-Json
    $themePath = $manifest.'resources/css/filament/admin/theme.css'.file
    $themeFile = Get-Item (Join-Path $Target ('public\build\' + ($themePath -replace '/', '\')))
    $response = Invoke-WebRequest "http://127.0.0.1:$Port/build/$themePath" -UseBasicParsing -TimeoutSec 60
    if ($response.StatusCode -ne 200 -or $response.RawContentLength -ne $themeFile.Length) {
        throw "Dashboard style file check failed (HTTP $($response.StatusCode), $($response.RawContentLength) of $($themeFile.Length) bytes)."
    }
    Write-Host "  Style file OK ($($themeFile.Length) bytes)"

    $after = Get-OrderFingerprint
    if ($after -ne $before) {
        throw "Order history changed during the update ($before -> $after). Do not use the till; send a photo of this window. The backup is in storage\backups."
    }
    Write-Host "  Orders after: $($after.Split('|')[0]) (unchanged)"

    & $powershell -NoProfile -ExecutionPolicy Bypass -File $launcher | Out-Null
} finally {
    Pop-Location
}

Write-Host ''
Write-Host '============================================================'
Write-Host ' OK - Caisse CafePOS : la caisse / the till'
Write-Host ' OK - Gerant CafePOS : le tableau de bord / the dashboard'
Write-Host '============================================================'
