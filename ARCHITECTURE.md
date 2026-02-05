# 🏗️ MikroPlaneta Booking - Architektura Systemu

**Wersja:** 1.0  
**Data:** 2026-01-29  
**Status:** Draft - Do zatwierdzenia

---

## 📐 Zasady Projektowe

### 1. Separation of Concerns
- **Backend (PHP)**: Logika biznesowa, dostęp do danych, API
- **Frontend (React)**: Interfejs użytkownika, prezentacja danych
- **Database**: Warstwa persystencji z jasno zdefiniowanym schematem

### 2. Dependency Injection
- Wszystkie klasy PHP przyjmują zależności przez konstruktor
- Brak globalnych zmiennych i funkcji pomocniczych
- Łatwe testowanie i wymiana implementacji

### 3. Single Responsibility Principle
- Każda klasa ma jedną, jasno zdefiniowaną odpowiedzialność
- Models = struktura danych
- Repositories = dostęp do bazy
- Services = logika biznesowa
- Controllers = obsługa HTTP

### 4. API-First Approach
- Frontend komunikuje się WYŁĄCZNIE przez REST API
- Brak bezpośredniego dostępu do bazy z JS
- Wszystkie endpointy udokumentowane w OpenAPI

### 5. Type Safety
- TypeScript w 100% (strict mode)
- PHP 8.0+ z type hints
- Wspólne typy w JSON Schema

---

## 📁 Struktura Katalogów

