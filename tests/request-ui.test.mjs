import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import vm from 'node:vm';

const page = await readFile(new URL('../administrator/manage_request.php', import.meta.url), 'utf8');
const saveFunction = page.slice(page.indexOf('function saving_req() {'), page.indexOf('function show_request_entry()'));

// Isolated UI fixtures: these tests never connect to the API or change records.
function setup(overrides = {}) {
  const values = {
    '#req_lastname': 'Fixture', '#req_firstname': 'Request', '#req_middlename': 'Test',
    '#req_ext': '', "input[name='req_namechange']:checked": 'No', '#req_formername': '',
    '#req_year_attendance': '2025', '#req_sem_midyear': '1st Semester', '#req_acad_year': '2025-2026',
    '#req_program': '1', '#req_contact_number': '09123456789',
    '#req_datetime_request': '2026-11-06T08:00', '#req_due_date': '2026-11-13',
    '#req_datetime_released': '', '#date_processed': '2026-11-06T08:00', '#prog_assigned': '1',
    ...overrides,
  };
  const button = { disabled: false, setAttribute() {}, removeAttribute() {} };
  const requests = [];
  const alerts = [];
  let hidden = 0;
  let refreshed = 0;
  const $ = selector => ({ val: () => values[selector] });
  $.ajax = options => requests.push(options);
  const context = vm.createContext({
    $, document: { getElementById: () => button }, Swal: { fire: alert => alerts.push(alert) },
    bootstrap: { Modal: { getOrCreateInstance: () => ({ hide: () => hidden++ }) } },
    get_req: () => refreshed++,
  });
  vm.runInContext(saveFunction, context);
  return { save: () => context.saving_req(), button, requests, alerts, values, hidden: () => hidden, refreshed: () => refreshed };
}

test('missing information keeps the modal open without submitting', () => {
  const ui = setup({ '#req_lastname': '' });
  ui.save();
  assert.equal(ui.requests.length, 0);
  assert.equal(ui.hidden(), 0);
  assert.equal(ui.button.disabled, false);
  assert.equal(ui.alerts[0].icon, 'warning');
});

test('invalid contact numbers do not submit or dismiss the modal', () => {
  const ui = setup({ '#req_contact_number': 'invalid' });
  ui.save();
  assert.equal(ui.requests.length, 0);
  assert.equal(ui.hidden(), 0);
  assert.equal(ui.alerts[0].icon, 'warning');
});

test('pending saves block duplicate submissions', () => {
  const ui = setup();
  ui.save();
  ui.save();
  assert.equal(ui.requests.length, 1);
  assert.equal(ui.button.disabled, true);
  assert.equal(ui.hidden(), 0);
});

test('only a confirmed successful save dismisses the modal and refreshes the list', () => {
  const ui = setup();
  ui.save();
  assert.equal(ui.hidden(), 0);
  ui.requests[0].success('success\n');
  ui.requests[0].complete();
  assert.equal(ui.hidden(), 1);
  assert.equal(ui.refreshed(), 1);
  assert.equal(ui.button.disabled, false);
});

test('a rejected save preserves the form and allows a retry', () => {
  const ui = setup();
  ui.save();
  ui.requests[0].success('error');
  ui.requests[0].complete();
  assert.equal(ui.hidden(), 0);
  assert.equal(ui.refreshed(), 0);
  assert.equal(ui.button.disabled, false);
  assert.equal(ui.values['#req_lastname'], 'Fixture');
  assert.equal(ui.alerts[0].icon, 'error');
});

test('connection failures keep the modal open and flag the uncertain result', () => {
  const ui = setup();
  ui.save();
  ui.requests[0].error();
  ui.requests[0].complete();
  assert.equal(ui.hidden(), 0);
  assert.equal(ui.button.disabled, false);
  assert.match(ui.alerts[0].text, /Check the request list/);
});
