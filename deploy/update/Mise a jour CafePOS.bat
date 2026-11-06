@echo off
title CafePOS POS - Mise a jour / Update
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0update.ps1"
if errorlevel 1 (
    echo.
    echo Envoyez une photo de cette fenetre / Please send a photo of this window.
)
echo.
pause
