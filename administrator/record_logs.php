<?php
session_start();
require __DIR__ . '/../db.php';
require __DIR__ . '/record_log_functions.php';
rrms_require_login();
$rowconfig = mysqli_fetch_assoc(mysqli_query($conn, 'SELECT * FROM tblconfig LIMIT 1')) ?: [];
$checkers = rrms_log_rows($conn, 'SELECT inchargeid, inchargename FROM tblincharge ORDER BY inchargename');
$actorName = trim($_SESSION['fullname'] ?? '') ?: $_SESSION['username'];
$pageData = [
    'csrfToken' => rrms_csrf_token(), 'timezone' => date_default_timezone_get(),
];
header('Cache-Control: no-store');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Student Record Logs: <?php echo rrms_h($rowconfig['systemname'] ?? 'reQuest'); ?></title>
  <link href="../assets/img/request-logo.png" rel="icon">
  <link href="https://fonts.googleapis.com/css?family=Open+Sans:400,500,600,700|Nunito:400,600,700|Poppins:400,500,600" rel="stylesheet">
  <link href="../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
  <link href="../assets/css/style.css" rel="stylesheet">
  <link href="../assets/css/admin-theme.css" rel="stylesheet">
  <link href="../assets/css/record-logs.css" rel="stylesheet">
</head>
<body>
<?php include __DIR__ . '/header.php'; include __DIR__ . '/sidebar.php'; ?>
<main id="main" class="main record-logs-page">
  <div class="pagetitle">
    <h1>Student Record Logs</h1>
    <nav aria-label="Breadcrumb"><ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="index.php">Home</a></li>
      <li class="breadcrumb-item active" aria-current="page">Student Record Logs</li>
    </ol></nav>
  </div>

  <div class="log-page-intro">
    <div><p class="log-eyebrow">RECORD MOVEMENT</p><h2>Keep every record accounted for.</h2>
      <p>Log a student record out, track its return, and record who checked it.</p></div>
    <span class="log-policy-pill"><i class="bi bi-calendar2-week" aria-hidden="true"></i>7 working days to return</span>
  </div>
  <div id="logNotice" class="alert" role="status" hidden></div>

  <section class="log-metrics" aria-label="Record log summary">
    <?php foreach ([['total', 'Total logs', 'bi-journals'], ['outstanding', 'Outstanding', 'bi-box-arrow-up-right'], ['overdue', 'Overdue', 'bi-exclamation-circle'], ['returned', 'Returned & checked', 'bi-check2-circle']] as $metric): ?>
    <div class="card log-metric log-metric-<?php echo $metric[0]; ?>">
      <span class="log-metric-icon"><i class="bi <?php echo $metric[2]; ?>" aria-hidden="true"></i></span>
      <div><span><?php echo $metric[1]; ?></span><strong id="count-<?php echo $metric[0]; ?>">&mdash;</strong></div>
    </div>
    <?php endforeach; ?>
  </section>

  <section class="card log-entry-card" aria-labelledby="newLogTitle">
    <div class="log-section-heading"><div><p class="log-eyebrow">NEW ENTRY</p><h2 id="newLogTitle">Log out a student record</h2></div>
      <span class="log-step-label">Search &rarr; Select &rarr; Log out</span></div>
    <div class="log-entry-grid">
      <div class="log-search-panel">
        <label for="studentSearch" class="form-label">Find a student</label>
        <div class="log-search-input"><i class="bi bi-search" aria-hidden="true"></i>
          <input id="studentSearch" type="search" class="form-control" placeholder="Search by first or last name…" autocomplete="off" maxlength="200" aria-describedby="studentSearchHelp studentSearchStatus" aria-controls="studentResults"></div>
        <p id="studentSearchHelp" class="log-help">Search students who already have an encoded request. Details come from their latest request.</p>
        <p id="studentSearchStatus" class="log-help" role="status">Enter at least 2 characters to search.</p>
        <div id="studentResults" class="log-student-results" aria-label="Matching students"></div>
        <div id="studentPlaceholder" class="log-student-placeholder"><i class="bi bi-person-vcard" aria-hidden="true"></i>
          <strong>Start with an existing student</strong><span>Select a search result to review the encoded details.</span></div>
        <form id="outForm" hidden>
          <div class="log-selected-student"><div class="log-selected-heading"><span class="log-eyebrow">SELECTED STUDENT</span><button id="changeStudent" class="btn btn-link btn-sm" type="button">Change</button></div>
            <h3 id="selectedName"></h3><dl class="log-student-details">
              <div><dt>Course / Program</dt><dd id="selectedCourse"></dd></div>
              <div><dt>Last year of attendance</dt><dd id="selectedYear"></dd></div>
              <div><dt>Contact number</dt><dd id="selectedContact"></dd></div>
              <div><dt>Program incharge</dt><dd id="selectedIncharge"></dd></div>
            </dl><small id="selectedSource" class="log-help"></small></div>
          <label for="outRemarks" class="form-label mt-3">Remarks <span class="log-optional">(optional)</span></label>
          <textarea id="outRemarks" class="form-control" rows="2" maxlength="2000" placeholder="Add a note about this record movement…"></textarea>
          <p id="outError" class="text-danger mt-2 mb-0" role="alert" hidden></p>
          <div class="log-form-actions"><button id="outSubmit" type="submit" class="btn btn-primary"><i class="bi bi-box-arrow-up-right me-2" aria-hidden="true"></i>Log out record</button>
            <small>Student details and the return deadline are saved with this entry.</small></div>
        </form>
      </div>
      <aside class="log-entry-summary" aria-label="Automatic entry details">
        <div class="log-summary-icon"><i class="bi bi-clock-history" aria-hidden="true"></i></div>
        <h3>A clear trail, from out to returned.</h3>
        <dl><div><dt>Logged out by</dt><dd><?php echo rrms_h($actorName); ?></dd></div>
          <div><dt>Date &amp; time out</dt><dd>Automatically recorded on save</dd></div>
          <div><dt>Expected return</dt><dd id="duePreview">7 working days after date out</dd></div></dl>
        <p><i class="bi bi-info-circle" aria-hidden="true"></i>Counting starts the next day. Saturdays and Sundays are skipped. A record becomes overdue after its due date.</p>
        <p>Returns can be recorded at any time. The signed-in account and return time are saved; select the checker from Program Incharge.</p>
      </aside>
    </div>
  </section>

  <section class="card log-register" aria-labelledby="registerTitle">
    <div class="log-section-heading"><div><p class="log-eyebrow">MOVEMENT HISTORY</p><h2 id="registerTitle">Record register</h2></div>
      <button id="refreshLogs" class="btn btn-outline-primary btn-sm" type="button"><i class="bi bi-arrow-clockwise me-1" aria-hidden="true"></i>Refresh</button></div>
    <div class="log-register-tools"><div class="log-filters" role="group" aria-label="Filter record logs">
      <button type="button" data-log-filter="all" aria-pressed="true">All logs</button>
      <button type="button" data-log-filter="out" aria-pressed="false">Outstanding</button>
      <button type="button" data-log-filter="overdue" aria-pressed="false">Overdue</button>
      <button type="button" data-log-filter="returned" aria-pressed="false">Returned</button>
    </div><div class="log-search-input"><i class="bi bi-search" aria-hidden="true"></i>
      <input id="registerSearch" type="search" class="form-control" maxlength="200" placeholder="Search the register…" aria-label="Search the record register"></div></div>
    <div id="registerError" class="alert alert-danger mx-4" role="alert" hidden></div>
    <div class="log-table-scroll" tabindex="0" role="region" aria-label="Student record movement register">
      <table class="table log-table"><thead><tr>
        <th scope="col">Date Out</th><th scope="col">Student Name</th><th scope="col">Course</th>
        <th scope="col">Year<small>Last attendance</small></th><th scope="col">Logged Out By</th>
        <th scope="col">Date Returned</th><th scope="col">Checked By</th><th scope="col">Remarks</th><th scope="col">Action</th>
      </tr></thead><tbody id="logRows"><tr><td colspan="9" class="log-empty">Loading record logs…</td></tr></tbody></table>
    </div>
    <div class="log-pagination"><span id="logPageInfo" role="status"></span><div>
      <button id="previousLogs" type="button" class="btn btn-outline-primary btn-sm" disabled>Previous</button>
      <button id="nextLogs" type="button" class="btn btn-outline-primary btn-sm" disabled>Next</button>
    </div></div>
  </section>
