<?php
declare(strict_types=1);

$root = __DIR__;
$configPath = $root . '/config.php';
$config = is_file($configPath) ? require $configPath : [];
$config = is_array($config) ? $config : [];
$siteUrl = rtrim((string) ($config['site_url'] ?? 'https://zbd.pawelrogoza.pl'), '/');
$templatePath = $root . '/index.html';
$contentPath = $root . '/data/content.json';
$html = is_file($templatePath) ? (string) file_get_contents($templatePath) : '';

function escapeHtml(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function contentValue(array $source, string $path): string
{
    $value = $source;
    foreach (explode('.', $path) as $key) {
        if (!is_array($value) || !array_key_exists($key, $value)) return '';
        $value = $value[$key];
    }
    return is_string($value) ? $value : '';
}

function textHtml(string $value): string
{
    $lines = preg_split('/\R/u', $value) ?: [$value];
    return implode('<br />', array_map(static fn (string $line): string => escapeHtml($line), $lines));
}

function renderEditableContent(string $html, array $content): string
{
    $pattern = '/(?P<open><(?P<tag>h[1-6]|p)\b[^>]*\bdata-content="(?P<path>[^"]+)"[^>]*>)(?P<body>.*?)(?P<close><\/(?P=tag)>)/isu';
    $rendered = preg_replace_callback($pattern, static function (array $match) use ($content): string {
        $value = contentValue($content, $match['path']);
        return $match['open'] . ($value !== '' ? textHtml($value) : $match['body']) . $match['close'];
    }, $html);
    return is_string($rendered) ? $rendered : $html;
}

function safeImagePath(string $path): string
{
    return preg_match('#^assets/images/[A-Za-z0-9._/-]+\.(?:webp|jpe?g|avif)$#i', $path) && !str_contains($path, '..') ? $path : '';
}

function renderPracticeContent(string $html, array $content): string
{
    $items = $content['practice']['items'] ?? [];
    if (!is_array($items)) return $html;
    for ($index = 0; $index < 2; $index++) {
        $item = $items[$index] ?? [];
        if (!is_array($item)) continue;
        $image = safeImagePath((string) ($item['image'] ?? ''));
        $alt = escapeHtml((string) ($item['alt'] ?? ''));
        $label = escapeHtml((string) ($item['label'] ?? ''));
        $title = escapeHtml((string) ($item['title'] ?? ''));
        $pattern = '/(?P<open><figure\b[^>]*\bdata-practice-index=["\']' . $index . '["\'][^>]*>)(?P<body>.*?)(?P<close><\/figure>)/isu';
        $html = preg_replace_callback($pattern, static function (array $match) use ($image, $alt, $label, $title): string {
            $body = $match['body'];
            if ($image !== '') {
                $body = preg_replace_callback('/<img\b[^>]*>/iu', static function (array $img) use ($image, $alt): string {
                    $tag = preg_replace_callback('/\bsrc=["\'][^"\']*["\']/iu', static function (array $attr) use ($image): string { return 'src="' . escapeHtml($image) . '"'; }, $img[0], 1) ?? $img[0];
                    if ($tag === $img[0]) $tag = preg_replace('/<img\b/iu', '<img src="' . escapeHtml($image) . '"', $tag, 1) ?? $tag;
                    return $alt !== '' ? (preg_replace_callback('/\balt=["\'][^"\']*["\']/iu', static function () use ($alt): string { return 'alt="' . $alt . '"'; }, $tag, 1) ?? $tag) : $tag;
                }, $body) ?? $body;
                if (str_starts_with($image, 'assets/images/uploads/')) {
                    $body = preg_replace_callback('/<source\b[^>]*>/iu', static function (array $source) use ($image): string {
                        $tag = preg_replace_callback('/\bsrcset=["\'][^"\']*["\']/iu', static function () use ($image): string { return 'srcset="' . escapeHtml($image) . '"'; }, $source[0], 1) ?? $source[0];
                        return $tag === $source[0] ? (preg_replace('/<source\b/iu', '<source srcset="' . escapeHtml($image) . '"', $tag, 1) ?? $tag) : $tag;
                    }, $body) ?? $body;
                }
            }
            if ($label !== '') $body = preg_replace_callback('/<figcaption\b[^>]*>.*?<span\b[^>]*>.*?<\/span>/isu', static function (array $caption) use ($label): string {
                return preg_replace_callback('/>[^<]*<\/span>$/isu', static function () use ($label): string { return '>' . $label . '</span>'; }, $caption[0]) ?? $caption[0];
            }, $body) ?? $body;
            if ($title !== '') $body = preg_replace_callback('/<figcaption\b[^>]*>.*?<strong\b[^>]*>.*?<\/strong>/isu', static function (array $caption) use ($title): string {
                return preg_replace_callback('/>[^<]*<\/strong>$/isu', static function () use ($title): string { return '>' . $title . '</strong>'; }, $caption[0]) ?? $caption[0];
            }, $body) ?? $body;
            return $match['open'] . $body . $match['close'];
        }, $html) ?? $html;
    }
    return $html;
}

function replaceMeta(string $html, string $attribute, string $name, string $value): string
{
    if ($value === '') return $html;
    $encoded = escapeHtml($value);
    $pattern = '/(<meta\b[^>]*\b' . preg_quote($attribute, '/') . '=["\']' . preg_quote($name, '/') . '["\'][^>]*\bcontent=["\'])[^"\']*(["\'])/iu';
    $replaced = preg_replace_callback($pattern, fn (array $match): string => $match[1] . $encoded . $match[2], $html);
    return is_string($replaced) ? $replaced : $html;
}

function replaceHiddenValue(string $html, string $name, string $value): string
{
    $encoded = escapeHtml($value);
    $pattern = '/(<input\b[^>]*\bname=["\']' . preg_quote($name, '/') . '["\'][^>]*\bvalue=["\'])[^"\']*(["\'])/iu';
    $replaced = preg_replace_callback($pattern, fn (array $match): string => $match[1] . $encoded . $match[2], $html);
    return is_string($replaced) ? $replaced : $html;
}

function replaceFormState(string $html, array $state): string
{
    foreach (['name', 'phone', 'email', 'location'] as $field) {
        $value = isset($state[$field]) && is_string($state[$field]) ? $state[$field] : '';
        $encoded = escapeHtml($value);
        $pattern = '/<input\b(?=[^>]*\bname=["\']' . preg_quote($field, '/') . '["\'])[^>]*>/iu';
        $html = (string) preg_replace_callback($pattern, static function (array $match) use ($encoded): string {
            $tag = preg_replace('/\s+value=["\'][^"\']*["\']/iu', '', $match[0]) ?? $match[0];
            return preg_replace_callback('/<input\b/iu', fn (): string => '<input value="' . $encoded . '"', $tag, 1) ?? $tag;
        }, $html);
    }

    $message = isset($state['message']) && is_string($state['message']) ? $state['message'] : '';
    $html = (string) preg_replace_callback('/(<textarea\b(?=[^>]*\bname=["\']message["\'])[^>]*>).*?(<\/textarea>)/isu', static function (array $match) use ($message): string {
        return $match[1] . escapeHtml($message) . $match[2];
    }, $html);

    $buildingType = isset($state['building_type']) && is_string($state['building_type']) ? $state['building_type'] : '';
    $html = (string) preg_replace_callback('/(<select\b(?=[^>]*\bname=["\']building_type["\'])[^>]*>)(.*?)(<\/select>)/isu', static function (array $match) use ($buildingType): string {
        $options = preg_replace_callback('/<option\b([^>]*)>(.*?)<\/option>/isu', static function (array $option) use ($buildingType): string {
            $attrs = preg_replace('/\s+selected(?:=["\'][^"\']*["\'])?/iu', '', $option[1]) ?? $option[1];
            if (trim(strip_tags($option[2])) === $buildingType) $attrs .= ' selected';
            return '<option' . $attrs . '>' . $option[2] . '</option>';
        }, $match[2]);
        return $match[1] . ($options ?? $match[2]) . $match[3];
    }, $html);

    $consent = !empty($state['consent']);
    $html = (string) preg_replace_callback('/<input\b(?=[^>]*\bname=["\']consent["\'])[^>]*>/iu', static function (array $match) use ($consent): string {
        $tag = preg_replace('/\s+checked(?:=["\'][^"\']*["\'])?/iu', '', $match[0]) ?? $match[0];
        return $consent ? (preg_replace('/\s*\/>$/', ' checked />', $tag) ?? $tag) : $tag;
    }, $html);

    return $html;
}

function renderContactDetails(array $config): string
{
    $parts = [];
    $phone = trim((string) ($config['contact_phone'] ?? ''));
    $email = trim((string) ($config['contact_email'] ?? ''));
    $area = trim((string) ($config['service_area'] ?? ''));
    $responseTime = trim((string) ($config['response_time'] ?? ''));
    if ($phone !== '' && preg_match('/^[+0-9 ()\-]{7,24}$/', $phone)) {
        $digits = (string) preg_replace('/\D+/', '', $phone);
        $displayPhone = $phone;
        if (strlen($digits) === 11 && str_starts_with($digits, '48')) {
            $displayPhone = '+48 ' . substr($digits, 2, 3) . ' ' . substr($digits, 5, 3) . ' ' . substr($digits, 8, 3);
        } elseif (strlen($digits) === 9) {
            $displayPhone = substr($digits, 0, 3) . ' ' . substr($digits, 3, 3) . ' ' . substr($digits, 6, 3);
        }
        $parts[] = '<p><span>Telefon</span><a href="tel:' . escapeHtml((string) preg_replace('/[^+0-9]/', '', $phone)) . '">' . escapeHtml($displayPhone) . '</a></p>';
    }
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $parts[] = '<p><span>E-mail</span><a href="mailto:' . escapeHtml($email) . '">' . escapeHtml($email) . '</a></p>';
    }
    if ($area !== '') $parts[] = '<p><span>Obszar działania</span><strong>' . escapeHtml($area) . '</strong></p>';
    if ($responseTime !== '') $parts[] = '<p><span>Odpowiedź</span><strong>' . escapeHtml($responseTime) . '</strong></p>';
    return $parts ? '<div class="contact-details-list">' . implode('', $parts) . '</div>' : '';
}

