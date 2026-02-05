# 📝 TODO - MikroPlaneta Booking Development Roadmap

**Last Updated:** 2026-02-05  
**Status:** In Production (Ongoing Development)

---

## 🔴 CRITICAL (Blokují produkcję)

### ❌ 1. Pricing Engine
**Priority:** HIGH  
**Story:** "Recepcjonista chce ustawić różne ceny dla pokoi"  
**Current:** Hardcoded $100 za rezerwację

**Tasks:**
- [ ] Stworzyć `class-pricing-service.php` z logiką kalkulacji
- [ ] Tabela `wp_hotel_pricing` (room_id, date_range, base_price, weekend_price)
  - [ ] Schema (migration 010)
- [ ] Admin UI do zarządzania cenami
  - [ ] Nowa zakładka: "Cennik" w admin panelu
  - [ ] Calendar picker (check-in / check-out)
  - [ ] Price input dla weekday/weekend
- [ ] REST API endpoint: `GET /pricing/calculate`
  - [ ] Parameters: room_id, check_in, check_out
  - [ ] Response: { base_price, weekend_surcharge, total }
- [ ] Integration w ReservationService
- [ ] Tests: `test-pricing.php`

**Files to create/modify:**
```
core/services/class-pricing-service.php        (NEW)
core/repositories/class-pricing-repository.php (NEW)
core/database/migrations/010-create-pricing.php (NEW)
core/models/class-pricing.php                  (NEW)
rest-api/controllers/class-pricing-controller.php (NEW)
admin/src/components/PricingTab.tsx            (UPDATE)
tests/unit/test-pricing.php                    (NEW)
```

---

### ❌ 2. Settings Page (Admin)
**Priority:** HIGH  
**Current:** Placeholder template tylko

**Tasks:**
- [ ] Stworzyć `class-settings.php` service
- [ ] Zapisywać ustawienia w `wp_options`
  - [ ] Hotel name
  - [ ] Check-in/Check-out times
  - [ ] Currency
  - [ ] Email notifications (enabled/disabled)
  - [ ] SMS notifications (Twilio API key - ENCRYPTED!)
  - [ ] Timezone
  - [ ] Language

- [ ] Admin UI - Settings Tab
  - [ ] Form fields (wszystkie powyżej)
  - [ ] Save button + validation
  - [ ] Success/Error notifications

- [ ] REST API:
  - [ ] `GET /settings` - pobierz wszystkie
  - [ ] `PUT /settings` - zaktualizuj

**Files to create/modify:**
```
core/services/class-settings-service.php       (NEW)
rest-api/controllers/class-settings-controller.php (NEW)
admin/src/components/Settings.tsx              (UPDATE)
tests/integration/test-settings-api.php       (NEW)
```

---

### ❌ 3. Email Notifications (Critical path)
**Priority:** HIGH  
**Current:** Templates exist, ale wysyłka jest placeholder

**Tasks:**
- [ ] Integration test: Email sending
- [ ] Implementacja: `NotificationService::sendEmail()`
  - [ ] Use `wp_mail()` natywnie
  - [ ] HTML email template rendering
  - [ ] Test email button w Settings
  
- [ ] Email templates (w `/notifications/templates/email/`)
  - [x] reservation-confirmed.php (istnieje)
  - [x] reservation-changed.php (istnieje)
  - [x] reminder-24h.php (istnieje)
  - [ ] reservation-cancelled.php
  - [ ] check-in-reminder.php

- [ ] Scheduler: Cronjob dla 24h reminder
  - [ ] Hook: `wp_schedule_event()` w Activator
  - [ ] Callback: `mikrop_send_checkin_reminders()`

**Files:**
```
core/services/class-notification-service.php   (UPDATE)
core/class-scheduler.php                       (NEW)
notifications/templates/email/*                (UPDATE)
tests/integration/test-email-sending.php       (NEW)
```

---

## 🟠 HIGH PRIORITY (Potem zaraz)

### ❌ 4. Changes Logging
**Story:** "Chcę śledzić wszystkie zmiany w rezerwacjach"  
**Current:** @TODO in code

**Tasks:**
- [ ] Tabela `wp_hotel_changes_log`
  - [ ] Migration 011
  - [ ] Fields: id, reservation_id, changed_by, change_type, old_value, new_value, timestamp

- [ ] Auto-logging w ReservationService
  - [ ] Hook: przed każdą zmianą
  - [ ] Log: kto zmienił, co zmienił, kiedy

- [ ] Admin UI: "Change History" tab
  - [ ] Timeline view zmian
  - [ ] Filter by date, user, type