</main>
<?php include __DIR__ . '/footer.php'; ?>

<div class="modal fade" id="returnModal" tabindex="-1" aria-labelledby="returnModalTitle" aria-hidden="true" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-fullscreen-sm-down">
    <form id="returnForm" class="modal-content">
      <div class="modal-header"><h2 class="modal-title fs-5" id="returnModalTitle">Record return</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close return form"></button></div>
      <div class="modal-body"><p class="log-help">Confirm the returned record and the person who checked it.</p>
        <div class="log-return-student"><strong id="returnStudent"></strong><span id="returnCourse"></span><small id="returnDue"></small></div>
        <label for="returnChecker" class="form-label mt-3">Checked by <span class="text-danger" aria-hidden="true">*</span></label>
        <select id="returnChecker" class="form-select" required><option value="">Select program incharge</option>
          <?php foreach ($checkers as $checker): ?><option value="<?php echo (int) $checker['inchargeid']; ?>"><?php echo rrms_h($checker['inchargename']); ?></option><?php endforeach; ?>
        </select><p class="log-help mt-2">From the <a href="manage_assigned_faculty.php">Program Incharge</a> list.</p>
        <label for="returnRemarks" class="form-label">Return remarks <span class="log-optional">(optional)</span></label>
        <textarea id="returnRemarks" class="form-control" rows="3" maxlength="2000" placeholder="Add any notes from the check…"></textarea>
        <div class="log-return-audit"><i class="bi bi-person-check" aria-hidden="true"></i><span>Recorded by <strong><?php echo rrms_h($actorName); ?></strong><br>The current date and time are saved automatically.</span></div>
        <p id="returnError" class="text-danger mt-3 mb-0" role="alert" hidden></p>
      </div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button id="returnSubmit" type="submit" class="btn btn-primary"><i class="bi bi-check2-circle me-1" aria-hidden="true"></i>Confirm return</button></div>
    </form>
  </div>
</div>
<script id="recordLogData" type="application/json"><?php echo json_encode($pageData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE); ?></script>
<script src="../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/record-logs.js"></script>
</body>
</html>
