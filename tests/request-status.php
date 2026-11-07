<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require __DIR__ . '/../db.php';
require __DIR__ . '/../administrator/request_status.php';

function request_status_assert($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

// Derived rows exercise status transitions without inserting or changing database records.
$fixtures = "(
    SELECT 1 AS req_id, '2026-11-05' AS req_due_date, '0000-00-00 00:00:00' AS req_datetime_released
    UNION ALL SELECT 2, '2026-11-06', '0000-00-00 00:00:00'
    UNION ALL SELECT 3, '2026-11-07', '0000-00-00 00:00:00'
    UNION ALL SELECT 4, '0000-00-00', '0000-00-00 00:00:00'
    UNION ALL SELECT 5, NULL, '0000-00-00 00:00:00'
    UNION ALL SELECT 6, '2026-11-05', '2026-11-06 09:00:00'
) r";
$cases = [
    ['pending', '2026-11-06', [2, 3, 4, 5]],
    ['overdue', '2026-11-06', [1]],
    ['pending', '2026-11-07', [3, 4, 5]],
    ['overdue', '2026-11-07', [1, 2]],
];
foreach ($cases as $case) {
    list($status, $today, $expected) = $case;
    $where = rrms_request_list_where($status, 'r.');
    $stmt = rrms_prepare_execute($conn, "SELECT req_id FROM $fixtures WHERE $where ORDER BY req_id", 's', [$today]);
    $result = $stmt ? mysqli_stmt_get_result($stmt) : false;
    request_status_assert((bool) $result, 'Unable to run status boundary check.');
    $ids = array_map('intval', array_column(mysqli_fetch_all($result, MYSQLI_ASSOC), 'req_id'));
    request_status_assert($ids === $expected, 'Incorrect ' . $status . ' classification on ' . $today);
}

$today = date('Y-m-d');
$counts = rrms_request_counts($conn, $today);
$activeResult = mysqli_query($conn, "SELECT COUNT(*) AS total FROM tbl_request_info WHERE req_datetime_released = '0000-00-00 00:00:00'");
$active = (int) mysqli_fetch_assoc($activeResult)['total'];
request_status_assert($counts['pending_requests'] + $counts['overdue_requests'] === $active, 'Active requests are missing or counted twice.');
$groups = [];
foreach (['pending', 'overdue'] as $status) {
    $where = rrms_request_list_where($status, 'r.');
    $stmt = rrms_prepare_execute($conn, "SELECT r.req_id FROM tbl_request_info r WHERE $where ORDER BY r.req_id", 's', [$today]);
    $result = mysqli_stmt_get_result($stmt);
    $groups[$status] = array_map('intval', array_column(mysqli_fetch_all($result, MYSQLI_ASSOC), 'req_id'));
    request_status_assert(count($groups[$status]) === $counts[$status . '_requests'], 'List and card counts differ for ' . $status);
}
request_status_assert(array_intersect($groups['pending'], $groups['overdue']) === [], 'Pending and overdue overlap.');
echo 'PASS: due-today, future, overdue, missing-date, released, and next-day transitions; disjoint lists; matching card counts.' . PHP_EOL;
echo json_encode(['pending' => $counts['pending_requests'], 'overdue' => $counts['overdue_requests'], 'unreleased' => $active]), PHP_EOL;