@echo off
REM ============================================================================
REM MikroPlaneta Booking - Security Cleanup Script (Windows)
REM 
REM Ten skrypt:
REM 1. Usuwa pliki debugowe i testowe
REM 2. Blokuje dostęp do .git
REM 3. Tworzy .htaccess z zabezpieczeniami
REM 4. Sprawdza czy build istnieje
REM
REM Usage:
REM   security-cleanup.bat
REM ============================================================================

setlocal enabledelayedexpansion

REM Colors (ANSI escape codes)
set "BLUE=[94m"
set "GREEN=[92m"
set "YELLOW=[93m"
set "RED=[91m"
set "NC=[0m"

echo.
echo ╔══════════════════════════════════════════════════════════╗
echo ║   MikroPlaneta Booking - Security Cleanup               ║
echo ╚══════════════════════════════════════════════════════════╝
echo.

REM Krok 1: Usuwanie plików debugowych
echo %BLUE%ℹ️  Step 1: Removing debug and test files...%NC%

set REMOVED_COUNT=0

if exist "debug-beds.php" (del "debug-beds.php" & set /a REMOVED_COUNT+=1 & echo   Removed: debug-beds.php)
if exist "debug-db-v2.php" (del "debug-db-v2.php" & set /a REMOVED_COUNT+=1 & echo   Removed: debug-db-v2.php)
if exist "debug-db.php" (del "debug-db.php" & set /a REMOVED_COUNT+=1 & echo   Removed: debug-db.php)
if exist "debug-pricing.php" (del "debug-pricing.php" & set /a REMOVED_COUNT+=1 & echo   Removed: debug-pricing.php)
if exist "test-api.php" (del "test-api.php" & set /a REMOVED_COUNT+=1 & echo   Removed: test-api.php)
if exist "test-create-pricing.php" (del "test-create-pricing.php" & set /a REMOVED_COUNT+=1 & echo   Removed: test-create-pricing.php)
if exist "test-get-pricing.php" (del "test-get-pricing.php" & set /a REMOVED_COUNT+=1 & echo   Removed: test-get-pricing.php)
if exist "test-group-pricing.php" (del "test-group-pricing.php" & set /a REMOVED_COUNT+=1 & echo   Removed: test-group-pricing.php)
if exist "test-insert.php" (del "test-insert.php" & set /a REMOVED_COUNT+=1 & echo   Removed: test-insert.php)
if exist "test-update.php" (del "test-update.php" & set /a REMOVED_COUNT+=1 & echo   Removed: test-update.php)
if exist "debug-log.txt" (del "debug-log.txt" & set /a REMOVED_COUNT+=1 & echo   Removed: debug-log.txt)
if exist "debug-output.txt" (del "debug-output.txt" & set /a REMOVED_COUNT+=1 & echo   Removed: debug-output.txt)

echo %GREEN%✅ Removed %REMOVED_COUNT% debug/test files%NC%
echo.

REM Krok 2: Sprawdzenie buildu React
echo %BLUE%ℹ️  Step 2: Checking React build...%NC%

if not exist "assets\admin\index.js" (
    echo %RED%❌ React build not found!%NC%
    echo %BLUE%ℹ️  Run: cd admin ^&^& npm run build%NC%
    pause
    exit /b 1
)

if not exist "assets\admin\index.css" (
    echo %RED%❌ React build not found!%NC%
    echo %BLUE%ℹ️  Run: cd admin ^&^& npm run build%NC%
    pause
    exit /b 1
)

echo %GREEN%✅ React build found%NC%
echo.

REM Krok 3: Tworzenie .htaccess z zabezpieczeniami
echo %BLUE%ℹ️  Step 3: Creating security .htaccess...%NC%

(
echo # MikroPlaneta Booking - Security Rules
echo.
echo # Block access to .git
echo ^<IfModule mod_alias.c^>
echo     RedirectMatch 403 /\.git
echo ^</IfModule^>
echo.
echo # Block access to sensitive files
echo ^<FilesMatch "^(composer\.json|composer\.lock|package\.json|package-lock\.json|\.env|\.gitignore)$"^>
echo     Order allow,deny
echo     Deny from all
echo ^</FilesMatch^>
echo.
echo # Block access to log files
echo ^<FilesMatch "\.(log|txt)$"^>
echo     Order allow,deny
echo     Deny from all
echo ^</FilesMatch^>
echo.
echo # Block access to PHP files in assets
echo ^<FilesMatch "^.*\.php$"^>
echo     Order allow,deny
echo     Deny from all
echo ^</FilesMatch^>
echo.
echo # Security headers
echo ^<IfModule mod_headers.c^>
echo     Header set X-Content-Type-Options "nosniff"
echo     Header set X-Frame-Options "SAMEORIGIN"
echo     Header set X-XSS-Protection "1; mode=block"
echo ^</IfModule^>
echo.
echo # Disable directory browsing
echo Options -Indexes
echo.
echo # PHP settings ^(if mod_php is available^)
echo ^<IfModule mod_php7.c^>
echo     php_flag display_errors Off
echo     php_flag log_errors On
echo ^</IfModule^>
) > .htaccess

echo %GREEN%✅ Security .htaccess created%NC%
echo.

REM Krok 4: Podsumowanie
echo.
echo %GREEN%✅ Security cleanup complete!%NC%
echo.
echo ╔══════════════════════════════════════════════════════════╗
echo ║                    Summary                               ║
echo ╠══════════════════════════════════════════════════════════╣
echo ║  ✅ Removed debug/test files: %REMOVED_COUNT%                      ║
echo ║  ✅ Created security .htaccess                           ║
echo ║  ✅ Checked React build                                  ║
echo ║                                                          ║
echo ║  ⚠️  TODO before production:                             ║
echo ║     - Implement real CAPTCHA                             ║
echo ║     - Add rate limiting                                  ║
echo ║     - Change logging to error_log()                      ║
echo ╚══════════════════════════════════════════════════════════╝
echo.

pause
