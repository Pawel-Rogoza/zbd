# Audyt i plan wydania — ZBD Budownictwo

Data audytu: 2026-08-14  
Zakres: kod w repozytorium, widok desktop 1440×1000, widok mobilny 390×844, menu, formularz, panel, SEO, prywatność, bezpieczeństwo i aktualna wersja online `https://zbd.pawelrogoza.pl/`.

## Wniosek

Kierunek wizualny jest spójny i profesjonalny. Strona jest responsywna, nie ma poziomego przepełnienia, ma poprawną hierarchię nagłówków, link pomijający, etykiety formularza, widoczny focus, lokalne fonty i lekką warstwę JavaScript. Nie ma potrzeby przebudowy designu.

Strona nie jest jednak jeszcze gotowa do przekazania klientowi. Blokują ją przede wszystkim konfiguracja produkcyjna, brak zatwierdzonych danych firmy i kontaktu, niewystarczająca informacja o prywatności oraz brak potwierdzonej dostarczalności formularza. Aktualna wersja online jest starsza od repozytorium i ma osobne błędy wydaniowe.

## P0 — blokery wydania

### 1. Zebrać i zatwierdzić dane od klienta

Potrzebne dane:

- pełna nazwa prawna administratora danych i dane kontaktowe;
- publiczny telefon, e-mail, obszar działania oraz adres, jeśli ma być publikowany;
- docelowa domena;
- adres odbiorczy formularza i adres nadawcy w domenie;
- decyzja o okresie przechowywania zapytań i podstawie prawnej przetwarzania;
- oficjalne logo ZBD;
- potwierdzenie prawa do zdjęć, filmów i używania nazwy/oznaczeń PRINZ oraz twierdzenia „firma partnerska”;
- prawdziwe realizacje, referencje lub certyfikaty, jeżeli mają być pokazane;
- decyzja, czy klient ma otrzymać panel edycji, czy panel ma pozostać wyłączony.

Nie wymyślać brakujących danych ani parametrów usług.

Kryterium odbioru: wszystkie publiczne dane, twierdzenia i materiały mają pisemne zatwierdzenie klienta.

### 2. Naprawić i zweryfikować konfigurację produkcyjną

Stan online z dnia audytu:

- canonical i `og:image` wskazują `https://twoja-domena.pl/`;
- `/sitemap.xml` zawiera `https://twoja-domena.pl/`;
- `/data/content.json` zwraca HTTP 403, więc panel nie może zasilać frontendu;
- `/admin/` informuje, że panel jest wyłączony z powodu pustego `admin_password_hash`;
- odpowiedź strony nie zawiera HSTS, CSP, `X-Content-Type-Options`, `X-Frame-Options` ani `Referrer-Policy` i ujawnia `X-Powered-By: PHP/8.5.9`;
- produkcja ładuje `styles.css?v=3` i `script.js?v=3`, a repozytorium używa nowszych wersji.

Prace:

1. Wdrożyć bieżący kod oraz aktualny vhost Nginx.
2. Utworzyć produkcyjny `config.php` z `site_url=https://zbd.pawelrogoza.pl`, prawdziwymi adresami pocztowymi i mocnym hashem hasła panelu, jeżeli panel ma być używany.
3. Ustawić poprawne uprawnienia dla `data/content.json` i `assets/images/uploads/`.
4. Upewnić się, że Nginx pozwala publicznie odczytać wyłącznie `data/content.json`, a nie cały katalog `data`.
5. Wyłączyć `expose_php` i potwierdzić nagłówki bezpieczeństwa na każdej odpowiedzi, także błędach i zasobach statycznych.
6. Dodać do `robots.txt` wiersz `Sitemap: https://zbd.pawelrogoza.pl/sitemap.xml`.
7. Ustawić reguły cache Cloudflare zgodne z originem; obecna produkcja daje zasobom tylko około 4 godzin cache.

Kryterium odbioru: canonical, Open Graph i mapa strony wskazują wyłącznie właściwą domenę; `data/content.json` zwraca 200 i `Cache-Control: no-store`; panel ma świadomie wybrany stan; nagłówki bezpieczeństwa są obecne; produkcja używa bieżących wersji CSS/JS.

### 3. Uzupełnić informację o prywatności i zmienić treść zgody

Obecny dokument nie podaje pełnej tożsamości administratora, konkretnej podstawy prawnej ani dostatecznie określonego okresu przechowywania. Artykuł 13 RODO wymaga między innymi tożsamości i danych kontaktowych administratora, celów i podstawy prawnej, odbiorców, okresu lub kryteriów przechowywania, praw osoby oraz informacji o obowiązku podania danych i konsekwencjach ich niepodania.

