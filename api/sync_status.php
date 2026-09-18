<?php
require_once __DIR__ . '/../auth.php';
requireLoginApi([2]);
/**
 * Sync Management API
 * 
 * Endpoints:
 * - GET /api/sync_status.php - Get current sync status
 * - POST /api/sync_status.php?action=config - Get/set configuration
 * - POST /api/sync_status.php?action=sync_now - Trigger immediate sync
 * - POST /api/sync_status.php?action=cleanup - Cleanup old records
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/sync_scheduler.php';

try {
    $action = $_GET['action'] ?? 'status';
    $scheduler = getSyncScheduler();
    
    switch ($action) {
        case 'status':
            // Get current sync status
            $response = [
                'success' => true,
                'data' => $scheduler->getStatus()
            ];
            break;
            
        case 'config':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                // Update configuration
                $body = json_decode(file_get_contents('php://input'), true);
                
                // Validate configuration
                $allowed_keys = ['enabled', 'interval_minutes', 'retry_attempts', 'retry_delay_seconds', 
                                'wifi_check_enabled', 'notification_on_error', 'auto_cleanup_enabled', 'cleanup_days'];
                $config_update = array_intersect_key($body, array_flip($allowed_keys));
                
                // Validate values
                if (isset($config_update['interval_minutes'])) {
                    $config_update['interval_minutes'] = max(1, min(1440, (int)$config_update['interval_minutes']));
                }
                if (isset($config_update['retry_attempts'])) {
                    $config_update['retry_attempts'] = max(1, min(10, (int)$config_update['retry_attempts']));
                }
                if (isset($config_update['retry_delay_seconds'])) {
                    $config_update['retry_delay_seconds'] = max(1, min(300, (int)$config_update['retry_delay_seconds']));
                }
                
                if ($scheduler->saveConfig($config_update)) {
                    $response = [
                        'success' => true,
                        'message' => 'Configuration updated',
                        'data' => $scheduler->getConfig()
                    ];
                } else {
                    throw new Exception('Failed to save configuration');
                }
            } else {
                // Get current configuration
                $response = [
                    'success' => true,
                    'data' => $scheduler->getConfig()
                ];
            }
            break;
            
        case 'sync_now':
            // Trigger immediate sync
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $result = $scheduler->executeSync();
                $response = [
                    'success' => $result['success'],
                    'message' => $result['message'] ?? 'Sync executed',
                    'data' => $result['stats'] ?? null
                ];
            } else {
                throw new Exception('POST method required');
            }
            break;
            
        case 'cleanup':
            // Cleanup old records
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $result = $scheduler->cleanupOldRecords();
                $response = [
                    'success' => $result['success'],
                    'message' => $result['message'],
                    'data' => ['deleted_count' => $result['deleted_count'] ?? 0]
                ];
            } else {
                throw new Exception('POST method required');
            }
            break;
            
        default:
            throw new Exception('Unknown action: ' . $action);
    }
    
} catch (Throwable $e) {
    $response = [
        'success' => false,
        'message' => $e->getMessage()
    ];
    http_response_code(400);
}

echo json_encode($response, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