```
mikroplaneta-booking/
│
├── mikroplaneta-booking.php          # Main plugin file (WordPress header)
│
├── core/                              # 🔵 BACKEND CORE
│   ├── class-plugin.php              # Main plugin class (singleton)
│   ├── class-activator.php           # Activation hooks
│   ├── class-deactivator.php         # Deactivation hooks
│   ├── class-loader.php              # Hooks & filters loader
│   │
│   ├── database/                      # Database layer
│   │   ├── class-database.php        # Migration runner
│   │   ├── class-schema.php          # Table definitions
│   │   └── migrations/               # SQL migration files
│   │       ├── 001-create-rooms.php
│   │       ├── 002-create-beds.php
│   │       ├── 003-create-reservations.php
│   │       ├── 004-create-guests.php
│   │       ├── 005-create-allocations-log.php
│   │       ├── 006-create-notifications.php
│   │       ├── 007-create-changes-log.php
│   │       └── 008-create-ai-suggestions.php
│   │
│   ├── models/                        # Data models (POPOs)
│   │   ├── class-room.php
│   │   ├── class-bed.php
│   │   ├── class-reservation.php
│   │   ├── class-guest.php
│   │   ├── class-allocation.php
│   │   └── class-notification.php
│   │
│   ├── repositories/                  # Data access layer
│   │   ├── interface-repository.php
│   │   ├── class-room-repository.php
│   │   ├── class-bed-repository.php
│   │   ├── class-reservation-repository.php
│   │   ├── class-guest-repository.php
│   │   └── class-notification-repository.php
│   │
│   └── services/                      # Business logic
│       ├── class-availability-service.php
│       ├── class-reservation-service.php
│       ├── class-guest-service.php
│       └── class-notification-service.php
│
├── ai/                                # 🤖 AI ENGINE
│   ├── class-ai-engine.php           # Main AI orchestrator
│   ├── algorithms/
│   │   ├── class-bin-packing.php     # Bed allocation algorithm
│   │   └── class-learning-engine.php # Feedback learning
│   └── providers/
│       ├── interface-ai-provider.php
│       ├── class-gemini-provider.php
│       └── class-openai-provider.php
│
├── notifications/                     # 📧 NOTIFICATION SYSTEM
│   ├── class-notification-manager.php
│   ├── channels/
│   │   ├── interface-channel.php
│   │   ├── class-email-channel.php
│   │   ├── class-sms-channel.php
│   │   └── class-push-channel.php
│   └── templates/
│       ├── email/
│       │   ├── reservation-confirmed.php
│       │   ├── reservation-changed.php
│       │   └── reminder-24h.php
│       └── sms/
│           └── reminder.php
│
├── rest-api/                          # 🌐 REST API
│   ├── class-rest-controller.php     # Base controller
│   ├── routes.php                    # Route registration
│   ├── controllers/
│   │   ├── class-rooms-controller.php
│   │   ├── class-beds-controller.php
│   │   ├── class-reservations-controller.php
│   │   ├── class-guests-controller.php
│   │   ├── class-availability-controller.php
│   │   ├── class-ai-controller.php
│   │   └── class-notifications-controller.php
│   └── middleware/
│       ├── class-auth-middleware.php
│       └── class-validation-middleware.php
│
├── admin/                             # 🎨 REACT ADMIN PANEL
│   ├── package.json
│   ├── tsconfig.json
│   ├── vite.config.ts
│   ├── index.html
│   │
│   ├── src/
│   │   ├── main.tsx                  # Entry point
│   │   ├── App.tsx                   # Root component
│   │   │
│   │   ├── components/               # UI Components
│   │   │   ├── layout/
│   │   │   │   ├── Sidebar.tsx
│   │   │   │   ├── Header.tsx
│   │   │   │   └── Layout.tsx
│   │   │   ├── dashboard/
│   │   │   │   ├── DashboardContent.tsx
│   │   │   │   ├── StatsCard.tsx
│   │   │   │   └── RecentActivity.tsx
│   │   │   ├── calendar/
│   │   │   │   ├── CalendarView.tsx
│   │   │   │   ├── DayCell.tsx
│   │   │   │   └── ReservationModal.tsx
│   │   │   ├── reservations/
│   │   │   │   ├── ReservationList.tsx
│   │   │   │   ├── ReservationForm.tsx
│   │   │   │   └── ReservationDetail.tsx
│   │   │   ├── rooms/
│   │   │   │   ├── RoomManager.tsx
│   │   │   │   ├── RoomForm.tsx
│   │   │   │   └── BedManager.tsx
│   │   │   ├── guests/
│   │   │   │   ├── GuestList.tsx
│   │   │   │   ├── GuestDetail.tsx
│   │   │   │   └── ContactWidget.tsx
│   │   │   ├── ai/
│   │   │   │   ├── AISuggestionWidget.tsx
│   │   │   │   ├── FeedbackForm.tsx
│   │   │   │   └── AISettings.tsx
│   │   │   └── common/
│   │   │       ├── Button.tsx
│   │   │       ├── Input.tsx
│   │   │       ├── Modal.tsx
│   │   │       ├── Toast.tsx
│   │   │       └── Spinner.tsx
│   │   │
│   │   ├── services/                 # API Communication
│   │   │   ├── api.ts               # Base API client
│   │   │   ├── roomsApi.ts
│   │   │   ├── bedsApi.ts
│   │   │   ├── reservationsApi.ts
│   │   │   ├── guestsApi.ts
│   │   │   ├── availabilityApi.ts
│   │   │   ├── aiApi.ts
│   │   │   └── notificationsApi.ts
│   │   │
│   │   ├── hooks/                    # Custom React Hooks
│   │   │   ├── useRooms.ts
│   │   │   ├── useBeds.ts
│   │   │   ├── useReservations.ts
│   │   │   ├── useGuests.ts
│   │   │   ├── useAvailability.ts
│   │   │   ├── useAI.ts
│   │   │   └── useNotifications.ts
│   │   │
│   │   ├── types/                    # TypeScript Types
│   │   │   ├── room.ts
│   │   │   ├── bed.ts
│   │   │   ├── reservation.ts
│   │   │   ├── guest.ts
│   │   │   ├── allocation.ts
│   │   │   ├── notification.ts
│   │   │   └── api.ts
│   │   │
│   │   ├── utils/                    # Utilities
│   │   │   ├── dateHelpers.ts
│   │   │   ├── validators.ts
│   │   │   └── formatters.ts
│   │   │
│   │   └── styles/                   # Global Styles
│   │       ├── index.css
│   │       └── variables.css
│   │
│   └── dist/                         # Build output (gitignored)
│
├── public/                            # 🌍 PUBLIC FRONTEND
│   ├── class-frontend.php            # Shortcode & widget registration
│   ├── templates/
│   │   ├── search-form.php
│   │   ├── booking-form.php
│   │   └── my-reservations.php
│   ├── css/
│   │   └── widget.css
│   └── js/
│       └── widget.js
│
├── integrations/                      # 🔌 EXTERNAL INTEGRATIONS
│   ├── class-base-integration.php
│   ├── email/
│   │   ├── class-smtp-integration.php
│   │   └── class-mailgun-integration.php
│   ├── sms/
│   │   └── class-twilio-integration.php
│   └── booking-platforms/
│       ├── class-booking-com.php     # Future
│       └── class-airbnb.php          # Future
│
├── tests/                             # 🧪 TESTS
│   ├── bootstrap.php
│   ├── unit/
│   │   ├── test-bin-packing.php
│   │   ├── test-availability.php
│   │   └── test-notification.php
│   └── integration/
│       └── test-api-endpoints.php
│
├── assets/                            # 📦 COMPILED ASSETS
│   ├── admin/                        # From admin/dist
│   │   ├── index.js
│   │   └── index.css
│   └── public/
│       ├── widget.js
│       └── widget.css
│
├── languages/                         # 🌍 I18N
│   └── mikroplaneta-booking.pot
│
├── docs/                              # 📚 DOCUMENTATION
│   ├── API.md                        # REST API documentation
│   ├── DATABASE.md                   # Database schema
│   ├── DEPLOYMENT.md                 # Deployment guide
│   └── DEVELOPMENT.md                # Development setup
│
├── .gitignore
├── composer.json                      # PHP dependencies
├── phpcs.xml                         # PHP CodeSniffer config
├── README.md
├── ARCHITECTURE.md                   # This file
├── SPRINTS.md                        # Sprint planning
└── LICENSE
```