Prace:

1. Uzupełnić dokument prawdziwymi danymi i poddać go akceptacji osoby odpowiedzialnej po stronie klienta lub prawnika.
2. Ustalić prawidłową podstawę prawną dla odpowiedzi na zapytanie. Nie zakładać automatycznie, że musi nią być zgoda.
3. Po wyborze podstawy zmienić obowiązkowy checkbox z „Wyrażam zgodę na kontakt...” na odpowiednią, jednoznaczną treść; przy podstawie innej niż zgoda może to być potwierdzenie zapoznania się z informacją o prywatności.
4. Opisać faktycznych dostawców hostingu, poczty i Cloudflare oraz ewentualne transfery danych poza EOG.
5. Zsynchronizować opis YouTube z faktycznym sposobem ładowania odtwarzaczy.

Kryterium odbioru: treść jest zgodna z realnym przepływem danych, zatwierdzona i nie zawiera placeholderów ani ogólników typu „nie dłużej niż potrzeba”, jeśli klient potrafi podać konkretny okres lub kryterium.

### 4. Doprowadzić formularz do niezawodnej wysyłki

Prace w `kontakt.php` i konfiguracji serwera:

1. Potwierdzić dostarczalność `mail()` na docelowym VPS. Preferowane rozwiązanie produkcyjne: uwierzytelnione SMTP z biblioteką taką jak PHPMailer, dane dostępowe poza repozytorium i jawny timeout.
2. Skonfigurować SPF, DKIM i DMARC dla domeny nadawcy.
3. Zachować nadawcę w domenie, a adres użytkownika wyłącznie w `Reply-To`.
4. Dodać log technicznych błędów wysyłki bez zapisywania treści zapytania i danych osobowych.
5. Naprawić limitowanie po IP za Cloudflare. Obecne użycie `REMOTE_ADDR` może widzieć adres węzła Cloudflare i wspólnie blokować różnych użytkowników. Skonfigurować zaufane `real_ip` w Nginx albo bezpiecznie obsłużyć `CF-Connecting-IP` tylko dla ruchu od Cloudflare.
6. Wzmocnić ochronę antyspamową: token formularza/CSRF lub podpisany token czasowy, atomowy licznik limitu i ograniczenie częstotliwości również na poziomie Nginx/Cloudflare. Honeypot i łatwy do podrobienia czas startu nie powinny być jedyną ochroną.
7. Zachować wartości formularza po błędzie zamiast zerować cały formularz albo przejść na odpowiedź AJAX z dostępnym komunikatem.

Kryterium odbioru: testowe zapytanie dochodzi do skrzynki i odpowiedź można wysłać przez `Reply-To`; błędne dane nie wysyłają wiadomości; awaria SMTP daje czytelny komunikat; limit jednego użytkownika nie blokuje innych.

### 5. Zdecydować o panelu i przygotować go do przekazania

Jeżeli panel ma być używany:

- ustawić mocne, unikalne hasło i przekazać je bezpiecznym kanałem;
- sprawdzić logowanie, wylogowanie, blokadę prób, zapis tekstu i oba uploady;
- dodać limity długości pól po stronie serwera;
- walidować wymiary obrazu, zmniejszać i ponownie kodować uploady do WebP/AVIF zamiast przechowywać dowolny plik do 8 MB;
- zapisywać `content.json` atomowo (plik tymczasowy + rename) i tworzyć wersję zapasową;
- usuwać lub archiwizować nieużywane uploady;
- poprawić limit logowania tak, aby udane logowanie czyściło licznik.

Jeżeli panel nie ma być używany: usunąć publiczną trasę `/admin/` lub jawnie zwracać 404, zamiast zostawiać ekran informujący o wyłączonej konfiguracji.

Kryterium odbioru: klient otrzymuje działający panel z instrukcją i kopią zapasową albo panel nie jest publicznie dostępny.

## P1 — poprawki o wysokiej wartości

### 6. Zwiększyć konwersję i wiarygodność

