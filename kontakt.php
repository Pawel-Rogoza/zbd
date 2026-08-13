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
    'Content-Type: text/plain; charset=UTF-8',
];

if (filter_var($contact, FILTER_VALIDATE_EMAIL)) {
    $headers[] = 'Reply-To: ' . $contact;
}

$sent = mail($recipient, $subject, $plainMessage, implode("\r\n", $headers));
redirectToForm($sent ? 'sent' : 'error');
