<?php
// Local configuration stays outside source control; host environment variables take precedence.
$environmentFile = __DIR__ . '/.env';
if (is_file($environmentFile)) {
    foreach (file($environmentFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
            continue;
        }
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        if (!preg_match('/^[A-Z][A-Z0-9_]*$/', $key) || getenv($key) !== false) {
            continue;
        }
        if (strlen($value) >= 2 && (($value[0] === '"' && substr($value, -1) === '"') || ($value[0] === "'" && substr($value, -1) === "'"))) {
            $value = substr($value, 1, -1);
        }
        putenv($key . '=' . $value);
    }
}
