<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../db.php';
require __DIR__ . '/../administrator/account_functions.php';
require __DIR__ . '/../administrator/request_status.php';
require __DIR__ . '/../administrator/request_documents.php';
require __DIR__ . '/../administrator/request_table.php';
function account_assert($condition, $message) { if (!$condition) throw new RuntimeException($message); }
function account_throws($class, $callback) {
    try { $callback(); } catch (Throwable $error) { account_assert($error instanceof $class, 'Expected ' . $class . ', got ' . get_class($error) . ': ' . $error->getMessage()); return; }
    throw new RuntimeException('Expected ' . $class . '.');
}
// All writes shadow the real tables only within this connection.
foreach (['tblaccounts', 'tblincharge', 'tbl_account_access', 'tbl_account_permissions', 'tbl_account_audit'] as $table) {
    $definition = mysqli_fetch_assoc(mysqli_query($conn, "SHOW CREATE TABLE $table"))['Create Table'];
    $definition = preg_replace('/^\s*CONSTRAINT[^\r\n]*[\r\n]+/m', '', $definition);
    $definition = preg_replace('/,\s*\)/', "\n)", $definition);
    mysqli_query($conn, preg_replace('/^CREATE TABLE /', 'CREATE TEMPORARY TABLE ', $definition));
}
mysqli_query($conn, "INSERT INTO tblincharge(inchargeid,inchargename) VALUES (9001,'Fixture Chair One'),(9002,'Fixture Chair Two'),(9003,'Fixture Chair Three')");
$originalPassword = 'abcde';
$fixtureHash = password_hash($originalPassword, PASSWORD_DEFAULT);
foreach ([9001 => 'fixture_admin', 9002 => 'fixture_admin_two'] as $id => $name) {
    rrms_prepare_execute($conn, "INSERT INTO tblaccounts(acc_id,fullname,acc_empoloyee_id,username,password) VALUES (?,'Fixture Administrator','',?,?)", 'iss', [$id, $name, $fixtureHash]);
    rrms_prepare_execute($conn, "INSERT INTO tbl_account_access(account_id,account_role) VALUES (?,'administrator')", 'i', [$id]);
}
$actor = rrms_account_principal($conn, 9001);
$secondActor = rrms_account_principal($conn, 9002);
$input = ['id'=>'','username'=>'fixture_user','chair_id'=>'9001','role'=>'user','active'=>'1','permissions'=>['requests.view'],
    'password'=>$originalPassword,'password_confirm'=>$originalPassword];
