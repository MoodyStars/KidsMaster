@echo off
REM start_worker_xampp.bat
REM Adjust XAMPP_PATH if needed
SET XAMPP_PATH=C:\xampp
SET PHP_EXE=%XAMPP_PATH%\php\php.exe
SET WORKER=%~dp0\..\workers\worker.php

echo Starting KidsMaster worker...
"%PHP_EXE%" "%WORKER%"
pause