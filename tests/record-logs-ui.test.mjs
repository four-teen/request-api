import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import vm from 'node:vm';

const script = await readFile(new URL('../assets/js/record-logs.js', import.meta.url), 'utf8');
const tick = () => new Promise(resolve => setImmediate(resolve));
const reply = (data, status = 200) => ({ ok: status < 400, status, json: async () => data });
const emptyList = { rows: [], counts: { total: 0, outstanding: 0, overdue: 0, returned: 0 }, total: 0, page: 1, pages: 1 };
const student = { request_id: 42, student_name: 'Fixture Student', course: 'Sample Program', attendance_year: '2024', contact: 'fixture contact', program_incharge_id: 3, program_incharge_name: 'Fixture Checker', open_log_id: null };

function harness(respond) {
  const elements = new Map(), timers = new Map(), calls = [], modals = [];
  let timerId = 0;
  class Element {
    constructor(tag = 'div') { this.tag = tag; this.value = ''; this.children = []; this.events = {}; this.attributes = {}; this.dataset = {}; this.hidden = false; this.disabled = false; this._text = ''; }
    set textContent(value) { this._text = String(value); this.children = []; }
    get textContent() { return this._text + this.children.map(child => child.textContent).join(''); }
    set innerHTML(value) { throw new Error('Record data must not be inserted as HTML: ' + value); }
    append(...children) { this.children.push(...children); }
    replaceChildren(...children) { this._text = ''; this.children = children; }
    setAttribute(name, value) { this.attributes[name] = value; }
    removeAttribute(name) { delete this.attributes[name]; }
    addEventListener(name, handler) { (this.events[name] ||= []).push(handler); }
    focus() { this.focused = true; }
    reset() { get('returnChecker').value = ''; get('returnRemarks').value = ''; }
    reportValidity() { return !!get('returnChecker').value; }
    async fire(name) { const event = { preventDefault() { this.prevented = true; } }; for (const handler of this.events[name] || []) await handler(event); }
  }
  const get = id => { if (!elements.has(id)) elements.set(id, new Element()); return elements.get(id); };
  get('recordLogData').textContent = JSON.stringify({ csrfToken: 'fixture-token', timezone: 'Asia/Manila' });
  const filters = ['all', 'out', 'overdue', 'returned'].map(status => { const button = new Element('button'); button.dataset.logFilter = status; return button; });
  const context = {
    window: { rrmsCan: () => true },
    document: { getElementById: get, createElement: tag => new Element(tag), querySelectorAll: () => filters, querySelector: () => null, hidden: false },
    bootstrap: { Modal: class { constructor() { this.visible = false; modals.push(this); } show() { this.visible = true; } hide() { this.visible = false; } } },
    URLSearchParams, Intl, Date,
    setInterval() {},
    setTimeout(callback) { const id = ++timerId; timers.set(id, callback); return id; },
    clearTimeout(id) { timers.delete(id); },
    fetch: async (url, options) => { const call = { url, options, query: new URL(url, 'http://fixture.local/').searchParams }; calls.push(call); return respond(call); },
  };
  vm.runInNewContext(script, context);
  return {
    get, calls, filters, modals,
    async search(text) { get('studentSearch').value = text; await get('studentSearch').fire('input'); for (const [id, callback] of [...timers]) { timers.delete(id); callback(); } await tick(); },
  };
}

test('student lookup ignores stale results, renders names as text, and blocks existing outstanding records', async () => {
  let resolveOld;
  const old = new Promise(resolve => { resolveOld = resolve; });
  const unsafeName = '<img src=x onerror=alert(1)>';
  const app = harness(call => {
    if (call.query.get('action') === 'list') return reply(emptyList);
    if (call.query.get('q') === 'Old') return old;
    return reply({ students: [{ ...student, student_name: unsafeName }, { ...student, request_id: 43, open_log_id: 6 }] });
  });
  await tick(); await app.search('Old'); await app.search('New');
  const buttons = app.get('studentResults').children;
  assert.equal(buttons.length, 2);
  assert.match(buttons[0].textContent, /<img src=x onerror=alert\(1\)>/);
  assert.equal(buttons[1].disabled, true);
  await buttons[0].fire('click');
  resolveOld(reply({ students: [{ ...student, student_name: 'Stale result' }] }));
  await tick();
  assert.equal(app.get('selectedName').textContent, unsafeName);
  assert.equal(app.get('studentResults').children.length, 0);
  assert.equal(app.get('outForm').hidden, false);
  assert.equal(app.get('selectedYear').textContent, '2024');
});

