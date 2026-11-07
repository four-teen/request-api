<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require __DIR__ . '/../db.php';
require __DIR__ . '/../administrator/request_status.php';
require __DIR__ . '/../administrator/request_documents.php';
require __DIR__ . '/../administrator/request_table.php';

function request_table_assert($condition, $message)
{
    if (!$condition) throw new RuntimeException($message);
}

// Rendered fixture names cannot create elements or escape action attributes.
$fixture = ['req_id' => 123, 'req_lastname' => '<script>alert(1)</script>', 'req_firstname' => 'A "Quoted"', 'req_middlename' => null, 'req_contact_number' => '<img src=x onerror=alert(1)>', 'req_datetime_request' => '', 'req_due_date' => null];
$rendered = rrms_request_table_row($fixture, 1, ['<b>Document</b>'], true, 'pending', '2026-11-06');
foreach (['requestor', 'documents', 'actions'] as $cell) {
    request_table_assert(strpos($rendered[$cell], '<script>') === false && strpos($rendered[$cell], '<img') === false, 'A fixture value became executable markup.');
}
request_table_assert(substr_count($rendered['actions'], '<button') === 4, 'Request actions are missing.');
request_table_assert(strpos($rendered['remarks'], 'btn-info') !== false, 'Remarks presence was lost.');
request_table_assert(strpos($rendered['dates'], 'Not set') !== false && strpos($rendered['status'], 'No date set') !== false, 'Missing dates are not represented correctly.');
foreach (['2026-11-05' => '1 day overdue', '2026-11-06' => 'Due today', '2026-11-07' => 'Due tomorrow', '2026-11-08' => '2 days remaining'] as $due => $label) {
    request_table_assert(strpos(rrms_due_status_badge($due, '2026-11-06'), $label) !== false, 'Incorrect status at a date boundary.');
}

$today = date('Y-m-d');
$counts = rrms_request_counts($conn, $today);
foreach (['pending', 'overdue', 'released'] as $status) {
    $input = ['request_status' => $status, 'draw' => 7, 'start' => 0, 'length' => 10];
    $first = rrms_request_table_response($conn, $input, $today);
    $total = $counts[$status . '_requests'];
    request_table_assert($first['draw'] === 7 && $first['recordsTotal'] === $total && $first['recordsFiltered'] === $total, 'Incorrect totals/draw for ' . $status);
    request_table_assert(count($first['data']) === min(10, $total), 'First page size is wrong for ' . $status);
    $second = rrms_request_table_response($conn, array_merge($input, ['start' => 10]), $today);
    request_table_assert(array_intersect(array_column($first['data'], 'DT_RowId'), array_column($second['data'], 'DT_RowId')) === [], 'Pages overlap for ' . $status);
    if ($second['data']) request_table_assert($second['data'][0]['number'] === '11.', 'Row numbering did not advance.');
    $outside = rrms_request_table_response($conn, array_merge($input, ['start' => $total + 10]), $today);
    request_table_assert($outside['data'] === [] && $outside['recordsFiltered'] === $total, 'Out-of-range paging changes totals.');

    $fullMap = rrms_request_document_map($conn, $status, $today);
    request_table_assert(is_array($fullMap), 'Unable to verify document content.');
    foreach ($first['data'] as $row) {
        $id = (int) substr($row['DT_RowId'], strlen('request-'));
        request_table_assert($row['documents'] === rrms_request_documents_html($fullMap[$id] ?? []), 'Paged document names differ from the complete list.');
    }
    $ids = array_map(function ($row) { return (int) substr($row['DT_RowId'], strlen('request-')); }, $first['data']);
    $pageMap = rrms_request_document_map($conn, $status, $today, $ids);
    request_table_assert(array_diff(array_keys($pageMap), $ids) === [], 'Document loading escaped the visible page.');

    // A record beyond the first page remains searchable, even with name words reversed.
    if ($second['data']) {
        $target = $second['data'][0]['DT_RowId'];
        $targetId = (int) substr($target, strlen('request-'));
        $person = rrms_request_table_query($conn, 'SELECT req_firstname, req_lastname FROM tbl_request_info WHERE req_id = ?', 'i', [$targetId])[0];
        $search = trim($person['req_firstname'] . ' ' . $person['req_lastname']);
        $searchInput = array_merge($input, ['length' => 100, 'search' => ['value' => $search]]);
        $found = false;
        for ($start = 0; ; $start += 100) {
            $match = rrms_request_table_response($conn, array_merge($searchInput, ['start' => $start]), $today);
            if (in_array($target, array_column($match['data'], 'DT_RowId'), true)) { $found = true; break; }
            if ($start + 100 >= $match['recordsFiltered']) break;
        }
        request_table_assert($found, 'Search did not cover records beyond the first page.');
    }
    $sortIndex = $status === 'released' ? 3 : 4;
    $sorted = rrms_request_table_response($conn, array_merge($input, ['order' => [['column' => $sortIndex, 'dir' => 'asc']]]), $today);
    request_table_assert(count($sorted['data']) === min(10, $total), 'Document sorting lost records.');
}

$bounded = rrms_request_table_response($conn, ['request_status' => 'overdue', 'length' => 10000, 'start' => -10, 'draw' => '<script>', 'order' => [['column' => 999, 'dir' => 'untrusted']]], $today);
request_table_assert(count($bounded['data']) <= 100 && is_int($bounded['draw']), 'Client input bypassed paging bounds or draw casting.');
$empty = rrms_request_table_response($conn, ['request_status' => 'overdue', 'search' => ['value' => 'rrms-no-match-' . bin2hex(random_bytes(16))]], $today);
request_table_assert($empty['recordsFiltered'] === 0 && $empty['data'] === [], 'A search with no matches did not return an empty result.');
[, $types, $params] = rrms_request_table_filter('released', '%_', $today);
$pattern = $params[0];
$literal = rrms_request_table_query($conn, "SELECT 'plain' LIKE ? ESCAPE '=' AS no_match, 'name%_suffix' LIKE ? ESCAPE '=' AS literal_match", 'ss', [$pattern, $pattern])[0];
request_table_assert((int) $literal['no_match'] === 0 && (int) $literal['literal_match'] === 1, 'Search wildcard characters were not treated literally.');
echo 'PASS: safe rendering, date boundaries, bounded/disjoint pages, complete document content, page-only lookups, cross-page/multi-word search, sorting, literal wildcard handling, and totals for all three lists. No records changed.' . PHP_EOL;
