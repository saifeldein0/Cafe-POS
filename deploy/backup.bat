@echo off
rem Backs up the database to storage\backups (keeps the newest 30). Safe while the app runs.
setlocal

set "DEPLOY_DIR=%~dp0"
pushd "%DEPLOY_DIR%.."

if exist "%DEPLOY_DIR%php\php.exe" (
    set "PHP=%DEPLOY_DIR%php\php.exe"
) else (
    set "PHP=php"
)

"%PHP%" artisan app:backup
set "RESULT=%ERRORLEVEL%"

popd
endlocal & exit /b %RESULT%
