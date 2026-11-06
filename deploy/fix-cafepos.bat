@echo off
rem Repairs an existing CafePOS install without re-copying the folder.
rem Put this file in the app folder or its deploy folder, then double-click it.
rem Adds the PHP intl extension, stops any running server and re-runs setup.bat.
rem Keeps existing data: setup never overwrites the database or the owner's edits.
title CafePOS POS - Reparation / Repair

rem Relaunch inside "cmd /k" so the window stays open whatever happens below.
if /i "%~1"=="_run" goto :main
set "CAFEPOS_SELF=%~f0"
net session >nul 2>&1
if not errorlevel 1 goto :relaunch
powershell -NoProfile -ExecutionPolicy Bypass -Command "$q = [char]34; try { Start-Process -FilePath cmd.exe -Verb RunAs -ErrorAction Stop -ArgumentList ('/k ' + $q + $q + $env:CAFEPOS_SELF + $q + ' _run' + $q) } catch { exit 1 }"
if not errorlevel 1 exit /b
echo Administrator rights were refused. Please try again and answer Yes.
pause
exit /b

:relaunch
start "CafePOS POS - Repair" cmd.exe /k ""%~f0" _run"
exit /b

:main
setlocal
set "DEPLOY_DIR="
if exist "%~dp0php\php.exe" set "DEPLOY_DIR=%~dp0"
if exist "%~dp0deploy\php\php.exe" set "DEPLOY_DIR=%~dp0deploy\"
if not defined DEPLOY_DIR (
    echo ERROR: put this file inside the CafePOS folder, next to setup.bat.
    goto :end
)
set "CAFEPOS_PHP=%DEPLOY_DIR%php\php.exe"
set "PHP_INI=%DEPLOY_DIR%php\php.ini"

net session >nul 2>&1
if errorlevel 1 (
    echo ERROR: administrator rights are required.
    goto :end
)

echo [1/4] Stopping any running CafePOS server...
powershell -NoProfile -ExecutionPolicy Bypass -Command "for ($i = 0; $i -lt 15; $i++) { $p = @(Get-Process php -ErrorAction SilentlyContinue | Where-Object { $_.Path -eq $env:CAFEPOS_PHP }); if (-not $p) { break }; $p | Stop-Process -Force -ErrorAction SilentlyContinue; Start-Sleep -Milliseconds 700 }"
echo   OK

echo [2/4] Adding the PHP intl extension...
findstr /b /c:"extension=intl" "%PHP_INI%" >nul
if errorlevel 1 (
    >> "%PHP_INI%" echo.
    >> "%PHP_INI%" echo extension=intl
    echo   Added
) else (
    echo   Already present
)
"%CAFEPOS_PHP%" -r "exit(extension_loaded('intl') ? 0 : 1);"
if errorlevel 1 (
    echo ERROR: PHP could not load intl.
    goto :end
)
echo   OK

echo [3/4] Running setup...
call "%DEPLOY_DIR%setup.bat"
if errorlevel 1 (
    echo.
    echo ERROR: setup failed. Please send a photo of this window.
    goto :end
)

echo [4/4] Checking the result...
pushd "%DEPLOY_DIR%.."
set "CHECK_OK=1"
if not exist ".env" set "CHECK_OK="
if not exist "database\database.sqlite" set "CHECK_OK="
"%CAFEPOS_PHP%" artisan migrate:status >nul 2>&1
if errorlevel 1 set "CHECK_OK="
popd
if not defined CHECK_OK (
    echo ERROR: the app is not ready. Please send a photo of this window.
    goto :end
)
echo.
echo ============================================================
echo  OK - double-cliquez sur l'icone Caisse CafePOS du bureau.
echo  OK - double-click the Caisse CafePOS icon on the desktop.
echo  Vous pouvez fermer cette fenetre / You can close this window.
echo ============================================================

:end
echo.
endlocal
