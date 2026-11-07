<?php
$currentPage = basename($_SERVER['PHP_SELF'] ?? '');

if (!function_exists('rrms_sidebar_active')) {
    function rrms_sidebar_active($pages)
    {
        global $currentPage;
        $pages = (array) $pages;

        return in_array($currentPage, $pages, true) ? '' : ' collapsed';
    }
}
?>
  <!-- ======= Sidebar ======= -->
  <aside id="sidebar" class="sidebar">

    <ul class="sidebar-nav" id="sidebar-nav">
      <li class="nav-heading">Overview</li>

      <li class="nav-item">
        <a class="nav-link<?php echo rrms_sidebar_active('index.php'); ?>" href="index.php">
          <i class="bi bi-grid"></i>
          <span>Dashboard</span>
        </a>
      </li>

      <li class="nav-heading">Operations</li>

      <li class="nav-item">
        <a class="nav-link<?php echo rrms_sidebar_active('manage_request.php'); ?>" href="manage_request.php">
          <i class="bi bi-inboxes"></i>
          <span>Manage Requests</span>
        </a>
      </li>

      <li class="nav-item">
        <a class="nav-link<?php echo rrms_sidebar_active('overdue_requests.php'); ?>" href="overdue_requests.php">
          <i class="bi bi-exclamation-circle"></i>
          <span>Overdue Requests</span>
        </a>
      </li>

      <li class="nav-item">
        <a class="nav-link<?php echo rrms_sidebar_active('record_logs.php'); ?>" href="record_logs.php">
          <i class="bi bi-journal-arrow-up"></i>
          <span>Student Record Logs</span>
        </a>
      </li>

      <li class="nav-item">
        <a class="nav-link<?php echo rrms_sidebar_active('document_type.php'); ?>" href="document_type.php">
          <i class="bi bi-files"></i>
          <span>Document Types</span>
        </a>
      </li>

      <li class="nav-item">
        <a class="nav-link<?php echo rrms_sidebar_active('released_summary.php'); ?>" href="released_summary.php">
          <i class="bi bi-check2-circle"></i>
          <span>Released Summary</span>
        </a>
      </li>

      <li class="nav-item">
        <a class="nav-link<?php echo rrms_sidebar_active('statistics_summary.php'); ?>" href="statistics_summary.php">
          <i class="bi bi-bar-chart"></i>
          <span>Statistics</span>
        </a>
      </li>

      <li class="nav-heading">Management</li>

      <li class="nav-item">
        <a class="nav-link<?php echo rrms_sidebar_active('manage_programs.php'); ?>" href="manage_programs.php">
          <i class="bi bi-mortarboard"></i>
          <span>Degree / Program</span>
        </a>
      </li>

      <li class="nav-item">
        <a class="nav-link<?php echo rrms_sidebar_active('manage_assigned_faculty.php'); ?>" href="manage_assigned_faculty.php">
          <i class="bi bi-person-badge"></i>
          <span>Program Incharge</span>
        </a>
      </li>

      <li class="nav-heading">Reports</li>

      <li class="nav-item">
        <a class="nav-link<?php echo rrms_sidebar_active(['print_summary.php', 'printed_summary.php']); ?>" href="print_summary.php">
          <i class="bi bi-printer"></i>
          <span>Released Report</span>
        </a>
      </li>

      <li class="nav-heading">Account</li>

      <li class="nav-item">
        <a class="nav-link collapsed" href="../logout.php">
          <i class="bi bi-box-arrow-right"></i>
          <span>Logout</span>
        </a>
      </li>
    </ul>

  </aside>
