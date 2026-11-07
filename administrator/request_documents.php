<?php

// Fetch names in one batch, optionally restricted to the IDs on the visible page.
function rrms_request_document_map($conn, $status, $today = null, $requestIds = null)
{
    if ($status === 'released') {
        $where = "r.req_datetime_released != '0000-00-00 00:00:00'";
        $types = '';
        $params = [];
    } else {
        $where = rrms_request_list_where($status, 'r.');
        $types = 's';
        $params = [$today ?? date('Y-m-d')];
    }
    if ($requestIds !== null) {
        $requestIds = array_values(array_unique(array_filter(array_map('intval', $requestIds), function ($id) { return $id > 0; })));
        if (!$requestIds) {
            return [];
        }
        $where .= ' AND rb.requestorID IN (' . implode(',', array_fill(0, count($requestIds), '?')) . ')';
        $types .= str_repeat('i', count($requestIds));
        $params = array_merge($params, $requestIds);
    }

    $stmt = rrms_prepare_execute($conn, "
        SELECT rb.requestorID, d.doc_desc
        FROM tblrequested_by rb
        INNER JOIN tbl_request_info r ON r.req_id = rb.requestorID
        LEFT JOIN tbldoctypes d ON d.id = rb.doc_id
        WHERE $where
        ORDER BY rb.requestorID, rb.reqbyid
    ", $types, $params);
    if (!$stmt) {
        return false;
    }
    $result = mysqli_stmt_get_result($stmt);
    if (!$result) {
        mysqli_stmt_close($stmt);
        return false;
    }

    $documents = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $documents[(int) $row['requestorID']][] = trim((string) ($row['doc_desc'] ?? ''));
    }
    mysqli_free_result($result);
    mysqli_stmt_close($stmt);
    return $documents;
}

function rrms_request_documents_html($documents)
{
    if ($documents === null) {
        return '<span class="request-documents-empty text-muted">Unable to load documents</span>';
    }
    if (!$documents) {
        return '<span class="request-documents-empty text-muted">No documents requested</span>';
    }

    $html = '<ul class="request-documents">';
    foreach ($documents as $document) {
        $html .= '<li>' . rrms_h($document === '' ? 'Unavailable document type' : $document) . '</li>';
    }
    return $html . '</ul>';
}
