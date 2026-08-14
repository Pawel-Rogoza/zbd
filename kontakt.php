<?php
declare(strict_types=1);

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

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

function redirectToForm(string $status, array $fields = []): void
{
    $_SESSION['contact_flash'] = [
        'status' => $status,
        'fields' => $status === 'sent' ? [] : $fields,
    ];
    header('Location: /?contact=' . rawurlencode($status) . '#kontakt', true, 303);
    exit;
}

function cleanText(string $value): string
{
    return trim(str_replace(["\r\n", "\r"], "\n", $value));
}

function postedText(string $key): string
{
    $value = $_POST[$key] ?? '';
    return is_string($value) ? cleanText($value) : "\0";
}

function textLength(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
}

function isValidContact(string $value): bool
{
    if (filter_var($value, FILTER_VALIDATE_EMAIL)) return true;
    $digits = preg_replace('/\D+/', '', $value);
    return is_string($digits) && strlen($digits) >= 7 && strlen($digits) <= 15;
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
    $isTrustedProxy = filter_var($remote, FILTER_VALIDATE_IP) && array_reduce(
        $trusted,
        static fn (bool $carry, string $cidr): bool => $carry || ipInCidr($remote, $cidr),
        false
    );
    $forwarded = trim((string) ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? ''));
    return $isTrustedProxy && filter_var($forwarded, FILTER_VALIDATE_IP) ? $forwarded : $remote;
}

function enforceRateLimit(string $address, string $prefix, int $limit, int $window): bool
{
    $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $prefix . '-' . hash('sha256', $address) . '.json';
    $handle = @fopen($path, 'c+');
    if ($handle === false || !flock($handle, LOCK_EX)) {
        if (is_resource($handle)) fclose($handle);
        error_log('[zbd] Nie można zablokować pliku limitu ' . $prefix . '.');
        return false;
    }
    $contents = stream_get_contents($handle);
    $decoded = json_decode(is_string($contents) ? $contents : '', true);
    $now = time();
    $timestamps = is_array($decoded) ? array_values(array_filter($decoded, static fn ($timestamp): bool => is_int($timestamp) && $timestamp > $now - $window)) : [];
    if (count($timestamps) >= $limit) {
        flock($handle, LOCK_UN);
        fclose($handle);
        return false;
    }
    $timestamps[] = $now;
    ftruncate($handle, 0);
    rewind($handle);
    fwrite($handle, (string) json_encode($timestamps));
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);
    return true;
}

function formFields(): array
{
    return [
        'name' => postedText('name'),
        'phone' => postedText('phone'),
        'email' => postedText('email'),
        'location' => postedText('location'),
        'building_type' => postedText('building_type'),
        'message' => postedText('message'),
        'consent' => ($_POST['consent'] ?? '') === '1',
    ];
}

function sendWithNativeMail(string $recipient, string $from, string $subject, string $message, string $replyTo): bool
{
    $headers = [
        'MIME-Version: 1.0',
        'From: ZBD Budownictwo <' . $from . '>',
        'Content-Type: text/plain; charset=UTF-8',
    ];
    if ($replyTo !== '') $headers[] = 'Reply-To: ' . $replyTo;
    return mail($recipient, '=?UTF-8?B?' . base64_encode($subject) . '?=', $message, implode("\r\n", $headers));
}

