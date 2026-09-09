@echo off
title Subir Paseo Macuto a GitHub
echo ========================================================
echo   PASEO MACUTO - SUBIR REPOSITORIO A GITHUB
echo   Repositorio: https://github.com/ClusterLG/paseo-macuto.git
echo ========================================================
echo.
echo Ejecutando git push -u origin main...
git push -u origin main
echo.
if %ERRORLEVEL% equ 0 (
    echo ========================================================
    echo   EXITO: El repositorio se ha subido correctamente!
    echo ========================================================
) else (
    echo.
    echo Si te solicito credenciales, inicia sesion con tu cuenta de GitHub.
)
echo.
pause
