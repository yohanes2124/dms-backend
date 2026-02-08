@echo off
REM Create rooms in database using Laravel Tinker
REM Run this from the backend directory

echo Creating 180 rooms in database...
echo.

REM Try to find PHP
for /f "delims=" %%i in ('where php 2^>nul') do set PHP_PATH=%%i

if "%PHP_PATH%"=="" (
    echo Trying XAMPP PHP...
    if exist "C:\xampp\bin\php.exe" (
        set PHP_PATH=C:\xampp\bin\php.exe
    ) else if exist "C:\php\php.exe" (
        set PHP_PATH=C:\php\php.exe
    ) else (
        echo ERROR: PHP not found in PATH or common locations
        echo Please install PHP or add it to your PATH
        pause
        exit /b 1
    )
)

echo Using PHP: %PHP_PATH%
echo.

REM Run the PHP script
"%PHP_PATH%" create-rooms-direct.php

if %ERRORLEVEL% EQU 0 (
    echo.
    echo SUCCESS! Rooms created. Refresh your browser.
) else (
    echo.
    echo ERROR: Failed to create rooms
    pause
)