1. Dodać prawdziwy telefon i e-mail jako linki `tel:` i `mailto:` w sekcji kontaktu i stopce; telefon warto umieścić również w nagłówku lub mobilnym CTA.
2. Podać obszar działania i orientacyjny czas odpowiedzi.
3. Rozważyć dwa mobilne CTA: „Zadzwoń” i „Wyślij zapytanie”. Obecnie stałe CTA tylko przewija do formularza i duplikuje przycisk widoczny w hero.
4. Ukrywać stałe CTA także wtedy, gdy przycisk hero jest widoczny oraz gdy otwarte jest menu.
5. Pokazać 2–4 prawdziwe realizacje z miejscem, problemem, zakresem prac i rezultatem. Nie dodawać anonimowych, wymyślonych opinii.
6. Uzupełnić sekcję o firmie o konkretne, weryfikowalne informacje: doświadczenie, kwalifikacje, gwarancję lub certyfikaty — tylko jeśli są prawdziwe.
7. Skonsultować tekst techniczny ze specjalistą: jasno odróżnić wykonanie bariery przeciwwilgociowej od późniejszego wysychania muru i zaznaczyć konieczność rozpoznania innych źródeł wilgoci.

Kryterium odbioru: użytkownik może skontaktować się bez formularza, wie gdzie firma działa i widzi przynajmniej jeden mocny, zweryfikowany dowód wiarygodności.

### 7. Ładować filmy YouTube dopiero po świadomym kliknięciu

Obecne cztery lazy iframe'y nadal łączą się z YouTube po dojściu użytkownika do sekcji. `youtube-nocookie.com` jest prawidłowym trybem zwiększonej prywatności, ale nie zastępuje świadomego ładowania zewnętrznego odtwarzacza.

Prace:

1. Zastąpić iframe'y lokalnymi posterami i dostępnym przyciskiem „Odtwórz film w YouTube”.
2. Dopiero po kliknięciu podmieniać poster na iframe `youtube-nocookie.com`.
3. Zapewnić zwykły link do filmu jako fallback bez JavaScript.
4. Zaktualizować CSP i informację o prywatności do faktycznego działania.

Kryterium odbioru: przed kliknięciem filmu nie ma żądań do domen YouTube; klawiatura i czytnik ekranu mogą uruchomić film; brak JavaScript nie blokuje dostępu do materiału.

### 8. Dopracować menu i dostępność

1. Na mobile zrobić pełny panel/overlay z tłem zasłaniającym stronę albo wyraźnym backdropem; obecnie pod menu widać i można pomylić elementy strony.
2. Ukryć stałe CTA przy otwartym menu.
3. Dodać pułapkę fokusu w menu, przywrócenie fokusu do przycisku po zamknięciu i poprawny stan po zmianie breakpointu.
4. Uprościć `aria-label` logo do nazwy zawierającej dokładnie widoczny tekst, jeśli ponowny test nadal zgłasza `label-content-name-mismatch`.
5. Ponownie sprawdzić kontrast drobnych czerwonych etykiet na granatowym i jasnoszarym tle.
6. Przetestować klawiaturą, przy powiększeniu 200% i z `prefers-reduced-motion`.

Kryterium odbioru: brak krytycznych błędów axe/Lighthouse, wszystkie elementy obsługiwalne klawiaturą, fokus zawsze widoczny i logiczny.

### 9. Dopracować SEO lokalne i metadane

1. Po otrzymaniu danych uzupełnić Schema.org `HomeAndConstructionBusiness` o `url`, `telephone`, prawdziwy adres lub `areaServed`, e-mail i logo.
2. Dodać miejscowość/region do title, description i treści tylko zgodnie z faktycznym obszarem działania.
3. Dodać `og:image:width`, `og:image:height`, `og:image:alt` oraz kartę społecznościową; przetestować podgląd udostępniania.
4. Dodać właściwe ikony 192/512 i `apple-touch-icon` albo usunąć manifest, jeśli strona nie ma być instalowalna.
5. Po wydaniu dodać domenę do Google Search Console i zgłosić sitemapę.
6. Rozważyć krótkie FAQ odpowiadające na prawdziwe pytania klientów, bez sztucznego upychania słów kluczowych.

Kryterium odbioru: Rich Results Test nie zgłasza błędów danych, podgląd udostępnienia ma właściwy obraz i opis, a Search Console widzi poprawny canonical i sitemapę.

## P2 — jakość i utrzymanie

### 10. Renderować treści panelu po stronie PHP

Obecnie `index.php` zwraca domyślny `index.html`, a `script.js` dopiero później pobiera `data/content.json`. Skutki: migotanie/CLS po większych edycjach, niespójność źródła HTML z treścią klienta oraz zależność panelu od publicznego JSON-u.

Prace:

1. Wczytywać i bezpiecznie renderować `content.json` w `index.php` już w odpowiedzi HTML.
2. Utrzymać jedno źródło prawdy dla treści zamiast ręcznie synchronizować HTML i JSON.
3. Aktualizować z tego samego źródła również odpowiednie meta description/tytuł, jeśli panel ma je edytować.
4. Zostawić wersję awaryjną na wypadek uszkodzenia JSON-u i logować błąd bez danych osobowych.

