# 🚀 Szybki Start - MikroPlaneta Booking

## 📦 Instalacja i pierwszy deploy

### Krok 1: Przygotowanie lokalne

```bash
# Przejdź do folderu pluginu
cd c:\laragon\www\gorytajemnic\wp-content\plugins\mikro-booking

# Zainstaluj PHP dependencies
composer install

# Zainstaluj Node.js dependencies i zbuduj React
cd admin
npm install
npm run build
```

### Krok 2: Aktywacja w WordPress

1. Zaloguj się do panelu WordPress: `http://gorytajemnic.test/wp-admin/`
2. Przejdź do **Wtyczki → Zainstalowane wtyczki**
3. Kliknij **Aktywuj** przy "MikroPlaneta Booking"

### Krok 3: Konfiguracja

1. Przejdź do **Booking → Settings**
2. Skonfiguruj:
   - Nazwa hotelu
   - Godziny check-in/check-out
   - Waluta
   - Timeout dla rezerwacji pending

### Krok 4: Dodaj pokoje i łóżka

1. Przejdź do **Booking → Rooms & Beds**
2. Kliknij **Add Room**
3. Wypełnij dane pokoju
4. Dodaj łóżka do pokoju (bed_number, bed_type)

### Krok 5: Ustaw ceny

1. Przejdź do **Booking → Pricing**
2. Wybierz pokój
3. Ustaw zakres dat
4. Wprowadź ceny (weekday/weekend)

### Krok 6: Testowanie rezerwacji

1. Przejdź do **Booking → Dashboard**
2. Kliknij na wolne łóżko w kalendarzu
3. Wybierz daty check-in/check-out
4. Wypełnij dane gościa
5. Kliknij **Zarezerwuj**

---

## 🌐 Deploy na serwer produkcyjny

### Opcja A: Windows (PowerShell/CMD)

```bash
# W folderze pluginu
deploy.bat production
```

### Opcja B: Linux/Mac (Bash)

```bash
# W folderze pluginu
chmod +x deploy.sh
./deploy.sh production
```

### Opcja C: Ręcznie

```bash
# 1. Zbuduj React
cd admin
npm run build

# 2. Wgraj na serwer (bez vendor i node_modules)
scp -r . user@server:/path/to/wp-content/plugins/mikro-booking/

# 3. Na serwerze
cd /path/to/wp-content/plugins/mikro-booking
composer install --no-dev --optimize-autoloader

# 4. Ustaw uprawnienia
chmod -R 755 .
find . -name '*.php' -exec chmod 644 {} \;

# 5. Aktywuj w WordPress
wp plugin activate mikro-booking
```

---

## 💻 Development

### Uruchomienie dev servera

```bash
# Terminal 1 - Vite dev server
cd admin
npm run dev

# Terminal 2 - WordPress (Laragon)
# Otwórz: http://gorytajemnic.test/wp-admin/
```

### Hot Reload

Zmiany w plikach React są widoczne **natychmiast** dzięki Vite HMR!

---

## 📁 Struktura folderów

```
mikro-booking/
├── admin/              # React app (source)
├── assets/admin/       # React app (built) ✅
├── core/               # PHP backend
├── rest-api/           # REST API controllers
├── public/             # Frontend shortcode
├── vendor/             # PHP dependencies
├── deploy.bat          # Windows deploy script
├── deploy.sh           # Linux/Mac deploy script
└── DEVELOPMENT.md      # Pełna dokumentacja
```

---

## 🔧 Najczęstsze komendy

### Backend (PHP)

```bash
composer install              # Instaluj PHP dependencies
composer test                 # Uruchom testy
composer phpcs                # Check code style
```

### Frontend (Node.js)

```bash
cd admin
npm install                   # Instaluj Node dependencies
npm run dev                   # Dev server z HMR
npm run build                 # Production build
npm run lint                  # Check code style
npm run type-check            # TypeScript check
```

### Deploy

```bash
./deploy.sh local             # Build lokalny
./deploy.sh production        # Deploy na produkcję
```

---

## ⚠️ Ważne uwagi

### Przed deployem:

1. ✅ Zbuduj React: `npm run build`
2. ✅ Sprawdź czy `assets/admin/index.js` istnieje
3. ✅ Usuń pliki debugowe (debug-*.php, test-*.php)
4. ✅ Nie wysyłaj `vendor/` (composer install na serwerze)
5. ✅ Nie wysyłaj `admin/node_modules/`

### Na serwerze:

```bash
# Zainstaluj PHP dependencies
composer install --no-dev --optimize-autoloader

# Ustaw uprawnienia
chmod -R 755 .
find . -name '*.php' -exec chmod 644 {} \;

# Wyczyść cache
wp cache flush
```

---

## 🆘 Pomoc

### Problem: Brak plików buildu

```bash
cd admin
npm install
npm run build
```

### Problem: 404 na REST API

1. Sprawdź czy plugin jest aktywny
2. **Settings → Permalinks → Save Changes**

### Problem: CORS errors

Upewnij się, że Vite proxy jest poprawnie skonfigurowane w `vite.config.ts`

---

## 📚 Więcej informacji

- [DEVELOPMENT.md](DEVELOPMENT.md) - Pełna dokumentacja developerska
- [ARCHITECTURE.md](ARCHITECTURE.md) - Architektura aplikacji
- [README.md](README.md) - Opis projektu

---

**Gotowe! 🎉**

*Ostatnia aktualizacja: 2026-02-23*
