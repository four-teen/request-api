<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
// An isolated render fixture, never an authenticated browser session.
ini_set('session.save_path', __DIR__ . '/../.runtime/sessions');
session_id('render-record-logs-' . bin2hex(random_bytes(12)));
session_start();
$_SESSION = ['username' => 'Render fixture', 'acc_id' => 0, 'fullname' => '<Fixture recorder>'];
session_write_close();
$_SERVER['PHP_SELF'] = '/administrator/record_logs.php';
// Only this CLI process uses a render principal; no browser session is authorized.
$GLOBALS['rrms_authenticated_user'] = ['id' => 0, 'username' => 'Render fixture', 'fullname' => '<Fixture recorder>', 'role' => 'administrator', 'permissions' => [], 'version' => 1];
ob_start();
require __DIR__ . '/../administrator/record_logs.php';
$html = ob_get_clean();
session_destroy();
foreach (['Student Record Logs', 'Find a student', 'Log out record', 'Record register', 'Checked by', 'Last attendance', '&lt;Fixture recorder&gt;'] as $text) {
    if (strpos($html, $text) === false) throw new RuntimeException('Page is missing expected content: ' . $text);
}
if (strpos($html, '<Fixture recorder>') !== false) throw new RuntimeException('Account display must be escaped.');
$document = new DOMDocument();
libxml_use_internal_errors(true);
$document->loadHTML($html);
$xpath = new DOMXPath($document);
$ids = [];
foreach ($xpath->query('//*[@id]') as $element) {
    $id = $element->getAttribute('id');
    if (isset($ids[$id])) throw new RuntimeException('Duplicate page ID: ' . $id);
    $ids[$id] = true;
}
if ($xpath->query('//table/thead/tr/th')->length !== 9) throw new RuntimeException('Register must contain all nine columns.');
if ($xpath->query('//select[@id="returnChecker"]/option')->length < 2) throw new RuntimeException('Program Incharge options were not loaded.');
if ($xpath->query('//a[@href="record_logs.php" and not(contains(@class,"collapsed"))]')->length !== 1) throw new RuntimeException('Sidebar link must be active.');
echo "PASS: authenticated page render, student search/form, nine register columns, Program Incharge options, unique element IDs, sidebar state, and escaped account display.\n";
