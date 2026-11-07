<?php

    session_start();    
    ob_start();


    include '../db.php';

    rrms_require_login();

    $mnt = '0';
    $yr = '0';  

    $getsystemconfig = "SELECT * FROM `tblconfig`";
    $runsystemconfig=mysqli_query($conn, $getsystemconfig);
    $rowconfig=mysqli_fetch_assoc($runsystemconfig);
    $_SESSION['systemname'] = $rowconfig['systemname'];
    $_SESSION['systemcopyright'] = $rowconfig['systemcopyright'];

?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">

  <title>Dashboard: <?php echo $rowconfig['systemname'] ?></title>
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

<body onload="get_assigned();">



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
          <li class="breadcrumb-item active">Manage Assigned Faculty</li>
        </ol>
      </nav>
    </div><!-- End Page Title -->

    <section class="section dashboard">

      <!-- <div id="test">test</div> -->
      <div class="row">

        <!-- Left side columns -->
        <div class="col-lg-12">
          <div class="row">

            <div class="col-lg-3">
              <div class="card info-card bg-light border-0 shadow-sm">
                <div class="card-body">
                  <h5 class="card-title">Programs <span class="text-muted">| Active</span></h5>
                  <div class="d-flex align-items-center">
                    <div class="card-icon rounded-circle d-flex align-items-center justify-content-center bg-primary text-white me-3" style="width:48px;height:48px;">
                      <i class='bx bx-qr'></i>
                    </div>
                    <div>
                      <h3 id="load_summary" class="mb-0">
                      <?php 
                          $get_req = "SELECT count(courseid) as req_count FROM `tblcourse`";
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
                      <small class="text-muted">total transactions</small>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Reports -->
            <div class="col-12">
              <div class="card">
                <div class="card-body">
                  <h5 class="card-title">Program<span> | List</span></h5>
                  <div id="main_data"></div>
                </div>
              </div>
            </div><!-- End Reports -->
          </div>
        </div><!-- End Left side columns -->

</div>


      </div>
    </section>

  </main><!-- End #main -->




  <!-- ======= Footer ======= -->
  <?php include __DIR__ . '/footer.php'; ?>



<!-- =============== ADD / EDIT FACULTY DRAWER =============== -->
<div class="offcanvas offcanvas-end shadow" tabindex="-1" id="facultyDrawer" aria-labelledby="facultyDrawerLabel">
  <div class="offcanvas-header bg-primary text-white">
    <h5 class="offcanvas-title" id="facultyDrawerLabel">
      <i class="bx bx-user"></i> Manage Faculty
    </h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
  </div>
  <div class="offcanvas-body">
    <form id="facultyForm">
      <input type="hidden" id="inchargeid">

      <div class="mb-3">
        <label for="inchargename" class="form-label fw-bold">Faculty Full Name</label>
        <input type="text" class="form-control" id="inchargename" placeholder="Enter faculty name..." required>
      </div>

      <div class="d-grid gap-2">
        <button type="button" class="btn btn-primary" onclick="save_faculty()">Save Faculty</button>
      </div>
    </form>
  </div>
</div>




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


  <!-- DataTables JS -->
  <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

  <!-- Template Main JS File -->
  <script src="../assets/js/main.js"></script>
  <script src="../assets/sweetalert2.js"></script>

  <script>

// 🟢 OPEN ADD FACULTY DRAWER
function openFacultyDrawer() {
  $('#inchargeid').val('');
  $('#inchargename').val('');
  $('#facultyDrawerLabel').text('Add Faculty');
  var drawer = new bootstrap.Offcanvas(document.getElementById('facultyDrawer'));
  drawer.show();
}


