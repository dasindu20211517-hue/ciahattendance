<?php header('Location: index.php'); exit; ?>
<?php $pageTitle = 'Auto Sync Manager'; $activePage = 'auto_sync'; ?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

        <div class="sync-manager-container">
            <!-- Sync Status Panel -->
            <div class="sync-status-panel">
                <div class="panel-header">
                    <h3>🔄 Sync Status</h3>
                    <button class="btn btn-sm btn-secondary" onclick="refreshStatus()">Refresh</button>
                </div>
                <div class="status-grid">
                    <div class="status-item">
                        <span class="status-label">Status</span>
                        <span class="status-value" id="syncStatus">Loading...</span>
                    </div>
                    <div class="status-item">
                        <span class="status-label">Last Sync</span>
                        <span class="status-value" id="lastSync">Never</span>
                    </div>
                    <div class="status-item">
                        <span class="status-label">Next Sync</span>
                        <span class="status-value" id="nextSync">--:--</span>
                    </div>
                    <div class="status-item">
                        <span class="status-label">Device</span>
                        <span class="status-value" id="deviceStatus">Checking...</span>
                    </div>
                </div>
                <div class="status-message" id="statusMessage" style="display: none;"></div>
                <div class="sync-controls">
                    <button class="btn btn-primary" onclick="triggerSync()">
                        <span>⟳</span> Sync Now
                    </button>
                    <button class="btn btn-secondary" onclick="toggleAutoSync()">
                        <span id="toggleIcon">⏸</span> <span id="toggleText">Pause</span>
                    </button>
                </div>
            </div>

            <!-- Configuration Panel -->
            <div class="sync-config-panel">
                <div class="panel-header">
                    <h3>⚙️ Configuration</h3>
                </div>
                <form id="configForm" onsubmit="saveConfig(event)">
                    <div class="form-group">
                        <label for="intervalMinutes">Sync Interval (minutes)</label>
                        <input type="number" id="intervalMinutes" name="interval_minutes" min="1" max="1440" value="15">
                        <small>How often to check for new attendance records (1-1440 minutes)</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="retryAttempts">Retry Attempts</label>
                        <input type="number" id="retryAttempts" name="retry_attempts" min="1" max="10" value="3">
                        <small>Number of attempts if sync fails</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="retryDelay">Retry Delay (seconds)</label>
                        <input type="number" id="retryDelay" name="retry_delay_seconds" min="1" max="300" value="10">
                        <small>Wait time between retry attempts (1-300 seconds)</small>
                    </div>
                    
                    <div class="form-group checkbox">
                        <input type="checkbox" id="wifiCheck" name="wifi_check_enabled">
                        <label for="wifiCheck">Check WiFi Connectivity</label>
                        <small>Verify device is reachable before syncing</small>
                    </div>
                    
                    <div class="form-group checkbox">
                        <input type="checkbox" id="autoCleanup" name="auto_cleanup_enabled">
                        <label for="autoCleanup">Enable Auto Cleanup</label>
                        <small>Automatically delete old attendance records</small>
                    </div>
                    
                    <div class="form-group" id="cleanupDaysGroup" style="display: none;">
                        <label for="cleanupDays">Cleanup Records Older Than (days)</label>
                        <input type="number" id="cleanupDays" name="cleanup_days" min="7" max="730" value="90">
                        <small>Delete attendance records older than this many days</small>
                    </div>
                    
                    <div class="form-group checkbox">
                        <input type="checkbox" id="notifyOnError" name="notification_on_error">
                        <label for="notifyOnError">Notify on Error</label>
                        <small>Send notification if sync fails</small>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">💾 Save Configuration</button>
                </form>
            </div>

            <!-- Recent Activity -->
            <div class="sync-logs-panel">
                <div class="panel-header">
                    <h3>📋 Recent Activity</h3>
                    <button class="btn btn-sm btn-outline" onclick="clearLogs()">Clear Logs</button>
                </div>
                <div class="logs-container" id="recentLogs">
                    <p class="no-data">Loading logs...</p>
                </div>
            </div>
        </div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<style>
.sync-manager-container {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
    padding: 20px 0;
}

.sync-status-panel,
.sync-config-panel,
.sync-logs-panel {
    background: var(--card-bg);
    border-radius: var(--radius-lg);
    padding: 24px;
    border: 1px solid var(--border);
    box-shadow: var(--shadow-md);
}

.panel-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 12px;
    border-bottom: 1px solid var(--border);
}

.panel-header h3 {
    margin: 0;
    font-size: 16px;
    color: var(--text-primary);
}

