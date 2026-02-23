# 🛠️ Development Guide - MikroPlaneta Booking

## 📋 Spis treści

1. [Wymagania](#wymagania)
2. [Konfiguracja środowiska](#konfiguracja-środowiska)
3. [Development lokalny](#development-lokalny)
4. [Testowanie na serwerze](#testowanie-na-serwerze)
5. [Deploy](#deploy)
6. [Struktura plików](#struktura-plików)
7. [Najczęstsze problemy](#najczęstsze-problemy)

---

## 🚀 Wymagania

### Niezbędne:
- **PHP 8.0+**
- **WordPress 6.0+**
- **MySQL 8.0+** lub MariaDB 10.3+
- **Node.js 18+** (LTS recommended)
- **npm 9+** lub **yarn 1.22+**

### Zalecane:
- **Laragon** (Windows) lub **LocalWP** (Mac/Windows)
- **VS Code** z rozszerzeniami:
  - ESLint
  - Prettier
  - PHP Intelephense
  - Tailwind CSS IntelliSense

---

## ⚙️ Konfiguracja środowiska

### Krok 1: Klonowanie repozytorium

```bash
cd c:\laragon\www
git clone <repo-url> gorytajemnic/wp-content/plugins/mikro-booking
cd gorytajemnic/wp-content/plugins/mikro-booking
```

### Krok 2: Instalacja PHP dependencies

```bash
composer install
```

### Krok 3: Instalacja Node.js dependencies

```bash
cd admin
npm install
```

### Krok 4: Konfiguracja środowiska

```bash
# Skopiuj plik .env.example
cp .env.example .env

# Edytuj .env i dostosuj do swojej konfiguracji
```

### Krok 5: Aktywacja pluginu

1. Zaloguj się do WordPress Admin
2. Przejdź do **Wtyczki → Zainstalowane wtyczki**
3. Kliknij **Aktywuj** przy "MikroPlaneta Booking"

---

## 💻 Development lokalny

### Opcja A: Vite Dev Server (ZALECANE)

**Terminal 1** - React Dev Server:
```bash
cd admin
npm run dev
```

**Terminal 2** - WordPress (Laragon już działa):
```
# Otwórz w przeglądarce:
http://gorytajemnic.test/wp-admin/
```

**Korzyści:**
- ✅ Hot Module Replacement (HMR) - zmiany widoczne natychmiast
- ✅ Fast Refresh - zachowuje stan React komponentów
- ✅ Source maps - łatwe debugowanie
- ✅ TypeScript checking w czasie rzeczywistym

**Jak to działa:**
```
┌──────────────────────────────────────────┐
│  Przeglądarka                            │
│  http://gorytajemnic.test/wp-admin/      │
│                                          │
│  ┌────────────────────────────────────┐  │
│  │ WordPress Admin (PHP)              │  │
│  │                                    │  │
│  │  <div id="mikroplaneta-booking-    │  │
│  │   root"></div>                     │  │
│  │                                    │  │
│  │  <script src="http://localhost:    │  │
│  │   3000/index.js"></script>         │  │
│  └────────────────────────────────────┘  │
│              ↕                           │
│  ┌────────────────────────────────────┐  │
│  │  Vite Dev Server                   │  │
│  │  http://localhost:3000             │  │
│  │                                    │  │
│  │  - index.js (React + HMR)          │  │
│  │  - index.css (Tailwind)            │  │
│  └────────────────────────────────────┘  │
└──────────────────────────────────────────┘
```

### Opcja B: Build + Refresh

Jeśli HMR nie działa:

```bash
cd admin
npm run build
# Odśwież stronę WordPressa
```

---

## 🌐 Testowanie na serwerze

### Krok 1: Zbuduj wersję produkcyjną

```bash
cd admin
npm run build
```

### Krok 2: Wgraj na serwer

**Opcja A - SCP:**
```bash
scp -r assets/admin/* user@server:/path/to/wp-content/plugins/mikro-booking/assets/admin/
```

**Opcja B - Git:**
```bash
git add assets/admin/
git commit -m "Build React app"
git push

# Na serwerze:
cd /path/to/wp-content/plugins/mikro-booking
git pull
```

**Opcja C - FTP/SFTP:**
Użyj FileZilla lub WinSCP, wgraj folder `assets/admin/`

### Krok 3: Sprawdź na serwerze

1. Odśwież WordPress Admin
2. Przejdź do **Booking → Dashboard**
3. Sprawdź konsolę przeglądarki (F12) pod kątem błędów

---

## 🚀 Deploy

### Szybki deploy (skrypt)

```bash
# Uruchom skrypt deployujący
./deploy.sh production
```

### Ręczny deploy - checklista

```bash
# 1. Zbuduj React app
cd admin
npm run build

# 2. Sprawdź czy pliki istnieją
ls -la ../assets/admin/
# Powinny być: index.js, index.css, index.html

# 3. Wgraj na serwer (bez vendor i node_modules)
# Wyklucz:
# - vendor/ (composer install na serwerze)
# - admin/node_modules/
# - debug-*.php
# - test-*.php
# - *.log

# 4. Na serwerze:
cd /path/to/wp-content/plugins/mikro-booking
composer install --no-dev --optimize-autoloader

# 5. Ustaw uprawnienia
find . -type d -exec chmod 755 {} \;
find . -type f -name "*.php" -exec chmod 644 {} \;

# 6. Wyczyść cache WordPressa
wp cache flush
```

### GitHub Actions (automatyczny deploy)

Jeśli masz skonfigurowane GitHub Actions:

```yaml
# .github/workflows/deploy.yml
on:
  push:
    branches: [main]

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - name: Build
        run: |
          cd admin
          npm install
          npm run build
      - name: Deploy
        uses: some-deploy-action@v1
```

---

## 📁 Struktura plików

```
mikro-booking/
├── 📁 admin/                    # React app source
│   ├── 📁 src/
│   │   ├── 📁 components/       # React komponenty
│   │   │   ├── CalendarView.tsx
│   │   │   ├── DashboardContent.tsx
│   │   │   ├── GuestsView.tsx
│   │   │   ├── PricingView.tsx
│   │   │   ├── ReservationModal.tsx
│   │   │   ├── RoomManager.tsx
│   │   │   └── Settings.tsx
│   │   ├── 📁 services/
│   │   │   └── api.ts           # API client (Axios)
│   │   ├── 📁 hooks/            # Custom React hooks
│   │   ├── 📁 types/            # TypeScript types
│   │   ├── 📁 utils/            # Helper functions
│   │   ├── App.tsx              # Główny komponent
│   │   ├── main.tsx             # Entry point
│   │   └── index.css            # Global styles
│   ├── package.json
│   ├── tsconfig.json
│   ├── vite.config.ts
│   └── .env.example
│
├── 📁 assets/admin/             # Built React app
│   ├── index.js                 # Zbundlowany React
│   ├── index.css                # Zbundlowany CSS
│   └── index.html
│
├── 📁 core/                     # PHP backend
│   ├── 📁 database/
│   │   ├── 📁 migrations/       # Database migrations
│   │   ├── class-database.php
│   │   └── class-schema.php
│   ├── 📁 models/               # Data models (POPO)
│   ├── 📁 repositories/         # Data access layer
│   ├── 📁 services/             # Business logic
│   ├── class-plugin.php
│   ├── class-activator.php
│   ├── class-admin.php
│   └── ...
│
├── 📁 rest-api/                 # REST API
│   ├── 📁 controllers/
│   ├── 📁 middleware/
│   ├── class-rest-controller.php
│   └── routes.php
│
├── 📁 public/                   # Frontend (shortcode)
│   ├── class-frontend.php
│   ├── 📁 css/
│   └── 📁 js/
│
├── 📁 vendor/                   # PHP dependencies
├── composer.json
├── mikroplaneta-booking.php     # Main plugin file
└── DEVELOPMENT.md               # Ten plik
```

---

## 🔧 Najczęstsze problemy

### Problem: "Build artifacts not found"

**Objaw:**
```
The React frontend application has not been built properly.
Please run: cd admin && npm install && npm run build
```

**Rozwiązanie:**
```bash
cd admin
npm install
npm run build
```

---

### Problem: HMR nie działa

**Objaw:**
- Zmiany w React nie są widoczne
- Console: "WebSocket connection failed"

**Rozwiązanie:**
1. Sprawdź czy Vite działa: `http://localhost:3000`
2. Restart Vite: `Ctrl+C` → `npm run dev`
3. Wyczyść cache przeglądarki: `Ctrl+Shift+R`

---

### Problem: 404 na REST API

**Objaw:**
```
GET http://gorytajemnic.test/wp-json/mikroplaneta/v1/rooms 404
```

**Rozwiązanie:**
1. Sprawdź czy plugin jest aktywny
2. Przejdź do **Settings → Permalinks** i kliknij **Save**
3. Sprawdź czy routes są zarejestrowane:
   ```bash
   wp rest route list --namespace=mikroplaneta/v1
   ```

---

### Problem: CORS errors

**Objaw:**
```
Access to fetch at '...' from origin '...' has been blocked by CORS policy
```

**Rozwiązanie:**
```bash
# W developmentzie - Vite proxy powinno pomóc
# Sprawdź vite.config.ts:
server: {
  proxy: {
    '/wp-json': {
      target: 'http://gorytajemnic.test',
      changeOrigin: true,
    },
  },
}
```

---

### Problem: TypeScript errors

**Objaw:**
```
TS2307: Cannot find module '...'
```

**Rozwiązanie:**
```bash
cd admin
npm install
npm run type-check
```

---

### Problem: Tailwind CSS nie działa

**Objaw:**
- Style nie są ładowane
- Brak klas Tailwind

**Rozwiązanie:**
```bash
cd admin
# Sprawdź konfigurację
cat tailwind.config.js

# Przebuduj
npm run build
```

---

## 📝 Komendy

### PHP (backend)

```bash
# Instalacja dependencies
composer install

# Development
composer test          # Uruchom testy
composer phpcs         # Check code style
composer phpcs:fix     # Fix code style
```

### Node.js (frontend)

```bash
cd admin

# Instalacja dependencies
npm install

# Development
npm run dev            # Vite dev server
npm run build          # Production build
npm run preview        # Preview production build
npm run type-check     # TypeScript check
npm run lint           # ESLint
npm run lint:fix       # Fix linting errors
```

---

## 🎯 Workflow - przykładowy dzień developera

```bash
# 1. Start Laragonu
# Uruchom Laragon → Start All

# 2. Start Vite dev servera
cd c:\laragon\www\gorytajemnic\wp-content\plugins\mikro-booking\admin
npm run dev

# 3. Otwórz WordPress Admin
# http://gorytajemnic.test/wp-admin/

# 4. Pracuj w kodzie
# - Edytuj komponenty React
# - Zmiany widoczne natychmiast (HMR)
# - TypeScript check w tle

# 5. Testuj zmiany
# - Sprawdzaj w przeglądarce
# - Console.log / React DevTools

# 6. Commit
git add .
git commit -m "Feature: dodano nowy komponent"
git push

# 7. Deploy na serwer testowy
# Automatycznie przez GitHub Actions lub ręcznie
```

---

## 📞 Support

W przypadku problemów:
1. Sprawdź logi: `wp-content/debug.log`
2. Sprawdź konsolę przeglądarki (F12)
3. Sprawdź PHP errors: `error_log()`

---

**Happy coding! 🚀**

*Ostatnia aktualizacja: 2026-02-23*
