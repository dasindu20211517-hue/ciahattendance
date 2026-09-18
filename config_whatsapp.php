<?php
/**
 * WhatsApp Integration Configuration
 * Configure this for OpenWA integration
 */

// WhatsApp Sender Configuration
define('WHATSAPP_SENDER_NUMBER', '+94753788180');
define('WHATSAPP_ENABLED', true);

/**
 * OpenWA Integration Settings
 * Update these based on your OpenWA setup
 */
define('OPENWA_API_URL', 'http://localhost:8081'); // OpenWA API URL (changed from 8080 due to port conflict)
define('OPENWA_SESSION_ID', 'default'); // OpenWA session ID

/**
 * Send WhatsApp message via OpenWA
 * 
 * @param string $toNumber WhatsApp number (with country code)
 * @param string $message Message to send
 * @return bool Success status
 */
function sendWhatsAppViaOpenWA($toNumber, $message) {
    if (!WHATSAPP_ENABLED) {
        error_log('[WhatsApp] WhatsApp integration disabled');
        return false;
    }
    
    $cleanNumber = preg_replace('/[^0-9]/', '', $toNumber);
    if (substr($cleanNumber, 0, 2) !== '94') {
        $cleanNumber = '94' . ltrim($cleanNumber, '0');
    }
    $chatId = $cleanNumber . '@c.us';
    
    $payload = [
        'chatId' => $chatId,
        'content' => $message,
        'options' => [
            'linkPreview' => false
        ]
    ];
    
    $url = OPENWA_API_URL . '/' . OPENWA_SESSION_ID . '/sendText';
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        error_log('[WhatsApp] cURL error: ' . $error);
        return false;
    }
    
    if ($httpCode !== 200) {
        error_log('[WhatsApp] HTTP error: ' . $httpCode . ' - ' . $response);
        return false;
    }
    
    $result = json_decode($response, true);
    if (isset($result['success']) && $result['success']) {
        error_log('[WhatsApp] Message sent successfully to ' . $toNumber);
        return true;
    } else {
        error_log('[WhatsApp] Failed to send message: ' . $response);
        return false;
    }
}

/**
 * Format WhatsApp number for Sri Lanka
 * 
 * @param string $number Phone number
 * @return string Formatted number
 */
function formatWhatsAppNumber($number) {
    $clean = preg_replace('/[^0-9+]/', '', $number);
    
    // Remove leading + if present
    if (substr($clean, 0, 1) === '+') {
        $clean = substr($clean, 1);
    }
    
    // Add country code if not present
    if (substr($clean, 0, 2) !== '94') {
        $clean = '94' . ltrim($clean, '0');
    }
    
    return '+' . $clean;
}