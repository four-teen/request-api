<?php

function rrms_request_table_query($conn, $sql, $types = '', $params = [])
{
    $stmt = rrms_prepare_execute($conn, $sql, $types, $params);
    if (!$stmt) {
        throw new RuntimeException('Unable to load requests.');
    }
    $result = mysqli_stmt_get_result($stmt);
    if (!$result) {
        mysqli_stmt_close($stmt);
        throw new RuntimeException('Unable to read requests.');
    }
    $rows = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_free_result($result);
    mysqli_stmt_close($stmt);
    return $rows;
}

function rrms_request_table_filter($status, $search, $today)
{
    $where = $status === 'released'
        ? "r.req_datetime_released != '0000-00-00 00:00:00'"
        : rrms_request_list_where($status, 'r.');
    $params = $status === 'released' ? [] : [$today];
    if ($search !== '') {
        // Preserve multi-word searching across names, dates, documents, and status.
        preg_match_all('/"([^"]+)"|(\S+)/u', $search, $matches, PREG_SET_ORDER);
        $terms = array_values(array_unique(array_map(function ($match) { return $match[1] !== '' ? $match[1] : $match[2]; }, $matches)));
        if (!$terms || count($terms) > 12) {
            $terms = [$search];
        }
        foreach ($terms as $term) {
            // Treat %, _, and the escape character as literal search text.
            $pattern = '%' . str_replace(['=', '%', '_'], ['==', '=%', '=_'], $term) . '%';
            $searchSql = [
                "CONCAT_WS(' ', r.req_lastname, r.req_firstname, r.req_middlename) LIKE ? ESCAPE '='",
                "CONCAT_WS(', ', r.req_lastname, CONCAT_WS(' ', r.req_firstname, r.req_middlename)) LIKE ? ESCAPE '='",
                "r.req_contact_number LIKE ? ESCAPE '='",
                "COALESCE(DATE_FORMAT(NULLIF(r.req_datetime_request, '0000-00-00 00:00:00'), '%b %d, %Y %h:%i %p'), 'Not set') LIKE ? ESCAPE '='",
                "COALESCE(DATE_FORMAT(NULLIF(r.req_due_date, '0000-00-00'), '%b %d, %Y'), 'Not set') LIKE ? ESCAPE '='",
                "r.req_id IN (
                    SELECT rb.requestorID FROM tblrequested_by rb
                    LEFT JOIN tbldoctypes d ON d.id = rb.doc_id
                    WHERE COALESCE(NULLIF(TRIM(d.doc_desc), ''), 'Unavailable document type') LIKE ? ESCAPE '='
                )",
            ];
            $params = array_merge($params, array_fill(0, count($searchSql), $pattern));
            if ($status === 'released') {
                $searchSql[] = "'Released' LIKE ? ESCAPE '='";
            } else {
                $diff = 'DATEDIFF(r.req_due_date, ?)';
                $searchSql[] = "(CASE
                    WHEN r.req_due_date IS NULL OR r.req_due_date = '0000-00-00' THEN 'No date set'
                    WHEN $diff = 0 THEN 'Due today'
                    WHEN $diff = 1 THEN 'Due tomorrow'
                    WHEN $diff > 1 THEN CONCAT($diff, ' days remaining')
                    ELSE CONCAT(ABS($diff), ' day', IF($diff = -1, '', 's'), ' overdue')
                END) LIKE ? ESCAPE '='";
                $params = array_merge($params, array_fill(0, 6, $today));
            }
            $params[] = $pattern;
            if (stripos('No documents requested', $term) !== false) {
                $searchSql[] = 'r.req_id NOT IN (SELECT requestorID FROM tblrequested_by WHERE requestorID IS NOT NULL)';
            }
            $where .= ' AND (' . implode(' OR ', $searchSql) . ')';
        }
    }
    return [$where, str_repeat('s', count($params)), $params];
}

