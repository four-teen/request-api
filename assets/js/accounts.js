(() => {
  'use strict';
  const byId = id => document.getElementById(id);
  const form = byId('accountForm');
  const modalElement = byId('accountModal');
  const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
  const dependencies = {'requests.create':'requests.view','requests.edit':'requests.view','requests.release':'requests.view','requests.delete':'requests.view','record_logs.out':'record_logs.view','record_logs.return':'record_logs.view','documents.manage':'documents.view'};
  let data = null;
  let saving = false;
  let loading = false;
  function message(id, text, success = false) { const element = byId(id); element.textContent = text; element.hidden = !text; if (id === 'accountNotice') element.className = 'alert ' + (success ? 'alert-success' : 'alert-danger'); }
  async function request(options) {
    const response = await fetch('query_accounts.php', {credentials:'same-origin',cache:'no-store',...options});
    const result = await response.json().catch(() => null);
    if (!response.ok || !result) throw new Error(result?.message || 'Unable to load or save accounts. Please try again.');
    return result;
  }
  function cell(row, text, className) { const element=document.createElement('td'); element.textContent=text; if(className)element.className=className; row.append(element); return element; }
  function renderRows() {
    const tbody=byId('accountRows'); tbody.replaceChildren();
    const search=byId('accountSearch').value.trim().toLocaleLowerCase();
    const rows=data.accounts.filter(account => `${account.fullname} ${account.username}`.toLocaleLowerCase().includes(search));
    byId('accountCount').textContent=`${data.accounts.length} account${data.accounts.length===1?'':'s'} · ${data.accounts.filter(account=>account.active).length} active`;
    for(const account of rows){
      const row=document.createElement('tr');
      const person=cell(row,account.fullname,'account-person');
      const detail=document.createElement('div');detail.className='account-subtle';detail.textContent=account.id===data.currentId?'Your account':account.chairId?'Linked program chair':'Existing administrator';person.append(detail);
      cell(row,account.username);cell(row,account.role==='administrator'?'Administrator':'User');
      const functions=cell(row,account.role==='administrator'?'All functions':`${account.permissions.length} assigned`);
      functions.title=account.permissions.map(key=>data.functions[key]?.[1]||key).join(', ');
      const status=cell(row,''); const badge=document.createElement('span');badge.className='badge '+(account.active?'bg-success':'bg-secondary');badge.textContent=account.active?'Active':'Disabled';status.append(badge);
      const actions=cell(row,'');const button=document.createElement('button');button.type='button';button.className='btn btn-outline-primary btn-sm';button.textContent='Edit account';button.setAttribute('aria-label',`Edit account ${account.username}`);button.addEventListener('click',()=>openAccount(account));actions.append(button);tbody.append(row);
    }
    if(!rows.length){const row=document.createElement('tr');const empty=cell(row,search?'No matching accounts.':'No accounts available.');empty.colSpan=6;tbody.append(row);}
  }
  function functionInputs() { return [...byId('accountFunctions').querySelectorAll('input')]; }
  function renderFunctions(selected) {
    const container=byId('accountFunctions');container.replaceChildren();const groups=new Map();
    for(const [key,[group,label]] of Object.entries(data.functions)){
      if(!groups.has(group)){const fieldset=document.createElement('fieldset');fieldset.className='account-function-group';const legend=document.createElement('legend');legend.textContent=group;fieldset.append(legend);groups.set(group,fieldset);container.append(fieldset);}
      const wrapper=document.createElement('label');const checkbox=document.createElement('input');checkbox.type='checkbox';checkbox.name='permissions[]';checkbox.value=key;checkbox.checked=selected.includes(key);
      checkbox.addEventListener('change',()=>{if(checkbox.checked&&dependencies[key]){const view=functionInputs().find(input=>input.value===dependencies[key]);if(view)view.checked=true;}if(!checkbox.checked)functionInputs().filter(input=>dependencies[input.value]===key).forEach(input=>{input.checked=false;});});
      const text=document.createElement('span');text.textContent=label;wrapper.append(checkbox,text);groups.get(group).append(wrapper);
    }
  }
  function updateRole() {
    const administrator=byId('accountRole').value==='administrator';
    byId('administratorHelp').hidden=!administrator;byId('accountFunctions').hidden=administrator;byId('selectFunctions').hidden=administrator;
    functionInputs().forEach(input=>{input.disabled=administrator;});
  }
  function openAccount(account = null) {
    if(saving||!data)return;
    form.reset();message('accountError','');
    byId('accountId').value=account?.id||'';byId('accountRevision').value=account?.revision||'';
    byId('accountUsername').value=account?.username||'';byId('accountRole').value=account?.role||'user';byId('accountActive').value=account?.active===false?'0':'1';
    const self=account?.id===data.currentId;byId('accountRole').disabled=self;byId('accountActive').disabled=self;
    const select=byId('accountChair');select.replaceChildren();
    const placeholder=document.createElement('option');placeholder.value='';placeholder.textContent=account&&!account.chairId?'Existing administrator — no chair link':'Choose a program chair';select.append(placeholder);
    const occupied=new Set(data.accounts.filter(item=>item.id!==account?.id&&item.chairId).map(item=>Number(item.chairId)));
    for(const chair of data.chairs){const option=document.createElement('option');option.value=chair.id;option.textContent=chair.name+(occupied.has(Number(chair.id))?' — account already linked':'');option.disabled=occupied.has(Number(chair.id));select.append(option);}
    select.value=account?.chairId||'';select.required=!account||!!account.chairId||account.role!=='administrator';
    byId('accountPassword').required=!account;byId('accountPasswordConfirm').required=!account;
    byId('passwordLabel').textContent=account?'New password':'Password';byId('accountPasswordHelp').textContent=account?'Leave both password fields blank to keep the password. Saving credentials or access ends other sessions.':'Use at least 5 characters. Passwords are never shown after saving.';
    byId('accountModalTitle').textContent=account?(self?'Edit your administrator account':'Edit account'):'Create account';byId('saveAccount').textContent=account?'Save changes':'Create account';
    renderFunctions(account?.permissions||[]);updateRole();modal.show();
  }
  async function load() {
    if(loading||saving)return;loading=true;byId('newAccount').disabled=true;byId('reloadAccounts').disabled=true;
    try{data=await request();renderRows();message('accountNotice','');}
    catch(error){message('accountNotice',error.message);byId('accountCount').textContent='Accounts could not be loaded.';}
    finally{loading=false;byId('newAccount').disabled=!data;byId('reloadAccounts').disabled=false;}
  }
  form.addEventListener('submit',async event=>{
    event.preventDefault();if(saving||!form.reportValidity())return;
    const fields=new URLSearchParams(new FormData(form));fields.set('action','save');fields.set('csrf_token',window.RRMS_CSRF_TOKEN||'');
    // Disabled controls for the signed-in administrator remain explicit in the request.
    fields.set('role',byId('accountRole').value);fields.set('active',byId('accountActive').value);
    saving=true;form.setAttribute('aria-busy','true');byId('saveAccount').disabled=true;message('accountError','');
    try{const result=await request({method:'POST',body:fields});window.RRMS_CSRF_TOKEN=result.csrfToken;
      if(result.selfUpdated){window.location.reload();return;}
      saving=false;modal.hide();await load();message('accountNotice',result.message,true);
    }catch(error){message('accountError',error.message);}
    finally{saving=false;form.removeAttribute('aria-busy');byId('saveAccount').disabled=false;byId('accountPassword').value='';byId('accountPasswordConfirm').value='';}
  });
  modalElement.addEventListener('hide.bs.modal',event=>{if(saving)event.preventDefault();});
  modalElement.addEventListener('hidden.bs.modal',()=>{byId('accountPassword').value='';byId('accountPasswordConfirm').value='';});
  byId('newAccount').addEventListener('click',()=>openAccount());byId('reloadAccounts').addEventListener('click',load);byId('accountSearch').addEventListener('input',()=>{if(data)renderRows();});
  byId('accountRole').addEventListener('change',()=>{byId('accountChair').required=byId('accountRole').value!=='administrator'||!byId('accountId').value||!!byId('accountChair').value;updateRole();});
  byId('selectFunctions').addEventListener('click',()=>functionInputs().forEach(input=>{input.checked=true;}));
  load();
})();
