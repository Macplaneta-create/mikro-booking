# REST API Documentation

## Base URL
```
/wp-json/mikroplaneta/v1
```

## Authentication
All endpoints require WordPress authentication (user with `manage_options` capability).

## Endpoints

### Rooms
- `GET /rooms` - List all rooms
- `GET /rooms/{id}` - Get single room
- `POST /rooms` - Create room
- `PUT /rooms/{id}` - Update room
- `DELETE /rooms/{id}` - Delete room

### Beds
- `GET /beds` - List all beds
- `GET /beds/{id}` - Get single bed
- `POST /beds` - Create bed
- `PUT /beds/{id}` - Update bed
- `DELETE /beds/{id}` - Delete bed
- `GET /rooms/{id}/beds` - Get beds for specific room

### Reservations
- `GET /reservations` - List reservations
- `GET /reservations/{id}` - Get single reservation
- `POST /reservations` - Create reservation
- `PUT /reservations/{id}` - Update reservation
- `DELETE /reservations/{id}` - Cancel reservation
- `GET /reservations/{id}/history` - Get change history

### Guests
- `GET /guests` - List guests
- `GET /guests/{id}` - Get single guest
- `POST /guests` - Create guest
- `PUT /guests/{id}` - Update guest
- `GET /guests/search?email=...` - Search by email
- `GET /guests/{id}/reservations` - Get guest's reservations

### Availability
- `POST /availability/check` - Check bed availability
- `GET /availability/calendar` - Get calendar view

### AI
- `POST /ai/suggest-allocation` - Get AI suggestion
- `POST /ai/feedback` - Submit feedback
- `GET /ai/stats` - Get AI performance stats

### Notifications
- `GET /notifications` - List notifications
- `POST /notifications/send` - Send notification
- `GET /notifications/{id}` - Get notification details

## Response Format

### Success Response
```json
{
  "success": true,
  "data": { ... }
}
```

### Error Response
```json
{
  "success": false,
  "message": "Error description",
  "data": { ... }
}
```
