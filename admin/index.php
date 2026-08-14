<?php
declare(strict_types=1);

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
session_start([
    'cookie_httponly' => true,
    'cookie_secure' => $isHttps,
    'cookie_samesite' => 'Strict',
    'use_strict_mode' => true,
]);

header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');

$root = dirname(__DIR__);
$configPath = $root . '/config.php';
$contentPath = $root . '/data/content.json';
$config = is_file($configPath) ? require $configPath : [];
$passwordHash = (string) ($config['admin_password_hash'] ?? '');
$panelEnabled = ($config['admin_enabled'] ?? false) === true && $passwordHash !== '';

if (!$panelEnabled) {
    http_response_code(404);
    exit;
}

function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function getPath(array $source, array $path): string
{
    $value = $source;
    foreach ($path as $key) {
        if (!is_array($value) || !array_key_exists($key, $value)) {
            return '';
        }
        $value = $value[$key];
    }
    return is_string($value) ? $value : '';
}

function setPath(array &$source, array $path, string $value): void
{
    $target = &$source;
    foreach ($path as $key) {
        if (!isset($target[$key]) || !is_array($target[$key])) {
            $target[$key] = [];
        }
        $target = &$target[$key];
    }
    $target = trim($value);
}

function textLength(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
}

function ipInCidr(string $ip, string $cidr): bool
{
    [$network, $prefix] = array_pad(explode('/', $cidr, 2), 2, null);
    $ipBinary = @inet_pton($ip);
    $networkBinary = @inet_pton((string) $network);
    if ($ipBinary === false || $networkBinary === false || $prefix === null || strlen($ipBinary) !== strlen($networkBinary)) return false;
    $prefixLength = (int) $prefix;
    $maxBits = strlen($ipBinary) * 8;
    if ($prefixLength < 0 || $prefixLength > $maxBits) return false;
    $fullBytes = intdiv($prefixLength, 8);
    $remainingBits = $prefixLength % 8;
    if ($fullBytes > 0 && substr($ipBinary, 0, $fullBytes) !== substr($networkBinary, 0, $fullBytes)) return false;
    if ($remainingBits === 0) return true;
    $mask = (0xff << (8 - $remainingBits)) & 0xff;
    return (ord($ipBinary[$fullBytes]) & $mask) === (ord($networkBinary[$fullBytes]) & $mask);
}

function clientIp(array $config): string
{
    $remote = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $trusted = array_values(array_filter((array) ($config['cloudflare_trusted_proxies'] ?? []), 'is_string'));
    $isTrusted = filter_var($remote, FILTER_VALIDATE_IP) && array_reduce(
        $trusted,
        static fn (bool $carry, string $cidr): bool => $carry || ipInCidr($remote, $cidr),
        false
    );
    $forwarded = trim((string) ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? ''));
    return $isTrusted && filter_var($forwarded, FILTER_VALIDATE_IP) ? $forwarded : $remote;
}

function saveUploadedImage(array $file, string $root, int $index): ?array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || (int) ($file['size'] ?? 0) > 8 * 1024 * 1024) {
        throw new RuntimeException('Nieprawidłowy plik zdjęcia.');
    }

    $tmp = (string) ($file['tmp_name'] ?? '');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
    $allowed = ['image/jpeg', 'image/png', 'image/webp'];
    if (!is_string($mime) || !in_array($mime, $allowed, true) || !is_uploaded_file($tmp)) {
        throw new RuntimeException('Dozwolone są wyłącznie pliki JPG, PNG i WebP.');
    }

    $dimensions = @getimagesize($tmp);
    $width = (int) ($dimensions[0] ?? 0);
    $height = (int) ($dimensions[1] ?? 0);
    if ($width < 200 || $height < 150 || $width > 6000 || $height > 6000) {
        throw new RuntimeException('Zdjęcie musi mieć wymiary od 200×150 do 6000×6000 pikseli.');
    }
    if (!function_exists('imagewebp')) {
        throw new RuntimeException('Serwer nie ma włączonej obsługi WebP.');
    }

    $source = match ($mime) {
        'image/jpeg' => @imagecreatefromjpeg($tmp),
        'image/png' => @imagecreatefrompng($tmp),
        'image/webp' => @imagecreatefromwebp($tmp),
        default => false,
    };
    if ($source === false) throw new RuntimeException('Nie udało się odczytać zdjęcia.');
    $maxDimension = 2400;
    $scale = min(1, $maxDimension / max($width, $height));
    $targetWidth = max(1, (int) round($width * $scale));
    $targetHeight = max(1, (int) round($height * $scale));
    $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
    if ($canvas === false) {
        imagedestroy($source);
        throw new RuntimeException('Nie udało się przygotować zdjęcia.');
    }
    imagealphablending($canvas, false);
    imagesavealpha($canvas, true);
    $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
    imagefilledrectangle($canvas, 0, 0, $targetWidth, $targetHeight, $transparent);
    imagecopyresampled($canvas, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
    imagedestroy($source);

    $uploadDir = $root . '/assets/images/uploads';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        throw new RuntimeException('Nie można utworzyć katalogu zdjęć.');
    }

    $filename = 'praktyka-' . $index . '-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.webp';
    $absolutePath = $uploadDir . '/' . $filename;
    if (!imagewebp($canvas, $absolutePath, 82)) {
        imagedestroy($canvas);
        throw new RuntimeException('Nie udało się zapisać zdjęcia.');
    }
    imagedestroy($canvas);
    chmod($absolutePath, 0644);
    return ['path' => 'assets/images/uploads/' . $filename, 'absolute' => $absolutePath];
}

