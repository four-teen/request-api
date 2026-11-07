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
echo json_encode([
    'csrfToken' => rrms_csrf_token(),
    'user' => empty($_SESSION['username']) ? null : [
        'id' => (int) $_SESSION['acc_id'],
        'username' => $_SESSION['username'],
        'fullname' => $_SESSION['fullname'],
    ],
    'workspaceUrl' => '/api/administrator/',
]);
