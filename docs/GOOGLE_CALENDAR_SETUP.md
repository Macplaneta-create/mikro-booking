# Google Calendar – Instrukcja konfiguracji

> **Model:** Bring Your Own Key (BYOK) – każda instalacja wtyczki korzysta z własnych credentials Google Cloud. Dzięki temu Twoje rezerwacje są całkowicie prywatne i niezależne od jakichkolwiek limitów współdzielonych z innymi użytkownikami.

---

## Spis treści

1. [Wymagania wstępne](#wymagania-wstępne)
2. [Krok 1 – Utwórz projekt w Google Cloud Console](#krok-1--utwórz-projekt-w-google-cloud-console)
3. [Krok 2 – Włącz Google Calendar API](#krok-2--włącz-google-calendar-api)
4. [Krok 3 – Skonfiguruj ekran zgody OAuth](#krok-3--skonfiguruj-ekran-zgody-oauth)
5. [Krok 4 – Utwórz OAuth 2.0 Client ID](#krok-4--utwórz-oauth-20-client-id)
6. [Krok 5 – Połącz wtyczkę z Google Calendar](#krok-5--połącz-wtyczkę-z-google-calendar)
7. [Rozwiązywanie problemów](#rozwiązywanie-problemów)
8. [FAQ](#faq)

---

## Wymagania wstępne

- Konto Google (gmail.com lub Google Workspace)
- Zalogowanie do [Google Cloud Console](https://console.cloud.google.com/)
- Uprawnienia administratora w panelu WordPress (rola: **Administrator**)

---

## Krok 1 – Utwórz projekt w Google Cloud Console

1. Otwórz [https://console.cloud.google.com/](https://console.cloud.google.com/)
2. W górnym pasku kliknij **listę projektów** (obok logo Google Cloud) → **Nowy projekt**
3. Wpisz nazwę projektu, np. `Góry Tajemnic Booking`
4. Kliknij **Utwórz**
5. Upewnij się, że nowo utworzony projekt jest wybrany w górnym pasku

---

## Krok 2 – Włącz Google Calendar API

1. W menu bocznym wybierz **API i usługi → Biblioteka**
2. W polu wyszukiwania wpisz `Google Calendar API`
3. Kliknij wynik i naciśnij **Włącz**

---

## Krok 3 – Skonfiguruj ekran zgody OAuth

> Ten krok jest wymagany przez Google – określa jak wygląda ekran autoryzacji.

1. W menu bocznym wybierz **API i usługi → Ekran zgody OAuth**
2. Wybierz typ użytkownika: **Zewnętrzny** (jeśli używasz darmowego konta Google) lub **Wewnętrzny** (Google Workspace)
3. Kliknij **Utwórz**
4. Uzupełnij:
   - **Nazwa aplikacji:** np. `Mikroplaneta Booking`
   - **Adres e-mail pomocy technicznej:** Twój email
   - **Dane kontaktowe dewelopera:** Twój email
5. Kliknij **Zapisz i kontynuuj** przez wszystkie kolejne kroki (Zakresy, Użytkownicy testowi)
6. W sekcji **Użytkownicy testowi** kliknij **Dodaj użytkowników** i dodaj swój adres Gmail, z którego się łączysz
   > ⚠️ Pomiń ten krok tylko jeśli aplikacja jest opublikowana (wymaga weryfikacji Google)

---

## Krok 4 – Utwórz OAuth 2.0 Client ID

1. W menu bocznym wybierz **API i usługi → Dane logowania**
2. Kliknij **Utwórz dane logowania → Identyfikator klienta OAuth**
3. Typ aplikacji: **Aplikacja internetowa**
4. Wpisz nazwę, np. `Mikroplaneta WordPress`
5. W sekcji **Autoryzowane identyfikatory URI przekierowania** kliknij **Dodaj URI**
6. Wklej **Redirect URI** skopiowany z panelu wtyczki:
   ``` 
   https://twoja-domena.pl/wp-admin/admin.php?page=mikroplaneta-booking&gcal_callback=1
   ```
   > 💡 Dokładny Redirect URI znajdziesz w **Booking → Settings → Google Calendar** w polu „Redirect URI do skopiowania".
7. Kliknij **Utwórz**
8. Skopiuj **Client ID** i **Client Secret** z okna które się otworzyło (lub pobierz plik JSON)

---

## Krok 5 – Połącz wtyczkę z Google Calendar

1. W panelu WordPress otwórz **Booking → Settings**
2. Przewiń do sekcji **Google Calendar**
3. Wklej skopiowany **Client ID** i **Client Secret**
4. Kliknij **Zapisz dane**
5. Kliknij **Połącz z Google** – otworzy się okno autoryzacji Google
6. Zaloguj się na konto Google i kliknij **Zezwól**
7. Po powrocie do panelu zobaczysz status: **✅ Połączony**
8. Z listy rozwijanej wybierz **Kalendarz**, do którego mają trafiać rezerwacje
9. Włącz przełącznik **„Włącz automatyczną synchronizację"**
10. Kliknij **Synchronizuj wszystkie rezerwacje** – Twoje istniejące rezerwacje pojawią się w Google Calendar

---

## Rozwiązywanie problemów

### Błąd: `redirect_uri_mismatch` (HTTP 400)

**Przyczyna:** Redirect URI w Google Cloud Console nie zgadza się z tym co generuje wtyczka.

**Rozwiązanie:**
1. Skopiuj Redirect URI z panelu wtyczki (sekcja Google Calendar → pole „Redirect URI")
2. W Google Cloud Console → Dane logowania → Twój Client ID → Edytuj
3. Usuń stary URI i wklej nowy
4. Zapisz i poczekaj kilka minut

---

### Błąd: `access_denied` (HTTP 403)

**Przyczyna:** Twoje konto Google nie jest na liście użytkowników testowych (gdy aplikacja jest w trybie testowym).

**Rozwiązanie:**
1. Otwórz Google Cloud Console → Ekran zgody OAuth → Użytkownicy testowi
2. Dodaj adres email, którym próbujesz się autoryzować
3. Spróbuj ponownie

---

### Ostrzeżenie: „Ta aplikacja nie jest zweryfikowana"

**Przyczyna:** Aplikacja jest w trybie testowym – Google wyświetla ostrzeżenie przed zaufaniem nieznanej aplikacji.

**Rozwiązanie (dla własnego użytku):**
1. Na ekranie ostrzeżenia kliknij **„Zaawansowane"**
2. Kliknij **„Przejdź do [nazwa aplikacji] (niebezpieczne)"**
3. Kliknij **Zezwól**

> To jest całkowicie bezpieczne – aplikacja to Twój własny projekt Google Cloud, a dane dostępowe trafiają wyłącznie do Twojej bazy WordPress.

---

### Rezerwacja nie pojawia się w Google Calendar

1. Sprawdź logi: `wp-content/debug.log` – szukaj `[MikroPlaneta Booking] GCal`
2. Sprawdź czy opcja **„Włącz automatyczną synchronizację"** jest włączona
3. Sprawdź czy wybrałeś właściwy kalendarz w ustawieniach
4. Kliknij **„Synchronizuj wszystkie rezerwacje"** ręcznie

---

## FAQ

**Co się stanie, gdy zmienię hasło do Google?**

Token OAuth nie jest związany z hasłem – możesz zmienić hasło, a integracja będzie działała dalej. Token można unieważnić tylko ręcznie w ustawieniach bezpieczeństwa Google.

**Jak odwołać dostęp wtyczki do mojego kalendarza?**

Metoda 1 (z wtyczki): Booking → Settings → Google Calendar → **Rozłącz**  
Metoda 2 (z Google): [https://myaccount.google.com/permissions](https://myaccount.google.com/permissions) → znajdź aplikację → Usuń dostęp

**Czy dane moich gości trafiają do Google?**

Tak – imię, nazwisko, daty pobytu i notatki trafiają jako treść wydarzenia w Google Calendar. Nie są wysyłane adresy email gości. Jeśli wymagania RODO to problematyczne, wyłącz integrację lub używaj zanonimizowanych notatek.

**Czy integracja działa gdy WordPress jest wyłączony?**

Dane w Google Calendar nie znikają gdy WordPress nie działa – to właśnie cel integracji jako backup. Recepcjonista widzi grafik rezerwacji bezpośrednio w aplikacji Google Calendar na telefonie.

**Czy można mieć kilka kalendarzy (np. dla różnych pokoi)?**

W obecnej wersji (Etap 1) wszystkie rezerwacje trafiają do jednego wybranego kalendarza. Obsługa wielu kalendarzy jest planowana w Etapie 2.
