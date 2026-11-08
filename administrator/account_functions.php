<?php
class RrmsAccountForbidden extends RuntimeException {}

function rrms_accounts_list($conn)
{
    $rows = rrms_account_rows($conn, 'SELECT a.acc_id AS id, a.username,
        COALESCE(i.inchargename, a.fullname) AS fullname, x.incharge_id AS chairId,
        x.account_role AS role, x.is_active AS active, x.session_version AS revision
        FROM tblaccounts a INNER JOIN tbl_account_access x ON x.account_id = a.acc_id
        LEFT JOIN tblincharge i ON i.inchargeid = x.incharge_id ORDER BY a.fullname, a.acc_id');
    $permissions = rrms_account_rows($conn, 'SELECT account_id, permission_key FROM tbl_account_permissions ORDER BY permission_key');
    $map = [];
    foreach ($permissions as $row) $map[(int) $row['account_id']][] = $row['permission_key'];
    foreach ($rows as &$row) {
        $row['id'] = (int) $row['id'];
        $row['chairId'] = $row['chairId'] === null ? null : (int) $row['chairId'];
        $row['active'] = (bool) $row['active'];
        $row['revision'] = (int) $row['revision'];
        $row['permissions'] = $map[$row['id']] ?? [];
    }
    unset($row);
    return $rows;
}

function rrms_account_text($input, $key, $default = '')
{
    $value = $input[$key] ?? $default;
    if (!is_string($value)) throw new InvalidArgumentException('Enter a valid ' . str_replace('_', ' ', $key) . '.');
    return $value;
}

function rrms_account_save($conn, $actor, $input)
{
    if (!rrms_user_can($actor, 'administration')) throw new RrmsAccountForbidden('Only administrators can manage accounts.');
    $rawId = rrms_account_text($input, 'id');
    $id = $rawId === '' ? null : filter_var($rawId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($id === false) throw new InvalidArgumentException('Choose a valid account.');
    $username = trim(rrms_account_text($input, 'username'));
    if (!preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]{2,29}\z/', $username)) throw new InvalidArgumentException('Username must have 3–30 letters, numbers, dots, hyphens, or underscores.');
    $role = rrms_account_text($input, 'role', 'user');
    if (!in_array($role, ['administrator', 'user'], true)) throw new InvalidArgumentException('Choose a valid account type.');
    $active = rrms_account_text($input, 'active', '1');
    if (!in_array($active, ['0', '1'], true)) throw new InvalidArgumentException('Choose a valid account status.');
    $chair = rrms_account_text($input, 'chair_id');
    $chairId = $chair === '' ? null : filter_var($chair, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($chairId === false || (!$id && !$chairId) || ($role === 'user' && !$chairId)) throw new InvalidArgumentException('Choose a person from the Program Chair list.');
    $permissions = $input['permissions'] ?? [];
    if (!is_array($permissions)) throw new InvalidArgumentException('Choose valid functions for this account.');
    foreach ($permissions as $permission) if (!is_string($permission) || !array_key_exists($permission, rrms_permission_catalog())) throw new InvalidArgumentException('An unrecognized function was selected.');
    $permissions = $role === 'administrator' ? [] : array_values(array_unique($permissions));
    foreach (rrms_permission_dependencies() as $permission => $required) {
        if (in_array($permission, $permissions, true) && !in_array($required, $permissions, true)) throw new InvalidArgumentException('Select the view function before granting its actions.');
    }
    if ($role === 'user' && !$permissions) throw new InvalidArgumentException('Select at least one function for a user account.');
    $password = rrms_account_text($input, 'password');
    if ($password !== '' || !$id) {
        if (!preg_match('/\A.{5,}\z/us', $password) || strlen($password) > 72 || trim($password) === '' || strpos($password, "\0") !== false) {
            throw new InvalidArgumentException('Use a password of at least 5 characters. If it is very long, use a shorter passphrase.');
        }
        if (!hash_equals($password, rrms_account_text($input, 'password_confirm'))) throw new InvalidArgumentException('The passwords do not match.');
    } elseif (rrms_account_text($input, 'password_confirm') !== '') throw new InvalidArgumentException('Enter both password fields, or leave both blank.');
    $revision = filter_var($input['revision'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($id && $revision === false) throw new InvalidArgumentException('Reload the account before saving.');
    $hash = $password === '' ? null : password_hash($password, PASSWORD_DEFAULT);
    mysqli_begin_transaction($conn);
    try {
        // All account mutations take these locks in the same order. This also
        // prevents two administrators from concurrently removing the last admin.
        $admins = rrms_account_rows($conn, "SELECT account_id FROM tbl_account_access
            WHERE account_role = 'administrator' AND is_active = 1 ORDER BY account_id FOR UPDATE");
        $currentActor = rrms_account_principal($conn, $actor['id']);
        if (!$currentActor || $currentActor['role'] !== 'administrator' || $currentActor['version'] !== $actor['version']) {
            throw new RrmsAccountForbidden('Your administrator session changed. Sign in again.');
        }
        $existing = $id ? rrms_account_rows($conn, 'SELECT a.acc_id, a.fullname, x.account_role, x.is_active, x.session_version, x.incharge_id
            FROM tblaccounts a INNER JOIN tbl_account_access x ON x.account_id = a.acc_id WHERE a.acc_id = ? FOR UPDATE', 'i', [$id]) : [];
        if ($id && !$existing) throw new DomainException('This account no longer exists. Reload the list.');
        if ($id && (int) $existing[0]['session_version'] !== $revision) throw new DomainException('This account was changed by another administrator. Reload it before saving.');
        if ($id === $actor['id'] && ($role !== 'administrator' || $active !== '1')) throw new DomainException('Keep your signed-in administrator account active with administrator access.');
        if ($id && $existing[0]['account_role'] === 'administrator' && (int) $existing[0]['is_active'] === 1
            && ($role !== 'administrator' || $active !== '1') && count($admins) <= 1) throw new DomainException('At least one active administrator must remain.');
        if (!$chairId && $id && $existing[0]['incharge_id'] !== null) throw new InvalidArgumentException('Keep this account linked to a program chair.');
        $person = $chairId ? rrms_account_rows($conn, 'SELECT inchargename FROM tblincharge WHERE inchargeid = ? FOR UPDATE', 'i', [$chairId]) : [];
        if ($chairId && !$person) throw new InvalidArgumentException('The selected program chair is no longer available.');
        $fullname = $person ? $person[0]['inchargename'] : $existing[0]['fullname'];
        $duplicate = rrms_account_rows($conn, 'SELECT acc_id FROM tblaccounts WHERE username = ? AND acc_id != ?', 'si', [$username, $id ?? 0]);
        if ($duplicate) throw new DomainException('That username is already in use.');
        if ($id) {
            $stmt = rrms_prepare_execute($conn, 'UPDATE tblaccounts SET username = ?, fullname = ? WHERE acc_id = ?', 'ssi', [$username, $fullname, $id]);
            if (!$stmt) throw new RuntimeException('Unable to update the account.');
            if ($hash !== null && !rrms_prepare_execute($conn, 'UPDATE tblaccounts SET password = ? WHERE acc_id = ?', 'si', [$hash, $id])) throw new RuntimeException('Unable to update the password.');
            $saved = rrms_prepare_execute($conn, 'UPDATE tbl_account_access SET incharge_id = ?, account_role = ?, is_active = ?,
                session_version = session_version + 1, updated_at = CURRENT_TIMESTAMP WHERE account_id = ?', 'isii', [$chairId, $role, (int) $active, $id]);
        } else {
            $saved = rrms_prepare_execute($conn, "INSERT INTO tblaccounts (fullname, acc_empoloyee_id, username, password) VALUES (?, '', ?, ?)", 'sss', [$fullname, $username, $hash]);
            if (!$saved) throw new RuntimeException('Unable to create the account.');
            $id = (int) mysqli_insert_id($conn);
            $saved = rrms_prepare_execute($conn, 'INSERT INTO tbl_account_access (account_id, incharge_id, account_role, is_active) VALUES (?, ?, ?, ?)', 'iisi', [$id, $chairId, $role, (int) $active]);
        }
        if (!$saved) throw new RuntimeException('Unable to save account access.');
        if (!rrms_prepare_execute($conn, 'DELETE FROM tbl_account_permissions WHERE account_id = ?', 'i', [$id])) throw new RuntimeException('Unable to update functions.');
        foreach ($permissions as $permission) if (!rrms_prepare_execute($conn, 'INSERT INTO tbl_account_permissions (account_id, permission_key) VALUES (?, ?)', 'is', [$id, $permission])) throw new RuntimeException('Unable to save functions.');
        $details = json_encode(['username' => $username, 'chair_id' => $chairId, 'role' => $role, 'active' => (bool) $active,
            'permissions' => $permissions, 'password_changed' => $hash !== null], JSON_THROW_ON_ERROR);
        if (!rrms_prepare_execute($conn, 'INSERT INTO tbl_account_audit (actor_id, account_id, action, details) VALUES (?, ?, ?, ?)', 'iiss',
            [$actor['id'], $id, $rawId === '' ? 'created' : 'updated', $details])) throw new RuntimeException('Unable to record the account change.');
        mysqli_commit($conn);
        return $id;
    } catch (Throwable $error) { mysqli_rollback($conn); throw $error; }
}
