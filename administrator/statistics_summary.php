<?php

    session_start();    
    ob_start();


    include '../db.php';

    rrms_require_login();
    rrms_require_permission('statistics.view');

    $mnt = '0';
    $yr = '0';  

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

  <title>Statistics | reQuest</title>
  <meta content="" name="description">
  <meta content="" name="keywords">

  <!-- Favicons -->
  <link href="../assets/img/request-logo.png" rel="icon">
  <link href="../assets/img/request-logo.png" rel="apple-touch-icon">

  <!-- Google Fonts -->
  <link href="https://fonts.gstatic.com" rel="preconnect">
  <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,300i,400,400i,600,600i,700,700i|Nunito:300,300i,400,400i,600,600i,700,700i|Poppins:300,300i,400,400i,500,500i,600,600i,700,700i" rel="stylesheet">

  <!-- Vendor CSS Files -->
  <link href="../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
  <link href="../assets/vendor/boxicons/css/boxicons.min.css" rel="stylesheet">
  <link href="../assets/vendor/quill/quill.snow.css" rel="stylesheet">
  <link href="../assets/vendor/quill/quill.bubble.css" rel="stylesheet">
  <link href="../assets/vendor/remixicon/remixicon.css" rel="stylesheet">
  <link href="../assets/vendor/simple-datatables/style.css" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- DataTables CSS (Bootstrap 5 Integration) -->
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">


  <!-- Template Main CSS File -->
  <link href="../assets/css/style.css" rel="stylesheet">

  <link href="../assets/css/admin-theme.css" rel="stylesheet">
</head>

<body onload="get_req();">



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
          <li class="breadcrumb-item active">Summary</li>
        </ol>
      </nav>
    </div><!-- End Page Title -->

    <section class="section dashboard">

      <!-- <div id="test">test</div> -->
      <div class="row">

        <!-- Left side columns -->
        <div class="col-lg-12">
          <div class="row">
            <!-- Summary Card -->
            <div class="col-lg-3">
              <div class="card info-card bg-light border-0 shadow-sm">
                <div class="card-body">
                  <h5 class="card-title">Summary <span class="text-muted">| Released</span></h5>
                  <div class="d-flex align-items-center">
                    <div class="card-icon rounded-circle d-flex align-items-center justify-content-center bg-warning text-white me-3" style="width:48px;height:48px;">
                      <i class='bx bxs-objects-vertical-top'></i>
                    </div>
                    <div>
                      <h3 id="load_summary" class="mb-0">
                      <?php 
                          $get_req = "SELECT count(req_id) as req_count FROM `tbl_request_info` WHERE req_datetime_released != '0000-00-00 00:00:00'";
                          $runget_req = mysqli_query($conn, $get_req);
                          if($runget_req){
                            $r_req = mysqli_fetch_assoc($runget_req);
                            echo $r_req['req_count'];
                          }
                    
                      ?>
                      </h3>
                      <small class="text-muted">transactions this period</small>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Summary Card -->
            <div class="col-lg-3">
              <div class="card info-card bg-light border-0 shadow-sm">
                <div class="card-body">
                  <h5 class="card-title">Statistics <span class="text-muted">| Documents</span></h5>
                  <div class="d-flex align-items-center">
                    <div class="card-icon rounded-circle d-flex align-items-center justify-content-center bg-danger text-white me-3" style="width:48px;height:48px;">
                      <i class='bx bx-bar-chart-alt-2'></i>
                    </div>
                    <div>
                      <h3 id="load_summary" class="mb-0">
                      <?php 
                          $get_req_stat = "SELECT count(reqbyid) as req_stat_count FROM `tblrequested_by`";
                          $runget_req_stat = mysqli_query($conn, $get_req_stat);
                          if($runget_req_stat){
                            $r_req_stat = mysqli_fetch_assoc($runget_req_stat);
                            echo $r_req_stat['req_stat_count'];
                          }
                    
                      ?>
                      </h3>
                      <small class="text-muted">total request</small>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Reports -->
            <div class="col-12">
              <div class="card">

                <div class="filter">
                  <a class="icon" href="#" data-bs-toggle="dropdown"><i class="bi bi-three-dots"></i></a>
                  <ul class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">
                    <li class="dropdown-header text-start">
                      <h6>Filter</h6>
                    </li>

                    <li><a class="dropdown-item" href="#">Today</a></li>
                    <li><a class="dropdown-item" href="#">This Month</a></li>
                    <li><a class="dropdown-item" href="#">This Year</a></li>
                  </ul>
                </div>

                <div class="card-body">
                  
