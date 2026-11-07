<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../db.php';
require __DIR__ . '/../administrator/record_log_functions.php';

function log_assert($condition, $message)
{
    if (!$condition) throw new RuntimeException($message);
}
function log_throws($class, $callback, $message)
{
    try { $callback(); } catch (Throwable $error) {
        log_assert($error instanceof $class, $message . ': unexpected exception ' . get_class($error));
        return $error;
    }
    throw new RuntimeException($message . ': no exception');
}

$timezone = new DateTimeZone('Asia/Manila');
foreach ([
    ['2026-10-15 09:30:00', '2026-10-26'],
    ['2026-11-06 23:59:59', '2026-11-17'],
    ['2026-11-07 09:00:00', '2026-11-17'],
    ['2026-11-08 09:00:00', '2026-11-17'],
    ['2026-11-09 09:00:00', '2026-11-18'],
    ['2026-12-25 09:00:00', '2027-01-05'],
] as [$out, $expected]) {
    log_assert(rrms_log_due_date(new DateTimeImmutable($out, $timezone)) === $expected, 'Incorrect seven-working-day due date for ' . $out);
}
log_assert(rrms_log_status(['due_date' => '2026-11-17', 'returned_at' => null], '2026-11-17') === 'out', 'Due date must remain available for return.');
log_assert(rrms_log_status(['due_date' => '2026-11-17', 'returned_at' => null], '2026-11-18') === 'overdue', 'Overdue must start after the seventh working day.');
log_assert(rrms_log_status(['due_date' => '2026-11-17', 'returned_at' => '2026-11-19 08:00:00'], '2026-11-20') === 'returned', 'An overdue return must become returned.');

// Shadow the production table only in this connection. All writes disappear on disconnect.
$tableDefinition = mysqli_fetch_assoc(mysqli_query($conn, 'SHOW CREATE TABLE tbl_record_logs'))['Create Table'];
mysqli_query($conn, preg_replace('/^CREATE TABLE /', 'CREATE TEMPORARY TABLE ', $tableDefinition));
$source = rrms_log_rows($conn, rrms_log_student_select() . ' ORDER BY r.req_id DESC LIMIT 1');
$checkers = rrms_log_rows($conn, 'SELECT inchargeid, inchargename FROM tblincharge ORDER BY inchargeid LIMIT 1');
log_assert((bool) $source && (bool) $checkers, 'These checks require an existing request and program incharge.');
$student = rrms_log_student($source[0]);
$actor = ['id' => 900001, 'name' => 'Test recorder'];
$receiver = ['id' => 900002, 'name' => 'Test return recorder'];
$out = new DateTimeImmutable('2026-11-06 10:25:00', $timezone);
$id = rrms_log_create($conn, $student['request_id'], $actor, $out, '<script>fixture</script>');
$saved = rrms_log_rows($conn, 'SELECT * FROM tbl_record_logs WHERE id = ?', 'i', [$id])[0];
log_assert($saved['student_name'] === $student['student_name'] && $saved['course'] === $student['course'], 'Encoded student details must be copied from the server.');
log_assert($saved['attendance_year'] === $student['attendance_year'], 'The encoded attendance year must be preserved.');
log_assert($saved['logged_out_by_name'] === $actor['name'] && (int) $saved['logged_out_by_id'] === $actor['id'], 'Log-out actor missing.');
log_assert($saved['logged_out_at'] === '2026-11-06 10:25:00' && $saved['due_date'] === '2026-11-17', 'Saved date/time or due date is incorrect.');
$duplicate = log_throws(mysqli_sql_exception::class, function () use ($conn, $student, $actor, $out) {
    rrms_log_create($conn, $student['request_id'], $actor, $out, 'duplicate');
}, 'Duplicate open log must fail');
log_assert($duplicate->getCode() === 1062, 'Expected the unique open-student constraint.');
$search = rrms_log_search_students($conn, trim($source[0]['req_firstname']));
$matching = array_values(array_filter($search, function ($item) use ($student) { return $item['request_id'] === $student['request_id']; }));
log_assert(count($matching) === 1 && $matching[0]['open_log_id'] === $id, 'Search must show the open log and latest encoded student.');
$list = rrms_log_list($conn, ['status' => 'overdue'], '2026-11-18');
log_assert($list['total'] === 1 && $list['counts']['overdue'] === 1, 'Overdue filter and counts disagree.');
log_assert(!isset($list['rows'][0]['student_key']) && !isset($list['rows'][0]['active_student_key']), 'Internal identity keys must not be returned.');
log_throws(InvalidArgumentException::class, function () use ($conn, $id, $receiver, $out) {
    rrms_log_return($conn, $id, 0, $receiver, $out, '');
}, 'Return without a real checker must fail');
$returned = new DateTimeImmutable('2026-11-19 11:30:00', $timezone);
rrms_log_return($conn, $id, (int) $checkers[0]['inchargeid'], $receiver, $returned, 'Checked fixture');
$saved = rrms_log_rows($conn, 'SELECT * FROM tbl_record_logs WHERE id = ?', 'i', [$id])[0];
log_assert($saved['returned_at'] === '2026-11-19 11:30:00', 'Return time missing.');
log_assert($saved['returned_by_name'] === $receiver['name'] && (int) $saved['returned_by_id'] === $receiver['id'], 'Return must retain its own current account.');
log_assert($saved['checked_by_name'] === $checkers[0]['inchargename'], 'Checker must come from Program Incharge.');
log_assert($saved['logged_out_by_name'] === $actor['name'], 'Returning must preserve the original recorder.');
log_assert($saved['active_student_key'] === null, 'Returned record must release the outstanding constraint.');
log_throws(DomainException::class, function () use ($conn, $id, $checkers, $receiver, $returned) {
    rrms_log_return($conn, $id, (int) $checkers[0]['inchargeid'], $receiver, $returned, 'replay');
}, 'Repeated return must fail');
$list = rrms_log_list($conn, ['status' => 'returned'], '2026-11-20');
log_assert($list['counts']['returned'] === 1 && $list['counts']['overdue'] === 0 && $list['rows'][0]['status'] === 'returned', 'Returned status and counts disagree.');
rrms_log_create($conn, $student['request_id'], $actor, $returned, 'Can log out again after return');
log_throws(InvalidArgumentException::class, function () use ($conn, $actor, $out) {
    rrms_log_create($conn, -1, $actor, $out, '');
}, 'Students without an encoded request must fail');
log_assert(rrms_log_search_students($conn, '%_fixture') === [], 'Search wildcards must remain literal.');
mysqli_query($conn, 'DROP TEMPORARY TABLE tbl_record_logs');
echo "PASS: seven-working-day boundaries, weekend/year transitions, encoded-student lookup, duplicate prevention, overdue filters, return audit/checker, repeated-return protection, and subsequent log-out.\n";
echo "All write checks used a connection-local temporary table; no real student requests or record logs were changed.\n";
