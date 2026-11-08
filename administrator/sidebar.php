<?php
$currentPage = basename($_SERVER['PHP_SELF'] ?? '');
$sidebarSections = [
    'Overview' => [['index.php', 'Dashboard', 'bi-grid', 'dashboard.view']],
    'Operations' => [
        ['manage_request.php', 'Manage Requests', 'bi-inboxes', 'requests.view'],
        ['overdue_requests.php', 'Overdue Requests', 'bi-exclamation-circle', 'requests.view'],
        ['record_logs.php', 'Student Record Logs', 'bi-journal-arrow-up', 'record_logs.view'],
        ['document_type.php', 'Document Types', 'bi-files', 'documents.view'],
        ['released_summary.php', 'Released Summary', 'bi-check2-circle', 'requests.view'],
        ['statistics_summary.php', 'Statistics', 'bi-bar-chart', 'statistics.view'],
    ],
    'Management' => [
        ['manage_accounts.php', 'User Accounts', 'bi-people', 'administration'],
        ['manage_programs.php', 'Degree / Program', 'bi-mortarboard', 'administration'],
        ['manage_assigned_faculty.php', 'Program Incharge', 'bi-person-badge', 'administration'],
    ],
    'Reports' => [['print_summary.php', 'Released Report', 'bi-printer', 'reports.view']],
];
?>
<aside id="sidebar" class="sidebar"><ul class="sidebar-nav" id="sidebar-nav">
<?php foreach ($sidebarSections as $heading => $items):
    $visible = array_filter($items, function ($item) { return rrms_can($item[3]); });
    if (!$visible) continue;
?>
  <li class="nav-heading"><?php echo rrms_h($heading); ?></li>
  <?php foreach ($visible as [$page, $label, $icon, $permission]):
    $active = $currentPage === $page || ($page === 'print_summary.php' && $currentPage === 'printed_summary.php');
  ?>
  <li class="nav-item"><a class="nav-link<?php echo $active ? '' : ' collapsed'; ?>" href="<?php echo rrms_h($page); ?>"><i class="bi <?php echo rrms_h($icon); ?>" aria-hidden="true"></i><span><?php echo rrms_h($label); ?></span></a></li>
  <?php endforeach; ?>
<?php endforeach; ?>
  <li class="nav-heading">Account</li>
  <li class="nav-item"><a class="nav-link collapsed" href="../logout.php"><i class="bi bi-box-arrow-right" aria-hidden="true"></i><span>Logout</span></a></li>
</ul></aside>
