# ZBD Budownictwo - strona v2

Lekka strona firmowa przygotowana dla PHP-FPM i Nginx na VPS. Frontend nie wymaga procesu budowania ani Node.js na serwerze.

## Co zawiera projekt

- responsywny frontend HTML/CSS/JavaScript;
- autorskie diagramy i ikony SVG;
- zoptymalizowane obrazy WebP z responsywnym `srcset`;
- formularz PHP z tokenem CSRF/czasowym, honeypotem i limitami wysyłek;
- sekcję wideo z czterema materiałami YouTube ładowanymi dopiero po kliknięciu;
- warstwę SEO z metadanymi Open Graph, Schema.org, dynamicznym canonicalem i mapą strony;
- opcjonalny, chroniony hasłem panel do edycji najważniejszych tekstów i dwóch zdjęć;
- lokalnie hostowane fonty;
- politykę bezpieczeństwa i cache w `.htaccess` oraz gotowy przykład vhosta Nginx.

## Konfiguracja przed publikacją

1. Skopiuj `config.example.php` jako `config.php`.
2. Potwierdź domenę `https://zbd.pawelrogoza.pl` w `site_url`.
3. Wygeneruj `app_secret` i ustaw prawdziwy firmowy adres odbiorcy w `contact_email`.
4. Ustaw adres nadawcy w tej samej domenie w `from_email`, a następnie skonfiguruj SMTP i ścieżkę do autoloadera PHPMailer. Hasło SMTP pozostaje wyłącznie w `config.php`.
5. Jeśli panel ma być używany, ustaw `admin_enabled` na `true` i wygeneruj bezpieczny hash hasła:

   ```bash
   php -r "echo password_hash('BARDZO_MOCNE_HASLO', PASSWORD_DEFAULT);"
   ```

6. Wklej wynik do `admin_password_hash`.

Jeśli panel nie ma być używany, pozostaw `admin_enabled` wyłączone — `/admin/` zwróci 404.

Plik `config.php` jest ignorowany przez Git i blokowany przed publicznym odczytem przez `.htaccess`.

## Publikacja na VPS

Przykładowy vhost znajduje się w `nginx/zbd.pawelrogoza.pl.conf.example`. Skopiuj go do `/etc/nginx/conf.d/`, sprawdź `nginx -t` i przeładuj Nginx. Strona produkcyjna jest serwowana przez `index.php`, a `/index.html` przekierowuje na `/`. Frontend ma również awaryjną normalizację adresu, która zachowuje fragment, np. `/index.html#osuszanie` → `/#osuszanie`.

Po publikacji sprawdź:

- `https://zbd.pawelrogoza.pl/`;
- `https://zbd.pawelrogoza.pl/sitemap.xml`;
- wysyłkę formularza przez SMTP oraz odpowiedź przez `Reply-To`;
- zapis treści w `https://zbd.pawelrogoza.pl/admin/`, jeśli panel został włączony;
- uprawnienia zapisu do `data/content.json` oraz `assets/images/uploads/`.

PHPMailer powinien być instalowany poza repozytorium (np. `composer install --no-dev` w katalogu aplikacji, do `/home/zbd/vendor`). Formularz domyślnie wymaga SMTP; tryb `mail` jest dostępny wyłącznie jako jawna konfiguracja awaryjna.

## Aktualizacja na VPS

Skrypt `deploy-zbd` wykonuje backup konfiguracji i treści, pobiera `main`, robi bezpieczny fast-forward, restartuje PHP-FPM oraz przeładowuje Nginx:

```bash
sudo install -m 755 deploy-zbd /usr/local/sbin/deploy-zbd
sudo deploy-zbd
```

Jeśli usługa PHP-FPM ma niestandardową nazwę:

```bash
sudo ZBD_PHP_FPM_SERVICE=php-fpm.service deploy-zbd
```

## Edycja treści

Panel `/admin/` zapisuje teksty do `data/content.json` atomowo, po utworzeniu kopii `content.json.bak`. `index.php` renderuje treść po stronie serwera, więc HTML bez JavaScript pokazuje aktualną wersję; JSON nie jest publicznym endpointem.

Panel pozwala również zmienić dwa zdjęcia sekcji „W praktyce”. Wgrywane obrazy trafiają do `assets/images/uploads/`. Przed publikacją nowych materiałów należy potwierdzić, że przedstawiają rzeczywiste prace lub obiekty i że firma ma prawo do ich wykorzystania.

## Dane nadal wymagające zatwierdzenia

Projekt celowo nie zawiera wymyślonych danych. Przed finalnym uruchomieniem trzeba dostarczyć:

- pełne dane identyfikacyjne administratora danych;
- zatwierdzony e-mail, telefon i obszar działania firmy;
- docelową domenę;
- oficjalny logotyp ZBD i ewentualnie zatwierdzony znak partnerstwa PRINZ;
- dokumentację prawdziwych realizacji, jeśli sekcja ma zostać zmieniona z „W praktyce” na „Realizacje”.

Po otrzymaniu danych należy zaktualizować stopkę, informację o prywatności oraz Schema.org.

Przed produkcją trzeba jeszcze zatwierdzić przez klienta wartości `service_area` i `response_time` oraz skonfigurować rekordy SPF, DKIM i DMARC dla domeny nadawcy. Telefon i adres e-mail są skonfigurowane w `config.php` na podstawie danych klienta.

