<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function rrms_log_reply($status, $data)
{
    http_response_code($status);
    echo json_encode($data, JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function rrms_log_input_id($value, $message)
{
    $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($id === false) throw new InvalidArgumentException($message);
    return $id;
}

if (empty($_SESSION['username']) || empty($_SESSION['acc_id'])) {
    rrms_log_reply(401, ['message' => 'Your session expired. Please sign in again.']);
}
require __DIR__ . '/../db.php';
require __DIR__ . '/record_log_functions.php';
$method = $_SERVER['REQUEST_METHOD'];
if (!in_array($method, ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    rrms_log_reply(405, ['message' => 'Method not allowed.']);
}
if ($method === 'POST' && !rrms_verify_csrf(rrms_request_csrf_token())) {
    rrms_log_reply(419, ['message' => 'Your security token expired. Refresh the page and try again.']);
}
$actorId = (int) $_SESSION['acc_id'];
session_write_close();

try {
    $now = new DateTimeImmutable('now');
    if ($method === 'GET') {
        $action = $_GET['action'] ?? 'list';
        if (!is_string($_GET['q'] ?? '')) throw new InvalidArgumentException('Enter a student name.');
        if (strlen($_GET['q'] ?? '') > 200) throw new InvalidArgumentException('Search must be 200 characters or fewer.');
        if ($action === 'students') {
            rrms_log_reply(200, ['students' => rrms_log_search_students($conn, trim($_GET['q'] ?? ''))]);
        }
        if ($action !== 'list') throw new InvalidArgumentException('Unknown record log action.');
        rrms_log_reply(200, rrms_log_list($conn, $_GET, $now->format('Y-m-d')));
    }
    $actors = rrms_log_rows($conn, 'SELECT acc_id, fullname, username FROM tblaccounts WHERE acc_id = ?', 'i', [$actorId]);
    if (!$actors) rrms_log_reply(401, ['message' => 'Your account is unavailable. Please sign in again.']);
    $actor = ['id' => $actorId, 'name' => trim($actors[0]['fullname']) ?: $actors[0]['username']];
    if (!is_string($_POST['remarks'] ?? '')) throw new InvalidArgumentException('Enter valid remarks.');
    $remarks = trim($_POST['remarks'] ?? '');
    if (strlen($remarks) > 2000) throw new InvalidArgumentException('Remarks must be 2,000 characters or fewer.');
    if (($_POST['action'] ?? '') === 'out') {
        $requestId = rrms_log_input_id($_POST['request_id'] ?? 0, 'Select a student with an existing request.');
        $id = rrms_log_create($conn, $requestId, $actor, $now, $remarks);
        rrms_log_reply(201, ['message' => 'Student record logged out.', 'id' => $id]);
    }
    if (($_POST['action'] ?? '') === 'return') {
        $id = rrms_log_input_id($_POST['id'] ?? 0, 'Select a valid record log.');
        $checkerId = rrms_log_input_id($_POST['checked_by_id'] ?? 0, 'Choose Checked By from the Program Incharge list.');
        rrms_log_return($conn, $id, $checkerId, $actor, $now, $remarks);
        rrms_log_reply(200, ['message' => 'Return and checker recorded.']);
    }
    throw new InvalidArgumentException('Unknown record log action.');
} catch (InvalidArgumentException $error) {
    rrms_log_reply(422, ['message' => $error->getMessage()]);
} catch (DomainException $error) {
    rrms_log_reply(409, ['message' => $error->getMessage()]);
} catch (mysqli_sql_exception $error) {
    if ($error->getCode() === 1062) rrms_log_reply(409, ['message' => 'This student already has an outstanding record log. Record its return before logging out again.']);
    error_log('Record logs database error (code ' . $error->getCode() . ').');
    rrms_log_reply(503, ['message' => 'Unable to load or save record logs. Please contact the administrator.']);
} catch (Throwable $error) {
    error_log('Record logs error: ' . get_class($error));
    rrms_log_reply(503, ['message' => 'Unable to load or save record logs. Please try again.']);
}