**Files:**
```
core/database/migrations/011-create-changes-log.php (NEW)
core/repositories/class-changelog-repository.php     (NEW)
core/models/class-changelog.php                      (NEW)
rest-api/controllers/class-changelog-controller.php  (NEW)
admin/src/components/ChangeHistory.tsx              (NEW)
core/services/class-changelog-service.php           (NEW)
```

---

### ❌ 5. AI Engine (Placeholder)
**Priority:** MEDIUM-HIGH  
**Story:** "System ma autom. przydzielać łóżka gościom"  
**Current:** `/ai/` folder jest, ale puste

**Tasks:**
- [ ] Bin Packing Algorithm (`ai/algorithms/class-bin-packing.php`)
  - [ ] Input: Request (guest count, dates), Available beds
  - [ ] Output: Optimal bed allocation + confidence score
  - [ ] Test: `test-bin-packing.php`

- [ ] Feedback System
  - [ ] Tabla: `wp_hotel_ai_feedback` (allocation_id, satisfaction_score, notes)
  - [ ] REST API: `POST /allocations/{id}/feedback`
  - [ ] Learn from feedback (future: ML model)

- [ ] Suggestion Component (React)
  - [ ] Show AI suggestion w booking form
  - [ ] Accept/Reject + tell why
  - [ ] Track feedback

**Files:**
```
ai/algorithms/class-bin-packing.php             (NEW)
ai/strategies/class-allocation-strategy.php     (NEW)
core/database/migrations/012-create-ai-feedback.php (NEW)
core/repositories/class-ai-feedback-repository.php  (NEW)
rest-api/controllers/class-ai-controller.php    (UPDATE)
admin/src/components/AISuggestion.tsx           (NEW)
tests/unit/test-bin-packing.php                 (NEW)
```

---

### ❌ 6. License Manager (Currently Placeholder)
**Priority:** MEDIUM  
**Current:** `class-license-manager.php` istnieje ale nie działa

**Tasks:**
- [ ] API Integration z `https://api.mikroplaneta.pl/verify` (to create)
  - [ ] POST /verify-license (key, domain, wp_version)
  - [ ] Response: {valid: bool, expires: date, features: []}

- [ ] Local license management
  - [ ] `wp_options`: mikroplaneta_booking_license (key, status, expires)
  - [ ] Auto-check: co 7 dni

- [ ] UI: License status w Settings
  - [ ] Input: Paste license key
  - [ ] Display: Status, Expiration, Features
  - [ ] Button: Check license now

- [ ] Dev Mode Bypass (already working)
  - [ ] `define('MIKROPLANETA_DEV_MODE', true)` w wp-config.php

**Files:**
```
core/class-license-manager.php                  (UPDATE)
rest-api/controllers/class-license-controller.php (NEW)
admin/src/components/LicenseStatus.tsx          (NEW)
docs/LICENSE.md                                 (NEW)
```

---

## 🟡 MEDIUM PRIORITY

### ❌ 7. Unit & Integration Tests
**Current:** /tests/ folder jest pusty

**Tasks:**
- [ ] Setup: PHPUnit configuration
  - [ ] `phpunit.xml.dist`
  - [ ] Bootstrap: `tests/bootstrap.php`
  - [ ] WP-specific: `WP_UnitTestCase`

- [ ] Unit Tests:
  - [ ] Availability checking (`test-availability.php`)
  - [ ] Bin packing algorithm (`test-bin-packing.php`)
  - [ ] Pricing calculation (`test-pricing.php`)
  - [ ] Validation services (`test-validation.php`)

- [ ] Integration Tests:
  - [ ] REST API endpoints (`test-api-endpoints.php`)
  - [ ] Database migrations (`test-migrations.php`)
  - [ ] Email sending (`test-email-sending.php`)
  - [ ] Permission checks (`test-permissions.php`)

- [ ] CI/CD: GitHub Actions
  - [ ] `.github/workflows/tests.yml`
  - [ ] Trigger: push, pull_request
  - [ ] Run: `composer test`

**Files:**
```
tests/bootstrap.php                             (NEW)
tests/unit/test-availability.php                (NEW)
tests/unit/test-bin-packing.php                 (NEW)
tests/unit/test-pricing.php                     (NEW)
tests/integration/test-api-endpoints.php        (NEW)
tests/integration/test-migrations.php           (NEW)
.github/workflows/tests.yml                     (NEW)
phpunit.xml.dist                                (NEW)
```

---

### ❌ 8. Code Quality & Standards
**Tasks:**
- [ ] PHPCS (WordPress coding standard)
  - [ ] Install: `composer require --dev squizlabs/php_codesniffer`
  - [ ] Config: `phpcs.xml`
  - [ ] Run: `composer phpcs` to find issues
  - [ ] Fix: `phpcbf --standard=WordPress`

