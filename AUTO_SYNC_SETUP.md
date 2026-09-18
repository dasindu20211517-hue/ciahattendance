# Auto Sync Configuration Guide

## Overview
The Auto Sync system automatically retrieves attendance data from your ZKTeco fingerprint machine over WiFi at regular intervals.

## Features
✅ Automatic periodic synchronization  
✅ WiFi connectivity checking  
✅ Retry logic with configurable attempts  
✅ Real-time sync status monitoring  
✅ Auto-cleanup of old records  
✅ Comprehensive logging  
✅ Lock mechanism to prevent concurrent syncs  

## Web Interface
Access the Auto Sync Manager at: **http://yourserver/auto_sync.php**

The interface provides:
- Real-time sync status and device connectivity
- Last sync time and next estimated sync
- Manual sync trigger button
- Enable/disable auto-sync toggle
- Configuration panel for all settings
- Recent activity logs

## Setup Instructions

### Windows (using Task Scheduler)

#### 1. Create a Scheduled Task

**Open Task Scheduler:**
- Press `Windows + R`
- Type `taskschd.msc` and press Enter

**Create New Task:**
1. Right-click "Task Scheduler Library" → "Create Task..."
2. Name: `CIAH Auto Sync`
3. Description: `Automatically sync attendance from ZKTeco device`
4. Check "Run with highest privileges"

**Triggers Tab:**
1. Click "New..."
2. Begin the task: "On a schedule"
3. Repeat task every: **5 minutes**
4. For a duration of: **Indefinitely**
5. Click OK

**Actions Tab:**
1. Click "New..."
2. Action: "Start a program"
3. Program/script: `C:\xampp\php\php.exe`
4. Add arguments: `"C:\xampp\htdocs\CIAH Attendance\cron\auto_sync.php"`
5. Start in: `C:\xampp\htdocs\CIAH Attendance`
6. Click OK

**Conditions Tab:**
- Uncheck "Start the task only if the computer is on AC power"
- Check "Wake the computer to run this task" (optional)

**Settings Tab:**
- Check "Run task as soon as possible after a scheduled start is missed"
- Check "If the task fails, restart every: 1 minute"
- Retry count: 3
- Click OK

#### 2. Verify Setup
- Right-click the task → Run
- Check if last run status shows "The task completed with an exit code of (0)"
- Visit http://yourserver/auto_sync.php and check status

### Linux / macOS (using Cron)

#### 1. Open Crontab Editor
```bash
crontab -e
```

#### 2. Add Cron Job

**For every 5 minutes:**
```cron
*/5 * * * * /usr/bin/php /path/to/ciah/cron/auto_sync.php >> /path/to/ciah/logs/cron.log 2>&1
```

**For every 15 minutes:**
```cron
*/15 * * * * /usr/bin/php /path/to/ciah/cron/auto_sync.php >> /path/to/ciah/logs/cron.log 2>&1
```

**For every hour:**
```cron
0 * * * * /usr/bin/php /path/to/ciah/cron/auto_sync.php >> /path/to/ciah/logs/cron.log 2>&1
```

#### 3. Verify Setup
```bash
# Check if cron job is saved
crontab -l

# View logs
tail -f /path/to/ciah/logs/sync.log
tail -f /path/to/ciah/logs/cron.log
```

## Configuration Options

### Sync Interval (minutes)
- **Default:** 15 minutes
- **Range:** 1 - 1440 minutes
- How frequently to check for new attendance records

### Retry Attempts
- **Default:** 3 attempts
- **Range:** 1 - 10
- Number of times to retry if sync fails

### Retry Delay (seconds)
- **Default:** 10 seconds
- **Range:** 1 - 300 seconds
- Wait time between retry attempts

### WiFi Connectivity Check
- **Default:** Enabled
- Checks if device is reachable before attempting sync
- Reduces unnecessary connection attempts

### Auto Cleanup
- **Default:** Disabled
- Automatically delete old attendance records
- Configure how many days of history to keep

### Notification on Error
- **Default:** Enabled
- Send alerts if sync fails
- Requires notification system configuration

## API Endpoints

### Get Sync Status
```
GET /api/sync_status.php?action=status
```
Returns current sync status, device connectivity, and recent logs.

### Get/Update Configuration
```
GET /api/sync_status.php?action=config
POST /api/sync_status.php?action=config
```