function rrms_request_table_order($orders, $status)
{
    $columns = $status === 'released'
        ? [1 => 'requestor', 2 => 'dates', 3 => 'documents', 4 => 'status']
        : [2 => 'requestor', 3 => 'dates', 4 => 'documents', 5 => 'status'];
    $parts = [];
    $documentOrder = false;
    foreach (array_slice(is_array($orders) ? $orders : [], 0, 3) as $order) {
        if (!is_array($order) || !is_scalar($order['column'] ?? null)) {
            continue;
        }
        $column = $columns[(int) $order['column']] ?? '';
        $direction = ($order['dir'] ?? '') === 'asc' ? 'ASC' : 'DESC';
        if ($column === 'requestor') {
            foreach (['req_lastname', 'req_firstname', 'req_middlename'] as $name) {
                $parts[] = 'r.' . $name . ' ' . $direction;
            }
        } elseif ($column === 'dates') {
            $parts[] = 'r.req_datetime_request ' . $direction;
        } elseif ($column === 'documents') {
            $parts[] = "COALESCE(doc_order.document_name, 'No documents requested') " . $direction;
            $documentOrder = true;
        } elseif ($column === 'status') {
            $parts[] = "CASE WHEN r.req_due_date IS NULL OR r.req_due_date = '0000-00-00' THEN 1 ELSE 0 END ASC";
            $parts[] = 'r.req_due_date ' . $direction;
        }
    }
    // A stable final key prevents records moving between pages when names/dates match.
    $parts[] = 'r.req_id DESC';
    $join = $documentOrder ? "LEFT JOIN (
        SELECT rb.requestorID, MIN(COALESCE(NULLIF(TRIM(d.doc_desc), ''), 'Unavailable document type')) AS document_name
        FROM tblrequested_by rb LEFT JOIN tbldoctypes d ON d.id = rb.doc_id
        GROUP BY rb.requestorID
    ) doc_order ON doc_order.requestorID = r.req_id" : '';
    return [implode(', ', $parts), $join];
}

function rrms_request_table_date($value, $format, $uppercase = false)
{
    $raw = trim((string) $value);
    $timestamp = strtotime($raw);
    if ($raw === '' || strpos($raw, '0000-00-00') === 0 || $timestamp === false) {
        return 'Not set';
    }
    $text = date($format, $timestamp);
    return $uppercase ? strtoupper($text) : $text;
}

function rrms_request_table_button($handler, $title, $class, $icon, $text = '')
{
    return '<button type="button" class="btn ' . $class . ' btn-sm" title="' . rrms_h($title)
        . '" aria-label="' . rrms_h($title) . '" onclick="' . rrms_h($handler) . '">'
        . ($icon === '' ? '' : '<i class="' . $icon . '" aria-hidden="true"></i>') . rrms_h($text) . '</button>';
}

