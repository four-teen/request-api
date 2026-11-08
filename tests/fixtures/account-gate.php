<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
// This principal exists only in a CLI render/denial process. Its nonexistent ID
// cannot authenticate through the real application, even with its fixture cookie.
$file = basename($argv[1] ?? '');
if (!in_array($file, ['manage_accounts.php','query_accounts.php','manage_programs.php','query_programs.php','manage_assigned_faculty.php','query_assigned.php',
    'query_request.php','query_record_logs.php','index.php','manage_request.php','record_logs.php','document_type.php','statistics_summary.php','print_summary.php','printed_summary.php'], true)) exit(1);
$options = json_decode($argv[2] ?? '{}', true, 512, JSON_THROW_ON_ERROR);
ini_set('session.save_path', __DIR__ . '/../../.runtime/sessions');
session_id('gate-' . bin2hex(random_bytes(10)));
session_start();
$_SESSION = ['username'=>'Gate fixture','acc_id'=>900001,'auth_version'=>1,'csrf_token'=>'fixture-token'];
session_write_close();
$GLOBALS['rrms_authenticated_user'] = ['id'=>900001,'username'=>'Gate fixture','fullname'=>'Gate fixture','version'=>1,
    'role'=>$options['role'] ?? 'user','permissions'=>$options['permissions'] ?? []];
$_SERVER['REQUEST_METHOD'] = $options['method'] ?? 'GET';
$_SERVER['SCRIPT_NAME'] = '/administrator/' . $file;
$_SERVER['PHP_SELF'] = $_SERVER['SCRIPT_NAME'];
$_SERVER['HTTP_ACCEPT'] = 'application/json';
$_POST = $options['post'] ?? [];
if ($options['csrf'] ?? true) $_POST['csrf_token'] = 'fixture-token';
register_shutdown_function(function () { echo "\nGATE_STATUS:" . http_response_code(); if (session_status() === PHP_SESSION_ACTIVE) session_destroy(); });
chdir(__DIR__ . '/../../administrator');
require $file;
