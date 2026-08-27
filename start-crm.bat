@echo off
title Unified CRM - Laravel Server
cd /d C:\laragon\www\crm

echo ========================================
echo   Menjalankan Unified CRM
echo   URL: http://127.0.0.1:8000
echo ========================================
echo.

"C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe" artisan serve --host=127.0.0.1 --port=8000

echo.
echo Server berhenti. Tekan tombol apa saja untuk menutup jendela.
pause >nul