**POST Body Example:**
```json
{
  "enabled": true,
  "interval_minutes": 15,
  "retry_attempts": 3,
  "retry_delay_seconds": 10,
  "wifi_check_enabled": true,
  "auto_cleanup_enabled": false,
  "cleanup_days": 90,
  "notification_on_error": true
}
```

### Trigger Immediate Sync
```
POST /api/sync_status.php?action=sync_now
```
Returns sync result with statistics.

### Cleanup Old Records
```
POST /api/sync_status.php?action=cleanup
```
Deletes attendance records older than configured days.

## Log Files

### Main Sync Log
- **Location:** `logs/sync.log`
- **Size limit:** 5 MB (auto-rotated)
- **Contains:** All sync attempts, results, and errors

### Cron Log (Linux/macOS)
- **Location:** `logs/cron.log`
- **Contains:** Cron job execution details

### Log Format
```
[2026-08-22 14:30:45] [success] Synced 45 users and 128 attendance records. Device time: 2026-08-22 14:30:12
[2026-08-22 14:25:30] [error] Cannot connect to ZKTeco device at 192.168.1.201:4370
[2026-08-22 14:15:12] [info] Sync attempt 1/3
```

## Troubleshooting

### Task/Cron Not Running
1. Verify PHP path is correct
2. Check file permissions on `cron/auto_sync.php` (must be readable)
3. Check if logs directory exists and is writable
4. Test manually: 
   ```bash
   php /path/to/crio/auto_sync.php
   ```

### Sync Fails with "Device not reachable"
1. Check device IP address in `config.php` (should be 192.168.1.201)
2. Verify device is connected to network
3. Ping device: `ping 192.168.1.201`
4. Check firewall rules allow port 4370

### No New Records Synced
1. Check device has attendance data
2. Verify `last_sync` time in status
3. Check device time matches server time
4. Review sync logs for errors
5. Try manual sync from web interface

### High CPU Usage
1. Reduce sync frequency (increase interval_minutes)
2. Check for sync process getting stuck
3. Review system logs for errors
4. Ensure PHP memory limit is sufficient (min 128MB)

### Database Locked
1. Stop sync process
2. Remove lock file: `rm logs/.sync.lock`
3. Restart sync

## Best Practices

1. **Sync Interval:** 
   - Set to 15-30 minutes for normal operation
   - Lower intervals increase server load
   - Higher intervals may miss records during peak hours

2. **Retry Settings:**
   - Keep at 3 attempts with 10-second delay
   - Adjust if experiencing network instability

3. **WiFi Connectivity Check:**
   - Keep enabled to prevent unnecessary connection attempts
   - Disable if experiencing false negatives

4. **Auto Cleanup:**
   - Enable after stabilizing system
   - Keep at least 90 days of history
   - Run cleanup during off-hours (configure in DB directly)

5. **Monitoring:**
   - Check status regularly from web interface
   - Review logs weekly for errors
   - Set up email alerts for persistent failures

## Performance Considerations

- **Sync Duration:** Typically 5-30 seconds depending on device data
- **Data Transfer:** ~10-50 KB per sync
- **Database Impact:** Minimal, uses efficient upsert logic
- **Server Load:** < 1% CPU during sync

## Security Notes

- Sync configuration stored in `.sync_config.json` (not exposed)
- API endpoints return only status/config info
- Sensitive device credentials stored in `config.php` only
- Lock files prevent concurrent access
- Logs contain no sensitive data

## Advanced

### Manual Sync Trigger
```php
require_once 'includes/sync_scheduler.php';
$scheduler = getSyncScheduler();
$result = $scheduler->executeSync();
var_dump($result);
```

### Get Scheduler Status
```php
require_once 'includes/sync_scheduler.php';
$scheduler = getSyncScheduler();
$status = $scheduler->getStatus();
```

### Check Connectivity
```php
require_once 'includes/sync_scheduler.php';
$scheduler = getSyncScheduler();
$reachable = $scheduler->checkDeviceConnectivity();
echo $reachable ? 'Device connected' : 'Device not reachable';
```

## Support

For issues or questions:
1. Check sync logs: `logs/sync.log`
2. Review configuration: `auto_sync.php` page
3. Test device connectivity from server
4. Verify cron/task scheduler status
5. Check PHP error logs

---
Last Updated: 2026-08-22
