@echo off
REM ============================================================================
REM MikroPlaneta Booking - Deploy Script (Windows)
REM 
REM Usage:
REM   deploy.bat [environment]
REM 
REM Environments:
REM   production  - Deploy to production server (default)
REM   staging     - Deploy to staging server
REM   local       - Build only for local testing
REM
REM Example:
REM   deploy.bat production
REM ============================================================================

setlocal enabledelayedexpansion

REM Configuration
set PLUGIN_NAME=mikro-booking
set ADMIN_DIR=admin
set ASSETS_DIR=assets\admin

REM Colors (ANSI escape codes)
set "BLUE=[94m"
set "GREEN=[92m"
set "YELLOW=[93m"
set "RED=[91m"
set "NC=[0m"

REM ============================================================================
REM Functions
REM ============================================================================

:log_info
echo %BLUE%ℹ️  %~1%NC%
goto :eof

:log_success
echo %GREEN%✅ %~1%NC%
goto :eof

:log_warning
echo %YELLOW%⚠️  %~1%NC%
goto :eof

:log_error
echo %RED%❌ %~1%NC%
goto :eof

:check_dependencies
echo.
%log_info% Checking dependencies...

REM Check npm
where npm >nul 2>nul
if %errorlevel% neq 0 (
    %log_error% npm is not installed. Please install Node.js first.
    exit /b 1
)

REM Check composer
where composer >nul 2>nul
if %errorlevel% neq 0 (
    %log_error% composer is not installed. Please install Composer first.
    exit /b 1
)

%log_success% Dependencies check passed
goto :eof

:build_frontend
echo.
%log_info% Building React frontend...

cd %ADMIN_DIR%

REM Install dependencies if needed
if not exist "node_modules" (
    %log_info% Installing npm dependencies...
    call npm install
)

REM Build
%log_info% Running npm run build...
call npm run build

if %errorlevel% neq 0 (
    %log_error% Build failed!
    cd ..
    exit /b 1
)

cd ..

REM Verify build
if not exist "%ASSETS_DIR%\index.js" (
    %log_error% Build failed! Missing index.js
    exit /b 1
)

if not exist "%ASSETS_DIR%\index.css" (
    %log_error% Build failed! Missing index.css
    exit /b 1
)

%log_success% Frontend built successfully

REM Show file sizes
for %%A in ("%ASSETS_DIR%\index.js") do set JS_SIZE=%%~zA
for %%A in ("%ASSETS_DIR%\index.css") do set CSS_SIZE=%%~zA

%log_info%   - %ASSETS_DIR%\index.js (!JS_SIZE! bytes^)
%log_info%   - %ASSETS_DIR%\index.css (!CSS_SIZE! bytes^)

goto :eof

:build_backend
echo.
%log_info% Installing PHP dependencies...

if not exist "vendor" (
    call composer install --no-dev --optimize-autoloader
) else (
    %log_info% Vendor directory exists, skipping composer install
)

%log_success% Backend ready
goto :eof

:deploy_local
echo.
%log_info% Building for local testing...

%log_success% Local build complete!
%log_info% Files are in: %ASSETS_DIR%\

goto :eof

:deploy_production
echo.
%log_info% Preparing production deployment...

REM For production, we'll create a clean copy
set DEPLOY_TEMP=%TEMP%\%PLUGIN_NAME%_deploy

if exist "%DEPLOY_TEMP%" rmdir /s /q "%DEPLOY_TEMP%"
mkdir "%DEPLOY_TEMP%"

REM Copy files (excluding unwanted)
%log_info% Copying files to temp directory...

robocopy . "%DEPLOY_TEMP%\%PLUGIN_NAME%" /E /XD ^
    .git .github node_modules vendor admin\node_modules admin\src ^
    admin\.parcel-cache admin\dist tests ^
    /XF *.log debug-*.php test-*.php force-*.php run-migration-*.php .env .env.local *.zip ^
    /NFL /NDL /NJH /NJS

if %errorlevel% geq 8 (
    %log_error% Failed to copy files
    exit /b 1
)

REM Create zip
%log_info% Creating deployment package...

cd "%DEPLOY_TEMP%"
powershell -Command "Compress-Archive -Path '%PLUGIN_NAME%' -DestinationPath '%PLUGIN_NAME%_production.zip' -Force"
cd ..

%log_success% Deployment package created: %DEPLOY_TEMP%\%PLUGIN_NAME%_production.zip

echo.
%log_info% Manual steps required:
echo   1. Upload %DEPLOY_TEMP%\%PLUGIN_NAME%_production.zip to your server
echo   2. Extract: unzip %PLUGIN_NAME%_production.zip -d wp-content/plugins/
echo   3. On server: cd wp-content/plugins/%PLUGIN_NAME% ^&^& composer install --no-dev
echo   4. Set permissions: chmod -R 755 . ^&^& find . -name '*.php' -exec chmod 644 {} \;

goto :eof

:cleanup
echo.
%log_info% Cleaning up...

REM Remove temporary files
if exist ".deployignore" del /q .deployignore
if exist "*.zip" del /q *.zip

%log_success% Cleanup complete
goto :eof

:show_help
echo.
echo MikroPlaneta Booking - Deploy Script
echo.
echo Usage: deploy.bat [environment]
echo.
echo Environments:
echo   production  - Deploy to production server
echo   staging     - Deploy to staging server
echo   local       - Build only for local testing
echo.
echo Examples:
echo   deploy.bat production
echo   deploy.bat staging
echo   deploy.bat local
echo.
goto :eof

REM ============================================================================
REM Main
REM ============================================================================

echo.
echo ╔══════════════════════════════════════════════════════════╗
echo ║   MikroPlaneta Booking - Deploy Script                  ║
echo ╚══════════════════════════════════════════════════════════╝

set ENV=%~1
if "%ENV%"=="" set ENV=local

if "%ENV%"=="production" (
    call :check_dependencies
    call :build_frontend
    call :build_backend
    call :deploy_production
    call :cleanup
) else if "%ENV%"=="staging" (
    call :check_dependencies
    call :build_frontend
    call :build_backend
    call :deploy_production
    call :cleanup
) else if "%ENV%"=="local" (
    call :check_dependencies
    call :build_frontend
    call :deploy_local
) else if "%ENV%"=="help" (
    call :show_help
) else (
    %log_error% Unknown environment: %ENV%
    call :show_help
    exit /b 1
)

echo.
%log_success% 🎉 Deployment complete!
echo.

endlocal
