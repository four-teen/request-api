<?php
session_start();
require __DIR__ . '/../db.php';
rrms_require_permission('administration');
$rowconfig = mysqli_fetch_assoc(mysqli_query($conn, 'SELECT * FROM tblconfig LIMIT 1')) ?: [];
?>
<!doctype html>
<html lang="en"><head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>User Accounts | reQuest</title>
  <link rel="icon" href="../assets/img/request-logo.png">
  <link rel="stylesheet" href="../assets/vendor/bootstrap/css/bootstrap.min.css">
  <link rel="stylesheet" href="../assets/vendor/bootstrap-icons/bootstrap-icons.css">
  <link rel="stylesheet" href="../assets/css/style.css">
  <link rel="stylesheet" href="../assets/css/admin-theme.css">
  <link rel="stylesheet" href="../assets/css/accounts.css">
</head><body>
<?php include __DIR__ . '/header.php'; include __DIR__ . '/sidebar.php'; ?>
<main id="main" class="main account-workspace">
  <div class="pagetitle"><h1>User Accounts</h1><nav aria-label="Breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="index.php">Home</a></li><li class="breadcrumb-item active">User Accounts</li></ol></nav></div>
  <section class="account-intro"><div><p class="account-eyebrow">ADMINISTRATION</p><h2>The right access for every person.</h2><p>Link an account to a program chair, choose their functions, and manage sign-in credentials.</p></div><button class="btn btn-primary" id="newAccount"><i class="bi bi-person-plus me-2" aria-hidden="true"></i>Create account</button></section>
  <div id="accountNotice" class="alert" role="status" hidden></div>
  <section class="card"><div class="card-body pt-4">
    <div class="account-list-heading"><div><h2 class="card-title p-0 mb-2">People &amp; access</h2><p id="accountCount" class="text-muted mb-0">Loading accounts…</p></div><label class="account-search">Search accounts<input id="accountSearch" type="search" class="form-control" placeholder="Name or username"></label></div>
    <div class="table-responsive"><table class="table account-table align-middle"><thead><tr><th>Person / Program chair</th><th>Username</th><th>Account type</th><th>Functions</th><th>Status</th><th>Actions</th></tr></thead><tbody id="accountRows"></tbody></table></div>
    <button id="reloadAccounts" class="btn btn-outline-primary btn-sm">Refresh list</button>
  </div></section>
  <div class="modal fade" id="accountModal" tabindex="-1" aria-labelledby="accountModalTitle" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-scrollable"><form id="accountForm" class="modal-content" autocomplete="off">
    <div class="modal-header"><div><p class="account-eyebrow mb-1">PERSON, CREDENTIALS &amp; ACCESS</p><h2 class="modal-title fs-5" id="accountModalTitle">Create account</h2></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close account form"></button></div>
    <div class="modal-body">
      <div id="accountError" class="alert alert-danger" role="alert" hidden></div>
      <input type="hidden" id="accountId" name="id"><input type="hidden" id="accountRevision" name="revision">
      <div class="row g-3"><div class="col-12"><label for="accountChair" class="form-label">Program chair</label><select id="accountChair" name="chair_id" class="form-select" required></select><p class="account-help">From the Program Incharge list. A person can have one linked account.</p></div>
      <div class="col-sm-7"><label for="accountUsername" class="form-label">Username</label><input id="accountUsername" name="username" class="form-control" minlength="3" maxlength="30" pattern="[A-Za-z0-9][A-Za-z0-9._\-]{2,29}" autocomplete="off" required></div>
      <div class="col-sm-5"><label for="accountRole" class="form-label">Account type</label><select id="accountRole" name="role" class="form-select"><option value="user">User</option><option value="administrator">Administrator</option></select></div>
      <div class="col-sm-6"><label for="accountPassword" class="form-label" id="passwordLabel">Password</label><input id="accountPassword" name="password" type="password" class="form-control" minlength="5" maxlength="72" autocomplete="new-password"></div>
      <div class="col-sm-6"><label for="accountPasswordConfirm" class="form-label">Confirm password</label><input id="accountPasswordConfirm" name="password_confirm" type="password" class="form-control" minlength="5" maxlength="72" autocomplete="new-password"></div></div>
      <p id="accountPasswordHelp" class="account-help mt-2">Use at least 5 characters. Passwords are never shown after saving.</p>
      <div class="account-function-heading"><div><h3>Allowed functions</h3><p class="account-help mb-0">Select what this account can open and perform.</p></div><button type="button" id="selectFunctions" class="btn btn-outline-primary btn-sm">Select all</button></div>
      <p id="administratorHelp" class="alert alert-info" hidden>Administrators can use all functions and manage people, programs, and accounts.</p>
      <div id="accountFunctions" class="account-function-grid"></div>
      <div class="mt-4"><label for="accountActive" class="form-label">Account status</label><select id="accountActive" name="active" class="form-select"><option value="1">Active</option><option value="0">Disabled</option></select><p class="account-help">Disabling an account stops sign-in and ends its existing sessions.</p></div>
    </div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary" id="saveAccount">Create account</button></div>
  </form></div></div>
</main>
<?php include __DIR__ . '/footer.php'; ?>
<script src="../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/main.js"></script>
<script src="../assets/js/accounts.js"></script>
</body></html>
