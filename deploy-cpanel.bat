@echo off
REM =============================================================================
REM Aurum Vault Logistics - cPanel Deployment Packager
REM =============================================================================
REM This script creates a deployment-ready folder structure for cPanel.
REM 
REM Output structure (in deploy/ folder):
REM   deploy/
REM     public_html/        <- Upload contents to cPanel public_html/
REM       index.php, login.php, etc.
REM       admin/
REM       client/
REM       assets/
REM       .htaccess
REM       .user.ini
REM     includes/           <- Upload to /home/user/includes/
REM     vendor/             <- Upload to /home/user/vendor/
REM     database/           <- Keep locally, import schema.sql via phpMyAdmin
REM =============================================================================

echo.
echo ========================================
echo  Aurum Vault Logistics - cPanel Deploy
echo ========================================
echo.

REM Clean previous deploy
if exist "deploy" (
    echo Cleaning previous deploy folder...
    rmdir /s /q deploy
)

echo Creating deployment structure...

REM Create directories
mkdir deploy\public_html\admin
mkdir deploy\public_html\client
mkdir deploy\public_html\client\api
mkdir deploy\public_html\assets
mkdir deploy\public_html\uploads\kyc
mkdir deploy\includes\repositories
mkdir deploy\includes\services
mkdir deploy\includes\validators
mkdir deploy\includes\templates\email

REM Copy public_html files
echo Copying public files...
copy public_html\*.php deploy\public_html\ >nul 2>&1
copy public_html\.htaccess deploy\public_html\ >nul 2>&1
copy public_html\.user.ini deploy\public_html\ >nul 2>&1
xcopy public_html\assets deploy\public_html\assets /E /I /Q >nul 2>&1

REM Copy admin files into public_html/admin/
echo Copying admin files...
copy admin\*.php deploy\public_html\admin\ >nul 2>&1

REM Copy client files into public_html/client/
echo Copying client files...
copy client\*.php deploy\public_html\client\ >nul 2>&1
if exist "client\api" (
    copy client\api\*.php deploy\public_html\client\api\ >nul 2>&1
)

REM Copy includes (above public_html)
echo Copying includes...
copy includes\*.php deploy\includes\ >nul 2>&1
copy includes\repositories\*.php deploy\includes\repositories\ >nul 2>&1
copy includes\services\*.php deploy\includes\services\ >nul 2>&1
copy includes\validators\*.php deploy\includes\validators\ >nul 2>&1
copy includes\templates\*.php deploy\includes\templates\ >nul 2>&1
xcopy includes\templates\email deploy\includes\templates\email /E /I /Q >nul 2>&1

REM Copy vendor (above public_html)
echo Copying vendor...
if exist "vendor" (
    xcopy vendor deploy\vendor /E /I /Q >nul 2>&1
)

REM Copy database schema (for reference)
echo Copying database schema...
mkdir deploy\database
copy database\schema.sql deploy\database\ >nul 2>&1

REM Copy setup-admin script (run once, then delete from server)
if exist "setup-admin.php" (
    echo Copying setup-admin.php...
    copy setup-admin.php deploy\public_html\ >nul 2>&1
)

REM Remove local-only files from deploy
echo Removing local-only files...
if exist "deploy\includes\config.local.php" del deploy\includes\config.local.php

REM Rename production config
if exist "deploy\includes\config.production.php" (
    echo Setting up production config...
    del deploy\includes\config.php 2>nul
    rename deploy\includes\config.production.php config.php
)

REM Patch admin/ and client/ paths for cPanel (../../includes instead of ../includes)
echo Patching include paths for cPanel structure...
powershell -Command "Get-ChildItem deploy\public_html\admin\*.php, deploy\public_html\client\*.php, deploy\public_html\client\api\*.php -ErrorAction SilentlyContinue | ForEach-Object { (Get-Content $_.FullName -Raw) -replace \"__DIR__ \. '/../includes/\", \"__DIR__ . '/../../includes/\" -replace '__DIR__ . ''/../includes/', '__DIR__ . ''/../../includes/' | Set-Content $_.FullName }"
REM Also patch vendor autoload references
powershell -Command "Get-ChildItem deploy\public_html\admin\*.php, deploy\public_html\client\*.php, deploy\public_html\client\api\*.php -ErrorAction SilentlyContinue | ForEach-Object { (Get-Content $_.FullName -Raw) -replace \"__DIR__ \. '/../vendor/\", \"__DIR__ . '/../../vendor/\" | Set-Content $_.FullName }"

echo.
echo ========================================
echo  Deployment package created!
echo ========================================
echo.
echo  Output: deploy\
echo.
echo  Upload instructions:
echo    1. Upload deploy\public_html\*  to  /home/user/public_html/
echo    2. Upload deploy\includes\      to  /home/user/includes/
echo    3. Upload deploy\vendor\        to  /home/user/vendor/
echo    4. Import deploy\database\schema.sql via phpMyAdmin
echo    5. Edit /home/user/includes/config.php with your DB credentials
echo.
pause
