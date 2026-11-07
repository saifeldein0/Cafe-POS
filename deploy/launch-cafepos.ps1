# Desktop launcher for CafePOS POS (target of the "Caisse CafePOS" desktop shortcut).
#
#  - If the server already answers on the port: just open the browser.
#  - Otherwise: start deploy\start-cafepos.bat in a HIDDEN window and wait until the server
#    answers (max ~120 s, with a small "starting" window), then load the page once.
#  - Then open the page in its own app window (Edge or Chrome, no tabs or address bar),
#    falling back to the default browser if neither is installed.
#  - -Page pos (till, default) or -Page admin (owner dashboard).
#  - Idempotent: a second double-click never starts a second server (port check + a
#    named mutex so two quick double-clicks cannot race).
#
# The shortcut runs:
#   powershell.exe -NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File launch-cafepos.ps1
#
# Environment (optional):
#   CAFEPOS_PORT        port to use (default 8000), same variable as start-cafepos.bat
#   CAFEPOS_NO_BROWSER  1 = do not open the browser, print the URL instead (testing)
#   CAFEPOS_TIMEOUT     seconds to wait for the server (default 120)

param(
    [ValidateSet('pos', 'admin')]
    [string]$Page = 'pos'
)

$ErrorActionPreference = 'Stop'

$DeployDir = $PSScriptRoot
$AppDir = (Resolve-Path (Join-Path $DeployDir '..')).Path
$Port = if ($env:CAFEPOS_PORT) { [int]$env:CAFEPOS_PORT } else { 8000 }
$Timeout = if ($env:CAFEPOS_TIMEOUT) { [int]$env:CAFEPOS_TIMEOUT } else { 120 }
$NoBrowser = $env:CAFEPOS_NO_BROWSER -eq '1'
$CheckUrl = "http://127.0.0.1:$Port/up"
$PageUrl = "http://localhost:$Port/$Page"
$LogFile = Join-Path $AppDir 'storage\logs\launcher.log'

function Write-Log([string]$Message) {
    $line = '{0:yyyy-MM-dd HH:mm:ss} [port {1}] {2}' -f (Get-Date), $Port, $Message
    Write-Output $line
    try { Add-Content -Path $LogFile -Value $line -Encoding UTF8 } catch { }
}

# True when anything answers HTTP on the port (any status code counts as "running").
function Test-ServerAnswers {
    try {
        $request = [System.Net.HttpWebRequest]::Create($CheckUrl)
        $request.Timeout = 5000
        $request.Proxy = $null
        $request.GetResponse().Close()
        return $true
    } catch [System.Net.WebException] {
        if ($_.Exception.Response) { $_.Exception.Response.Close(); return $true }
        return $false
    } catch {
        return $false
    }
}

# True when something is listening on the port (e.g. a server that is still starting).
function Test-PortListening {
    $client = New-Object System.Net.Sockets.TcpClient
    try {
        $async = $client.BeginConnect('127.0.0.1', $Port, $null, $null)
        if (-not $async.AsyncWaitHandle.WaitOne(500)) { return $false }
        $client.EndConnect($async)
        return $true
    } catch {
        return $false
    } finally {
        $client.Close()
    }
}

