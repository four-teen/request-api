<?php
session_start();
require __DIR__ . '/../db.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    echo json_encode(['message' => 'Method not allowed.']);
    exit;
}
$user = rrms_current_user();
echo json_encode([
    'csrfToken' => rrms_csrf_token(),
    'user' => !$user ? null : [
        'id' => $user['id'], 'username' => $user['username'], 'fullname' => $user['fullname'],
        'role' => $user['role'], 'permissions' => $user['permissions'],
    ],
    'workspaceUrl' => $user ? rrms_user_workspace($user) : '/dashboard',
]);
