# MikroPlaneta Booking - Architecture Documentation

## Overview
Professional WordPress booking plugin with group reservation support, built with modern architecture patterns and best practices.

## Tech Stack

### Backend
- **PHP 8.0+** - Modern PHP with strict typing
- **WordPress 6.0+** - Latest WordPress APIs
- **MySQL 8.0+** - Relational database with foreign keys

### Frontend
- **React 18** - Modern UI framework
- **TypeScript** - Type-safe JavaScript
- **Vite** - Fast build tool
- **date-fns** - Date manipulation
- **lucide-react** - Icon library

## Architecture Patterns

### 1. Repository Pattern
All data access goes through repository classes:
```
Controller → Service → Repository → Database
```

**Benefits:**
- Separation of concerns
- Easy to test
- Swappable data sources
- Consistent API

### 2. Service Layer
Business logic is isolated in service classes:
- `ReservationService` - Reservation management
- `AvailabilityService` - Bed availability checks
- `PricingService` - Price calculations

### 3. Model Layer
Plain PHP objects (POPOs) represent entities:
- `Reservation`
- `Guest`
- `Room`
- `Bed`
- `ReservationBed` (junction)

## Database Schema

### Core Tables

#### `reservations`
```sql
id              BIGINT UNSIGNED PRIMARY KEY
guest_id        BIGINT UNSIGNED NOT NULL
check_in        DATE NOT NULL
check_out       DATE NOT NULL
status          ENUM('pending', 'confirmed', 'checked_in', 'checked_out', 'cancelled')
adults          INT DEFAULT 1
children        INT DEFAULT 0
total_price     DECIMAL(10,2)
notes           TEXT
created_by      BIGINT UNSIGNED
created_at      DATETIME
updated_at      DATETIME
```

#### `reservation_beds` (Junction Table)
```sql
id              BIGINT UNSIGNED PRIMARY KEY
reservation_id  BIGINT UNSIGNED NOT NULL
bed_id          BIGINT UNSIGNED NOT NULL
created_at      DATETIME

UNIQUE (reservation_id, bed_id)
```

**Design Decision:** 
- Removed `bed_id` from `reservations` table
- All bed assignments go through `reservation_beds`
- Enables group reservations natively
- Cleaner, more scalable architecture

### Migration System

Migrations are numbered and run in order:
```
001-create-rooms.php
002-create-beds.php
...
011-create-reservation-beds.php
012-remove-bed-id-from-reservations.php
```

**Running Migrations:**
1. Via Admin: `Booking → Migrations`
2. Programmatically: `$db->migrate()`
3. On plugin activation (automatic)

## REST API

### Endpoint Structure
```
/wp-json/mikroplaneta/v1/
├── rooms/
├── beds/
├── reservations/
├── guests/
└── availability/
```

### Creating a Reservation

**Request:**
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

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 456,
    "guest_id": 123,
    "bed_ids": [1, 2, 3],
    "check_in": "2026-03-10",
    "check_out": "2026-03-13",
    "status": "confirmed",
    "total_price": 450.00,
    ...
  }
}
```

**Backend Flow:**
1. `ReservationsController::create_item()`
2. `ReservationService::createReservation()`
   - Validates guest exists
   - Validates all beds exist and are active
   - Checks availability for each bed
   - Calculates total price (sum of all beds)
3. `ReservationRepository::create()` - Creates reservation
4. `ReservationBedRepository::setBedsForReservation()` - Links beds
5. Fires `mikroplaneta_booking_reservation_created` action

## Frontend Architecture

### Component Structure
```
src/
├── components/
│   ├── CalendarView.tsx       # Main calendar with multi-select
│   ├── ReservationModal.tsx   # Booking form
│   ├── GuestsView.tsx         # Guest management
│   ├── RoomManager.tsx        # Room & Bed management
│   └── ...
├── services/
│   └── api.ts                 # API client
└── App.tsx                    # Main app router
```

### State Management
- **React Hooks** - Local component state
- **No global state** - Keep it simple
- **API as source of truth** - Fetch on mount

### Multi-Bed Selection

**User Flow:**
1. Click arrival date on any bed
2. Click departure date on same bed
3. **Ctrl+Click** other beds to add to group
4. Click "ZAREZERWUJ" button
5. Complete guest details
6. Submit → Creates one reservation with multiple beds

**Implementation:**
```typescript
const [selectedBeds, setSelectedBeds] = useState<Set<number>>(new Set());

const handleCellClick = (bedId, roomId, date, event) => {
  if (event.ctrlKey) {
    // Toggle bed in selection
    const newSet = new Set(selectedBeds);
    newSet.has(bedId) ? newSet.delete(bedId) : newSet.add(bedId);
    setSelectedBeds(newSet);
  } else {
    // Normal date selection
    // ...
  }
};
```

## WordPress Integration

### Plugin Structure
```
mikro-booking/
├── mikroplaneta-booking.php   # Main plugin file
├── core/
│   ├── class-plugin.php       # Plugin bootstrap
│   ├── class-admin.php        # Admin menu
│   ├── models/                # Data models
│   ├── repositories/          # Data access
│   ├── services/              # Business logic
│   └── database/              # Migrations
├── rest-api/
│   ├── routes.php             # Route registration
│   └── controllers/           # API controllers
└── admin/
    ├── src/                   # React source
    └── dist/                  # Built assets
```

### Hooks & Filters

**Actions:**
```php
do_action('mikroplaneta_booking_reservation_created', $reservation, $bed_ids);
do_action('mikroplaneta_booking_reservation_cancelled', $reservation);
```

**Filters:**
```php
apply_filters('mikroplaneta_booking_price_calculation', $price, $bed_id, $dates);
```

### Room & Bed Types
The plugin handles mapping between frontend UI types and database ENUMs:

**Mapping Logic (`RoomRepository`):**
- Frontend `private` → Database `standard`
- Frontend `dorm` → Database `dormitory`

**Database ENUMs:**
- `rooms.room_type`: `'standard'`, `'deluxe'`, `'suite'`, `'dormitory'`
- `beds.bed_type`: `'single'`, `'double'`, `'bunk'`

## Security

### Authentication
- WordPress nonce verification
- `current_user_can('manage_options')` checks
- REST API nonce in headers

### Data Validation
- Type hints on all methods
- Input sanitization
- SQL prepared statements
- Foreign key constraints

### SQL Injection Prevention
```php
// ✅ Good - Prepared statement
$wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id);
```

## Troubleshooting

### Common Issues

**"bed_ids required" error:**
- Frontend must always send `bed_ids` as array
- Even single bed: `bed_ids: [123]`

**Room Save Error:**
- Check invalid `room_type`. The system automatically normalizes `private` to `standard` and `dorm` to `dormitory`.
- Check database logs for missing columns.
- Use the "Force Update" tool in Migrations page if tables are missing.

**Migration fails:**
- Check MySQL user has ALTER TABLE permissions
- Review error in `debug-log.txt`
- Run migrations manually via admin page or via `admin-post.php?action=mikroplaneta_force_update`

---

*Last updated: 2026-02-22*
*Version: 1.1.2 (Architecture Cleanup)*
