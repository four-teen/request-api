<?php
session_start();
ob_start();

include __DIR__ . '/db.php';

function request_login_failure($message, $status)
{
    if (defined('REQUEST_AUTH_JSON') && REQUEST_AUTH_JSON) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode(['message' => $message]);
    } else {
        $_SESSION['status'] = $message;
        header('Location: index.php');
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    request_login_failure('Method not allowed.', 405);
}

if (!rrms_verify_csrf(rrms_request_csrf_token())) {
    request_login_failure('Your session expired. Please try logging in again.', 419);
}

$username = trim($_POST['username'] ?? '');
$password = (string) ($_POST['password'] ?? '');

if ($username === '' || $password === '') {
    request_login_failure('Please fill in both fields.', 400);
}

$stmt = rrms_prepare_execute(
    $conn,
    'SELECT acc_id, username, fullname, password FROM tblaccounts WHERE username = ? LIMIT 1',
    's',
    [$username]
);

if (!$stmt) {
    request_login_failure('Unable to sign in right now.', 503);
}

$result = mysqli_stmt_get_result($stmt);
$row = $result ? mysqli_fetch_assoc($result) : null;
$valid = false;

if ($row) {
    $storedPassword = (string) $row['password'];
    $passwordInfo = password_get_info($storedPassword);

    if (!empty($passwordInfo['algo'])) {
        $valid = password_verify($password, $storedPassword);
    } else {
        $valid = hash_equals($storedPassword, $password);
    }
}

if (!$valid) {
    request_login_failure('Invalid username or password.', 401);
}

session_regenerate_id(true);

$_SESSION['username'] = $row['username'];
$_SESSION['acc_id'] = $row['acc_id'];
$_SESSION['fullname'] = $row['fullname'];
rrms_csrf_token();

if (empty(password_get_info((string) $row['password'])['algo'])) {
    $newHash = password_hash($password, PASSWORD_DEFAULT);
    rrms_prepare_execute(
        $conn,
        'UPDATE tblaccounts SET password = ? WHERE acc_id = ?',
        'si',
        [$newHash, (int) $row['acc_id']]
    );
}

if (defined('REQUEST_AUTH_JSON') && REQUEST_AUTH_JSON) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode(['workspaceUrl' => '/api/administrator/']);
    exit;
}

header('Location: administrator/');
exit;

?>
