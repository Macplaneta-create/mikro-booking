#!/bin/bash

################################################################################
# MikroPlaneta Booking - Security & Cleanup Script
# 
# Ten skrypt:
# 1. Usuwa pliki debugowe i testowe
# 2. Blokuje dostęp do .git
# 3. Tworzy .htaccess z zabezpieczeniami
# 4. Sprawdza czy build istnieje
#
# Usage:
#   ./security-cleanup.sh
################################################################################

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

log_info() { echo -e "${BLUE}ℹ️  $1${NC}"; }
log_success() { echo -e "${GREEN}✅ $1${NC}"; }
log_warning() { echo -e "${YELLOW}⚠️  $1${NC}"; }
log_error() { echo -e "${RED}❌ $1${NC}"; }

echo ""
echo "╔══════════════════════════════════════════════════════════╗"
echo "║   MikroPlaneta Booking - Security Cleanup               ║"
echo "╚══════════════════════════════════════════════════════════╝"
echo ""

# Krok 1: Usuwanie plików debugowych
log_info "Step 1: Removing debug and test files..."

DEBUG_FILES=(
    "debug-beds.php"
    "debug-db-v2.php"
    "debug-db.php"
    "debug-pricing.php"
    "test-api.php"
    "test-create-pricing.php"
    "test-get-pricing.php"
    "test-group-pricing.php"
    "test-insert.php"
    "test-update.php"
    "debug-log.txt"
    "debug-output.txt"
)

REMOVED_COUNT=0
for file in "${DEBUG_FILES[@]}"; do
    if [ -f "$file" ]; then
        rm "$file"
        log_info "  Removed: $file"
        ((REMOVED_COUNT++))
    fi
done

log_success "Removed $REMOVED_COUNT debug/test files"

# Krok 2: Sprawdzenie buildu React
log_info "Step 2: Checking React build..."

if [ ! -f "assets/admin/index.js" ] || [ ! -f "assets/admin/index.css" ]; then
    log_error "React build not found!"
    log_info "Run: cd admin && npm run build"
    exit 1
fi

JS_SIZE=$(du -h "assets/admin/index.js" | cut -f1)
CSS_SIZE=$(du -h "assets/admin/index.css" | cut -f1)

log_success "React build found (JS: $JS_SIZE, CSS: $CSS_SIZE)"

# Krok 3: Tworzenie .htaccess z zabezpieczeniami
log_info "Step 3: Creating security .htaccess..."

cat > .htaccess << 'EOF'
# MikroPlaneta Booking - Security Rules

# Block access to .git
<IfModule mod_alias.c>
    RedirectMatch 403 /\.git
</IfModule>

# Block access to sensitive files
<FilesMatch "^(composer\.json|composer\.lock|package\.json|package-lock\.json|\.env|\.gitignore)$">
    Order allow,deny
    Deny from all
</FilesMatch>

# Block access to log files
<FilesMatch "\.(log|txt)$">
    Order allow,deny
    Deny from all
</FilesMatch>

# Block access to PHP files in assets
<FilesMatch "^.*\.php$">
    Order allow,deny
    Deny from all
</FilesMatch>

# Security headers
<IfModule mod_headers.c>
    Header set X-Content-Type-Options "nosniff"
    Header set X-Frame-Options "SAMEORIGIN"
    Header set X-XSS-Protection "1; mode=block"
</IfModule>

# Disable directory browsing
Options -Indexes

# PHP settings (if mod_php is available)
<IfModule mod_php7.c>
    php_flag display_errors Off
    php_flag log_errors On
</IfModule>
EOF

log_success "Security .htaccess created"

# Krok 4: Tworzenie .gitignore dla produkcji
log_info "Step 4: Updating .gitignore..."

if ! grep -q "debug-\*.php" .gitignore; then
    cat >> .gitignore << 'EOF'

# Security cleanup
debug-*.php
test-*.php
*.log
EOF
    log_success ".gitignore updated"
else
    log_info ".gitignore already has security rules"
fi

# Krok 5: Sprawdzenie uprawnień
log_info "Step 5: Checking file permissions..."

# PHP files should be 644
find . -name "*.php" -type f -exec chmod 644 {} \; 2>/dev/null || true

# Directories should be 755
find . -type d -exec chmod 755 {} \; 2>/dev/null || true

log_success "File permissions set"

# Krok 6: Podsumowanie
echo ""
log_success "Security cleanup complete!"
echo ""
echo "╔══════════════════════════════════════════════════════════╗"
echo "║                    Summary                               ║"
echo "╠══════════════════════════════════════════════════════════╣"
echo "║  ✅ Removed debug/test files: $REMOVED_COUNT                      ║"
echo "║  ✅ Created security .htaccess                           ║"
echo "║  ✅ Updated .gitignore                                   ║"
echo "║  ✅ Set file permissions                                 ║"
echo "║                                                          ║"
echo "║  ⚠️  TODO before production:                             ║"
echo "║     - Implement real CAPTCHA                             ║"
echo "║     - Add rate limiting                                  ║"
echo "║     - Change logging to error_log()                      ║"
echo "╚══════════════════════════════════════════════════════════╝"
echo ""
