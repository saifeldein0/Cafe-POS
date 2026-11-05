@echo off
rem App-level setup: .env, app key, database, migrations, caches.
rem Called by setup.bat; safe to run on its own (no admin rights, no system changes)
rem and safe to re-run (keeps the existing .env key and database).
setlocal

set "DEPLOY_DIR=%~dp0"
pushd "%DEPLOY_DIR%.."

if exist "%DEPLOY_DIR%php\php.exe" (
    set "PHP=%DEPLOY_DIR%php\php.exe"
) else (
    set "PHP=php"
)

echo [1/6] Checking PHP...
"%PHP%" -v >nul 2>&1
if errorlevel 1 (
    echo   ERROR: PHP does not start. Is deploy\php present and complete?
    goto :fail
)
"%PHP%" -r "echo '  PHP '.PHP_VERSION.PHP_EOL;"

echo [2/6] Preparing .env...
if not exist ".env" (
    copy /y ".env.example" ".env" >nul || goto :fail
    echo   Created .env from .env.example
) else (
    echo   .env already exists, keeping it
)
powershell -NoProfile -ExecutionPolicy Bypass -Command ^
  "$p = (Resolve-Path '.env').Path; $c = [IO.File]::ReadAllLines($p);" ^
  "$c = $c -replace '^APP_ENV=.*', 'APP_ENV=production' -replace '^APP_DEBUG=.*', 'APP_DEBUG=false';" ^
  "if (-not ($c -match '^APP_ENV=')) { $c += 'APP_ENV=production' };" ^
  "if (-not ($c -match '^APP_DEBUG=')) { $c += 'APP_DEBUG=false' };" ^
  "[IO.File]::WriteAllLines($p, $c)" || goto :fail
echo   APP_ENV=production, APP_DEBUG=false

echo [3/6] Application key...
findstr /b /c:"APP_KEY=base64:" ".env" >nul
if errorlevel 1 (
    "%PHP%" artisan key:generate --force || goto :fail
) else (
    echo   Key already set, keeping it
)

echo [4/6] Database...
if not exist "database\database.sqlite" (
    type nul > "database\database.sqlite"
    echo   Created database\database.sqlite
) else (
    echo   Existing database kept
)

echo [5/6] Migrations and seed data (never overwrites existing edits)...
"%PHP%" artisan migrate --force --seed || goto :fail

echo [6/6] Caching for speed...
"%PHP%" artisan optimize || goto :fail
"%PHP%" artisan list filament 2>nul | findstr /c:"filament:optimize " >nul
if errorlevel 1 (
    echo   filament:optimize not available, skipped
) else (
    "%PHP%" artisan filament:optimize || goto :fail
)

echo.
echo App setup OK.
popd
endlocal & exit /b 0

:fail
echo.
echo App setup FAILED - see the messages above.
popd
endlocal & exit /b 1
