<?php
/**
 * Check OpenWA Status and Connection
 */

echo "OpenWA Status Check\n";
echo "==================\n\n";

// Check if OpenWA is responding
$baseUrl = 'http://localhost:8081';
$sessionId = 'default';

// Test basic health check
$healthUrl = "$baseUrl/health";
$ch = curl_init($healthUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 3,
    CURLOPT_CONNECTTIMEOUT => 3,
    CURLOPT_SSL_VERIFYPEER => false,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "Health Check:\n";
if ($error) {
    echo "❌ Connection Error: $error\n";
} else {
    echo "✅ HTTP Status: $httpCode\n";
    if ($httpCode === 200) {
        echo "Response: $response\n";
    }
}

// Check session status
echo "\nSession Status Check:\n";
$sessionUrl = "$baseUrl/$sessionId/getSessionInfo";
$ch = curl_init($sessionUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 3,
    CURLOPT_CONNECTTIMEOUT => 3,
    CURLOPT_SSL_VERIFYPEER => false,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    echo "❌ Session Error: $error\n";
} else {
    echo "Session HTTP Status: $httpCode\n";
    if ($httpCode === 200) {
        echo "✅ Session is active!\n";
        echo "Response: $response\n";
    } else {
        echo "⏳ Session not ready yet (waiting for QR scan)\n";
        echo "Response: $response\n";
    }
}

echo "\nNext Steps:\n";
echo "1. Check the OpenWA console window for QR code\n";
echo "2. Open WhatsApp on your phone\n";
echo "3. Go to Settings > Linked Devices\n";
echo "4. Tap 'Link a Device'\n";
echo "5. Scan the QR code shown in the console\n";
echo "6. Wait for connection confirmation\n";
echo "7. Run this script again or test_whatsapp.php\n";

?>