// 🟢 SAVE FACULTY
function save_faculty() {
  var formData = {
    save_faculty: 1,
    inchargeid: $('#inchargeid').val(),
    inchargename: $('#inchargename').val().trim()
  };

  if (formData.inchargename === '') {
    Swal.fire({ icon: 'warning', title: 'Please enter a faculty name.' });
    return;
  }

  $.ajax({
    type: "POST",
    url: "query_assigned.php",
    data: formData,
    success: function(response) {
      if (response.trim() === 'success' || response.trim() === 'updated') {
        Swal.fire({
          icon: 'success',
          title: response.trim() === 'success' ? 'Faculty Added!' : 'Faculty Updated!',
          timer: 1500,
          showConfirmButton: false
        });
        get_assigned();
        bootstrap.Offcanvas.getInstance(document.getElementById('facultyDrawer')).hide();
      } else {
        Swal.fire({ icon: 'error', title: 'Error', text: 'Something went wrong.' });
      }
    }
  });
}


// 🟢 EDIT FACULTY
function edit_faculty(id) {
  $.ajax({
    type: "POST",
    url: "query_assigned.php",
    data: { load_faculty: 1, inchargeid: id },
    dataType: "json",
    success: function(data) {
      $('#inchargeid').val(data.inchargeid);
      $('#inchargename').val(data.inchargename);
      $('#facultyDrawerLabel').text('Edit Faculty');
      var drawer = new bootstrap.Offcanvas(document.getElementById('facultyDrawer'));
      drawer.show();
    }
  });
}


// 🟢 DELETE FACULTY
function delete_faculty(id) {
  Swal.fire({
    title: "Are you sure?",
    text: "This will permanently delete the faculty record.",
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#3085d6",
    cancelButtonColor: "#d33",
    confirmButtonText: "Yes, delete it!"
  }).then((result) => {
    if (result.isConfirmed) {
      $.ajax({
        type: "POST",
        url: "query_assigned.php",
        dataType: "json",
        data: { delete_faculty: 1, inchargeid: id },
        success: function(response) {
          if (response.status === "deleted") {
            Swal.fire({ icon: "success", title: "Deleted!", text: response.message, timer: 1500, showConfirmButton: false });
            get_assigned();
          } else if (response.status === "in_use") {
            Swal.fire({ icon: "info", title: "Cannot Delete", text: response.message });
          } else {
            Swal.fire({ icon: "error", title: "Error", text: response.message });
          }
        },
        error: function() {
          Swal.fire({ icon: "error", title: "Server Error", text: "Something went wrong. Please try again later." });
        }
      });
    }
  });
}


  function get_assigned() {
      let progress = 0;
      let interval;

      // Insert the stylish striped progress bar
      $('#main_data').html(`
          <div style="padding: 1rem;">
              <div class="progress" style="height: 6px; background-color: #e9ecef;">
                  <div id="progress-bar" class="progress-bar progress-bar-striped progress-bar-animated" 
                       role="progressbar"
                       style="width: 0%; background: linear-gradient(90deg, #17a2b8, #0dcaf0);">
                  </div>
              </div>
              <div id="progress-label" style="font-size: 11px; margin-top: 6px; color: #6c757d;">
                  Loading biometric logs... 0%
              </div>
          </div>
      `);

      // Animate progress bar up to 90%
      interval = setInterval(() => {
          if (progress < 90) {
              progress++;
              $('#progress-bar').css('width', progress + '%');
              $('#progress-label').text(`Loading biometric logs... ${progress}%`);
          }
      }, 20);

      // AJAX load employee data
      $.ajax({
          type: "POST",
          url: "query_assigned.php",
          data: { "loading_assigned_fac": "1" },
          success: function (response) {
              clearInterval(interval);
              $('#progress-bar').css('width', '100%');
              $('#progress-label').html(`<i class="bx bx-check-circle text-success"></i> Load complete!`);

              // Fade out the loader then show table
              setTimeout(() => {
                  $('#main_data').html(response);
                  $('#requestTable').DataTable({
                      paging: true,
                      pageLength: 10,
                      lengthChange: true,
                      searching: true,
                      ordering: true,
                      info: true,
                      autoWidth: false
                  });
              }, 600);
          }
      });
  }






  </script>

</body>

</html>
