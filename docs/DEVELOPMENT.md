# Development Guide

## Prerequisites

- PHP 8.0+
- WordPress 6.0+
- Node.js 18+
- Composer

## Setup

### 1. Install PHP Dependencies
```bash
composer install
```

### 2. Install Node Dependencies
```bash
cd admin
npm install
```

### 3. Activate Plugin
```bash
wp plugin activate mikroplaneta-booking
```

This will automatically run database migrations.

## Development Workflow

### Backend (PHP)

#### Run Tests
```bash
composer test
```

#### Code Style Check
```bash
composer phpcs
```

#### Auto-fix Code Style
```bash
./vendor/bin/phpcbf --standard=WordPress .
```

### Frontend (React)

#### Development Server
```bash
cd admin
npm run dev
```

#### Build for Production
```bash
cd admin
npm run build
```

#### Type Check
```bash
cd admin
npm run type-check
```

#### Lint
```bash
cd admin
npm run lint
```

## Project Structure

See `ARCHITECTURE.md` for detailed structure documentation.

## Coding Standards

- **PHP**: PSR-12 + WordPress Coding Standards
- **TypeScript**: Airbnb style guide
- **Commits**: Conventional Commits format

## Database Migrations

### Create New Migration
1. Create file in `core/database/migrations/`
2. Name it `00X-description.php`
3. Implement `up()` and `down()` methods

### Run Migrations
Migrations run automatically on plugin activation.

### Rollback Last Migration
```php
$db = new \MikroPlaneta\Booking\Core\Database\Database();
$db->rollback();
```
