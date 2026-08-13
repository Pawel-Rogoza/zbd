<?php
declare(strict_types=1);

$configPath = __DIR__ . '/config.php';
$config = is_file($configPath) ? require $configPath : [];
$siteUrl = rtrim((string) ($config['site_url'] ?? ''), '/');

if (!filter_var($siteUrl, FILTER_VALIDATE_URL)) {
    http_response_code(404);
    exit;
}

header('Content-Type: application/xml; charset=UTF-8');
$escaped = htmlspecialchars($siteUrl . '/', ENT_XML1 | ENT_QUOTES, 'UTF-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><url><loc>' . $escaped . '</loc><changefreq>monthly</changefreq><priority>1.0</priority></url></urlset>';
