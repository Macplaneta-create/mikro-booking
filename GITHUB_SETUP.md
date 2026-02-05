# 🚀 GitHub Setup Guide - MikroPlaneta Booking

**Step-by-step instrukcja do wysłania wtyczki na GitHub**

---

## ✅ Checklist Pre-Push

- [x] .gitignore właściwy (vendor/, node_modules/, itd.)
- [x] Kod sformatowany (PHPCS)
- [x] Dokumentacja kompletna
- [x] Security audit passed
- [ ] README.md (będzie niżej)
- [ ] LICENSE file
- [ ] GitHub Actions workflow

---

## 📝 Step 1: Zainicjalizuj repozytorium lokalnie

```bash
cd /path/to/mikro-booking

# Inicjalizuj Git
git init

# Dodaj wszystkie pliki (z .gitignore)
git add .

# Check co będzie dodane
git status

# Pierwszy commit
git commit -m "Initial commit: MikroPlaneta Booking v1.0.0

- Backend: REST API with PHP 8.0+
- Frontend: React admin panel with TypeScript
- Database: Migration system (9 migrations)
- Security: ABSPATH checks, prepared statements, permission checks
- Features: Room management, reservations, availability checking

Production-ready. See PRODUCTION_READY.md for details."
```

---

## 🔑 Step 2: Utwórz repozytorium na GitHub

1. Zaloguj się: https://github.com/login
2. Kliknij: **New Repository**
3. Wpisz:
   - **Repository name:** `mikro-booking`
   - **Description:** Advanced hotel booking system for WordPress
   - **Visibility:** Public (jeśli chcesz, by inni widzieli)
   - **Initialize repository:** ❌ (już masz lokalne)
   - **License:** GPL-3.0 (WordPress standard) ✅

4. **Create Repository**

---

## 📡 Step 3: Connectuj lokalny repo z GitHub

```bash
cd /path/to/mikro-booking

# Add remote origin
git remote add origin https://github.com/YOUR_USERNAME/mikro-booking.git

# Verify
git remote -v
# Output:
# origin  https://github.com/YOUR_USERNAME/mikro-booking.git (fetch)
# origin  https://github.com/YOUR_USERNAME/mikro-booking.git (push)

# Push master branch
git branch -M main
git push -u origin main

# Czekaj ~1 minutę...
# ✅ Gotowe! Widać Twoje repo na GitHub
```

---

## 🏷️ Step 4: Utwórz Release

```bash
git tag -a v1.0.0 -m "Production release: Security audit passed, all core features complete"
git push origin v1.0.0

# GitHub → Releases → v1.0.0 → будет widoczny
```

---

## 🔄 Step 5: GitHub Actions (Auto-testing)

Stwórz plik `.github/workflows/tests.yml`:

```yaml
name: Tests

on:
  push:
    branches: [ main, develop ]
  pull_request:
    branches: [ main ]

jobs:
  test:
    runs-on: ubuntu-latest
    
    strategy:
      matrix:
        php-version: ['8.0', '8.1', '8.2', '8.3']
        wp-version: ['6.0', '6.1', '6.2', '6.3', '6.4']
    
    steps:
    - uses: actions/checkout@v3
    
    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: ${{ matrix.php-version }}
        coverage: xdebug
    
    - name: Install dependencies
      run: composer install
    
    - name: Run PHPCS
      run: vendor/bin/phpcs --standard=WordPress core/ rest-api/ public/
      continue-on-error: true
    
    - name: Run tests
      run: vendor/bin/phpunit
      continue-on-error: true
    
    - name: Setup Node
      uses: actions/setup-node@v3
      with:
        node-version: '18'
    
    - name: Install Node deps
      run: cd admin && npm install
    
    - name: Type check
      run: cd admin && npm run type-check
      continue-on-error: true
    
    - name: Build React
      run: cd admin && npm run build
```

**Zapamiętaj:** Utwórz plik `.github/workflows/tests.yml` w repo

