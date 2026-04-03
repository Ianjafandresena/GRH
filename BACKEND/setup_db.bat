@echo off
chcp 65001 >nul
echo =====================================================
echo   SETUP BASE DE DONNEES - ARMP Plateforme Unifiee
echo =====================================================
echo.

cd /d "%~dp0"

echo [1/2] Migration : Creation des tables...
echo.
php spark migrate --all
if %ERRORLEVEL% NEQ 0 (
    echo.
    echo ERREUR : La migration a echoue !
    pause
    exit /b 1
)
echo.
echo OK - Tables creees avec succes.
echo.

echo [2/2] Seed : Insertion des valeurs par defaut...
echo.
php spark db:seed UnifiedSeeder
if %ERRORLEVEL% NEQ 0 (
    echo.
    echo ERREUR : Le seed a echoue !
    pause
    exit /b 1
)
echo.
echo =====================================================
echo   TERMINE ! Base de donnees prete.
echo =====================================================
echo.
pause
