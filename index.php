<?php
require_once __DIR__ . '/config.php';
header('Location: ' . (getenv('REQUEST_WEB_URL') ?: '/'));
exit;
