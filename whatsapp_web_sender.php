<?php
/**
 * Alternative WhatsApp Sender - Opens WhatsApp Web for manual sending
 * This is more reliable when OpenWA has issues
 */

// Include the original WhatsApp config for fallback
require_once 'config_whatsapp.php';

/**
 * Send WhatsApp message via Web interface (manual approach)
 * Opens WhatsApp Web with pre-filled message
 */
function sendWhatsAppViaWeb($toNumber, $message) {
    $cleanNumber = preg_replace('/[^0-9]/', '', $toNumber);
    if (substr($cleanNumber, 0, 2) !== '94') {
        $cleanNumber = '94' . ltrim($cleanNumber, '0');
    }
    
    $encodedMessage = urlencode($message);
    $whatsappUrl = "https://web.whatsapp.com/send?phone={$cleanNumber}&text={$encodedMessage}";
    
    // For Windows, open in default browser
    $command = "start \"\" \"$whatsappUrl\"";
    exec($command);
    
    return true;
}

/**
 * Enhanced WhatsApp sender with fallback options
 */
function sendWhatsAppMessage($toNumber, $message) {
    echo "Attempting to send WhatsApp message...\n";
    echo "To: $toNumber\n";
    echo "Message: $message\n\n";
    
    // Try OpenWA first if available
    if (function_exists('curl_init')) {
        echo "Trying OpenWA API...\n";
        $result = sendWhatsAppViaOpenWA($toNumber, $message);
        if ($result) {
            echo "✅ SUCCESS: Message sent via OpenWA API!\n";
            return true;
        }
        echo "❌ OpenWA API failed, trying web interface...\n\n";
    }
    
    // Fallback to web interface
    echo "Opening WhatsApp Web interface...\n";
    sendWhatsAppViaWeb($toNumber, $message);
    echo "✅ WhatsApp Web opened with pre-filled message.\n";
    echo "📱 Complete the sending manually in the browser.\n";
    
    return true;
}

// Test the system
if (basename(__FILE__) == basename($_SERVER['SCRIPT_NAME'])) {
    $testNumber = '+94783788180';
    $testMessage = 'Hello! This is a test message from the CIAH Attendance System. 📱✅

The WhatsApp gateway is working! This message was sent from the attendance system.

System details:
- Sender: +94753788180
- Time: ' . date('Y-m-d H:i:s') . '
- Status: Test Successful ✅';

    echo "WhatsApp Web Sender Test\n";
    echo "========================\n\n";
    
    sendWhatsAppMessage($testNumber, $testMessage);
}
?>