<?php

    session_start();    
    ob_start();


    include '../db.php';
    require_once __DIR__ . '/request_status.php';

    rrms_require_login();

    $getsystemconfig = "SELECT * FROM `tblconfig`";
    $runsystemconfig=mysqli_query($conn, $getsystemconfig);
    $rowconfig=mysqli_fetch_assoc($runsystemconfig);
    $_SESSION['systemname'] = 'reQuest';
    $_SESSION['systemcopyright'] = $rowconfig['systemcopyright'];

    $requestListMode = isset($requestListMode) && $requestListMode === 'overdue' ? 'overdue' : 'pending';
    $requestPageTitle = $requestListMode === 'overdue' ? 'Overdue Requests' : 'Manage Requests';
    $requestTableTitle = $requestListMode === 'overdue' ? 'Overdue Requests' : 'Pending Requests';
    $requestTableSubtitle = $requestListMode === 'overdue' ? 'Needs attention' : 'Processing';
    $dashboard_counts = rrms_request_counts($conn);


?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">

  <title><?php echo rrms_h($requestPageTitle); ?> | reQuest</title>
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
  <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />


  <!-- Template Main CSS File -->
  <link href="../assets/css/style.css" rel="stylesheet">
  <style>
  /* 🔧 Adjust Select2 height to match Bootstrap 5 input fields */
  .select2-container .select2-selection--single {
    height: 38px !important;              /* same as .form-control */
    border: 1px solid #ced4da !important; /* consistent border */
    border-radius: 0.375rem !important;   /* rounded corners */
    padding: 4px 8px !important;
  }

  .select2-container--default .select2-selection--single .select2-selection__rendered {
    line-height: 28px !important;         /* vertically center text */
    font-size: 0.95rem !important;
    color: #495057 !important;
  }

  .select2-container--default .select2-selection--single .select2-selection__arrow {
    height: 36px !important;              /* aligns arrow perfectly */
    right: 8px !important;
  }

  .rrms-percent-loader {
    max-width: 520px;
    margin: 0 auto;
    padding: 1.25rem;
  }

  .rrms-loader-meta {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: 0.5rem;
    color: #6c757d;
    font-size: 0.875rem;
    font-weight: 600;
  }

  .rrms-loader-count {
    color: var(--request-green);
    font-variant-numeric: tabular-nums;
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
      <h1><?php echo rrms_h($requestPageTitle); ?></h1>
      <nav>
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="index.php">Home</a></li>
          <?php if ($requestListMode === 'overdue'): ?>
          <li class="breadcrumb-item"><a href="manage_request.php">Manage Requests</a></li>
          <?php endif; ?>
          <li class="breadcrumb-item active"><?php echo rrms_h($requestPageTitle); ?></li>
        </ol>
      </nav>
    </div><!-- End Page Title -->

    <section class="section dashboard">

      <!-- <div id="test">test</div> -->
      <div class="row">

        <!-- Left side columns -->
        <div class="col-lg-12">
          <div class="row request-actions">
            <!-- Manage Request Card -->
            <div class="col-12 col-sm-6 col-xl-4 col-xxl">
              <a class="card info-card request-action" href="manage_request.php"<?php if ($requestListMode === 'pending'): ?> aria-current="page"<?php endif; ?>>
                <span class="card-body">
                  <span class="card-title">New Request <span class="text-muted">| Entry</span></span>
                  <span class="d-flex align-items-center">
                    <span class="card-icon rounded-circle d-flex align-items-center justify-content-center bg-primary text-white me-3" style="width:48px;height:48px;">
                      <i class="bx bx-file" aria-hidden="true"></i>
                    </span>
                    <span>
                      <span id="load_requests" class="request-metric mb-0"><?php echo number_format((int) $dashboard_counts['pending_requests']); ?></span>
                      <small class="text-muted">pending requests</small>
                    </span>
                  </span>
                </span>
              </a>
            </div>

            <!-- Overdue Request Card -->
            <div class="col-12 col-sm-6 col-xl-4 col-xxl">
              <a class="card info-card request-action" href="overdue_requests.php" aria-label="View overdue requests"<?php if ($requestListMode === 'overdue'): ?> aria-current="page"<?php endif; ?>>
                <div class="card-body">
                  <h5 class="card-title">Overdue Requests <span class="text-muted">| Follow-up</span></h5>
                  <div class="d-flex align-items-center">
                    <div class="card-icon rounded-circle d-flex align-items-center justify-content-center bg-danger text-white me-3" style="width:48px;height:48px;">
                      <i class="bx bxs-error-circle"></i>
                    </div>
                    <div>
                      <h3 id="load_overdue" class="mb-0"><?php echo number_format((int) $dashboard_counts['overdue_requests']); ?></h3>
                      <small class="text-muted">past due, unreleased</small>
                    </div>
                  </div>
                </div>
              </a>
            </div>

            <!-- Manage Document Type Card -->
            <div class="col-12 col-sm-6 col-xl-4 col-xxl">
              <a class="card info-card request-action" href="document_type.php">
                <div class="card-body">
                  <h5 class="card-title">Document Types <span class="text-muted">| Setup</span></h5>
                  <div class="d-flex align-items-center">
                    <div class="card-icon rounded-circle d-flex align-items-center justify-content-center bg-success text-white me-3" style="width:48px;height:48px;">
                      <i class='bx bx-book'></i>
                    </div>
                    <div>
                      <h3 id="load_doc_types" class="mb-0"><?php echo number_format((int) $dashboard_counts['document_types']); ?></h3>
                      <small class="text-muted">document types available</small>
                    </div>
                  </div>
                </div>
              </a>
            </div>

            <!-- Summary Card -->
            <div class="col-12 col-sm-6 col-xl-4 col-xxl">
              <a class="card info-card request-action" href="released_summary.php">
                <div class="card-body">
                  <h5 class="card-title">Summary <span class="text-muted">| Released</span></h5>
                  <div class="d-flex align-items-center">
                    <div class="card-icon rounded-circle d-flex align-items-center justify-content-center bg-warning text-white me-3" style="width:48px;height:48px;">
                      <i class='bx bxs-objects-vertical-top'></i>
                    </div>
                    <div>
                      <h3 id="load_summary" class="mb-0"><?php echo number_format((int) $dashboard_counts['released_requests']); ?></h3>
                      <small class="text-muted">transactions this period</small>
                    </div>
                  </div>
                </div>
              </a>
            </div>

            <!-- Summary Card -->
            <div class="col-12 col-sm-6 col-xl-4 col-xxl">
              <a class="card info-card request-action" href="statistics_summary.php">
                <div class="card-body">
                  <h5 class="card-title">Statistics <span class="text-muted">| Documents</span></h5>
                  <div class="d-flex align-items-center">
                    <div class="card-icon rounded-circle d-flex align-items-center justify-content-center bg-danger text-white me-3" style="width:48px;height:48px;">
                      <i class='bx bx-bar-chart-alt-2'></i>
                    </div>
                    <div>
                      <h3 id="load_document_transactions" class="mb-0"><?php echo number_format((int) $dashboard_counts['document_transactions']); ?></h3>
                      <small class="text-muted">total transactions</small>
                    </div>
                  </div>
                </div>
              </a>
            </div>

            <!-- Reports -->
            <div class="col-12">
              <div class="card">

                <div class="card-body">
                  <div class="request-list-toolbar">
                    <h5 class="card-title"><?php echo rrms_h($requestTableTitle); ?> <span>| <?php echo rrms_h($requestTableSubtitle); ?></span></h5>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addRequestModal" aria-haspopup="dialog" aria-controls="addRequestModal">
                      <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Create Request
                    </button>
                  </div>
                  <div id="main_data"><?php $requestTableMode = $requestListMode; include __DIR__ . '/request_table_view.php'; ?></div>
                </div>

              </div>
            </div><!-- End Reports -->


          </div>
        </div><!-- End Left side columns -->

 
<!-- Create Request Modal -->
<div class="modal fade" id="addRequestModal" tabindex="-1" aria-labelledby="addRequestModalLabel" aria-hidden="true" data-bs-backdrop="static">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable modal-fullscreen-sm-down">
    <form id="requestForm" class="modal-content request-form border-0 shadow-lg">
      <div class="modal-header">
        <h5 class="modal-title font-weight-bold" id="addRequestModalLabel">
          <i class="bi bi-file-earmark-plus me-2" aria-hidden="true"></i>Create Request
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close create request"></button>
      </div>

        <div class="modal-body px-4 py-3">

          <!-- Personal Information -->
          <div class="card mb-3 border-primary">
            <div class="card-header bg-light py-2">
              <strong><i class="bi bi-person me-1 text-primary" aria-hidden="true"></i> Personal Information</strong>
            </div>
            <div class="card-body pt-3 pb-1">
              <div class="row">
                <div class="col-lg-6">
                  <label for="date_processed">DATE PROCESSED</label>
                  <input type="datetime-local" class="form-control" id="date_processed">
                </div>
              </div>
              <div class="row py-2">
                <div class="col-12 col-sm-6 col-xl-4 col-xxl">
                  <label for="req_lastname">Last Name</label>
                  <input type="text" class="form-control" id="req_lastname" required>
                </div>
                <div class="col-12 col-sm-6 col-xl-4 col-xxl">
                  <label for="req_firstname">First Name</label>
                  <input type="text" class="form-control" id="req_firstname" required>
                </div>
                <div class="col-12 col-sm-6 col-xl-4 col-xxl">
                  <label for="req_ext">Ext.</label>
                  <input type="text" class="form-control" id="req_ext" placeholder="e.g. Jr., III">
                </div>
                <div class="col-12 col-sm-6 col-xl-4 col-xxl">
                  <label for="req_middlename">Middle Name</label>
                  <input type="text" class="form-control" id="req_middlename">
                </div>
              </div>

              <div class="row mt-2">
                <div class="col-lg-12">
                  <label>Did you have any changes in your name?</label>
                  <div class="d-flex align-items-center gap-3 mt-1">
                    <div class="custom-control custom-radio">
                      <input class="custom-control-input" type="radio" id="req_namechange_yes" name="req_namechange" value="Yes">
                      <label class="custom-control-label" for="req_namechange_yes">Yes</label>
                    </div>
                    <div class="custom-control custom-radio">
                      <input class="custom-control-input" type="radio" id="req_namechange_no" name="req_namechange" value="No" checked>
                      <label class="custom-control-label" for="req_namechange_no">No</label>
                    </div>
                  </div>
                </div>
              </div>

              <div class="row mt-2">
                <div class="col-lg-12">
                  <label for="req_formername">If Yes, Former Name</label>
                  <input type="text" class="form-control" id="req_formername" placeholder="Enter former name if applicable">
                </div>
              </div>
            </div>
          </div>

          <!-- Academic Details -->
          <div class="card mb-3 border-success">
            <div class="card-header bg-light py-2">
              <strong><i class="bi bi-mortarboard me-1 text-success" aria-hidden="true"></i> Academic Details</strong>
            </div>

            <div class="card-body">
              <div class="row">
                <div class="col-12 col-sm-6 col-xl-4 col-xxl">
                  <label for="req_year_attendance">Last Year of Attendance</label>
                  <select id="req_year_attendance" class="form-control">
                    <option value="">Select</option>
                    <?php for ($i = date("Y"); $i >= 1990; $i--) echo "<option value='$i'>$i</option>"; ?>
                  </select>
                </div>

                <div class="col-12 col-sm-6 col-xl-4 col-xxl">
                  <label for="req_sem_midyear">Semester/Midyear</label>
                  <select id="req_sem_midyear" class="form-control">
                    <option value="1st Semester">1st Semester</option>
                    <option value="2nd Semester">2nd Semester</option>
                    <option value="Midyear">Midyear</option>
                    <option value="Summer">Summer</option>
                  </select>
                </div>

                <div class="col-12 col-sm-6 col-xl-4 col-xxl">
                  <label for="req_acad_year">Academic Year</label>
                  <select id="req_acad_year" class="form-control">
                    <option value="">Select</option>
                    <?php
                      $startYear = 1990;
                      $currentYear = date('Y') + 1; // one year ahead to include current SY (e.g., 2025-2026)
                      for ($y = $currentYear; $y >= $startYear; $y--) {
                          $next = $y + 1;
                          echo "<option value='{$y}-{$next}'>{$y}-{$next}</option>";
                      }
                    ?>
                  </select>
                </div>


                <div class="col-lg-12">
                  <label for="req_program">Degree / Program</label>
                  <select  class="js-example-basic-single" name="state" id="req_program">
                    <?php 
                      $get_course = "SELECT * FROM `tblcourse` order by coursecode ASC";
                      $runget_course = mysqli_query($conn, $get_course);
                      while($r_course = mysqli_fetch_assoc($runget_course)){
                        echo '<option value="'.$r_course['courseid'].'">'.$r_course['coursecode'].' - '.$r_course['coursedescription'].' ('.$r_course['coursemajor'].')</option>';
                      }
                    ?>
                  </select>
                </div>
                <div class="col-lg-6">
                  <label for="req_contact_number">Contact Number</label>
                  <input type="text" class="form-control" id="req_contact_number" placeholder="e.g. 09XXXXXXXXX">
                </div>

              </div>
            </div>
          </div>

          <!-- Request Timeline -->
          <div class="card border-info">
            <div class="card-header bg-light py-2">
              <strong><i class="bi bi-clock me-1 text-primary" aria-hidden="true"></i> Request Timeline</strong>
            </div>
            <div class="card-body">
              <div class="row">
                <div class="col-lg-4">
                  <label for="req_datetime_request">Date & Time of Request</label>
                  <input type="datetime-local" class="form-control" id="req_datetime_request">
                </div>
                <div class="col-lg-4">
                  <label for="req_due_date">Due Date</label>
                  <input type="date" class="form-control" id="req_due_date">
                </div>
                <div class="col-lg-4" style="display: none;">
                  <label for="req_datetime_released">Date & Time of Release</label>
                  <input type="datetime-local" class="form-control" id="req_datetime_released">
                </div>
                <div class="col-lg-4">
                  <label for="prog_assigned">Select Program Assigned</label>
                  <select id="prog_assigned" class="form-control">
                      <?php 
                        $get_ass = "SELECT * FROM `tblincharge`";
                        $runget_ass = mysqli_query($conn, $get_ass);
                        while($row_ass = mysqli_fetch_assoc($runget_ass)){
                          echo'<option value="'.$row_ass['inchargeid'].'">'.$row_ass['inchargename'].'</option>';
                        }
                      ?>
                  </select>
                </div>
              </div>
            </div>
          </div>

        </div>

        <div class="modal-footer bg-light">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
            Cancel
          </button>
          <button type="submit" id="saveRequestButton" class="btn btn-primary">
            <i class="bi bi-check2 me-1" aria-hidden="true"></i>Create Request
          </button>
        </div>
    </form>
  </div>
</div>





</div>
        <!-- Manage Document Types Modal -->
        <div class="modal fade" id="modal_request_doc" tabindex="-1" aria-labelledby="modalDocTypesLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
          <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable modal-fullscreen-sm-down">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title" id="modalDocTypesLabel">Select Document Request <br><span id="get_fullname"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">
                <div class="row">
                  <div class="col-lg-12">
                    <input type="hidden" id="the_requestor">
                    <label for="get_doc_id">Select Document</label>
                    <select id="get_doc_id" class="form-control">
                      <?php 
                        $get_doc_list = "SELECT * FROM `tbldoctypes`";
                        $runget_list = mysqli_query($conn, $get_doc_list);
                        while($r_doclist = mysqli_fetch_assoc($runget_list)){
                         echo'<option value="'.$r_doclist['id'].'">'.$r_doclist['doc_desc'].'</option>';
                        }
                      ?>
                      
                    </select>
                  </div>
                </div>
                <div class="row">
                  <div class="col-lg-12 py-2">
                    <button onclick="saving_requested()" class="btn btn-primary btn-sm">Add to request</button>
                  </div>
                </div>
                <div class="row">
                  <div class="col-lg-12">
                    <div id="requestor_req">Loading request</div>
                  </div>
                </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
              </div>
            </div>
          </div>
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

<!-- jQuery (Required for DataTables) -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

  <!-- DataTables JS -->
  <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

  <!-- Template Main JS File -->
  <script src="../assets/js/main.js"></script>
  <script src="../assets/sweetalert2.js"></script>
  <script src="../assets/js/request-records.js"></script>

  <script>
let requestDocsChanged = false;

function add_remarks(req_id) {
  // 1️⃣ Fetch existing remarks (if any)
  $.post('get_remarks.php', { req_id: req_id }, function(existing) {
    Swal.fire({
      title: existing.trim() ? 'Edit Remarks' : 'Add Remarks',
      input: 'textarea',
      inputLabel: 'Enter your remarks:',
      inputValue: existing.trim(),
      inputPlaceholder: 'Type your remarks here...',
      showCancelButton: true,
      confirmButtonText: existing.trim() ? 'Update' : 'Save',
      preConfirm: (remarks) => {
        if (!remarks.trim()) {
          Swal.showValidationMessage('Remarks cannot be empty!');
          return false;
        }
        return remarks.trim();
      }
    }).then((result) => {
      if (!result.isConfirmed) return;

      $.ajax({
        url: 'query_request.php',
        type: 'POST',
        data: { req_id: req_id, remarks: result.value },
        success: function (response) {
          if (response.trim() === 'success') {
            // ✅ Change button to info (blue)
            const btn = $(`button[onclick="add_remarks('${req_id}')"]`);
            btn.removeClass('btn-secondary').addClass('btn-info');

            Swal.fire({
              icon: 'success',
              title: 'Saved!',
              text: 'Remarks saved successfully.',
              timer: 1500,
              showConfirmButton: false
            });
          } else {
            Swal.fire({ icon: 'error', title: 'Failed', text: response });
          }
        },
        error: function () {
          Swal.fire({ icon: 'error', title: 'Error', text: 'Unable to save remarks.' });
        }
      });
    });
  });
}




// ✅ Initialize Select2 safely after DOM and Bootstrap are ready
$(document).ready(function () {
  $('#requestForm').on('submit', function (event) {
    event.preventDefault();
    saving_req();
  });
  if ($.fn.select2) {
    $('.js-example-basic-single').select2({
      width: '100%',
      placeholder: 'Select Program',
      allowClear: true,
      dropdownParent: $('#addRequestModal')
    });

    $('#addRequestModal').on('shown.bs.modal', function () {
      $('.js-example-basic-single').select2({
        width: '100%',
        placeholder: 'Select Program',
        allowClear: true,
        dropdownParent: $('#addRequestModal')
      });
    });
  }

  get_req();

  $('#modal_request_doc').on('hidden.bs.modal', function () {
    if (!requestDocsChanged) {
      return;
    }

    requestDocsChanged = false;
    get_req();
  });
});

function statistics_summary(){
  window.location='statistics_summary.php';
}

function released_summary(){
  window.location='released_summary.php';
}

function editRequestDrawer(req_id) {
  var drawer = new bootstrap.Offcanvas(document.getElementById('detailsDrawer'));
  drawer.show();

  $('#drawerContent').html(`
    <div class="text-center py-5 text-muted">
      <div class="spinner-border text-primary mb-3"></div>
      <p>Loading edit form...</p>
    </div>
  `);

  $.ajax({
    type: "POST",
    url: "query_request.php",
    data: { "load_edit_drawer": "1", "req_id": req_id },
    success: function (response) {
      $('#drawerContent').html(response);
    },
    error: function () {
      $('#drawerContent').html(`<p class="text-danger text-center mt-3">Error loading edit form.</p>`);
    }
  });
}


// ✅ Function to save updated request
function update_request(req_id) {
  var formData = {
    update_request: 1,
    req_id: req_id,
    req_lastname: $('#edit_req_lastname').val().trim(),
    req_firstname: $('#edit_req_firstname').val().trim(),
    req_middlename: $('#edit_req_middlename').val().trim(),
    req_ext: $('#edit_req_ext').val().trim(),
    req_contact_number: $('#edit_req_contact_number').val().trim(),
    req_due_date: $('#edit_req_due_date').val(),
    date_processed: $('#edit_date_processed').val(),
    prog_assigned_edit: $('#prog_assigned_edit').val()    
  };

  $.ajax({
    type: "POST",
    url: "query_request.php",
    data: formData,
    success: function (response) {
      if (response.trim() === "success") {
        Swal.fire({
          icon: "success",
          title: "Updated!",
          text: "Request information updated successfully.",
          timer: 1500,
          showConfirmButton: false
        });
        get_req();
        var drawer = bootstrap.Offcanvas.getInstance(document.getElementById('detailsDrawer'));
        drawer.hide();
      } else {
        Swal.fire({
          icon: "error",
          title: "Error",
          text: "Update failed: " + response
        });
      }
    }
  });
}


    function refresh_request(){
      if (!requestDocsChanged) {
        return;
      }

      requestDocsChanged = false;
      get_req();
    }

    function releasing_docs(req_id){

      var date_released = $('#date_released').val();

      var prog_assigned = '999';
      var released_remarks = $('#released_remarks').val();      

      if(date_released==''){
        Swal.fire({
          title: "Date and Time!",
          text: "You must select date and time first!",
          icon: "info"
        });
      }else{
        Swal.fire({
          title: "Release the docs?",
          text: "You are about to release all related documents here!",
          icon: "info",
          showCancelButton: true,
          confirmButtonColor: "#3085d6",
          cancelButtonColor: "#d33",
          confirmButtonText: "Yes, released it!"
        }).then((result) => {
          if (result.isConfirmed) {
            $.ajax({
              type: "POST",
              url: "query_request.php",
              data: {
                "released_requested": "1",
                "date_released" : date_released,
                "req_id": req_id,
                "prog_assigned": prog_assigned,
                "released_remarks": released_remarks
              },
              success: function (response) {
                get_req();

                // ✅ Close the drawer smoothly
                setTimeout(() => {
                  const drawer = bootstrap.Offcanvas.getInstance(document.getElementById('detailsDrawer'));
                  if (drawer) drawer.hide();
                }, 600);
                            
              }
            });
          }
        });        
      }


    }


function showDetailsDrawer(req_id) {
  // Show the drawer
  var drawer = new bootstrap.Offcanvas(document.getElementById('detailsDrawer'));
  drawer.show();

  // Insert loading animation
  $('#drawerContent').html(`
    <div class="text-center py-5 text-muted">
      <div class="spinner-border text-primary mb-3"></div>
      <p>Fetching document details...</p>
    </div>
  `);

  // Fetch details via AJAX
  $.ajax({
    type: "POST",
    url: "query_request.php",
    data: { "load_details_drawer": "1", "req_id": req_id },
    success: function (response) {
      $('#drawerContent').html(response);
    },
    error: function () {
      $('#drawerContent').html(`<p class="text-danger text-center mt-3">Error loading details.</p>`);
    }
  });
}


    function remove_requested_doc(reqbyid){
      var the_requestor = $('#the_requestor').val();
      Swal.fire({
        title: "Are you sure?",
        text: "You won't be able to revert this!",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33",
        confirmButtonText: "Yes, delete it!"
      }).then((result) => {
        if (result.isConfirmed) {
          $.ajax({
            type: "POST",
            url: "query_request.php",
            data: {
              "delete_the_requestor_requested": "1",
              "reqbyid": reqbyid
            },
            success: function (response) {
              requestDocsChanged = true;
              get_requested(the_requestor);
            }
          });
        }
      }); 
    }

    function saving_requested(){
      var get_doc_id = $('#get_doc_id').val();
      var the_requestor = $('#the_requestor').val();
      $.ajax({
        type: "POST",
        url: "query_request.php",
        data: {
          "save_doc_type_requestor": "1",
          "get_doc_id": get_doc_id,
          "the_requestor" : the_requestor
        },
        success: function (response) {
          requestDocsChanged = true;
          get_requested(the_requestor);
        }
      });
    }

    function showPercentLoader(target, label) {
      const $target = $(target);
      let progress = 0;
      let interval;

      $target.html(`
          <div class="rrms-percent-loader">
              <div class="rrms-loader-meta">
                  <span>${label}</span>
                  <span class="rrms-loader-count">0%</span>
              </div>
              <div class="progress" style="height: 8px; background-color: #e9ecef;">
                  <div class="progress-bar progress-bar-striped progress-bar-animated rrms-loader-bar"
                       role="progressbar"
                       aria-valuenow="0"
                       aria-valuemin="0"
                       aria-valuemax="100"
                       style="width: 0%; background: linear-gradient(90deg, #284b35, #849d65);">
                  </div>
              </div>
          </div>
      `);

      function setProgress(value) {
        progress = Math.max(0, Math.min(100, value));
        $target.find('.rrms-loader-bar')
          .css('width', progress + '%')
          .attr('aria-valuenow', progress);
        $target.find('.rrms-loader-count').text(progress + '%');
      }

      interval = setInterval(function () {
        if (progress < 90) {
          setProgress(progress + Math.max(1, Math.ceil((90 - progress) / 9)));
        }
      }, 80);

      return function finish(callback) {
        clearInterval(interval);
        setProgress(100);
        callback();
      };
    }


    function get_requested(req_id){
      const finishLoader = showPercentLoader('#requestor_req', 'Loading requested documents...');

      $.ajax({
          type: "POST",
          url: "query_request.php",
          data: { 
            "loding_req_req": "1", 
            "req_id" : req_id 
          },
          success: function (response) {
            finishLoader(function () {
              $('#requestor_req').html(response);
              $('#requestorTable').DataTable({
                  paging: true,
                  pageLength: 10,
                  lengthChange: true,
                  searching: true,
                  ordering: true,
                  info: true,
                  autoWidth: false,
                  deferRender: true
              });
            });
          }
      });
    }

    function request_docs(req_id, fullname){
      requestDocsChanged = false;
      $('#the_requestor').val(req_id);
      $('#get_fullname').html(fullname);
      get_requested(req_id);
      $('#modal_request_doc').modal('show');
    }

    function remove_requestor(id){
      Swal.fire({
        title: "Are you sure?",
        text: "You won't be able to revert this!",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33",
        confirmButtonText: "Yes, delete it!"
      }).then((result) => {
        if (result.isConfirmed) {
          $.ajax({
            type: "POST",
            url: "query_request.php",
            data: {
              "delete_the_requestor": "1",
              "id": id
            },
            success: function (response) {
              get_req();
            }
          });
        }
      });
    }

function saving_req() {
  const saveButton = document.getElementById('saveRequestButton');
  if (saveButton.disabled) return;
  var req_lastname = $('#req_lastname').val().trim();
  var req_firstname = $('#req_firstname').val().trim();
  var req_ext = $('#req_ext').val().trim();
  var req_middlename = $('#req_middlename').val().trim();
  var req_namechange = $("input[name='req_namechange']:checked").val();
  var req_formername = $('#req_formername').val().trim();
  var req_year_attendance = $('#req_year_attendance').val();
  var req_sem_midyear = $('#req_sem_midyear').val().trim();
  var req_acad_year = $('#req_acad_year').val();
  var req_program = $('#req_program').val();
  var req_contact_number = $('#req_contact_number').val().trim();
  var req_datetime_request = $('#req_datetime_request').val();
  var req_due_date = $('#req_due_date').val();
  var req_datetime_released = $('#req_datetime_released').val();
  var date_processed = $('#date_processed').val();
  var prog_assigned = $('#prog_assigned').val();  


  // 🧩 Validation
  if (
    req_lastname === "" ||
    req_firstname === "" ||
    req_middlename === "" ||
    req_year_attendance === "" ||
    req_sem_midyear === "" ||
    req_acad_year === "" ||
    req_program === "" ||
    req_contact_number === "" ||
    req_datetime_request === "" ||
    req_due_date === "" ||
    prog_assigned === ""
  ) {
    Swal.fire({
      title: "Please check",
      text: "Missing Information, Please fill out all required fields before saving.",
      icon: "warning"
    });
    return;
  }

  // ✅ Contact number format check
  var contactPattern = /^09\d{9}$/;
  if (!contactPattern.test(req_contact_number)) {
    Swal.fire({
      title: "Please check",
      text: "Invalid Contact Number, Please enter a valid Philippine mobile number (e.g. 09XXXXXXXXX).",
      icon: "warning"
    });
    return;
  }

  // ✅ AJAX Submission
  saveButton.disabled = true;
  saveButton.setAttribute('aria-busy', 'true');
  $.ajax({
    type: "POST",
    url: "query_request.php",
    data: {
      save_request: 1,
      req_lastname: req_lastname,
      req_firstname: req_firstname,
      req_ext: req_ext,
      req_middlename: req_middlename,
      req_namechange: req_namechange,
      req_formername: req_formername,
      req_year_attendance: req_year_attendance,
      req_sem_midyear: req_sem_midyear,
      req_acad_year: req_acad_year,
      req_program: req_program,
      req_contact_number: req_contact_number,
      req_datetime_request: req_datetime_request,
      req_due_date: req_due_date,
      req_datetime_released: req_datetime_released,
      date_processed: date_processed,
      prog_assigned: prog_assigned
    },
    success: function (response) {
      if (response.trim() === 'success') {
        bootstrap.Modal.getOrCreateInstance(document.getElementById('addRequestModal')).hide();
        get_req();
      } else {
        Swal.fire({ icon: 'error', title: 'Unable to create request', text: 'Your request was not saved. Please check the information and try again.' });
      }
    },
    error: function () {
      Swal.fire({ icon: 'error', title: 'Unable to create request', text: 'The connection was interrupted. Check the request list before trying again.' });
    },
    complete: function () {
      saveButton.disabled = false;
      saveButton.removeAttribute('aria-busy');
    }

  });
}



    function show_request_entry(){
      $('#addRequestModal').modal('show');
    }

  function get_req() {
    window.rrmsRequestRecords.load(<?php echo json_encode($requestListMode); ?>);
  }

  </script>

</body>

</html>