function atomicWriteJson(string $path, string $contents): void
{
    $backupPath = $path . '.bak';
    if (is_file($path) && !copy($path, $backupPath)) {
        throw new RuntimeException('Nie udało się utworzyć kopii zapasowej treści.');
    }
    $temporaryPath = tempnam(dirname($path), 'content-');
    if ($temporaryPath === false || file_put_contents($temporaryPath, $contents, LOCK_EX) === false) {
        if (is_string($temporaryPath) && is_file($temporaryPath)) @unlink($temporaryPath);
        throw new RuntimeException('Nie udało się przygotować zapisu treści.');
    }
    chmod($temporaryPath, 0640);
    if (!rename($temporaryPath, $path)) {
        @unlink($temporaryPath);
        throw new RuntimeException('Nie udało się zatwierdzić zapisu treści.');
    }
}

function archiveReplacedUpload(string $root, string $relativePath): void
{
    $prefix = 'assets/images/uploads/';
    if (!str_starts_with($relativePath, $prefix)) return;
    $source = $root . '/' . $relativePath;
    if (!is_file($source)) return;
    $archiveDir = $root . '/data/archive/uploads';
    if (!is_dir($archiveDir) && !mkdir($archiveDir, 0750, true) && !is_dir($archiveDir)) {
        error_log('[zbd] Nie można utworzyć archiwum uploadów.');
        return;
    }
    $target = $archiveDir . '/' . basename($source) . '.' . date('Ymd-His') . '.bak';
    if (!rename($source, $target)) error_log('[zbd] Nie można zarchiwizować zastąpionego uploadu.');
}

function loginAttemptsPath(string $address): string
{
    return sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'zbd-admin-login-' . hash('sha256', $address) . '.json';
}

function readLoginAttempts(string $path): array
{
    $now = time();
    $attempts = [];
    if (is_file($path)) {
        $decoded = json_decode((string) file_get_contents($path), true);
        if (is_array($decoded)) {
            $attempts = array_values(array_filter($decoded, static fn ($timestamp): bool => is_int($timestamp) && $timestamp > $now - 15 * 60));
        }
    }
    return $attempts;
}

function loginAllowed(string $address): bool
{
    return count(readLoginAttempts(loginAttemptsPath($address))) < 5;
}

function recordLoginFailure(string $address): void
{
    $path = loginAttemptsPath($address);
    $attempts = readLoginAttempts($path);
    $attempts[] = time();
    file_put_contents($path, (string) json_encode($attempts), LOCK_EX);
}

function clearLoginFailures(string $address): void
{
    $path = loginAttemptsPath($address);
    if (is_file($path)) @unlink($path);
}

if (isset($_GET['logout'])) {
    $_SESSION = [];
    session_destroy();
    header('Location: index.php');
    exit;
}

$clientAddress = clientIp($config);
if (!isset($_SESSION['login_csrf']) || !is_string($_SESSION['login_csrf'])) {
    $_SESSION['login_csrf'] = bin2hex(random_bytes(24));
}
$loginError = '';
if (isset($_POST['admin_password'])) {
    $csrfValid = hash_equals((string) $_SESSION['login_csrf'], (string) ($_POST['login_csrf'] ?? ''));
    $allowed = loginAllowed($clientAddress);
    if ($csrfValid && $allowed && password_verify((string) $_POST['admin_password'], $passwordHash)) {
        clearLoginFailures($clientAddress);
        session_regenerate_id(true);
        $_SESSION['zbd_admin'] = true;
        unset($_SESSION['login_csrf']);
        header('Location: index.php');
        exit;
    }
    if ($csrfValid && $allowed) recordLoginFailure($clientAddress);
    $loginError = $allowed ? 'Nieprawidłowe hasło.' : 'Zbyt wiele prób logowania. Spróbuj ponownie za 15 minut.';
}

