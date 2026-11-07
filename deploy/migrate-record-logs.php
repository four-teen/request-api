<?php
// Run once per environment using PHP CLI. Existing request tables and rows are preserved.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../db.php';
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS tbl_record_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL,
    student_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    active_student_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
    student_name VARCHAR(512) NOT NULL,
    course VARCHAR(1024) NOT NULL,
    attendance_year VARCHAR(50) NOT NULL DEFAULT '',
    program_incharge_id INT NOT NULL DEFAULT 0,
    logged_out_by_id INT NOT NULL,
    logged_out_by_name VARCHAR(255) NOT NULL,
    logged_out_at DATETIME NOT NULL,
    due_date DATE NOT NULL,
    returned_at DATETIME NULL,
    returned_by_id INT NULL,
    returned_by_name VARCHAR(255) NULL,
    checked_by_id INT NULL,
    checked_by_name VARCHAR(255) NULL,
    out_remarks TEXT NOT NULL,
    return_remarks TEXT NULL,
    UNIQUE KEY one_open_student (active_student_key),
    KEY due_returns (returned_at, due_date),
    KEY request_history (request_id),
    KEY student_history (student_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "Student record log table is ready. Existing records were preserved.\n";
