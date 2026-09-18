#!/bin/bash

################################################################################
# Auto Sync Setup Script for Linux/macOS
################################################################################
# This script helps set up the cron job for automatic attendance 
# synchronization on Unix-like systems
################################################################################

set -e

echo ""
echo "========================================"
echo " CIAH Auto Sync - Linux/macOS Setup"
echo "========================================"
echo ""

# Get script directory
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
PHP_CMD=$(command -v php || echo "php")
SYNC_SCRIPT="$SCRIPT_DIR/cron/auto_sync.php"
LOG_DIR="$SCRIPT_DIR/logs"
CRON_LOG="$LOG_DIR/cron.log"

echo "Detected paths:"
echo "  CIAH Directory: $SCRIPT_DIR"
echo "  PHP Command: $PHP_CMD"
echo "  Sync Script: $SYNC_SCRIPT"
echo "  Log Directory: $LOG_DIR"
echo ""

# Verify PHP is available
if ! command -v $PHP_CMD &> /dev/null; then
    echo "ERROR: PHP not found in PATH"
    echo ""
    echo "Please ensure PHP is installed and in your PATH, then run this script again."
    exit 1
fi

# Verify sync script exists
if [ ! -f "$SYNC_SCRIPT" ]; then
    echo "ERROR: Sync script not found at $SYNC_SCRIPT"
    exit 1
fi

# Create logs directory
mkdir -p "$LOG_DIR"
chmod 755 "$LOG_DIR"

# Make script executable
chmod +x "$SYNC_SCRIPT"

echo "Created/verified logs directory..."
echo ""

# Choose sync interval
echo "Select sync interval:"
echo "  1) Every 5 minutes (most frequent)"
echo "  2) Every 15 minutes (recommended)"
echo "  3) Every 30 minutes"
echo "  4) Every hour"
echo ""
read -p "Enter choice (1-4) [default: 2]: " interval_choice
interval_choice=${interval_choice:-2}

case $interval_choice in
    1) cron_pattern="*/5" interval_name="5 minutes" ;;
    2) cron_pattern="*/15" interval_name="15 minutes" ;;
    3) cron_pattern="*/30" interval_name="30 minutes" ;;
    4) cron_pattern="0" interval_name="1 hour" ;;
    *) echo "Invalid choice, using default (15 minutes)"; cron_pattern="*/15" interval_name="15 minutes" ;;
esac

# Build cron job command
CRON_CMD="$cron_pattern * * * * $PHP_CMD $SYNC_SCRIPT >> $CRON_LOG 2>&1"

echo ""
echo "Setting up cron job to run every $interval_name..."
echo "Command: $CRON_CMD"
echo ""

# Check if cron job already exists
TEMP_CRON=$(mktemp)
crontab -l > "$TEMP_CRON" 2>/dev/null || true

if grep -q "$SYNC_SCRIPT" "$TEMP_CRON"; then
    echo "Warning: A cron job for this script already exists"
    read -p "Replace it? (y/N): " replace_choice
    if [ "$replace_choice" != "y" ] && [ "$replace_choice" != "Y" ]; then
        echo "Cancelled. Existing cron job not modified."
        rm "$TEMP_CRON"
        exit 0
    fi
    # Remove existing job
    grep -v "$SYNC_SCRIPT" "$TEMP_CRON" > "$TEMP_CRON.new" || true
    mv "$TEMP_CRON.new" "$TEMP_CRON"
fi

# Add new cron job
echo "$CRON_CMD" >> "$TEMP_CRON"

# Install cron job
crontab "$TEMP_CRON"
rm "$TEMP_CRON"

echo ""
echo "SUCCESS: Cron job installed!"
echo ""
echo "Cron Job Details:"
echo "  Schedule: Every $interval_name"
echo "  Command: $CRON_CMD"
echo "  Log: $CRON_LOG"
echo ""

# Display installed cron job
echo "Installed cron job:"
echo "---"
crontab -l | grep "$SYNC_SCRIPT" || echo "(Not found)"
echo "---"
echo ""

# Test the script
echo "Testing script execution..."
if $PHP_CMD "$SYNC_SCRIPT" >/dev/null 2>&1; then
    echo "✓ Test PASSED - Script executed successfully"
else
    TEST_EXIT=$?
    if [ $TEST_EXIT -eq 0 ]; then
        echo "✓ Test PASSED - Script executed successfully"
    else
        echo "⚠ Test returned exit code $TEST_EXIT"
        echo "  This may be normal if device is not reachable"
    fi
fi

echo ""
echo "========================================"
echo " Setup Complete"
echo "========================================"
echo ""
echo "Next steps:"
echo "1. Monitor sync status: http://localhost/auto_sync.php"
echo "2. View cron logs: tail -f $CRON_LOG"
echo "3. View sync logs: tail -f $LOG_DIR/sync.log"
echo ""
echo "To remove the cron job later, run: crontab -e"
echo "and delete the CIAH Auto Sync line"
echo ""

exit 0
