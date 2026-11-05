@echo off
rem One-time installation of CafePOS POS on the cafe laptop.
rem Right-click -> "Run as administrator".
rem   1. App setup (setup-app.bat): .env, key, database, caches
rem   2. Windows setup: firewall rule, auto-start, nightly backup, desktop shortcut
title CafePOS POS - Installation
setlocal

set "DEPLOY_DIR=%~dp0"
for %%i in ("%DEPLOY_DIR%..") do set "APP_DIR=%%~fi"

echo ============================================================
echo  CafePOS POS - installation
echo  Folder: %APP_DIR%
echo ============================================================
echo.

net session >nul 2>&1
if errorlevel 1 (
    echo ERROR: please run this file as administrator
    echo        ^(right-click setup.bat -^> "Run as administrator"^).
    goto :fail
)

if not exist "%DEPLOY_DIR%php\php.exe" (
    echo ERROR: deploy\php\php.exe is missing.
    echo        The package must be built with deploy\get-php.ps1 + deploy\package.ps1.
    goto :fail
)

echo --- Application -------------------------------------------
call "%DEPLOY_DIR%setup-app.bat"
if errorlevel 1 goto :fail
echo.

echo --- Windows -----------------------------------------------
echo [a] Firewall: allow incoming TCP 8000 (phone access)...
netsh advfirewall firewall delete rule name="CafePOS POS" >nul 2>&1
netsh advfirewall firewall add rule name="CafePOS POS" dir=in action=allow protocol=TCP localport=8000 >nul
if errorlevel 1 (echo   WARNING: firewall rule failed) else (echo   OK)

echo [b] Start the app automatically at logon (minimized)...
powershell -NoProfile -ExecutionPolicy Bypass -Command ^
  "$s = (New-Object -ComObject WScript.Shell).CreateShortcut([IO.Path]::Combine([Environment]::GetFolderPath('Startup'), 'CafePOS POS.lnk'));" ^
  "$s.TargetPath = '%DEPLOY_DIR%start-cafepos.bat'; $s.WorkingDirectory = '%APP_DIR%'; $s.WindowStyle = 7; $s.Save()"
if errorlevel 1 (echo   WARNING: startup shortcut failed) else (echo   OK)

echo [c] Nightly backup at 23:30...
schtasks /create /tn "CafePOS POS Backup" /tr "\"%DEPLOY_DIR%backup.bat\"" /sc daily /st 23:30 /f >nul
if errorlevel 1 (echo   WARNING: scheduled task failed) else (echo   OK)

echo [d] Desktop shortcut "Caisse CafePOS"...
powershell -NoProfile -ExecutionPolicy Bypass -Command ^
  "$s = (New-Object -ComObject WScript.Shell).CreateShortcut([IO.Path]::Combine([Environment]::GetFolderPath('Desktop'), 'Caisse CafePOS.url'));" ^
  "$s.TargetPath = 'http://localhost:8000/pos'; $s.Save()"
if errorlevel 1 (echo   WARNING: desktop shortcut failed) else (echo   OK)

echo.
echo ============================================================
echo  Installation complete
echo ============================================================
echo  Start now     : double-click deploy\start-cafepos.bat
echo                  (starts automatically at every logon)
echo  Cashier (here): "Caisse CafePOS" on the desktop
echo                  = http://localhost:8000/pos
echo  Owner (phone, same Wi-Fi - usually the 192.168.x.x address):
for /f "tokens=2 delims=:" %%a in ('ipconfig ^| findstr /c:"IPv4"') do call :showip %%a
echo  Owner PIN     : 1234 by default - change it in the dashboard ^("Changer le PIN"^)
echo  Backups       : storage\backups (every night at 23:30)
echo ============================================================
echo.
pause
endlocal & exit /b 0

:showip
set "IP=%~1"
for /f "tokens=1 delims=( " %%b in ("%IP%") do set "IP=%%b"
echo                  http://%IP%:8000/admin
exit /b 0

:fail
echo.
echo Installation stopped.
pause
endlocal & exit /b 1
