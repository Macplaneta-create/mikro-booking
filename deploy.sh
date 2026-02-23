#!/bin/bash

################################################################################
# MikroPlaneta Booking - Deploy Script
# 
# Usage:
#   ./deploy.sh [environment]
# 
# Environments:
#   production  - Deploy to production server (default)
#   staging     - Deploy to staging server
#   local       - Build only for local testing
#
# Example:
#   ./deploy.sh production
################################################################################

set -e  # Exit on error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
PLUGIN_NAME="mikro-booking"
ADMIN_DIR="admin"
ASSETS_DIR="assets/admin"

# Server configurations (edit these!)
declare -A SERVERS=(
    ["production"]="user@your-production-server.com:/path/to/wp-content/plugins/mikro-booking"
    ["staging"]="user@staging-server.com:/path/to/wp-content/plugins/mikro-booking"
)

# Files to exclude from deployment
EXCLUDE=(
    ".git"
    ".github"
    "node_modules"
    "vendor"
    "admin/node_modules"
    "admin/src"
    "admin/.parcel-cache"
    "admin/dist"
    "tests"
    "*.log"
    "debug-*.php"
    "test-*.php"
    "force-*.php"
    "run-migration-*.php"
    ".env"
    ".env.local"
    "*.md"
    "!README.md"
    "!DEVELOPMENT.md"
    ".DS_Store"
    "Thumbs.db"
)

################################################################################
# Functions
################################################################################

log_info() {
    echo -e "${BLUE}ℹ️  $1${NC}"
}

log_success() {
    echo -e "${GREEN}✅ $1${NC}"
}

log_warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

log_error() {
    echo -e "${RED}❌ $1${NC}"
}

check_dependencies() {
    log_info "Checking dependencies..."
    
    if ! command -v npm &> /dev/null; then
        log_error "npm is not installed. Please install Node.js first."
        exit 1
    fi
    
    if ! command -v composer &> /dev/null; then
        log_error "composer is not installed. Please install Composer first."
        exit 1
    fi
    
    if ! command -v rsync &> /dev/null; then
        log_warning "rsync is not installed. Will use scp instead."
        USE_RSYNC=false
    else
        USE_RSYNC=true
    fi
    
    log_success "Dependencies check passed"
}

build_frontend() {
    log_info "Building React frontend..."
    
    cd "$ADMIN_DIR"
    
    # Install dependencies if needed
    if [ ! -d "node_modules" ]; then
        log_info "Installing npm dependencies..."
        npm install
    fi
    
    # Build
    log_info "Running npm run build..."
    npm run build
    
    cd ..
    
    # Verify build
    if [ ! -f "$ASSETS_DIR/index.js" ] || [ ! -f "$ASSETS_DIR/index.css" ]; then
        log_error "Build failed! Missing build artifacts."
        exit 1
    fi
    
    log_success "Frontend built successfully"
    log_info "  - $ASSETS_DIR/index.js ($(du -h "$ASSETS_DIR/index.js" | cut -f1))"
    log_info "  - $ASSETS_DIR/index.css ($(du -h "$ASSETS_DIR/index.css" | cut -f1))"
}

build_backend() {
    log_info "Installing PHP dependencies..."
    
    if [ ! -d "vendor" ]; then
        composer install --no-dev --optimize-autoloader
    else
        log_info "Vendor directory exists, skipping composer install"
    fi
    
    log_success "Backend ready"
}

create_deploy_package() {
    log_info "Creating deployment package..."
    
    TIMESTAMP=$(date +%Y%m%d_%H%M%S)
    PACKAGE_NAME="${PLUGIN_NAME}-${TIMESTAMP}.zip"
    
    # Create .deployignore file for rsync
    cat > .deployignore << 'EOF'
.git/
.github/
node_modules/
vendor/
admin/node_modules/
admin/src/
admin/.parcel-cache/
admin/dist/
tests/
*.log
debug-*.php
test-*.php
force-*.php
run-migration-*.php
.env
.env.local
.DS_Store
Thumbs.db
*.zip
EOF
    
    log_success "Deployment package created: $PACKAGE_NAME"
}

