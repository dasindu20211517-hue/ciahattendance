<?php
/**
 * Auto Sync Cron Job
 * 
 * This script should be executed by the system cron every 5-10 minutes
 * 
 * For Windows Task Scheduler:
 *   C:\xampp\php\php.exe "C:\xampp\htdocs\CIAH Attendance\cron\auto_sync.php"
 * 
 * For Linux/macOS Cron:
 *   */5 * * * * /usr/bin/php /path/to/ciah/cron/auto_sync.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/../includes/sync_scheduler.php';

$scheduler = getSyncScheduler();

// Check if sync should run
if (!$scheduler->shouldSync()) {
    exit(0); // Not yet time to sync
}

// Execute sync
$result = $scheduler->executeSync();

// Exit with appropriate code
exit($result['success'] ? 0 : 1);

