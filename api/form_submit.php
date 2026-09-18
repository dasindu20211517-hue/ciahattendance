<?php
/**
 * Form Submission API
 * 
 * POST /api/form_submit.php?token=xxx
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/form_manager.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('POST method required');
    }
    
    $token = $_GET['token'] ?? null;
    if (!$token) {
        throw new Exception('Form token is required');
    }
    
    // Get form data from request
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        throw new Exception('Invalid form data');
    }
    
    // Get client info
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    
    // Submit form
    $manager = getFormLinkManager();
    $result = $manager->submitForm($token, $data, $ipAddress, $userAgent);
    
    if ($result['success']) {
        http_response_code(200);
    } else {
        http_response_code(400);
    }
    
    echo json_encode($result, JSON_UNESCAPED_SLASHES);
    
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