test('log-out prevents duplicate submits and preserves selected details after an interrupted save', async () => {
  let rejectSave;
  const pendingSave = new Promise((resolve, reject) => { rejectSave = reject; });
  const app = harness(call => {
    if (call.options.method === 'POST') return pendingSave;
    return reply(call.query.get('action') === 'students' ? { students: [student] } : emptyList);
  });
  await tick(); await app.search('Fixture'); await app.get('studentResults').children[0].fire('click');
  app.get('outRemarks').value = 'Preserve this note';
  const saving = app.get('outForm').fire('submit');
  await app.get('outForm').fire('submit');
  assert.equal(app.calls.filter(call => call.options.method === 'POST').length, 1);
  const save = app.calls.find(call => call.options.method === 'POST');
  assert.equal(save.options.body.get('request_id'), '42');
  assert.equal(save.options.headers['X-CSRF-Token'], 'fixture-token');
  assert.equal(save.options.body.has('logged_out_by_id'), false);
  assert.equal(save.options.body.has('logged_out_at'), false);
  assert.equal(app.get('outSubmit').disabled, true);
  rejectSave(new Error('interrupted'));
  await saving;
  assert.equal(app.get('outSubmit').disabled, false);
  assert.equal(app.get('outForm').hidden, false);
  assert.equal(app.get('outRemarks').value, 'Preserve this note');
  assert.match(app.get('outError').textContent, /Refresh the register before trying again/);
});

test('an overdue record can be returned with a listed checker and a required selection', async () => {
  const log = { id: 7, request_id: 42, student_name: 'Fixture Student', course: 'Sample Program', attendance_year: '2024', logged_out_at: '2026-11-06 10:00:00', due_date: '2026-11-17', logged_out_by_name: 'Original recorder', program_incharge_id: 3, returned_at: null, status: 'overdue' };
  let returned = false;
  const app = harness(call => {
    if (call.options.method === 'POST') { returned = true; return reply({ message: 'Return and checker recorded.' }); }
    return reply({ ...emptyList, rows: [{ ...log, ...(returned ? { returned_at: '2026-11-19 11:30:00', returned_by_name: 'Current recorder', checked_by_name: 'Fixture Checker', status: 'returned' } : {}) }], counts: { total: 1, outstanding: returned ? 0 : 1, overdue: returned ? 0 : 1, returned: returned ? 1 : 0 }, total: 1 });
  });
  await tick();
  const row = app.get('logRows').children[0];
  assert.match(row.children[7].textContent, /Overdue/);
  await row.children[8].children[0].fire('click');
  assert.equal(app.modals[0].visible, true);
  assert.equal(app.get('returnChecker').value, '3');
  app.get('returnChecker').value = '';
  await app.get('returnForm').fire('submit');
  assert.equal(app.calls.filter(call => call.options.method === 'POST').length, 0);
  app.get('returnChecker').value = '3';
  await app.get('returnForm').fire('submit');
  const save = app.calls.find(call => call.options.method === 'POST');
  assert.equal(save.options.body.get('checked_by_id'), '3');
  assert.equal(save.options.body.get('id'), '7');
  assert.equal(app.modals[0].visible, false);
  assert.match(app.get('logRows').textContent, /Returned and Checked/);
  assert.match(app.get('logRows').textContent, /Recorded by Current recorder/);
  assert.equal(app.get('count-overdue').textContent, '0');
});
