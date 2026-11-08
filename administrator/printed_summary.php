<?php
session_start();
ob_start();
include '../db.php';

rrms_require_login();
rrms_require_permission('reports.view');

// Filters from URL
$docType = trim($_GET['docType'] ?? '');
$dateFrom = trim($_GET['dateFrom'] ?? '');
$dateTo   = trim($_GET['dateTo'] ?? '');

// Get system config
$getsystemconfig = "SELECT * FROM `tblconfig`";
$runsystemconfig=mysqli_query($conn, $getsystemconfig);
$rowconfig=mysqli_fetch_assoc($runsystemconfig);
$systemname = 'reQuest';
$systemcopyright = $rowconfig['systemcopyright'] ?? '©';

// Resolve document type label
$docLabel = 'All Document Types';
if (!empty($docType)) {
  $docStmt = rrms_prepare_execute($conn, "SELECT doc_desc FROM tbldoctypes WHERE id = ? LIMIT 1", 'i', [(int) $docType]);
  $docQuery = $docStmt ? mysqli_stmt_get_result($docStmt) : false;
  if ($docQuery && mysqli_num_rows($docQuery) > 0) {
    $docLabel = mysqli_fetch_assoc($docQuery)['doc_desc'];
  }
}

// Build WHERE clause
$where = "WHERE 1";
$types = '';
$params = [];
if (!empty($docType)) {
  $where .= " AND tbldoctypes.id = ?";
  $types .= 'i';
  $params[] = (int) $docType;
}
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
  $dateToExclusive = date('Y-m-d', strtotime($dateTo . ' +1 day'));
  $where .= " AND tblrequested_by.request_added >= ? AND tblrequested_by.request_added < ?";
  $types .= 'ss';
  $params[] = $dateFrom . ' 00:00:00';
  $params[] = $dateToExclusive . ' 00:00:00';
}

// Main query
$sql = "
  SELECT 
    tbl_request_info.req_lastname,
    tbl_request_info.req_firstname,
    tbl_request_info.req_middlename,
    tbldoctypes.doc_desc,
    tblrequested_by.request_added,
    tbl_request_info.req_datetime_released
  FROM tblrequested_by
  INNER JOIN tbldoctypes ON tbldoctypes.id = tblrequested_by.doc_id
  INNER JOIN tbl_request_info ON tbl_request_info.req_id = tblrequested_by.requestorID
  $where
  ORDER BY tblrequested_by.request_added DESC
";
$stmt = rrms_prepare_execute($conn, $sql, $types, $params);
$result = $stmt ? mysqli_stmt_get_result($stmt) : false;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <link href="../assets/img/request-logo.png" rel="icon" type="image/png">
  <meta charset="utf-8">
  <title>Print Report | <?php echo rrms_h($systemname); ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { font-family: 'Segoe UI', sans-serif; font-size: 14px; margin: 20px; }
    .header { text-align: center; margin-bottom: 20px; }
    .header img { width: 70px; margin-bottom: 10px; }
    .header h4 { margin: 0; font-weight: 700; }
    .header h6 { margin: 0; color: #555; }
    table { width: 100%; border-collapse: collapse; margin-top: 15px; }
    th, td { border: 1px solid #dee2e6; padding: 6px 10px; }
    th { background-color: #f8f9fa; text-align: center; }
    td { vertical-align: middle; }
    .footer { margin-top: 30px; text-align: right; font-size: 12px; color: #6c757d; }
    @media print {
      .no-print { display: none; }
      body { margin: 10px; }
    }
  </style>
</head>
<body>
<div class="mt-4 no-print">
  <button onclick="window.print()" class="btn btn-primary"><i class="bi bi-printer"></i> Print</button>
  <a href="print_summary.php" class="btn btn-secondary">Close</a>
</div>

<div class="header">
  <img src="../assets/img/request-logo.png" alt="Logo">
  <h4>SULTAN KUDARAT STATE UNIVERSITY</h4>
  <span>EJC Montilla, Tacurong City</span>
  <hr>
  <h6>Registrar Report Summary</h6>
</div>

<div class="mb-2">
  <strong>Document Type:</strong> <?php echo rrms_h($docLabel); ?><br>
  <strong>Date Range:</strong> 
  <?php 
    if (!empty($dateFrom) && !empty($dateTo)) 
      echo date('F d, Y', strtotime($dateFrom)) . " - " . date('F d, Y', strtotime($dateTo));
    else 
      echo "All Dates";
  ?>
</div>

<table class="table table-bordered">
  <thead>
    <tr>
      <th>#</th>
      <th>Requestor Name</th>
      <th>Document Requested</th>
      <th>Date Requested</th>
      <th>Date Released</th>
    </tr>
  </thead>
  <tbody>
    <?php
    $count = 1;
            $thedate_release = '';
    if ($result && mysqli_num_rows($result) > 0) {
      while ($row = mysqli_fetch_assoc($result)) {
        $fullname = strtoupper($row['req_lastname']).', '.ucwords(strtolower($row['req_firstname'])).' '.ucfirst(strtolower($row['req_middlename']));

        if ($row['req_datetime_released'] == '0000-00-00 00:00:00' || empty($row['req_datetime_released'])) {
           $thedate_release = '';;
        }else{
            $thedate_release = ''.date('M d, Y h:i A', strtotime($row['req_datetime_released'])).'';
        }


        echo "
          <tr>
            <td class='text-center'>{$count}</td>
            <td>".rrms_h($fullname)."</td>
            <td>".rrms_h($row['doc_desc'])."</td>
            <td class='text-center'>".date('M d, Y h:i A', strtotime($row['request_added']))."</td>
            <td class='text-center'>".$thedate_release."</td>
          </tr>
        ";
        $count++;
      }
    } else {
      echo "<tr><td colspan='5' class='text-center text-muted'>No records found</td></tr>";
    }
    ?>
  </tbody>
</table>

<div class="footer">
  <span>Printed on: <?php echo date('F d, Y h:i A'); ?></span>
</div>

<div class="text-center mt-4 no-print">
  <button onclick="window.print()" class="btn btn-primary"><i class="bi bi-printer"></i> Print</button>
  <a href="print_summary.php" class="btn btn-secondary">Close</a>
</div>

</body>
</html>
