<?php

session_start();
ob_start();

include '../db.php';

rrms_require_login();

$getsystemconfig = "SELECT * FROM `tblconfig`";
$runsystemconfig = mysqli_query($conn, $getsystemconfig);
$rowconfig = $runsystemconfig ? mysqli_fetch_assoc($runsystemconfig) : [];
$_SESSION['systemname'] = $rowconfig['systemname'] ?? 'RRMS';
$_SESSION['systemcopyright'] = $rowconfig['systemcopyright'] ?? '';

$docCountResult = mysqli_query($conn, "SELECT COUNT(id) AS total FROM `tbldoctypes`");
$docCountRow = $docCountResult ? mysqli_fetch_assoc($docCountResult) : ['total' => 0];
$docTypeCount = (int) ($docCountRow['total'] ?? 0);

$categoryCountResult = mysqli_query($conn, "SELECT COUNT(catid) AS total FROM `tblcategory`");
$categoryCountRow = $categoryCountResult ? mysqli_fetch_assoc($categoryCountResult) : ['total' => 0];
$categoryCount = (int) ($categoryCountRow['total'] ?? 0);

$usedDocResult = mysqli_query($conn, "SELECT COUNT(DISTINCT doc_id) AS total FROM `tblrequested_by`");
$usedDocRow = $usedDocResult ? mysqli_fetch_assoc($usedDocResult) : ['total' => 0];
$usedDocCount = (int) ($usedDocRow['total'] ?? 0);

?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">

  <title>Document Types: <?php echo rrms_h($rowconfig['systemname'] ?? 'RRMS') ?></title>
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
  <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
  <link href="../assets/css/style.css" rel="stylesheet">
  <style>
    .setup-card {
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
      <h1>Document Types</h1>
      <nav>
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="index.php">Home</a></li>
          <li class="breadcrumb-item active">Document Types</li>
        </ol>
      </nav>
    </div>

    <section class="section dashboard">
      <div class="row g-3">
        <div class="col-12 col-md-4">
          <div class="card setup-card">
            <div class="card-body">
              <h5 class="card-title">Types <span>| Active</span></h5>
              <div class="d-flex align-items-center gap-3">
                <div class="metric-icon bg-primary"><i class="bi bi-files"></i></div>
                <div>
                  <h3 class="mb-0"><?php echo number_format($docTypeCount) ?></h3>
                  <small class="text-muted">available document types</small>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="col-12 col-md-4">
          <div class="card setup-card">
            <div class="card-body">
              <h5 class="card-title">Categories <span>| Groups</span></h5>
              <div class="d-flex align-items-center gap-3">
                <div class="metric-icon bg-success"><i class="bi bi-folder2-open"></i></div>
                <div>
                  <h3 class="mb-0"><?php echo number_format($categoryCount) ?></h3>
                  <small class="text-muted">document categories</small>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="col-12 col-md-4">
          <div class="card setup-card">
            <div class="card-body">
              <h5 class="card-title">In Use <span>| Requested</span></h5>
              <div class="d-flex align-items-center gap-3">
                <div class="metric-icon bg-warning"><i class="bi bi-link-45deg"></i></div>
                <div>
                  <h3 class="mb-0"><?php echo number_format($usedDocCount) ?></h3>
                  <small class="text-muted">types linked to requests</small>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="col-12 col-xl-4">
          <div class="card setup-card">
            <div class="card-body">
              <h5 class="card-title">Add Document Type</h5>
              <div class="mb-3">
                <label for="doc_cat" class="form-label">Category</label>
                <select id="doc_cat" class="form-control">
                  <?php
                    $categories = mysqli_query($conn, "SELECT catid, cat_name FROM `tblcategory` ORDER BY cat_name ASC");
                    while ($categories && ($category = mysqli_fetch_assoc($categories))) {
                        echo '<option value="' . (int) $category['catid'] . '">' . rrms_h($category['cat_name']) . '</option>';
                    }
                  ?>
                </select>
              </div>
              <div class="mb-3">
                <label for="doc_desc" class="form-label">Description</label>
                <input type="text" class="form-control" id="doc_desc" placeholder="e.g. Transcript of Records">
              </div>
              <button type="button" class="btn btn-primary w-100" onclick="save_doc_type()">
                <i class="bi bi-plus-circle"></i> Add Document Type
              </button>
            </div>
          </div>
        </div>

        <div class="col-12 col-xl-8">
          <div class="card setup-card">
            <div class="card-body">
              <h5 class="card-title">Document Type List</h5>
              <div id="doc_types_list">Loading...</div>
            </div>
          </div>
        </div>
      </div>
    </section>

  </main>

  <?php include __DIR__ . '/footer.php'; ?>

  <a href="#" class="back-to-top d-flex align-items-center justify-content-center"><i class="bi bi-arrow-up-short"></i></a>

  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
  <script src="../assets/js/main.js"></script>
  <script src="../assets/sweetalert2.js"></script>
  <script>
    $(document).ready(function () {
      get_doctypes();
    });

    function save_doc_type() {
      const docCat = $('#doc_cat').val();
      const docDesc = $('#doc_desc').val().trim();

      if (docDesc === '') {
        Swal.fire({
          icon: 'warning',
          title: 'Please check',
          text: 'Document description is required.'
        });
        return;
      }

      $.ajax({
        type: 'POST',
        url: 'query_request.php',
        data: {
          save_doc_type: '1',
          doc_cat: docCat,
          doc_desc: docDesc
        },
        success: function () {
          $('#doc_desc').val('');
          get_doctypes();
          Swal.fire({
            icon: 'success',
            title: 'Saved!',
            text: 'Document type added successfully.',
            timer: 1400,
            showConfirmButton: false
          });
        }
      });
    }

    function remove_doc(id) {
      Swal.fire({
        title: 'Are you sure?',
        text: 'This document type will be removed if it is not linked to a request.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, delete it!'
      }).then((result) => {
        if (!result.isConfirmed) return;

        $.ajax({
          type: 'POST',
          url: 'query_request.php',
          dataType: 'json',
          data: {
            delete_doc_type: '1',
            id: id
          },
          success: function (response) {
            if (response.status === 'success') {
              Swal.fire({
                icon: 'success',
                title: 'Deleted!',
                text: response.message,
                timer: 1500,
                showConfirmButton: false
              });
              get_doctypes();
            } else {
              Swal.fire({
                icon: 'error',
                title: 'Cannot Delete',
                text: response.message
              });
            }
          },
          error: function () {
            Swal.fire({
              icon: 'error',
              title: 'Error',
              text: 'Something went wrong while processing your request.'
            });
          }
        });
      });
    }

    function get_doctypes() {
      $('#doc_types_list').html(`
        <div style="padding: 1rem;">
          <div class="progress" style="height: 6px; background-color: #e9ecef;">
            <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 100%;"></div>
          </div>
          <div class="text-muted small mt-2">Loading document types...</div>
        </div>
      `);

      $.ajax({
        type: 'POST',
        url: 'query_request.php',
        data: { loading_doc_type: '1' },
        success: function (response) {
          $('#doc_types_list').html(response);
          $('#docttypeTable').DataTable({
            paging: true,
            pageLength: 10,
            lengthChange: true,
            searching: true,
            ordering: true,
            info: true,
            autoWidth: false
          });
        }
      });
    }
  </script>

</body>

</html>
