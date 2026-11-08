(() => {
  const pages = {
    'index.php':'dashboard.view','dashboard':'dashboard.view',
    'manage_request.php':'requests.view','requests':'requests.view','overdue_requests.php':'requests.view','overdue-requests':'requests.view',
    'released_summary.php':'requests.view','released-requests':'requests.view',
    'record_logs.php':'record_logs.view','record-logs':'record_logs.view','document_type.php':'documents.view','document-types':'documents.view',
    'statistics_summary.php':'statistics.view','statistics':'statistics.view','print_summary.php':'reports.view','reports':'reports.view',
    'manage_accounts.php':'administration','users':'administration','manage_programs.php':'administration','programs':'administration',
    'manage_assigned_faculty.php':'administration','program-incharge':'administration',
  };
  function apply() {
    for (const element of document.querySelectorAll('[data-permission]')) {
      if (!window.rrmsCan(element.dataset.permission)) element.setAttribute('data-access-denied','');
    }
    for (const link of document.querySelectorAll('a[href]')) {
      const url = new URL(link.getAttribute('href'), document.baseURI);
      if (url.origin !== window.location.origin || url.hash) continue;
      const permission = pages[url.pathname.split('/').pop()];
      if (permission && !window.rrmsCan(permission)) {
        link.removeAttribute('href'); link.setAttribute('aria-disabled','true'); link.tabIndex=-1;
      }
    }
  }
  function start() { apply(); new MutationObserver(apply).observe(document.body,{subtree:true,childList:true}); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded',start,{once:true}); else start();
})();
