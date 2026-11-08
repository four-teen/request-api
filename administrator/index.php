<?php

session_start();
ob_start();

include '../db.php';
require_once __DIR__ . '/request_status.php';

rrms_require_login();
rrms_require_permission('dashboard.view');

$getsystemconfig = "SELECT * FROM `tblconfig`";
$runsystemconfig = mysqli_query($conn, $getsystemconfig);
$rowconfig = $runsystemconfig ? mysqli_fetch_assoc($runsystemconfig) : [];
$_SESSION['systemname'] = 'reQuest';
$_SESSION['systemcopyright'] = $rowconfig['systemcopyright'] ?? '';

if (!function_exists('rrms_admin_count')) {
    function rrms_admin_count($conn, $sql)
    {
        $result = mysqli_query($conn, $sql);
        if (!$result) {
            return 0;
        }

        $row = mysqli_fetch_assoc($result);
        return (int) ($row['total'] ?? 0);
    }
}

if (!function_exists('rrms_admin_count_between')) {
    function rrms_admin_count_between($conn, $table, $field, $start, $end, $extraSql = '')
    {
        $sql = "SELECT COUNT(*) AS total FROM `$table` WHERE `$field` BETWEEN ? AND ? $extraSql";
        $stmt = rrms_prepare_execute($conn, $sql, 'ss', [$start, $end]);
        $result = $stmt ? mysqli_stmt_get_result($stmt) : false;
        $row = $result ? mysqli_fetch_assoc($result) : [];

        return (int) ($row['total'] ?? 0);
    }
}

$monthStart = (new DateTimeImmutable('now'))->modify('first day of this month')->setTime(0, 0, 0);
$monthEnd = (new DateTimeImmutable('now'))->modify('last day of this month')->setTime(23, 59, 59);
$monthStartSql = $monthStart->format('Y-m-d H:i:s');
$monthEndSql = $monthEnd->format('Y-m-d H:i:s');
$monthLabel = $monthStart->format('F Y');
$chartStart = $monthStart->modify('first day of previous month')->setTime(0, 0, 0);
$chartEnd = $monthEnd;
$chartLabel = $chartStart->format('M Y') . ' - ' . $chartEnd->format('M Y');

$totalRequests = rrms_admin_count($conn, "SELECT COUNT(req_id) AS total FROM `tbl_request_info`");
$requestCounts = rrms_request_counts($conn);
$pendingRequests = $requestCounts['pending_requests'];
$releasedRequests = rrms_admin_count($conn, "SELECT COUNT(req_id) AS total FROM `tbl_request_info` WHERE req_datetime_released != '0000-00-00 00:00:00'");
$documentTransactions = rrms_admin_count($conn, "SELECT COUNT(reqbyid) AS total FROM `tblrequested_by`");
$overdueRequests = $requestCounts['overdue_requests'];
$documentTypes = rrms_admin_count($conn, "SELECT COUNT(id) AS total FROM `tbldoctypes`");
$programs = rrms_admin_count($conn, "SELECT COUNT(courseid) AS total FROM `tblcourse`");
$completionRate = $totalRequests > 0 ? round(($releasedRequests / $totalRequests) * 100) : 0;

$monthlyRequests = rrms_admin_count_between($conn, 'tbl_request_info', 'req_datetime_request', $monthStartSql, $monthEndSql);
$monthlyProcessed = rrms_admin_count_between($conn, 'tbl_request_info', 'date_processed', $monthStartSql, $monthEndSql, "AND date_processed != '0000-00-00 00:00:00'");
$monthlyReleased = rrms_admin_count_between($conn, 'tbl_request_info', 'req_datetime_released', $monthStartSql, $monthEndSql, "AND req_datetime_released != '0000-00-00 00:00:00'");
$monthlyDocuments = rrms_admin_count_between($conn, 'tblrequested_by', 'request_added', $monthStartSql, $monthEndSql);

$weekLabels = [];
$weeklyReceived = [];
$weeklyProcessed = [];
$weeklyReleased = [];
$weeklyDocuments = [];
$cursor = $chartStart;