function rrms_request_table_row($row, $number, $documents, $hasRemarks, $status, $today)
{
    $id = (int) $row['req_id'];
    $fullname = strtoupper((string) $row['req_lastname']) . ', ' . strtoupper((string) $row['req_firstname']) . ' ' . strtoupper((string) ($row['req_middlename'] ?? ''));
    $details = rrms_request_table_button('showDetailsDrawer(' . $id . ')', 'View request details', 'btn-primary', 'bi bi-menu-down');
    if ($status === 'released') {
        $actions = rrms_request_table_button('showDetailsDrawer(' . $id . ')', 'View request details', 'btn-primary', '', 'Details');
    } else {
        $actions = '<div class="request-row-actions" role="group" aria-label="Request actions">' . $details
            . rrms_request_table_button('editRequestDrawer(' . $id . ')', 'Edit request', 'btn-success', 'bi bi-pencil-square')
            . rrms_request_table_button('request_docs(' . $id . ', ' . json_encode($fullname, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE) . ')', 'Manage requested documents', 'btn-warning', 'bi bi-chat-square-dots-fill')
            . rrms_request_table_button('remove_requestor(' . $id . ')', 'Delete request', 'btn-danger', 'bi bi-trash-fill') . '</div>';
    }
    return [
        'DT_RowId' => 'request-' . $id,
        'number' => $number . '.',
        'remarks' => rrms_request_table_button('add_remarks(' . $id . ')', 'Remarks', $hasRemarks ? 'btn-info' : 'btn-secondary', 'bi bi-info-square'),
        'requestor' => '<div class="requestor-name">' . rrms_h($fullname) . '</div><div class="request-contact-quote"><i class="bi bi-telephone" aria-hidden="true"></i><span>' . rrms_h($row['req_contact_number']) . '</span></div>',
        'dates' => '<span class="request-date-label">Date Request</span><span class="request-date-value">' . rrms_h(rrms_request_table_date($row['req_datetime_request'], 'M d, Y h:i a', true)) . '</span><span class="request-date-label mt-2">Due Date</span><span class="request-date-value text-danger">' . rrms_h(rrms_request_table_date($row['req_due_date'], 'M d, Y')) . '</span>',
        'documents' => rrms_request_documents_html($documents),
        'status' => $status === 'released' ? '<span class="badge bg-success"><i class="bx bx-check" aria-hidden="true"></i> Released</span>' : rrms_due_status_badge($row['req_due_date'], $today),
        'actions' => $actions,
    ];
}

function rrms_request_table_response($conn, $input, $today = null)
{
    $today = $today ?? date('Y-m-d');
    $status = in_array($input['request_status'] ?? '', ['pending', 'overdue', 'released'], true) ? $input['request_status'] : 'pending';
    $draw = max(0, (int) ($input['draw'] ?? 0));
    $start = max(0, (int) ($input['start'] ?? 0));
    $length = max(1, min(100, (int) ($input['length'] ?? 10)));
    $search = is_string($input['search']['value'] ?? null) ? trim($input['search']['value']) : '';
    preg_match('/\A.{0,200}/us', $search, $match);
    $search = $match[0] ?? '';
    $counts = rrms_request_counts($conn, $today);
    $total = (int) $counts[$status . '_requests'];
    [$where, $types, $params] = rrms_request_table_filter($status, $search, $today);
    $filtered = $search === '' ? $total : (int) rrms_request_table_query($conn, "SELECT COUNT(*) AS total FROM tbl_request_info r WHERE $where", $types, $params)[0]['total'];
    [$order, $join] = rrms_request_table_order($input['order'] ?? [], $status);
    $rows = rrms_request_table_query($conn, "
        SELECT r.req_id, r.req_lastname, r.req_firstname, r.req_middlename,
               r.req_contact_number, r.req_datetime_request, r.req_due_date
        FROM tbl_request_info r $join
        WHERE $where ORDER BY $order LIMIT ? OFFSET ?
    ", $types . 'ii', array_merge($params, [$length, $start]));
    $ids = array_column($rows, 'req_id');
    $documents = rrms_request_document_map($conn, $status, $today, $ids);
    if ($documents === false) {
        throw new RuntimeException('Unable to load documents.');
    }
    $remarks = [];
    if ($ids && $status !== 'released') {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $remarkRows = rrms_request_table_query($conn, "SELECT DISTINCT req_id FROM tblremarks WHERE req_id IN ($placeholders)", str_repeat('i', count($ids)), $ids);
        $remarks = array_fill_keys(array_column($remarkRows, 'req_id'), true);
    }
    $data = [];
    foreach ($rows as $index => $row) {
        $data[] = rrms_request_table_row($row, $start + $index + 1, $documents[(int) $row['req_id']] ?? [], isset($remarks[$row['req_id']]), $status, $today);
    }
    return ['draw' => $draw, 'recordsTotal' => $total, 'recordsFiltered' => $filtered, 'data' => $data, 'counts' => $counts];
}