function sendWithSmtp(array $config, string $recipient, string $from, string $subject, string $message, string $replyTo): bool
{
    $autoload = (string) ($config['phpmailer_autoload'] ?? (__DIR__ . '/vendor/autoload.php'));
    if (is_file($autoload)) require_once $autoload;
    if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
        error_log('[zbd] SMTP niedostępne: brak PHPMailer.');
        return false;
    }
    try {
        $mailer = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mailer->isSMTP();
        $mailer->Host = (string) ($config['smtp_host'] ?? '');
        $mailer->SMTPAuth = true;
        $mailer->Username = (string) ($config['smtp_username'] ?? '');
        $mailer->Password = (string) ($config['smtp_password'] ?? '');
        $mailer->Port = (int) ($config['smtp_port'] ?? 587);
        $mailer->Timeout = max(1, (int) ($config['smtp_timeout'] ?? 10));
        $encryption = strtolower((string) ($config['smtp_encryption'] ?? 'tls'));
        $mailer->SMTPSecure = $encryption === 'ssl'
            ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
            : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mailer->CharSet = 'UTF-8';
        $mailer->setFrom($from, 'ZBD Budownictwo');
        $mailer->addAddress($recipient);
        if ($replyTo !== '') $mailer->addReplyTo($replyTo);
        $mailer->Subject = $subject;
        $mailer->Body = $message;
        return $mailer->send();
    } catch (Throwable $exception) {
        error_log('[zbd] SMTP error class=' . get_class($exception) . '.');
        return false;
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirectToForm('invalid');

$configPath = __DIR__ . '/config.php';
$config = is_file($configPath) ? require $configPath : [];
$config = is_array($config) ? $config : [];
$recipient = trim((string) ($config['contact_email'] ?? ''));
$from = trim((string) ($config['from_email'] ?? ''));
$appSecret = (string) ($config['app_secret'] ?? '');
$fields = formFields();

if (!filter_var($recipient, FILTER_VALIDATE_EMAIL) || !filter_var($from, FILTER_VALIDATE_EMAIL) || strlen($appSecret) < 32) {
    error_log('[zbd] Formularz ma niepełną konfigurację.');
    redirectToForm('error', $fields);
}

$csrf = (string) ($_POST['csrf'] ?? '');
$expectedCsrf = (string) ($_SESSION['contact_csrf'] ?? '');
$formToken = (string) ($_POST['form_token'] ?? '');
$expectedFormToken = (string) ($_SESSION['contact_token'] ?? '');
$tokenParts = explode('.', $formToken, 2);
$payload = (string) ($tokenParts[0] ?? '');
$signature = (string) ($tokenParts[1] ?? '');
$issuedAt = (int) explode(':', $payload, 2)[0];
$tokenValid = count($tokenParts) === 2
    && hash_equals($expectedCsrf, $csrf)
    && hash_equals($expectedFormToken, $formToken)
    && hash_equals(hash_hmac('sha256', $payload, $appSecret), $signature)
    && $issuedAt > time() - 7200
    && $issuedAt <= time();
unset($_SESSION['contact_csrf'], $_SESSION['contact_token']);
if (!$tokenValid) redirectToForm('invalid', $fields);

$startedAt = filter_var($_POST['form_started'] ?? null, FILTER_VALIDATE_INT);
$elapsed = is_int($startedAt) ? time() - $startedAt : 0;
if ($fields['name'] === '' || ($fields['phone'] === '' && $fields['email'] === '') || ($fields['phone'] !== '' && !isValidContact($fields['phone'])) || ($fields['email'] !== '' && !filter_var($fields['email'], FILTER_VALIDATE_EMAIL)) || $fields['message'] === '' || !$fields['consent'] || ($startedAt !== false && ($elapsed < 2 || $elapsed > 7200))) {
    redirectToForm('invalid', $fields);
}

$limits = [
    'name' => 80,
    'phone' => 24,
    'email' => 120,
    'location' => 100,
    'building_type' => 80,
    'message' => 2000,
];
foreach ($limits as $field => $limit) {
    if (textLength($fields[$field]) > $limit) redirectToForm('invalid', $fields);
}

if (!enforceRateLimit(clientIp($config), 'zbd-contact', 3, 15 * 60)) redirectToForm('limit', $fields);

$subject = 'Nowe zapytanie o oględziny - ZBD Budownictwo';
$plainMessage = implode("\n", [
    'Nowe zapytanie ze strony ZBD Budownictwo',
    '',
    'Imię: ' . $fields['name'],
    'Telefon: ' . ($fields['phone'] !== '' ? $fields['phone'] : '-'),
    'E-mail: ' . ($fields['email'] !== '' ? $fields['email'] : '-'),
    'Miejscowość: ' . ($fields['location'] !== '' ? $fields['location'] : '-'),
    'Rodzaj budynku: ' . ($fields['building_type'] !== '' ? $fields['building_type'] : '-'),
    '',
    'Opis problemu:',
    $fields['message'],
]);

$transport = strtolower((string) ($config['mail_transport'] ?? 'smtp'));
$sent = $transport === 'mail'
    ? sendWithNativeMail($recipient, $from, $subject, $plainMessage, $fields['email'])
    : sendWithSmtp($config, $recipient, $from, $subject, $plainMessage, $fields['email']);
if (!$sent) {
    error_log('[zbd] Nie udało się wysłać formularza transport=' . ($transport === 'mail' ? 'mail' : 'smtp') . '.');
    redirectToForm('error', $fields);
}

redirectToForm('sent');

