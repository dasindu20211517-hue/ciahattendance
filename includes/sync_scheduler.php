<?php
/**
 * Sync Scheduler - Manages automatic device synchronization over WiFi
 * 
 * Features:
 * - Periodic auto-sync with configurable intervals
 * - WiFi connectivity checks
 * - Sync status tracking
 * - Error logging and recovery
 * - Background worker support
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/sync_service.php';

class SyncScheduler {
    private $db;
    private $configFile;
    private $logFile;
    private $lockFile;
    
    public function __construct() {
        $this->db = getDB();
        $this->configFile = __DIR__ . '/../.sync_config.json';
        $this->logFile = __DIR__ . '/../logs/sync.log';
        $this->lockFile = __DIR__ . '/../logs/.sync.lock';
        
        // Ensure logs directory exists
        if (!is_dir(__DIR__ . '/../logs')) {
            mkdir(__DIR__ . '/../logs', 0755, true);
        }
    }
    
    /**
     * Get current sync configuration
     */
    public function getConfig(): array {
        $default = [
            'enabled' => true,
            'interval_minutes' => 15,
            'retry_attempts' => 3,
            'retry_delay_seconds' => 10,
            'max_records_per_sync' => 10000,
            'last_sync' => null,
            'last_sync_status' => 'pending',
            'last_sync_message' => '',
            'auto_cleanup_enabled' => false,
            'cleanup_days' => 90,
            'wifi_check_enabled' => true,
            'notification_on_error' => true,
        ];
        
        if (file_exists($this->configFile)) {
            $stored = json_decode(file_get_contents($this->configFile), true) ?? [];
            return array_merge($default, $stored);
        }
        
        return $default;
    }
    
    /**
     * Save sync configuration
     */
    public function saveConfig(array $config): bool {
        $existing = $this->getConfig();
        $merged = array_merge($existing, $config);
        return file_put_contents($this->configFile, json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) !== false;
    }
    
    /**
     * Check WiFi/network connectivity to device
     */
    public function checkDeviceConnectivity(): bool {
        if (!extension_loaded('sockets')) {
            return true; // Can't check, assume connected
        }
        
        $socket = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if ($socket === false) return false;
        
        socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, ['sec' => 2, 'usec' => 0]);
        $result = @socket_connect($socket, ZK_IP, ZK_PORT);
        @socket_close($socket);
        
        return $result === true;
    }
    
    /**
     * Execute device sync with retry logic
     */
    public function executeSync(): array {
        $config = $this->getConfig();
        
        // Check if sync is enabled
        if (!$config['enabled']) {
            return [
                'success' => false,
                'message' => 'Auto-sync is disabled',
                'skipped' => true
            ];
        }
        
        // Check if another sync is running
        if ($this->isLocked()) {
            return [
                'success' => false,
                'message' => 'Another sync is already in progress',
                'skipped' => true
            ];
        }
        
        // Check WiFi connectivity
        if ($config['wifi_check_enabled'] && !$this->checkDeviceConnectivity()) {
            $this->logSync('error', 'Device not reachable over network');
            return [
                'success' => false,
                'message' => 'Device not reachable over WiFi'
            ];
        }
        
        $this->lock();
        
        try {
            $result = null;
            
            // Retry logic
            for ($attempt = 1; $attempt <= $config['retry_attempts']; $attempt++) {
                $this->logSync('info', "Sync attempt {$attempt}/{$config['retry_attempts']}");
                
                $result = runDeviceSync();
                
                if ($result['success']) {
                    break;
                }
                
                if ($attempt < $config['retry_attempts']) {
                    sleep($config['retry_delay_seconds']);
                }
            }
            
            // Update config with sync result
            $config['last_sync'] = date('Y-m-d H:i:s');
            $config['last_sync_status'] = $result['success'] ? 'success' : 'error';
            $config['last_sync_message'] = $result['message'] ?? 'Unknown error';
            $this->saveConfig($config);
            
            // Log the sync
            $this->logSync(
                $result['success'] ? 'success' : 'error',
                $result['message'] ?? 'Unknown error',
                $result['stats'] ?? []
            );
            
            return $result;
            
        } finally {
            $this->unlock();
        }
    }
    
    /**
     * Check if sync should run based on interval
     */
    public function shouldSync(): bool {
        $config = $this->getConfig();
        
        if (!$config['enabled']) return false;
        
        $lastSync = $config['last_sync'] ? strtotime($config['last_sync']) : 0;
        $interval = $config['interval_minutes'] * 60;
        $now = time();
        
        return ($now - $lastSync) >= $interval;
    }
    
    /**
     * Get sync status for monitoring
     */
    public function getStatus(): array {
        $config = $this->getConfig();
        $isLocked = $this->isLocked();
        $recentLogs = $this->getRecentLogs(10);
        
        return [
            'enabled' => $config['enabled'],
            'interval_minutes' => $config['interval_minutes'],
            'last_sync' => $config['last_sync'],
            'last_sync_status' => $config['last_sync_status'],
            'last_sync_message' => $config['last_sync_message'],
            'sync_in_progress' => $isLocked,
            'next_sync_estimate' => $this->getNextSyncTime(),
            'should_sync_now' => $this->shouldSync(),
            'device_reachable' => $this->checkDeviceConnectivity(),
            'recent_logs' => $recentLogs,
        ];
    }
    
    /**
     * Lock sync (prevent concurrent syncs)
     */
    private function lock(): void {
        file_put_contents($this->lockFile, time());
    }
    
    /**
     * Unlock sync
     */
    private function unlock(): void {
        if (file_exists($this->lockFile)) {
            unlink($this->lockFile);
        }
    }
    
    /**
     * Check if sync is locked
     */
    private function isLocked(): bool {
        if (!file_exists($this->lockFile)) return false;
        
        $lockTime = (int)file_get_contents($this->lockFile);
        $lockAge = time() - $lockTime;
        
        // Consider lock stale after 30 minutes
        if ($lockAge > 1800) {
            $this->unlock();
            return false;
        }
        
        return true;
    }
    
    /**
     * Log sync event
     */
    private function logSync(string $level, string $message, array $stats = []): void {
        $timestamp = date('Y-m-d H:i:s');
        $statsJson = !empty($stats) ? ' | ' . json_encode($stats) : '';
        $logLine = "[{$timestamp}] [{$level}] {$message}{$statsJson}\n";
        
        file_put_contents($this->logFile, $logLine, FILE_APPEND | LOCK_EX);
        
        // Keep log file size reasonable (max 5MB)
        if (filesize($this->logFile) > 5242880) {
            $logs = file($this->logFile);
            $logs = array_slice($logs, -5000); // Keep last 5000 lines
            file_put_contents($this->logFile, implode('', $logs));
        }
    }
    
    /**
     * Get recent log entries
     */
    private function getRecentLogs(int $count = 10): array {
        if (!file_exists($this->logFile)) return [];
        
        $logs = file($this->logFile);
        $recent = array_slice($logs, -$count);
        
        return array_map(function($line) {
            return trim($line);
        }, $recent);
    }
    
    /**
     * Estimate next sync time
     */
    private function getNextSyncTime(): ?string {
        $config = $this->getConfig();
        $lastSync = $config['last_sync'] ? strtotime($config['last_sync']) : 0;
        $nextSync = $lastSync + ($config['interval_minutes'] * 60);
        
        return $nextSync > time() ? date('Y-m-d H:i:s', $nextSync) : 'Now';
    }
    
    /**
     * Cleanup old attendance records
     */
    public function cleanupOldRecords(): array {
        $config = $this->getConfig();
        
        if (!$config['auto_cleanup_enabled']) {
            return ['success' => false, 'message' => 'Auto-cleanup is disabled'];
        }
        
        try {
            $beforeDate = date('Y-m-d', strtotime("-{$config['cleanup_days']} days"));
            
            $stmt = $this->db->prepare("
                DELETE FROM attendance 
                WHERE check_time < ? 
                AND user_id IN (SELECT id FROM users)
            ");
            $stmt->execute([$beforeDate . ' 00:00:00']);
            
            $deleted = $stmt->rowCount();
            
            $this->logSync('info', "Cleaned up {$deleted} old attendance records");
            
            return [
                'success' => true,
                'message' => "Deleted {$deleted} records older than {$beforeDate}",
                'deleted_count' => $deleted
            ];
        } catch (Throwable $e) {
            $this->logSync('error', 'Cleanup failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Cleanup error: ' . $e->getMessage()
            ];
        }
    }
}

// Helper function for quick access
function getSyncScheduler(): SyncScheduler {
    static $scheduler = null;
    if ($scheduler === null) {
        $scheduler = new SyncScheduler();
    }
    return $scheduler;
}