function renderMobilePhone(array $config): string
{
    $phone = trim((string) ($config['contact_phone'] ?? ''));
    if ($phone === '' || !preg_match('/^[+0-9 ()\-]{7,24}$/', $phone)) return '';
    $href = escapeHtml((string) preg_replace('/[^+0-9]/', '', $phone));
    return '<a class="mobile-contact mobile-call" href="tel:' . $href . '">Zadzwoń <svg class="icon"><use href="#icon-arrow" /></svg></a>';
}

function schemaData(array $config, string $siteUrl): string
{
    $schema = [
        '@context' => 'https://schema.org',
        '@type' => 'HomeAndConstructionBusiness',
        'name' => 'ZBD Budownictwo',
        'description' => 'Osuszanie murów metodą cięcia PRINZ oraz wykonawstwo budowlane.',
        'knowsAbout' => ['osuszanie murów', 'izolacja pozioma', 'technologia PRINZ', 'renowacja zabytków', 'remonty kapitalne'],
    ];
    if (filter_var($siteUrl, FILTER_VALIDATE_URL)) $schema['url'] = $siteUrl . '/';
    $phone = trim((string) ($config['contact_phone'] ?? ''));
    $email = trim((string) ($config['contact_email'] ?? ''));
    $area = trim((string) ($config['service_area'] ?? ''));
    $logo = trim((string) ($config['logo_url'] ?? ''));
    if ($phone !== '') $schema['telephone'] = $phone;
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) $schema['email'] = $email;
    if ($area !== '') $schema['areaServed'] = $area;
    if ($logo !== '') $schema['logo'] = filter_var($logo, FILTER_VALIDATE_URL) ? $logo : $siteUrl . '/' . ltrim($logo, '/');
    return (string) json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
}

