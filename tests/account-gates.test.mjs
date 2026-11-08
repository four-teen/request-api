import test from 'node:test';
import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
const root=fileURLToPath(new URL('../',import.meta.url));
function endpoint(file,options={}){
  const result=spawnSync('php',['tests/fixtures/account-gate.php',file,JSON.stringify(options)],{cwd:root,encoding:'utf8'});
  assert.equal(result.status,0,result.stderr||result.stdout);
  const match=result.stdout.match(/GATE_STATUS:(\d+)/);
  assert.ok(match,result.stdout);return{status:Number(match[1]),body:result.stdout.split('GATE_STATUS:')[0]};
}
test('regular users cannot manage accounts or program/people configuration through direct URLs',()=>{
  for(const file of ['manage_accounts.php','query_accounts.php','manage_programs.php','query_programs.php','manage_assigned_faculty.php','query_assigned.php']){
    assert.equal(endpoint(file,{method:file.startsWith('query_')?'POST':'GET',post:{action:'save'}}).status,403,file);
  }
});
test('account writes reject invalid CSRF even for an administrator',()=>{
  assert.equal(endpoint('query_accounts.php',{role:'administrator',method:'POST',post:{action:'save'},csrf:false}).status,419);
});
test('view functions cannot invoke write actions or combined action flags',()=>{
  for(const post of [{delete_the_requestor:1,id:1},{update_request:1,req_id:1},{released_requested:1,req_id:1},{request_table:1,delete_the_requestor:1}]){
    assert.equal(endpoint('query_request.php',{permissions:['requests.view'],method:'POST',post}).status,403);
  }
  assert.equal(endpoint('query_request.php',{permissions:['requests.view','requests.create'],method:'POST',post:{save_request:1,req_datetime_released:'2026-11-08 12:00:00'}}).status,403);
});
test('record log returns require their own assigned function',()=>{
  assert.equal(endpoint('query_record_logs.php',{permissions:['record_logs.view'],method:'POST',post:{action:'return',id:1,checked_by_id:1}}).status,403);
});
test('unassigned pages and unknown actions are denied before data is read or changed',()=>{
  for(const file of ['index.php','manage_request.php','record_logs.php','document_type.php','statistics_summary.php','print_summary.php','printed_summary.php'])assert.equal(endpoint(file).status,403,file);
  assert.equal(endpoint('query_request.php',{role:'administrator',method:'POST',post:{unknown:1}}).status,400);
});
