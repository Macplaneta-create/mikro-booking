# 🚀 QUICK START - MikroPlaneta Booking Development

**Setup lokalny + Development workflow**

---

## ⚡ 5-Minute Setup

### 1. Zainstaluj dependencies

```bash
# Root plugin folder
cd /path/to/mikro-booking
composer install
cd admin && npm install
npm run build
cd ../..
```

### 2. Aktywuj wtyczkę

```bash
# Via WordPress admin
# lub via WP-CLI:
wp plugin activate mikroplaneta-booking
```

### 3. Sprawdzić status

```bash
# Sprawdzić czy tabele się utworzyły
wp db query "SHOW TABLES LIKE 'wp_hotel%';" 

# Sprawdzić logi
tail -f wp-content/debug.log
```

**✅ Gotowe! Panel admin:** `http://yoursite.local/wp-admin/?page=mikroplaneta-booking`

---

## 📁 Struktura katalogów (co gdzie)

```
mikro-booking/
├── core/                          # Backend logic
│   ├── services/                 # Business logic (Pricing, Reservation, etc.)
│   ├── repositories/             # Database access layer
│   ├── models/                   # Data structures (Room, Bed, Reservation)
│   └── database/                 # Migrations
│
├── rest-api/                      # REST API endpoints
│   ├── controllers/              # API request handlers
│   └── routes.php                # Route registration
│
├── public/                        # Frontend guest features (shortcodes)
│
├── admin/src/                     # React admin panel (TypeScript)
│   ├── components/               # UI components
│   ├── services/                 # API calls
│   ├── hooks/                    # Custom React hooks
│   ├── types/                    # TypeScript types
│   └── utils/                    # Helpers
│
├── assets/                        # Built frontend (index.js, index.css)
│
└── tests/                         # Unit & integration tests
```

---

## 🔄 Development Workflow

### Step 1: Pick a task from TODO.md

```bash
git checkout -b feature/pricing-engine
```

### Step 2: Code

**Backend (PHP):**
```bash
# Create new file or update existing service
# Example: core/services/class-pricing-service.php
vim core/services/class-pricing-service.php

# Format code
phpcbf --standard=WordPress core/services/class-pricing-service.php
```

**Frontend (React):**
```bash
# In admin/ folder
cd admin/
vim src/components/PricingTab.tsx

# Dev mode with hot reload
npm run dev

# When done - build
npm run build
```

### Step 3: Test locally

```bash
# Check if everything works
wp db query "SELECT COUNT(*) FROM wp_hotel_rooms;"

# Check REST API
curl -X GET http://yoursite.local/wp-json/mikroplaneta/v1/rooms \
  -H "Authorization: Bearer YOUR_NONCE" \
  -H "Content-Type: application/json"
```

### Step 4: Commit

```bash
git add .
git commit -m "feat: add pricing engine

- Create PricingService class
- Add pricing database table
- Update ReservationService to use pricing
- Add admin UI component
- Tests: all pricing calculations pass

Closes #42"
```

### Step 5: Update TODO.md

Mark task as done:
```markdown
- [x] Pricing Engine  ✅ 2026-02-10
```

---

## 🛠️ Common Commands

### Database

```bash
# Check plugin tables
wp db tables --scope=plugin

# Query debug
wp db query "SELECT * FROM wp_hotel_rooms LIMIT 1;"

# Reset database (⚠️ DANGEROUS)
wp db reset
wp plugin activate mikroplaneta-booking
```

### REST API Debugging

```bash
# Get admin nonce
NONCE=$(wp eval 'echo wp_create_nonce("wp_rest");')

# Test endpoint
curl -X GET http://yoursite.local/wp-json/mikroplaneta/v1/rooms \
  -H "Authorization: Bearer $NONCE" \
  -H "X-WP-Nonce: $NONCE"

# Create test data
wp eval 'do_action("rest_api_init");' 
# then POST to create room
```

### React Development

```bash
cd admin/

# Development with hot reload
npm run dev

# Build for production
npm run build

# Type checking
npm run type-check

# Linting
npm run lint
```

### PHP Code Quality

```bash
# Check WordPress coding standards
phpcbf --standard=WordPress --report=summary .

# Auto-fix
phpcbf --standard=WordPress .
```

