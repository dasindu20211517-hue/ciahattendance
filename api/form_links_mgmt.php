<?php
/**
 * Form Links Management API
 * 
 * GET /api/form_links.php?action=list&type=leave
 * GET /api/form_links.php?action=generate&type=leave&user_id=123
 * POST /api/form_links.php?action=deactivate
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../auth.php';
requireLoginApi([2]);
require_once __DIR__ . '/../includes/form_manager.php';

try {
    $action = $_GET['action'] ?? 'list';
    $manager = getFormLinkManager();
    $db = getDB();
    
    if ($action === 'list') {
        $formType = $_GET['type'] ?? null;
        $userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
        
        if (!$formType || !in_array($formType, ['leave', 'overtime'])) {
            throw new Exception('Valid form type required');
        }
        
        $query = "SELECT 
            fl.id, fl.token, fl.form_type, fl.user_id, fl.created_at, fl.expires_at, fl.is_active,
            COALESCE(NULLIF(u.calling_name, ''), u.name) as employee_name,
            (SELECT COUNT(*) FROM form_submissions WHERE form_link_id = fl.id) as submission_count
        FROM form_links fl
        LEFT JOIN users u ON fl.user_id = u.id
        WHERE fl.form_type = ?";
        
        $params = [$formType];
        
        if ($userId) {
            $query .= " AND fl.user_id = ?";
            $params[] = $userId;
        }
        
        $query .= " ORDER BY fl.created_at DESC";
        
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $links = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Add URLs to each link
        $links = array_map(function($link) {
            $link['url'] = FormLinkManager::getFormURL($link['token']);
            return $link;
        }, $links);
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'links' => $links
        ]);
        
    } elseif ($action === 'generate') {
        $formType = $_GET['type'] ?? null;
        $userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
        $expireDays = isset($_GET['expire_days']) ? (int)$_GET['expire_days'] : 30;
        
        if (!$formType || !in_array($formType, ['leave', 'overtime'])) {
            throw new Exception('Valid form type required');
        }
        
        $token = $manager->generateFormLink($formType, $userId, null, $expireDays);
        $url = FormLinkManager::getFormURL($token);
        
        $whatsappSent = false;
        
        // Send notification to the user if userId is specified
        if ($userId) {
            $emp = $db->query("SELECT whatsapp_number, COALESCE(NULLIF(calling_name,''), name) as name FROM users WHERE id=" . (int)$userId)->fetch(PDO::FETCH_ASSOC);
            
            // Send via WhatsApp (only method)
            if ($emp && !empty($emp['whatsapp_number'])) {
                require_once __DIR__ . '/../config_whatsapp.php';
                require_once __DIR__ . '/../whatsapp_web_sender.php';
                
                $typeLabel = $formType === 'leave' ? 'Leave' : 'Overtime';
                $typeEmoji = $formType === 'leave' ? '🏖️' : '⏰';
                
                $whatsappMessage = "$typeEmoji *{$typeLabel} Request Form*\n";
                $whatsappMessage .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
                $whatsappMessage .= "Hi {$emp['name']}! 👋\n\n";
                $whatsappMessage .= "You have been invited to submit a *{$typeLabel} Request*.\n\n";
                $whatsappMessage .= "📋 *Please fill out the form using this link:*\n";
                $whatsappMessage .= "{$url}\n\n";
                $whatsappMessage .= "⏰ *Important:*\n";
                $whatsappMessage .= "• This link will expire in *{$expireDays} days*\n";
                $whatsappMessage .= "• Please submit your request as soon as possible\n";
                $whatsappMessage .= "• You will receive confirmation once submitted\n\n";
                $whatsappMessage .= "📱 *CIAH Attendance System*\n";
                $whatsappMessage .= "_Automated Form Link • " . date('Y-m-d H:i') . "_";
                
                try {
                    $whatsappResult = sendWhatsAppMessage($emp['whatsapp_number'], $whatsappMessage);
                    if ($whatsappResult) {
                        $whatsappSent = true;
                        
                        // Log WhatsApp notification
                        $stmt = $db->prepare("INSERT INTO notifications (user_id, type, channel, subject, message, status) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt->execute([
                            $userId,
                            'form_link',
                            'whatsapp',
                            "{$typeLabel} Form Link",
                            $whatsappMessage,
                            'sent'
                        ]);
                    }
                } catch (Exception $e) {
                    error_log('[WhatsApp Form Link] Failed to send: ' . $e->getMessage());
                }
            } else {
                error_log('[WhatsApp Form Link] No WhatsApp number found for user ID: ' . $userId);
            }
        }
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'token' => $token,
            'url' => $url,
            'form_type' => $formType,
            'expires_in_days' => $expireDays,
            'whatsapp_sent' => $whatsappSent,
            'notification_sent' => $userId ? $whatsappSent : false,
            'message' => $whatsappSent ? 'Form link generated and sent via WhatsApp' : 'Form link generated (WhatsApp number not available)'
        ]);
        
    } elseif ($action === 'deactivate') {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        $token = $data['token'] ?? null;
        
        if (!$token) {
            throw new Exception('Token is required');
        }
        
        if ($manager->deactivateFormLink($token)) {
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Form link deactivated successfully'
            ]);
        } else {
            throw new Exception('Failed to deactivate form link');
        }
        
    } else {
        throw new Exception('Unknown action: ' . $action);
    }
    
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