while ($cursor <= $chartEnd) {
    $weekStart = $cursor;
    $weekEnd = $cursor->modify('+6 days')->setTime(23, 59, 59);
    if ($weekEnd > $chartEnd) {
        $weekEnd = $chartEnd;
    }

    $startLabel = $weekStart->format('M j');
    $endLabel = $weekStart->format('M') === $weekEnd->format('M') ? $weekEnd->format('j') : $weekEnd->format('M j');
    $weekLabels[] = $startLabel . '-' . $endLabel;

    $startSql = $weekStart->format('Y-m-d H:i:s');
    $endSql = $weekEnd->format('Y-m-d H:i:s');

    $weeklyReceived[] = rrms_admin_count_between($conn, 'tbl_request_info', 'req_datetime_request', $startSql, $endSql);
    $weeklyProcessed[] = rrms_admin_count_between($conn, 'tbl_request_info', 'date_processed', $startSql, $endSql, "AND date_processed != '0000-00-00 00:00:00'");
    $weeklyReleased[] = rrms_admin_count_between($conn, 'tbl_request_info', 'req_datetime_released', $startSql, $endSql, "AND req_datetime_released != '0000-00-00 00:00:00'");
    $weeklyDocuments[] = rrms_admin_count_between($conn, 'tblrequested_by', 'request_added', $startSql, $endSql);

    $cursor = $weekEnd->modify('+1 second');
}

$chartSeries = [
    ['name' => 'Requests Received', 'data' => $weeklyReceived],
    ['name' => 'Processed', 'data' => $weeklyProcessed],
    ['name' => 'Released', 'data' => $weeklyReleased],
    ['name' => 'Documents Requested', 'data' => $weeklyDocuments],
];

$topDocuments = [];
$topDocsStmt = rrms_prepare_execute(
    $conn,
    "SELECT d.doc_desc, COUNT(rb.reqbyid) AS total
     FROM tblrequested_by rb
     INNER JOIN tbldoctypes d ON d.id = rb.doc_id
     WHERE rb.request_added BETWEEN ? AND ?
     GROUP BY d.id, d.doc_desc
     ORDER BY total DESC
     LIMIT 5",
    'ss',
    [$monthStartSql, $monthEndSql]
);
$topDocsResult = $topDocsStmt ? mysqli_stmt_get_result($topDocsStmt) : false;
while ($topDocsResult && ($row = mysqli_fetch_assoc($topDocsResult))) {
    $topDocuments[] = $row;
}

