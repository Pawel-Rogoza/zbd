<?php
declare(strict_types=1);

function redirectToForm(string $status): void
{
    header('Location: ./?contact=' . rawurlencode($status) . '#kontakt', true, 303);
    exit;
}

function cleanText(string $value, int $maxLength): string
{
    $value = trim(str_replace(["\r\n", "\r"], "\n", $value));
    return function_exists('mb_substr')
        ? mb_substr($value, 0, $maxLength, 'UTF-8')
        : substr($value, 0, $maxLength);
}

function isValidContact(string $value): bool
{
    if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
        return true;
    }

    $digits = preg_replace('/\D+/', '', $value);
    return is_string($digits) && strlen($digits) >= 7 && strlen($digits) <= 15;
}

function enforceRateLimit(string $address): bool
{
    $now = time();
    $window = 15 * 60;
    $limit = 3;
    $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'zbd-contact-' . hash('sha256', $address) . '.json';
    $timestamps = [];

    if (is_file($path)) {
        $decoded = json_decode((string) file_get_contents($path), true);
        if (is_array($decoded)) {
            $timestamps = array_values(array_filter($decoded, static fn ($timestamp): bool => is_int($timestamp) && $timestamp > $now - $window));
        }
    }

    if (count($timestamps) >= $limit) {
        return false;
    }

    $timestamps[] = $now;
    file_put_contents($path, json_encode($timestamps), LOCK_EX);
    return true;
}

function collectAttachments(): array
{
    if (!isset($_FILES['photos']) || !is_array($_FILES['photos']['name'])) {
        return [];
    }

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    $attachments = [];
    $totalSize = 0;
    $fileCount = count($_FILES['photos']['name']);

    if ($fileCount > 3) {
        throw new RuntimeException('Too many files');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    for ($index = 0; $index < $fileCount; $index++) {
        $error = (int) ($_FILES['photos']['error'][$index] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if ($error !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Upload error');
        }

        $size = (int) ($_FILES['photos']['size'][$index] ?? 0);
        $tmpName = (string) ($_FILES['photos']['tmp_name'][$index] ?? '');
        if ($size < 1 || $size > 5 * 1024 * 1024 || !is_uploaded_file($tmpName)) {
            throw new RuntimeException('Invalid file size');
        }

        $totalSize += $size;
        if ($totalSize > 12 * 1024 * 1024) {
            throw new RuntimeException('Attachments too large');
        }

        $mime = $finfo->file($tmpName);
        if (!is_string($mime) || !isset($allowed[$mime])) {
            throw new RuntimeException('Invalid file type');
        }

        $content = file_get_contents($tmpName);
        if ($content === false) {
            throw new RuntimeException('Cannot read attachment');
        }

        $attachments[] = [
            'mime' => $mime,
            'name' => 'zdjecie-' . ($index + 1) . '.' . $allowed[$mime],
            'content' => $content,
        ];
    }

    return $attachments;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectToForm('invalid');
}

$configPath = __DIR__ . '/config.php';
if (!is_file($configPath)) {
    redirectToForm('error');
}

$config = require $configPath;
$recipient = (string) ($config['contact_email'] ?? '');
$from = (string) ($config['from_email'] ?? '');
if (!filter_var($recipient, FILTER_VALIDATE_EMAIL) || !filter_var($from, FILTER_VALIDATE_EMAIL)) {
    redirectToForm('error');
}

$honeypot = trim((string) ($_POST['website'] ?? ''));
$startedAt = filter_var($_POST['form_started'] ?? null, FILTER_VALIDATE_INT);
$elapsed = is_int($startedAt) ? time() - $startedAt : 0;
if ($honeypot !== '' || $elapsed < 3 || $elapsed > 7200) {
    redirectToForm('invalid');
}

$name = cleanText((string) ($_POST['name'] ?? ''), 80);
$contact = cleanText((string) ($_POST['contact'] ?? ''), 120);
$location = cleanText((string) ($_POST['location'] ?? ''), 100);
$buildingType = cleanText((string) ($_POST['building_type'] ?? ''), 80);
$message = cleanText((string) ($_POST['message'] ?? ''), 2000);
$consent = ($_POST['consent'] ?? '') === '1';

if ($name === '' || !isValidContact($contact) || $message === '' || !$consent) {
    redirectToForm('invalid');
}

if (!enforceRateLimit((string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'))) {
    redirectToForm('limit');
}

try {
    $attachments = collectAttachments();
} catch (RuntimeException $exception) {
    redirectToForm('invalid');
}

$subjectText = 'Nowe zapytanie o oględziny - ZBD Budownictwo';
$subject = '=?UTF-8?B?' . base64_encode($subjectText) . '?=';
$plainMessage = implode("\n", [
    'Nowe zapytanie ze strony ZBD Budownictwo',
    '',
    'Imię: ' . $name,
    'Kontakt: ' . $contact,
    'Miejscowość: ' . ($location !== '' ? $location : '-'),
    'Rodzaj budynku: ' . ($buildingType !== '' ? $buildingType : '-'),
    '',
    'Opis problemu:',
    $message,
]);

$headers = [
    'MIME-Version: 1.0',
    'From: ' . $from,
];

if (filter_var($contact, FILTER_VALIDATE_EMAIL)) {
    $headers[] = 'Reply-To: ' . $contact;
}

if ($attachments === []) {
    $headers[] = 'Content-Type: text/plain; charset=UTF-8';
    $body = $plainMessage;
} else {
    $boundary = '=_zbd_' . bin2hex(random_bytes(12));
    $headers[] = 'Content-Type: multipart/mixed; boundary="' . $boundary . '"';
    $parts = [
        '--' . $boundary,
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
        '',
        $plainMessage,
    ];

    foreach ($attachments as $attachment) {
        $parts[] = '--' . $boundary;
        $parts[] = 'Content-Type: ' . $attachment['mime'] . '; name="' . $attachment['name'] . '"';
        $parts[] = 'Content-Disposition: attachment; filename="' . $attachment['name'] . '"';
        $parts[] = 'Content-Transfer-Encoding: base64';
        $parts[] = '';
        $parts[] = chunk_split(base64_encode($attachment['content']));
    }
    $parts[] = '--' . $boundary . '--';
    $body = implode("\r\n", $parts);
}

$sent = mail($recipient, $subject, $body, implode("\r\n", $headers));
redirectToForm($sent ? 'sent' : 'error');