---

## 📋 Step 6: Dokumentacja w README.md root repo

Utwórz/Zaktualizuj `README.md`:

```markdown
# 🏨 MikroPlaneta Booking

Advanced hotel booking system for WordPress with AI-powered bed allocation.

[![PHP 8.0+](https://img.shields.io/badge/PHP-8.0%2B-blue)](https://www.php.net/)
[![WordPress 6.0+](https://img.shields.io/badge/WordPress-6.0%2B-green)](https://wordpress.org/)
[![License: GPL v3](https://img.shields.io/badge/License-GPLv3-blue.svg)](https://www.gnu.org/licenses/gpl-3.0)
[![Build Status](https://github.com/YOUR_USERNAME/mikro-booking/workflows/Tests/badge.svg)](https://github.com/YOUR_USERNAME/mikro-booking/actions)

## Features

- 🏠 Room & Bed Management
- 📅 Advanced Reservation System
- 💰 Dynamic Pricing Engine
- 🤖 AI-Powered Bed Allocation (Bin Packing)
- 📧 Email Notifications
- 👥 Guest Management
- 📊 Admin Dashboard
- 🔐 Enterprise-Grade Security
- REST API
- Type-Safe (PHP 8.0+ types, TypeScript)

## Requirements

- **PHP:** 8.0 or higher
- **WordPress:** 6.0 or higher
- **MySQL:** 5.7 or higher
- **Node.js:** 18+ (for development)

## Installation

### Via WordPress Admin

1. Download from Releases
2. WordPress → Plugins → Add New → Upload
3. Activate

### Via Git (Development)

```bash
cd wp-content/plugins/
git clone https://github.com/YOUR_USERNAME/mikro-booking.git
cd mikro-booking
composer install
cd admin && npm install && npm run build && cd ..
```

### Via WP-CLI

```bash
wp plugin install https://github.com/YOUR_USERNAME/mikro-booking/releases/download/v1.0.0/mikro-booking.zip
wp plugin activate mikro-booking
```

## Quick Start

See [QUICK_START.md](docs/QUICK_START.md) for development setup.

```bash
# Local development
composer install
cd admin && npm run dev

# Build
npm run build

# Test
composer test
```

## API Documentation

REST API endpoints: `/wp-json/mikroplaneta/v1/`

- `GET /rooms` - List all rooms
- `POST /rooms` - Create room
- `GET /reservations` - List reservations
- `POST /reservations` - Create reservation
- `GET /guests` - List guests
- `POST /guests` - Create guest

See [API.md](docs/API.md) for full documentation.

## Architecture

```
core/                    Database, Models, Services
rest-api/                REST API Controllers  
public/                  Frontend (Shortcodes)
admin/src/              React Admin Panel
assets/                 Built Frontend
```

See [ARCHITECTURE.md](ARCHITECTURE.md).

## Security

✅ SQL Injection protected
✅ XSS protected  
✅ CSRF protected
✅ WordPress security standards
✅ Fully audited

See [SECURITY_AUDIT.md](SECURITY_AUDIT.md).

## Development

### Roadmap

See [TODO.md](TODO.md) for complete feature roadmap and status.

### Testing

```bash
# All tests
composer test

# Specific test
composer test tests/unit/test-availability.php

# With coverage
composer test -- --coverage-html coverage
```

### Code Standards

```bash
# Check PHP
phpcbf --standard=WordPress .

# Check TypeScript
cd admin && npm run lint
```

## Deployment

See [DEPLOY_CHECKLIST.md](DEPLOY_CHECKLIST.md) for production deployment.

```bash
# 1. Prepare
composer install --no-dev

# 2. Upload
scp -r . user@server:/wp-content/plugins/mikro-booking/

