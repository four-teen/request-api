<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require __DIR__ . '/../db.php';
require __DIR__ . '/../administrator/request_status.php';
require __DIR__ . '/../administrator/request_documents.php';

function request_documents_assert($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$html = rrms_request_documents_html(['Transcript & Certification', '<img src=x onerror=alert(1)>', 'Repeat', 'Repeat', '']);
request_documents_assert(substr_count($html, '<li>') === 5, 'Documents or duplicate entries were lost.');
request_documents_assert(strpos($html, 'Transcript &amp; Certification') !== false, 'Document names were not escaped.');
request_documents_assert(strpos($html, '<img') === false && strpos($html, '&lt;img') !== false, 'A document name became executable markup.');
request_documents_assert(strpos($html, 'Unavailable document type') !== false, 'An unavailable document type was hidden.');
request_documents_assert(strpos(rrms_request_documents_html([]), 'No documents requested') !== false, 'Empty requests are not identified.');
request_documents_assert(strpos(rrms_request_documents_html(null), 'Unable to load documents') !== false, 'Load failures are confused with empty requests.');

// Compare complete document maps with independent row counts; all queries are read-only.
$today = date('Y-m-d');
$maps = [];
foreach (['pending', 'overdue', 'released'] as $status) {
    $maps[$status] = rrms_request_document_map($conn, $status, $today);
    request_documents_assert(is_array($maps[$status]), 'Unable to load the ' . $status . ' document map.');
    $where = $status === 'released'
        ? "r.req_datetime_released != '0000-00-00 00:00:00'"
        : rrms_request_list_where($status, 'r.');
    $stmt = rrms_prepare_execute($conn, "
        SELECT COUNT(*) AS total
        FROM tblrequested_by rb
        INNER JOIN tbl_request_info r ON r.req_id = rb.requestorID
        WHERE $where
    ", $status === 'released' ? '' : 's', $status === 'released' ? [] : [$today]);
    $result = $stmt ? mysqli_stmt_get_result($stmt) : false;
    request_documents_assert((bool) $result, 'Unable to count ' . $status . ' document rows.');
    $expected = (int) mysqli_fetch_assoc($result)['total'];
    $actual = array_sum(array_map('count', $maps[$status]));
    request_documents_assert($actual === $expected, 'Document rows were lost or duplicated in the ' . $status . ' list.');
    mysqli_free_result($result);
    mysqli_stmt_close($stmt);
}
request_documents_assert(array_intersect(array_keys($maps['pending']), array_keys($maps['overdue'])) === [], 'Pending and overdue document maps overlap.');
request_documents_assert(array_intersect(array_keys($maps['pending'] + $maps['overdue']), array_keys($maps['released'])) === [], 'Released documents appear in unreleased lists.');
echo 'PASS: document-name escaping, duplicate/empty/missing cases, load-error rendering, and matching read-only document maps for pending, overdue, and released requests.' . PHP_EOL;
