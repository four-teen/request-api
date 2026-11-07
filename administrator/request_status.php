<?php
// Pending and overdue are separate views of the same unreleased-request workflow.
function rrms_request_list_where($status, $prefix = '')
{
    $released = $prefix . 'req_datetime_released';
    $due = $prefix . 'req_due_date';
    $unreleased = "$released = '0000-00-00 00:00:00'";
    if ($status === 'overdue') {
        return "$unreleased AND $due IS NOT NULL AND $due != '0000-00-00' AND $due < ?";
    }
    return "$unreleased AND ($due IS NULL OR $due = '0000-00-00' OR $due >= ?)";
}

function rrms_request_counts($conn, $today = null)
{
    $today = $today ?? date('Y-m-d');
    $counts = ['pending_requests' => 0, 'overdue_requests' => 0, 'document_types' => 0, 'released_requests' => 0, 'document_transactions' => 0];
    $pending = rrms_request_list_where('pending');
    $overdue = rrms_request_list_where('overdue');
    $sql = "SELECT
        COALESCE(SUM(CASE WHEN $pending THEN 1 ELSE 0 END), 0) AS pending_requests,
        COALESCE(SUM(CASE WHEN $overdue THEN 1 ELSE 0 END), 0) AS overdue_requests,
        (SELECT COUNT(id) FROM tbldoctypes) AS document_types,
        COALESCE(SUM(CASE WHEN req_datetime_released != '0000-00-00 00:00:00' THEN 1 ELSE 0 END), 0) AS released_requests,
        (SELECT COUNT(reqbyid) FROM tblrequested_by) AS document_transactions
        FROM tbl_request_info";
    $stmt = rrms_prepare_execute($conn, $sql, 'ss', [$today, $today]);
    $result = $stmt ? mysqli_stmt_get_result($stmt) : false;
    $row = $result ? mysqli_fetch_assoc($result) : null;
    if ($row) {
        foreach ($counts as $key => $value) {
            $counts[$key] = (int) $row[$key];
        }
    }
    return $counts;
}

if (!function_exists('rrms_due_status_badge')) {
    function rrms_due_status_badge($dueDateValue, $todayValue = null) {
        $rawDueDate = trim((string) $dueDateValue);
        $dueTime = strtotime($rawDueDate);

        if ($rawDueDate === '' || $rawDueDate === '0000-00-00' || $rawDueDate === '0000-00-00 00:00:00' || !$dueTime) {
            return '<span class="badge bg-secondary px-3 py-2">No date set</span>';
        }

        $today = new DateTimeImmutable($todayValue ?? 'today');
        $dueDate = new DateTimeImmutable(date('Y-m-d', $dueTime));
        $daysLeft = (int) $today->diff($dueDate)->format('%r%a');

        if ($daysLeft === 0) {
            return '<span class="badge bg-warning text-dark px-3 py-2">
                        <i class="bx bx-time-five"></i> Due today
                    </span>';
        }

        if ($daysLeft === 1) {
            return '<span class="badge bg-warning text-dark px-3 py-2">
                        <i class="bx bx-time-five"></i> Due tomorrow
                    </span>';
        }

        if ($daysLeft > 1) {
            return '<span class="badge bg-info text-dark px-3 py-2">
                        <i class="bx bx-calendar"></i> '.$daysLeft.' day'.($daysLeft > 1 ? 's' : '').' remaining
                    </span>';
        }

        $overdueDays = abs($daysLeft);

        return '<span class="badge bg-danger px-3 py-2">
                    <i class="bx bx-error"></i> '.$overdueDays.' day'.($overdueDays > 1 ? 's' : '').' overdue
                </span>';
    }
}