<div class="col-lg-12">
  <div class="card info-card bg-light border-0 shadow-sm">
    <div class="card-body">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="card-title mb-0">
          Statistics <span class="text-muted">| Documents Requested</span>
        </h5>
        <div>
          <button class="btn btn-sm btn-outline-warning me-2" id="btnLine"><i class="bx bx-line-chart"></i> Line</button>
          <button class="btn btn-sm btn-outline-secondary me-2" id="btnBar"><i class="bx bx-bar-chart-alt"></i> Bar</button>
          <button class="btn btn-sm btn-outline-info" id="btnDonut"><i class="bx bx-doughnut-chart"></i> Donut</button>
        </div>
      </div>
      <div id="documentChart" style="height: 420px;"></div>
    </div>
  </div>
</div>


                </div>

              </div>
            </div><!-- End Reports -->
          </div>
        </div><!-- End Left side columns -->


</div>


      </div>
    </section>

  </main><!-- End #main -->

<!-- Details Drawer -->
<div class="offcanvas offcanvas-end shadow" tabindex="-1" id="detailsDrawer" aria-labelledby="detailsDrawerLabel">
  <div class="offcanvas-header bg-primary text-white">
    <h5 class="offcanvas-title" id="detailsDrawerLabel">
      <i class="bx bx-file"></i> Request Details
    </h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
  </div>
  <div class="offcanvas-body">
    <div id="drawerContent">
      <div class="text-center py-5 text-muted">
        <div class="spinner-border text-primary mb-3"></div>
        <p>Loading request details...</p>
      </div>
    </div>
  </div>
</div>



  <!-- ======= Footer ======= -->
  <?php include __DIR__ . '/footer.php'; ?>

  <a href="#" class="back-to-top d-flex align-items-center justify-content-center"><i class="bi bi-arrow-up-short"></i></a>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- jQuery (Required for DataTables) -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <!-- Vendor JS Files -->
  <script src="../assets/bootstrap.bundle.min.js"></script>
  <script src="../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

  <script src="../assets/vendor/simple-datatables/simple-datatables.js"></script>
  <script src="../assets/vendor/tinymce/tinymce.min.js"></script>
  <script src="../assets/vendor/php-email-form/validate.js"></script>
   <script src="https://unpkg.com/boxicons@2.1.4/dist/boxicons.js"></script>
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

  <!-- DataTables JS -->
  <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

  <!-- Template Main JS File -->
  <script src="../assets/js/main.js"></script>
  <script src="../assets/sweetalert2.js"></script>

  <script>


let chart;
let currentType = "line";

// ✂️ Shorten long document names
function shortenLabel(label, maxLength = 14) {
  return label.length > maxLength ? label.substring(0, maxLength - 2) + "…" : label;
}

