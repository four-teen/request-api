<?php

function rrms_log_due_date(DateTimeImmutable $out)
{
    $due = $out->setTime(0, 0);
    for ($days = 0; $days < 7;) {
        $due = $due->modify('+1 day');
        if ((int) $due->format('N') <= 5) {
            $days++;
        }
    }
    return $due->format('Y-m-d');
}

function rrms_log_status($row, $today)
{
    if (!empty($row['returned_at'])) return 'returned';
    return $row['due_date'] < $today ? 'overdue' : 'out';
}

function rrms_log_rows($conn, $sql, $types = '', $params = [])
{
    $stmt = rrms_prepare_execute($conn, $sql, $types, $params);
    if (!$stmt) throw new RuntimeException('Unable to read record logs.');
    $result = mysqli_stmt_get_result($stmt);
    $rows = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_free_result($result);
    mysqli_stmt_close($stmt);
    return $rows;
}

function rrms_log_student($row)
{
    $parts = array_map(function ($field) use ($row) {
        return preg_replace('/\s+/u', ' ', trim((string) ($row[$field] ?? '')));
    }, ['req_firstname', 'req_middlename', 'req_lastname', 'req_ext']);
    $identity = array_merge($parts, [(string) $row['req_program']]);
    $identity = array_map(function ($value) {
        return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    }, $identity);
    $course = trim((string) ($row['coursedescription'] ?? ''));
    if ($course === '') $course = trim((string) ($row['coursecode'] ?? '')) ?: 'Program not recorded';
    if (!empty($row['coursemajor'])) $course .= ' — ' . $row['coursemajor'];
    return [
        'request_id' => (int) $row['req_id'],
        'student_key' => hash('sha256', json_encode($identity, JSON_UNESCAPED_UNICODE)),
        'student_name' => implode(' ', array_filter($parts, function ($part) { return $part !== ''; })),
        'course' => $course,
        'attendance_year' => (string) ($row['req_year_attendance'] ?? ''),
        'contact' => (string) ($row['req_contact_number'] ?? ''),
        'program_incharge_id' => (int) ($row['prog_assigned'] ?? 0),
        'program_incharge_name' => (string) ($row['inchargename'] ?? ''),
    ];
}

function rrms_log_student_select()
{
    return 'SELECT r.*, c.coursecode, c.coursedescription, c.coursemajor, i.inchargename
        FROM tbl_request_info r LEFT JOIN tblcourse c ON c.courseid = r.req_program
        LEFT JOIN tblincharge i ON i.inchargeid = r.prog_assigned';
}

function rrms_log_search_students($conn, $search)
{
    if (strlen($search) < 2) return [];
    $terms = preg_split('/\s+/u', str_replace(',', ' ', $search), -1, PREG_SPLIT_NO_EMPTY);
    $where = [];
    $params = [];
    foreach (array_slice($terms, 0, 8) as $term) {
        $where[] = "CONCAT_WS(' ', r.req_firstname, r.req_middlename, r.req_lastname, r.req_ext) LIKE ? ESCAPE '='";
        $params[] = '%' . str_replace(['=', '%', '_'], ['==', '=%', '=_'], $term) . '%';
    }
    if (!$where) return [];
    // Requests are the existing student source. Show the latest encoded request per name/program.
    $sql = rrms_log_student_select() . ' INNER JOIN (
        SELECT MAX(req_id) AS latest_id FROM tbl_request_info
        GROUP BY LOWER(TRIM(req_firstname)), LOWER(TRIM(req_middlename)), LOWER(TRIM(req_lastname)),
                 LOWER(TRIM(req_ext)), req_program
    ) latest ON latest.latest_id = r.req_id WHERE ' . implode(' AND ', $where) . ' ORDER BY r.req_id DESC LIMIT 20';
    $students = array_map('rrms_log_student', rrms_log_rows($conn, $sql, str_repeat('s', count($params)), $params));
    foreach ($students as &$student) {
        $open = rrms_log_rows($conn, 'SELECT id FROM tbl_record_logs WHERE active_student_key = ?', 's', [$student['student_key']]);
        $student['open_log_id'] = $open ? (int) $open[0]['id'] : null;
        unset($student['student_key']);
    }
    unset($student);
    return $students;
}

