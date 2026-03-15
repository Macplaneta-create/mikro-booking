# MikroPlaneta Booking — Architecture Documentation

## Overview

Professional WordPress booking plugin with group reservation support, built with modern architecture patterns and best practices.

**Current version:** 1.2.7  
**Last updated:** 2026-03-15

---

## Tech Stack

### Backend
- **PHP 8.0+** — Modern PHP with strict typing and namespaces
- **WordPress 6.0+** — Latest WordPress APIs (nonces, options, cron, hooks)
- **MySQL 8.0+** — Relational database with foreign keys

### Frontend (Admin)
- **React 18** — Modern UI framework
- **TypeScript** — Type-safe JavaScript
- **Vite** — Fast build tool
- **date-fns** — Date manipulation
- **lucide-react** — Icon library

### Frontend (Public)
- PHP shortcodes rendered as server-side HTML with light vanilla JS
- `[mikro_booking]` — full booking form (`class-frontend.php`)
- `[mikro_room_card]` — single room card (`class-room-card-shortcode.php`)

---

## Architecture Patterns

### 1. Repository Pattern

All data access goes through repository classes:

```
Controller → Service → Repository → Database
```

Benefits: separation of concerns, testability, consistent API.

### 2. Service Layer

Business logic is isolated in service classes. Controllers never touch repositories directly.

### 3. Model Layer

Plain PHP objects (POPOs) representing entities. No ORM dependency.

---

## Directory Structure

```
mikro-booking/
├── mikroplaneta-booking.php        # Main plugin file, constants, bootstrap call
├── core/
│   ├── class-plugin.php            # Boots services, hooks, REST
│   ├── class-admin.php             # Admin menu, assets
│   ├── class-activator.php         # Activation hooks
│   ├── class-deactivator.php       # Deactivation hooks
│   ├── class-cron-handler.php      # WP-Cron jobs (expiry, retry)
│   ├── class-consent-handler.php   # GDPR consent handling
│   ├── class-logging-handler.php   # Low-level log writer
│   ├── class-rest-rate-limiter.php # Per-IP rate limiting for REST
│   ├── models/                     # POPOs (Room, Bed, BedPlace, Guest, Reservation,
│   │                               #   ReservationBed, ReservationExtra, ExtraService, Pricing)
│   ├── repositories/               # Data access layer (one class per entity)
│   ├── services/                   # Business logic layer
│   └── database/
│       ├── class-database.php      # Migration runner
│       ├── class-schema.php        # Schema helpers
│       └── migrations/             # 001–024 sequential SQL migrations
├── rest-api/
│   ├── routes.php                  # Wires repos → services → controllers, registers routes
│   ├── controllers/                # One controller per resource
│   └── middleware/                 # Rate limiter middleware
├── public/
│   ├── class-frontend.php          # [mikro_booking] shortcode
│   ├── class-frontend-simple.php   # Lightweight booking form variant
│   └── class-room-card-shortcode.php  # [mikro_room_card] shortcode
├── admin/
│   ├── src/                        # React + TypeScript source
│   │   ├── App.tsx                 # Main router / tab switcher
│   │   ├── components/
│   │   │   ├── CalendarView.tsx    # Main booking calendar (multi-bed select)
│   │   │   ├── ReservationModal.tsx
│   │   │   ├── BookingHistory.tsx
│   │   │   ├── GuestsView.tsx
│   │   │   ├── RoomManager.tsx
│   │   │   ├── DashboardContent.tsx
│   │   │   ├── PricingView.tsx
│   │   │   ├── ExtrasManager.tsx
│   │   │   ├── Settings.tsx        # Google Calendar BYOK, notifications, payments
│   │   │   └── Onboarding.tsx      # First-run wizard overlay
│   │   └── services/
│   │       └── api.ts
│   └── dist/                       # Built assets (committed)
├── tools/
│   ├── force-repair-db.php         # One-off DB repair script
│   ├── force-update.php            # Force migration re-run
│   └── optimize-dashboard.php      # Dashboard query optimizer
├── assets/                         # Public static assets
├── docs/                           # Extended docs and setup guides
│   ├── API.md
│   ├── DATABASE.md
│   ├── DEVELOPMENT.md
│   ├── GOOGLE_CALENDAR_SETUP.md
│   └── archive/                    # Historical release notes
├── tests/
│   ├── bootstrap.php
│   └── integration/
└── vendor/                         # Composer dependencies (PHPUnit, etc.)
```