if ($html === '') {
    http_response_code(500);
    exit('Brak szablonu strony.');
}

$content = [];
if (is_file($contentPath)) {
    $decoded = json_decode((string) file_get_contents($contentPath), true);
    if (is_array($decoded)) {
        $content = $decoded;
    } else {
        error_log('[zbd] Nie można odczytać data/content.json; użyto treści awaryjnych.');
    }
}

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'httponly' => true,
        'secure' => $isHttps,
        'samesite' => 'Lax',
        'path' => '/',
    ]);
    session_start();
}

$appSecret = (string) ($config['app_secret'] ?? '');
$contactToken = '';
$csrfToken = '';
if (strlen($appSecret) >= 32) {
    $payload = time() . ':' . bin2hex(random_bytes(16));
    $contactToken = $payload . '.' . hash_hmac('sha256', $payload, $appSecret);
    $csrfToken = bin2hex(random_bytes(24));
    $_SESSION['contact_token'] = $contactToken;
    $_SESSION['contact_csrf'] = $csrfToken;
}

$flash = is_array($_SESSION['contact_flash'] ?? null) ? $_SESSION['contact_flash'] : [];
unset($_SESSION['contact_flash']);
$html = renderEditableContent($html, $content);
$html = renderPracticeContent($html, $content);
$seoTitle = contentValue($content, 'seo.title');
$seoDescription = contentValue($content, 'seo.description');
$html = $seoTitle !== '' ? (preg_replace_callback('/<title>.*?<\/title>/isu', fn (): string => '<title>' . escapeHtml($seoTitle) . '</title>', $html, 1) ?? $html) : $html;
$html = replaceMeta($html, 'name', 'description', $seoDescription);
$html = replaceMeta($html, 'property', 'og:title', $seoTitle);
$html = replaceMeta($html, 'property', 'og:description', $seoDescription);
$html = replaceMeta($html, 'name', 'twitter:title', $seoTitle);
$html = replaceMeta($html, 'name', 'twitter:description', $seoDescription);
$html = replaceHiddenValue($html, 'csrf', $csrfToken);
$html = replaceHiddenValue($html, 'form_token', $contactToken);
$html = replaceFormState($html, is_array($flash['fields'] ?? null) ? $flash['fields'] : []);

