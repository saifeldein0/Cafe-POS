@echo off
rem Starts the CafePOS POS server on port 8000 (reachable from the phone on the same Wi-Fi).
rem The desktop icon runs it hidden through launch-cafepos.ps1; run it directly to see the
rem server console (closing the window stops the server). CAFEPOS_PORT overrides the port.
title CafePOS POS
setlocal

set "DEPLOY_DIR=%~dp0"
pushd "%DEPLOY_DIR%.."

if exist "%DEPLOY_DIR%php\php.exe" (
    set "PHP=%DEPLOY_DIR%php\php.exe"
) else (
    set "PHP=php"
)

if "%CAFEPOS_PORT%"=="" set "CAFEPOS_PORT=8000"

echo CafePOS POS - http://localhost:%CAFEPOS_PORT%/pos
echo Ne fermez pas cette fenetre / Do not close this window.
echo.
"%PHP%" artisan serve --host=0.0.0.0 --port=%CAFEPOS_PORT%

popd
endlocal