- [ ] Type Safety (TypeScript)
  - [ ] Remove all `any` types
  - [ ] Add proper interfaces
  - [ ] Enable `strict: true`全域

- [ ] Linting (ESLint)
  - [ ] Config: `.eslintrc.cjs`
  - [ ] Rules: strict, no unused variables
  - [ ] Pre-commit: lint before push

- [ ] Pre-commit hooks
  - [ ] `.husky/` - format code before commit
  - [ ] Prevent bad commits

**Files:**
```
phpcs.xml                                       (UPDATE)
.eslintrc.cjs                                   (NEW)
admin/.eslintrc.cjs                             (NEW)
.husky/pre-commit                               (NEW)
composer.json                                   (UPDATE - add scripts)
admin/package.json                              (UPDATE - add lint script)
```

---

### ❌ 9. Documentation Tasks
**Tasks:**
- [ ] API Documentation (OpenAPI/Swagger)
  - [ ] File: `docs/openapi.yaml`
  - [ ] All endpoints documented
  - [ ] Example requests/responses
  - [ ] Auto-generate: swagger-ui

- [ ] Setup Guide for developers
  - [ ] `docs/SETUP.md`
  - [ ] Local development
  - [ ] Database setup
  - [ ] Testing locally

- [ ] Architecture Decisions (ADR)
  - [ ] `docs/adr/001-rest-api-design.md`
  - [ ] `docs/adr/002-database-schema.md`
  - [ ] `docs/adr/003-frontend-framework.md`

- [ ] Inline code documentation
  - [ ] JSDoc for React components
  - [ ] PHPDoc for classes
  - [ ] README for each module

**Files:**
```
docs/openapi.yaml                               (NEW)
docs/SETUP.md                                   (NEW)
docs/adr/001-rest-api-design.md                 (NEW)
```

---

## 🟢 LOW PRIORITY (Later)

### ❌ 10. Performance Optimization
- [ ] Database indexing (`KEY idx_check_in`)
- [ ] Query optimization (JOIN optimize, N+1 queries)
- [ ] Caching layer (Redis/Memcached)
- [ ] API rate limiting
- [ ] Bundle size optimization (React)

### ❌ 11. Advanced Features
- [ ] Multi-property support
- [ ] Guest self-check-in kiosk
- [ ] Integration: Booking.com, Airbnb
- [ ] Payment gateway (Stripe/PayPal)
- [ ] Analytics dashboard
- [ ] Reporting (Excel export)

### ❌ 12. UX Improvements
- [ ] Dark mode theme
- [ ] Mobile app (React Native)
- [ ] Accessibility (WCAG 2.1 AA)
- [ ] Multi-language (i18n)
- [ ] Custom branding (logo upload)

---

## 📊 Completion Status

| Category | Total | Done | % |
|----------|-------|------|---|
| **Critical** | 6 | 1 | 17% |
| **High Priority** | 3 | 0 | 0% |
| **Medium** | 2 | 0 | 0% |
| **Low** | 3 | 0 | 0% |
| **TOTAL** | 14 | 1 | 7% |

---

## 🚀 Suggested Sprint Sequence

**Sprint 8 (1-2 weeks):**
- [x] Setup testing framework
- [ ] Pricing Engine
- [ ] Settings Page

**Sprint 9 (1-2 weeks):**
- [ ] Email Notifications
- [ ] Changes Logging
- [ ] Scheduler setup

**Sprint 10 (2-3 weeks):**
- [ ] AI Engine (Bin Packing)
- [ ] Feedback system
- [ ] Unit tests

**Sprint 11 (Ongoing):**
- [ ] Code quality
- [ ] Documentation
- [ ] Performance optimization

---

## 💡 Tips for Development

1. **Before starting task:**
   ```bash
   git checkout -b feature/task-name
   ```

2. **Code formatting:**
   ```bash
   phpcbf --standard=WordPress core/
   npm run lint --fix  # w admin folder
   ```

3. **Testing locally:**
   ```bash
   composer test
   npm run test  # jeśli będą unit tests React
   ```

4. **After committing:**
   - Update this file
   - Mark task as done: `[x]`
   - Note completion date

---

## 🎯 Next 30 Days Goals

**Week 1-2:** Pricing Engine + Settings  
**Week 3:** Email + Logging  
**Week 4:** Testing framework + Initial tests  

**By end of March:** Core features 60% complete

---

**Last Updated:** 2026-02-05  
**Next Review:** 2026-02-12
