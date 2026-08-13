<?php
declare(strict_types=1);

session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'use_strict_mode' => true,
]);

$root = dirname(__DIR__);
$configPath = $root . '/config.php';
$contentPath = $root . '/data/content.json';
$config = is_file($configPath) ? require $configPath : [];
$passwordHash = (string) ($config['admin_password_hash'] ?? '');

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

function saveUploadedImage(array $file, string $root, int $index): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || (int) ($file['size'] ?? 0) > 8 * 1024 * 1024) {
        throw new RuntimeException('Nieprawidłowy plik zdjęcia.');
    }

    $tmp = (string) ($file['tmp_name'] ?? '');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!is_string($mime) || !isset($extensions[$mime]) || !is_uploaded_file($tmp)) {
        throw new RuntimeException('Dozwolone są wyłącznie pliki JPG, PNG i WebP.');
    }

    $uploadDir = $root . '/assets/images/uploads';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        throw new RuntimeException('Nie można utworzyć katalogu zdjęć.');
    }

    $filename = 'praktyka-' . $index . '-' . date('Ymd-His') . '.' . $extensions[$mime];
    if (!move_uploaded_file($tmp, $uploadDir . '/' . $filename)) {
        throw new RuntimeException('Nie udało się zapisać zdjęcia.');
    }
    return 'assets/images/uploads/' . $filename;
}

if (isset($_GET['logout'])) {
    $_SESSION = [];
    session_destroy();
    header('Location: index.php');
    exit;
}

$loginError = '';
if (isset($_POST['admin_password'])) {
    if ($passwordHash !== '' && password_verify((string) $_POST['admin_password'], $passwordHash)) {
        session_regenerate_id(true);
        $_SESSION['zbd_admin'] = true;
        header('Location: index.php');
        exit;
    }
    $loginError = 'Nieprawidłowe hasło.';
}

$authenticated = ($_SESSION['zbd_admin'] ?? false) === true;
if (!$authenticated):
?>
<!doctype html><html lang="pl"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Panel ZBD</title><link rel="stylesheet" href="admin.css"></head><body><main class="login-card"><strong class="admin-logo">ZBD <span>PANEL</span></strong><h1>Edycja strony</h1><?php if ($passwordHash === ''): ?><p class="notice error">Panel jest wyłączony. Ustaw <code>admin_password_hash</code> w pliku <code>config.php</code>.</p><?php else: ?><form method="post"><label>Hasło<input type="password" name="admin_password" required autofocus autocomplete="current-password"></label><button type="submit">Zaloguj się</button><?php if ($loginError): ?><p class="notice error"><?= escape($loginError) ?></p><?php endif; ?></form><?php endif; ?><a href="../index.html">← Wróć na stronę</a></main></body></html>
<?php
exit;
endif;

$fields = [
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

$content = json_decode((string) file_get_contents($contentPath), true);
if (!is_array($content)) {
    $content = [];
}

$message = '';
$messageType = 'success';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_content'])) {
    if (!hash_equals((string) ($_SESSION['csrf'] ?? ''), (string) ($_POST['csrf'] ?? ''))) {
        $message = 'Sesja wygasła. Odśwież stronę i spróbuj ponownie.';
        $messageType = 'error';
    } else {
        try {
            foreach ($fields as $id => $definition) {
                setPath($content, $definition['path'], (string) ($_POST['fields'][$id] ?? ''));
            }
            for ($index = 0; $index < 2; $index++) {
                $imagePath = saveUploadedImage($_FILES['practice_' . $index . '_image'] ?? [], $root, $index);
                if ($imagePath !== null) {
                    setPath($content, ['practice', 'items', $index, 'image'], $imagePath);
                }
            }
            $encoded = json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($encoded === false || file_put_contents($contentPath, $encoded . "\n", LOCK_EX) === false) {
                throw new RuntimeException('Nie udało się zapisać treści.');
            }
            $message = 'Zmiany zostały zapisane.';
        } catch (RuntimeException $exception) {
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
    <header class="admin-header"><strong class="admin-logo">ZBD <span>PANEL</span></strong><nav><a href="../index.html" target="_blank" rel="noopener">Podgląd strony</a><a href="?logout=1">Wyloguj</a></nav></header>
    <main class="admin-main"><div class="admin-title"><div><p>EDYCJA TREŚCI</p><h1>Strona główna</h1></div><span>Zmiany są widoczne po odświeżeniu strony.</span></div><?php if ($message): ?><p class="notice <?= escape($messageType) ?>"><?= escape($message) ?></p><?php endif; ?>
      <form method="post" enctype="multipart/form-data" class="editor-form"><input type="hidden" name="csrf" value="<?= escape($_SESSION['csrf']) ?>"><input type="hidden" name="save_content" value="1">
        <section><h2>Treści</h2><div class="field-grid"><?php foreach ($fields as $id => $definition): $value = getPath($content, $definition['path']); ?><label><?= escape($definition['label']) ?><?php if ($definition['type'] === 'textarea'): ?><textarea name="fields[<?= escape($id) ?>]" rows="4"><?= escape($value) ?></textarea><?php else: ?><input type="text" name="fields[<?= escape($id) ?>]" value="<?= escape($value) ?>"><?php endif; ?></label><?php endforeach; ?></div></section>
        <section><h2>Zdjęcia sekcji „W praktyce”</h2><div class="field-grid two"><label>Nowe zdjęcie 1<input type="file" name="practice_0_image" accept="image/jpeg,image/png,image/webp"><small>Pozostaw puste, aby zachować obecne zdjęcie.</small></label><label>Nowe zdjęcie 2<input type="file" name="practice_1_image" accept="image/jpeg,image/png,image/webp"><small>Pozostaw puste, aby zachować obecne zdjęcie.</small></label></div></section>
        <div class="save-bar"><button type="submit">Zapisz zmiany</button></div>
      </form>
    </main>
  </body>
</html>
