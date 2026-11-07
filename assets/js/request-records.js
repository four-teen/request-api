(() => {
  'use strict';

  const countFields = {
    load_requests: 'pending_requests', load_overdue: 'overdue_requests', load_doc_types: 'document_types',
    load_summary: 'released_requests', load_document_transactions: 'document_transactions',
  };

  window.rrmsRequestRecords = {
    load(mode) {
      const $table = $('#requestTable');
      if ($.fn.dataTable.isDataTable('#requestTable')) {
        $table.DataTable().ajax.reload(null, true);
        return;
      }
      const status = ['pending', 'overdue', 'released'].includes(mode) ? mode : 'pending';
      let latestDraw = 0;
      const columns = [{ data: 'number', orderable: false, searchable: false, className: 'align-top request-number-cell' }];
      if (status !== 'released') {
        columns.push({ data: 'remarks', orderable: false, searchable: false, className: 'align-top request-remarks-cell' });
      }
      columns.push(
        { data: 'requestor', className: 'align-top requestor-cell' },
        { data: 'dates', className: 'align-top request-date-cell' },
        { data: 'documents', className: 'align-top request-documents-cell' },
        { data: 'status', className: 'align-top request-status-cell' },
        { data: 'actions', orderable: false, searchable: false, className: 'align-top request-actions-cell' },
      );
      $table.DataTable({
        serverSide: true, processing: true, deferRender: true, searchDelay: 350,
        pageLength: 10, lengthMenu: [10, 25, 50, 100], lengthChange: true,
        searching: true, ordering: true, info: true, autoWidth: false, order: [], columns,
        initComplete() {
          // Keep paging/search controls stationary when unwrapped names need horizontal space.
          $(this.api().table().node()).wrap('<div class="request-records-scroll"></div>');
        },
        language: { processing: '<span class="spinner-border spinner-border-sm text-primary me-2" aria-hidden="true"></span>Loading requests...' },
        ajax(parameters, callback) {
          latestDraw = parameters.draw;
          $('#requestListError').prop('hidden', true);
          $.ajax({
            url: 'query_request.php', type: 'POST', dataType: 'json',
            data: { ...parameters, request_table: 1, request_status: status },
            success(response) {
              if (parameters.draw === latestDraw) {
                $('#requestListError').prop('hidden', true);
                for (const [id, field] of Object.entries(countFields)) {
                  if (response.counts && Number.isFinite(Number(response.counts[field]))) {
                    $('#' + id).text(Number(response.counts[field]).toLocaleString());
                  }
                }
              }
              callback(response);
            },
            error() {
              if (parameters.draw === latestDraw) $('#requestListError').prop('hidden', false);
              callback({ draw: parameters.draw, recordsTotal: 0, recordsFiltered: 0, data: [] });
            },
          });
        },
      });
    },
  };
})();
