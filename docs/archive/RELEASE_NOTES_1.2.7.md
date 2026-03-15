# MikroPlaneta Booking 1.2.7 — Release Notes

Release date: 2026-03-03

## Highlights

- Security hardening for temporary exports, iCal access and maintenance tooling.
- New configurable retention for backup and iCal files, plus automated cleanup scheduling.
- CAPTCHA flow improvements across providers: `none`, `recaptcha_v3`, `hcaptcha`.
- Booking widget UX improvements: stricter date validation, clearer feedback messages, and more consistent behavior.
- Improved release packaging validation for WordPress.org and CI-friendly JSON output.

## Backend & Security

- Tightened access and handling around temporary file endpoints and iCal generation.
- Improved runtime safety around maintenance and migration operations.
- Retention options wired end-to-end (settings API, cron scheduling, cleanup logic).

## Frontend (Public Widgets)

- Unified and simplified message rendering in full and simple widgets.
- Better invalid date-range handling before availability/submission operations.
- Improved bed suggestion feedback consistency (`info` messaging for suggestions/auto-selection).

## Quality & Testing

- Integration test suite stabilized and expanded for key booking/CAPTCHA/cron scenarios.
- Current integration status: `OK (21 tests, 88 assertions)`.

## Packaging / Release

- Release script validated from plugin root in dry-run and full mode.
- Latest validated artifacts:
  - `releases/mikro-booking-1.2.7-20260303-231515.zip`
  - `releases/mikro-booking-1.2.7-20260303-231515.report.txt`
  - `releases/mikro-booking-1.2.7-20260303-231515.json`

## Upgrade Notes

- Recommended for all installs due to combined security, reliability and UX consistency improvements.
- No manual migration step is required for standard upgrades.

## Post-release Update (2026-03-04)

- Admin `Settings` UX cleanup:
  - Cron test actions moved to dedicated **Narzędzia Cron** block (outside payment controls).
  - Notification history section kept but collapsed by default for better scanability.
- Pricing settings organization:
  - Bed price multipliers moved from `Settings` into `Pricing` view where they are now edited and saved.
- Admin asset cache-busting fix:
  - `core/class-admin.php` now versions `assets/admin/index.js` and `index.css` via `filemtime()` in all environments.
  - Prevents stale admin bundles when `WP_DEBUG` is disabled.
- QA and repository hygiene:
  - Added [QA_CHECKLIST_1.2.7.md](QA_CHECKLIST_1.2.7.md) for manual verification before release.
  - Added ignore rules for local generated artifacts (`.phpunit.result.cache`, `releases/`) and stopped tracking PHPUnit cache file.

### Commit Summary (pushed to `main`)

- `f70ed3d` — admin: reorder Settings cron tools and improve admin asset cache busting
- `0dcf58f` — docs: add QA checklist for 1.2.7 verification
- `c8c770b` — chore: ignore local test cache and release artifacts
- `4dd0243` — chore: stop tracking phpunit result cache

## Stability Sprint 1 Update (2026-03-04)

- Operational Health Check added in admin settings (`SMTP`, `WP-Cron`, `REST API`, temp storage permissions).
- Email delivery hardened with retry + backoff in notification flows.
- Cron idempotency improved with task-level locks to prevent concurrent duplicate executions.
- Integration tests expanded and green after update.

### Additional commits

- `a19aba3` — feat(settings): add operational health check diagnostics
- `32657fd` — feat(email): add retry with backoff for notification delivery
- `1e2191f` — feat(cron): add task locks to prevent concurrent executions