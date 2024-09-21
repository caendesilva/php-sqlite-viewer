@echo off
if "%~1"=="" (
    echo Error: Please provide the path to the SQLite database as an argument.
    echo Usage: %~nx0 path\to\your\database.sqlite
    exit /b 1
)

if not exist "%~1" (
    echo Error: The specified database file does not exist.
    echo Path: %~1
    exit /b 1
)

set SQLITE_DB_PATH=%~1
php -S localhost:8000 %~dp0sqlite_viewer.php