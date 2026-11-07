import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import { readFile } from 'node:fs/promises';

const script = await readFile(new URL('../assets/js/request-records.js', import.meta.url), 'utf8');

function setup() {
  let initialized = false;
  let options;
  let reloads = 0;
  const requests = [];
  const hidden = new Map();
  const texts = new Map();
  const $ = selector => ({
    DataTable(value) {
      if (value) { options = value; initialized = true; }
      return { ajax: { reload: () => reloads++ } };
    },
    prop(key, value) { hidden.set(selector, value); },
    text(value) { texts.set(selector, value); },
  });
  $.fn = { dataTable: { isDataTable: () => initialized } };
  $.ajax = request => requests.push(request);
  const window = {};
  vm.runInNewContext(script, { $, window });
  return { load: mode => window.rrmsRequestRecords.load(mode), options: () => options, requests, hidden, texts, reloads: () => reloads };
}

test('the request table asks the server for a page and preserves its search/order parameters', () => {
  const ui = setup();
  ui.load('overdue');
  const options = ui.options();
  assert.equal(options.serverSide, true);
  assert.equal(options.pageLength, 10);
  assert.equal(options.searchDelay, 350);
  assert.equal(options.columns.length, 7);
  const parameters = { draw: 1, start: 20, length: 10, search: { value: 'fixture' }, order: [{ column: 2, dir: 'asc' }] };
  options.ajax(parameters, () => {});
  assert.equal(ui.requests[0].data.request_status, 'overdue');
  assert.equal(ui.requests[0].data.start, 20);
  assert.equal(ui.requests[0].data.search.value, 'fixture');
  assert.equal(ui.requests[0].data.order[0].dir, 'asc');
});

test('a successful response draws immediately and refreshes card counts', () => {
  const ui = setup();
  ui.load('pending');
  let drawn;
  ui.options().ajax({ draw: 1 }, response => { drawn = response; });
  const response = { draw: 1, data: [{ number: '1.' }], counts: { pending_requests: 42 } };
  ui.requests[0].success(response);
  assert.equal(drawn, response);
  assert.equal(ui.texts.get('#load_requests'), '42');
  assert.equal(ui.hidden.get('#requestListError'), true);
});

test('refreshes reuse the table rather than recreating every row and control', () => {
  const ui = setup();
  ui.load('pending');
  const options = ui.options();
  ui.load('pending');
  assert.equal(ui.options(), options);
  assert.equal(ui.reloads(), 1);
});

test('released lists use the same paging controller without a remarks column', () => {
  const ui = setup();
  ui.load('released');
  assert.equal(ui.options().columns.length, 6);
  assert.equal(ui.options().columns.some(column => column.data === 'remarks'), false);
});

test('load errors stop processing through the callback and expose retry feedback', () => {
  const ui = setup();
  ui.load('overdue');
  let drawn;
  ui.options().ajax({ draw: 4 }, response => { drawn = response; });
  ui.requests[0].error();
  assert.equal(drawn.draw, 4);
  assert.equal(drawn.data.length, 0);
  assert.equal(ui.hidden.get('#requestListError'), false);
});

test('older responses cannot overwrite the latest card counts or error state', () => {
  const ui = setup();
  ui.load('overdue');
  ui.options().ajax({ draw: 1 }, () => {});
  ui.options().ajax({ draw: 2 }, () => {});
  ui.requests[1].success({ draw: 2, data: [], counts: { overdue_requests: 10 } });
  ui.requests[0].success({ draw: 1, data: [], counts: { overdue_requests: 99 } });
  ui.requests[0].error();
  assert.equal(ui.texts.get('#load_overdue'), '10');
  assert.equal(ui.hidden.get('#requestListError'), true);
});