---

## 🗄️ Schemat Bazy Danych

### ERD (Entity Relationship Diagram)

```
┌─────────────────┐
│  wp_hotel_rooms │
├─────────────────┤
│ id (PK)         │
│ name            │
│ floor           │
│ room_type       │
│ created_at      │
│ updated_at      │
└────────┬────────┘
         │
         │ 1:N
         │
┌────────▼────────┐
│  wp_hotel_beds  │
├─────────────────┤
│ id (PK)         │
│ room_id (FK)    │
│ bed_number      │
│ bed_type        │
│ is_active       │
│ created_at      │
└────────┬────────┘
         │
         │ 1:N
         │
┌────────▼──────────────────┐
│  wp_hotel_reservations    │
├───────────────────────────┤
│ id (PK)                   │
│ bed_id (FK)               │
│ guest_id (FK)             │
│ check_in                  │
│ check_out                 │
│ status                    │
│ total_price               │
│ notes                     │
│ created_by                │
│ created_at                │
│ updated_at                │
└───────────────────────────┘
         │
         │ N:1
         │
┌────────▼────────────────┐
│  wp_hotel_guests        │
├─────────────────────────┤
│ id (PK)                 │
│ first_name              │
│ last_name               │
│ email                   │
│ phone                   │
│ preferences (JSON)      │
│ total_stays             │
│ last_stay_date          │
│ created_at              │
│ updated_at              │
└─────────────────────────┘

┌──────────────────────────────┐
│  wp_hotel_allocations_log    │
├──────────────────────────────┤
│ id (PK)                      │
│ reservation_id (FK)          │
│ suggested_bed_id             │
│ actual_bed_id                │
│ ai_confidence                │
│ satisfaction_score           │
│ feedback_notes               │
│ created_at                   │
└──────────────────────────────┘

┌──────────────────────────────┐
│  wp_hotel_notifications      │
├──────────────────────────────┤
│ id (PK)                      │
│ reservation_id (FK)          │
│ guest_id (FK)                │
│ channel (email/sms/push)     │
│ template_name                │
│ status (sent/failed/pending) │
│ sent_at                      │
│ opened_at                    │
│ clicked_at                   │
│ error_message                │
│ created_at                   │
└──────────────────────────────┘

┌──────────────────────────────┐
│  wp_hotel_changes_log        │
├──────────────────────────────┤
│ id (PK)                      │
│ reservation_id (FK)          │
│ changed_by                   │
│ change_type                  │
│ old_value (JSON)             │
│ new_value (JSON)             │
│ created_at                   │
└──────────────────────────────┘

┌──────────────────────────────┐
│  wp_hotel_ai_suggestions     │
├──────────────────────────────┤
│ id (PK)                      │
│ request_params (JSON)        │
│ suggestion (JSON)            │
│ confidence_score             │
│ was_accepted                 │
│ feedback                     │
│ created_at                   │
└──────────────────────────────┘
```