if (isset($flash['status'])) {
    $messages = [
        'sent' => 'Dziękujemy. Zapytanie zostało wysłane.',
        'error' => 'Nie udało się wysłać wiadomości. Spróbuj ponownie później.',
        'invalid' => 'Sprawdź wymagane pola i spróbuj ponownie.',
        'limit' => 'Wysłano zbyt wiele zapytań. Spróbuj ponownie za kilkanaście minut.',
    ];
    $status = (string) $flash['status'];
    if (isset($messages[$status])) {
        $message = escapeHtml($messages[$status]);
        $html = preg_replace('/<p class="form-message"[^>]*>.*?<\/p>/isu', '<p class="form-message" data-state="' . ($status === 'sent' ? 'success' : 'error') . '" aria-live="polite">' . $message . '</p>', $html) ?? $html;
    }
}

$contactDetails = renderContactDetails($config);
$html = str_replace('<div class="contact-details" data-contact-details></div>', $contactDetails, $html);
$html = str_replace('<div class="footer-contact" data-footer-contact></div>', $contactDetails, $html);
$mobilePhone = renderMobilePhone($config);
if ($mobilePhone !== '') {
    $html = preg_replace('/class="mobile-contact"/', 'class="mobile-contact mobile-form"', $html, 1) ?? $html;
    $mobileAnchor = '<a class="mobile-contact mobile-form" href="#kontakt">Umów oględziny <svg class="icon"><use href="#icon-arrow" /></svg></a>';
    $replaceCount = 1;
    $html = str_replace($mobileAnchor, $mobileAnchor . $mobilePhone, $html, $replaceCount);
}

if (filter_var($siteUrl, FILTER_VALIDATE_URL)) {
    $canonical = escapeHtml($siteUrl . '/');
    $html = preg_replace('/\s*<link rel="canonical"[^>]*>/i', '', $html) ?? $html;
    $html = preg_replace('/\s*<meta property="og:url"[^>]*>/i', '', $html) ?? $html;
    $meta = '    <link rel="canonical" href="' . $canonical . '" />' . "\n" . '    <meta property="og:url" content="' . $canonical . '" />';
    $html = str_replace('    <title>', $meta . "\n    <title>", $html);
    $html = str_replace('content="assets/images/og-zbd.webp"', 'content="' . $canonical . 'assets/images/og-zbd.webp"', $html);
}

$schema = schemaData($config, $siteUrl);
$html = preg_replace('/<script type="application\/ld\+json">.*?<\/script>/isu', '<script type="application/ld+json">' . $schema . '</script>', $html, 1) ?? $html;

header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-Frame-Options: SAMEORIGIN');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
header("Content-Security-Policy: default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'; object-src 'none'; script-src 'self'; style-src 'self'; font-src 'self'; img-src 'self' data:; connect-src 'self'; frame-src https://www.youtube-nocookie.com");
if ($isHttps) header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
echo $html;

