# ZBD Budownictwo - strona v2

Lekka strona firmowa przygotowana dla zwykĹ‚ego hostingu Apache/PHP, w tym home.pl. Frontend nie wymaga procesu budowania ani Node.js na serwerze.

## Co zawiera projekt

- responsywny frontend HTML/CSS/JavaScript;
- autorskie diagramy i ikony SVG;
- zoptymalizowane obrazy WebP z responsywnym `srcset`;
- formularz PHP z zaĹ‚Ä…cznikami, honeypotem, kontrolÄ… typĂłw plikĂłw i limitem wysyĹ‚ek;
- warstwÄ™ SEO z metadanymi Open Graph, Schema.org, dynamicznym canonicalem i mapÄ… strony;
- prosty, chroniony hasĹ‚em panel do edycji najwaĹĽniejszych tekstĂłw i dwĂłch zdjÄ™Ä‡;
- lokalnie hostowane fonty;
- politykÄ™ bezpieczeĹ„stwa i cache w `.htaccess`.

## Konfiguracja przed publikacjÄ…

1. Skopiuj `config.example.php` jako `config.php`.
2. Ustaw prawdziwÄ… domenÄ™ w `site_url`.
3. Ustaw firmowy adres odbiorcy w `contact_email`.
4. Ustaw adres nadawcy w tej samej domenie w `from_email`.
5. Wygeneruj bezpieczny hash hasĹ‚a do panelu:

   ```bash
   php -r "echo password_hash('BARDZO_MOCNE_HASLO', PASSWORD_DEFAULT);"
   ```

6. Wklej wynik do `admin_password_hash`.

Plik `config.php` jest ignorowany przez Git i blokowany przed publicznym odczytem przez `.htaccess`.

## Publikacja na home.pl

Wgraj caĹ‚y katalog do katalogu domeny przez SFTP/FTP. Serwer powinien obsĹ‚ugiwaÄ‡ PHP, funkcjÄ™ `mail()`, moduĹ‚ `mod_rewrite` i pliki `.htaccess`. Strona produkcyjna jest serwowana przez `index.php`, ktĂłry dodaje canonical, peĹ‚ny adres obrazu Open Graph oraz adres strony do Schema.org. Statyczny `index.html` pozostaje wariantem awaryjnym i umoĹĽliwia szybki lokalny podglÄ…d.

Po publikacji sprawdĹş:

- `https://twoja-domena.pl/`;
- `https://twoja-domena.pl/sitemap.xml`;
- wysyĹ‚kÄ™ formularza razem ze zdjÄ™ciem;
- zapis treĹ›ci w `https://twoja-domena.pl/admin/`;
- uprawnienia zapisu do `data/content.json` oraz `assets/images/uploads/`.

JeĹĽeli hosting blokuje `mail()`, formularz naleĹĽy przeĹ‚Ä…czyÄ‡ na uwierzytelnione SMTP. Nie zapisuj hasĹ‚a pocztowego w repozytorium.

## Edycja treĹ›ci

Panel `/admin/` zapisuje teksty do `data/content.json`. Frontend pobiera ten plik po zaĹ‚adowaniu strony, zachowujÄ…c jednoczeĹ›nie domyĹ›lne treĹ›ci HTML dla SEO i na wypadek bĹ‚Ä™du sieciowego.

Panel pozwala rĂłwnieĹĽ zmieniÄ‡ dwa zdjÄ™cia sekcji â€žW praktyceâ€ť. Wgrywane obrazy trafiajÄ… do `assets/images/uploads/`. Przed publikacjÄ… nowych materiaĹ‚Ăłw naleĹĽy potwierdziÄ‡, ĹĽe przedstawiajÄ… rzeczywiste prace lub obiekty i ĹĽe firma ma prawo do ich wykorzystania.

## Dane nadal wymagajÄ…ce zatwierdzenia

Projekt celowo nie zawiera wymyĹ›lonych danych. Przed finalnym uruchomieniem trzeba dostarczyÄ‡:

- peĹ‚ne dane identyfikacyjne administratora danych;
- zatwierdzony e-mail, telefon i obszar dziaĹ‚ania firmy;
- docelowÄ… domenÄ™;
- oficjalny logotyp ZBD i ewentualnie zatwierdzony znak partnerstwa PRINZ;
- dokumentacjÄ™ prawdziwych realizacji, jeĹ›li sekcja ma zostaÄ‡ zmieniona z â€žW praktyceâ€ť na â€žRealizacjeâ€ť.

Po otrzymaniu danych naleĹĽy zaktualizowaÄ‡ stopkÄ™, informacjÄ™ o prywatnoĹ›ci oraz Schema.org.

