# ZBD Budownictwo - strona v2

Lekka strona firmowa przygotowana dla zwykłego hostingu Apache/PHP, w tym home.pl. Frontend nie wymaga procesu budowania ani Node.js na serwerze.

## Co zawiera projekt

- responsywny frontend HTML/CSS/JavaScript;
- autorskie diagramy i ikony SVG;
- zoptymalizowane obrazy WebP z responsywnym `srcset`;
- formularz PHP z honeypotem i limitem wysyłek;
- sekcję wideo z czterema osadzonymi materiałami YouTube partnera;
- warstwę SEO z metadanymi Open Graph, Schema.org, dynamicznym canonicalem i mapą strony;
- prosty, chroniony hasłem panel do edycji najważniejszych tekstów i dwóch zdjęć;
- lokalnie hostowane fonty;
- politykę bezpieczeństwa i cache w `.htaccess`.

## Konfiguracja przed publikacją

1. Skopiuj `config.example.php` jako `config.php`.
2. Ustaw prawdziwą domenę w `site_url`.
3. Ustaw firmowy adres odbiorcy w `contact_email`.
4. Ustaw adres nadawcy w tej samej domenie w `from_email`.
5. Wygeneruj bezpieczny hash hasła do panelu:

   ```bash
   php -r "echo password_hash('BARDZO_MOCNE_HASLO', PASSWORD_DEFAULT);"
   ```

6. Wklej wynik do `admin_password_hash`.

Plik `config.php` jest ignorowany przez Git i blokowany przed publicznym odczytem przez `.htaccess`.

## Publikacja na home.pl

Wgraj cały katalog do katalogu domeny przez SFTP/FTP. Serwer powinien obsługiwać PHP, funkcję `mail()`, moduł `mod_rewrite` i pliki `.htaccess`. Strona produkcyjna jest serwowana przez `index.php`, który dodaje canonical, pełny adres obrazu Open Graph oraz adres strony do Schema.org. Statyczny `index.html` pozostaje wariantem awaryjnym i umożliwia szybki lokalny podgląd.

Po publikacji sprawdź:

- `https://twoja-domena.pl/`;
- `https://twoja-domena.pl/sitemap.xml`;
- wysyłkę formularza bez załączników;
- zapis treści w `https://twoja-domena.pl/admin/`;
- uprawnienia zapisu do `data/content.json` oraz `assets/images/uploads/`.

Jeżeli hosting blokuje `mail()`, formularz należy przełączyć na uwierzytelnione SMTP. Nie zapisuj hasła pocztowego w repozytorium.

## Edycja treści

Panel `/admin/` zapisuje teksty do `data/content.json`. Frontend pobiera ten plik po załadowaniu strony, zachowując jednocześnie domyślne treści HTML dla SEO i na wypadek błędu sieciowego.

Panel pozwala również zmienić dwa zdjęcia sekcji „W praktyce”. Wgrywane obrazy trafiają do `assets/images/uploads/`. Przed publikacją nowych materiałów należy potwierdzić, że przedstawiają rzeczywiste prace lub obiekty i że firma ma prawo do ich wykorzystania.

## Dane nadal wymagające zatwierdzenia

Projekt celowo nie zawiera wymyślonych danych. Przed finalnym uruchomieniem trzeba dostarczyć:

- pełne dane identyfikacyjne administratora danych;
- zatwierdzony e-mail, telefon i obszar działania firmy;
- docelową domenę;
- oficjalny logotyp ZBD i ewentualnie zatwierdzony znak partnerstwa PRINZ;
- dokumentację prawdziwych realizacji, jeśli sekcja ma zostać zmieniona z „W praktyce” na „Realizacje”.

Po otrzymaniu danych należy zaktualizować stopkę, informację o prywatności oraz Schema.org.