function rrms_log_list($conn, $input, $today)
{
    $filter = $input['status'] ?? 'all';
    $page = max(1, min(1000000, (int) ($input['page'] ?? 1)));
    $search = trim((string) ($input['q'] ?? ''));
    $where = '1=1';
    $types = '';
    $params = [];
    if ($filter === 'returned') $where .= ' AND returned_at IS NOT NULL';
    if ($filter === 'out') $where .= ' AND returned_at IS NULL';
    if ($filter === 'overdue') {
        $where .= ' AND returned_at IS NULL AND due_date < ?';
        $types .= 's';
        $params[] = $today;
    }
    if ($search !== '') {
        $where .= " AND CONCAT_WS(' ', student_name, course, logged_out_by_name, checked_by_name) LIKE ? ESCAPE '='";
        $types .= 's';
        $params[] = '%' . str_replace(['=', '%', '_'], ['==', '=%', '=_'], $search) . '%';
    }
    $counts = rrms_log_rows($conn, 'SELECT COUNT(*) AS total, COALESCE(SUM(returned_at IS NULL), 0) AS outstanding,
        COALESCE(SUM(returned_at IS NULL AND due_date < ?), 0) AS overdue,
        COALESCE(SUM(returned_at IS NOT NULL), 0) AS returned FROM tbl_record_logs', 's', [$today])[0];
    $total = (int) rrms_log_rows($conn, "SELECT COUNT(*) AS total FROM tbl_record_logs WHERE $where", $types, $params)[0]['total'];
    $pages = max(1, (int) ceil($total / 10));
    $page = min($page, $pages);
    $rows = rrms_log_rows($conn, "SELECT * FROM tbl_record_logs WHERE $where ORDER BY id DESC LIMIT 10 OFFSET ?", $types . 'i', array_merge($params, [($page - 1) * 10]));
    foreach ($rows as &$row) {
        $row['status'] = rrms_log_status($row, $today);
        unset($row['student_key'], $row['active_student_key']);
    }
    unset($row);
    return ['rows' => $rows, 'counts' => array_map('intval', $counts), 'page' => $page, 'pages' => $pages, 'total' => $total];
}

function rrms_log_create($conn, $requestId, $actor, DateTimeImmutable $now, $remarks)
{
    $rows = rrms_log_rows($conn, rrms_log_student_select() . ' WHERE r.req_id = ?', 'i', [$requestId]);
    if (!$rows) throw new InvalidArgumentException('Select a student with an existing request.');
    $student = rrms_log_student($rows[0]);
    // The nullable unique key also prevents duplicates from concurrent submissions.
    $stmt = rrms_prepare_execute($conn, 'INSERT INTO tbl_record_logs
        (request_id, student_key, active_student_key, student_name, course, attendance_year, program_incharge_id,
         logged_out_by_id, logged_out_by_name, logged_out_at, due_date, out_remarks)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', 'isssssiissss', [
        $requestId, $student['student_key'], $student['student_key'], $student['student_name'], $student['course'],
        $student['attendance_year'], $student['program_incharge_id'], $actor['id'], $actor['name'],
        $now->format('Y-m-d H:i:s'), rrms_log_due_date($now), $remarks,
    ]);
    if (!$stmt) throw new RuntimeException('Unable to save the record log.');
    mysqli_stmt_close($stmt);
    return (int) mysqli_insert_id($conn);
}

function rrms_log_return($conn, $id, $checkerId, $actor, DateTimeImmutable $now, $remarks)
{
    $checkers = rrms_log_rows($conn, 'SELECT inchargename FROM tblincharge WHERE inchargeid = ?', 'i', [$checkerId]);
    if (!$checkers) throw new InvalidArgumentException('Choose Checked By from the Program Incharge list.');
    $stmt = rrms_prepare_execute($conn, 'UPDATE tbl_record_logs SET returned_at = ?, returned_by_id = ?,
        returned_by_name = ?, checked_by_id = ?, checked_by_name = ?, return_remarks = ?, active_student_key = NULL
        WHERE id = ? AND returned_at IS NULL', 'sisissi', [
        $now->format('Y-m-d H:i:s'), $actor['id'], $actor['name'], $checkerId, $checkers[0]['inchargename'], $remarks, $id,
    ]);
    if (!$stmt) throw new RuntimeException('Unable to record the return.');
    $changed = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);
    if ($changed !== 1) throw new DomainException('This log has already been returned or is no longer available. Refresh the register.');
}