---

## Services

| Class | Responsibility |
|---|---|
| `ReservationService` | Create, update, cancel reservations; fires WP actions |
| `AvailabilityService` | Check bed availability for a date range |
| `PricingService` | Calculate prices (per-bed, rules, weekends) |
| `GuestService` | Guest CRUD + reservation history |
| `NotificationService` | Send booking confirmation / cancellation emails |
| `LoggerService` | Write structured change entries to DB |
| `BackupService` | CSV/SQL export of booking data |
| `ExtraServiceService` | Manage add-ons attached to reservations |
| `ReservationExpiryService` | Auto-expire pending reservations via cron |
| `ICalService` | Generate `.ics` feed for guests |
| `GoogleCalendarService` | OAuth 2.0 BYOK — sync reservations to owner's Google Calendar |

---

## Repositories

| Class | Table |
|---|---|
| `RoomRepository` | `rooms` |
| `BedRepository` | `beds` |
| `BedPlaceRepository` | `bed_places` |
| `GuestRepository` | `guests` |
| `ReservationRepository` | `reservations` |
| `ReservationBedRepository` | `reservation_beds` |
| `ReservationExtraRepository` | `reservation_extras` |
| `ExtraServiceRepository` | `extra_services` |
| `PricingRepository` | `pricing_rules` |
| `ChangesLogRepository` | `changes_log` |

---

## REST API

**Namespace:** `/wp-json/mikroplaneta/v1/`

| Resource | Controller | Auth |
|---|---|---|
| `/rooms` | `RoomsController` | admin nonce |
| `/reservations` | `ReservationsController` | admin nonce |
| `/reservations/public` | `PublicReservationsController` | rate-limited, nonce |
| `/guests` | `GuestsController` | admin nonce |
| `/availability` | `AvailabilityController` | public |
| `/pricing` | `PricingController` | admin nonce |
| `/dashboard` | `DashboardController` | admin nonce |
| `/settings` | `SettingsController` | admin nonce |
| `/logs` | `LogsController` | admin nonce |
| `/extras` | `ExtrasController` | admin nonce |
| `/backup` | `BackupController` | admin nonce |
| `/gcal` | `GoogleCalendarController` | admin nonce |
### Creating a Reservation (admin)

```json
POST /wp-json/mikroplaneta/v1/reservations
{
  "guest_id": 123,
  "bed_ids": [1, 2, 3],
  "check_in": "2026-03-10",
  "check_out": "2026-03-13",
  "adults": 4,
  "children": 2,
  "notes": "Group booking"
}
```

**Backend flow:**
1. `ReservationsController::create_item()`
2. `ReservationService::createReservation()` — validates guest, beds, availability; calculates price
3. `ReservationRepository::create()` — inserts reservation row
4. `ReservationBedRepository::setBedsForReservation()` — links beds
5. `NotificationService` — sends confirmation email
6. `do_action('mikroplaneta_booking_reservation_created', $reservation, $bed_ids)`

---

## Database Schema

### Core Tables

#### `reservations`
```sql
id              BIGINT UNSIGNED PRIMARY KEY
guest_id        BIGINT UNSIGNED NOT NULL
check_in        DATE NOT NULL
check_out       DATE NOT NULL
status          ENUM('pending','confirmed','checked_in','checked_out','cancelled')
adults          INT DEFAULT 1
children        INT DEFAULT 0
total_price     DECIMAL(10,2)
notes           TEXT
created_by      BIGINT UNSIGNED
created_at      DATETIME
updated_at      DATETIME
```

#### `reservation_beds` (junction)
```sql
id              BIGINT UNSIGNED PRIMARY KEY
reservation_id  BIGINT UNSIGNED NOT NULL
bed_id          BIGINT UNSIGNED NOT NULL
UNIQUE (reservation_id, bed_id)
```

**Design decision:** all bed assignments go through `reservation_beds`; the `reservations` table has no `bed_id` column. Enables group reservations natively.

### Migration History

Migrations run sequentially on activation and via the admin Migrations page:

