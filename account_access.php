<?php
// Shared authorization. Loading this file never creates tables or assigns roles.
function rrms_permission_catalog()
{
    return [
        'dashboard.view' => ['Overview', 'View dashboard'],
        'requests.view' => ['Requests', 'View pending, overdue, and released requests'],
        'requests.create' => ['Requests', 'Create requests'],
        'requests.edit' => ['Requests', 'Edit requests, documents, and remarks'],
        'requests.release' => ['Requests', 'Release requests'],
        'requests.delete' => ['Requests', 'Delete requests'],
        'record_logs.view' => ['Student Record Logs', 'View register and search students'],
        'record_logs.out' => ['Student Record Logs', 'Log records out'],
        'record_logs.return' => ['Student Record Logs', 'Record returns and checkers'],
        'documents.view' => ['Document Types', 'View document types'],
        'documents.manage' => ['Document Types', 'Add and remove document types'],
        'statistics.view' => ['Reports', 'View statistics'],
        'reports.view' => ['Reports', 'Generate and print released reports'],
    ];
}

function rrms_permission_dependencies()
{
    return [
        'requests.create' => 'requests.view', 'requests.edit' => 'requests.view',
        'requests.release' => 'requests.view', 'requests.delete' => 'requests.view',
        'record_logs.out' => 'record_logs.view', 'record_logs.return' => 'record_logs.view',
        'documents.manage' => 'documents.view',
    ];
}

function rrms_account_rows($conn, $sql, $types = '', $params = [])
{
    $stmt = rrms_prepare_execute($conn, $sql, $types, $params);
    if (!$stmt) throw new RuntimeException('Unable to read account access.');
    $result = mysqli_stmt_get_result($stmt);
    $rows = $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];
    mysqli_stmt_close($stmt);
    return $rows;
}

function rrms_account_principal($conn, $id)
{
    $rows = rrms_account_rows($conn, 'SELECT a.acc_id, a.username,
        COALESCE(i.inchargename, a.fullname) AS fullname, x.incharge_id,
        x.account_role, x.is_active, x.session_version
        FROM tblaccounts a INNER JOIN tbl_account_access x ON x.account_id = a.acc_id
        LEFT JOIN tblincharge i ON i.inchargeid = x.incharge_id WHERE a.acc_id = ?', 'i', [$id]);
    if (!$rows || !(int) $rows[0]['is_active']) return null;
    $row = $rows[0];
    if (!in_array($row['account_role'], ['administrator', 'user'], true)) return null;
    $permissions = rrms_account_rows($conn, 'SELECT permission_key FROM tbl_account_permissions WHERE account_id = ?', 'i', [$id]);
    return [
        'id' => (int) $row['acc_id'], 'username' => $row['username'], 'fullname' => $row['fullname'],
        'chairId' => $row['incharge_id'] === null ? null : (int) $row['incharge_id'],
        'role' => $row['account_role'], 'version' => (int) $row['session_version'],
        'permissions' => array_values(array_intersect(array_keys(rrms_permission_catalog()), array_column($permissions, 'permission_key'))),
    ];
}

function rrms_session_user($conn)
{
    if (empty($_SESSION['acc_id']) || !isset($_SESSION['auth_version'])) return null;
    $user = rrms_account_principal($conn, (int) $_SESSION['acc_id']);
    return $user && $user['version'] === (int) $_SESSION['auth_version'] ? $user : null;
}

function rrms_current_user()
{
    global $conn;
    if (array_key_exists('rrms_authenticated_user', $GLOBALS)) return $GLOBALS['rrms_authenticated_user'];
    try {
        return $GLOBALS['rrms_authenticated_user'] = rrms_session_user($conn);
    } catch (Throwable $error) {
        error_log('reQuest account access unavailable (code ' . $error->getCode() . ').');
        return $GLOBALS['rrms_authenticated_user'] = null;
    }
}

function rrms_user_can($user, $permission)
{
    if (!$user) return false;
    if ($user['role'] === 'administrator') return true;
    return $permission !== 'administration' && in_array($permission, $user['permissions'], true);
}

function rrms_can($permission)
{
    return rrms_user_can(rrms_current_user(), $permission);
}

function rrms_access_denied($status, $message)
{
    http_response_code($status);
    header('Cache-Control: no-store');
    if (strpos(basename($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? ''), 'query_') === 0
        || strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['message' => $message, 'error' => $message]);
    } else {
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Access unavailable | reQuest</title></head><body><h1>Access unavailable</h1><p>'
            . rrms_h($message) . '</p><p><a href="../logout.php">Sign in again</a></p></body></html>';
    }
    exit;
}

function rrms_require_permission($permission)
{
    rrms_require_login();
    if (!rrms_can($permission)) rrms_access_denied(403, 'Your account does not have access to this function. Contact the administrator.');
}

function rrms_user_workspace($user)
{
    foreach ([
        'dashboard.view' => '/dashboard', 'requests.view' => '/requests', 'record_logs.view' => '/record-logs',
        'documents.view' => '/document-types', 'statistics.view' => '/statistics', 'reports.view' => '/reports',
    ] as $permission => $path) if (rrms_user_can($user, $permission)) return $path;
    return null;
}

function rrms_set_login_session($user)
{
    $_SESSION['acc_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['fullname'] = $user['fullname'];
    $_SESSION['auth_version'] = $user['version'];
    $GLOBALS['rrms_authenticated_user'] = $user;
}

function rrms_request_action_permissions($input)
{
    $actions = [
        'request_table' => 'requests.view', 'update_request' => 'requests.edit', 'load_edit_drawer' => 'requests.edit',
        'released_requested' => 'requests.release', 'load_details_drawer' => 'requests.view',
        'delete_the_requestor_requested' => 'requests.edit', 'save_doc_type_requestor' => 'requests.edit',
        'loding_req_req' => 'requests.edit', 'delete_the_requestor' => 'requests.delete',
        'save_request' => 'requests.create', 'delete_doc_type' => 'documents.manage',
        'save_doc_type' => 'documents.manage', 'loading_doc_type' => 'documents.view',
        'loading_released_summary' => 'requests.view', 'loading_employee' => 'requests.view',
    ];
    $required = [];
    foreach ($actions as $key => $permission) if (isset($input[$key])) $required[] = $permission;
    if (isset($input['req_id'], $input['remarks'])) $required[] = 'requests.edit';
    return array_values(array_unique($required));
}
