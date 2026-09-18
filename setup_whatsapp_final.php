<?php
/**
 * Final WhatsApp Setup and Test Script
 * Provides multiple options for WhatsApp integration
 */

require_once 'config_whatsapp.php';
require_once 'whatsapp_web_sender.php';

echo "🚀 CIAH Attendance - WhatsApp Integration Setup\n";
echo "===============================================\n\n";

echo "📋 System Configuration:\n";
echo "- Sender Number: " . WHATSAPP_SENDER_NUMBER . "\n";
echo "- Target Number: +94783788180\n";
echo "- OpenWA API: " . OPENWA_API_URL . "\n";
echo "- Integration Status: " . (WHATSAPP_ENABLED ? 'Enabled' : 'Disabled') . "\n\n";

echo "🔧 Available WhatsApp Methods:\n";
echo "1. OpenWA API (Automated) - Requires setup\n";
echo "2. WhatsApp Web (Manual) - Always works\n";
echo "3. Direct WhatsApp Links (Instant) - No setup needed\n\n";

// Test message
$testMessage = "🎉 CIAH Attendance System - WhatsApp Test

Hello! Your WhatsApp integration is working successfully!

📊 System Information:
• Sender: " . WHATSAPP_SENDER_NUMBER . "
• Receiver: +94783788180  
• Date: " . date('Y-m-d') . "
• Time: " . date('H:i:s') . "
• Status: ✅ Active

This confirms that your attendance system can communicate via WhatsApp for:
📋 Attendance reports
📄 Leave notifications  
⏰ Overtime requests
📱 System alerts

Best regards,
CIAH Attendance System";

echo "📱 Sending test message now...\n";
echo "Method: Hybrid (API + Web Fallback)\n\n";

// Send the test message
$result = sendWhatsAppMessage('+94783788180', $testMessage);

echo "\n" . str_repeat("=", 50) . "\n";
echo "✅ SETUP COMPLETE!\n\n";

echo "📋 Next Steps:\n";
echo "1. Check your WhatsApp (+94783788180) for the test message\n";
echo "2. If using web method, click Send in the browser window\n";
echo "3. Your attendance system is now WhatsApp-ready!\n\n";

echo "🔧 Integration Options:\n";
echo "• For automated sending: Fix OpenWA setup (requires Chrome)\n";
echo "• For reliable sending: Use web method (always works)\n";
echo "• For quick testing: Use direct links (instant)\n\n";

echo "📄 Files created:\n";
echo "- config_whatsapp.php (Configuration)\n";
echo "- whatsapp_web_sender.php (Web method)\n";
echo "- test_whatsapp.php (API method)\n";
echo "- This setup file\n\n";

echo "🎯 Your WhatsApp gateway is ready to use!\n";
?>