.status-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 16px;
    margin-bottom: 20px;
}

.status-item {
    display: flex;
    flex-direction: column;
    gap: 6px;
    padding: 12px;
    background: var(--body-bg);
    border-radius: var(--radius);
    border: 1px solid var(--border);
}

.status-label {
    font-size: 12px;
    font-weight: 600;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.status-value {
    font-size: 14px;
    font-weight: 600;
    color: var(--primary);
}

.status-value.error {
    color: var(--danger);
}

.status-value.success {
    color: var(--success);
}

.status-message {
    padding: 12px;
    margin-bottom: 16px;
    border-radius: var(--radius);
    font-size: 13px;
    background: var(--info-light);
    color: var(--info);
}

.status-message.error {
    background: var(--danger-light);
    color: var(--danger);
}

.status-message.success {
    background: var(--success-light);
    color: var(--success);
}

.sync-controls {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}

.sync-controls .btn {
    flex: 1;
    min-width: 140px;
}

.form-group {
    margin-bottom: 16px;
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.form-group label {
    font-weight: 600;
    font-size: 13px;
    color: var(--text-primary);
}

.form-group input {
    padding: 9px 12px;
    border: 1.5px solid var(--border);
    border-radius: var(--radius);
    font-size: 13px;
    font-family: inherit;
    outline: none;
    transition: var(--transition);
}

.form-group input:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px var(--primary-50);
}

.form-group small {
    font-size: 11px;
    color: var(--text-muted);
    margin-top: -2px;
}

.form-group.checkbox {
    flex-direction: row;
    align-items: flex-start;
    gap: 8px;
}

.form-group.checkbox input {
    width: 18px;
    height: 18px;
    margin-top: 2px;
}

.form-group.checkbox label {
    margin: 0;
}

.sync-logs-panel {
    grid-column: 1 / -1;
}

.logs-container {
    font-family: 'Courier New', monospace;
    font-size: 12px;
    background: #1e1e1e;
    color: #d4d4d4;
    padding: 12px;
    border-radius: var(--radius);
    max-height: 300px;
    overflow-y: auto;
}

.logs-container .log-line {
    padding: 4px 0;
    line-height: 1.4;
}

.logs-container .log-line.info {
    color: #569cd6;
}

.logs-container .log-line.success {
    color: #6a9955;
}

.logs-container .log-line.error {
    color: #f48771;
}

.logs-container .no-data {
    color: #858585;
    font-style: italic;
}

@media (max-width: 1024px) {
    .sync-manager-container {
        grid-template-columns: 1fr;
    }
    
    .status-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .sync-manager-container {
        gap: 16px;
        padding: 12px 0;
    }
    
    .sync-status-panel,
    .sync-config-panel,
    .sync-logs-panel {
        padding: 16px;
    }
    
    .sync-controls .btn {
        width: 100%;
    }
    
    .logs-container {
        max-height: 200px;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Show/hide cleanup days based on auto cleanup checkbox
    const autoCleanupCheckbox = document.getElementById('autoCleanup');
    const cleanupDaysGroup = document.getElementById('cleanupDaysGroup');
    
    autoCleanupCheckbox.addEventListener('change', function() {
        cleanupDaysGroup.style.display = this.checked ? 'flex' : 'none';
    });
    
    // Load initial status and config
    refreshStatus();
    loadConfig();
    
    // Auto refresh status every 30 seconds
    setInterval(refreshStatus, 30000);
});

function refreshStatus() {
    fetch('api/sync_status.php?action=status')
        .then(r => r.json())
        .then(data => {
            if (!data.success) throw new Error(data.message);
            
            const status = data.data;
            const syncStatus = document.getElementById('syncStatus');
            const lastSync = document.getElementById('lastSync');
            const nextSync = document.getElementById('nextSync');
            const deviceStatus = document.getElementById('deviceStatus');
            const statusMessage = document.getElementById('statusMessage');
            
            // Update status
            const statusText = status.sync_in_progress ? '⟳ Syncing...' : 
                             status.last_sync_status === 'success' ? '✓ Success' :
                             status.last_sync_status === 'error' ? '✗ Error' : 'Pending';
            syncStatus.textContent = statusText;
            syncStatus.className = 'status-value ' + status.last_sync_status;
            
            // Update times
            lastSync.textContent = status.last_sync || 'Never';
            nextSync.textContent = status.next_sync_estimate || '--:--';
            
            // Update device status
            deviceStatus.textContent = status.device_reachable ? '🟢 Connected' : '🔴 Offline';
            deviceStatus.className = 'status-value ' + (status.device_reachable ? 'success' : 'error');
            
            // Update message if there's an error
            if (status.last_sync_message) {
                statusMessage.textContent = status.last_sync_message;
                statusMessage.className = 'status-message ' + status.last_sync_status;
                statusMessage.style.display = 'block';
            } else {
                statusMessage.style.display = 'none';
            }
            
            // Update toggle button
            const toggleIcon = document.getElementById('toggleIcon');
            const toggleText = document.getElementById('toggleText');
            if (status.enabled) {
                toggleIcon.textContent = '⏸';
                toggleText.textContent = 'Pause';
            } else {
                toggleIcon.textContent = '▶';
                toggleText.textContent = 'Resume';
            }
            
            // Update logs
            if (status.recent_logs && status.recent_logs.length) {
                const logsHtml = status.recent_logs.map(log => {
                    let level = 'info';
                    if (log.includes('[error]')) level = 'error';
                    if (log.includes('[success]')) level = 'success';
                    return `<div class="log-line ${level}">${escapeHtml(log)}</div>`;
                }).join('');
                document.getElementById('recentLogs').innerHTML = logsHtml;
            }
        })
        .catch(e => {
            console.error('Status refresh failed:', e);
            document.getElementById('syncStatus').textContent = '? Error';
        });
}

function loadConfig() {
    fetch('api/sync_status.php?action=config')
        .then(r => r.json())
        .then(data => {
            if (!data.success) throw new Error(data.message);
            
            const config = data.data;
            document.getElementById('intervalMinutes').value = config.interval_minutes;
            document.getElementById('retryAttempts').value = config.retry_attempts;
            document.getElementById('retryDelay').value = config.retry_delay_seconds;
            document.getElementById('wifiCheck').checked = config.wifi_check_enabled;
            document.getElementById('autoCleanup').checked = config.auto_cleanup_enabled;
            document.getElementById('cleanupDays').value = config.cleanup_days;
            document.getElementById('notifyOnError').checked = config.notification_on_error;
            
            // Update cleanup days visibility
            document.getElementById('cleanupDaysGroup').style.display = config.auto_cleanup_enabled ? 'flex' : 'none';
        })
        .catch(e => console.error('Config load failed:', e));
}

function saveConfig(e) {
    e.preventDefault();
    
    const formData = new FormData(document.getElementById('configForm'));
    const config = {
        interval_minutes: parseInt(formData.get('interval_minutes')),
        retry_attempts: parseInt(formData.get('retry_attempts')),
        retry_delay_seconds: parseInt(formData.get('retry_delay_seconds')),
        wifi_check_enabled: formData.get('wifi_check_enabled') ? true : false,
        auto_cleanup_enabled: formData.get('auto_cleanup_enabled') ? true : false,
        cleanup_days: parseInt(formData.get('cleanup_days')),
        notification_on_error: formData.get('notification_on_error') ? true : false,
    };
    
    fetch('api/sync_status.php?action=config', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(config)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('Configuration saved successfully', 'success');
            loadConfig();
        } else {
            showToast(data.message || 'Failed to save configuration', 'error');
        }
    })
    .catch(e => showToast('Error: ' + e.message, 'error'));
}