function Open-Page {
    if ($NoBrowser) {
        Write-Log "Window skipped (CAFEPOS_NO_BROWSER=1): $PageUrl"
        return
    }
    $candidates = @(
        (Join-Path ${env:ProgramFiles(x86)} 'Microsoft\Edge\Application\msedge.exe'),
        (Join-Path $env:ProgramFiles 'Microsoft\Edge\Application\msedge.exe'),
        (Join-Path $env:ProgramFiles 'Google\Chrome\Application\chrome.exe'),
        (Join-Path ${env:ProgramFiles(x86)} 'Google\Chrome\Application\chrome.exe'),
        (Join-Path $env:LOCALAPPDATA 'Google\Chrome\Application\chrome.exe')
    )
    $exe = $candidates | Where-Object { Test-Path $_ } | Select-Object -First 1
    if ($exe) {
        # A dedicated profile keeps this window apart from normal browsing, its cache and zoom.
        $profileDir = Join-Path $env:LOCALAPPDATA 'CafePOS\window'
        Write-Log "Opening $PageUrl in an app window ($exe)"
        Start-Process -FilePath $exe -ArgumentList @(
            "--app=$PageUrl", "--user-data-dir=`"$profileDir`"", '--no-first-run',
            '--no-default-browser-check', '--start-maximized'
        )
    } else {
        Write-Log "No Edge or Chrome found: opening $PageUrl in the default browser"
        Start-Process $PageUrl
    }
}

# Loads the page once before the window opens, so its slow first render happens here.
function Invoke-WarmUp {
    try {
        $request = [System.Net.HttpWebRequest]::Create("http://127.0.0.1:$Port/$Page")
        $request.Timeout = 60000
        $request.Proxy = $null
        $request.GetResponse().Close()
    } catch [System.Net.WebException] {
        if ($_.Exception.Response) { $_.Exception.Response.Close() }
    } catch { }
}

# Small "starting" window, so nobody thinks nothing is happening on a slow first start.
$script:Splash = $null
function Show-Splash {
    if ($NoBrowser) { return }
    try {
        Add-Type -AssemblyName System.Windows.Forms
        $form = New-Object System.Windows.Forms.Form
        $form.Text = 'Caisse CafePOS'
        $form.StartPosition = 'CenterScreen'
        $form.FormBorderStyle = 'FixedDialog'
        $form.ControlBox = $false
        $form.TopMost = $true
        $form.ClientSize = New-Object System.Drawing.Size(420, 110)
        $label = New-Object System.Windows.Forms.Label
        $label.Text = "Démarrage de CafePOS, veuillez patienter...`nStarting CafePOS, please wait..."
        $label.Font = New-Object System.Drawing.Font('Segoe UI', 12)
        $label.TextAlign = 'MiddleCenter'
        $label.Dock = 'Fill'
        $form.Controls.Add($label)
        $form.Show()
        [System.Windows.Forms.Application]::DoEvents()
        $script:Splash = $form
    } catch { }
}
function Update-Splash { if ($script:Splash) { [System.Windows.Forms.Application]::DoEvents() } }
function Close-Splash {
    if ($script:Splash) {
        try { $script:Splash.Close(); $script:Splash.Dispose() } catch { }
        $script:Splash = $null
    }
}

function Show-Error([string]$Message) {
    Write-Log "ERROR: $Message"
    if ($NoBrowser) { return }
    try {
        Add-Type -AssemblyName System.Windows.Forms
        [System.Windows.Forms.MessageBox]::Show($Message, 'Caisse CafePOS', 'OK', 'Warning') | Out-Null
    } catch { }
}

$mutex = New-Object System.Threading.Mutex($false, "Local\CafePOSLauncher-$Port")
try {
    try { [void]$mutex.WaitOne(($Timeout + 15) * 1000) } catch [System.Threading.AbandonedMutexException] { }

    if (Test-ServerAnswers) {
        Write-Log 'Server already running'
        Invoke-WarmUp
        Open-Page
        exit 0
    }

    Show-Splash

    $process = $null
    if (Test-PortListening) {
        Write-Log 'Port busy but not answering yet: waiting, not starting a second server'
    } else {
        $bat = Join-Path $DeployDir 'start-cafepos.bat'
        Write-Log 'Starting server (hidden window)'
        $env:CAFEPOS_PORT = [string]$Port
        $process = Start-Process -FilePath $env:ComSpec -ArgumentList '/c', "`"$bat`"" `
            -WorkingDirectory $AppDir -WindowStyle Hidden -PassThru
    }

    $deadline = (Get-Date).AddSeconds($Timeout)
    while ((Get-Date) -lt $deadline) {
        Update-Splash
        if (Test-ServerAnswers) {
            Write-Log 'Server is up'
            Invoke-WarmUp
            Close-Splash
            Open-Page
            exit 0
        }
        if ($process -and $process.HasExited) { break }
        Start-Sleep -Milliseconds 500
    }
    Close-Splash

    $detail = if ($process -and $process.HasExited) {
        "le serveur s'est arrêté tout de suite"
    } else {
        "pas de réponse sur le port $Port après $Timeout secondes"
    }
    Show-Error ("La caisse CafePOS n'a pas pu démarrer.`n`n" +
        "Redémarrez l'ordinateur puis double-cliquez à nouveau sur l'icône Caisse CafePOS.`n" +
        "Si le problème continue, appelez la personne qui a installé l'application.`n`n" +
        "(Détail : $detail.)")
    exit 1
} finally {
    Close-Splash
    try { $mutex.ReleaseMutex() } catch { }
    $mutex.Dispose()
}
