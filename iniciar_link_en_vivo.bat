@echo off
title Paseo Macuto - Enlace Publico en Vivo (Cloudflare Tunnel)
echo ========================================================
echo   PASEO MACUTO - SERVICIO EN VIVO PARA INTERNET
echo   Generando enlace publico seguro HTTPS con Cloudflare
echo ========================================================
echo.
echo 1. Verificando servidor Flask local...
tasklist /FI "IMAGENAME eq python.exe" 2>NUL | find /I /N "python.exe">NUL
if "%ERRORLEVEL%"=="0" (
    echo    [OK] Servidor Python activo en el puerto 5000.
) else (
    echo    [!] Iniciando servidor Python en segundo plano...
    start /B python app.py
    timeout /t 3 /nobreak >nul
)

echo.
echo 2. Abriendo tunel publico seguro...
echo    Copia el link que termine en ".trycloudflare.com" que aparecera abajo.
echo.
echo ========================================================
.\cloudflared.exe tunnel --url http://127.0.0.1:5000
pause
