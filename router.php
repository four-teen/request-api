<?php
// The PHP development server does not apply .htaccess; keep private files inaccessible here too.
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');
$segments = explode('/', trim($path, '/'));
$blocked = strpos($path, "\\") !== false;
foreach ($segments as $segment) {
    if ($segment !== '' && $segment[0] === '.') {
        $blocked = true;
    }
}
$filename = basename($path);
if (in_array($filename, ['db.php', 'config.php', 'router.php', 'serve.mjs', 'README.md', 'composer.json', 'composer.lock'], true)
    || (isset($segments[0]) && $segments[0] === 'deploy')) {
    $blocked = true;
}
if ($blocked) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Not found.';
    return true;
}
return false;