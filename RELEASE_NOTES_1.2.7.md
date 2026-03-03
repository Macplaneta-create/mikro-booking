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