### Szczegółowe Definicje Tabel

#### 1. `wp_hotel_rooms`
```sql
CREATE TABLE wp_hotel_rooms (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    floor TINYINT DEFAULT 0,
    room_type ENUM('standard', 'deluxe', 'suite', 'dormitory') DEFAULT 'standard',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_room_type (room_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 2. `wp_hotel_beds`
```sql
CREATE TABLE wp_hotel_beds (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    room_id BIGINT UNSIGNED NOT NULL,
    bed_number TINYINT NOT NULL,
    bed_type ENUM('single', 'double', 'bunk') DEFAULT 'single',
    is_active BOOLEAN DEFAULT TRUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (room_id) REFERENCES wp_hotel_rooms(id) ON DELETE CASCADE,
    UNIQUE KEY unique_bed (room_id, bed_number),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 3. `wp_hotel_reservations`
```sql
CREATE TABLE wp_hotel_reservations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    bed_id BIGINT UNSIGNED NOT NULL,
    guest_id BIGINT UNSIGNED NOT NULL,
    check_in DATE NOT NULL,
    check_out DATE NOT NULL,
    status ENUM('pending', 'confirmed', 'checked_in', 'checked_out', 'cancelled') DEFAULT 'pending',
    total_price DECIMAL(10,2) DEFAULT 0.00,
    notes TEXT,
    created_by BIGINT UNSIGNED,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (bed_id) REFERENCES wp_hotel_beds(id) ON DELETE RESTRICT,
    FOREIGN KEY (guest_id) REFERENCES wp_hotel_guests(id) ON DELETE RESTRICT,
    INDEX idx_dates (check_in, check_out),
    INDEX idx_status (status),
    INDEX idx_bed_dates (bed_id, check_in, check_out)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 4. `wp_hotel_guests`
```sql
CREATE TABLE wp_hotel_guests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    preferences JSON,
    total_stays INT DEFAULT 0,
    last_stay_date DATE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_email (email),
    INDEX idx_name (last_name, first_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 5. `wp_hotel_allocations_log`
```sql
CREATE TABLE wp_hotel_allocations_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reservation_id BIGINT UNSIGNED NOT NULL,
    suggested_bed_id BIGINT UNSIGNED,
    actual_bed_id BIGINT UNSIGNED NOT NULL,
    ai_confidence DECIMAL(5,2),
    satisfaction_score TINYINT,
    feedback_notes TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reservation_id) REFERENCES wp_hotel_reservations(id) ON DELETE CASCADE,
    INDEX idx_reservation (reservation_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 6. `wp_hotel_notifications`
```sql
CREATE TABLE wp_hotel_notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reservation_id BIGINT UNSIGNED,
    guest_id BIGINT UNSIGNED NOT NULL,
    channel ENUM('email', 'sms', 'push') NOT NULL,
    template_name VARCHAR(100) NOT NULL,
    status ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
    sent_at DATETIME,
    opened_at DATETIME,
    clicked_at DATETIME,
    error_message TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reservation_id) REFERENCES wp_hotel_reservations(id) ON DELETE SET NULL,
    FOREIGN KEY (guest_id) REFERENCES wp_hotel_guests(id) ON DELETE CASCADE,
    INDEX idx_status (status),
    INDEX idx_guest (guest_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 7. `wp_hotel_changes_log`
```sql
CREATE TABLE wp_hotel_changes_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reservation_id BIGINT UNSIGNED NOT NULL,
    changed_by BIGINT UNSIGNED,
    change_type ENUM('created', 'updated', 'cancelled', 'status_changed') NOT NULL,
    old_value JSON,
    new_value JSON,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reservation_id) REFERENCES wp_hotel_reservations(id) ON DELETE CASCADE,
    INDEX idx_reservation (reservation_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 8. `wp_hotel_ai_suggestions`
```sql
CREATE TABLE wp_hotel_ai_suggestions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    request_params JSON NOT NULL,
    suggestion JSON NOT NULL,
    confidence_score DECIMAL(5,2),
    was_accepted BOOLEAN DEFAULT FALSE,
    feedback TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 🌐 REST API Endpoints

### Namespace: `/mikroplaneta/v1`

#### **Rooms**
```
GET    /rooms              - List all rooms
GET    /rooms/{id}         - Get single room
POST   /rooms              - Create room
PUT    /rooms/{id}         - Update room
DELETE /rooms/{id}         - Delete room
```

#### **Beds**
```
GET    /beds               - List all beds
GET    /beds/{id}          - Get single bed
POST   /beds               - Create bed
PUT    /beds/{id}          - Update bed
DELETE /beds/{id}          - Delete bed
GET    /rooms/{id}/beds    - Get beds for specific room
```

#### **Reservations**
```
GET    /reservations              - List reservations (with filters)
GET    /reservations/{id}         - Get single reservation
POST   /reservations              - Create reservation
PUT    /reservations/{id}         - Update reservation
DELETE /reservations/{id}         - Cancel reservation
GET    /reservations/{id}/history - Get change history
```

#### **Guests**
```
GET    /guests                    - List guests
GET    /guests/{id}               - Get single guest
POST   /guests                    - Create guest
PUT    /guests/{id}               - Update guest
GET    /guests/search?email=...   - Search by email
GET    /guests/{id}/reservations  - Get guest's reservations
```

#### **Availability**
```
POST   /availability/check        - Check bed availability
GET    /availability/calendar     - Get calendar view
```

#### **AI**
```
POST   /ai/suggest-allocation     - Get AI suggestion for bed allocation
POST   /ai/feedback               - Submit feedback on AI suggestion
GET    /ai/stats                  - Get AI performance stats
```

#### **Notifications**
```
GET    /notifications             - List notifications
POST   /notifications/send        - Send notification to guest
GET    /notifications/{id}        - Get notification details
```

### Request/Response Examples

#### POST `/reservations`
**Request:**
```json
{
  "bed_id": 5,
  "guest_id": 12,
  "check_in": "2026-02-01",
  "check_out": "2026-02-05",
  "total_price": 400.00,
  "notes": "Late check-in requested"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 42,
    "bed_id": 5,
    "guest_id": 12,
    "check_in": "2026-02-01",
    "check_out": "2026-02-05",
    "status": "confirmed",
    "total_price": 400.00,
    "created_at": "2026-01-29T17:30:00Z"
  }
}
```

#### POST `/ai/suggest-allocation`
**Request:**
```json
{
  "group_size": 3,
  "check_in": "2026-02-10",
  "check_out": "2026-02-15",
  "guest_emails": ["john@example.com", "jane@example.com"]
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "suggestion": {
      "beds": [5, 6, 7],
      "room_ids": [2, 2, 3],
      "reasoning": "Grouped 2 guests in same room based on past preferences"
    },
    "confidence": 0.87,
    "alternatives": [
      {
        "beds": [8, 9, 10],
        "confidence": 0.72
      }
    ]
  }
}
```

---

## 🔧 Konwencje Kodowania

### PHP (PSR-12 + WordPress)

#### Namespace
```php
namespace MikroPlaneta\Booking\Core\Services;
```

#### Class Structure
```php
<?php
/**
 * Reservation Service
 *
 * @package MikroPlaneta\Booking
 * @since 1.0.0
 */

namespace MikroPlaneta\Booking\Core\Services;

use MikroPlaneta\Booking\Core\Repositories\ReservationRepository;
use MikroPlaneta\Booking\Core\Models\Reservation;

class ReservationService {
    
    private ReservationRepository $repository;
    private NotificationService $notifier;
    
    public function __construct(
        ReservationRepository $repository,
        NotificationService $notifier
    ) {
        $this->repository = $repository;
        $this->notifier = $notifier;
    }
    
    /**
     * Create new reservation
     *
     * @param array $data Reservation data
     * @return Reservation|WP_Error
     */
    public function create(array $data): Reservation|\WP_Error {
        // Validation
        $validated = $this->validate($data);
        if (is_wp_error($validated)) {
            return $validated;
        }
        
        // Business logic
        $reservation = $this->repository->create($validated);
        
        // Side effects
        $this->notifier->send($reservation->guest_id, 'reservation-confirmed');
        
        return $reservation;
    }
}
```

#### Repository Pattern
```php
<?php

namespace MikroPlaneta\Booking\Core\Repositories;

use MikroPlaneta\Booking\Core\Models\Reservation;

class ReservationRepository implements RepositoryInterface {
    
    private string $table;
    
    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'hotel_reservations';
    }
    
    public function find(int $id): ?Reservation {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d", $id),
            ARRAY_A
        );
        
        return $row ? Reservation::fromArray($row) : null;
    }
    
    public function create(array $data): Reservation {
        global $wpdb;
        $wpdb->insert($this->table, $data);
        return $this->find($wpdb->insert_id);
    }
}
```

### TypeScript (Strict Mode)

#### API Service
```typescript
// services/reservationsApi.ts

import { apiClient } from './api';
import type { Reservation, CreateReservationDTO } from '../types/reservation';

export const reservationsApi = {
  async getAll(): Promise<Reservation[]> {
    const response = await apiClient.get<Reservation[]>('/reservations');
    return response.data;
  },
  
  async getById(id: number): Promise<Reservation> {
    const response = await apiClient.get<Reservation>(`/reservations/${id}`);
    return response.data;
  },
  
  async create(data: CreateReservationDTO): Promise<Reservation> {
    const response = await apiClient.post<Reservation>('/reservations', data);
    return response.data;
  },
  
  async update(id: number, data: Partial<CreateReservationDTO>): Promise<Reservation> {
    const response = await apiClient.put<Reservation>(`/reservations/${id}`, data);
    return response.data;
  },
  
  async delete(id: number): Promise<void> {
    await apiClient.delete(`/reservations/${id}`);
  }
};
```

#### Custom Hook
```typescript
// hooks/useReservations.ts

import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { reservationsApi } from '../services/reservationsApi';
import type { CreateReservationDTO } from '../types/reservation';

export function useReservations() {
  const queryClient = useQueryClient();
  
  const { data: reservations, isLoading, error } = useQuery({
    queryKey: ['reservations'],
    queryFn: reservationsApi.getAll
  });
  
  const createMutation = useMutation({
    mutationFn: (data: CreateReservationDTO) => reservationsApi.create(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['reservations'] });
    }
  });
  
  return {
    reservations,
    isLoading,
    error,
    createReservation: createMutation.mutate,
    isCreating: createMutation.isPending
  };
}
```

#### Type Definitions
```typescript
// types/reservation.ts

export interface Reservation {
  id: number;
  bed_id: number;
  guest_id: number;
  check_in: string; // ISO date
  check_out: string;
  status: 'pending' | 'confirmed' | 'checked_in' | 'checked_out' | 'cancelled';
  total_price: number;
  notes?: string;
  created_at: string;
  updated_at: string;
}

export interface CreateReservationDTO {
  bed_id: number;
  guest_id: number;
  check_in: string;
  check_out: string;
  total_price: number;
  notes?: string;
}

export interface UpdateReservationDTO extends Partial<CreateReservationDTO> {
  status?: Reservation['status'];
}
```

---

## 🔄 Dependency Flow

```
┌─────────────────────────────────────────┐
│         WordPress Core                  │
└──────────────┬──────────────────────────┘
               │
               ▼
┌──────────────────────────────────────────┐
│    mikroplaneta-booking.php              │
│    (Plugin Bootstrap)                    │
└──────────────┬───────────────────────────┘
               │
               ▼
┌──────────────────────────────────────────┐
│    Core\Plugin (Singleton)               │
│    - Registers hooks                     │
│    - Initializes services                │
└──────────────┬───────────────────────────┘
               │
               ├──────────────────┬─────────────────┬──────────────────┐
               ▼                  ▼                 ▼                  ▼
        ┌─────────────┐   ┌─────────────┐   ┌─────────────┐   ┌─────────────┐
        │  Database   │   │  REST API   │   │  Admin UI   │   │ AI Engine   │
        │  Migration  │   │  Routes     │   │  (React)    │   │             │
        └─────────────┘   └──────┬──────┘   └──────┬──────┘   └──────┬──────┘
                                 │                  │                  │
                                 ▼                  ▼                  ▼
                          ┌─────────────┐   ┌─────────────┐   ┌─────────────┐
                          │ Controllers │   │  Services   │   │ Algorithms  │
                          └──────┬──────┘   └──────┬──────┘   └──────┬──────┘
                                 │                  │                  │
                                 └──────────┬───────┴──────────────────┘
                                            ▼
                                    ┌──────────────┐
                                    │ Repositories │
                                    └──────┬───────┘
                                           │
                                           ▼
                                    ┌──────────────┐
                                    │   Database   │
                                    └──────────────┘
```

---

## 📦 Deployment Strategy

### Development
```bash
# Backend
composer install
npm run dev --prefix admin

# Database
wp plugin activate mikroplaneta-booking
# Runs migrations automatically
```

### Production Build
```bash
# Build React admin panel
cd admin
npm run build

# Assets are copied to /assets/admin/
# WordPress loads from there
```

### Version Control
```
.gitignore:
  /admin/node_modules/
  /admin/dist/
  /vendor/
  /assets/admin/*
  .env
```

---

## 🧪 Testing Strategy

### PHP Unit Tests (PHPUnit)
```php
class BinPackingTest extends WP_UnitTestCase {
    public function test_allocates_group_to_same_room() {
        $algorithm = new BinPacking();
        $result = $algorithm->allocate(3, '2026-02-01', '2026-02-05');
        
        $this->assertCount(3, $result['beds']);
        $this->assertEquals(1, count(array_unique($result['room_ids'])));
    }
}
```

### TypeScript Tests (Vitest)
```typescript
import { describe, it, expect } from 'vitest';
import { formatDateRange } from '../utils/dateHelpers';

describe('dateHelpers', () => {
  it('formats date range correctly', () => {
    const result = formatDateRange('2026-02-01', '2026-02-05');
    expect(result).toBe('Feb 1 - Feb 5, 2026');
  });
});
```

---

## 🚀 Migration Plan

### Order of Execution
1. `001-create-rooms.php` - Base rooms table
2. `002-create-beds.php` - Beds (depends on rooms)
3. `003-create-guests.php` - Guests (independent)
4. `004-create-reservations.php` - Reservations (depends on beds + guests)
5. `005-create-allocations-log.php` - AI logs (depends on reservations)
6. `006-create-notifications.php` - Notifications (depends on guests)
7. `007-create-changes-log.php` - Change tracking (depends on reservations)
8. `008-create-ai-suggestions.php` - AI suggestions (independent)

### Rollback Strategy
Each migration file includes:
```php
public function up() {
    // Create table
}

public function down() {
    // Drop table
}
```

---

## 📝 Checklist przed rozpoczęciem kodowania

- [ ] Zatwierdzenie struktury katalogów
- [ ] Zatwierdzenie schematu bazy danych
- [ ] Zatwierdzenie endpointów API
- [ ] Zatwierdzenie konwencji nazewnictwa
- [ ] Zatwierdzenie dependency flow
- [ ] Przygotowanie środowiska (Composer, NPM)
- [ ] Konfiguracja ESLint + Prettier
- [ ] Konfiguracja PHPCS + PHPStan

---

## 🎯 Następne Kroki

Po zatwierdzeniu tego dokumentu:

1. **Wygenerowanie pustej struktury** - wszystkie foldery i pliki `.gitkeep`
2. **Utworzenie plików migracji** - SQL dla wszystkich tabel
3. **Stworzenie głównego pliku wtyczki** - `mikroplaneta-booking.php`
4. **Inicjalizacja projektu React** - `admin/` z Vite
5. **Konfiguracja Composer** - autoloading PSR-4
6. **Pierwsze testy** - sprawdzenie czy struktura działa

---

**Status:** ⏳ Oczekuje na zatwierdzenie  
**Autor:** Antigravity AI  
**Data:** 2026-01-29