$recentRequests = [];
$recentResult = mysqli_query($conn, "
    SELECT r.*,
           COALESCE(doc_counts.file_count, 0) AS file_count
    FROM tbl_request_info r
    LEFT JOIN (
        SELECT requestorID, COUNT(reqbyid) AS file_count
        FROM tblrequested_by
        GROUP BY requestorID
    ) doc_counts ON doc_counts.requestorID = r.req_id
    ORDER BY r.req_id DESC
    LIMIT 6
");
while ($recentResult && ($row = mysqli_fetch_assoc($recentResult))) {
    $recentRequests[] = $row;
}

$programWorkload = [];
$workloadResult = mysqli_query($conn, "
    SELECT COALESCE(NULLIF(i.inchargename, ''), 'Unassigned') AS incharge_name,
           COUNT(r.req_id) AS pending_count
    FROM tbl_request_info r
    LEFT JOIN tblincharge i ON i.inchargeid = r.prog_assigned
    WHERE r.req_datetime_released = '0000-00-00 00:00:00'
    GROUP BY incharge_name
    ORDER BY pending_count DESC
    LIMIT 5
");
while ($workloadResult && ($row = mysqli_fetch_assoc($workloadResult))) {
    $programWorkload[] = $row;
}

$tomorrow = (new DateTimeImmutable('tomorrow'))->format('Y-m-d');
$dueTomorrowTotal = rrms_admin_count(
    $conn,
    "SELECT COUNT(req_id) AS total
     FROM `tbl_request_info`
     WHERE req_due_date = '$tomorrow'
       AND req_datetime_released = '0000-00-00 00:00:00'"
);
$dueTomorrowRequests = [];
$dueTomorrowStmt = rrms_prepare_execute(
    $conn,
    "SELECT r.req_id,
            r.req_lastname,
            r.req_firstname,
            r.req_middlename,
            r.req_contact_number,
            r.req_due_date,
            COALESCE(doc_counts.file_count, 0) AS file_count
     FROM tbl_request_info r
     LEFT JOIN (
        SELECT requestorID, COUNT(reqbyid) AS file_count
        FROM tblrequested_by
        GROUP BY requestorID
     ) doc_counts ON doc_counts.requestorID = r.req_id
     WHERE r.req_due_date = ?
       AND r.req_datetime_released = '0000-00-00 00:00:00'
     ORDER BY r.req_id DESC",
    's',
    [$tomorrow]
);
$dueTomorrowResult = $dueTomorrowStmt ? mysqli_stmt_get_result($dueTomorrowStmt) : false;
while ($dueTomorrowResult && ($row = mysqli_fetch_assoc($dueTomorrowResult))) {
    $dueTomorrowRequests[] = $row;
}
$dueTomorrowPreview = array_slice($dueTomorrowRequests, 0, 6);

?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">

  <title>Dashboard | reQuest</title>
  <meta content="" name="description">
  <meta content="" name="keywords">

  <link href="../assets/img/request-logo.png" rel="icon">
  <link href="../assets/img/request-logo.png" rel="apple-touch-icon">
  <link href="https://fonts.gstatic.com" rel="preconnect">
  <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,300i,400,400i,600,600i,700,700i|Nunito:300,300i,400,400i,600,600i,700,700i|Poppins:300,300i,400,400i,500,500i,600,600i,700,700i" rel="stylesheet">

  <link href="../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
  <link href="../assets/vendor/boxicons/css/boxicons.min.css" rel="stylesheet">
  <link href="../assets/vendor/quill/quill.snow.css" rel="stylesheet">
  <link href="../assets/vendor/quill/quill.bubble.css" rel="stylesheet">
  <link href="../assets/vendor/remixicon/remixicon.css" rel="stylesheet">
  <link href="../assets/vendor/simple-datatables/style.css" rel="stylesheet">
  <link href="../assets/vendor/apexcharts/apexcharts.css" rel="stylesheet">
  <link href="../assets/css/style.css" rel="stylesheet">
  <style>
    .dashboard-card {
      border: 0;
      border-radius: 8px;
      box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
    }

    .metric-icon {
      width: 46px;
      height: 46px;
      border-radius: 50%;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      color: #fff;
      font-size: 22px;
      flex: 0 0 auto;
    }

    .metric-card {
      min-height: 142px;
    }

    .metric-card h3 {
      font-size: 28px;
      line-height: 1.1;
      margin: 0;
      color: #012970;
      font-weight: 700;
    }

    .dashboard-label {
      color: #6c757d;
      font-size: 13px;
      font-weight: 600;
      text-transform: uppercase;
    }

    .dashboard-chart {
      min-height: 460px;
    }

    .quick-link {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      padding: 12px 0;
      border-bottom: 1px solid #edf1f7;
      color: #344767;
    }

    .quick-link:last-child {
      border-bottom: 0;
    }

    .quick-link:hover {
      color: #0d6efd;
    }

    .mini-stat {
      border: 1px solid #edf1f7;
      border-radius: 8px;
      padding: 14px;
      height: 100%;
      background: #fff;
    }

    .mini-stat strong {
      display: block;
      color: #012970;
      font-size: 22px;
      line-height: 1.1;
    }

    .activity-row {
      display: grid;
      grid-template-columns: minmax(0, 1fr) auto;
      gap: 12px;
      align-items: center;
      padding: 12px 0;
      border-bottom: 1px solid #edf1f7;
    }

    .activity-row:last-child {
      border-bottom: 0;
    }

    .activity-name {
      color: #012970;
      font-weight: 700;
      margin-bottom: 2px;
    }

    .dashboard-status-badge {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-width: 96px;
      min-height: 28px;
      border-radius: 6px;
      font-weight: 700;
      line-height: 1;
      white-space: nowrap;
    }

    .due-tomorrow-card {
      height: 100%;
    }

    .due-tomorrow-count {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-width: 54px;
      min-height: 44px;
      padding: 0.45rem 0.95rem;
      border-radius: 999px;
      background: #fff3cd;
      color: #8a5a00;
      font-size: 18px;
      font-weight: 800;
      line-height: 1;
    }

    .due-tomorrow-list {
      max-height: 390px;
      overflow-y: auto;
      padding-right: 4px;
    }

    .due-tomorrow-item {
      display: grid;
      grid-template-columns: minmax(0, 1fr) auto;
      gap: 12px;
      align-items: center;
      padding: 13px 0;
      border-bottom: 1px solid #edf1f7;
    }

    .due-tomorrow-item:last-child {
      border-bottom: 0;
    }

    .due-tomorrow-name {
      color: #012970;
      font-weight: 700;
      line-height: 1.25;
    }

    .due-tomorrow-meta {
      color: #6c757d;
      font-size: 12px;
      margin-top: 3px;
    }

    .due-tomorrow-actions {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      margin-top: 14px;
      padding-top: 12px;
      border-top: 1px solid #edf1f7;
    }

    .due-tomorrow-modal-list {
      max-height: 65vh;
      overflow-y: auto;
      padding-right: 6px;
    }

    @media (max-width: 575.98px) {
      .dashboard-chart {
        min-height: 380px;
      }

      .activity-row {
        grid-template-columns: 1fr;
      }

      .dashboard-status-badge {
        min-width: 88px;
      }
    }
  </style>
  <link href="../assets/css/admin-theme.css" rel="stylesheet">
</head>

<body>

<?php
include 'header.php';
include 'sidebar.php';
?>

  <main id="main" class="main">

    <div class="pagetitle">
      <h1>Dashboard</h1>
      <nav>
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="index.php">Home</a></li>
          <li class="breadcrumb-item active">Dashboard</li>
        </ol>
      </nav>
    </div>

    <section class="section dashboard">
      <div class="row g-3">
        <div class="col-12 col-sm-6 col-xl-3">
          <a href="manage_request.php" class="text-decoration-none">
            <div class="card dashboard-card metric-card">
              <div class="card-body">
                <h5 class="card-title">Pending <span>| Requests</span></h5>
                <div class="d-flex align-items-center gap-3">
                  <div class="metric-icon bg-primary"><i class="bi bi-inboxes"></i></div>
                  <div>
                    <h3><?php echo number_format($pendingRequests) ?></h3>
                    <div class="dashboard-label">Awaiting release</div>
                  </div>
                </div>
              </div>
            </div>
          </a>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
          <a href="released_summary.php" class="text-decoration-none">
            <div class="card dashboard-card metric-card">
              <div class="card-body">
                <h5 class="card-title">Released <span>| Completed</span></h5>
                <div class="d-flex align-items-center gap-3">
                  <div class="metric-icon bg-success"><i class="bi bi-check2-circle"></i></div>
                  <div>
                    <h3><?php echo number_format($releasedRequests) ?></h3>
                    <div class="dashboard-label"><?php echo $completionRate ?>% completion</div>
                  </div>
                </div>
              </div>
            </div>
          </a>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
          <a href="statistics_summary.php" class="text-decoration-none">
            <div class="card dashboard-card metric-card">
              <div class="card-body">
                <h5 class="card-title">Documents <span>| Requested</span></h5>
                <div class="d-flex align-items-center gap-3">
                  <div class="metric-icon bg-warning"><i class="bi bi-files"></i></div>
                  <div>
                    <h3><?php echo number_format($documentTransactions) ?></h3>
                    <div class="dashboard-label"><?php echo number_format($documentTypes) ?> active types</div>
                  </div>
                </div>
              </div>
            </div>
          </a>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
          <a href="overdue_requests.php" class="text-decoration-none">
            <div class="card dashboard-card metric-card">
              <div class="card-body">
                <h5 class="card-title">Overdue <span>| Attention</span></h5>
                <div class="d-flex align-items-center gap-3">
                  <div class="metric-icon bg-danger"><i class="bi bi-exclamation-triangle"></i></div>
                  <div>
                    <h3><?php echo number_format($overdueRequests) ?></h3>
                    <div class="dashboard-label">Past due date</div>
                  </div>
                </div>
              </div>
            </div>
          </a>
        </div>

        <div class="col-12 col-xl-8">
          <div class="card dashboard-card h-100">
            <div class="card-body">
              <div class="d-flex flex-column flex-lg-row justify-content-between gap-2 mb-3">
                <div>
                  <h5 class="card-title mb-0">Two-Month Document Flow <span>| <?php echo rrms_h($chartLabel) ?></span></h5>
                  <div class="text-muted small">Weekly movement across previous and current month requests, processing, release, and document transactions</div>
                </div>
                <div class="d-flex align-items-center gap-2 text-muted small">
                  <i class="bi bi-calendar3"></i>
                  <span><?php echo rrms_h($chartStart->format('M j')) ?> to <?php echo rrms_h($chartEnd->format('M j, Y')) ?></span>
                </div>
              </div>
              <div id="monthlyFlowChart" class="dashboard-chart"></div>
            </div>
          </div>
        </div>

        <div class="col-12 col-xl-4">
          <div class="card dashboard-card due-tomorrow-card">
            <div class="card-body">
              <div class="d-flex align-items-start justify-content-between gap-3 mb-2">
                <div>
                  <h5 class="card-title mb-0">Due Tomorrow <span>| Requests</span></h5>
                  <div class="text-muted small"><?php echo rrms_h((new DateTimeImmutable('tomorrow'))->format('M d, Y')) ?></div>
                </div>
                <div class="due-tomorrow-count"><?php echo number_format($dueTomorrowTotal) ?></div>
              </div>

              <?php if (count($dueTomorrowRequests) === 0) { ?>
                <div class="text-muted py-4 text-center">
                  <i class="bi bi-check2-circle d-block mb-2 fs-3 text-success"></i>
                  No pending requests due tomorrow.
                </div>
              <?php } else { ?>
                <div class="due-tomorrow-list">
                  <?php foreach ($dueTomorrowPreview as $request) {
                      $fullname = strtoupper($request['req_lastname'] . ', ' . $request['req_firstname'] . ' ' . $request['req_middlename']);
                  ?>
                    <div class="due-tomorrow-item">
                      <div>
                        <div class="due-tomorrow-name"><?php echo rrms_h($fullname) ?></div>
                        <div class="due-tomorrow-meta">
                          <?php echo rrms_h($request['req_contact_number']) ?> &middot;
                          <?php echo number_format((int) $request['file_count']) ?> document(s)
                        </div>
                      </div>
                      <span class="badge bg-warning text-dark dashboard-status-badge">Due tomorrow</span>
                    </div>
                  <?php } ?>
                </div>

                <div class="due-tomorrow-actions">
                  <div class="text-muted small">
                    Showing <?php echo number_format(count($dueTomorrowPreview)) ?> of <?php echo number_format($dueTomorrowTotal) ?> due tomorrow.
                  </div>
                  <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#dueTomorrowModal">
                    Show all
                  </button>
                </div>
              <?php } ?>
            </div>
          </div>
        </div>

        <div class="col-12 col-xl-8">
          <div class="card dashboard-card h-100">
            <div class="card-body">
              <h5 class="card-title">This Month <span>| Snapshot</span></h5>
              <div class="row g-3">
                <div class="col-6 col-lg-3">
                  <div class="mini-stat">
                    <span class="dashboard-label">Requests</span>
                    <strong><?php echo number_format($monthlyRequests) ?></strong>
                  </div>
                </div>
                <div class="col-6 col-lg-3">
                  <div class="mini-stat">
                    <span class="dashboard-label">Processed</span>
                    <strong><?php echo number_format($monthlyProcessed) ?></strong>
                  </div>
                </div>
                <div class="col-6 col-lg-3">
                  <div class="mini-stat">
                    <span class="dashboard-label">Released</span>
                    <strong><?php echo number_format($monthlyReleased) ?></strong>
                  </div>
                </div>
                <div class="col-6 col-lg-3">
                  <div class="mini-stat">
                    <span class="dashboard-label">Documents</span>
                    <strong><?php echo number_format($monthlyDocuments) ?></strong>
                  </div>
                </div>
              </div>

              <div class="row g-3 mt-1">
                <div class="col-12 col-lg-6">
                  <h6 class="fw-bold mt-3 mb-2">Top Documents</h6>
                  <?php if (count($topDocuments) === 0) { ?>
                    <div class="text-muted py-3">No document requests recorded this month.</div>
                  <?php } else { ?>
                    <?php foreach ($topDocuments as $doc) { ?>
                      <div class="quick-link">
                        <span><?php echo rrms_h($doc['doc_desc']) ?></span>
                        <span class="badge bg-primary rounded-pill"><?php echo number_format((int) $doc['total']) ?></span>
                      </div>
                    <?php } ?>
                  <?php } ?>
                </div>

                <div class="col-12 col-lg-6">
                  <h6 class="fw-bold mt-3 mb-2">Program Workload</h6>
                  <?php if (count($programWorkload) === 0) { ?>
                    <div class="text-muted py-3">No pending program workload.</div>
                  <?php } else { ?>
                    <?php foreach ($programWorkload as $item) { ?>
                      <div class="quick-link">
                        <span><?php echo rrms_h($item['incharge_name']) ?></span>
                        <span class="badge bg-warning text-dark rounded-pill"><?php echo number_format((int) $item['pending_count']) ?></span>
                      </div>
                    <?php } ?>
                  <?php } ?>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="col-12 col-xl-4">
          <div class="card dashboard-card h-100">
            <div class="card-body">
              <h5 class="card-title">Recent Requests <span>| Latest</span></h5>
              <?php if (count($recentRequests) === 0) { ?>
                <div class="text-muted py-3">No requests recorded yet.</div>
              <?php } else { ?>
                <?php foreach ($recentRequests as $request) {
                    $fullname = strtoupper($request['req_lastname'] . ', ' . $request['req_firstname'] . ' ' . $request['req_middlename']);
                    $isReleased = $request['req_datetime_released'] !== '0000-00-00 00:00:00' && !empty($request['req_datetime_released']);
                    $dueTime = strtotime($request['req_due_date']);
                    $isOverdue = !$isReleased && $dueTime && $dueTime < strtotime(date('Y-m-d'));
                    $badgeClass = $isReleased ? 'bg-success' : ($isOverdue ? 'bg-danger' : 'bg-info text-dark');
                    $badgeText = $isReleased ? 'Released' : ($isOverdue ? 'Overdue' : 'Processing');
                ?>
                  <div class="activity-row">
                    <div>
                      <div class="activity-name"><?php echo rrms_h($fullname) ?></div>
                      <div class="text-muted small">
                        <?php echo number_format((int) $request['file_count']) ?> document(s) &middot;
                        <?php echo rrms_h(date('M d, Y', strtotime($request['req_datetime_request']))) ?>
                      </div>
                    </div>
                    <span class="badge dashboard-status-badge <?php echo rrms_h($badgeClass) ?>"><?php echo rrms_h($badgeText) ?></span>
                  </div>
                <?php } ?>
                <a href="manage_request.php" class="btn btn-outline-primary btn-sm mt-3 w-100">Open Manage Requests</a>
              <?php } ?>
            </div>
          </div>
        </div>

        <div class="col-12">
          <div class="card dashboard-card">
            <div class="card-body">
              <h5 class="card-title">System Coverage <span>| Master Data</span></h5>
              <div class="row g-3">
                <div class="col-6 col-md-3">
                  <div class="mini-stat">
                    <span class="dashboard-label">All Requests</span>
                    <strong><?php echo number_format($totalRequests) ?></strong>
                  </div>
                </div>
                <div class="col-6 col-md-3">
                  <div class="mini-stat">
                    <span class="dashboard-label">Programs</span>
                    <strong><?php echo number_format($programs) ?></strong>
                  </div>
                </div>
                <div class="col-6 col-md-3">
                  <div class="mini-stat">
                    <span class="dashboard-label">Doc Types</span>
                    <strong><?php echo number_format($documentTypes) ?></strong>
                  </div>
                </div>
                <div class="col-6 col-md-3">
                  <div class="mini-stat">
                    <span class="dashboard-label">Pending Share</span>
                    <strong><?php echo $totalRequests > 0 ? number_format(($pendingRequests / $totalRequests) * 100, 1) : '0.0' ?>%</strong>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

  </main>

  <div class="modal fade" id="dueTomorrowModal" tabindex="-1" aria-labelledby="dueTomorrowModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <div>
            <h5 class="modal-title" id="dueTomorrowModalLabel">Due Tomorrow</h5>
            <div class="text-muted small">
              <?php echo rrms_h((new DateTimeImmutable('tomorrow'))->format('M d, Y')) ?> &middot;
              <?php echo number_format($dueTomorrowTotal) ?> request(s)
            </div>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <?php if (count($dueTomorrowRequests) === 0) { ?>
            <div class="text-muted text-center py-4">No pending requests due tomorrow.</div>
          <?php } else { ?>
            <div class="due-tomorrow-modal-list">
              <?php foreach ($dueTomorrowRequests as $request) {
                  $fullname = strtoupper($request['req_lastname'] . ', ' . $request['req_firstname'] . ' ' . $request['req_middlename']);
              ?>
                <div class="due-tomorrow-item">
                  <div>
                    <div class="due-tomorrow-name"><?php echo rrms_h($fullname) ?></div>
                    <div class="due-tomorrow-meta">
                      <?php echo rrms_h($request['req_contact_number']) ?> &middot;
                      <?php echo number_format((int) $request['file_count']) ?> document(s)
                    </div>
                  </div>
                  <span class="badge bg-warning text-dark dashboard-status-badge">Due tomorrow</span>
                </div>
              <?php } ?>
            </div>
          <?php } ?>
        </div>
        <div class="modal-footer">
          <a href="manage_request.php" class="btn btn-primary btn-sm">Open Manage Requests</a>
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

  <?php include __DIR__ . '/footer.php'; ?>

  <a href="#" class="back-to-top d-flex align-items-center justify-content-center"><i class="bi bi-arrow-up-short"></i></a>

  <script src="../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
  <script src="../assets/vendor/apexcharts/apexcharts.min.js"></script>
  <script src="../assets/js/main.js"></script>
  <script>
    const chartOptions = {
      series: <?php echo json_encode($chartSeries, JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES) ?>,
      chart: {
        type: 'line',
        height: 440,
        parentHeightOffset: 0,
        toolbar: { show: true },
        zoom: { enabled: false },
        animations: {
          enabled: true,
          easing: 'easeinout',
          speed: 700,
          animateGradually: { enabled: true, delay: 120 },
          dynamicAnimation: { enabled: true, speed: 450 }
        }
      },
      colors: ['#2563eb', '#16a34a', '#f59e0b', '#dc2626'],
      stroke: {
        curve: 'smooth',
        width: [4, 4, 4, 3],
        lineCap: 'round'
      },
      markers: {
        size: 4,
        strokeWidth: 2,
        hover: { size: 7 }
      },
      dataLabels: { enabled: false },
      grid: {
        borderColor: '#e9eef5',
        strokeDashArray: 4,
        padding: { left: 8, right: 16 }
      },
      xaxis: {
        categories: <?php echo json_encode($weekLabels, JSON_UNESCAPED_SLASHES) ?>,
        labels: {
          style: { colors: '#6c757d', fontSize: '12px' }
        }
      },
      yaxis: {
        min: 0,
        forceNiceScale: true,
        labels: { style: { colors: '#6c757d' } }
      },
      legend: {
        position: 'top',
        horizontalAlign: 'left',
        fontSize: '13px',
        markers: { radius: 4 }
      },
      tooltip: {
        shared: true,
        intersect: false,
        y: {
          formatter: function (value) {
            return Number(value).toLocaleString() + ' transaction(s)';
          }
        }
      },
      responsive: [{
        breakpoint: 768,
        options: {
          chart: { height: 360 },
          legend: { position: 'bottom' },
          stroke: { width: 3 }
        }
      }]
    };

    const monthlyFlowChart = new ApexCharts(document.querySelector('#monthlyFlowChart'), chartOptions);
    monthlyFlowChart.render();
  </script>

</body>

</html>
