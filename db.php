<?php

require_once __DIR__ . '/config.php';

if (!function_exists('rrms_env')) {
    function rrms_env($key, $default = '')
    {
        $value = getenv($key);
        return $value === false ? $default : $value;
    }
}

if (!function_exists('rrms_h')) {
    function rrms_h($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('rrms_csrf_token')) {
    function rrms_csrf_token()
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return '';
        }

        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('rrms_request_csrf_token')) {
    function rrms_request_csrf_token()
    {
        if (isset($_POST['csrf_token'])) {
            return $_POST['csrf_token'];
        }

        return $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    }
}

if (!function_exists('rrms_verify_csrf')) {
    function rrms_verify_csrf($token)
    {
        return session_status() === PHP_SESSION_ACTIVE
            && is_string($token)
            && isset($_SESSION['csrf_token'])
            && hash_equals($_SESSION['csrf_token'], $token);
    }
}

if (!function_exists('rrms_require_csrf')) {
    function rrms_require_csrf()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        if (!rrms_verify_csrf(rrms_request_csrf_token())) {
            http_response_code(419);
            echo 'Invalid security token. Please refresh the page and try again.';
            exit;
        }
    }
}

if (!function_exists('rrms_require_login')) {
    function rrms_require_login()
    {
        if (empty($_SESSION['username'])) {
            header('location:../logout.php');
            exit;
        }
    }
}

if (!function_exists('rrms_bind_params')) {
    function rrms_bind_params($stmt, $types, array $params)
    {
        $refs = [];
        foreach ($params as $key => $value) {
            $refs[$key] = &$params[$key];
        }

        array_unshift($refs, $types);
        return call_user_func_array([$stmt, 'bind_param'], $refs);
    }
}

if (!function_exists('rrms_prepare_execute')) {
    function rrms_prepare_execute($conn, $sql, $types = '', array $params = [])
    {
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            error_log('SQL prepare failed: ' . mysqli_error($conn));
            return false;
        }

        if ($types !== '' && !rrms_bind_params($stmt, $types, $params)) {
            error_log('SQL bind failed: ' . mysqli_stmt_error($stmt));
            return false;
        }

        if (!mysqli_stmt_execute($stmt)) {
            error_log('SQL execute failed: ' . mysqli_stmt_error($stmt));
            return false;
        }

        return $stmt;
    }
}

$servername = rrms_env('RRMS_DB_HOST');
$username = rrms_env('RRMS_DB_USER');
$password = rrms_env('RRMS_DB_PASS');
$dbase = rrms_env('RRMS_DB_NAME');
$databasePort = (int) rrms_env('RRMS_DB_PORT', '3306');

if ($servername === '' || $username === '' || $dbase === '' || getenv('RRMS_DB_PASS') === false) {
    error_log('reQuest database environment is incomplete.');
    http_response_code(500);
    die('Database configuration is unavailable.');
}

try {
    $conn = mysqli_connect($servername, $username, $password, $dbase, $databasePort);
} catch (mysqli_sql_exception $exception) {
    error_log('reQuest database connection failed (code ' . $exception->getCode() . ').');
    http_response_code(500);
    die('Database connection failed.');
}

if (!$conn) {
    error_log('reQuest database connection failed.');
    http_response_code(500);
    die('Database connection failed.');
}

mysqli_set_charset($conn, 'utf8mb4');
date_default_timezone_set(rrms_env('RRMS_TIMEZONE', 'Asia/Manila'));

?>