deploy_to_server() {
    local ENV=$1
    local SERVER=${SERVERS[$ENV]}
    
    if [ -z "$SERVER" ]; then
        log_error "Server configuration not found for environment: $ENV"
        log_info "Available environments: ${!SERVERS[@]}"
        exit 1
    fi
    
    log_info "Deploying to $ENV server..."
    log_info "Server: $SERVER"
    
    if [ "$USE_RSYNC" = true ]; then
        # Build exclude list
        EXCLUDE_ARGS=""
        for pattern in "${EXCLUDE[@]}"; do
            if [[ $pattern == "!"* ]]; then
                # Include pattern (remove !)
                EXCLUDE_ARGS="$EXCLUDE_ARGS --include=${pattern:1}"
            else
                EXCLUDE_ARGS="$EXCLUDE_ARGS --exclude=$pattern"
            fi
        done
        
        rsync -avz --delete \
            --exclude='.git/' \
            --exclude='node_modules/' \
            --exclude='vendor/' \
            --exclude='admin/node_modules/' \
            --exclude='admin/src/' \
            --exclude='*.log' \
            --exclude='debug-*.php' \
            --exclude='test-*.php' \
            --exclude='force-*.php' \
            --exclude='run-migration-*.php' \
            --exclude='.env' \
            --exclude='.deployignore' \
            ./ "$SERVER"
    else
        # Fallback to scp
        log_warning "rsync not available, using scp..."
        
        # Create temporary zip
        TEMP_DIR=$(mktemp -d)
        DEPLOY_DIR="$TEMP_DIR/$PLUGIN_NAME"
        mkdir -p "$DEPLOY_DIR"
        
        # Copy files (excluding unwanted)
        rsync -av \
            --exclude='.git/' \
            --exclude='node_modules/' \
            --exclude='vendor/' \
            --exclude='admin/node_modules/' \
            --exclude='admin/src/' \
            --exclude='*.log' \
            --exclude='debug-*.php' \
            --exclude='test-*.php' \
            --exclude='force-*.php' \
            --exclude='run-migration-*.php' \
            --exclude='.env' \
            ./ "$DEPLOY_DIR/"
        
        # Create zip
        cd "$TEMP_DIR"
        zip -r "$PACKAGE_NAME" "$PLUGIN_NAME"
        cd -
        
        # Upload
        scp "$TEMP_DIR/$PACKAGE_NAME" "$SERVER:/tmp/"
        
        # Extract on server
        ssh "${SERVER%%:*}" << EOF
cd $(echo $SERVER | cut -d: -f2)
unzip -o /tmp/$PACKAGE_NAME
rm /tmp/$PACKAGE_NAME
EOF
        
        # Cleanup
        rm -rf "$TEMP_DIR"
    fi
    
    log_success "Deployed to $ENV successfully!"
}

deploy_local() {
    log_info "Building for local testing..."
    
    # Just build, don't deploy anywhere
    log_success "Local build complete!"
    log_info "Files are in: $ASSETS_DIR/"
}

cleanup() {
    log_info "Cleaning up..."
    
    # Remove temporary files
    rm -f .deployignore
    rm -f *.zip
    
    log_success "Cleanup complete"
}

show_help() {
    echo "MikroPlaneta Booking - Deploy Script"
    echo ""
    echo "Usage: $0 [environment]"
    echo ""
    echo "Environments:"
    echo "  production  - Deploy to production server"
    echo "  staging     - Deploy to staging server"
    echo "  local       - Build only for local testing"
    echo ""
    echo "Examples:"
    echo "  $0 production"
    echo "  $0 staging"
    echo "  $0 local"
    echo ""
}

################################################################################
# Main
################################################################################

main() {
    local ENV="${1:-local}"
    
    echo ""
    echo "╔══════════════════════════════════════════════════════════╗"
    echo "║   MikroPlaneta Booking - Deploy Script                  ║"
    echo "╚══════════════════════════════════════════════════════════╝"
    echo ""
    
    case "$ENV" in
        "production"|"staging")
            check_dependencies
            build_frontend
            build_backend
            create_deploy_package
            deploy_to_server "$ENV"
            cleanup
            ;;
        "local")
            check_dependencies
            build_frontend
            deploy_local
            ;;
        "help"|"-h"|"--help")
            show_help
            ;;
        *)
            log_error "Unknown environment: $ENV"
            show_help
            exit 1
            ;;
    esac
    
    echo ""
    log_success "🎉 Deployment complete!"
    echo ""
}

# Run main function
main "$@"