---

## 📝 File Naming Conventions

**PHP:**
```
class-service-name.php          # Services
class-repository.php            # Repositories  
class-model-name.php            # Models
interface-name.php              # Interfaces
001-migration-name.php          # Migrations (numbered)
```

**React/TypeScript:**
```
ComponentName.tsx               # Components
useHookName.ts                  # Custom hooks
serviceName.ts                  # Services/API calls
componentName.module.css        # Styles
types.ts                        # Type definitions
```

---

## 🧪 Testing

### Run tests

```bash
# All tests
composer test

# Specific test
composer test tests/unit/test-pricing.php

# Watch mode
composer test -- --watch
```

### Write a test

```php
<?php
// tests/unit/test-pricing.php

class Test_Pricing extends WP_UnitTestCase {
    
    public function test_pricing_calculation() {
        $service = new PricingService();
        
        $price = $service->calculate([
            'room_id' => 1,
            'check_in' => '2026-02-15',
            'check_out' => '2026-02-17',  // 2 nights
        ]);
        
        $this->assertEquals(200, $price);  // $100/night
    }
}
```

---

## 🐛 Debugging

### Enable debug mode

```php
// wp-config.php (local only!)
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);

// Check logs:
tail -f wp-content/debug.log
```

### Frontend debugging

```javascript
// In React components
console.log('Data:', data);  // Chrome DevTools

// TypeScript error checking
npm run type-check
```

### REST API debugging

```bash
# Test with verbose output
curl -v -X POST http://yoursite.local/wp-json/mikroplaneta/v1/reservations \
  -H "Content-Type: application/json" \
  -d '{"guest_id": 1, "bed_id": 1, "check_in": "2026-02-15", "check_out": "2026-02-17"}'
```

---

## 📌 Priority Tasks (Next 2 weeks)

```
[ ] 1. Pricing Engine
    - core/services/class-pricing-service.php
    - Migration 010
    - REST API /pricing/calculate
    - Admin UI

[ ] 2. Settings Page
    - core/services/class-settings-service.php
    - WordPress options storage
    - Settings tab in admin

[ ] 3. Email Notifications
    - NotificationService implementation
    - wp_mail() integration
    - Template rendering

[ ] 4. Unit Tests Setup
    - PHPUnit configuration
    - Bootstrap.php
    - First 3 test suites
```

---

## 🤝 Git Workflow

```bash
# Create feature branch
git checkout -b feature/pricing-engine

# Make changes
vim core/services/class-pricing-service.php
cd admin && npm run build && cd ..

# Commit with conventional commits
git add .
git commit -m "feat(pricing): implement pricing calculator

- Create PricingService with rate calculation
- Add database schema for pricing rules
- Implement REST endpoint for price calculation
- Add admin UI for price management

Relates to #12"

# Push to remote
git push origin feature/pricing-engine

# Create PR on GitHub
# Wait for review
# Merge after approval
```

---

## 📞 Need Help?

### Common Issues

**Problem:** REST API returns 404
```bash
# Solution: Re-flush permalinks
wp rewrite flush
wp plugin deactivate mikroplaneta-booking
wp plugin activate mikroplaneta-booking
```

**Problem:** Database error on activation
```bash
# Check PHP version
php -v

# Check MySQL version
wp db query "SELECT VERSION();"

# Run migrations manually
wp eval 'do_action("plugins_loaded");'
```

**Problem:** React won't compile
```bash
# Check Node version
node -v  # Should be 18+

# Clear npm cache
npm cache clean --force
rm -rf node_modules package-lock.json
npm install
npm run build
```

---

## ✅ Checklist Before Submitting PR

- [ ] Code follows WordPress coding standard (`phpcbf`)
- [ ] TypeScript compiles without errors (`npm run type-check`)
- [ ] Tests pass (`composer test` / `npm test`)
- [ ] No console.error or console.log in production code
- [ ] Database migrations are idempotent
- [ ] Documentation updated (code comments)
- [ ] TODO.md updated with task status
- [ ] Commit message follows conventional commits

---

**Happy coding! 🎉**

For more: See `TODO.md`, `ARCHITECTURE.md`, `API.md`