Kryterium odbioru: HTML bez JavaScript pokazuje aktualną treść panelu; edycja nie powoduje widocznego przeskoku; uszkodzony JSON nie wyłącza strony.

### 11. Uporządkować zasoby i wydajność

- Usunąć z paczki produkcyjnej nieużywane PNG o łącznej wadze około 8,4 MB albo przenieść je do katalogu źródłowego niewysyłanego na serwer.
- Dodać mniejszy wariant hero (około 480 px) i poprawić `sizes`, jeżeli ponowny Lighthouse potwierdzi nadmiarowy transfer.
- Zachować responsywne WebP, `width`/`height`, lazy loading zdjęć poniżej hero i `fetchpriority=high` dla LCP.
- Minifikować CSS/JS w procesie wydania lub przynajmniej włączyć Brotli/Gzip.
- Stosować wersjonowane nazwy lub konsekwentnie zwiększać query version po każdej zmianie; nie łączyć `immutable` z niezmienionym adresem zasobu.
- Usunąć z serwera katalogi robocze i raporty z `tmp/`.

Kryterium odbioru: Lighthouse mobile ≥90 w każdej kategorii, LCP ≤2,5 s, CLS ≤0,1, brak zbędnych zasobów w transferze i paczce produkcyjnej.

## Kolejność wdrożenia dla kolejnego modelu

1. Nie zmieniać designu globalnie; zachować obecną typografię, granat/czerwień, rytm sekcji i zdjęcia do czasu decyzji klienta.
2. Wdrożyć techniczne P0 możliwe bez danych klienta: formularz, Cloudflare IP, panel, atomowe zapisy, click-to-load YouTube, dostępność menu, serwerowe renderowanie treści.
3. Wstawić jawne znaczniki `DO_UZUPELNIENIA` wyłącznie w konfiguracji/deweloperskim checklistcie, nigdy w publicznym HTML.
4. Po otrzymaniu danych klienta uzupełnić kontakt, prywatność, SEO i dowody wiarygodności.
5. Wdrożyć aktualny Nginx i kod na staging, nie bezpośrednio na produkcję.
6. Wykonać pełną checklistę odbiorową.
7. Dopiero po akceptacji stagingu wykonać backup i publikację produkcyjną.

## Checklista odbiorowa przed przekazaniem klientowi

- [ ] Desktop: 1440×900 i 1920×1080.
- [ ] Tablet: 768×1024 i 1024×768.
- [ ] Mobile: 360×800, 390×844 i 412×915.
- [ ] Chrome, Firefox i Safari/iOS.
- [ ] Klawiatura, 200% zoom, reduced motion, czytnik ekranu w kluczowym flow.
- [ ] Wszystkie linki, kotwice, menu, polityka prywatności i cztery filmy.
- [ ] Formularz: puste pola, sam telefon, sam e-mail, oba kontakty, błędny format, brak checkboxa, sukces, awaria SMTP i limit.
- [ ] Nie testować wysyłki danymi prawdziwej osoby; użyć uzgodnionej skrzynki i danych testowych.
- [ ] Panel: błędne hasło, poprawne hasło, wylogowanie, zapis tekstu, dwa obrazy, odtworzenie kopii.
- [ ] Canonical, OG, Schema.org, robots, sitemap i 404.
- [ ] Nagłówki bezpieczeństwa, brak `X-Powered-By`, HTTPS i przekierowanie HTTP→HTTPS.
- [ ] Brak publicznego dostępu do `config.php`, kopii zapasowych, repozytorium Git i plików roboczych.
- [ ] Brak żądań do YouTube przed kliknięciem odtwarzania.
- [ ] Lighthouse mobile ≥90/90/90/90; brak krytycznych błędów dostępności.
- [ ] SPF, DKIM i DMARC przechodzą; wiadomość nie trafia do spamu.
- [ ] Backup wykonany i procedura odtworzenia sprawdzona.

## Materiały źródłowe audytu

- RODO, art. 12–13: https://eur-lex.europa.eu/eli/reg/2016/679/oj?locale=pl
- YouTube — tryb zwiększonej prywatności: https://support.google.com/youtube/answer/171780?hl=pl
- Lokalny raport Lighthouse (dotyczy starszej wersji online): `tmp/zbd-lighthouse.json` — wynik 94 Performance, 97 Accessibility, 100 Best Practices, 100 SEO; raport nie może być traktowany jako końcowy po zmianach i wdrożeniu.

