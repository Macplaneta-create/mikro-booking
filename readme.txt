=== MikroPlaneta Booking ===
Contributors: mikroplaneta
Tags: booking, hotel, reservations, calendar, rooms
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 8.0
Stable tag: 1.2.8
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Advanced hotel booking system with room/bed management, pricing rules, public booking widget and admin dashboard.

== Description ==

MikroPlaneta Booking adds a complete hotel reservation workflow to WordPress:

* Room and bed management
* Availability calendar
* Reservation lifecycle (pending, confirmed, check-in, check-out, cancelled)
* Pricing rules and weekend pricing
* Extra services
* Public booking form with CAPTCHA support
* Daily backup/export tools for administrators

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Open **Booking** in WordPress admin to configure rooms, beds and settings.
4. Optionally add booking widget shortcode on the frontend.

== Frequently Asked Questions ==

= How do I show the booking form on frontend? =

Use shortcode:

`[mikroplaneta_booking]`

For room-specific context:

`[mikroplaneta_booking room_id="123"]`

= Who can manage reservations in admin? =

Users with `manage_options` capability.

== Changelog ==

= 1.2.8 =
* Publiczny shortcode kalendarza dostępności miesięcznej (`[mikroplaneta_availability_calendar]`) z CTA `Rezerwuj`.
* Usprawnienia UX w Ustawieniach (czytelniejsze opisy i sekcja shortcode) oraz poprawki przycisków `Kopiuj`.
* Ulepszone komunikaty testów Cron/SMTP i dopracowanie liczników dashboardu.
* Aktualizacja checklist QA/release oraz runbook GO/NO-GO dla szybszej decyzji publikacji.

= 1.2.7 =
* Security hardening for temporary exports, iCal delivery and maintenance endpoints.
* New retention settings for backup and iCal files with scheduled cleanup cron.
* CAPTCHA provider improvements (none/reCAPTCHA v3/hCaptcha) with frontend/backend alignment.
* Public booking widgets: better date-range validation, unified user messages and UX consistency fixes.
* Integration test stability improvements and expanded regression coverage.
* WordPress.org packaging and release pipeline validation improvements.

== Upgrade Notice ==

= 1.2.8 =
Public release with monthly availability calendar, admin UX/message improvements and release workflow updates.

= 1.2.7 =
Security, reliability and UX consistency update recommended for all installations.
