@echo off
title Calvary Ellicott website preview
echo Starting the website preview...
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0tools\preview-server.ps1"
pause
