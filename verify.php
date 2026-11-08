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

try {
    $stmt = rrms_prepare_execute(
        $conn,
        'SELECT a.acc_id, a.username, a.fullname, a.password, x.session_version
         FROM tblaccounts a LEFT JOIN tbl_account_access x ON x.account_id = a.acc_id WHERE a.username = ? LIMIT 1',
        's',
        [$username]
    );
} catch (Throwable $error) {
    error_log('reQuest sign-in access setup unavailable (code ' . $error->getCode() . ').');
    request_login_failure('Account access is not configured. Please contact the administrator.', 503);
}

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

try {
    $user = rrms_account_principal($conn, (int) $row['acc_id']);
} catch (Throwable $error) {
    error_log('reQuest sign-in access setup unavailable (code ' . $error->getCode() . ').');
    request_login_failure('Account access is not configured. Please contact the administrator.', 503);
}
if (!$user || $user['version'] !== (int) $row['session_version'] || !rrms_user_workspace($user)) request_login_failure('Your account is unavailable or has changed. Please sign in again or contact the administrator.', 403);

session_regenerate_id(true);
rrms_set_login_session($user);
rrms_csrf_token();

if (empty(password_get_info((string) $row['password'])['algo'])) {
    $newHash = password_hash($password, PASSWORD_DEFAULT);
    rrms_prepare_execute(
        $conn,
        'UPDATE tblaccounts SET password = ? WHERE acc_id = ? AND password = ?',
        'sis',
        [$newHash, (int) $row['acc_id'], $storedPassword]
    );
}

if (defined('REQUEST_AUTH_JSON') && REQUEST_AUTH_JSON) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode(['workspaceUrl' => rrms_user_workspace($user)]);
    exit;
}

header('Location: ' . rrms_user_workspace($user));
exit;

?>