// 🧠 Unified render function for all chart types
function renderChart(chartType = "line") {
  $.ajax({
    url: "get_doc_request_stats.php",
    method: "GET",
    dataType: "json",
    success: function (data) {
      if (!data || data.length === 0) {
        $('#documentChart').html('<p class="text-muted text-center mt-4">No document requests found.</p>');
        return;
      }

      const labels = data.map(item => shortenLabel(item.document));
      const counts = data.map(item => item.count);

      let options = {};

      // === LINE CHART (Gold-Orange) ===
      if (chartType === "line") {
        options = {
          series: [{ name: "Requests", data: counts }],
          chart: {
            type: "line",
            height: 420,
            toolbar: { show: false },
            animations: { enabled: true, easing: 'easeinout', speed: 700 },
          },
          stroke: { curve: "smooth", width: 3, colors: ["#f5a623"] },
          markers: {
            size: 5,
            colors: ["#fff"],
            strokeColors: "#f5a623",
            strokeWidth: 2,
            hover: { size: 7 },
          },
          dataLabels: { enabled: true },
          xaxis: {
            categories: labels,
            labels: { rotate: -45, style: { fontSize: "12px", colors: "#555" } },
          },
          yaxis: { title: { text: "Requests", style: { fontWeight: 600 } } },
          grid: { borderColor: "#f1f1f1", strokeDashArray: 4 },
          colors: ["#f5a623"],
          tooltip: { theme: "light" },
          legend: { position: "bottom" },
        };
      }

      // === BAR CHART (Light Orange, no border) ===
      else if (chartType === "bar") {
        options = {
          series: [{ name: "Requests", data: counts }],
          chart: {
            type: "bar",
            height: 420,
            toolbar: { show: false },
            animations: { enabled: true, easing: 'easeinout', speed: 700 },
          },
          plotOptions: {
            bar: {
              horizontal: true,
              borderRadius: 5,
              columnWidth: "55%",
              colors: { backgroundBarOpacity: 0 },
            },
          },
          dataLabels: { enabled: true },
          xaxis: {
            categories: labels,
            title: { text: "Number of Requests", style: { fontWeight: 600 } },
          },
          grid: { borderColor: "#f3f3f3", strokeDashArray: 4 },
          colors: ["#ffd580"], // light orange shade
          legend: { position: "bottom" },
        };
      }

      // === DONUT CHART (Auto color palette) ===
      else if (chartType === "donut") {
        options = {
          series: counts,
          chart: {
            type: "donut",
            height: 420,
            toolbar: { show: false },
          },
          labels: labels,
          colors: ["#ffb347", "#ffcc80", "#ff9966", "#ffc266", "#ffa366", "#ffd480", "#ffe5b4"],
          dataLabels: { enabled: true },
          legend: {
            position: "bottom",
            horizontalAlign: "center",
            fontSize: "13px",
            itemMargin: { horizontal: 10, vertical: 4 },
          },
          tooltip: {
            y: {
              formatter: val => val + " request(s)"
            }
          },
          responsive: [{
            breakpoint: 600,
            options: { chart: { height: 380 }, legend: { position: "bottom" } }
          }]
        };
      }

      if (chart) chart.destroy();
      chart = new ApexCharts(document.querySelector("#documentChart"), options);
      chart.render();
    },
    error: function () {
      $('#documentChart').html('<p class="text-danger text-center mt-4">Error loading statistics.</p>');
    },
  });
}

// 🎛️ Toggle events
$(document).ready(function () {
  renderChart("line");

  $("#btnLine").click(function () {
    currentType = "line";
    $("#btnLine").addClass("btn-warning").removeClass("btn-outline-warning");
    $("#btnBar").removeClass("btn-warning").addClass("btn-outline-secondary");
    $("#btnDonut").removeClass("btn-info").addClass("btn-outline-info");
    renderChart("line");
  });

  $("#btnBar").click(function () {
    currentType = "bar";
    $("#btnBar").addClass("btn-warning").removeClass("btn-outline-secondary");
    $("#btnLine").removeClass("btn-warning").addClass("btn-outline-warning");
    $("#btnDonut").removeClass("btn-info").addClass("btn-outline-info");
    renderChart("bar");
  });

  $("#btnDonut").click(function () {
    currentType = "donut";
    $("#btnDonut").addClass("btn-info").removeClass("btn-outline-info");
    $("#btnLine").removeClass("btn-warning").addClass("btn-outline-warning");
    $("#btnBar").removeClass("btn-warning").addClass("btn-outline-secondary");
    renderChart("donut");
  });
});


    function statistics_summary(){
      window.location='statistics_summary.php';
    }

    function released_summary(){
      window.location='released_summary.php';
    }







  </script>

</body>

</html>
