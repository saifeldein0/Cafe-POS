@echo off
rem One-time installation of CafePOS POS on the cafe laptop.
rem Right-click -> "Run as administrator".
rem   1. App setup (setup-app.bat): .env, key, database, caches
rem   2. Windows setup: firewall rule, nightly backup, desktop icon
rem No auto-start: the "Caisse CafePOS" desktop icon starts the server (hidden) and opens the till.
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

echo [b] Nightly backup at 23:30 (runs only if the laptop is on)...
schtasks /create /tn "CafePOS POS Backup" /tr "\"%DEPLOY_DIR%backup.bat\"" /sc daily /st 23:30 /f >nul
if errorlevel 1 (echo   WARNING: scheduled task failed) else (echo   OK)

echo [c] Desktop icons "Caisse CafePOS" (till) and "Gerant CafePOS" (dashboard)...
rem Shortcut -> conhost --headless -> hidden PowerShell -> deploy\launch-cafepos.ps1:
rem no console window, even when Windows Terminal is the default terminal.
rem Also removes what older versions created (Startup-folder auto-start, .url shortcut).
powershell -NoProfile -ExecutionPolicy Bypass -Command ^
  "$startup = [IO.Path]::Combine([Environment]::GetFolderPath('Startup'), 'CafePOS POS.lnk'); if (Test-Path $startup) { Remove-Item $startup -Force };" ^
  "$desktop = [Environment]::GetFolderPath('Desktop'); $old = Join-Path $desktop 'Caisse CafePOS.url'; if (Test-Path $old) { Remove-Item $old -Force };" ^
  "$sys = [Environment]::SystemDirectory;" ^
  "$s = (New-Object -ComObject WScript.Shell).CreateShortcut((Join-Path $desktop 'Caisse CafePOS.lnk'));" ^
  "$s.TargetPath = Join-Path $sys 'conhost.exe';" ^
  "$s.Arguments = '--headless \"' + (Join-Path $sys 'WindowsPowerShell\v1.0\powershell.exe') + '\" -NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File \"%DEPLOY_DIR%launch-cafepos.ps1\"';" ^
  "$s.WorkingDirectory = '%APP_DIR%'; $s.IconLocation = '%DEPLOY_DIR%cafepos.ico,0';" ^
  "$s.Description = 'Caisse CafePOS'; $s.WindowStyle = 7; $s.Save();" ^
  "$a = (New-Object -ComObject WScript.Shell).CreateShortcut((Join-Path $desktop 'Gerant CafePOS.lnk'));" ^
  "$a.TargetPath = $s.TargetPath; $a.Arguments = $s.Arguments + ' -Page admin';" ^
  "$a.WorkingDirectory = '%APP_DIR%'; $a.IconLocation = '%DEPLOY_DIR%cafepos.ico,0';" ^
  "$a.Description = 'Gerant CafePOS'; $a.WindowStyle = 7; $a.Save()"
if errorlevel 1 (echo   WARNING: desktop icon failed) else (echo   OK)

echo.
echo ============================================================
echo  Installation complete
echo ============================================================
chcp 65001 >nul
echo  Pour démarrer: double-cliquez sur l'icône Caisse CafePOS du bureau
echo                 (starts the app hidden and opens the till in its own window;
echo                  no auto-start: do it again after each restart of the laptop)
for /f "delims=" %%d in ('powershell -NoProfile -Command "[Environment]::GetFolderPath('Desktop')"') do set "URLDIR=%%d"
set "URLFILE=%URLDIR%\Adresse telephone - CafePOS.txt"
> "%URLFILE%" echo CAFEPOS - Tableau de bord depuis le telephone / Dashboard from the phone
>> "%URLFILE%" echo.
>> "%URLFILE%" echo Sur le meme Wi-Fi, ouvrez cette adresse dans le navigateur du telephone :
>> "%URLFILE%" echo On the same Wi-Fi, open this address in the phone browser:
>> "%URLFILE%" echo.
echo  Owner (phone, same Wi-Fi - usually the 192.168.x.x address):
for /f "tokens=2 delims=:" %%a in ('ipconfig ^| findstr /c:"IPv4"') do call :showip %%a
>> "%URLFILE%" echo.
>> "%URLFILE%" echo (S'il y a plusieurs adresses, prenez celle en 192.168.x.x)
>> "%URLFILE%" echo (If there are several addresses, use the 192.168.x.x one)
>> "%URLFILE%" echo Le portable doit etre allume et l'application demarree.
>> "%URLFILE%" echo The laptop must be on and the app started.
echo                  Works only while the laptop is on and the app is started.
echo                  Ask for a DHCP reservation in the router so this address never changes.
echo                  Also saved on the desktop: "Adresse telephone - CafePOS.txt"
echo  Owner PIN     : 1234 by default - change it in the dashboard ^("Changer le PIN"^)
echo  Backups       : storage\backups (every night at 23:30, if the laptop is on)
echo ============================================================
echo.
pause
endlocal & exit /b 0

:showip
set "IP=%~1"
for /f "tokens=1 delims=( " %%b in ("%IP%") do set "IP=%%b"
echo                  http://%IP%:8000/admin
>> "%URLFILE%" echo     http://%IP%:8000/admin
exit /b 0

:fail
echo.
echo Installation stopped.
pause
endlocal & exit /b 1
