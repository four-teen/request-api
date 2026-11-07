(() => {
  'use strict';
  const config = JSON.parse(document.getElementById('recordLogData').textContent);
  const byId = id => document.getElementById(id);
  const endpoint = 'query_record_logs.php';
  const dateFormat = new Intl.DateTimeFormat('en-US', { month: 'short', day: '2-digit', year: 'numeric', timeZone: 'UTC' });
  let selected = null, returning = null, savingOut = false, savingReturn = false;
  let page = 1, pages = 1, status = 'all', searchVersion = 0, listVersion = 0, searchTimer, listTimer;
  let listRows = new Map();
  const returnModal = new bootstrap.Modal(byId('returnModal'));

  function node(tag, text, className) {
    const element = document.createElement(tag);
    if (text != null) element.textContent = text;
    if (className) element.className = className;
    return element;
  }
  function setMessage(id, text) { byId(id).textContent = text; byId(id).hidden = !text; }
  function notice(text) { byId('logNotice').className = 'alert alert-success'; setMessage('logNotice', text); }
  function date(value) { return value ? dateFormat.format(new Date(value.slice(0, 10) + 'T12:00:00Z')) : '—'; }
  function time(value) {
    // Stored timestamps use the configured PHP timezone. Parse their components without assuming the browser timezone.
    return value ? value.slice(11, 16).replace(/^(\d{2}):(\d{2})$/, (_, h, m) => `${Number(h) % 12 || 12}:${m} ${Number(h) < 12 ? 'AM' : 'PM'}`) : '';
  }
  async function request(params, post = false) {
    const options = { credentials: 'same-origin', cache: 'no-store' };
    const fields = new URLSearchParams(params);
    if (post) { options.method = 'POST'; options.body = fields; options.headers = { 'X-CSRF-Token': config.csrfToken }; }
    let response;
    try { response = await fetch(post ? endpoint : `${endpoint}?${fields}`, options); }
    catch { throw new Error(post ? 'The connection was interrupted. Refresh the register before trying again.' : 'Unable to connect. Check your connection and try again.'); }
    const data = await response.json().catch(() => null);
    if (!response.ok || !data) throw new Error(data?.message || 'Unable to load or save record logs. Please try again.');
    return data;
  }
  function resetStudent(focus = false) {
    selected = null;
    byId('outForm').hidden = true;
    byId('studentPlaceholder').hidden = false;
    byId('studentSearch').value = '';
    byId('studentResults').replaceChildren();
    byId('outRemarks').value = '';
    setMessage('outError', '');
    setMessage('studentSearchStatus', 'Enter at least 2 characters to search.');
    searchVersion++;
    if (focus) byId('studentSearch').focus();
  }
  function selectStudent(student) {
    selected = student;
    searchVersion++;
    byId('studentResults').replaceChildren();
    byId('studentSearch').value = student.student_name;
    byId('studentPlaceholder').hidden = true;
    byId('outForm').hidden = false;
    byId('outSubmit').disabled = false;
    for (const [id, value] of Object.entries({ selectedName: student.student_name, selectedCourse: student.course,
      selectedYear: student.attendance_year || 'Not recorded', selectedContact: student.contact || 'Not recorded',
      selectedIncharge: student.program_incharge_name || 'Not assigned', selectedSource: `From encoded request #${student.request_id}` })) byId(id).textContent = value;
    setMessage('studentSearchStatus', 'Student selected. Review the details before logging out.');
    setMessage('outError', '');
    byId('outRemarks').focus();
  }
  async function searchStudents(version, query) {
    try {
      const data = await request({ action: 'students', q: query });
      if (version !== searchVersion) return;
      byId('studentResults').replaceChildren();
      setMessage('studentSearchStatus', data.students.length ? `${data.students.length} matching student${data.students.length === 1 ? '' : 's'}. Select the correct student and program.` : 'No existing student request found. Encode a request in Manage Requests first.');
      for (const student of data.students) {
        const button = node('button', null, 'log-student-result');
        button.type = 'button';
        const details = node('span');
        details.append(node('strong', student.student_name), node('small', student.course), node('small', `Last attendance: ${student.attendance_year || 'Not recorded'} · Request #${student.request_id}`));
        button.append(details, node('span', student.open_log_id ? 'Already outstanding' : 'Select →'));
        if (student.open_log_id) button.disabled = true;
        else button.addEventListener('click', () => selectStudent(student));
        byId('studentResults').append(button);
      }
    } catch (error) { if (version === searchVersion) setMessage('studentSearchStatus', error.message); }
  }
  byId('studentSearch').addEventListener('input', () => {
    if (savingOut) return;
    selected = null;
    byId('outForm').hidden = true;
    byId('studentPlaceholder').hidden = false;
    byId('studentResults').replaceChildren();
    const query = byId('studentSearch').value.trim();
    const version = ++searchVersion;
    clearTimeout(searchTimer);
    if (query.length < 2) return setMessage('studentSearchStatus', 'Enter at least 2 characters to search.');
    setMessage('studentSearchStatus', 'Searching existing requests…');
    searchTimer = setTimeout(() => searchStudents(version, query), 250);
  });
  byId('changeStudent').addEventListener('click', () => { if (!savingOut) resetStudent(true); });
  byId('outForm').addEventListener('submit', async event => {
    event.preventDefault();
    if (!selected || savingOut) return;
    savingOut = true;
    byId('outSubmit').disabled = true;
    byId('studentSearch').disabled = true;
    byId('changeStudent').disabled = true;
    setMessage('outError', '');
    try {
      const data = await request({ action: 'out', request_id: selected.request_id, remarks: byId('outRemarks').value }, true);
      resetStudent(); page = 1; status = 'all'; byId('registerSearch').value = '';
      updateFilters(); notice(data.message); await loadLogs();
    } catch (error) { setMessage('outError', error.message); }
    finally { savingOut = false; byId('outSubmit').disabled = false; byId('studentSearch').disabled = false; byId('changeStudent').disabled = false; }
  });

  function appendDate(cell, value) {
    cell.append(node('span', date(value), 'log-date'));
    if (value) cell.append(node('small', time(value)));
  }
  function renderRows(rows) {
    byId('logRows').replaceChildren();
    listRows = new Map(rows.map(row => [String(row.id), row]));
    if (!rows.length) {
      const tr = node('tr'), td = node('td', byId('registerSearch').value || status !== 'all' ? 'No logs match this search or filter.' : 'No record movements yet. Search for a student above to create the first log.', 'log-empty');
      td.colSpan = 9; tr.append(td); byId('logRows').append(tr); return;
    }
    for (const row of rows) {
      const tr = node('tr');
      const cells = Array.from({ length: 9 }, () => node('td'));
      appendDate(cells[0], row.logged_out_at);
      cells[0].append(node('small', `Due ${date(row.due_date)}`));
      cells[1].append(node('span', row.student_name, 'log-student-name'), node('small', `Log #${row.id} · Request #${row.request_id}`));
      cells[2].className = 'log-course-cell'; cells[2].textContent = row.course;
      cells[3].textContent = row.attendance_year || '—';
      cells[4].textContent = row.logged_out_by_name;
      appendDate(cells[5], row.returned_at);
      if (row.returned_at) cells[5].append(node('small', `Recorded by ${row.returned_by_name}`));
      cells[6].textContent = row.checked_by_name || '—';
      cells[7].className = 'log-remarks-cell';
      const label = { out: 'Outstanding', overdue: 'Overdue', returned: 'Returned and Checked' }[row.status];
      cells[7].append(node('span', label, `log-status log-status-${row.status}`));
      if (row.out_remarks) cells[7].append(node('small', `Out: ${row.out_remarks}`));
      if (row.return_remarks) cells[7].append(node('small', `Return: ${row.return_remarks}`));
      if (!row.returned_at) {
        const button = node('button', 'Record return', 'btn btn-outline-primary btn-sm');
        button.type = 'button'; button.setAttribute('aria-label', `Record return for ${row.student_name}`);
        button.addEventListener('click', () => openReturn(String(row.id))); cells[8].append(button);
      } else cells[8].append(node('small', 'Completed'));
      tr.append(...cells); byId('logRows').append(tr);
    }
  }
  function updateFilters() {
    document.querySelectorAll('[data-log-filter]').forEach(button => button.setAttribute('aria-pressed', String(button.dataset.logFilter === status)));
  }
  async function loadLogs() {
    const version = ++listVersion;
    byId('refreshLogs').disabled = true;
    byId('logRows').setAttribute('aria-busy', 'true');
    try {
      const data = await request({ action: 'list', status, page, q: byId('registerSearch').value.trim() });
      if (version !== listVersion) return;
      page = data.page; pages = data.pages;
      renderRows(data.rows);
      for (const [key, value] of Object.entries(data.counts)) byId(`count-${key}`).textContent = value.toLocaleString();
      byId('logPageInfo').textContent = data.total ? `${(page - 1) * 10 + 1}–${Math.min(page * 10, data.total)} of ${data.total} logs` : '0 logs';
      byId('previousLogs').disabled = page <= 1; byId('nextLogs').disabled = page >= pages;
      setMessage('registerError', '');
    } catch (error) {
      if (version !== listVersion) return;
      setMessage('registerError', error.message);
      if (!listRows.size) { byId('logRows').replaceChildren(); const tr = node('tr'), td = node('td', 'Record logs could not be loaded. Use Refresh to try again.', 'log-empty'); td.colSpan = 9; tr.append(td); byId('logRows').append(tr); }
    } finally {
      if (version === listVersion) { byId('refreshLogs').disabled = false; byId('logRows').removeAttribute('aria-busy'); }
    }
  }
  document.querySelectorAll('[data-log-filter]').forEach(button => button.addEventListener('click', () => {
    status = button.dataset.logFilter; page = 1; updateFilters(); loadLogs();
  }));
  byId('registerSearch').addEventListener('input', () => { clearTimeout(listTimer); ++listVersion; listTimer = setTimeout(() => { page = 1; loadLogs(); }, 250); });
  byId('refreshLogs').addEventListener('click', loadLogs);
  byId('previousLogs').addEventListener('click', () => { if (page > 1) { page--; loadLogs(); } });
  byId('nextLogs').addEventListener('click', () => { if (page < pages) { page++; loadLogs(); } });

  function openReturn(id) {
    returning = listRows.get(id);
    if (!returning || returning.returned_at) return;
    byId('returnForm').reset(); setMessage('returnError', '');
    byId('returnStudent').textContent = returning.student_name;
    byId('returnCourse').textContent = returning.course;
    byId('returnDue').textContent = `Log #${returning.id} · Out ${date(returning.logged_out_at)} · Due ${date(returning.due_date)}`;
    byId('returnChecker').value = String(returning.program_incharge_id || '');
    returnModal.show();
  }
  byId('returnModal').addEventListener('shown.bs.modal', () => byId('returnChecker').focus());
  byId('returnModal').addEventListener('hide.bs.modal', event => { if (savingReturn) event.preventDefault(); });
  byId('returnForm').addEventListener('submit', async event => {
    event.preventDefault();
    if (savingReturn || !returning || !byId('returnForm').reportValidity()) return;
    savingReturn = true; byId('returnSubmit').disabled = true; setMessage('returnError', '');
    try {
      const data = await request({ action: 'return', id: returning.id, checked_by_id: byId('returnChecker').value, remarks: byId('returnRemarks').value }, true);
      savingReturn = false; returnModal.hide(); returning = null; notice(data.message); await loadLogs();
      // Clear stale student choices so returned records can be selected again.
      if (!selected) resetStudent();
    } catch (error) { setMessage('returnError', error.message); }
    finally { savingReturn = false; byId('returnSubmit').disabled = false; }
  });
  document.querySelector('.toggle-sidebar-btn')?.addEventListener('click', () => document.body.classList.toggle('toggle-sidebar'));
  document.querySelector('.search-bar-toggle')?.addEventListener('click', () => document.querySelector('.search-bar')?.classList.toggle('search-bar-show'));
  // Preview only; the backend always calculates and saves the authoritative date/time and deadline.
  function updateDuePreview() {
    const parts = new Intl.DateTimeFormat('en-CA', { timeZone: config.timezone, year: 'numeric', month: '2-digit', day: '2-digit' }).formatToParts(new Date());
    const value = type => parts.find(part => part.type === type).value;
    const due = new Date(Date.UTC(Number(value('year')), Number(value('month')) - 1, Number(value('day')), 12));
    for (let count = 0; count < 7;) { due.setUTCDate(due.getUTCDate() + 1); if (due.getUTCDay() !== 0 && due.getUTCDay() !== 6) count++; }
    byId('duePreview').textContent = dateFormat.format(due);
  }
  updateDuePreview(); loadLogs();
  setInterval(() => { if (!document.hidden && !savingOut && !savingReturn) { updateDuePreview(); loadLogs(); } }, 60000);
})();
