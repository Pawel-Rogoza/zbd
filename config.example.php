<?php
return [
    'site_url' => 'https://zbd.pawelrogoza.pl',
    // Losowy sekret >= 32 znaków, np. `php -r "echo bin2hex(random_bytes(32));"`.
    'app_secret' => '',
    // Ustaw prawdziwy adres, na który mają trafiać zapytania z formularza.
    'contact_email' => 'zbd@plechowski.pl',
    // Adres nadawcy musi istnieć w tej samej domenie i mieć poprawne SPF/DKIM.
    'from_email' => 'zbd@plechowski.pl',
    // DO_UZUPELNIENIA: tylko po zatwierdzeniu przez klienta; puste pola nie są publikowane.
    'contact_phone' => '+48509384181',
    'service_area' => '',
    'response_time' => '',
    'logo_url' => 'assets/favicon.svg',
    // Produkcyjnie używaj uwierzytelnionego SMTP z PHPMailer.
    'mail_transport' => 'smtp',
    'phpmailer_autoload' => '/home/zbd/vendor/autoload.php',
    'smtp_host' => '',
    'smtp_port' => 587,
    'smtp_encryption' => 'tls',
    'smtp_username' => '',
    'smtp_password' => '',
    'smtp_timeout' => 10,
    // Aktualne zakresy proxy Cloudflare; nie ufaj CF-Connecting-IP spoza tej listy.
    'cloudflare_trusted_proxies' => [
        '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22',
        '141.101.64.0/18', '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20',
        '197.234.240.0/22', '198.41.128.0/17', '162.158.0.0/15', '104.16.0.0/13',
        '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22', '2400:cb00::/32',
        '2606:4700::/32', '2803:f800::/32', '2405:b500::/32', '2405:8100::/32',
        '2a06:98c0::/29', '2c0f:f248::/32',
    ],
    // Wygeneruj poleceniem: php -r "echo password_hash('TWOJE_HASLO', PASSWORD_DEFAULT);"
    'admin_enabled' => false,
    'admin_password_hash' => '',
];

