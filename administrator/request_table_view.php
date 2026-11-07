<div id="requestListError" class="alert alert-warning" role="alert" hidden>
  Unable to load requests. Please try again.
  <button type="button" class="btn btn-outline-primary btn-sm ms-2" onclick="get_req()">Retry</button>
</div>
<table id="requestTable" class="table table-sm table-striped request-records-table">
  <thead>
    <tr>
      <th class="request-number-cell">#</th>
      <?php if (($requestTableMode ?? '') !== 'released'): ?><th class="request-remarks-cell" aria-label="Remarks"></th><?php endif; ?>
      <th class="requestor-cell">REQUESTOR</th>
      <th class="request-date-cell">REQUEST DATES</th>
      <th class="request-documents-cell">REQUEST</th>
      <th class="request-status-cell">STATUS</th>
      <th aria-label="Actions"></th>
    </tr>
  </thead>
  <tbody></tbody>
</table>
