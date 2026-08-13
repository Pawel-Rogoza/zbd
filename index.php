<?php
declare(strict_types=1);

$html = (string) file_get_contents(__DIR__ . '/index.html');
$configPath = __DIR__ . '/config.php';
$config = is_file($configPath) ? require $configPath : [];
$siteUrl = rtrim((string) ($config['site_url'] ?? ''), '/');

if (filter_var($siteUrl, FILTER_VALIDATE_URL)) {
    $meta = implode("\n", [
        '    <link rel="canonical" href="' . htmlspecialchars($siteUrl . '/', ENT_QUOTES, 'UTF-8') . '" />',
        '    <meta property="og:url" content="' . htmlspecialchars($siteUrl . '/', ENT_QUOTES, 'UTF-8') . '" />',
    ]);
    $html = str_replace('    <title>', $meta . "\n    <title>", $html);
    $html = str_replace('content="assets/images/og-zbd.webp"', 'content="' . htmlspecialchars($siteUrl . '/assets/images/og-zbd.webp', ENT_QUOTES, 'UTF-8') . '"', $html);
    $html = str_replace('"knowsAbout":', '"url": "' . addslashes($siteUrl . '/') . '", "knowsAbout":', $html);
}

header('Content-Type: text/html; charset=UTF-8');
echo $html;