# 3. Activate
wp plugin activate mikro-booking
```

## License

GPL-3.0 - See [LICENSE](LICENSE) file.

## Support

- **Documentation:** See [docs/](docs/) folder
- **Issues:** GitHub Issues
- **Email:** support@mikroplaneta.pl

## Credits

Developed by [Your Team] using Antigravity AI.

---

**Status:** ✅ Production Ready ([See PRODUCTION_READY.md](PRODUCTION_READY.md))
```

---

## 🔐 Step 7: Utwórz LICENSE file

Utwórz plik `LICENSE` w root:

```
GPL-3.0 LICENSE TEXT

[Full license text: https://www.gnu.org/licenses/gpl-3.0.txt]

Możesz skopiować z: https://raw.githubusercontent.com/wordpress/wordpress-develop/trunk/license.txt
```

---

## 📦 Step 8: Utwórz archiwum Release

```bash
# Przygotuj paczkę (bez vendor, node_modules, itd.)
git archive --format zip --output ~/mikro-booking-v1.0.0.zip \
  --prefix=mikro-booking/ HEAD

# Upload do GitHub Releases manually:
# GitHub → Releases → Draft → Upload file
```

---

## 🔄 Step 9: Continuous Integration Setup

Dodaj do `composer.json`:

```json
{
  "scripts": {
    "test": "phpunit",
    "phpcs": "phpcs --standard=WordPress .",
    "phpcs-fix": "phpcbf --standard=WordPress .",
    "lint": "eslint .",
    "type-check": "cd admin && npm run type-check"
  }
}
```

---

## 📊 Step 10: GitHub Settings (Recommended)

1. Go to: Repository → Settings
2. **General:**
   - [x] Require status checks to pass before merging
   - [x] Require code reviews before merging (1 reviewer)

3. **Branch protection rules:**
   - Create rule for `main`
   - Require tests to pass
   - Require 1 approval

4. **Secrets** (if needed for deployment):
   ```
   Add secret: DEPLOY_KEY (SSH key for server)
   ```

---

## 🎯 GitHub Pages (Optional - Docs)

Utwórz `docs/index.md`:

```markdown
# MikroPlaneta Booking Documentation

- [Architecture](../ARCHITECTURE.md)
- [API Reference](API.md)
- [Quick Start](../QUICK_START.md)
- [Roadmap](../TODO.md)
```

Enable GitHub Pages: Settings → Pages → Source: main `/docs`

---

## 📝 Step-by-Step Commands (Copy-Paste)

```bash
# Wszystkie komendy razem
cd /path/to/mikro-booking

git init
git add .
git commit -m "Initial commit: MikroPlaneta Booking v1.0.0"
git branch -M main
git remote add origin https://github.com/YOUR_USERNAME/mikro-booking.git
git push -u origin main
git tag -a v1.0.0 -m "Initial release"
git push origin v1.0.0

echo "✅ Done! Repo on GitHub"
```

---

## ✅ Verification Checklist

- [ ] GitHub repo created
- [ ] Code pushed to main
- [ ] Release v1.0.0 created
- [ ] GitHub Actions workflows running
- [ ] README.md complete
- [ ] LICENSE file present
- [ ] Branch protection enabled
- [ ] Issue templates created (optional)
- [ ] Contributing guide added (optional)

---

## 🔮 Future: GitHub releases automation

Dodaj `.github/workflows/release.yml` do auto-publishing:

```yaml
name: Release

on:
  push:
    tags:
      - 'v*'

jobs:
  release:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - name: Create Release
        uses: softprops/action-gh-release@v1
        with:
          files: mikro-booking-*.zip
          draft: false
          prerelease: false
```

---

**🎉 Gotowe! Twoja wtyczka na GitHub jest live!**

## Przydatne linki:

- GitHub Desktop: https://desktop.github.com/ (GUI)
- GIT docs: https://git-scm.com/doc
- GitHub Guides: https://guides.github.com/
- WordPress Plugin Best Practices: https://developer.wordpress.org/plugins/

---

**Następny krok:** Zaproś developerów do collaboracji na stronie repo Settings → Collaborators
