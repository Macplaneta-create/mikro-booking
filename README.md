# MikroPlaneta Booking

Advanced hotel booking system with AI-powered bed allocation for WordPress.

## 📚 Documentation

- **[Architecture](ARCHITECTURE.md)** - Complete system architecture and design
- **[API Documentation](docs/API.md)** - REST API endpoints reference
- **[Database Schema](docs/DATABASE.md)** - Database structure and relationships
- **[Development Guide](DEVELOPMENT.md)** - Setup and development workflow
- **[Quick Start (PL)](SZYBKI_START.md)** - Quick start guide in Polish

## ✨ Features

- 🏨 **Room & Bed Management** - Flexible room types and bed configurations
- 💰 **Dynamic Pricing** - Per-room and per-bed pricing modes
- 📅 **Real-time Availability** - Live calendar and availability checking
- 💳 **Deposit Payments** - Configurable deposit system with payment info
- 🛏️ **AI Bed Allocation** - Smart bed assignment for group bookings
- 📧 **Email Notifications** - Automated reservation confirmations
- 🔒 **GDPR Compliant** - Built-in consent management
- 🌐 **Multi-language** - WordPress i18n ready

## 🚀 Quick Start

### Prerequisites
- PHP 8.0+
- WordPress 6.0+
- Node.js 18+
- Composer

### Installation

1. **Install PHP dependencies:**
   ```bash
   composer install
   ```

2. **Install Node dependencies:**
   ```bash
   cd admin
   npm install
   ```

3. **Activate plugin in WordPress:**
   ```bash
   wp plugin activate mikroplaneta-booking
   ```

4. **Start development server:**
   ```bash
   cd admin
   npm run dev
   ```

## 📁 Project Structure

```
mikroplaneta-booking/
├── core/              # Backend (PHP)
├── ai/                # AI Engine
├── notifications/     # Notification System
├── rest-api/          # REST API
├── admin/             # React Admin Panel
├── public/            # Public Frontend
├── integrations/      # External Services
├── tests/             # Tests
└── docs/              # Documentation
```

## 🎯 Features

- **Bed-Level Reservations** - Reserve individual beds, not just rooms
- **AI-Powered Allocation** - Smart bed assignment using bin packing algorithm
- **Guest Management** - Track guest preferences and history
- **Multi-Channel Notifications** - Email, SMS, Push notifications
- **Change Tracking** - Complete audit log of all changes
- **Learning System** - AI improves based on feedback

## 📝 License

GPL v3 or later

## 👨‍💻 Author

MikroPlaneta - [https://mikroplaneta.pl](https://mikroplaneta.pl)
