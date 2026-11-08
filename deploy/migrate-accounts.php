<?php
// Explicit, additive setup. Re-running never restores roles or changes credentials.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../db.php';
$options = getopt('', ['administrator:']);
$names = $options['administrator'] ?? [];
$names = is_array($names) ? $names : [$names];
$administrators = [];
foreach ($names as $name) {
    $rows = rrms_account_rows($conn, 'SELECT acc_id FROM tblaccounts WHERE username = ?', 's', [$name]);
    if (count($rows) !== 1) throw new RuntimeException('Each --administrator must identify exactly one existing account.');
    $administrators[] = (int) $rows[0]['acc_id'];
}
$existing = mysqli_query($conn, "SHOW TABLES LIKE 'tbl_account_access'");
if (!mysqli_num_rows($existing) && !$administrators) throw new RuntimeException('Choose initial administrators with --administrator=EXISTING_USERNAME.');
$duplicates = rrms_account_rows($conn, 'SELECT username FROM tblaccounts GROUP BY username HAVING COUNT(*) > 1');
if ($duplicates) throw new RuntimeException('Duplicate account usernames must be resolved before access setup. No accounts were changed.');
$indexes = rrms_account_rows($conn, 'SHOW INDEX FROM tblaccounts');
$uniqueUsername = false;
foreach ($indexes as $index) if ($index['Key_name'] === 'account_username_unique') $uniqueUsername = true;
if (!$uniqueUsername) mysqli_query($conn, 'ALTER TABLE tblaccounts ADD UNIQUE KEY account_username_unique (username)');
// The person source supports 100 characters; preserve the entire selected name.
$columns = rrms_account_rows($conn, "SHOW COLUMNS FROM tblaccounts LIKE 'fullname'");
if (preg_match('/varchar\((\d+)\)/i', $columns[0]['Type'], $match) && (int) $match[1] < 100) {
    mysqli_query($conn, 'ALTER TABLE tblaccounts MODIFY fullname VARCHAR(100) NOT NULL');
}
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS tbl_account_access (
    account_id INT NOT NULL PRIMARY KEY,
    incharge_id INT NULL,
    account_role VARCHAR(20) NOT NULL DEFAULT 'user',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    session_version INT UNSIGNED NOT NULL DEFAULT 1,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY one_account_per_chair (incharge_id),
    CONSTRAINT account_access_account FOREIGN KEY (account_id) REFERENCES tblaccounts(acc_id) ON DELETE CASCADE,
    CONSTRAINT account_access_chair FOREIGN KEY (incharge_id) REFERENCES tblincharge(inchargeid) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS tbl_account_permissions (
    account_id INT NOT NULL,
    permission_key VARCHAR(64) NOT NULL,
    PRIMARY KEY (account_id, permission_key),
    CONSTRAINT permission_account FOREIGN KEY (account_id) REFERENCES tbl_account_access(account_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS tbl_account_audit (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    actor_id INT NOT NULL,
    account_id INT NOT NULL,
    action VARCHAR(20) NOT NULL,
    details TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
mysqli_begin_transaction($conn);
try {
    $accounts = rrms_account_rows($conn, 'SELECT acc_id FROM tblaccounts ORDER BY acc_id');
    foreach ($accounts as $account) {
        $id = (int) $account['acc_id'];
        $role = in_array($id, $administrators, true) ? 'administrator' : 'user';
        $saved = rrms_prepare_execute($conn, 'INSERT IGNORE INTO tbl_account_access (account_id, account_role) VALUES (?, ?)', 'is', [$id, $role]);
        if (!$saved) throw new RuntimeException('Unable to initialize account access.');
    }
    $activeAdmins = rrms_account_rows($conn, "SELECT account_id FROM tbl_account_access WHERE account_role = 'administrator' AND is_active = 1");
    if (!$activeAdmins) throw new RuntimeException('No active administrator exists. Resolve account access explicitly before continuing.');
    mysqli_commit($conn);
} catch (Throwable $error) { mysqli_rollback($conn); throw $error; }
echo "Account access is ready. Existing credentials and request data were preserved. Sign in again to load the new access rules.\n";