$id = rrms_account_save($conn, $actor, $input);
$user = rrms_account_principal($conn, $id);
account_assert($user['fullname'] === 'Fixture Chair One' && $user['chairId'] === 9001, 'The selected person must supply the account identity.');
account_assert(rrms_user_can($user, 'requests.view') && !rrms_user_can($user, 'requests.edit') && !rrms_user_can($user, 'administration'), 'A user received unselected functions.');
account_assert(rrms_user_workspace($user) === '/requests', 'A restricted user must land on an allowed page.');
$password = rrms_account_rows($conn, 'SELECT password FROM tblaccounts WHERE acc_id = ?', 'i', [$id])[0]['password'];
account_assert($password !== $originalPassword && password_verify($originalPassword, $password), 'Passwords must be hashed.');
$listing = json_encode(rrms_accounts_list($conn));
account_assert(strpos($listing, $password) === false && strpos($listing, 'password') === false, 'The account API exposed a password or hash.');
account_throws(RrmsAccountForbidden::class, function () use ($conn, $user, $input) { rrms_account_save($conn, $user, array_merge($input, ['role'=>'administrator'])); });
account_throws(InvalidArgumentException::class, function () use ($conn, $actor, $input) { rrms_account_save($conn, $actor, array_merge($input, ['permissions'=>['administration']])); });
account_throws(InvalidArgumentException::class, function () use ($conn, $actor, $input) { rrms_account_save($conn, $actor, array_merge($input, ['permissions'=>['requests.delete']])); });
account_throws(InvalidArgumentException::class, function () use ($conn, $actor, $input) { rrms_account_save($conn, $actor, array_merge($input, ['chair_id'=>'999999'])); });
account_throws(InvalidArgumentException::class, function () use ($conn, $actor, $input) { rrms_account_save($conn, $actor, array_merge($input, ['password'=>'abcd','password_confirm'=>'abcd'])); });
account_throws(InvalidArgumentException::class, function () use ($conn, $actor, $input) { rrms_account_save($conn, $actor, array_merge($input, ['username'=>['invalid']])); });
$count = count(rrms_accounts_list($conn));
account_throws(DomainException::class, function () use ($conn, $actor, $input) { rrms_account_save($conn, $actor, array_merge($input, ['chair_id'=>'9002'])); });
account_throws(mysqli_sql_exception::class, function () use ($conn, $actor, $input) { rrms_account_save($conn, $actor, array_merge($input, ['username'=>'duplicate_chair'])); });
account_assert(count(rrms_accounts_list($conn)) === $count, 'A failed save left an orphan account.');
$_SESSION = ['acc_id'=>$id,'auth_version'=>$user['version']];
account_assert(rrms_session_user($conn) !== null, 'A valid session was rejected.');
$changed = array_merge($input, ['id'=>(string)$id,'revision'=>(string)$user['version'],'username'=>'renamed_user','password'=>'abcdef','password_confirm'=>'abcdef']);
rrms_account_save($conn, $actor, $changed);
account_assert(rrms_session_user($conn) === null, 'Changing credentials must invalidate old sessions.');
$password = rrms_account_rows($conn, 'SELECT password FROM tblaccounts WHERE acc_id = ?', 'i', [$id])[0]['password'];
account_assert(password_verify($changed['password'], $password) && !password_verify($originalPassword, $password), 'Password renewal did not replace the old credential.');
account_throws(DomainException::class, function () use ($conn, $actor, $changed) { rrms_account_save($conn, $actor, $changed); });
$user = rrms_account_principal($conn, $id);
$changed = array_merge($changed, ['revision'=>(string)$user['version'],'password'=>'','password_confirm'=>'','permissions'=>['record_logs.view']]);
rrms_account_save($conn, $actor, $changed);
account_assert(rrms_account_rows($conn, 'SELECT password FROM tblaccounts WHERE acc_id = ?', 'i', [$id])[0]['password'] === $password, 'Blank password fields must preserve the hash.');
$user = rrms_account_principal($conn, $id);
account_assert(!rrms_user_can($user, 'requests.view') && rrms_user_workspace($user) === '/record-logs', 'Removed functions remained available.');
$changed['revision'] = (string)$user['version']; $changed['active']='0';
rrms_account_save($conn, $actor, $changed);
account_assert(rrms_account_principal($conn, $id) === null, 'Disabled accounts must not authenticate.');
$changed['revision']=(string)($user['version']+1);$changed['active']='1';
rrms_account_save($conn, $actor, $changed);
account_assert(rrms_account_principal($conn, $id) !== null, 'An account could not be reactivated.');
$self = ['id'=>'9001','revision'=>'1','username'=>'fixture_admin_renamed','chair_id'=>'','role'=>'administrator','active'=>'1','password'=>'Self renewed password 789!','password_confirm'=>'Self renewed password 789!'];
rrms_account_save($conn, $actor, $self);
$actor = rrms_account_principal($conn, 9001);
account_assert($actor['username'] === 'fixture_admin_renamed' && $actor['version'] === 2, 'Administrator self-edit did not update credentials.');
account_throws(DomainException::class, function () use ($conn, $actor, $self) { rrms_account_save($conn, $actor, array_merge($self,['revision'=>'2','active'=>'0'])); });
$demote = ['id'=>'9002','revision'=>'1','username'=>'fixture_admin_two','chair_id'=>'9002','role'=>'user','active'=>'1','permissions'=>['requests.view'],'password'=>'','password_confirm'=>''];
rrms_account_save($conn, $actor, $demote);
account_throws(RrmsAccountForbidden::class, function () use ($conn,$secondActor,$input){rrms_account_save($conn,$secondActor,array_merge($input,['username'=>'stale_admin_attempt','chair_id'=>'9003']));});
account_throws(DomainException::class, function () use ($conn,$actor,$self){rrms_account_save($conn,$actor,array_merge($self,['revision'=>'2','role'=>'user','chair_id'=>'9003','permissions'=>['requests.view']]));});
$audit = json_encode(rrms_account_rows($conn,'SELECT details FROM tbl_account_audit'));
foreach ([$originalPassword,$fixtureHash,$password,$self['password']] as $secret) account_assert(strpos($audit,$secret) === false,'An audit entry exposed a password or hash.');
account_assert(rrms_request_action_permissions(['request_table'=>1,'delete_the_requestor'=>1]) === ['requests.view','requests.delete'],'Combined parameters must require every function.');
account_assert(rrms_request_action_permissions(['unknown'=>1]) === [], 'Unknown actions must not obtain access.');
$_SESSION = ['acc_id'=>9001];
account_assert(rrms_session_user($conn) === null, 'Pre-migration sessions must sign in again.');
$GLOBALS['rrms_authenticated_user'] = rrms_account_principal($conn,9002);
$fixture = ['req_id'=>1,'req_lastname'=>'Fixture','req_firstname'=>'Student','req_middlename'=>'','req_contact_number'=>'','req_datetime_request'=>'','req_due_date'=>null];
$row = rrms_request_table_row($fixture,1,[],false,'pending',date('Y-m-d'));
account_assert(substr_count($row['actions'],'<button') === 1 && strpos($row['actions'],'Delete') === false,'A view-only user was shown write actions.');
echo "PASS: chair-linked creation, permission denial, hashed credentials, administrator self-edit, renewal/revocation, duplicate rollback, stale edits, last-administrator protection, and private audit/list responses.\n";