```
001 create-rooms
002 create-beds
003 create-guests
004 create-reservations
005 create-allocations-log
006 create-notifications
007 create-changes-log
008 create-ai-suggestions
009 add-adults-children
010 create-pricing
011 create-reservation-beds
012 remove-bed-id-from-reservations
013 create-extra-services
014 create-reservation-extras
015 add-room-details
016 add-pricing-mode-to-rooms
017 create-bed-places
018 add-pricing-rules-scopes
019 add-studio-room-type
020 add-name-to-pricing-rules
021 add-weekend-range-to-pricing-rules
022 add-cabin-room-type
023 normalize-double-bed-place-capacity
024 add-payment-settings
```

---

## Frontend — Admin

### Component Responsibilities

| Component | Role |
|---|---|
| `App.tsx` | Tab router, auth guard, Onboarding overlay trigger |
| `CalendarView.tsx` + `calendar/` | Main booking grid, multi-bed Ctrl+Click selection |
| `ReservationModal.tsx` | Booking form, guest lookup, extras |
| `BookingHistory.tsx` | Past reservations list + filters |
| `GuestsView.tsx` | Guest CRUD |
| `RoomManager.tsx` | Room and bed management |
| `DashboardContent.tsx` | KPI cards, occupancy chart |
| `PricingView.tsx` | Pricing rules editor |
| `ExtrasManager.tsx` | Add-on services management |
| `Settings.tsx` | Notifications, Google Calendar OAuth, payment settings |
| `Onboarding.tsx` | 3-step first-run wizard, saves `onboarding_completed` flag |

### Multi-Bed Selection Flow

1. Click arrival date on a bed
2. Click departure date on the same bed
3. **Ctrl+Click** additional beds to add to group
4. Click "ZAREZERWUJ"
5. Fill guest details → submit → one reservation with multiple beds

### State Management

- React Hooks for local component state
- No global state manager
- API as source of truth (fetch on mount / on action)

---

## WordPress Integration

### Hooks & Filters

**Actions fired by the plugin:**
```php
do_action('mikroplaneta_booking_reservation_created',  $reservation, $bed_ids);
do_action('mikroplaneta_booking_reservation_updated',  $reservation);
do_action('mikroplaneta_booking_reservation_cancelled', $reservation);
```

**Filters:**
```php
apply_filters('mikroplaneta_booking_price_calculation', $price, $bed_id, $dates);
```

### Room & Bed Type Mapping

Frontend sends human-readable types; repository normalises before persisting:

| Frontend value | Database ENUM |
|---|---|
| `private` | `standard` |
| `dorm` | `dormitory` |

**Database ENUMs:**
- `rooms.room_type`: `standard`, `deluxe`, `suite`, `dormitory`, `studio`, `cabin`
- `beds.bed_type`: `single`, `double`, `bunk`

---

## Security

- WordPress nonce verification on every authenticated endpoint
- `current_user_can('manage_options')` checks in admin controllers
- Per-IP rate limiting on public endpoints (`class-rest-rate-limiter.php`)
- Input sanitisation and type hints throughout
- Prepared statements via `$wpdb->prepare()` — no raw interpolation
- Foreign key constraints enforce referential integrity
- Google Calendar tokens stored encrypted in `wp_options`

---

## Troubleshooting

**`bed_ids required` error:** frontend must always send `bed_ids` as an array, even for single beds: `"bed_ids": [123]`.

**Room save error:** the system auto-normalises `private → standard` and `dorm → dormitory`. If tables are missing columns, run migrations via `Booking → Migrations` or use `tools/force-update.php`.

**Migration fails:** check that the DB user has `ALTER TABLE` permissions. Review errors in WP debug log. Run `tools/force-repair-db.php` as a last resort.

**PHP fatal — undefined constant `MIKROPLANETA_BOOKING_FILE`:** the correct constant is `MIKROPLANETA_BOOKING_PLUGIN_FILE` (defined in `mikroplaneta-booking.php`). Check `class-plugin.php` `load_textdomain()` call if this occurs.

**Google Calendar OAuth callback fails:** confirm `MIKROPLANETA_GCAL_CLIENT_ID` and `MIKROPLANETA_GCAL_CLIENT_SECRET` are set (via `wp-config.php` or Settings), and that the redirect URI in Google Cloud Console matches `<site_url>/wp-json/mikroplaneta/v1/gcal/callback`.
