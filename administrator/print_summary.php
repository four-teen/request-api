<?php
session_start();
ob_start();
include '../db.php';

rrms_require_login();

// System configuration
$getsystemconfig = "SELECT * FROM `tblconfig`";
$runsystemconfig=mysqli_query($conn, $getsystemconfig);
$rowconfig=mysqli_fetch_assoc($runsystemconfig);
$_SESSION['systemname'] = 'reQuest';
$_SESSION['systemcopyright'] = $rowconfig['systemcopyright'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">
  <title>Reports | reQuest</title>

  <!-- Favicons -->
  <link href="../assets/img/request-logo.png" rel="icon">

  <!-- Bootstrap & DataTables -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">

  <!-- Icons & Theme -->
  <link href="../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
  <link href="../assets/vendor/boxicons/css/boxicons.min.css" rel="stylesheet">
  <link href="../assets/css/style.css" rel="stylesheet">
  <link href="../assets/css/admin-theme.css" rel="stylesheet">
</head>

<body onload="get_req();">
<?php include 'header.php'; include 'sidebar.php'; ?>

<main id="main" class="main">
  <div class="pagetitle">
    <h1>Report Management</h1>
    <nav>
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="index.php">Home</a></li>
        <li class="breadcrumb-item active">Reports</li>
      </ol>
    </nav>
  </div>

  <section class="section dashboard">
    <div class="row">
      <div class="col-lg-12">

        <!-- Filter Card -->
        <div class="card shadow-sm mb-4">
          <div class="card-body">
            <h5 class="fw-bold text-primary mb-3">Generate Report</h5>
            <form id="filterForm" class="row g-3 align-items-end">
              <div class="col-md-4">
                <label for="docType" class="form-label">Document Type</label>
                <select class="form-select" id="docType" name="docType">
                  <option value="">-- All Document Types --</option>
                  <?php
                    $result = mysqli_query($conn, "SELECT * FROM tbldoctypes ORDER BY doc_desc ASC");
                    while ($r = mysqli_fetch_assoc($result)) {
                      echo '<option value="'.(int) $r['id'].'">'.rrms_h($r['doc_desc']).'</option>';
                    }
                  ?>
                </select>
              </div>
              <div class="col-md-3">
                <label for="dateFrom" class="form-label">Date From</label>
                <input type="date" id="dateFrom" name="dateFrom" class="form-control">
              </div>
              <div class="col-md-3">
                <label for="dateTo" class="form-label">Date To</label>
                <input type="date" id="dateTo" name="dateTo" class="form-control">
              </div>
              <div class="col-md-2 text-end">
                <button type="button" class="btn btn-primary w-100" id="generateReport">
                  <i class="bx bx-search"></i> Generate
                </button>
              </div>
            </form>
          </div>
        </div>

        <!-- Reports Table Card -->
        <div class="card border-0 shadow-sm">
          <div class="card-body">
            <!-- <h5 class="card-title mb-3"><i class="bx bx-bar-chart"></i> Reports Summary</h5> -->
            <div id="main_data"></div>
          </div>
        </div>

      </div>
    </div>
  </section>
</main>

<!-- Footer -->
<?php include __DIR__ . '/footer.php'; ?>

<a href="#" class="back-to-top d-flex align-items-center justify-content-center"><i class="bi bi-arrow-up-short"></i></a>

<!-- JS Dependencies -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="../assets/sweetalert2.js"></script>

<script>
function get_req() {
  loadReports(); // load all on start
}

// 🎯 Load reports with progress bar
function loadReports(docType = '', dateFrom = '', dateTo = '') {
  let progress = 0;
  let interval;

  $('#main_data').html(`
    <div style="padding: 1rem;">
      <div class="progress" style="height: 6px;">
        <div id="progress-bar" class="progress-bar progress-bar-striped progress-bar-animated"
             role="progressbar" style="width: 0%; background: linear-gradient(90deg, #0d6efd, #0dcaf0);">
        </div>
      </div>
      <div id="progress-label" style="font-size: 11px; margin-top: 6px; color: #6c757d;">
        Loading report data... 0%
      </div>
    </div>
  `);

  interval = setInterval(() => {
    if (progress < 90) {
      progress++;
      $('#progress-bar').css('width', progress + '%');
      $('#progress-label').text(`Loading report data... ${progress}%`);
    }
  }, 25);

  $.ajax({
    type: "POST",
    url: "query_print_report.php",
    data: { loadReports: 1, docType, dateFrom, dateTo },
    success: function (response) {
      clearInterval(interval);
      $('#progress-bar').css('width', '100%');
      $('#progress-label').html(`<i class="bx bx-check-circle text-success"></i> Load complete!`);
      setTimeout(() => {
        $('#main_data').html(response);
        $('#requestTable').DataTable({
          paging: true,
          searching: true,
          ordering: true,
          responsive: true,
          pageLength: 10
        });
      }, 600);
    }
  });
}

// 🧭 Trigger filter
$('#generateReport').on('click', function() {
  const docType = $('#docType').val();
  const dateFrom = $('#dateFrom').val();
  const dateTo = $('#dateTo').val();
  loadReports(docType, dateFrom, dateTo);
});
</script>
</body>
</html>
