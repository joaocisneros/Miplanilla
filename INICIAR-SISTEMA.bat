@echo off
title MiPlanilla - Iniciar sistema
color 0B
echo ==================================================
echo    MiPlanilla - Encendiendo el sistema...
echo ==================================================
echo.
echo  1) Se abrira una ventana con el SERVIDOR (no la cierres)
echo  2) Se abrira otra ventana con el TUNEL y el LINK
echo.
echo  Copia el link que aparece como:
echo     https://algo-algo.trycloudflare.com
echo  y pasaselo al cliente.
echo.
echo ==================================================
echo.

cd /d C:\laragon\www\MiPlanilla

REM --- Ventana 1: Servidor Laravel ---
start "MiPlanilla - SERVIDOR (no cerrar)" cmd /k C:\laragon\bin\php\php-8.2.31-Win32-vs16-x64\php.exe artisan serve --host=127.0.0.1 --port=8000

REM --- Espera 4 segundos a que arranque el servidor ---
timeout /t 4 /nobreak >nul

REM --- Ventana 2: Tunel Cloudflare (aqui sale el LINK) ---
start "MiPlanilla - TUNEL (aqui esta el LINK)" cmd /k C:\laragon\bin\cloudflared\cloudflared.exe tunnel --url http://localhost:8000

echo.
echo  Listo. Mira la ventana del TUNEL para copiar el link.
echo  Para APAGAR el sistema: cierra las dos ventanas que se abrieron.
echo.
pause
