<?php   
session_start();    
ob_start();
include '../db.php';

rrms_require_login();
rrms_require_permission('reports.view');
rrms_require_csrf();

if (isset($_POST['loadReports'])) {

    $docType = trim($_POST['docType'] ?? '');
    $dateFrom = trim($_POST['dateFrom'] ?? '');
    $dateTo   = trim($_POST['dateTo'] ?? '');

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

    // Query: join both tables
    $sql = "
        SELECT 
            tblrequested_by.reqbyid,
            tblrequested_by.requestorID,
            tbl_request_info.req_lastname, tbl_request_info.req_firstname, tbl_request_info.req_middlename,
            tbldoctypes.doc_desc,
            tblrequested_by.request_added
        FROM tblrequested_by
        INNER JOIN tbldoctypes ON tbldoctypes.id = tblrequested_by.doc_id
        INNER JOIN tbl_request_info on tbl_request_info.req_id=tblrequested_by.requestorID
        $where
        ORDER BY tblrequested_by.request_added DESC
    ";

    $stmt = rrms_prepare_execute($conn, $sql, $types, $params);
    $result = $stmt ? mysqli_stmt_get_result($stmt) : false;

    // Output data
    if ($result && mysqli_num_rows($result) > 0) {

        echo '
<div class="d-flex justify-content-between align-items-center mb-2">
    <h5 class="card-title m-0">
        <i class="bx bx-bar-chart"></i> Reports Summary
    </h5>
    <button class="btn btn-success btn-sm px-4" id="printReportTop">
        <i class="bx bx-printer"></i> Print Report
    </button>
</div>

        <div class="table-responsive">
            <table id="requestTable" class="table table-bordered table-hover align-middle">
                <thead class="table-light">
                    <tr class="text-center">
                        <th>#</th>
                        <th>Requestor Name</th>
                        <th>Document Type</th>
                    </tr>
                </thead>
                <tbody>
        ';

        $count = 1;
        while ($row = mysqli_fetch_assoc($result)) {        
            $fullnamed = $row['req_lastname'].', '.$row['req_firstname'].' '.$row['req_middlename'];
            echo '
                <tr>
                    <td class="text-center">'.$count.'</td>
                    <td>'.rrms_h($fullnamed).'</td>
                    <td>'.rrms_h($row['doc_desc']).'</td>
                </tr>
            ';
            $count++;
        }

        echo '
                </tbody>
            </table>
        </div>

        <!-- 🔽 Print Button (Bottom) -->
        <div class="d-flex justify-content-end mt-3">
            <button class="btn btn-success btn-sm px-4" id="printReportBottom">
                <i class="bx bx-printer"></i> Print Report
            </button>
        </div>
        ';

        // Attach JS for print
        echo '
        <script>
        $("#printReportTop, #printReportBottom").click(function() {
            var docType = $("#docType").val();
            var dateFrom = $("#dateFrom").val();
            var dateTo = $("#dateTo").val();
            window.open("printed_summary.php?docType=" + docType + "&dateFrom=" + dateFrom + "&dateTo=" + dateTo, "_blank");
        });
        </script>
        ';

    } else {
        echo "<div class='alert alert-warning text-center'>No records found for the selected filters.</div>";
    }
}
?>