$authenticated = ($_SESSION['zbd_admin'] ?? false) === true;
if (!$authenticated):
?>
<!doctype html><html lang="pl"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Panel ZBD</title><link rel="stylesheet" href="admin.css"></head><body><main class="login-card"><strong class="admin-logo">ZBD <span>PANEL</span></strong><h1>Edycja strony</h1><form method="post"><input type="hidden" name="login_csrf" value="<?= escape((string) $_SESSION['login_csrf']) ?>"><label>Hasło<input type="password" name="admin_password" required autofocus autocomplete="current-password"></label><button type="submit">Zaloguj się</button><?php if ($loginError): ?><p class="notice error"><?= escape($loginError) ?></p><?php endif; ?></form><a href="../">← Wróć na stronę</a></main></body></html>
<?php
exit;
endif;

$fields = [
    'seo_title' => ['label' => 'SEO - tytuł strony', 'path' => ['seo', 'title'], 'type' => 'text'],
    'seo_description' => ['label' => 'SEO - opis strony', 'path' => ['seo', 'description'], 'type' => 'textarea'],
    'hero_title' => ['label' => 'Hero - nagłówek', 'path' => ['hero', 'title'], 'type' => 'textarea'],
    'hero_kicker' => ['label' => 'Hero - specjalizacja', 'path' => ['hero', 'kicker'], 'type' => 'text'],
    'hero_description' => ['label' => 'Hero - opis', 'path' => ['hero', 'description'], 'type' => 'textarea'],
    'moisture_title' => ['label' => 'Problem wilgoci - nagłówek', 'path' => ['moisture', 'title'], 'type' => 'textarea'],
    'moisture_description_1' => ['label' => 'Problem wilgoci - akapit 1', 'path' => ['moisture', 'description_1'], 'type' => 'textarea'],
    'moisture_description_2' => ['label' => 'Problem wilgoci - akapit 2', 'path' => ['moisture', 'description_2'], 'type' => 'textarea'],
    'method_title' => ['label' => 'Metoda - nagłówek', 'path' => ['method', 'title'], 'type' => 'text'],
    'method_description' => ['label' => 'Metoda - opis', 'path' => ['method', 'description'], 'type' => 'textarea'],
    'benefits_title' => ['label' => 'Korzyści - nagłówek', 'path' => ['benefits', 'title'], 'type' => 'textarea'],
    'practice_title' => ['label' => 'W praktyce - nagłówek', 'path' => ['practice', 'title'], 'type' => 'text'],
    'practice_description' => ['label' => 'W praktyce - opis', 'path' => ['practice', 'description'], 'type' => 'textarea'],
    'practice_0_label' => ['label' => 'Zdjęcie 1 - etykieta', 'path' => ['practice', 'items', 0, 'label'], 'type' => 'text'],
    'practice_0_title' => ['label' => 'Zdjęcie 1 - podpis', 'path' => ['practice', 'items', 0, 'title'], 'type' => 'text'],
    'practice_0_alt' => ['label' => 'Zdjęcie 1 - opis dostępności', 'path' => ['practice', 'items', 0, 'alt'], 'type' => 'text'],
    'practice_1_label' => ['label' => 'Zdjęcie 2 - etykieta', 'path' => ['practice', 'items', 1, 'label'], 'type' => 'text'],
    'practice_1_title' => ['label' => 'Zdjęcie 2 - podpis', 'path' => ['practice', 'items', 1, 'title'], 'type' => 'text'],
    'practice_1_alt' => ['label' => 'Zdjęcie 2 - opis dostępności', 'path' => ['practice', 'items', 1, 'alt'], 'type' => 'text'],
    'services_title' => ['label' => 'Usługi - nagłówek', 'path' => ['services', 'title'], 'type' => 'text'],
    'services_description' => ['label' => 'Usługi - opis', 'path' => ['services', 'description'], 'type' => 'textarea'],
    'partner_title' => ['label' => 'Partnerstwo - nagłówek', 'path' => ['partner', 'title'], 'type' => 'textarea'],
    'partner_description' => ['label' => 'Partnerstwo - opis', 'path' => ['partner', 'description'], 'type' => 'textarea'],
    'contact_title' => ['label' => 'Kontakt - nagłówek', 'path' => ['contact', 'title'], 'type' => 'text'],
    'contact_description' => ['label' => 'Kontakt - opis', 'path' => ['contact', 'description'], 'type' => 'textarea'],
];