function triggerSync() {
    const btn = event.target.closest('.btn');
    btn.disabled = true;
    btn.textContent = '⟳ Syncing...';
    
    fetch('api/sync_status.php?action=sync_now', { method: 'POST' })
        .then(r => r.json())
        .then(data => {
            showToast(data.message || 'Sync triggered', data.success ? 'success' : 'error');
            setTimeout(refreshStatus, 500);
        })
        .catch(e => showToast('Error: ' + e.message, 'error'))
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = '<span>⟳</span> Sync Now';
        });
}

function toggleAutoSync() {
    fetch('api/sync_status.php?action=config')
        .then(r => r.json())
        .then(data => {
            const config = data.data;
            config.enabled = !config.enabled;
            
            return fetch('api/sync_status.php?action=config', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(config)
            });
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showToast(data.data.enabled ? 'Auto-sync enabled' : 'Auto-sync paused', 'success');
                refreshStatus();
            } else {
                showToast(data.message || 'Failed to toggle auto-sync', 'error');
            }
        })
        .catch(e => showToast('Error: ' + e.message, 'error'));
}

function clearLogs() {
    if (confirm('Clear all sync logs?')) {
        document.getElementById('recentLogs').innerHTML = '<p class="no-data">Logs cleared</p>';
        showToast('Logs cleared', 'info');
    }
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>
