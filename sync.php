<?php
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/includes/sync_service.php';

ob_end_clean();
header('Content-Type: application/json');

echo json_encode(runDeviceSync());