$fieldLimits = [
    'seo_title' => 120, 'seo_description' => 180,
    'hero_title' => 120, 'hero_kicker' => 120, 'hero_description' => 300,
    'moisture_title' => 120, 'moisture_description_1' => 500, 'moisture_description_2' => 500,
    'method_title' => 120, 'method_description' => 500, 'benefits_title' => 120,
    'practice_title' => 120, 'practice_description' => 500,
    'practice_0_label' => 40, 'practice_0_title' => 120, 'practice_0_alt' => 180,
    'practice_1_label' => 40, 'practice_1_title' => 120, 'practice_1_alt' => 180,
    'services_title' => 120, 'services_description' => 500,
    'partner_title' => 200, 'partner_description' => 500,
    'contact_title' => 120, 'contact_description' => 500,
];

$content = json_decode((string) file_get_contents($contentPath), true);
$contentIsValid = is_array($content);
if (!$contentIsValid) {
    $content = [];
}

$message = '';
$messageType = 'success';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_content'])) {
    if (!hash_equals((string) ($_SESSION['csrf'] ?? ''), (string) ($_POST['csrf'] ?? ''))) {
        $message = 'Sesja wygasła. Odśwież stronę i spróbuj ponownie.';
        $messageType = 'error';
    } else {
        $originalContent = $content;
        $newUploads = [];
        try {
            if (!$contentIsValid) throw new RuntimeException('Treść jest uszkodzona. Przywróć content.json z kopii zapasowej przed zapisem.');
            foreach ($fields as $id => $definition) {
                $value = $_POST['fields'][$id] ?? '';
                if (!is_string($value) || textLength(trim($value)) > ($fieldLimits[$id] ?? 500)) {
                    throw new RuntimeException('Jedno z pól przekracza dozwoloną długość.');
                }
                setPath($content, $definition['path'], $value);
            }
            for ($index = 0; $index < 2; $index++) {
                $imageUpload = saveUploadedImage($_FILES['practice_' . $index . '_image'] ?? [], $root, $index);
                if ($imageUpload !== null) {
                    $newUploads[] = [
                        'absolute' => $imageUpload['absolute'],
                        'old' => getPath($content, ['practice', 'items', $index, 'image']),
                    ];
                    setPath($content, ['practice', 'items', $index, 'image'], $imageUpload['path']);
                }
            }
            $encoded = json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($encoded === false) throw new RuntimeException('Nie udało się zakodować treści.');
            atomicWriteJson($contentPath, $encoded . "\n");
            $contentIsValid = true;
            foreach ($newUploads as $upload) archiveReplacedUpload($root, (string) $upload['old']);
            $message = 'Zmiany zostały zapisane.';
        } catch (RuntimeException $exception) {
            $content = $originalContent;
            foreach ($newUploads as $upload) {
                if (is_file((string) $upload['absolute'])) @unlink((string) $upload['absolute']);
            }
            $message = $exception->getMessage();
            $messageType = 'error';
        }
    }
}

$_SESSION['csrf'] = bin2hex(random_bytes(24));
?>
<!doctype html>
<html lang="pl">
  <head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Panel ZBD</title><link rel="stylesheet" href="admin.css"></head>
  <body>
    <header class="admin-header"><strong class="admin-logo">ZBD <span>PANEL</span></strong><nav><a href="../" target="_blank" rel="noopener">Podgląd strony</a><a href="?logout=1">Wyloguj</a></nav></header>
    <main class="admin-main"><div class="admin-title"><div><p>EDYCJA TREŚCI</p><h1>Strona główna</h1></div><span>Zmiany są widoczne po odświeżeniu strony.</span></div><?php if ($message): ?><p class="notice <?= escape($messageType) ?>"><?= escape($message) ?></p><?php endif; ?>
      <form method="post" enctype="multipart/form-data" class="editor-form"><input type="hidden" name="csrf" value="<?= escape($_SESSION['csrf']) ?>"><input type="hidden" name="save_content" value="1">
        <section><h2>Treści</h2><div class="field-grid"><?php foreach ($fields as $id => $definition): $value = getPath($content, $definition['path']); ?><label><?= escape($definition['label']) ?><?php if ($definition['type'] === 'textarea'): ?><textarea name="fields[<?= escape($id) ?>]" rows="4"><?= escape($value) ?></textarea><?php else: ?><input type="text" name="fields[<?= escape($id) ?>]" value="<?= escape($value) ?>"><?php endif; ?></label><?php endforeach; ?></div></section>
        <section><h2>Zdjęcia sekcji „W praktyce”</h2><div class="field-grid two"><label>Nowe zdjęcie 1<input type="file" name="practice_0_image" accept="image/jpeg,image/png,image/webp"><small>Pozostaw puste, aby zachować obecne zdjęcie.</small></label><label>Nowe zdjęcie 2<input type="file" name="practice_1_image" accept="image/jpeg,image/png,image/webp"><small>Pozostaw puste, aby zachować obecne zdjęcie.</small></label></div></section>
        <div class="save-bar"><button type="submit">Zapisz zmiany</button></div>
      </form>
    </main>
  </body>
</html>

