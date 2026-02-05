# MikroPlaneta Booking

Advanced hotel booking system with AI-powered bed allocation for WordPress.

## 📚 Documentation

- **[Architecture](ARCHITECTURE.md)** - Complete system architecture and design
- **[API Documentation](docs/API.md)** - REST API endpoints reference
- **[Database Schema](docs/DATABASE.md)** - Database structure and relationships
- **[Development Guide](docs/DEVELOPMENT.md)** - Setup and development workflow
- **[Sprint Planning](SPRINTS.md)** - Implementation roadmap

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
